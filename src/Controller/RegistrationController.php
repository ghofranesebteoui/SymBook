<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\AlternativePart;
use Symfony\Component\Mime\Part\Multipart\MixedPart;
use Symfony\Component\Mime\Part\TextPart;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Psr\Log\LoggerInterface;

class RegistrationController extends AbstractController
{
    private $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        LoggerInterface $logger
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // Encode the plain password
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $plainPassword
                )
            );

            // Generate verification token
            $verificationToken = bin2hex(random_bytes(32));
            $user->setVerificationToken($verificationToken);
            $user->setIsVerified(false);

            $entityManager->persist($user);
            $entityManager->flush();

            // Generate verification URL
            $verificationUrl = $this->urlGenerator->generate(
                'app_verify_email',
                ['token' => $verificationToken],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            // Send verification email with enhanced error handling
            try {
                $logger->info('Attempting to send verification email to: ' . $user->getEmail());

                // Create HTML content with verification button
                $htmlContent = $this->renderView('emails/verification.html.twig', [
                    'verificationUrl' => $verificationUrl,
                    'email' => $user->getEmail()
                ]);

                // Create plain text content as fallback
                $textContent = "Bonjour " . $user->getEmail() . ",\n\n" .
                    "Merci de vous être inscrit sur SymBook ! Nous sommes ravis de vous accueillir.\n\n" .
                    "Pour vérifier votre adresse email, veuillez cliquer sur le lien suivant :\n" .
                    $verificationUrl . "\n\n" .
                    "Cordialement,\n" .
                    "L'équipe SymBook";

                $email = (new Email())
                    ->from(new Address('ghofranesebteoui16@gmail.com', 'SymBook'))
                    ->to($user->getEmail())
                    ->subject('Vérification de votre compte SymBook')
                    ->html($htmlContent)
                    ->text($textContent);

                $mailer->send($email);
                $logger->info('Email successfully sent to: ' . $user->getEmail());
                $this->addFlash(
                    'success',
                    'Inscription réussie ! Un email de vérification a été envoyé à votre adresse email.'
                );
            } catch (TransportExceptionInterface $e) {
                $logger->error('Email transport failed: ' . $e->getMessage(), [
                    'exception' => $e,
                    'email' => $user->getEmail()
                ]);
                $this->addFlash(
                    'warning',
                    'Inscription réussie, mais l\'email de vérification n\'a pas pu être envoyé (erreur technique).'
                );
            } catch (\Exception $e) {
                $logger->critical('Unexpected error sending email: ' . $e->getMessage(), [
                    'exception' => $e,
                    'email' => $user->getEmail()
                ]);
                $this->addFlash(
                    'warning',
                    'Inscription réussie, mais une erreur inattendue est survenue lors de l\'envoi de l\'email.'
                );
            }

            return $this->redirectToRoute('app_livres_all');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/verify/email/{token}', name: 'app_verify_email')]
    public function verifyEmail(
        string $token,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ): Response {
        $user = $entityManager->getRepository(User::class)->findOneBy(['verificationToken' => $token]);

        if (!$user) {
            $this->addFlash('error', 'Le lien de vérification est invalide ou a expiré.');
            return $this->redirectToRoute('app_login');
        }

        $user->setIsVerified(true);
        $user->setVerificationToken(null); // Clear the token after verification

        $entityManager->persist($user);
        $entityManager->flush();

        $logger->info('Email verified for user: ' . $user->getEmail());

        $this->addFlash('success', 'Votre adresse email a été vérifiée avec succès ! Vous pouvez maintenant vous connecter.');

        return $this->redirectToRoute('app_login');
    }
}