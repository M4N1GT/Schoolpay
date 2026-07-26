<?php

namespace App\Tests\Controller;

use App\Entity\FeeAssignment;
use App\Entity\FeeType;
use App\Entity\SchoolClass;
use App\Entity\SchoolYear;
use App\Entity\Student;
use App\Entity\User;
use App\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Recherche, filtres, tri et remise a zero (cahier des charges, section 24),
 * plus les filtres attendus sur les paiements et le journal d audit.
 */
final class ListFilterTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private string $token;
    private Student $student;
    private FeeAssignment $fee;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
        $this->token = strtoupper(substr(uniqid(), -8));
        $this->createFixtures();
        $this->client->loginUser($this->admin());
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
     * La zone de recherche s affichait sur les huit ressources mais n etait
     * traitee que pour les eleves : les autres listes l ignoraient en silence.
     */
    public function testSearchNarrowsNonStudentResources(): void
    {
        $all = $this->client->request('GET', '/backoffice/classes');
        self::assertGreaterThan(0, $all->filter('table tbody tr')->count());

        $matching = $this->client->request('GET', '/backoffice/classes?q=' . $this->token);
        self::assertSame(1, $matching->filter('table tbody tr')->count());

        $none = $this->client->request('GET', '/backoffice/classes?q=introuvable' . $this->token);
        self::assertStringContainsString('Aucune donnee', $none->filter('table tbody')->text());
    }

    public function testPaymentsAreFilteredByMethod(): void
    {
        $this->pay(10000.0, 'especes');
        $this->pay(20000.0, 'MVola');

        $crawler = $this->client->request('GET', '/backoffice/payments?q=' . $this->token . '&method=MVola');
        $text = $crawler->filter('table tbody')->text();

        self::assertStringContainsString('MVola', $text);
        self::assertStringNotContainsString('especes', $text);
    }

    public function testPaymentsAreFilteredByDateRange(): void
    {
        $this->pay(10000.0, 'especes');

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $past = (new \DateTimeImmutable('-30 days'))->format('Y-m-d');

        // On verifie le contenu et non le nombre de lignes : la ligne
        // "Aucun paiement" est elle-meme un <tr>, un comptage vaudrait 1 dans
        // les deux cas et le test ne prouverait rien.
        $inRange = $this->client->request('GET', sprintf('/backoffice/payments?q=%s&from=%s&to=%s', $this->token, $past, $today));
        self::assertStringNotContainsString('Aucun paiement', $inRange->filter('table tbody')->text());
        self::assertStringContainsString($this->token, $inRange->filter('table tbody')->text());

        // Bornes anterieures au paiement : il doit disparaitre.
        $outOfRange = $this->client->request('GET', sprintf('/backoffice/payments?q=%s&from=%s&to=%s', $this->token, $past, (new \DateTimeImmutable('-15 days'))->format('Y-m-d')));
        self::assertStringContainsString('Aucun paiement', $outOfRange->filter('table tbody')->text());
    }

    /**
     * La borne haute doit inclure la journee entiere, sinon un paiement
     * enregistre a 14h disparait quand on filtre "jusqu a aujourd hui".
     */
    public function testUpperDateBoundIncludesTheWholeDay(): void
    {
        $this->pay(10000.0, 'especes');
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');

        $crawler = $this->client->request('GET', sprintf('/backoffice/payments?q=%s&to=%s', $this->token, $today));

        self::assertStringNotContainsString('Aucun paiement', $crawler->filter('table tbody')->text());
        self::assertStringContainsString($this->token, $crawler->filter('table tbody')->text());
    }

    public function testSortingIsAppliedAndReversible(): void
    {
        // Trois comptes portant le jeton : le tri est observable et la
        // recherche isole le jeu de donnees du test.
        foreach (['aaa', 'mmm', 'zzz'] as $prefix) {
            $this->user($prefix . '-' . strtolower($this->token) . '@schoolpay.test', $prefix);
        }

        $url = '/backoffice/users?q=' . strtolower($this->token) . '&sort=Email&dir=';
        $first = fn (string $direction): string => $this->client
            ->request('GET', $url . $direction)
            ->filter('table tbody tr')->first()->text();

        self::assertStringContainsString('aaa-', $first('asc'));
        self::assertStringContainsString('zzz-', $first('desc'));
    }

    /**
     * Un champ de tri inconnu ne doit pas atteindre le DQL.
     */
    public function testUnknownSortFieldFallsBackToTheDefault(): void
    {
        $this->client->request('GET', '/backoffice/users?sort=e.password%20OR%201=1&dir=asc');

        self::assertResponseIsSuccessful();
    }

    public function testResetLinkAppearsOnlyWhenFiltersAreActive(): void
    {
        $plain = $this->client->request('GET', '/backoffice/classes');
        self::assertSame(0, $plain->filter('a:contains("Reinitialiser")')->count());

        $filtered = $this->client->request('GET', '/backoffice/classes?q=' . $this->token);
        self::assertSame(1, $filtered->filter('a:contains("Reinitialiser")')->count());
    }

    public function testAuditIsFilteredByAction(): void
    {
        $this->pay(10000.0, 'especes');

        $matching = $this->client->request('GET', '/backoffice/audit?action=paiement');
        self::assertGreaterThan(0, $matching->filter('table tbody tr')->count());

        $none = $this->client->request('GET', '/backoffice/audit?action=action_inexistante');
        self::assertStringContainsString('Aucune trace', $none->filter('table tbody')->text());
    }

    private function pay(float $amount, string $method): void
    {
        self::getContainer()->get(PaymentService::class)->registerPayment(
            $this->student,
            [$this->fee],
            $amount,
            $method,
            null,
            null,
            null,
        );
    }

    private function createFixtures(): void
    {
        $year = (new SchoolYear())
            ->setName('Filtre ' . $this->token)
            ->setStartDate(new \DateTimeImmutable('2026-09-01'))
            ->setEndDate(new \DateTimeImmutable('2027-07-31'))
            ->setIsActive(true);

        $schoolClass = (new SchoolClass())
            ->setName('Classe ' . $this->token)
            ->setLevel('Niveau ' . $this->token)
            ->setSchoolYear($year);

        $feeType = (new FeeType())->setName('Frais ' . $this->token)->setCode('FIL-' . $this->token);

        $this->fee = (new FeeAssignment())
            ->setFeeType($feeType)
            ->setSchoolYear($year)
            ->setSchoolClass($schoolClass)
            ->setAmount(500000)
            ->setDueDate(new \DateTimeImmutable('2026-09-30'));

        // Le jeton figure dans le nom, seul champ de l'eleve affiche dans la
        // liste des paiements : les assertions de contenu peuvent s y appuyer.
        $this->student = (new Student())
            ->setRegistrationNumber($this->token)
            ->setFirstName('Eleve')
            ->setLastName('Filtre ' . $this->token)
            ->setSchoolClass($schoolClass)
            ->setSchoolYear($year);

        foreach ([$year, $schoolClass, $feeType, $this->fee, $this->student] as $entity) {
            $this->em->persist($entity);
        }

        $this->em->flush();
    }

    private function admin(): User
    {
        return $this->user('admin-' . strtolower($this->token) . '@schoolpay.test', 'Ada');
    }

    private function user(string $email, string $firstName): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setRoles(['ROLE_ADMIN'])
            ->setFirstName($firstName)
            ->setLastName('Test')
            ->setIsActive(true);
        $user->setPassword('x');

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
