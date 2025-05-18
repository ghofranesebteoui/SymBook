<?php

namespace App\Controller;

use App\Repository\LivresRepository;
use App\Repository\CategoriesRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        LivresRepository     $livresRepository,
        CategoriesRepository $categoriesRepository,
        PaginatorInterface   $paginator,
        Request              $request
    ): Response
    {
        // Récupérer tous les livres sans pagination d'abord
        $allLivres = $livresRepository->findAll();

        // Paginer les livres
        $livres = $paginator->paginate(
            $allLivres, // Requête contenant les données à paginer
            $request->query->getInt('page', 1), // Numéro de la page en cours, 1 par défaut
            12 // Nombre d'éléments par page
        );

        $categories = $categoriesRepository->findAll();

        return $this->render('home/check_email.html.twig', [
            'livres' => $livres,
            'categories' => $categories,
        ]);
    }
}