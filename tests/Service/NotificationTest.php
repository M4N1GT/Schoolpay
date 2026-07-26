<?php

namespace App\Tests\Service;

use App\Entity\FeeAssignment;
use App\Entity\FeeType;
use App\Entity\Notification;
use App\Entity\ParentGuardian;
use App\Entity\SchoolClass;
use App\Entity\SchoolYear;
use App\Entity\Student;
use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Service\NotificationService;
use App\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Notifications internes (cahier des charges, section 20).
 */
final class NotificationTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private NotificationRepository $notifications;
    private string $token;
    private Student $student;
    private FeeAssignment $overdueFee;
    private User $parentUser;
    private User $orphanParentUser;
    private Student $orphanStudent;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->notifications = self::getContainer()->get(NotificationRepository::class);
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

    public function testRegisteringAPaymentNotifiesTheGuardians(): void
    {
        $before = $this->notificationCount(Notification::TYPE_NEW_PAYMENT);

        $this->pay(30000.0);

        self::assertSame($before + 1, $this->notificationCount(Notification::TYPE_NEW_PAYMENT));
        self::assertStringContainsString('30 000 Ar', $this->latest(Notification::TYPE_NEW_PAYMENT)->getMessage());
    }

    public function testCancellingAPaymentNotifiesTheGuardians(): void
    {
        $payment = $this->pay(30000.0);

        self::getContainer()->get(PaymentService::class)->cancelPayment($payment, 'Erreur de caisse', null);

        $notification = $this->latest(Notification::TYPE_PAYMENT_CANCELLED);
        self::assertNotNull($notification);
        self::assertStringContainsString('Erreur de caisse', $notification->getMessage());
    }

    /**
     * Un eleve dont le responsable n a pas de compte utilisateur ne doit pas
     * faire echouer l encaissement : il n est simplement pas notifie.
     */
    public function testGuardianWithoutAccountIsSkippedWithoutError(): void
    {
        $before = $this->notificationCount(Notification::TYPE_NEW_PAYMENT);

        self::getContainer()->get(PaymentService::class)->registerPayment(
            $this->orphanStudent,
            [$this->overdueFee],
            10000.0,
            'especes',
            null,
            null,
            null,
        );

        self::assertSame($before, $this->notificationCount(Notification::TYPE_NEW_PAYMENT));
    }

    /**
     * La reference empeche qu une meme alerte parte deux fois : sans elle, une
     * commande quotidienne repeterait ses avis a chaque execution.
     */
    public function testReferenceMakesNotificationsIdempotent(): void
    {
        $notifier = self::getContainer()->get(NotificationService::class);

        $first = $notifier->notify($this->parentUser, Notification::TYPE_DEADLINE_NEAR, 'Titre', 'Message', 'ref:' . $this->token);
        $this->em->flush();
        $second = $notifier->notify($this->parentUser, Notification::TYPE_DEADLINE_NEAR, 'Titre', 'Message', 'ref:' . $this->token);

        self::assertInstanceOf(Notification::class, $first);
        self::assertNull($second, 'La seconde tentative doit etre ignoree.');
    }

    public function testDeadlineCommandNotifiesOverdueFeesOnlyOnce(): void
    {
        $tester = $this->commandTester();

        $tester->execute([]);
        $afterFirstRun = $this->notificationCount(Notification::TYPE_DEADLINE_PASSED);
        self::assertGreaterThan(0, $afterFirstRun, 'L echeance depassee doit declencher un avis.');

        $tester->execute([]);
        self::assertSame($afterFirstRun, $this->notificationCount(Notification::TYPE_DEADLINE_PASSED), 'La seconde execution ne doit rien ajouter.');
    }

    public function testDryRunPersistsNothing(): void
    {
        $before = $this->notificationCount(Notification::TYPE_DEADLINE_PASSED);

        $this->commandTester()->execute(['--dry-run' => true]);

        // Un flush explicite apres coup : sans le vidage de l'unite de travail,
        // les notifications preparees seraient ecrites malgre le mode simulation.
        $this->em->flush();

        self::assertSame($before, $this->notificationCount(Notification::TYPE_DEADLINE_PASSED));
    }

    private function commandTester(): CommandTester
    {
        return new CommandTester(
            (new Application(self::$kernel))->find('app:notify-deadlines')
        );
    }

    private function notificationCount(string $type): int
    {
        return (int) $this->notifications->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.user = :user')
            ->andWhere('n.type = :type')
            ->setParameter('user', $this->parentUser)
            ->setParameter('type', $type)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function latest(string $type): ?Notification
    {
        return $this->notifications->findOneBy(
            ['user' => $this->parentUser, 'type' => $type],
            ['id' => 'DESC']
        );
    }

    private function pay(float $amount): \App\Entity\Payment
    {
        return self::getContainer()->get(PaymentService::class)->registerPayment(
            $this->student,
            [$this->overdueFee],
            $amount,
            'especes',
            null,
            null,
            null,
        );
    }

    private function createFixtures(): void
    {
        $year = (new SchoolYear())
            ->setName('Notif ' . $this->token)
            ->setStartDate(new \DateTimeImmutable('2026-09-01'))
            ->setEndDate(new \DateTimeImmutable('2027-07-31'))
            ->setIsActive(true);

        $schoolClass = (new SchoolClass())
            ->setName('Classe ' . $this->token)
            ->setLevel('College')
            ->setSchoolYear($year);

        $feeType = (new FeeType())->setName('Ecolage ' . $this->token)->setCode('NOT-' . $this->token);

        // Echeance volontairement dans le passe : la commande doit la traiter
        // comme depassee.
        $this->overdueFee = (new FeeAssignment())
            ->setFeeType($feeType)
            ->setSchoolYear($year)
            ->setSchoolClass($schoolClass)
            ->setAmount(200000)
            ->setDueDate(new \DateTimeImmutable('-10 days'));

        $this->parentUser = $this->user('parent-' . strtolower($this->token) . '@schoolpay.test');

        $guardian = (new ParentGuardian())
            ->setFirstName('Prisca')
            ->setLastName('Test')
            ->setPhone('+261 34 00 000 00')
            ->setRelationshipType('Mere')
            ->setUser($this->parentUser);

        $this->student = (new Student())
            ->setRegistrationNumber('NOT-' . $this->token)
            ->setFirstName('Eleve')
            ->setLastName('Notifie')
            ->setSchoolClass($schoolClass)
            ->setSchoolYear($year)
            ->addParent($guardian);

        // Responsable legal sans compte utilisateur.
        $orphanGuardian = (new ParentGuardian())
            ->setFirstName('Sans')
            ->setLastName('Compte')
            ->setPhone('+261 34 11 111 11')
            ->setRelationshipType('Pere');

        $this->orphanStudent = (new Student())
            ->setRegistrationNumber('ORP-' . $this->token)
            ->setFirstName('Eleve')
            ->setLastName('Sans compte')
            ->setSchoolClass($schoolClass)
            ->setSchoolYear($year)
            ->addParent($orphanGuardian);

        foreach ([$year, $schoolClass, $feeType, $this->overdueFee, $guardian, $this->student, $orphanGuardian, $this->orphanStudent] as $entity) {
            $this->em->persist($entity);
        }

        $this->em->flush();
    }

    private function user(string $email): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setRoles(['ROLE_PARENT'])
            ->setFirstName('Prisca')
            ->setLastName('Test')
            ->setIsActive(true);
        $user->setPassword('x');

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
