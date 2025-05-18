<?php

namespace App\Controller;

use App\Entity\Categories;
use App\Form\CategorieType;
use App\Repository\CategoriesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class CategoriesController extends AbstractController
{
    #[Route('/categories', name: 'app_categories_all')]
    public function index(CategoriesRepository $rep, PaginatorInterface $paginator, Request $request): Response
    {
        $categories = $paginator->paginate(
            $rep->findAll(),
            $request->query->getInt('page', 1),
            10
        );
        return $this->render('categories/check_email.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/categories/create', name: 'app_categories_create')]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        $categorie = new Categories();
        $form = $this->createForm(CategorieType::class, $categorie);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($categorie);
            $em->flush();
            $this->addFlash('success', 'La catégorie a été bien ajoutée');
            return $this->redirectToRoute('app_categories_all');
        }
        return $this->render('categories/create.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/categories/update/{id}', name: 'app_categories_update')]
    public function update(Request $request, EntityManagerInterface $em, Categories $categorie): Response
    {
        if (!$categorie) {
            throw $this->createNotFoundException("Catégorie not found");
        }
        $form = $this->createForm(CategorieType::class, $categorie);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'La catégorie a été bien modifiée');
            return $this->redirectToRoute('app_categories_all');
        }
        return $this->render('categories/check_email.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/categories/delete/{id}', name: 'app_categories_delete')]
    public function delete(EntityManagerInterface $em, Categories $categorie): Response
    {
        if (!$categorie) {
            throw $this->createNotFoundException("Catégorie not found");
        }
        $em->remove($categorie);
        $em->flush();
        $this->addFlash('success', 'La catégorie a été supprimée');
        return $this->redirectToRoute('app_categories_all');
    }
}