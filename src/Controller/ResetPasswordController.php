<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;
use Psr\Log\LoggerInterface;

#[Route('/reset-password')]
class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Affiche et traite le formulaire pour demander un lien de réinitialisation de mot de passe.
     */
    #[Route('', name: 'app_forgot_password_request')]
    public function request(Request $request, MailerInterface $mailer, TranslatorInterface $translator): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->processSendingPasswordResetEmail(
                $form->get('email')->getData(),
                $mailer,
                $translator
            );
        }

        return $this->render('reset_password/request.html.twig', [
            'requestForm' => $form->createView(),
            'categories' => $this->getCategoriesForMenu(),
        ]);
    }

    /**
     * Page de confirmation après qu'un utilisateur a demandé un lien de réinitialisation de mot de passe.
     */
    #[Route('/check-email', name: 'app_check_email')]
    public function checkEmail(): Response
    {
        // Générer un faux jeton si l'utilisateur n'existe pas ou si quelqu'un accède directement à cette page.
        // Cela évite de révéler si un utilisateur a été trouvé avec l'adresse email donnée ou non
        if (null === ($resetToken = $this->getTokenObjectFromSession())) {
            $resetToken = $this->resetPasswordHelper->generateFakeResetToken();
        }

        return $this->render('reset_password/check_email.html.twig', [
            'resetToken' => $resetToken,
            'categories' => $this->getCategoriesForMenu(),
        ]);
    }

    /**
     * Valide et traite l'URL de réinitialisation que l'utilisateur a cliquée dans son email.
     */
    #[Route('/reset/{token}', name: 'app_reset_password')]
    public function reset(Request $request, UserPasswordHasherInterface $passwordHasher, TranslatorInterface $translator, string $token = null): Response
    {
        if ($token) {
            // Nous stockons le jeton en session et le supprimons de l'URL, pour éviter que l'URL ne soit
            // chargée dans un navigateur et potentiellement divulguée à des scripts JS tiers.
            $this->storeTokenInSession($token);

            return $this->redirectToRoute('app_reset_password');
        }

        $token = $this->getTokenFromSession();
        if (null === $token) {
            throw $this->createNotFoundException('Aucun jeton de réinitialisation de mot de passe trouvé dans l\'URL ou dans la session.');
        }

        try {
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $e) {
            $this->addFlash('reset_password_error', sprintf(
                '%s - %s',
                $translator->trans(ResetPasswordExceptionInterface::MESSAGE_PROBLEM_VALIDATE, [], 'ResetPasswordBundle'),
                $translator->trans($e->getReason(), [], 'ResetPasswordBundle')
            ));

            return $this->redirectToRoute('app_forgot_password_request');
        }

        // Le jeton est valide; permettre à l'utilisateur de changer son mot de passe.
        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Un jeton de réinitialisation de mot de passe ne doit être utilisé qu'une seule fois, le supprimer.
            $this->resetPasswordHelper->removeResetRequest($token);

            // Encoder (hacher) le mot de passe en clair et le définir.
            $encodedPassword = $passwordHasher->hashPassword(
                $user,
                $form->get('plainPassword')->getData()
            );

            $user->setPassword($encodedPassword);
            $this->entityManager->flush();

            // La session est nettoyée après la réinitialisation du mot de passe.
            $this->cleanSessionAfterReset();

            $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter avec votre nouveau mot de passe.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('reset_password/reset.html.twig', [
            'resetForm' => $form->createView(),
            'categories' => $this->getCategoriesForMenu(),
        ]);
    }

    private function processSendingPasswordResetEmail(string $emailFormData, MailerInterface $mailer, TranslatorInterface $translator): RedirectResponse
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'email' => $emailFormData,
        ]);

        // Ne révélez pas si un compte utilisateur a été trouvé ou non.
        if (!$user) {
            $this->logger->info('Tentative de réinitialisation de mot de passe pour un email inexistant: ' . $emailFormData);
            return $this->redirectToRoute('app_check_email');
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface $e) {
            $this->logger->error('Erreur lors de la génération du token de réinitialisation: ' . $e->getMessage());
            return $this->redirectToRoute('app_check_email');
        }

        try {
            $email = (new TemplatedEmail())
                ->from(new Address('ghofranesebteoui16@gmail.com', 'SymBook'))
                ->to($user->getEmail())
                ->subject('Votre demande de réinitialisation de mot de passe')
                ->htmlTemplate('reset_password/email.html.twig')
                ->context([
                    'resetToken' => $resetToken,
                    'tokenLifetime' => $this->resetPasswordHelper->getTokenLifetime(),
                ]);

            $mailer->send($email);
            $this->logger->info('Email de réinitialisation envoyé avec succès à: ' . $user->getEmail());
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'envoi de l\'email de réinitialisation: ' . $e->getMessage());
        }

        // Stockez l'objet jeton en session pour récupération dans la route check-email.
        $this->setTokenObjectInSession($resetToken);

        return $this->redirectToRoute('app_check_email');
    }

    private function getCategoriesForMenu()
    {
        // Récupérer les catégories pour le menu
        // Si vous avez un service ou un repository pour cela, utilisez-le ici
        try {
            return $this->entityManager->getRepository('App\Entity\Categories')->findAll();
        } catch (\Exception $e) {
            return [];
        }
    }
}
