<?php

namespace App\Tests\Controller;

use App\Entity\FeeAssignment;
use App\Entity\FeeType;
use App\Entity\SchoolClass;
use App\Entity\SchoolYear;
use App\Entity\Student;
use App\Entity\User;
use App\Service\PaymentService;
use App\Service\ReportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Module de rapports (cahier des charges, section 19).
 */
final class ReportTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private string $token;
    private Student $student;
    private FeeAssignment $fee;
    private User $accountant;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
        $this->token = strtoupper(substr(uniqid(), -8));
        $this->createFixtures();
        $this->client->loginUser($this->accountant);
    }

    protected function tearDown(): void
    {
        $connection = $this->em->getConnection();
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }

        parent::tearDown();
    }

    /** @return array<string, array{0: string}> */
    public function reportProvider(): array
    {
        $keys = array_keys(self::catalogueKeys());

        return array_combine(
            $keys,
            array_map(static fn (string $key): array => [$key], $keys)
        );
    }

    /**
     * Les quinze rapports doivent au minimum s afficher sans erreur.
     *
     * @dataProvider reportProvider
     */
    public function testEveryReportRenders(string $key): void
    {
        $this->client->request('GET', '/backoffice/reports/' . $key . $this->periodQuery());

        self::assertResponseIsSuccessful();
    }

    public function testCatalogueCoversTheFifteenExpectedReports(): void
    {
        self::assertCount(15, self::getContainer()->get(ReportService::class)->catalogue());
    }

    public function testDailyReportAggregatesThePayment(): void
    {
        $this->pay(120000.0, 'MVola');

        $crawler = $this->client->request('GET', '/backoffice/reports/journalier' . $this->periodQuery());
        $text = $crawler->filter('table')->text();

        self::assertStringContainsString('120 000 Ar', $text);
        self::assertStringNotContainsString('Aucune donnee', $crawler->filter('.table-panel')->text());
    }

    public function testMethodReportSplitsByPaymentMethod(): void
    {
        $this->pay(30000.0, 'especes');
        $this->pay(70000.0, 'MVola');

        $text = $this->client->request('GET', '/backoffice/reports/par-mode' . $this->periodQuery())->filter('table')->text();

        self::assertStringContainsString('especes', $text);
        self::assertStringContainsString('MVola', $text);
        self::assertStringContainsString('100 000 Ar', $text, 'Le pied de tableau doit totaliser les deux modes.');
    }

    /**
     * Un paiement annule ne doit plus compter dans les recettes mais doit
     * apparaitre dans le rapport des annulations.
     */
    public function testCancelledPaymentMovesFromRevenueToCancellations(): void
    {
        $payment = $this->pay(50000.0, 'especes');
        self::getContainer()->get(PaymentService::class)->cancelPayment($payment, 'Erreur de caisse', $this->accountant);

        $revenue = $this->client->request('GET', '/backoffice/reports/journalier' . $this->periodQuery())->filter('.table-panel')->text();
        self::assertStringNotContainsString('50 000 Ar', $revenue);

        $cancellations = $this->client->request('GET', '/backoffice/reports/annulations' . $this->periodQuery())->filter('table')->text();
        self::assertStringContainsString('Erreur de caisse', $cancellations);
        self::assertStringContainsString('50 000 Ar', $cancellations);
    }

    /**
     * L export doit porter les montants en decimale virgule sans separateur de
     * milliers : c est ce qu un tableur configure en francais sait relire.
     */
    public function testCsvExportUsesFrenchDecimalSeparator(): void
    {
        $this->pay(120000.0, 'especes');

        $this->client->request('GET', '/backoffice/reports/par-mode' . $this->periodQuery() . '&export=csv');
        $csv = $this->client->getResponse()->getContent();

        self::assertStringContainsString('text/csv', (string) $this->client->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString('120000,00', $csv);
        self::assertStringContainsString('Montant', $csv, 'La ligne d en-tete doit etre presente.');
    }

    public function testPeriodOutsideThePaymentExcludesIt(): void
    {
        $this->pay(90000.0, 'especes');

        $past = sprintf(
            '?from=%s&to=%s',
            (new \DateTimeImmutable('-40 days'))->format('Y-m-d'),
            (new \DateTimeImmutable('-30 days'))->format('Y-m-d')
        );

        $text = $this->client->request('GET', '/backoffice/reports/journalier' . $past)->filter('.table-panel')->text();

        self::assertStringContainsString('Aucune donnee', $text);
    }

    public function testUnknownReportReturnsNotFound(): void
    {
        $this->client->request('GET', '/backoffice/reports/rapport-inexistant');

        self::assertResponseStatusCodeSame(404);
    }

    /** @return array<string, string> */
    private static function catalogueKeys(): array
    {
        return [
            'journalier' => '', 'hebdomadaire' => '', 'mensuel' => '', 'annuel' => '',
            'par-classe' => '', 'par-niveau' => '', 'par-eleve' => '', 'par-parent' => '',
            'par-mode' => '', 'par-comptable' => '', 'par-type-de-frais' => '',
            'reductions' => '', 'annulations' => '', 'impayes' => '', 'recouvrement' => '',
        ];
    }

    private function periodQuery(): string
    {
        return sprintf(
            '?from=%s&to=%s',
            (new \DateTimeImmutable('-7 days'))->format('Y-m-d'),
            (new \DateTimeImmutable('today'))->format('Y-m-d')
        );
    }

    private function pay(float $amount, string $method): \App\Entity\Payment
    {
        return self::getContainer()->get(PaymentService::class)->registerPayment(
            $this->student,
            [$this->fee],
            $amount,
            $method,
            null,
            null,
            $this->accountant,
        );
    }

    private function createFixtures(): void
    {
        $year = (new SchoolYear())
            ->setName('Rap ' . $this->token)
            ->setStartDate(new \DateTimeImmutable('2026-09-01'))
            ->setEndDate(new \DateTimeImmutable('2027-07-31'))
            ->setIsActive(true);

        $schoolClass = (new SchoolClass())
            ->setName('Classe ' . $this->token)
            ->setLevel('Niveau ' . $this->token)
            ->setSchoolYear($year);

        $feeType = (new FeeType())->setName('Frais ' . $this->token)->setCode('RAP-' . $this->token);

        $this->fee = (new FeeAssignment())
            ->setFeeType($feeType)
            ->setSchoolYear($year)
            ->setSchoolClass($schoolClass)
            ->setAmount(900000)
            ->setDueDate(new \DateTimeImmutable('2026-09-30'));

        $this->student = (new Student())
            ->setRegistrationNumber($this->token)
            ->setFirstName('Eleve')
            ->setLastName('Rapport')
            ->setSchoolClass($schoolClass)
            ->setSchoolYear($year);

        $this->accountant = (new User())
            ->setEmail('comptable-' . strtolower($this->token) . '@schoolpay.test')
            ->setRoles(['ROLE_COMPTABLE'])
            ->setFirstName('Celine')
            ->setLastName('Caisse')
            ->setIsActive(true);
        $this->accountant->setPassword('x');

        foreach ([$year, $schoolClass, $feeType, $this->fee, $this->student, $this->accountant] as $entity) {
            $this->em->persist($entity);
        }

        $this->em->flush();
    }
}
