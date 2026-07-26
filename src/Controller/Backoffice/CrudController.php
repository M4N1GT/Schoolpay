<?php

namespace App\Controller\Backoffice;

use App\Entity\Discount;
use App\Entity\FeeAssignment;
use App\Entity\FeeType;
use App\Entity\ParentGuardian;
use App\Entity\SchoolClass;
use App\Entity\SchoolYear;
use App\Entity\Student;
use App\Entity\User;
use App\Form\DiscountType;
use App\Form\FeeAssignmentType;
use App\Form\FeeTypeType;
use App\Form\ParentGuardianType;
use App\Form\SchoolClassType;
use App\Form\SchoolYearType;
use App\Form\StudentType;
use App\Form\UserType;
use App\Security\Voter\BackofficeVoter;
use App\Service\AuditLoggerService;
use App\Service\CsvExportService;
use App\Service\ListFilterService;
use App\Service\PaginationService;
use App\Service\SchoolYearService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/backoffice')]
class CrudController extends AbstractController
{
    /**
     * Chaque ressource declare ce sur quoi la recherche porte et les colonnes
     * triables. Les expressions sont des chemins DQL complets : rien de ce qui
     * vient de l'URL n'atteint la requete, seules ces valeurs sont utilisees.
     */
    private const RESOURCES = [
        'school-years' => [
            'class' => SchoolYear::class, 'form' => SchoolYearType::class, 'label' => 'Annees scolaires',
            'headers' => ['Nom', 'Debut', 'Fin', 'Statut'],
            'search' => ['e.name'],
            'sort' => ['Nom' => 'e.name', 'Debut' => 'e.startDate', 'Fin' => 'e.endDate'],
        ],
        'classes' => [
            'class' => SchoolClass::class, 'form' => SchoolClassType::class, 'label' => 'Classes',
            'headers' => ['Nom', 'Niveau', 'Annee', 'Statut'],
            'search' => ['e.name', 'e.level'],
            'sort' => ['Nom' => 'e.name', 'Niveau' => 'e.level'],
        ],
        'students' => [
            'class' => Student::class, 'form' => StudentType::class, 'label' => 'Eleves',
            'headers' => ['Matricule', 'Nom', 'Classe', 'Statut'],
            'sort' => ['Matricule' => 's.registrationNumber', 'Nom' => 's.lastName', 'Statut' => 's.status'],
        ],
        'parents' => [
            'class' => ParentGuardian::class, 'form' => ParentGuardianType::class, 'label' => 'Parents',
            'headers' => ['Nom', 'Email', 'Telephone', 'Enfants'],
            'search' => ['e.firstName', 'e.lastName', 'e.email', 'e.phone'],
            'sort' => ['Nom' => 'e.lastName', 'Email' => 'e.email'],
        ],
        'fee-types' => [
            'class' => FeeType::class, 'form' => FeeTypeType::class, 'label' => 'Types de frais',
            'headers' => ['Nom', 'Code', 'Obligatoire', 'Statut'],
            'search' => ['e.name', 'e.code'],
            'sort' => ['Nom' => 'e.name', 'Code' => 'e.code'],
        ],
        'fee-assignments' => [
            'class' => FeeAssignment::class, 'form' => FeeAssignmentType::class, 'label' => 'Frais affectes',
            'headers' => ['Frais', 'Cible', 'Montant', 'Echeance'],
            'joins' => ['e.feeType' => 'ft'],
            'search' => ['ft.name', 'ft.code', 'e.description'],
            'sort' => ['Frais' => 'ft.name', 'Montant' => 'e.amount', 'Echeance' => 'e.dueDate'],
        ],
        'discounts' => [
            'class' => Discount::class, 'form' => DiscountType::class, 'label' => 'Reductions',
            'headers' => ['Nom', 'Type', 'Valeur', 'Statut'],
            'search' => ['e.name', 'e.reason'],
            'sort' => ['Nom' => 'e.name', 'Type' => 'e.type', 'Valeur' => 'e.value'],
        ],
        'users' => [
            'class' => User::class, 'form' => UserType::class, 'label' => 'Utilisateurs',
            'headers' => ['Nom', 'Email', 'Roles', 'Statut'],
            'search' => ['e.firstName', 'e.lastName', 'e.email'],
            'sort' => ['Nom' => 'e.lastName', 'Email' => 'e.email'],
        ],
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private AuditLoggerService $auditLogger,
    ) {
    }

