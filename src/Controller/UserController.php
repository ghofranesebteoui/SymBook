<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class UserController extends AbstractController
{
    #[Route('/users', name: 'app_users_all')]
    public function all(UserRepository $rep, PaginatorInterface $paginator, Request $request): Response
    {
        $users = $paginator->paginate(
            $rep->findAll(),
            $request->query->getInt('page', 1),
            10
        );
        return $this->render('users/all.html.twig.twig', ['users' => $users]);
    }

    #[Route('/users/update/{id}', name: 'app_users_update')]
    public function update(Request $request, EntityManagerInterface $em, User $user): Response
    {
        if (!$user) {
            throw $this->createNotFoundException("Utilisateur not found");
        }
        $form = $this->createForm(/* UserType::class */ $user); // Assuming UserType exists
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'L\'utilisateur a été bien modifié');
            return $this->redirectToRoute('app_users_all');
        }
        return $this->render('users/update.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/users/delete/{id}', name: 'app_users_delete')]
    public function delete(EntityManagerInterface $em, User $user): Response
    {
        if (!$user) {
            throw $this->createNotFoundException("Utilisateur not found");
        }
        $em->remove($user);
        $em->flush();
        $this->addFlash('success', 'L\'utilisateur a été supprimé');
        return $this->redirectToRoute('app_users_all');
    }
}