<?php

namespace App\Controller\Backoffice;

use App\Form\SchoolSettingType;
use App\Repository\SchoolSettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SettingController extends AbstractController
{
    #[Route('/backoffice/settings', name: 'backoffice_settings')]
    public function index(Request $request, SchoolSettingRepository $settingsRepository, EntityManagerInterface $entityManager): Response
    {
        $settings = $settingsRepository->getSettings();
        $form = $this->createForm(SchoolSettingType::class, $settings);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $settings->touch();
            $entityManager->flush();
            $this->addFlash('success', 'Parametres enregistres.');

            return $this->redirectToRoute('backoffice_settings');
        }

        return $this->render('backoffice/settings/index.html.twig', ['form' => $form->createView()]);
    }
}
