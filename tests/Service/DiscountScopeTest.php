<?php

namespace App\Tests\Service;

use App\Entity\Discount;
use App\Entity\FeeAssignment;
use App\Entity\FeeType;
use App\Entity\SchoolClass;
use App\Entity\SchoolYear;
use App\Entity\Student;
use App\Entity\StudentDiscount;
use App\Service\PaymentCalculationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Portee d'une reduction accordee (cahier des charges, section 15).
 *
 * L'ecran d'attribution laisse choisir une annee scolaire, un frais cible et
 * une periode de validite : ces tests verifient que le calcul les respecte
 * reellement.
 */
final class DiscountScopeTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private PaymentCalculationService $calculator;
    private string $token;
    private SchoolYear $currentYear;
    private SchoolYear $previousYear;
    private SchoolClass $schoolClass;
    private Student $student;
    private FeeAssignment $currentFee;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->calculator = self::getContainer()->get(PaymentCalculationService::class);
        $this->em->getConnection()->beginTransaction();
        $this->token = strtoupper(substr(uniqid(), -8));
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

    public function testDiscountOfTheYearApplies(): void
    {
        $this->grant($this->percentDiscount(10), $this->currentYear);

        self::assertSame(90000.0, $this->calculator->getNetAmount($this->student, $this->currentFee));
    }

    /**
     * Une reduction accordee au titre d'une annee revolue ne doit pas se
     * reporter sur les frais de l'annee en cours.
     */
    public function testDiscountGrantedForAnotherYearDoesNotCarryOver(): void
    {
        $this->grant($this->percentDiscount(10), $this->previousYear);

        self::assertSame(100000.0, $this->calculator->getNetAmount($this->student, $this->currentFee));
    }

    public function testDiscountTargetingAnotherFeeIsIgnored(): void
    {
        $otherFee = (new FeeAssignment())
            ->setFeeType($this->currentFee->getFeeType())
            ->setSchoolYear($this->currentYear)
            ->setSchoolClass($this->schoolClass)
            ->setAmount(50000)
            ->setDueDate(new \DateTimeImmutable('2026-10-31'));
        $this->em->persist($otherFee);

        $this->grant($this->percentDiscount(10), $this->currentYear, $otherFee);

        self::assertSame(100000.0, $this->calculator->getNetAmount($this->student, $this->currentFee));
    }

    public function testExpiredDiscountStopsApplying(): void
    {
        $expired = $this->percentDiscount(10)
            ->setStartDate(new \DateTimeImmutable('-60 days'))
            ->setEndDate(new \DateTimeImmutable('-10 days'));

        $this->grant($expired, $this->currentYear);

        self::assertSame(100000.0, $this->calculator->getNetAmount($this->student, $this->currentFee));
    }

    public function testFutureDiscountDoesNotApplyYet(): void
    {
        $future = $this->percentDiscount(10)->setStartDate(new \DateTimeImmutable('+10 days'));

        $this->grant($future, $this->currentYear);

        self::assertSame(100000.0, $this->calculator->getNetAmount($this->student, $this->currentFee));
    }

    public function testInactiveDiscountIsIgnored(): void
    {
        $this->grant($this->percentDiscount(10)->setIsActive(false), $this->currentYear);

        self::assertSame(100000.0, $this->calculator->getNetAmount($this->student, $this->currentFee));
    }

    /**
     * Plusieurs reductions cumulees ne doivent jamais rendre le montant negatif.
     */
    public function testAccumulatedDiscountsNeverGoBelowZero(): void
    {
        $this->grant($this->percentDiscount(80), $this->currentYear);
        $this->grant($this->percentDiscount(80), $this->currentYear);

        self::assertSame(0.0, $this->calculator->getNetAmount($this->student, $this->currentFee));
    }

    private function percentDiscount(int $value): Discount
    {
        $discount = (new Discount())
            ->setName('Reduction ' . $value . ' ' . $this->token)
            ->setType(Discount::TYPE_PERCENT)
            ->setValue($value)
            ->setIsActive(true);

        $this->em->persist($discount);

        return $discount;
    }

    private function grant(Discount $discount, SchoolYear $year, ?FeeAssignment $feeAssignment = null): void
    {
        $granted = (new StudentDiscount())
            ->setStudent($this->student)
            ->setDiscount($discount)
            ->setSchoolYear($year);

        if ($feeAssignment instanceof FeeAssignment) {
            $granted->setFeeAssignment($feeAssignment);
        }

        $this->em->persist($granted);
        $this->em->flush();
    }

    private function createFixtures(): void
    {
        $this->currentYear = (new SchoolYear())
            ->setName('Cur ' . $this->token)
            ->setStartDate(new \DateTimeImmutable('2026-09-01'))
            ->setEndDate(new \DateTimeImmutable('2027-07-31'))
            ->setIsActive(true);

        $this->previousYear = (new SchoolYear())
            ->setName('Prev ' . $this->token)
            ->setStartDate(new \DateTimeImmutable('2025-09-01'))
            ->setEndDate(new \DateTimeImmutable('2026-07-31'));

        $this->schoolClass = (new SchoolClass())
            ->setName('Classe ' . $this->token)
            ->setLevel('College')
            ->setSchoolYear($this->currentYear);

        $feeType = (new FeeType())->setName('Ecolage ' . $this->token)->setCode('DSC-' . $this->token);

        $this->currentFee = (new FeeAssignment())
            ->setFeeType($feeType)
            ->setSchoolYear($this->currentYear)
            ->setSchoolClass($this->schoolClass)
            ->setAmount(100000)
            ->setDueDate(new \DateTimeImmutable('2026-09-30'));

        $this->student = (new Student())
            ->setRegistrationNumber('DSC-' . $this->token)
            ->setFirstName('Eleve')
            ->setLastName('Reduction')
            ->setSchoolClass($this->schoolClass)
            ->setSchoolYear($this->currentYear);

        foreach ([$this->currentYear, $this->previousYear, $this->schoolClass, $feeType, $this->currentFee, $this->student] as $entity) {
            $this->em->persist($entity);
        }

        $this->em->flush();
    }
}