    #[Route('/{resource}', name: 'backoffice_crud_index', requirements: ['resource' => 'school-years|classes|students|parents|fee-types|fee-assignments|discounts|users'])]
    public function index(string $resource, Request $request, CsvExportService $csvExport, PaginationService $paginator, ListFilterService $filters): Response
    {
        $config = $this->config($resource);
        $repository = $this->entityManager->getRepository($config['class']);
        $query = trim((string) $request->query->get('q', ''));

        if ($resource === 'students') {
            $queryBuilder = $repository->searchQueryBuilder($query ?: null, $request->query->all());
            $filters->applySort($queryBuilder, $config['sort'], $request, 's.lastName');
        } else {
            $queryBuilder = $repository->createQueryBuilder('e');
            foreach ($config['joins'] ?? [] as $path => $alias) {
                $queryBuilder->leftJoin($path, $alias)->addSelect($alias);
            }
            $filters->applySearch($queryBuilder, $config['search'] ?? [], $query);
            $filters->applySort($queryBuilder, $config['sort'] ?? [], $request, 'e.id', 'DESC');
        }

        // L'export porte sur la selection complete, jamais sur la seule page
        // affichee : il doit rester exploitable comme extraction de reference.
        if ($request->query->get('export') === 'csv') {
            $rows = array_map(
                fn (object $item): array => $this->row($resource, $item),
                $queryBuilder->getQuery()->getResult()
            );

            return new Response($csvExport->build($rows, $config['headers']), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $resource . '.csv"',
            ]);
        }

        $pagination = $paginator->paginate($queryBuilder, $paginator->currentPage($request));

