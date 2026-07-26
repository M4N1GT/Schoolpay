<?php

namespace App\Tests\Service;

use App\Entity\SchoolClass;
use App\Entity\SchoolYear;
use App\Entity\Student;
use App\Repository\StudentRepository;
use App\Service\CsvImportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Import CSV des eleves (cahier des charges, section 10).
 *
 * Chaque test s execute dans une transaction annulee en fin de test ; les
 * fichiers temporaires sont supprimes dans tearDown.
 */
final class CsvImportServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CsvImportService $import;
    private StudentRepository $students;
    private string $token;
    /** @var string[] */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->import = self::getContainer()->get(CsvImportService::class);
        $this->students = self::getContainer()->get(StudentRepository::class);
        $this->em->getConnection()->beginTransaction();
        $this->token = strtoupper(substr(uniqid(), -8));
        $this->createReferential();
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        $connection = $this->em->getConnection();
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }

        parent::tearDown();
    }

    public function testValidRowsAreImported(): void
    {
        $result = $this->importCsv([
            ['matricule', 'prenom', 'nom', 'sexe', 'date_naissance', 'classe'],
            [$this->token . '-1', 'Miora', 'Rakoto', 'F', '12/04/2014', $this->className()],
            [$this->token . '-2', 'Tahina', 'Rakoto', 'M', '02/11/2013', $this->className()],
        ]);

        self::assertSame(2, $result->imported());
        self::assertSame(0, $result->rejected());
        self::assertFalse($result->hasErrors());

        $student = $this->students->findOneBy(['registrationNumber' => $this->token . '-1']);
        self::assertInstanceOf(Student::class, $student);
        self::assertSame('12/04/2014', $student->getBirthDate()?->format('d/m/Y'));
    }

    /**
     * Une ligne fautive ne doit pas empecher les lignes saines d entrer :
     * l import est partiel et rapporte le detail.
     */
    public function testInvalidRowsAreReportedWithoutBlockingTheValidOnes(): void
    {
        $result = $this->importCsv([
            ['matricule', 'prenom', 'nom', 'sexe', 'date_naissance', 'classe'],
            [$this->token . '-1', 'Miora', 'Rakoto', 'F', '12/04/2014', $this->className()],
            ['', 'Sans', 'Matricule', 'M', '', $this->className()],
            [$this->token . '-3', 'Classe', 'Inconnue', 'M', '', 'Classe qui n existe pas'],
            [$this->token . '-4', 'Date', 'Invalide', 'M', '32/13/2014', $this->className()],
        ]);

        self::assertSame(1, $result->imported());
        self::assertSame(3, $result->rejected());
        self::assertCount(3, $result->errors());

        self::assertSame([3, 4, 5], array_column($result->errors(), 'line'));
    }

    public function testDuplicateInsideFileIsRejected(): void
    {
        $result = $this->importCsv([
            ['matricule', 'prenom', 'nom', 'sexe', 'date_naissance', 'classe'],
            [$this->token . '-1', 'Premier', 'Eleve', 'F', '', $this->className()],
            [$this->token . '-1', 'Doublon', 'Eleve', 'M', '', $this->className()],
        ]);

        self::assertSame(1, $result->imported());
        self::assertStringContainsString('double dans le fichier', $result->errors()[0]['message']);
    }

    public function testDuplicateAlreadyInDatabaseIsRejected(): void
    {
        $this->importCsv([
            ['matricule', 'prenom', 'nom', 'sexe', 'date_naissance', 'classe'],
            [$this->token . '-1', 'Premier', 'Eleve', 'F', '', $this->className()],
        ]);

        $second = $this->importCsv([
            ['matricule', 'prenom', 'nom', 'sexe', 'date_naissance', 'classe'],
            [$this->token . '-1', 'Deuxieme', 'Tentative', 'M', '', $this->className()],
        ]);

        self::assertSame(0, $second->imported());
        self::assertStringContainsString('deja present en base', $second->errors()[0]['message']);
    }

    public function testMissingColumnAbortsTheImport(): void
    {
        $result = $this->importCsv([
            ['matricule', 'prenom', 'nom'],
            [$this->token . '-1', 'Miora', 'Rakoto'],
        ]);

        self::assertSame(0, $result->imported());
        self::assertNull($result->errors()[0]['line'], 'Une erreur d en-tete porte sur le fichier, pas sur une ligne.');
        self::assertStringContainsString('Colonnes manquantes', $result->errors()[0]['message']);
    }

    /**
     * Excel prefixe ses exports UTF-8 d un BOM : sans traitement, il colle au
     * nom de la premiere colonne et rend l en-tete introuvable.
     */
    public function testUtf8ByteOrderMarkIsTolerated(): void
    {
        $path = $this->writeCsv([
            ['matricule', 'prenom', 'nom', 'sexe', 'date_naissance', 'classe'],
            [$this->token . '-1', 'Miora', 'Rakoto', 'F', '', $this->className()],
        ]);
        file_put_contents($path, "\xEF\xBB\xBF" . file_get_contents($path));

        $result = $this->import->importStudents($path);

        self::assertSame(1, $result->imported(), 'Le BOM ne doit pas masquer la colonne matricule.');
    }

    public function testBlankLinesAreIgnored(): void
    {
        $path = $this->writeCsv([
            ['matricule', 'prenom', 'nom', 'sexe', 'date_naissance', 'classe'],
            [$this->token . '-1', 'Miora', 'Rakoto', 'F', '', $this->className()],
        ]);
        file_put_contents($path, file_get_contents($path) . "\n\n");

        $result = $this->import->importStudents($path);

        self::assertSame(1, $result->rows(), 'Les lignes vides ne comptent pas comme des lignes rejetees.');
        self::assertSame(1, $result->imported());
    }

    public function testTemplateMatchesExpectedColumns(): void
    {
        $lines = explode("\n", trim($this->import->studentTemplate()));

        self::assertSame(implode(';', $this->import->expectedStudentColumns()), trim($lines[0]));
    }

    private function className(): string
    {
        return 'Classe ' . $this->token;
    }

    private function createReferential(): void
    {
        $year = (new SchoolYear())
            ->setName('Import ' . $this->token)
            ->setStartDate(new \DateTimeImmutable('2026-09-01'))
            ->setEndDate(new \DateTimeImmutable('2027-07-31'))
            ->setIsActive(true);

        $schoolClass = (new SchoolClass())
            ->setName($this->className())
            ->setLevel('College')
            ->setSchoolYear($year);

        $this->em->persist($year);
        $this->em->persist($schoolClass);
        $this->em->flush();
    }

    /**
     * @param array<int, string[]> $rows
     */
    private function importCsv(array $rows): \App\Service\CsvImportResult
    {
        return $this->import->importStudents($this->writeCsv($rows));
    }

    /**
     * @param array<int, string[]> $rows
     */
    private function writeCsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'schoolpay-import-');
        $this->tempFiles[] = $path;

        $handle = fopen($path, 'w');
        foreach ($rows as $row) {
            fputcsv($handle, $row, CsvImportService::DELIMITER);
        }
        fclose($handle);

        return $path;
    }
}
