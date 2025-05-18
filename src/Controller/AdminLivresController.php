<?php

namespace App\Controller;

use App\Entity\Categories;
use App\Entity\Livres;
use App\Entity\Commandes;
use App\Entity\User;
use App\Form\CategorieType;
use App\Form\LivresType;
use App\Repository\CategoriesRepository;
use App\Repository\LivresRepository;
use App\Repository\CommandesRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class AdminLivresController extends AbstractController
{
    // Book Management
    #[Route('/livres', name: 'app_livres_all')]
    public function allBooks(LivresRepository $rep, PaginatorInterface $paginator, Request $request): Response
    {
        $search = $request->query->get('search');
        $queryBuilder = $rep->createQueryBuilder('l');

        if ($search) {
            $queryBuilder
                ->where('l.titre LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $livres = $paginator->paginate(
            $queryBuilder->getQuery(),
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('admin/livres/all.html.twig', [
            'livres' => $livres,
            'search' => $search
        ]);
    }

    #[Route('/livres/show/{id}', name: 'app_livres_show')]
    public function showBook(Livres $livre): Response
    {
        if (!$livre) {
            throw $this->createNotFoundException("Livre not found");
        }
        return $this->render('admin/livres/detail.html.twig', ['livre' => $livre]);
    }

    #[Route('/livres/create', name: 'app_livres_create')]
    public function createBook(Request $request, EntityManagerInterface $em): Response
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

        return $this->render('admin/livres/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/livres/update/{id}', name: 'app_livres_update')]
    public function updateBook(Request $request, EntityManagerInterface $em, Livres $livre): Response
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

        return $this->render('admin/livres/update.html.twig', [
            'form' => $form->createView(),
            'livre' => $livre
        ]);
    }

    #[Route('/livres/delete/{id}', name: 'app_livres_delete')]
    public function deleteBook(EntityManagerInterface $em, Livres $livre): Response
    {
        if (!$livre) {
            throw $this->createNotFoundException("Livre not found");
        }

        $em->remove($livre);
        $em->flush();
        $this->addFlash('success', 'Le livre a été supprimé');

        return $this->redirectToRoute('app_livres_all');
    }

    // Category Management
    #[Route('/categories', name: 'app_categories_all')]
    public function allCategories(CategoriesRepository $rep, PaginatorInterface $paginator, Request $request): Response
    {
        $categories = $paginator->paginate(
            $rep->findAll(),
            $request->query->getInt('page', 1),
            10
        );
        return $this->render('admin/categories/all.html.twig', ['categories' => $categories]);
    }

    #[Route('/categories/create', name: 'app_categories_create')]
    public function createCategory(Request $request, EntityManagerInterface $em): Response
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

        return $this->render('admin/categories/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/categories/update/{id}', name: 'app_categories_update')]
    public function updateCategory(Request $request, EntityManagerInterface $em, Categories $categorie): Response
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

        return $this->render('admin/categories/update.html.twig', [
            'form' => $form->createView(),
            'categorie' => $categorie
        ]);
    }

    #[Route('/categories/delete/{id}', name: 'app_categories_delete')]
    public function deleteCategory(EntityManagerInterface $em, Categories $categorie): Response
    {
        if (!$categorie) {
            throw $this->createNotFoundException("Catégorie not found");
        }

        // Vérifier si la catégorie est utilisée par des livres
        $livres = $em->getRepository(Livres::class)->findBy(['categorie' => $categorie]);

        if (count($livres) > 0) {
            $this->addFlash('error', 'Impossible de supprimer cette catégorie car elle est utilisée par ' . count($livres) . ' livre(s).');
            return $this->redirectToRoute('app_categories_all');
        }

        $em->remove($categorie);
        $em->flush();
        $this->addFlash('success', 'La catégorie a été supprimée');

        return $this->redirectToRoute('app_categories_all');
    }

    // Order Management
    #[Route('/orders', name: 'app_orders_all')]
    public function allOrders(CommandesRepository $rep, PaginatorInterface $paginator, Request $request): Response
    {
        $orders = $paginator->paginate(
            $rep->findAll(),
            $request->query->getInt('page', 1),
            10
        );
        return $this->render('admin/orders/all.html.twig', ['orders' => $orders]);
    }

    #[Route('/orders/show/{id}', name: 'app_orders_show')]
    public function showOrder(Commandes $order): Response
    {
        if (!$order) {
            throw $this->createNotFoundException("Commande not found");
        }
        return $this->render('admin/orders/detail.html.twig', ['order' => $order]);
    }

    #[Route('/orders/update-status/{id}', name: 'app_orders_update_status')]
    public function updateOrderStatus(Request $request, EntityManagerInterface $em, Commandes $order): Response
    {
        if (!$order) {
            throw $this->createNotFoundException("Commande not found");
        }

        $status = $request->request->get('status');

        if ($status) {
            $order->setStatut($status);
            $em->flush();
            $this->addFlash('success', 'Le statut de la commande a été mis à jour');
        }

        return $this->redirectToRoute('app_orders_show', ['id' => $order->getId()]);
    }

    // User Management
    #[Route('/users', name: 'app_users_all')]
    public function allUsers(UserRepository $rep, PaginatorInterface $paginator, Request $request): Response
    {
        $users = $paginator->paginate(
            $rep->findAll(),
            $request->query->getInt('page', 1),
            10
        );
        return $this->render('admin/users/all.html.twig', ['users' => $users]);
    }

    #[Route('/users/show/{id}', name: 'app_users_show')]
    public function showUser(User $user): Response
    {
        if (!$user) {
            throw $this->createNotFoundException("Utilisateur not found");
        }
        return $this->render('admin/users/detail.html.twig', ['user' => $user]);
    }

    #[Route('/users/toggle-role/{id}', name: 'app_users_toggle_role')]
    public function toggleUserRole(EntityManagerInterface $em, User $user): Response
    {
        if (!$user) {
            throw $this->createNotFoundException("Utilisateur not found");
        }

        $roles = $user->getRoles();

        if (in_array('ROLE_ADMIN', $roles)) {
            $user->setRoles(['ROLE_USER']);
            $message = 'Les droits administrateur ont été retirés';
        } else {
            $user->setRoles(['ROLE_USER', 'ROLE_ADMIN']);
            $message = 'Les droits administrateur ont été accordés';
        }

        $em->flush();
        $this->addFlash('success', $message);

        return $this->redirectToRoute('app_users_show', ['id' => $user->getId()]);
    }

    #[Route('/users/delete/{id}', name: 'app_users_delete')]
    public function deleteUser(EntityManagerInterface $em, User $user): Response
    {
        if (!$user) {
            throw $this->createNotFoundException("Utilisateur not found");
        }

        // Vérifier si l'utilisateur a des commandes
        $orders = $em->getRepository(Commandes::class)->findBy(['user' => $user]);

        if (count($orders) > 0) {
            $this->addFlash('error', 'Impossible de supprimer cet utilisateur car il a ' . count($orders) . ' commande(s).');
            return $this->redirectToRoute('app_users_all');
        }

        $em->remove($user);
        $em->flush();
        $this->addFlash('success', 'L\'utilisateur a été supprimé');

        return $this->redirectToRoute('app_users_all');
    }
}
