<?php

namespace App\Service;

use App\Entity\SchoolClass;
use App\Entity\SchoolYear;
use App\Entity\Student;
use App\Repository\SchoolClassRepository;
use App\Repository\SchoolYearRepository;
use App\Repository\StudentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Import d'eleves depuis un fichier CSV.
 *
 * L'import est volontairement partiel : les lignes valides sont enregistrees
 * et les lignes fautives sont rapportees avec leur numero, plutot que de tout
 * rejeter pour une seule erreur de saisie.
 */
class CsvImportService
{
    public const DELIMITER = ';';

    /** Formats de date acceptes, du plus courant au moins courant. */
    private const DATE_FORMATS = ['d/m/Y', 'Y-m-d', 'd-m-Y'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private StudentRepository $students,
        private SchoolClassRepository $schoolClasses,
        private SchoolYearRepository $schoolYears,
        private ValidatorInterface $validator,
    ) {
    }

    /** @return string[] */
    public function expectedStudentColumns(): array
    {
        return ['matricule', 'prenom', 'nom', 'sexe', 'date_naissance', 'classe'];
    }

    /**
     * Modele a telecharger : l'en-tete attendu et une ligne d'exemple.
     */
    public function studentTemplate(): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $this->expectedStudentColumns(), self::DELIMITER);
        fputcsv($handle, ['SP-2026-001', 'Miora', 'Rakoto', 'F', '12/04/2014', '6eme A'], self::DELIMITER);
        rewind($handle);

        return stream_get_contents($handle) ?: '';
    }

    public function importStudents(string $path): CsvImportResult
    {
        $result = new CsvImportResult();

        $year = $this->schoolYears->findActive();
        if (!$year instanceof SchoolYear) {
            $result->addError(null, 'Aucune annee scolaire active : activez une annee avant d importer des eleves.');

            return $result;
        }

        $handle = @fopen($path, 'r');
        if ($handle === false) {
            $result->addError(null, 'Le fichier n a pas pu etre ouvert.');

            return $result;
        }

        try {
            $columns = $this->readHeader($handle, $result);
            if ($columns === null) {
                return $result;
            }

            $seen = [];
            $line = 1;

            while (($row = fgetcsv($handle, 0, self::DELIMITER)) !== false) {
                ++$line;

                if ($this->isBlank($row)) {
                    continue;
                }

                $result->countRow();
                $student = $this->buildStudent($row, $columns, $year, $seen, $line, $result);

                if ($student instanceof Student) {
                    $seen[$student->getRegistrationNumber()] = true;
                    $this->entityManager->persist($student);
                    $result->countImported();
                }
            }
        } finally {
            fclose($handle);
        }

        if ($result->imported() > 0) {
            $this->entityManager->flush();
        }

        return $result;
    }

    /**
     * @return array<string, int>|null position de chaque colonne attendue
     */
    private function readHeader($handle, CsvImportResult $result): ?array
    {
        $header = fgetcsv($handle, 0, self::DELIMITER);
        if ($header === false) {
            $result->addError(null, 'Le fichier est vide.');

            return null;
        }

        // Excel prefixe volontiers ses exports UTF-8 d'un BOM, qui collerait
        // au nom de la premiere colonne et la rendrait introuvable.
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);

        $columns = [];
        foreach ($header as $index => $name) {
            $columns[strtolower(trim((string) $name))] = $index;
        }

        $missing = array_diff($this->expectedStudentColumns(), array_keys($columns));
        if ($missing !== []) {
            $result->addError(null, sprintf(
                'Colonnes manquantes : %s. Attendu : %s.',
                implode(', ', $missing),
                implode(self::DELIMITER, $this->expectedStudentColumns())
            ));

            return null;
        }

        return $columns;
    }

    /**
     * @param array<int, string|null> $row
     * @param array<string, int>      $columns
     * @param array<string, true>     $seen
     */
    private function buildStudent(array $row, array $columns, SchoolYear $year, array $seen, int $line, CsvImportResult $result): ?Student
    {
        $value = fn (string $column): string => trim((string) ($row[$columns[$column]] ?? ''));

        $registrationNumber = strtoupper($value('matricule'));
        if ($registrationNumber === '') {
            $result->addError($line, 'Matricule manquant.');

            return null;
        }

        if (isset($seen[$registrationNumber])) {
            $result->addError($line, sprintf('Matricule %s en double dans le fichier.', $registrationNumber));

            return null;
        }

        if ($this->students->findOneBy(['registrationNumber' => $registrationNumber])) {
            $result->addError($line, sprintf('Matricule %s deja present en base.', $registrationNumber));

            return null;
        }

        $className = $value('classe');
        $schoolClass = $className === '' ? null : $this->schoolClasses->findOneBy(['name' => $className]);
        if (!$schoolClass instanceof SchoolClass) {
            $result->addError($line, sprintf('Classe "%s" introuvable.', $className));

            return null;
        }

        $student = (new Student())
            ->setRegistrationNumber($registrationNumber)
            ->setFirstName($value('prenom'))
            ->setLastName($value('nom'))
            ->setSchoolClass($schoolClass)
            ->setSchoolYear($year);

        if ($value('sexe') !== '') {
            $student->setGender($value('sexe'));
        }

        $rawBirthDate = $value('date_naissance');
        if ($rawBirthDate !== '') {
            $birthDate = $this->parseDate($rawBirthDate);
            if (!$birthDate instanceof \DateTimeImmutable) {
                $result->addError($line, sprintf('Date de naissance "%s" illisible (attendu jj/mm/aaaa).', $rawBirthDate));

                return null;
            }
            $student->setBirthDate($birthDate);
        }

        $violations = $this->validator->validate($student);
        if (count($violations) > 0) {
            foreach ($violations as $violation) {
                $result->addError($line, $violation->getPropertyPath() . ' : ' . $violation->getMessage());
            }

            return null;
        }

        return $student;
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        foreach (self::DATE_FORMATS as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            if ($date instanceof \DateTimeImmutable && $this->parsedCleanly()) {
                return $date->setTime(0, 0);
            }
        }

        return null;
    }

    /**
     * createFromFormat accepte des dates absurdes (32/13/2000) en les reportant
     * sur le mois suivant : on refuse des qu il signale la moindre anomalie.
     *
     * getLastErrors() retourne false depuis PHP 8.2 et un tableau de compteurs
     * auparavant ; le projet supportant PHP 8.1, les deux cas sont traites.
     */
    private function parsedCleanly(): bool
    {
        $errors = \DateTimeImmutable::getLastErrors();

        return $errors === false || (($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0);
    }

    /**
     * @param array<int, string|null> $row
     */
    private function isBlank(array $row): bool
    {
        return trim(implode('', array_map(fn ($cell): string => (string) $cell, $row))) === '';
    }
}
