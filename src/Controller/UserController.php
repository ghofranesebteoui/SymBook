<?php

namespace App\Controller;

use App\Entity\Livres;
use App\Entity\Categories;
use App\Entity\Commandes;
use App\Entity\CommandeLivres;
use App\Entity\User;
use App\Entity\Avis;
use App\Repository\LivresRepository;
use App\Repository\CategoriesRepository;
use App\Repository\CommandesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Dompdf\Options;
use Dompdf\Dompdf;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mailer\MailerInterface;

class UserController extends AbstractController
{
    private $categoriesRepository;
    private $livresRepository;
    private $entityManager;
    private $commandesRepository;

    public function __construct(
        CategoriesRepository $categoriesRepository,
        LivresRepository $livresRepository,
        EntityManagerInterface $entityManager,
        CommandesRepository $commandesRepository
    ) {
        $this->categoriesRepository = $categoriesRepository;
        $this->livresRepository = $livresRepository;
        $this->entityManager = $entityManager;
        $this->commandesRepository = $commandesRepository;
    }

    #[Route('/', name: 'app_home')]
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        // Récupérer les livres mis en avant (par exemple, les 8 derniers)
        $livresQuery = $this->livresRepository->createQueryBuilder('l')
            ->orderBy('l.id', 'DESC')
            ->getQuery();

        $livres = $paginator->paginate(
            $livresQuery,
            $request->query->getInt('page', 1),
            8
        );

        // Récupérer toutes les catégories
        $categories = $this->categoriesRepository->findAll();

