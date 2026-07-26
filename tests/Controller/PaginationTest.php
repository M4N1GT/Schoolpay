<?php

namespace App\Tests\Controller;

use App\Entity\ParentGuardian;
use App\Entity\SchoolClass;
use App\Entity\SchoolYear;
use App\Entity\Student;
use App\Entity\User;
use App\Service\PaginationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Pagination des grandes listes (cahier des charges, section 24).
 *
 * Les eleves crees portent un matricule prefixe par un jeton unique : les
 * assertions restent valables quel que soit le contenu preexistant de la base
 * de test, la recherche isolant le jeu de donnees du test.
 */
final class PaginationTest extends WebTestCase
{
    private const TOTAL_STUDENTS = 30;

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private string $token;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Sans cela le kernel redemarre entre deux requetes et ouvre une
        // nouvelle connexion, aveugle aux donnees de la transaction de test :
        // la seconde requete perdrait la session et redirigerait vers /login.
        $this->client->disableReboot();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
        $this->token = 'pagtest' . uniqid();
        $this->createStudents();
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

    public function testFirstPageIsCappedAndSecondPageHoldsTheRemainder(): void
    {
        $first = $this->client->request('GET', '/backoffice/students?q=' . $this->token);
        self::assertSame(PaginationService::PER_PAGE, $first->filter('table tbody tr')->count());

        $second = $this->client->request('GET', '/backoffice/students?q=' . $this->token . '&page=2');
        self::assertSame(self::TOTAL_STUDENTS - PaginationService::PER_PAGE, $second->filter('table tbody tr')->count());
    }

    /**
     * Un eleve rattache a plusieurs parents ne doit pas compter plusieurs fois :
     * sans fetchJoinCollection, la jointure vers les parents dupliquerait les
     * lignes et fausserait a la fois le total et la taille des pages.
     */
    public function testJoinedCollectionDoesNotInflateTheTotal(): void
    {
        $crawler = $this->client->request('GET', '/backoffice/students?q=' . $this->token);

        self::assertStringContainsString(
            'sur ' . self::TOTAL_STUDENTS,
            $crawler->filter('.pagination')->text()
        );
    }

    /**
     * La recherche doit survivre au changement de page (section 24).
     */
    public function testFiltersArePreservedAcrossPages(): void
    {
        $crawler = $this->client->request('GET', '/backoffice/students?q=' . $this->token);
        $next = $crawler->filter('.pagination a[rel="next"]');

        self::assertCount(1, $next);
        self::assertStringContainsString('q=' . $this->token, $next->attr('href'));
    }

    /**
     * L'export CSV porte sur la selection entiere, pas sur la page affichee.
     */
    public function testCsvExportIgnoresPagination(): void
    {
        $this->client->request('GET', '/backoffice/students?q=' . $this->token . '&export=csv');
        $csv = $this->client->getResponse()->getContent();

        $lines = array_filter(explode("\n", trim($csv)));

        self::assertCount(self::TOTAL_STUDENTS + 1, $lines, 'Une ligne d en-tete puis un eleve par ligne.');
    }

    private function createStudents(): void
    {
        $year = (new SchoolYear())
            // school_year.name est limite a 30 caracteres.
            ->setName(substr('Pag ' . $this->token, 0, 30))
            ->setStartDate(new \DateTimeImmutable('2026-09-01'))
            ->setEndDate(new \DateTimeImmutable('2027-07-31'));

        $schoolClass = (new SchoolClass())
            ->setName('Classe pagination')
            ->setLevel('College')
            ->setSchoolYear($year);

        $this->em->persist($year);
        $this->em->persist($schoolClass);

        for ($i = 1; $i <= self::TOTAL_STUDENTS; ++$i) {
            $student = (new Student())
                ->setRegistrationNumber($this->token . '-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT))
                ->setFirstName('Eleve')
                ->setLastName('Numero ' . $i)
                ->setSchoolClass($schoolClass)
                ->setSchoolYear($year);

            // Le premier eleve a deux responsables legaux : c est le cas qui
            // ferait deraper un comptage naif sur une jointure to-many.
            if ($i === 1) {
                foreach (['Mere', 'Pere'] as $relationship) {
                    $parent = (new ParentGuardian())
                        ->setFirstName($relationship)
                        ->setLastName('Test')
                        ->setPhone('+261 34 00 000 00')
                        ->setRelationshipType($relationship);
                    $this->em->persist($parent);
                    $student->addParent($parent);
                }
            }

            $this->em->persist($student);
        }

        $this->em->flush();
    }

    private function admin(): User
    {
        $user = (new User())
            ->setEmail('admin-' . $this->token . '@schoolpay.test')
            ->setRoles(['ROLE_ADMIN'])
            ->setFirstName('Ada')
            ->setLastName('Admin')
            ->setIsActive(true);
        $user->setPassword('x');

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
