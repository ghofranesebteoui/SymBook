<?php

namespace App\Controller;

use App\Entity\Categories;
use App\Form\CategorieType;
use App\Repository\CategoriesRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CategoriesController extends AbstractController
{
    #[Route('/admin/categories', name: 'admin_categories')]
    public function index(CategoriesRepository $rep): Response
    {
        $categories = $rep->findAll();
        return $this->render('categories/index.html.twig', [
            'categories' => $categories,
        ]);
    }
    #[Route('/admin/categories/create', name: 'app_categories_create')]
    public function create(Request $request,EntityManagerInterface $em): Response
    {
        $categorie=new Categories();
        //affichage du formulaire
        $form=$this->createForm(CategorieType::class,$categorie);
        //traitement des données issues
        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()){
            //dd($categorie);
            $em->persist($categorie);
            $em->flush();
            $this->addFlash('success','la categorie a été bien ajouter');
            return $this->redirectToRoute('admin_categories');
        }
        return $this->render('categories/create.html.twig', [
            'form' => $form,
        ]);
    }
    #[Route('/admin/categories/update/{id}', name: 'app_categories_update')]
    public function update(Request $request,EntityManagerInterface $em,Categories $categorie): Response
    {
        //affichage du formulaire
        $form=$this->createForm(CategorieType::class,$categorie);
        //traitement des données issues
        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()){
            $em->flush();
            $this->addFlash('success','la categorie a été bien modifier');
            return $this->redirectToRoute('admin_categories');
        }
        return $this->render('categories/update.html.twig', [
            'form' => $form,
        ]);

    }
}
