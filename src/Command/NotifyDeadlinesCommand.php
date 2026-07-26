<?php

namespace App\Command;

use App\Entity\Notification;
use App\Repository\StudentRepository;
use App\Service\NotificationService;
use App\Service\PaymentCalculationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Previent les parents des echeances proches et depassees (section 20).
 *
 * Destinee a une execution quotidienne planifiee. La reference portee par
 * chaque notification garantit qu'un meme frais ne declenche qu'un seul avis
 * par type, quelle que soit la frequence d'execution.
 */
#[AsCommand(
    name: 'app:notify-deadlines',
    description: 'Notifie les parents des echeances proches et depassees.'
)]
class NotifyDeadlinesCommand extends Command
{
    private const DEFAULT_WINDOW_DAYS = 7;

    public function __construct(
        private StudentRepository $students,
        private PaymentCalculationService $calculator,
        private NotificationService $notifier,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Fenetre en jours pour les echeances a venir', self::DEFAULT_WINDOW_DAYS)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche ce qui serait envoye sans rien enregistrer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = max(1, (int) $input->getOption('days'));
        $dryRun = (bool) $input->getOption('dry-run');

        $today = new \DateTimeImmutable('today');
        $limit = $today->modify(sprintf('+%d days', $days));
        $sent = 0;

        foreach ($this->students->findAll() as $student) {
            foreach ($this->calculator->getStudentSituation($student)['items'] as $item) {
                if ($item['remaining'] <= 0) {
                    continue;
                }

                $dueDate = $item['fee']->getDueDate();
                if (!$dueDate instanceof \DateTimeImmutable) {
                    continue;
                }

                [$type, $title, $sentence] = match (true) {
                    $dueDate < $today => [
                        Notification::TYPE_DEADLINE_PASSED,
                        'Echeance depassee',
                        sprintf('etait due le %s', $dueDate->format('d/m/Y')),
                    ],
                    $dueDate <= $limit => [
                        Notification::TYPE_DEADLINE_NEAR,
                        'Echeance proche',
                        sprintf('arrive a echeance le %s', $dueDate->format('d/m/Y')),
                    ],
                    default => [null, null, null],
                };

                if ($type === null) {
                    continue;
                }

                $notifications = $this->notifier->notifyGuardiansOf(
                    $student,
                    $type,
                    $title,
                    sprintf(
                        '%s pour %s : %s Ar restant, %s.',
                        $item['fee']->getFeeType()?->getName() ?? 'Frais',
                        $student->getFullName(),
                        number_format($item['remaining'], 0, ',', ' '),
                        $sentence,
                    ),
                    sprintf('fee:%d:student:%d:%s', $item['fee']->getId(), $student->getId(), $type),
                );

                $sent += count($notifications);
            }
        }

        if ($dryRun) {
            // Les notifications ont ete rattachees a l'unite de travail sans
            // etre ecrites : on la vide explicitement pour qu'un flush
            // ulterieur ne les enregistre pas malgre tout.
            $this->entityManager->clear();
            $io->note(sprintf('%d notification(s) auraient ete envoyees. Rien n a ete enregistre.', $sent));

            return Command::SUCCESS;
        }

        $this->entityManager->flush();
        $io->success(sprintf('%d notification(s) envoyee(s).', $sent));

        return Command::SUCCESS;
    }
}
