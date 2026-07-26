<?php

namespace App\Tests\Repository;

use App\Entity\FeeAssignment;
use App\Entity\FeeType;
use App\Entity\SchoolClass;
use App\Entity\SchoolYear;
use App\Entity\Student;
use App\Repository\PaymentRepository;
use App\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Agregats alimentant les tableaux de bord.
 */
final class PaymentRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private PaymentRepository $payments;
    private Student $student;
    private FeeAssignment $fee;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->payments = self::getContainer()->get(PaymentRepository::class);
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

    public function testTodaySummaryCountsTheDayPayments(): void
    {
        $before = $this->payments->summaryBetween(new \DateTimeImmutable('today'), new \DateTimeImmutable('tomorrow'));

        $this->pay(40000.0);

        $after = $this->payments->summaryBetween(new \DateTimeImmutable('today'), new \DateTimeImmutable('tomorrow'));

        self::assertSame($before['count'] + 1, $after['count']);
        self::assertSame(40000.0, round($after['total'] - $before['total'], 2));
    }

    /**
     * La serie doit couvrir toute la fenetre demandee, y compris les jours
     * sans le moindre encaissement : sinon le graphique ecrase les creux.
     */
    public function testRevenueSeriesFillsEmptyPeriods(): void
    {
        $series = $this->payments->revenueSeries('day', 14);

        self::assertCount(14, $series);
        self::assertArrayHasKey((new \DateTimeImmutable('today'))->format('Y-m-d'), $series);
        self::assertArrayHasKey((new \DateTimeImmutable('-13 days'))->format('Y-m-d'), $series);
        self::assertContainsOnly('float', $series);
    }

    public function testMonthlySeriesSpansTwelveMonths(): void
    {
        $series = $this->payments->revenueSeries('month', 12);

        self::assertCount(12, $series);
        self::assertArrayHasKey((new \DateTimeImmutable('first day of this month'))->format('Y-m'), $series);
    }

    public function testCancelledPaymentsAreExcludedFromBreakdowns(): void
    {
        $payment = $this->pay(25000.0);
        $method = $payment->getPaymentMethod();

        $before = $this->payments->sumByMethod()[$method] ?? 0.0;

        self::getContainer()->get(PaymentService::class)->cancelPayment($payment, 'Erreur de saisie', null);

        $after = $this->payments->sumByMethod()[$method] ?? 0.0;

        self::assertSame(25000.0, round($before - $after, 2));
    }

    private function pay(float $amount): \App\Entity\Payment
    {
        return self::getContainer()->get(PaymentService::class)->registerPayment(
            $this->student,
            [$this->fee],
            $amount,
            'especes',
            null,
            null,
            null,
        );
    }

    private function createFixtures(): void
    {
        $token = strtoupper(substr(uniqid(), -8));

        $year = (new SchoolYear())
            ->setName('Agr ' . $token)
            ->setStartDate(new \DateTimeImmutable('2026-09-01'))
            ->setEndDate(new \DateTimeImmutable('2027-07-31'));

        $schoolClass = (new SchoolClass())
            ->setName('Classe ' . $token)
            ->setLevel('Niveau ' . $token)
            ->setSchoolYear($year);

        $feeType = (new FeeType())->setName('Frais ' . $token)->setCode('AGR-' . $token);

        $this->fee = (new FeeAssignment())
            ->setFeeType($feeType)
            ->setSchoolYear($year)
            ->setSchoolClass($schoolClass)
            ->setAmount(500000)
            ->setDueDate(new \DateTimeImmutable('2026-09-30'));

        $this->student = (new Student())
            ->setRegistrationNumber('AGR-' . $token)
            ->setFirstName('Eleve')
            ->setLastName('Agregat')
            ->setSchoolClass($schoolClass)
            ->setSchoolYear($year);

        foreach ([$year, $schoolClass, $feeType, $this->fee, $this->student] as $entity) {
            $this->em->persist($entity);
        }

        $this->em->flush();
    }
}
