<?php

namespace App\Controller;

use AllowDynamicProperties;
use App\Entity\Commandes;
use App\Entity\CommandeLivres;
use App\Repository\CategoriesRepository;
use App\Repository\CommandesRepository;
use App\Repository\LivresRepository;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AllowDynamicProperties] #[Route('/commande')]
#[IsGranted('ROLE_USER')]
class CommandeController extends AbstractController
{
    private $entityManager;
    private $categoriesRepository;
    private $commandesRepository;
    private MailerInterface $mailer;
    private LivresRepository $livresRepository;
    private $logger;
    private $urlGenerator;

    public function __construct(
        EntityManagerInterface $entityManager,
        LivresRepository $livresRepository,
        CategoriesRepository $categoriesRepository,
        CommandesRepository $commandesRepository,
        MailerInterface $mailer
    ) {
        $this->entityManager = $entityManager;
        $this->livresRepository = $livresRepository;
        $this->categoriesRepository = $categoriesRepository;
        $this->commandesRepository = $commandesRepository;
        $this->mailer = $mailer;
    }


    #[Route('/passer', name: 'app_commande_passer')]
    public function passerCommande(SessionInterface $session): Response
    {
        // Vérifier si le panier est vide
        $panier = $session->get('panier', []);
        if (empty($panier)) {
            $this->addFlash('error', 'Votre panier est vide');
            return $this->redirectToRoute('app_panier');
        }

        // Récupérer l'utilisateur connecté
        $user = $this->getUser();

        // Récupérer toutes les catégories pour le menu
        $categories = $this->categoriesRepository->findAll();

        return $this->render('commande/passer.html.twig', [
            'categories' => $categories
        ]);
    }

    #[Route('/confirmer', name: 'app_commande_confirmer', methods: ['POST'])]
    public function confirmerCommande(Request $request, SessionInterface $session): Response
    {
        // Vérifier si le panier est vide
        $panier = $session->get('panier', []);
        if (empty($panier)) {
            $this->addFlash('error', 'Votre panier est vide');
            return $this->redirectToRoute('app_panier');
        }

        // Récupérer l'utilisateur connecté
        $user = $this->getUser();

        // Récupérer les informations de livraison du formulaire
        $prenom = $request->request->get('prenom');
        $nom = $request->request->get('nom');
        $adresse = $request->request->get('adresse');
        $codePostal = $request->request->get('codePostal');
        $ville = $request->request->get('ville');
        $telephone = $request->request->get('telephone');
        $email = $request->request->get('email');
        $gouvernorat = $request->request->get('gouvernorat');

        // Récupérer la méthode de paiement
        $paymentMethod = $request->request->get('paymentMethod', 'card');

        // Créer une nouvelle commande
        $commande = new Commandes();
        $commande->setReference('REF-' . date('YmdHis') . '-' . uniqid()); // Set unique reference
        $commande->setUser($user);
        $commande->setDate(new \DateTime());

        // Enregistrer les informations de livraison
        $commande->setPrenom($prenom);
        $commande->setNom($nom);
        $commande->setAdresse($adresse);
        $commande->setCodePostal($codePostal);
        $commande->setVille($ville);
        $commande->setTelephone($telephone);
        $commande->setEmail($email);
        if ($gouvernorat) {
            $commande->setGouvernorat($gouvernorat);
        }

        // Vérifier si on doit finaliser directement la commande
        $directFinalize = $request->request->get('directFinalize');

        if ($directFinalize === 'true') {
            // Marquer la commande comme payée directement
            $commande->setStatut('Payée');
            $commande->setMethodePaiement($paymentMethod);
            $commande->setDatePaiement(new \DateTime());
        } else {
            // Comportement par défaut
            $commande->setStatut('En attente de paiement');
        }

        $this->entityManager->persist($commande);

        // Ajouter les livres à la commande
        $livresRepository = $this->entityManager->getRepository('App\Entity\Livres');

        // Calculer les totaux
        $sousTotal = 0;
        $fraisLivraison = 4.99; // Frais de livraison fixes

        foreach ($panier as $livreId => $quantite) {
            $livre = $livresRepository->find($livreId);

            if ($livre) {
                $commandeLivre = new CommandeLivres();
                $commandeLivre->setCommande($commande);
                $commandeLivre->setLivre($livre);
                $commandeLivre->setQuantite($quantite);
                $commandeLivre->setPrice((string)$livre->getPrix());

                $sousTotal += $livre->getPrix() * $quantite;

                $this->entityManager->persist($commandeLivre);
            }
        }

        // Calculer la TVA et le total
        $tva = $sousTotal * 0.2;
        $total = $sousTotal + $fraisLivraison + $tva;

        // Enregistrer les montants
        $commande->setSousTotal($sousTotal);
        $commande->setFraisLivraison($fraisLivraison);
        $commande->setTva($tva);
        $commande->setMontantTotal($total);

        $this->entityManager->flush();

        // Vider le panier
        $session->set('panier', []);

        // Si la commande est déjà finalisée, rediriger vers la confirmation
        if ($directFinalize === 'true') {
            // Envoyer l'email de confirmation si nécessaire
            // $this->sendOrderConfirmationEmail($commande, $mailer);

            return $this->redirectToRoute('app_commande_confirmation', [
                'id' => $commande->getId()
            ]);
        }

        // Sinon, rediriger vers la page de paiement (comportement par défaut)
        return $this->redirectToRoute('app_commande_paiement', [
            'id' => $commande->getId()
        ]);
    }




