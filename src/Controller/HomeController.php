<?php

namespace App\Controller;

use App\Repository\LivresRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(LivresRepository $livresRepository): Response
    {
        $livres = $livresRepository->findAll();
        return $this->render('home/index.html.twig', [
            'livres' => $livres,
        ]);
    }
}