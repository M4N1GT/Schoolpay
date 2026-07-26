<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig', [
            'stats' => [
                ['value' => '4', 'label' => 'roles securises'],
                ['value' => '100%', 'label' => 'suivi des paiements'],
                ['value' => '24/7', 'label' => 'consultation parent'],
            ],
            'services' => [
                [
                    'title' => 'Back-office ecole',
                    'description' => 'Gestion des classes, eleves, parents, frais, paiements, recus, rapports et audit.',
                ],
                [
                    'title' => 'Caisse fiable',
                    'description' => 'Paiements partiels, affectation aux frais, annulation justifiee et recus verifiables.',
                ],
                [
                    'title' => 'Espace parent',
                    'description' => 'Chaque parent consulte uniquement ses enfants, les echeances et les recus disponibles.',
                ],
            ],
        ]);
    }
}
