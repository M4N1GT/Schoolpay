<?php

namespace App\Tests\Controller;

use App\Entity\FeeAssignment;
use App\Entity\FeeType;
use App\Entity\ParentGuardian;
use App\Entity\Payment;
use App\Entity\SchoolClass;
use App\Entity\SchoolYear;
use App\Entity\Student;
use App\Entity\User;
use App\Service\AmountInWords;
use App\Service\PaymentService;
use App\Service\QrCodeGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Recus : contenu, verification publique et acces parent (section 16).
 */
final class ReceiptTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private string $token;
    private Student $student;
    private FeeAssignment $fee;
    private User $parentUser;
    private User $otherParentUser;
    private User $admin;
    private Payment $payment;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
        $this->token = strtoupper(substr(uniqid(), -8));
        $this->createFixtures();

        $this->payment = self::getContainer()->get(PaymentService::class)->registerPayment(
            $this->student,
            [$this->fee],
            185000.0,
            'MVola',
            'MV-' . $this->token,
            null,
            $this->admin,
        );
    }

    protected function tearDown(): void
    {
        $connection = $this->em->getConnection();
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }

        parent::tearDown();
    }

    public function testReceiptCarriesEveryRequiredMention(): void
    {
        $this->client->loginUser($this->admin);
        $crawler = $this->client->request('GET', '/backoffice/receipts/' . $this->payment->getReceipt()->getId() . '/print');

        self::assertResponseIsSuccessful();
        $text = $crawler->filter('.receipt')->text();

        self::assertStringContainsString($this->payment->getReceipt()->getReceiptNumber(), $text);
        self::assertStringContainsString($this->student->getRegistrationNumber(), $text);
        self::assertStringContainsString($this->student->getFullName(), $text);
        self::assertStringContainsString('185 000', $text);
        self::assertStringContainsString('MVola', $text);
        self::assertStringContainsString('MV-' . $this->token, $text);
        self::assertStringContainsString($this->payment->getReceipt()->getVerificationCode(), $text);
    }

    public function testReceiptShowsTheAmountInWords(): void
    {
        $this->client->loginUser($this->admin);
        $crawler = $this->client->request('GET', '/backoffice/receipts/' . $this->payment->getReceipt()->getId() . '/print');

        self::assertStringContainsString('Cent quatre-vingt-cinq mille ariary', $crawler->filter('.receipt')->text());
    }

    public function testReceiptEmbedsAQrCodeWithoutExternalRequest(): void
    {
        $this->client->loginUser($this->admin);
        $crawler = $this->client->request('GET', '/backoffice/receipts/' . $this->payment->getReceipt()->getId() . '/print');

        $qr = $crawler->filter('img.receipt-qr');
        self::assertCount(1, $qr);
        self::assertStringStartsWith('data:image/svg+xml;base64,', $qr->attr('src'));
    }

    public function testParentCanOpenTheReceiptOfTheirOwnChild(): void
    {
        $this->client->loginUser($this->parentUser);
        $this->client->request('GET', '/parent/receipts/' . $this->payment->getReceipt()->getId());

        self::assertResponseIsSuccessful();
    }

    /**
     * Le point sensible du nouvel acces : un parent ne doit pas atteindre le
     * recu d un enfant qui n est pas le sien.
     */
    public function testParentCannotOpenSomeoneElsesReceipt(): void
    {
        $this->client->loginUser($this->otherParentUser);
        $this->client->request('GET', '/parent/receipts/' . $this->payment->getReceipt()->getId());

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    /**
     * La page publique doit permettre de confirmer un recu sans exposer
     * l identite complete de l eleve ni le detail des frais.
     */
    public function testPublicVerificationExposesOnlyTheMinimum(): void
    {
        $crawler = $this->client->request('GET', '/receipt/verify/' . $this->payment->getReceipt()->getVerificationCode());

        self::assertResponseIsSuccessful();
        $text = $crawler->filter('.panel')->text();

        self::assertStringContainsString('authentique', $text);
        self::assertStringContainsString('185 000', $text);
        self::assertStringNotContainsString($this->student->getRegistrationNumber(), $text);
        self::assertStringNotContainsString($this->student->getLastName(), $text);
        self::assertStringContainsString('Rakoto', $text, 'Le prenom reste visible pour permettre la confirmation.');
    }

    public function testUnknownCodeIsReportedWithoutLeakingAnything(): void
    {
        $crawler = $this->client->request('GET', '/receipt/verify/CODEINEXISTANT');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Code inconnu', $crawler->filter('.panel')->text());
    }

    public function testAmountInWordsServiceIsAvailable(): void
    {
        $service = self::getContainer()->get(AmountInWords::class);

        self::assertTrue($service->isAvailable());
        self::assertSame('Cent quatre-vingt-cinq mille ariary', $service->format(185000.0));
    }

    public function testQrGeneratorRejectsEmptyContent(): void
    {
        self::assertNull(self::getContainer()->get(QrCodeGenerator::class)->dataUri(''));
    }

    private function createFixtures(): void
    {
        $year = (new SchoolYear())
            ->setName('Recu ' . $this->token)
            ->setStartDate(new \DateTimeImmutable('2026-09-01'))
            ->setEndDate(new \DateTimeImmutable('2027-07-31'))
            ->setIsActive(true);

        $schoolClass = (new SchoolClass())
            ->setName('Classe ' . $this->token)
            ->setLevel('College')
            ->setSchoolYear($year);

        $feeType = (new FeeType())->setName('Ecolage ' . $this->token)->setCode('REC-' . $this->token);

        $this->fee = (new FeeAssignment())
            ->setFeeType($feeType)
            ->setSchoolYear($year)
            ->setSchoolClass($schoolClass)
            ->setAmount(300000)
            ->setDueDate(new \DateTimeImmutable('2026-09-30'));

        $this->parentUser = $this->user('parent-' . strtolower($this->token) . '@schoolpay.test', ['ROLE_PARENT']);
        $this->otherParentUser = $this->user('autre-' . strtolower($this->token) . '@schoolpay.test', ['ROLE_PARENT']);
        $this->admin = $this->user('admin-' . strtolower($this->token) . '@schoolpay.test', ['ROLE_ADMIN']);

        $guardian = (new ParentGuardian())
            ->setFirstName('Prisca')
            ->setLastName('Rakoto')
            ->setPhone('+261 34 00 000 00')
            ->setRelationshipType('Mere')
            ->setUser($this->parentUser);

        // Responsable d'un autre foyer, sans lien avec l'eleve du test.
        $otherGuardian = (new ParentGuardian())
            ->setFirstName('Autre')
            ->setLastName('Foyer')
            ->setPhone('+261 34 99 999 99')
            ->setRelationshipType('Pere')
            ->setUser($this->otherParentUser);

        // Prenom "Rakoto", nom "Andrianina" : la page publique doit montrer le
        // prenom et masquer le nom.
        $this->student = (new Student())
            ->setRegistrationNumber('REC-' . $this->token)
            ->setFirstName('Rakoto')
            ->setLastName('Andrianina')
            ->setSchoolClass($schoolClass)
            ->setSchoolYear($year)
            ->addParent($guardian);

        foreach ([$year, $schoolClass, $feeType, $this->fee, $guardian, $otherGuardian, $this->student] as $entity) {
            $this->em->persist($entity);
        }

        $this->em->flush();
    }

    /**
     * @param string[] $roles
     */
    private function user(string $email, array $roles): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setRoles($roles)
            ->setFirstName('Test')
            ->setLastName('User')
            ->setIsActive(true);
        $user->setPassword('x');

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
