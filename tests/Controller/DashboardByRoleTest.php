<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Chaque role doit atterrir sur son propre tableau de bord (section 18).
 */
final class DashboardByRoleTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
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
     * @return array<string, array{0: string[], 1: string}>
     */
    public function dashboardProvider(): array
    {
        return [
            'administrateur' => [['ROLE_ADMIN'], 'Tableau de bord administrateur'],
            'comptable' => [['ROLE_COMPTABLE'], 'Tableau de bord caisse'],
            'directeur' => [['ROLE_DIRECTEUR'], 'Tableau de bord direction'],
        ];
    }

    /**
     * @dataProvider dashboardProvider
     *
     * @param string[] $roles
     */
    public function testEachRoleGetsItsOwnDashboard(array $roles, string $expectedTitle): void
    {
        $this->client->loginUser($this->user($roles));
        $crawler = $this->client->request('GET', '/backoffice');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString($expectedTitle, $crawler->filter('.topbar')->text());
    }

    /**
     * Le comptable n'a pas a voir le taux de recouvrement global ni le nombre
     * d'utilisateurs : ce sont des indicateurs de pilotage.
     */
    public function testAccountantDashboardIsCentredOnTheCashDesk(): void
    {
        $this->client->loginUser($this->user(['ROLE_COMPTABLE']));
        $crawler = $this->client->request('GET', '/backoffice');

        $text = $crawler->filter('.app-main')->text();
        self::assertStringContainsString('Encaisse aujourd hui', $text);
        self::assertStringContainsString('Repartition par mode de paiement', $text);
        self::assertStringNotContainsString('Recouvrement', $text);
    }

    /**
     * Le directeur consulte des series de recettes, pas un raccourci de caisse.
     */
    public function testDirectorDashboardShowsRevenueSeriesAndNoCashShortcut(): void
    {
        $this->client->loginUser($this->user(['ROLE_DIRECTEUR']));
        $crawler = $this->client->request('GET', '/backoffice');

        $text = $crawler->filter('.app-main')->text();
        self::assertStringContainsString('Recettes des 14 derniers jours', $text);
        self::assertStringContainsString('Recettes par classe', $text);
        self::assertStringNotContainsString('Nouveau paiement', $text);
    }

    /**
     * @param string[] $roles
     */
    private function user(array $roles): User
    {
        $user = (new User())
            ->setEmail(strtolower($roles[0]) . '-' . uniqid() . '@schoolpay.test')
            ->setRoles($roles)
            ->setFirstName('Test')
            ->setLastName($roles[0])
            ->setIsActive(true);
        $user->setPassword('x');

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