    #[Route('/commandes', name: 'app_commande_historique')]
    public function historique(CommandesRepository $commandesRepository): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $commandes = $commandesRepository->findBy(['user' => $user], ['date' => 'DESC']);

        return $this->render('user/historique.html.twig', [
            'commandes' => $commandes,
        ]);
    }

    #[Route('/commande/{id}/details', name: 'app_commande_details')]
    public function details(Commandes $commande): Response
    {
        $user = $this->getUser();

        if (!$user || $commande->getUser() !== $user) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('user/commande_details.html.twig', [
            'commande' => $commande,
            'paymentMethod' => $commande->getMethodePaiement() ?: 'card', // Utiliser la méthode de paiement de la commande ou 'card' par défaut
        ]);
    }
    #[Route('/commande/{id}/paiement', name: 'app_commande_paiement')]
    public function paiement(Commandes $commande, Request $request): Response
    {
        $user = $this->getUser();

        if (!$user || $commande->getUser() !== $user) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer la méthode de paiement depuis la requête (si elle existe)
        $paymentMethod = $request->query->get('method');

        return $this->render('user/paiement.html.twig', [
            'commande' => $commande,
            'paymentMethod' => $paymentMethod,
        ]);
    }

    #[Route('/commande/{id}/finaliser', name: 'app_commande_finaliser')]
    public function finaliserCommande(Commandes $commande, Request $request, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        $user = $this->getUser();

        if (!$user || $commande->getUser() !== $user) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer la méthode de paiement
        $paymentMethod = $request->request->get('paymentMethod', 'card');

        // Mettre à jour la commande
        $commande->setMethodePaiement($paymentMethod);

        // Pour les deux méthodes de paiement, on considère la commande comme payée
        // Cela évite d'afficher le bouton "Payer" dans l'historique
        $commande->setStatut('Payée');
        $commande->setDatePaiement(new \DateTime());

        // Si c'est un paiement à la livraison, on ajoute une note
        if ($paymentMethod === 'cashOnDelivery') {
            // Optionnel : ajouter une note pour indiquer que c'est un paiement à la livraison
            // Si vous avez un champ notes dans votre entité Commandes
            if (method_exists($commande, 'setNotes')) {
                $commande->setNotes('Paiement à la livraison');
            }
        }

        $entityManager->flush();

        // Envoyer l'email de confirmation
        $this->sendOrderConfirmationEmail($commande, $mailer);

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

        // Solution 1: Utiliser une requête native SQL pour les suggestions
        $entityManager = $this->entityManager;
        $connection = $entityManager->getConnection();
        $sql = 'SELECT l.* FROM livres l ORDER BY RAND() LIMIT 4';
        $stmt = $connection->prepare($sql);
        $resultSet = $stmt->executeQuery();
        $randomLivres = $resultSet->fetchAllAssociative();

        // Convertir les résultats en entités Livres
        $suggestions = [];
        foreach ($randomLivres as $livreData) {
            $livre = $this->livresRepository->find($livreData['id']);
            if ($livre) {
                $suggestions[] = $livre;
            }
        }

        // Alternative: Si vous préférez utiliser PHP pour le mélange aléatoire
        // Décommentez ces lignes et commentez le code ci-dessus
        /*
        $allLivres = $this->livresRepository->findAll();
        shuffle($allLivres);
        $suggestions = array_slice($allLivres, 0, 4);
        */

        // Envoyer l'email de confirmation
        $this->sendOrderConfirmationEmail($commande, $this->mailer);

        return $this->render('user/confirmation.html.twig', [
            'commande' => $commande,
            'suggestions' => $suggestions,
            'categories' => $this->categoriesRepository->findAll()
        ]);
    }


    #[Route('/commande/{id}/pdf', name: 'app_commande_pdf')]
    public function generatePdf(Commandes $commande): Response
    {
        $user = $this->getUser();

        if (!$user || $commande->getUser() !== $user) {
            return $this->redirectToRoute('app_login');
        }

        // Configurer Dompdf
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);

        $dompdf = new Dompdf($options);

        // Générer le HTML pour le PDF
        $html = $this->renderView('pdf/commande.html.twig', [
            'commande' => $commande,
        ]);

        // Charger le HTML dans Dompdf
        $dompdf->loadHtml($html);

        // Définir le format du papier
        $dompdf->setPaper('A4', 'portrait');

        // Rendre le PDF
        $dompdf->render();

        // Générer un nom de fichier
        $filename = 'commande_' . $commande->getReference() . '.pdf';

        // Retourner le PDF comme réponse
        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }

    private function sendOrderConfirmationEmail(Commandes $commande): void
    {
        $user = $commande->getUser();
        $email = $commande->getEmail() ?: $user->getEmail();

        // Générer l'URL de détails de la commande
        $orderDetailsUrl = $this->urlGenerator->generate(
            'app_commande_details',
            ['id' => $commande->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        try {
            $this->logger->info('Tentative d\'envoi d\'email de confirmation de commande à: ' . $email);

            // Créer le contenu HTML avec les détails de la commande
            $htmlContent = $this->renderView('emails/confirmation_commande.html.twig', [
                'commande' => $commande,
                'orderDetailsUrl' => $orderDetailsUrl,
                'user' => $user
            ]);

            // Créer le contenu texte comme alternative
            $textContent = "Bonjour " . $commande->getPrenom() . " " . $commande->getNom() . ",\n\n" .
                "Merci pour votre commande sur SymBook !\n\n" .
                "Votre commande #" . $commande->getReference() . " a été confirmée et est en cours de traitement.\n\n" .
                "Détails de la commande :\n" .
                "Date : " . $commande->getDate()->format('d/m/Y H:i') . "\n" .
                "Statut : " . $commande->getStatut() . "\n" .
                "Total : " . number_format($commande->getMontantTotal(), 2, ',', ' ') . " DT\n\n" .
                "Pour consulter les détails de votre commande, cliquez sur le lien suivant :\n" .
                $orderDetailsUrl . "\n\n" .
                "Cordialement,\n" .
                "L'équipe SymBook";

            $email = (new Email())
                ->from(new Address('ghofranesebteoui16@gmail.com', 'SymBook'))
                ->to($email)
                ->subject('Confirmation de votre commande #' . $commande->getReference())
                ->html($htmlContent)
                ->text($textContent);

            $this->mailer->send($email);
            $this->logger->info('Email de confirmation de commande envoyé avec succès à: ' . $email);

            // Optionnel : ajouter un flash message si vous êtes dans un contexte où c'est pertinent
            // $this->addFlash('success', 'Un email de confirmation a été envoyé à votre adresse email.');

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Échec du transport email: ' . $e->getMessage(), [
                'exception' => $e,
                'email' => $email,
                'commande_id' => $commande->getId()
            ]);
            // $this->addFlash('warning', 'L\'email de confirmation n\'a pas pu être envoyé (erreur technique).');

        } catch (\Exception $e) {
            $this->logger->critical('Erreur inattendue lors de l\'envoi de l\'email: ' . $e->getMessage(), [
                'exception' => $e,
                'email' => $email,
                'commande_id' => $commande->getId()
            ]);
            // $this->addFlash('warning', 'Une erreur inattendue est survenue lors de l\'envoi de l\'email de confirmation.');
        }
    }



}