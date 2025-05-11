<?php

namespace App\Controller;

use App\Entity\Livres;
use App\Form\LivresType;
use App\Repository\LivresRepository;
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
    #[Route('/livres', name: 'app_livres_all')]
    public function all(LivresRepository $rep, PaginatorInterface $paginator, Request $request): Response
    {
        $livres = $paginator->paginate(
            $rep->findAll(),
            $request->query->getInt('page', 1),
            10
        );
        return $this->render('livres/all.html.twig.twig', ['livres' => $livres]);
    }

    #[Route('/livres/show/{id}', name: 'app_livres_show')]
    public function show(Livres $livre): Response
    {
        if (!$livre) {
            throw $this->createNotFoundException("Livre not found");
        }
        return $this->render('livres/detail.html.twig', ['livre' => $livre]);
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
        return $this->render('livres/create.html.twig', [
            'form' => $form,
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
        return $this->render('livres/update.html.twig', [
            'form' => $form,
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