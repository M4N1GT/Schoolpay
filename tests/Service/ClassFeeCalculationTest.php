<?php

namespace App\Tests\Service;

use App\Entity\FeeAssignment;
use App\Entity\FeeType;
use App\Entity\SchoolClass;
use App\Entity\SchoolYear;
use App\Entity\Student;
use App\Service\PaymentCalculationService;
use App\Service\PaymentService;
use App\Service\ReportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests de non-regression sur les frais affectes a une classe.
 *
 * Un FeeAssignment rattache a une classe (student = NULL) est du par chaque
 * eleve de cette classe. Deux bugs en decoulaient :
 *  - le montant paye n'etait pas filtre par eleve, donc le paiement d'un eleve
 *    soldait le frais pour toute sa classe ;
 *  - le montant attendu comptait le frais une seule fois au lieu d'une fois
 *    par eleve.
 *
 * Chaque test s'execute dans une transaction annulee en fin de test : la base
 * n'est jamais modifiee durablement.
 */
final class ClassFeeCalculationTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private SchoolYear $schoolYear;
    private SchoolClass $schoolClass;
    private FeeAssignment $classFee;
    private Student $studentA;
    private Student $studentB;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
        $this->createFixtures();
    }

    protected function tearDown(): void
    {
        $connection = $this->em->getConnection();
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }

        parent::tearDown();
    }

    /**
     * Le paiement de l'eleve A ne doit rien solder chez l'eleve B, alors qu'ils
     * partagent le meme frais de classe.
     */
    public function testClassFeePaidByOneStudentIsNotCreditedToTheOther(): void
    {
        self::getContainer()->get(PaymentService::class)->registerPayment(
            $this->studentA,
            [$this->classFee],
            100000.0,
            'especes',
            null,
            null,
            null,
        );

        $calculator = self::getContainer()->get(PaymentCalculationService::class);

        self::assertSame(0.0, $calculator->getStudentSituation($this->studentA)['remainingTotal']);
        self::assertSame(100000.0, $calculator->getStudentSituation($this->studentB)['remainingTotal']);
    }

    /**
     * Ajouter un eleve dans la classe doit augmenter le montant attendu du
     * montant du frais de classe. Avec l'ancien calcul, l'ecart etait nul.
     */
    public function testExpectedAmountGrowsWithEachStudentOfTheClass(): void
    {
        $reports = self::getContainer()->get(ReportService::class);
        $before = $reports->overview()['expected'];

        $this->em->persist($this->student('C'));
        $this->em->flush();

        $after = $reports->overview()['expected'];

        self::assertSame(100000.0, round($after - $before, 2));
    }

    private function createFixtures(): void
    {
        $this->schoolYear = (new SchoolYear())
            ->setName('Test ' . uniqid())
            ->setStartDate(new \DateTimeImmutable('2026-09-01'))
            ->setEndDate(new \DateTimeImmutable('2027-07-31'));

        $this->schoolClass = (new SchoolClass())
            ->setName('Classe de test')
            ->setLevel('College')
            ->setSchoolYear($this->schoolYear);

        $feeType = (new FeeType())
            ->setName('Ecolage de test')
            ->setCode('TEST-' . uniqid());

        $this->classFee = (new FeeAssignment())
            ->setFeeType($feeType)
            ->setSchoolYear($this->schoolYear)
            ->setSchoolClass($this->schoolClass)
            ->setAmount(100000)
            ->setDueDate(new \DateTimeImmutable('2026-09-30'));

        $this->studentA = $this->student('A');
        $this->studentB = $this->student('B');

        foreach ([$this->schoolYear, $this->schoolClass, $feeType, $this->classFee, $this->studentA, $this->studentB] as $entity) {
            $this->em->persist($entity);
        }

        $this->em->flush();
    }

    private function student(string $suffix): Student
    {
        return (new Student())
            ->setRegistrationNumber('SP-TEST-' . $suffix . '-' . uniqid())
            ->setFirstName('Eleve')
            ->setLastName($suffix)
            ->setSchoolClass($this->schoolClass)
            ->setSchoolYear($this->schoolYear);
    }
}
