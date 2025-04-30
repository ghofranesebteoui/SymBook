<?php

namespace App\Controller;

use App\Entity\Categories;
use App\Entity\Livres;
use App\Form\CategorieType;
use App\Form\LivresType;
use App\Repository\LivresRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LivresController extends AbstractController
{

    #[Route('admin/livres/show/{id}', name: 'app_livres_show')]
    public function show(Livres $livre): Response
    {
       if(!$livre){
           throw $this->createNotFoundException("livre not found");
       }
        return $this->render('livres/detail.html.twig', ['livre'=>$livre]);

    }
    #[Route('admin/livres/show2', name: 'app_livres_show2')]
    public function show2(LivresRepository $rep): Response
    {
        $livre=$rep->findOneBy(['titre'=>'Titre 1','editeur'=>'Cérès Éditions']);
        if(!$livre){
            throw $this->createNotFoundException("livre not found");
        }
        dd($livre);
    }
    #[Route('admin/livres/show3', name: 'app_livres_show3')]
    public function show3(LivresRepository $rep): Response
    {
        $livres=$rep->findBy(['titre'=>'Titre 1'],['prix'=>'DESC']);
        if(!$livres){
            throw $this->createNotFoundException("livre not found ");
        }
        dd($livres);
    }
    #[Route('admin/livres', name: 'app_livres_all')]
    public function all(LivresRepository $rep,PaginatorInterface $paginator, Request $request): Response
    {
        $livres= $paginator->paginate(
            $rep->findAll(),
            $request->query->getInt('page', 1), 10
        );
        return $this->render('livres/all.html.twig', ['livres'=>$livres]);
    }
    #[Route('admin/livres/delete/{id}', name: 'app_livres_delete')]
    public function delete(LivresRepository $rep,EntityManagerInterface $em,Livres $livre): Response
    {
        if(!$livre){
            throw $this->createNotFoundException("livre not found");
        }
        $em->remove($livre);
        $em->flush();
        return $this->redirectToRoute('app_livres_all');
    }
    #[Route('admin/livres/update/{id}', name: 'app_livres_update')]
    public function update(LivresRepository $rep,EntityManagerInterface $em,Livres $livre): Response
    {
        if(!$livre){
            throw $this->createNotFoundException("livre not found");
        }
        $nouveauPrix=$livre->getPrix()*1.1;
        $livre->setPrix($nouveauPrix);
        $em->persist($livre);
        $em->flush();
        return $this->redirectToRoute('app_livres_all');
    }
    #[Route('/admin/livres/create', name: 'app_livres_create')]
    public function create(Request $request,EntityManagerInterface $em): Response
    {
        $livre=new Livres();
        //affichage du formulaire
        $form=$this->createForm(LivresType::class, $livre);
        //traitement des données issues
        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()){
            //dd($categorie);
            $em->persist($livre);
            $em->flush();
            $this->addFlash('success','le livre a été bien ajouter');
            return $this->redirectToRoute('app_livres_all');
        }
        return $this->render('livres/create.html.twig', [
            'form' => $form,
        ]);
    }
}