        return $this->render('home/index.html.twig', [
            'livres' => $livres,
            'categories' => $categories
        ]);
    }

    #[Route('/livres', name: 'app_livres_all')]
    public function allLivres(Request $request, PaginatorInterface $paginator): Response
    {
        // Récupérer les paramètres de recherche et de filtrage
        $search = $request->query->get('search');
        $categorieId = $request->query->get('categorie');
        $minPrice = $request->query->get('min_price');
        $maxPrice = $request->query->get('max_price');
        $sort = $request->query->get('sort', 'titre_asc');

        // Créer la requête de base
        $queryBuilder = $this->livresRepository->createQueryBuilder('l');

        // Appliquer les filtres
        if ($search) {
            $queryBuilder
                ->where('l.titre LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($categorieId) {
            $queryBuilder
                ->andWhere('l.categorie = :categorieId')
                ->setParameter('categorieId', $categorieId);
        }

        if ($minPrice) {
            $queryBuilder
                ->andWhere('l.prix >= :minPrice')
                ->setParameter('minPrice', $minPrice);
        }

        if ($maxPrice) {
            $queryBuilder
                ->andWhere('l.prix <= :maxPrice')
                ->setParameter('maxPrice', $maxPrice);
        }

        // Trier les résultats
        switch ($sort) {
            case 'titre_desc':
                $queryBuilder->orderBy('l.titre', 'DESC');
                break;
            case 'prix_asc':
                $queryBuilder->orderBy('l.prix', 'ASC');
                break;
            case 'prix_desc':
                $queryBuilder->orderBy('l.prix', 'DESC');
                break;
            case 'titre_asc':
            default:
                $queryBuilder->orderBy('l.titre', 'ASC');
                break;
        }

        // Paginer les résultats
        $pagination = $paginator->paginate(
            $queryBuilder->getQuery(),
            $request->query->getInt('page', 1),
            12 // Nombre d'éléments par page
        );

        // Récupérer toutes les catégories pour le filtre
        $categories = $this->categoriesRepository->findAll();

        return $this->render('user/livres.html.twig', [
            'livres' => $pagination,
            'search' => $search,
            'categorieId' => $categorieId,
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'sort' => $sort,
            'categories' => $categories
        ]);
    }

    #[Route('/livre/{id}', name: 'app_livre_detail')]
    public function livreDetail(Livres $livre): Response
    {
        if (!$livre) {
            throw $this->createNotFoundException("Livre non trouvé");
        }

        // Récupérer des livres similaires (même catégorie)
        $similarBooks = $this->livresRepository->createQueryBuilder('l')
            ->where('l.categorie = :categorieId')
            ->andWhere('l.id != :livreId')
            ->setParameter('categorieId', $livre->getCategorie()->getId())
            ->setParameter('livreId', $livre->getId())
            ->getQuery()
            ->getResult();

        // Mélanger en PHP
        shuffle($similarBooks);

        // Garder seulement les 4 premiers
        $similarBooks = array_slice($similarBooks, 0, 4);

        // Récupérer les avis sur ce livre
        $reviews = $this->entityManager->getRepository(Avis::class)->findBy(
            ['livre' => $livre],
            ['createdAt' => 'DESC']
        );

        // Récupérer toutes les catégories pour le menu
        $categories = $this->categoriesRepository->findAll();

        return $this->render('user/livre_detail.html.twig', [
            'livre' => $livre,
            'similarBooks' => $similarBooks,
            'reviews' => $reviews,
            'categories' => $categories
        ]);
    }


    #[Route('/livre/{id}/review', name: 'app_livre_review', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function livreReview(Livres $livre, Request $request): Response
    {
        $rating = $request->request->getInt('rating');
        $comment = $request->request->get('comment');

        if ($rating < 1 || $rating > 5) {
            $this->addFlash('error', 'La note doit être comprise entre 1 et 5.');
            return $this->redirectToRoute('app_livre_detail', ['id' => $livre->getId()]);
        }

        // Vérifier si l'utilisateur a déjà laissé un avis pour ce livre
        $existingReview = $this->entityManager->getRepository(Avis::class)->findOneBy([
            'user' => $this->getUser(),
            'livre' => $livre
        ]);

        if ($existingReview) {
            // Mettre à jour l'avis existant
            $existingReview->setRating($rating);
            $existingReview->setComment($comment);
            $existingReview->setUpdatedAt(new \DateTimeImmutable());
        } else {
            // Créer un nouvel avis
            $avis = new Avis();
            $avis->setUser($this->getUser());
            $avis->setLivre($livre);
            $avis->setRating($rating);
            $avis->setComment($comment);
            $avis->setCreatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($avis);
        }

        $this->entityManager->flush();

        $this->addFlash('success', 'Votre avis a été enregistré avec succès.');
        return $this->redirectToRoute('app_livre_detail', ['id' => $livre->getId()]);
    }

    #[Route('/categorie/{id}', name: 'app_categorie_livres')]
    public function categorieLivres(Categories $categorie, Request $request, PaginatorInterface $paginator): Response
    {
        if (!$categorie) {
            throw $this->createNotFoundException("Catégorie non trouvée");
        }

        // Récupérer les livres de cette catégorie
        $queryBuilder = $this->livresRepository->createQueryBuilder('l')
            ->where('l.categorie = :categorieId')
            ->setParameter('categorieId', $categorie->getId())
            ->orderBy('l.titre', 'ASC');

        // Paginer les résultats
        $pagination = $paginator->paginate(
            $queryBuilder->getQuery(),
            $request->query->getInt('page', 1),
            12 // Nombre d'éléments par page
        );

        // Récupérer toutes les catégories pour le menu
        $categories = $this->categoriesRepository->findAll();

        return $this->render('user/categorie_livres.html.twig', [
            'categorie' => $categorie,
            'livres' => $pagination,
            'categories' => $categories
        ]);
    }

    #[Route('/categories', name: 'app_categories_all')]
    public function allCategories(): Response
    {
        $categories = $this->categoriesRepository->findAll();

        return $this->render('user/categories.html.twig', [
            'categories' => $categories
        ]);
    }

    #[Route('/panier', name: 'app_panier')]
    public function panier(SessionInterface $session): Response
    {
        $panier = $session->get('panier', []);
        $panierWithData = [];
        $sousTotal = 0;

        foreach ($panier as $id => $quantity) {
            $livre = $this->livresRepository->find($id);
            if ($livre) {
                $panierWithData[] = [
                    'livre' => $livre,
                    'quantity' => $quantity
                ];
                $sousTotal += $livre->getPrix() * $quantity;
            }
        }

        $fraisLivraison = 7.99;
        $tva = $sousTotal * 0.20;
        $total = $sousTotal + $fraisLivraison + $tva;

        return $this->render('user/panier.html.twig', [
            'panier' => $panierWithData,
            'sousTotal' => $sousTotal,
            'fraisLivraison' => $fraisLivraison,
            'tva' => $tva,
            'total' => $total,
            'categories' => $this->categoriesRepository->findAll()
        ]);
    }

    #[Route('/panier/ajouter/{id}', name: 'app_panier_ajouter')]
    public function ajouterAuPanier(Livres $livre, Request $request, SessionInterface $session): Response
    {
        $panier = $session->get('panier', []);
        $id = $livre->getId();
        $quantity = (int) $request->request->get('quantity', 1);

        if (!empty($panier[$id])) {
            $panier[$id] += $quantity;
        } else {
            $panier[$id] = $quantity;
        }

        $session->set('panier', $panier);

        $this->addFlash('success', 'Le livre a été ajouté à votre panier.');

        return $this->redirectToRoute('app_panier');
    }

    #[Route('/panier/modifier/{id}', name: 'app_panier_modifier')]
    public function modifierPanier(Livres $livre, Request $request, SessionInterface $session): Response
    {
        $panier = $session->get('panier', []);
        $id = $livre->getId();
        $quantity = (int) $request->request->get('quantity', 1);

        if ($quantity > 0) {
            $panier[$id] = $quantity;
        } else {
            unset($panier[$id]);
        }

        $session->set('panier', $panier);

        return $this->redirectToRoute('app_panier');
    }

    #[Route('/panier/supprimer/{id}', name: 'app_panier_supprimer')]
    public function supprimerDuPanier(Livres $livre, SessionInterface $session): Response
    {
        $panier = $session->get('panier', []);
        $id = $livre->getId();

        if (!empty($panier[$id])) {
            unset($panier[$id]);
        }

        $session->set('panier', $panier);

        return $this->redirectToRoute('app_panier');
    }

    #[Route('/checkout', name: 'app_checkout')]
    #[IsGranted('ROLE_USER')]
    public function checkout(SessionInterface $session): Response
    {
        $panier = $session->get('panier', []);

        if (empty($panier)) {
            $this->addFlash('warning', 'Votre panier est vide.');
            return $this->redirectToRoute('app_panier');
        }

        $panierWithData = [];
        $sousTotal = 0;

        foreach ($panier as $id => $quantity) {
            $livre = $this->livresRepository->find($id);
            if ($livre) {
                $panierWithData[] = [
                    'livre' => $livre,
                    'quantity' => $quantity
                ];
                $sousTotal += $livre->getPrix() * $quantity;
            }
        }

        $fraisLivraison = 7.99;
        $tva = $sousTotal * 0.20;
        $total = $sousTotal + $fraisLivraison + $tva;

        return $this->render('user/checkout.html.twig', [
            'panier' => $panierWithData,
            'sousTotal' => $sousTotal,
            'fraisLivraison' => $fraisLivraison,
            'tva' => $tva,
            'total' => $total,
            'categories' => $this->categoriesRepository->findAll()
        ]);
    }

    #[Route('/checkout/process', name: 'app_checkout_process', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function checkoutProcess(Request $request, SessionInterface $session): Response
    {
        $panier = $session->get('panier', []);

        if (empty($panier)) {
            $this->addFlash('warning', 'Votre panier est vide.');
            return $this->redirectToRoute('app_panier');
        }

        // Récupérer l'utilisateur connecté
        $user = $this->getUser();

        // Créer une nouvelle commande
        $commande = new Commandes();
        $commande->setUser($user);
        $commande->setDate(new \DateTime());
        $commande->setStatut('pending'); // Utiliser setStatut au lieu de setStatus
        $commande->setReference(uniqid('CMD-'));

        // Copier les informations de l'utilisateur dans la commande
        $commande->setPrenom($request->request->get('prenom'));
        $commande->setNom($request->request->get('nom'));
        $commande->setEmail($request->request->get('email'));
        $commande->setTelephone($request->request->get('telephone'));
        $commande->setAdresse($request->request->get('adresse'));
        $commande->setCodePostal($request->request->get('codePostal'));
        $commande->setVille($request->request->get('ville'));

        // Récupérer le gouvernorat selon le mode de paiement
        $paymentMethod = $request->request->get('paymentMethod');
        if ($paymentMethod === 'cashOnDelivery') {
            $commande->setGouvernorat($request->request->get('gouvernorat'));
        }

        // Définir la méthode de paiement
        $commande->setMethodePaiement($paymentMethod);

        // Calcul des montants
        $sousTotal = 0;

        // Frais de livraison selon le gouvernorat si paiement à la livraison
        $fraisLivraison = 7.99; // Valeur par défaut
        if ($paymentMethod === 'cashOnDelivery' && $request->request->get('gouvernorat')) {
            $fraisLivraison = $this->calculerFraisLivraison($request->request->get('gouvernorat'));
        }

        // Ajouter les livres à la commande
        foreach ($panier as $id => $quantity) {
            $livre = $this->livresRepository->find($id);
            if ($livre) {
                $commandeLivre = new CommandeLivres();
                $commandeLivre->setCommande($commande);
                $commandeLivre->setLivre($livre);
                $commandeLivre->setQuantite($quantity);
                $commandeLivre->setPrix($livre->getPrix());

                $this->entityManager->persist($commandeLivre);

                $sousTotal += $livre->getPrix() * $quantity;
            }
        }

        $tva = $sousTotal * 0.20;
        $total = $sousTotal + $fraisLivraison + $tva;

        // Enregistrer les montants dans la commande
        $commande->setSousTotal($sousTotal);
        $commande->setFraisLivraison($fraisLivraison);
        $commande->setTva($tva);
        $commande->setMontantTotal($total);

        // Persister la commande
        $this->entityManager->persist($commande);
        $this->entityManager->flush();

        // Sauvegarder l'adresse si demandé
        if ($request->request->get('saveAddress')) {
            $user->setPrenom($request->request->get('prenom'));
            $user->setNom($request->request->get('nom'));
            $user->setTelephone($request->request->get('telephone'));
            $user->setAdresse($request->request->get('adresse'));
            $user->setCodePostal($request->request->get('codePostal'));
            $user->setVille($request->request->get('ville'));
            if ($paymentMethod === 'cashOnDelivery') {
                $user->setGouvernorat($request->request->get('gouvernorat'));
            }

            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }

        // Si paiement par carte, rediriger vers la page de paiement
        if ($paymentMethod === 'card') {
            return $this->redirectToRoute('app_commande_paiement', ['id' => $commande->getId()]);
        }

        // Si paiement à la livraison, finaliser directement la commande
        if ($paymentMethod === 'cashOnDelivery') {
            // Mettre à jour le statut de la commande
            $commande->setStatut('pending_delivery');
            $this->entityManager->persist($commande);
            $this->entityManager->flush();

            // Vider le panier
            $session->remove('panier');

            // Rediriger vers la page de confirmation
            return $this->redirectToRoute('app_commande_confirmation', ['id' => $commande->getId()]);
        }

        // Redirection par défaut
        return $this->redirectToRoute('app_commande_paiement', ['id' => $commande->getId()]);
    }

    #[Route('/commande/paiement/{id}', name: 'app_commande_paiement')]
    #[IsGranted('ROLE_USER')]
    public function paiement(Commandes $commande): Response
    {
        // Vérifier que la commande appartient à l'utilisateur connecté
        if ($commande->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à cette commande.');
        }

        return $this->render('user/paiement.html.twig', [
            'commande' => $commande,
            'categories' => $this->categoriesRepository->findAll()
        ]);
    }

    #[Route('/commande/finaliser/{id}', name: 'app_commande_finaliser', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function finaliserCommande(Commandes $commande, Request $request, SessionInterface $session, MailerInterface $mailer): Response
    {
        // Vérifier que la commande appartient à l'utilisateur connecté
        if ($commande->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à cette commande.');
        }

        // Mettre à jour le statut de la commande
        $commande->setStatut('completed'); // Utiliser setStatut au lieu de setStatus
        $commande->setMethodePaiement('card');
        $commande->setDatePaiement(new \DateTime());

        $this->entityManager->persist($commande);
        $this->entityManager->flush();

        // Envoyer l'email de confirmation
        $this->envoyerEmailConfirmation($commande, $mailer);

        // Vider le panier
        $session->remove('panier');

        // Rediriger vers la page de confirmation
        return $this->redirectToRoute('app_commande_confirmation', ['id' => $commande->getId()]);
    }

    #[Route('/commande/confirmation/{id}', name: 'app_commande_confirmation')]
    #[IsGranted('ROLE_USER')]
    public function confirmation(Commandes $commande): Response
    {
        // Vérifier que la commande appartient à l'utilisateur connecté
        if ($commande->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à cette commande.');
        }

        // Suggestions de livres triées par ID décroissant (par exemple)
        $suggestions = $this->livresRepository->createQueryBuilder('l')
            ->orderBy('l.id', 'DESC')
            ->setMaxResults(4)
            ->getQuery()
            ->getResult();

        return $this->render('user/confirmation.html.twig', [
            'commande' => $commande,
            'suggestions' => $suggestions,
            'categories' => $this->categoriesRepository->findAll()
        ]);
    }


    #[Route('/commande/historique', name: 'app_commande_historique')]
    #[IsGranted('ROLE_USER')]
    public function historiqueCommandes(Request $request, PaginatorInterface $paginator): Response
    {
        $user = $this->getUser();

        $queryBuilder = $this->commandesRepository->createQueryBuilder('c')
            ->where('c.user = :user')
            ->setParameter('user', $user)
            ->orderBy('c.date', 'DESC');

        $pagination = $paginator->paginate(
            $queryBuilder->getQuery(),
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('user/historique.html.twig', [
            'commandes' => $pagination,
            'categories' => $this->categoriesRepository->findAll()
        ]);
    }

    #[Route('/commande/details/{id}', name: 'app_commande_details')]
    #[IsGranted('ROLE_USER')]
    public function detailsCommande(Commandes $commande): Response
    {
        // Vérifier que la commande appartient à l'utilisateur connecté
        if ($commande->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à cette commande.');
        }

        return $this->render('user/commande_detail.html.twig', [
            'commande' => $commande,
            'categories' => $this->categoriesRepository->findAll()
        ]);
    }

    #[Route('/commande/annuler/{id}', name: 'app_commande_annuler')]
    #[IsGranted('ROLE_USER')]
    public function annulerCommande(Commandes $commande): Response
    {
        // Vérifier que la commande appartient à l'utilisateur connecté
        if ($commande->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à cette commande.');
        }

        // Vérifier que la commande est en attente
        if ($commande->getStatut() !== 'pending') {
            $this->addFlash('warning', 'Vous ne pouvez annuler que les commandes en attente de paiement.');
            return $this->redirectToRoute('app_commande_historique');
        }

        // Mettre à jour le statut de la commande
        $commande->setStatut('cancelled');
        $this->entityManager->persist($commande);
        $this->entityManager->flush();

        $this->addFlash('success', 'Votre commande a été annulée avec succès.');
        return $this->redirectToRoute('app_commande_historique');
    }

    #[Route('/commande/review/{id}', name: 'app_commande_review', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function reviewCommande(Commandes $commande, Request $request): Response
    {
        // Vérifier que la commande appartient à l'utilisateur connecté
        if ($commande->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à cette commande.');
        }

        // Vérifier que la commande est terminée
        if ($commande->getStatut() !== 'completed') {
            $this->addFlash('warning', 'Vous ne pouvez évaluer que les commandes terminées.');
            return $this->redirectToRoute('app_commande_historique');
        }

        $rating = $request->request->getInt('rating');
        $comment = $request->request->get('comment');

        if ($rating < 1 || $rating > 5) {
            $this->addFlash('error', 'La note doit être comprise entre 1 et 5.');
            return $this->redirectToRoute('app_commande_details', ['id' => $commande->getId()]);
        }

        // Créer un avis pour chaque livre de la commande
        foreach ($commande->getItems() as $item) {
            $livre = $item->getLivre();

            // Vérifier si l'utilisateur a déjà laissé un avis pour ce livre
            $existingReview = $this->entityManager->getRepository(Avis::class)->findOneBy([
                'user' => $this->getUser(),
                'livre' => $livre
            ]);

            if ($existingReview) {
                // Mettre à jour l'avis existant
                $existingReview->setRating($rating);
                $existingReview->setComment($comment);
                $existingReview->setUpdatedAt(new \DateTimeImmutable());
            } else {
                // Créer un nouvel avis
                $avis = new Avis();
                $avis->setUser($this->getUser());
                $avis->setLivre($livre);
                $avis->setRating($rating);
                $avis->setComment($comment);
                $avis->setCreatedAt(new \DateTimeImmutable());

                $this->entityManager->persist($avis);
            }
        }

        $this->entityManager->flush();

        $this->addFlash('success', 'Votre évaluation a été enregistrée avec succès.');
        return $this->redirectToRoute('app_commande_details', ['id' => $commande->getId()]);
    }

    #[Route('/profile', name: 'app_user_profile')]
    #[IsGranted('ROLE_USER')]
    public function profile(): Response
    {
        return $this->render('user/profile.html.twig', [
            'categories' => $this->categoriesRepository->findAll()
        ]);
    }

    #[Route('/profile/update', name: 'app_user_profile_update', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function updateProfile(Request $request): Response
    {
        $user = $this->getUser();

        $user->setPrenom($request->request->get('prenom'));
        $user->setNom($request->request->get('nom'));
        $user->setTelephone($request->request->get('telephone'));

        if (method_exists($user, 'setUpdatedAt')) {
            $user->setUpdatedAt(new \DateTimeImmutable());
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->addFlash('success', 'Vos informations personnelles ont été mises à jour avec succès.');

        return $this->redirectToRoute('app_user_profile');
    }

    #[Route('/profile/address/update', name: 'app_user_address_update', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function updateAddress(Request $request): Response
    {
        $user = $this->getUser();

        $user->setAdresse($request->request->get('adresse'));
        $user->setCodePostal($request->request->get('codePostal'));
        $user->setVille($request->request->get('ville'));

        if (method_exists($user, 'setUpdatedAt')) {
            $user->setUpdatedAt(new \DateTimeImmutable());
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->addFlash('success', 'Votre adresse a été mise à jour avec succès.');

        return $this->redirectToRoute('app_user_profile');
    }

    #[Route('/profile/password/update', name: 'app_user_password_update', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function updatePassword(Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = $this->getUser();

        $currentPassword = $request->request->get('currentPassword');
        $newPassword = $request->request->get('newPassword');
        $confirmPassword = $request->request->get('confirmPassword');

        // Vérifier que le mot de passe actuel est correct
        if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
            $this->addFlash('error', 'Le mot de passe actuel est incorrect.');
            return $this->redirectToRoute('app_user_profile');
        }

        // Vérifier que les nouveaux mots de passe correspondent
        if ($newPassword !== $confirmPassword) {
            $this->addFlash('error', 'Les nouveaux mots de passe ne correspondent pas.');
            return $this->redirectToRoute('app_user_profile');
        }

        // Vérifier que le nouveau mot de passe est assez fort
        if (strlen($newPassword) < 8) {
            $this->addFlash('error', 'Le nouveau mot de passe doit contenir au moins 8 caractères.');
            return $this->redirectToRoute('app_user_profile');
        }

        // Encoder le nouveau mot de passe
        $encodedPassword = $passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($encodedPassword);

        if (method_exists($user, 'setUpdatedAt')) {
            $user->setUpdatedAt(new \DateTimeImmutable());
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->addFlash('success', 'Votre mot de passe a été mis à jour avec succès.');

        return $this->redirectToRoute('app_user_profile');
    }

    #[Route('/profile/preferences/update', name: 'app_user_preferences_update', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function updatePreferences(Request $request): Response
    {
        $user = $this->getUser();

        $user->setNewsletter($request->request->has('newsletter'));
        $user->setNotifications($request->request->has('notifications'));
        $user->setPromotions($request->request->has('promotions'));

        if (method_exists($user, 'setUpdatedAt')) {
            $user->setUpdatedAt(new \DateTimeImmutable());
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->addFlash('success', 'Vos préférences ont été mises à jour avec succès.');

        return $this->redirectToRoute('app_user_profile');
    }



    private function calculerFraisLivraison(string $gouvernorat): float
    {
        // Frais de livraison selon le gouvernorat
        $fraisParGouvernorat = [
            'Tunis' => 7.00,
            'Ariana' => 7.00,
            'Ben Arous' => 7.00,
            'La Manouba' => 7.00,
            'Nabeul' => 8.00,
            'Bizerte' => 8.00,
            'Sousse' => 9.00,
            'Monastir' => 9.00,
            'Sfax' => 10.00,
            'Gabès' => 12.00,
            'Médenine' => 12.00,
            'Kairouan' => 10.00,
            'Béja' => 9.00,
            'Jendouba' => 10.00,
            'Le Kef' => 10.00,
            'Siliana' => 10.00,
            'Zaghouan' => 8.00,
            'Mahdia' => 9.00,
            'Sidi Bouzid' => 11.00,
            'Gafsa' => 12.00,
            'Tozeur' => 13.00,
            'Kébili' => 13.00,
            'Tataouine' => 14.00,
            'Kasserine' => 11.00,
        ];

        return $fraisParGouvernorat[$gouvernorat] ?? 10.00; // Valeur par défaut si le gouvernorat n'est pas trouvé
    }



    private function envoyerEmailConfirmation(Commandes $commande, MailerInterface $mailer): void
    {
        $email = (new Email())
            ->from(new Address('noreply@symbook.com', 'SymBook'))
            ->to($commande->getEmail())
            ->subject('Confirmation de votre commande #' . $commande->getReference())
            ->html($this->renderView('emails/confirmation_commande.html.twig', [
                'commande' => $commande
            ]));

        $mailer->send($email);
    }

    #[Route('/commande/pdf/{id}', name: 'app_commande_pdf')]
    #[IsGranted('ROLE_USER')]
    public function generatePdf(Commandes $commande): Response
    {
        // Vérifier que la commande appartient à l'utilisateur connecté
        if ($commande->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à cette commande.');
        }

        // Configurer Dompdf
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);

        // Générer le HTML pour le PDF
        $html = $this->renderView('pdf/commande.html.twig', [
            'commande' => $commande
        ]);

        // Charger le HTML dans Dompdf
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Générer le PDF
        $response = new Response($dompdf->output());
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', 'attachment; filename="commande-' . $commande->getReference() . '.pdf"');

        return $response;
    }
}
