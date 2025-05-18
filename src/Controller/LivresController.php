<?php

namespace App\Controller;

use App\Entity\Livres;
use App\Entity\Categories; // Add this import
use App\Form\LivresType;
use App\Repository\LivresRepository;
use App\Repository\CategoriesRepository; // Add this import
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class LivresController extends AbstractController
{
    private $categoriesRepository;

    public function __construct(CategoriesRepository $categoriesRepository)
    {
        $this->categoriesRepository = $categoriesRepository;
    }

    #[Route('/livres', name: 'app_livres_all')]
    public function all(Request $request, PaginatorInterface $paginator, EntityManagerInterface $entityManager): Response
    {
        // Récupérer le paramètre de recherche
        $search = $request->query->get('search');

        // Créer la requête de base
        $queryBuilder = $entityManager->getRepository(Livres::class)->createQueryBuilder('l');

        // Si un terme de recherche est fourni, filtrer par titre
        if ($search) {
            $queryBuilder
                ->where('l.titre LIKE :search')
                ->setParameter('search', '%' . $search . '%')
                ->orderBy('l.titre', 'ASC');
        } else {
            // Si pas de recherche, trier par ID décroissant (ou autre critère)
            $queryBuilder->orderBy('l.id', 'DESC');
        }

        // Paginer les résultats
        $pagination = $paginator->paginate(
            $queryBuilder->getQuery(),
            $request->query->getInt('page', 1),
            12 // Nombre d'éléments par page
        );

        // Récupérer toutes les catégories
        $categories = $this->categoriesRepository->findAll();

        // Ajouter un message flash pour le débogage
        if ($search) {
            $this->addFlash('info', 'Recherche effectuée pour: ' . $search);
        }

        return $this->render('livres/all.html.twig', [
            'livres' => $pagination,
            'search' => $search,
            'categories' => $categories // Add this line
        ]);
    }

    #[Route('/livres/show/{id}', name: 'app_livres_show')]
    public function show(Livres $livre): Response
    {
        if (!$livre) {
            throw $this->createNotFoundException("Livre not found");
        }

        // Récupérer toutes les catégories
        $categories = $this->categoriesRepository->findAll();

        return $this->render('livres/detail.html.twig', [
            'livre' => $livre,
            'categories' => $categories // Add this line
        ]);
    }

    #[Route('/livres/create', name: 'app_livres_create')]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        $livre = new Livres();
        $form = $this->createForm(LivresType::class, $livre);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($livre);
            $em->flush();
            $this->addFlash('success', 'Le livre a été bien ajouté');
            return $this->redirectToRoute('app_livres_all');
        }

        // Récupérer toutes les catégories
        $categories = $this->categoriesRepository->findAll();

        return $this->render('livres/create.html.twig', [
            'form' => $form,
            'categories' => $categories // Add this line
        ]);
    }

    #[Route('/livres/update/{id}', name: 'app_livres_update')]
    public function update(Request $request, EntityManagerInterface $em, Livres $livre): Response
    {
        if (!$livre) {
            throw $this->createNotFoundException("Livre not found");
        }
        $form = $this->createForm(LivresType::class, $livre);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Le livre a été bien modifié');
            return $this->redirectToRoute('app_livres_all');
        }

        // Récupérer toutes les catégories
        $categories = $this->categoriesRepository->findAll();

        return $this->render('livres/update.html.twig', [
            'form' => $form,
            'categories' => $categories // Add this line
        ]);
    }

    #[Route('/livres/delete/{id}', name: 'app_livres_delete')]
    public function delete(EntityManagerInterface $em, Livres $livre): Response
    {
        if (!$livre) {
            throw $this->createNotFoundException("Livre not found");
        }
        $em->remove($livre);
        $em->flush();
        $this->addFlash('success', 'Le livre a été supprimé');
        return $this->redirectToRoute('app_livres_all');
    }
}