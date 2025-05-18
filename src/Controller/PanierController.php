<?php

namespace App\Controller;

use App\Entity\Livres;
use App\Repository\LivresRepository;
use App\Repository\CategoriesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/panier')]
class PanierController extends AbstractController
{
    private $livresRepository;
    private $categoriesRepository;

    public function __construct(
        LivresRepository $livresRepository,
        CategoriesRepository $categoriesRepository
    ) {
        $this->livresRepository = $livresRepository;
        $this->categoriesRepository = $categoriesRepository;
    }

    #[Route('/', name: 'app_panier')]
    public function index(SessionInterface $session): Response
    {
        // Récupérer le panier depuis la session
        $panier = $session->get('panier', []);
        $panierWithData = [];
        $total = 0;

        // Pour chaque livre dans le panier, récupérer les données complètes
        foreach ($panier as $id => $quantity) {
            $livre = $this->livresRepository->find($id);
            if ($livre) {
                $panierWithData[] = [
                    'livre' => $livre,
                    'quantity' => $quantity
                ];
                $total += $livre->getPrix() * $quantity;
            }
        }

        // Récupérer toutes les catégories pour le menu
        $categories = $this->categoriesRepository->findAll();

        return $this->render('panier/check_email.html.twig', [
            'items' => $panierWithData,
            'total' => $total,
            'categories' => $categories
        ]);
    }

    #[Route('/ajouter/{id}', name: 'app_panier_ajouter')]
    public function ajouter(Livres $livre, SessionInterface $session, Request $request): Response
    {
        // Récupérer l'ID du livre
        $id = $livre->getId();

        // Récupérer le panier actuel
        $panier = $session->get('panier', []);

        // Récupérer la quantité depuis la requête ou utiliser 1 par défaut
        $quantity = (int)$request->request->get('quantity', 1);

        // Ajouter ou mettre à jour la quantité
        if (!empty($panier[$id])) {
            $panier[$id] += $quantity;
        } else {
            $panier[$id] = $quantity;
        }

        // Sauvegarder le panier mis à jour dans la session
        $session->set('panier', $panier);

        $this->addFlash('success', 'Le livre a été ajouté à votre panier');

        // Rediriger vers la page précédente ou la page du panier
        $referer = $request->headers->get('referer');
        return $referer ? $this->redirect($referer) : $this->redirectToRoute('app_panier');
    }

    #[Route('/supprimer/{id}', name: 'app_panier_supprimer')]
    public function supprimer(Livres $livre, SessionInterface $session): Response
    {
        // Récupérer le panier actuel
        $panier = $session->get('panier', []);
        $id = $livre->getId();

        // Supprimer le livre du panier
        if (!empty($panier[$id])) {
            unset($panier[$id]);
        }

        // Sauvegarder le panier mis à jour
        $session->set('panier', $panier);

        $this->addFlash('success', 'Le livre a été retiré de votre panier');

        return $this->redirectToRoute('app_panier');
    }

    #[Route('/modifier/{id}', name: 'app_panier_modifier', methods: ['POST'])]
    public function modifier(Livres $livre, Request $request, SessionInterface $session): Response
    {
        // Récupérer le panier actuel
        $panier = $session->get('panier', []);
        $id = $livre->getId();
        $quantity = (int)$request->request->get('quantity');

        // Mettre à jour la quantité ou supprimer si quantité <= 0
        if ($quantity > 0) {
            $panier[$id] = $quantity;
        } else {
            unset($panier[$id]);
        }

        // Sauvegarder le panier mis à jour
        $session->set('panier', $panier);

        $this->addFlash('success', 'Votre panier a été mis à jour');

        return $this->redirectToRoute('app_panier');
    }

    #[Route('/vider', name: 'app_panier_vider')]
    public function vider(SessionInterface $session): Response
    {
        // Vider le panier
        $session->set('panier', []);

        $this->addFlash('success', 'Votre panier a été vidé');

        return $this->redirectToRoute('app_panier');
    }
}