<?php

namespace App\Controller\Backoffice;

use App\Service\CsvExportService;
use App\Service\ReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Un seul controleur sert les quinze rapports : ils partagent le rendu,
 * l'impression et l'export, seule la source de donnees change.
 */
#[Route('/backoffice/reports')]
class ReportController extends AbstractController
{
    #[Route('', name: 'backoffice_report_index')]
    public function index(ReportService $reports): Response
    {
        return $this->render('backoffice/report/index.html.twig', [
            'overview' => $reports->overview(),
            'catalogue' => $reports->catalogue(),
            'defaultPeriod' => $this->defaultPeriod(),
        ]);
    }

    #[Route('/{key}', name: 'backoffice_report_show', requirements: ['key' => '[a-z-]+'])]
    public function show(string $key, Request $request, ReportService $reports, CsvExportService $csvExport): Response
    {
        if (!$reports->has($key)) {
            throw $this->createNotFoundException('Rapport inconnu.');
        }

        [$from, $to] = $this->period($request);
        $result = $reports->run($key, $from, $to);

        if ($request->query->get('export') === 'csv') {
            return new Response($csvExport->build($result->csvRows(), $result->headers), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => sprintf('attachment; filename="rapport-%s.csv"', $key),
            ]);
        }

        return $this->render('backoffice/report/show.html.twig', [
            'key' => $key,
            'result' => $result,
            'catalogue' => $reports->catalogue(),
            'from' => $from->format('Y-m-d'),
            'to' => $to->modify('-1 day')->format('Y-m-d'),
        ]);
    }

    /**
     * Periode demandee, ou l'annee civile en cours par defaut. La borne haute
     * est portee au lendemain pour que la derniere journee soit incluse.
     *
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    private function period(Request $request): array
    {
        $default = $this->defaultPeriod();

        $from = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $request->query->get('from'))
            ?: new \DateTimeImmutable($default['from']);
        $to = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $request->query->get('to'))
            ?: new \DateTimeImmutable($default['to']);

        return [$from->setTime(0, 0), $to->setTime(0, 0)->modify('+1 day')];
    }

    /** @return array{from: string, to: string} */
    private function defaultPeriod(): array
    {
        return [
            'from' => (new \DateTimeImmutable('first day of january this year'))->format('Y-m-d'),
            'to' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
        ];
    }
}
