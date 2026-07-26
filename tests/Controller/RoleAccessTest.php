<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Verifie la separation des pouvoirs decrite au cahier des charges :
 *  - le directeur consulte mais ne modifie rien ;
 *  - le comptable encaisse mais ne gere pas le referentiel ;
 *  - le parent n entre pas dans le back-office.
 *
 * Chaque test s execute dans une transaction annulee en fin de test.
 */
final class RoleAccessTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
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
     * @return array<string, array{0: string[], 1: string, 2: int}>
     */
    public function accessProvider(): array
    {
        return [
            'directeur consulte les eleves' => [['ROLE_DIRECTEUR'], '/backoffice/students', Response::HTTP_OK],
            'directeur ne cree pas d eleve' => [['ROLE_DIRECTEUR'], '/backoffice/students/new', Response::HTTP_FORBIDDEN],
            'directeur ne cree pas de frais' => [['ROLE_DIRECTEUR'], '/backoffice/fee-assignments/new', Response::HTTP_FORBIDDEN],
            'directeur n encaisse pas' => [['ROLE_DIRECTEUR'], '/backoffice/payments/new', Response::HTTP_FORBIDDEN],
            'directeur consulte les impayes' => [['ROLE_DIRECTEUR'], '/backoffice/outstanding', Response::HTTP_OK],
            'comptable encaisse' => [['ROLE_COMPTABLE'], '/backoffice/payments/new', Response::HTTP_OK],
            'comptable ne cree pas d eleve' => [['ROLE_COMPTABLE'], '/backoffice/students/new', Response::HTTP_FORBIDDEN],
            'comptable n ouvre pas l audit' => [['ROLE_COMPTABLE'], '/backoffice/audit', Response::HTTP_FORBIDDEN],
            'admin cree un eleve' => [['ROLE_ADMIN'], '/backoffice/students/new', Response::HTTP_OK],
            'admin ouvre l audit' => [['ROLE_ADMIN'], '/backoffice/audit', Response::HTTP_OK],
            'parent hors du back-office' => [['ROLE_PARENT'], '/backoffice', Response::HTTP_FORBIDDEN],
        ];
    }

    /**
     * @dataProvider accessProvider
     *
     * @param string[] $roles
     */
    public function testAccessByRole(array $roles, string $url, int $expectedStatus): void
    {
        $this->client->loginUser($this->user($roles));
        $this->client->request('GET', $url);

        self::assertSame($expectedStatus, $this->client->getResponse()->getStatusCode());
    }

    /**
     * Le directeur ne doit pas pouvoir annuler un paiement, meme en postant
     * directement sur la route sans passer par un bouton.
     */
    public function testDirectorCannotCancelPayment(): void
    {
        $this->client->loginUser($this->user(['ROLE_DIRECTEUR']));
        $this->client->request('POST', '/backoffice/payments/1/cancel', ['reason' => 'test']);

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
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
        $user->setPassword(
            self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'password123')
        );

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
