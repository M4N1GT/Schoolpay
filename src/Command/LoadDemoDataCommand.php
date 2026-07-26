<?php

namespace App\Command;

use App\Entity\Discount;
use App\Entity\FeeAssignment;
use App\Entity\FeeType;
use App\Entity\Notification;
use App\Entity\ParentGuardian;
use App\Entity\SchoolClass;
use App\Entity\SchoolSetting;
use App\Entity\SchoolYear;
use App\Entity\Student;
use App\Entity\StudentDiscount;
use App\Entity\User;
use App\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:load-demo-data', description: 'Charge les donnees de demonstration SchoolPay.')]
class LoadDemoDataCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private PaymentService $paymentService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@schoolpay.test'])) {
            $output->writeln('Les donnees de demonstration existent deja.');

            return Command::SUCCESS;
        }

        $admin = $this->user('admin@schoolpay.test', ['ROLE_ADMIN'], 'Ada', 'Admin');
        $accountant = $this->user('comptable@schoolpay.test', ['ROLE_COMPTABLE'], 'Celine', 'Caisse');
        $director = $this->user('directeur@schoolpay.test', ['ROLE_DIRECTEUR'], 'David', 'Directeur');
        $parentUser = $this->user('parent@schoolpay.test', ['ROLE_PARENT'], 'Prisca', 'Parent');

        $settings = (new SchoolSetting())
            ->setSchoolName('SchoolPay Academy')
            ->setSchoolCode('SPA')
            ->setAddress('Antananarivo')
            ->setPhone('+261 34 00 000 00')
            ->setEmail('contact@schoolpay.test')
            ->setReceiptFooter('Merci de conserver ce recu. Verification disponible en ligne.');

        $year = (new SchoolYear())
            ->setName('2026-2027')
            ->setStartDate(new \DateTimeImmutable('2026-09-01'))
            ->setEndDate(new \DateTimeImmutable('2027-07-31'))
            ->setIsActive(true);

        $sixth = (new SchoolClass())->setName('6eme A')->setLevel('College')->setCapacity(35)->setSchoolYear($year);
        $fifth = (new SchoolClass())->setName('5eme B')->setLevel('College')->setCapacity(32)->setSchoolYear($year);

        $parent = (new ParentGuardian())
            ->setFirstName('Prisca')
            ->setLastName('Rakoto')
            ->setEmail('parent@schoolpay.test')
            ->setPhone('+261 34 11 222 33')
            ->setAddress('Lot II SchoolPay')
            ->setProfession('Commercante')
            ->setRelationshipType('Mere')
            ->setUser($parentUser);

        $studentA = (new Student())
            ->setRegistrationNumber('SP-2026-001')
            ->setFirstName('Miora')
            ->setLastName('Rakoto')
            ->setGender('F')
            ->setBirthDate(new \DateTimeImmutable('2014-04-12'))
            ->setSchoolClass($sixth)
            ->setSchoolYear($year)
            ->addParent($parent);

        $studentB = (new Student())
            ->setRegistrationNumber('SP-2026-002')
            ->setFirstName('Tahina')
            ->setLastName('Rakoto')
            ->setGender('M')
            ->setBirthDate(new \DateTimeImmutable('2013-11-02'))
            ->setSchoolClass($fifth)
            ->setSchoolYear($year)
            ->addParent($parent);

        $registration = (new FeeType())->setName('Inscription')->setCode('INSCRIPTION')->setIsMandatory(true);
        $tuition = (new FeeType())->setName('Ecolage mensuel')->setCode('ECOLAGE')->setIsMandatory(true)->setIsRecurring(true)->setRecurrenceType('mensuel');
        $canteen = (new FeeType())->setName('Cantine')->setCode('CANTINE')->setIsMandatory(false)->setIsRecurring(true)->setRecurrenceType('mensuel');

        $fees = [
            (new FeeAssignment())->setFeeType($registration)->setSchoolYear($year)->setSchoolClass($sixth)->setAmount(150000)->setDueDate(new \DateTimeImmutable('2026-09-10'))->setDescription('Inscription annuelle'),
            (new FeeAssignment())->setFeeType($tuition)->setSchoolYear($year)->setSchoolClass($sixth)->setAmount(100000)->setDueDate(new \DateTimeImmutable('2026-09-30'))->setMonthNumber(9),
            (new FeeAssignment())->setFeeType($tuition)->setSchoolYear($year)->setSchoolClass($sixth)->setAmount(100000)->setDueDate(new \DateTimeImmutable('2026-10-31'))->setMonthNumber(10),
            (new FeeAssignment())->setFeeType($canteen)->setSchoolYear($year)->setStudent($studentA)->setAmount(60000)->setDueDate(new \DateTimeImmutable('2026-09-30'))->setMonthNumber(9)->setIsMandatory(false),
            (new FeeAssignment())->setFeeType($registration)->setSchoolYear($year)->setSchoolClass($fifth)->setAmount(150000)->setDueDate(new \DateTimeImmutable('2026-09-10')),
            (new FeeAssignment())->setFeeType($tuition)->setSchoolYear($year)->setSchoolClass($fifth)->setAmount(110000)->setDueDate(new \DateTimeImmutable('2026-09-30'))->setMonthNumber(9),
        ];

        $discount = (new Discount())->setName('Reduction fratrie')->setType(Discount::TYPE_PERCENT)->setValue(10)->setReason('Deux enfants inscrits');
        $studentDiscount = (new StudentDiscount())->setStudent($studentA)->setDiscount($discount)->setSchoolYear($year)->setApprovedBy($admin)->setJustification('Fratrie confirmee');

        foreach ([$admin, $accountant, $director, $parentUser, $settings, $year, $sixth, $fifth, $parent, $studentA, $studentB, $registration, $tuition, $canteen, $discount, $studentDiscount, ...$fees] as $entity) {
            $this->entityManager->persist($entity);
        }

        $notification = (new Notification())
            ->setUser($parentUser)
            ->setTitle('Bienvenue dans SchoolPay')
            ->setMessage('Votre espace parent est pret. Vous pouvez consulter les situations de vos enfants.')
            ->setType('compte_parent');
        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        $this->paymentService->registerPayment($studentA, [$fees[0], $fees[1]], 180000, 'especes', null, 'Paiement de demonstration', $accountant);
        $this->paymentService->registerPayment($studentB, [$fees[4]], 150000, 'MVola', 'MV-123456', 'Paiement complet inscription', $accountant);
        $this->entityManager->flush();

        $output->writeln('Donnees de demonstration chargees.');
        $output->writeln('Mot de passe commun: password123');

        return Command::SUCCESS;
    }

    private function user(string $email, array $roles, string $firstName, string $lastName): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setRoles($roles)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setIsActive(true);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'password123'));

        return $user;
    }
}