        return $this->render('backoffice/crud/index.html.twig', [
            'resource' => $resource,
            'title' => $config['label'],
            'headers' => $config['headers'],
            'items' => $pagination->items,
            'pagination' => $pagination,
            'query' => $query,
            'sortable' => $config['sort'] ?? [],
            'hasFilters' => $filters->hasActiveFilters($request),
        ]);
    }

    #[Route('/{resource}/new', name: 'backoffice_crud_new', requirements: ['resource' => 'school-years|classes|students|parents|fee-types|fee-assignments|discounts|users'])]
    public function new(string $resource, Request $request): Response
    {
        $this->denyAccessUnlessGranted(BackofficeVoter::MANAGE, $resource);
        $config = $this->config($resource);
        $class = $config['class'];
        $entity = new $class();
        $form = $this->buildFormFor($resource, $entity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleUserPassword($entity, $form);
            $this->entityManager->persist($entity);
            $this->auditLogger->log('creation', $class, null, $config['label'] . ' cree', $this->getUser());
            $this->entityManager->flush();
            $this->addFlash('success', 'Enregistrement cree.');

            return $this->redirectToRoute('backoffice_crud_index', ['resource' => $resource]);
        }

        return $this->render('backoffice/crud/form.html.twig', [
            'title' => 'Nouveau - ' . $config['label'],
            'resource' => $resource,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{resource}/{id}/edit', name: 'backoffice_crud_edit', requirements: ['resource' => 'school-years|classes|students|parents|fee-types|fee-assignments|discounts|users'])]
    public function edit(string $resource, int $id, Request $request, SchoolYearService $schoolYearService): Response
    {
        $this->denyAccessUnlessGranted(BackofficeVoter::MANAGE, $resource);
        $config = $this->config($resource);
        $entity = $this->entityManager->getRepository($config['class'])->find($id);
        if (!$entity) {
            throw $this->createNotFoundException();
        }

        $form = $this->buildFormFor($resource, $entity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleUserPassword($entity, $form);
            if (method_exists($entity, 'touch')) {
                $entity->touch();
            }
            if ($entity instanceof SchoolYear && $entity->isActive()) {
                $schoolYearService->activate($entity);
            } else {
                $this->entityManager->flush();
            }
            $this->auditLogger->log('modification', $config['class'], $id, $config['label'] . ' modifie', $this->getUser());
            $this->entityManager->flush();
            $this->addFlash('success', 'Modifications enregistrees.');

            return $this->redirectToRoute('backoffice_crud_index', ['resource' => $resource]);
        }

        return $this->render('backoffice/crud/form.html.twig', [
            'title' => 'Modifier - ' . $config['label'],
            'resource' => $resource,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{resource}/{id}', name: 'backoffice_crud_show', requirements: ['resource' => 'school-years|classes|students|parents|fee-types|fee-assignments|discounts|users'])]
    public function show(string $resource, int $id): Response
    {
        $config = $this->config($resource);
        $item = $this->entityManager->getRepository($config['class'])->find($id);
        if (!$item) {
            throw $this->createNotFoundException();
        }

        return $this->render('backoffice/crud/show.html.twig', [
            'title' => $config['label'],
            'resource' => $resource,
            'item' => $item,
            'row' => $this->row($resource, $item),
            'headers' => $config['headers'],
        ]);
    }

    private function config(string $resource): array
    {
        return self::RESOURCES[$resource] ?? throw $this->createNotFoundException();
    }

    private function buildFormFor(string $resource, object $entity): FormInterface
    {
        $config = $this->config($resource);
        $options = $resource === 'users' && !$entity->getId() ? ['password_required' => true] : [];

        return $this->createForm($config['form'], $entity, $options);
    }

    private function handleUserPassword(object $entity, FormInterface $form): void
    {
        if (!$entity instanceof User) {
            return;
        }

        $plainPassword = (string) $form->get('plainPassword')->getData();
        if ($plainPassword !== '') {
            $entity->setPassword($this->passwordHasher->hashPassword($entity, $plainPassword));
        }
    }

    private function row(string $resource, object $item): array
    {
        return match ($resource) {
            'school-years' => [$item->getName(), $item->getStartDate()?->format('d/m/Y'), $item->getEndDate()?->format('d/m/Y'), $item->isActive() ? 'Active' : ($item->isClosed() ? 'Cloturee' : 'Inactive')],
            'classes' => [$item->getName(), $item->getLevel(), (string) $item->getSchoolYear(), $item->isActive() ? 'Active' : 'Inactive'],
            'students' => [$item->getRegistrationNumber(), $item->getFullName(), (string) $item->getSchoolClass(), $item->getStatus()],
            'parents' => [$item->getFullName(), $item->getEmail(), $item->getPhone(), (string) $item->getStudents()->count()],
            'fee-types' => [$item->getName(), $item->getCode(), $item->isMandatory() ? 'Oui' : 'Non', $item->isActive() ? 'Actif' : 'Inactif'],
            'fee-assignments' => [(string) $item->getFeeType(), $item->getStudent()?->getFullName() ?: (string) $item->getSchoolClass(), number_format((float) $item->getAmount(), 0, ',', ' ') . ' Ar', $item->getDueDate()?->format('d/m/Y')],
            'discounts' => [$item->getName(), $item->getType(), $item->getValue(), $item->isActive() ? 'Active' : 'Inactive'],
            'users' => [$item->getFullName(), $item->getEmail(), implode(', ', $item->getRoles()), $item->isActive() ? 'Actif' : 'Inactif'],
            default => [],
        };
    }
}
