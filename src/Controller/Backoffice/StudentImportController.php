<?php

namespace App\Controller\Backoffice;

use App\Entity\Student;
use App\Entity\User;
use App\Security\Voter\BackofficeVoter;
use App\Service\AuditLoggerService;
use App\Service\CsvImportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Le prefixe /backoffice/import evite toute collision avec la route generique
 * /backoffice/{resource}/{id} du CrudController.
 */
#[Route('/backoffice/import')]
class StudentImportController extends AbstractController
{
    #[Route('/students', name: 'backoffice_student_import')]
    public function import(Request $request, CsvImportService $csvImport, AuditLoggerService $auditLogger, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(BackofficeVoter::MANAGE, 'students');

        $result = null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('import_students', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }

            $file = $request->files->get('file');
            if (!$file instanceof UploadedFile) {
                $this->addFlash('error', 'Selectionnez un fichier CSV.');

                return $this->redirectToRoute('backoffice_student_import');
            }

            $result = $csvImport->importStudents($file->getPathname());

            if ($result->imported() > 0) {
                $auditLogger->log(
                    'import',
                    Student::class,
                    null,
                    sprintf('Import CSV : %d eleve(s) importe(s), %d rejete(s).', $result->imported(), $result->rejected()),
                    $this->getUser() instanceof User ? $this->getUser() : null,
                );
                $entityManager->flush();
            }
        }

        return $this->render('backoffice/import/students.html.twig', [
            'columns' => $csvImport->expectedStudentColumns(),
            'result' => $result,
        ]);
    }

    #[Route('/students/template', name: 'backoffice_student_import_template')]
    public function template(CsvImportService $csvImport): Response
    {
        $this->denyAccessUnlessGranted(BackofficeVoter::MANAGE, 'students');

        return new Response($csvImport->studentTemplate(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="modele-eleves.csv"',
        ]);
    }
}
