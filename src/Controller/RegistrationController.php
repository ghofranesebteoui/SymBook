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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;

class RegistrationController extends AbstractController
{
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

            $entityManager->persist($user);
            $entityManager->flush();

            // Send welcome email with enhanced error handling
            try {
                $logger->info('Attempting to send welcome email to: ' . $user->getEmail());

                $email = (new Email())
                    ->from('ghofranesebteoui16@gmail.com')
                    ->to($user->getEmail())
                    ->subject('Bienvenue sur SymBook !')
                    ->text(
                        'Bonjour ' . $user->getEmail() . ",\n\n" .
                        "Merci de vous être inscrit sur SymBook ! Nous sommes ravis de vous accueillir.\n\n" .
                        "Cordialement,\n" .
                        "L'équipe SymBook"
                    );

                $mailer->send($email);
                $logger->info('Email successfully sent to: ' . $user->getEmail());
                $this->addFlash(
                    'success',
                    'Inscription réussie ! Un email de bienvenue a été envoyé.'
                );
            } catch (TransportExceptionInterface $e) {
                $logger->error('Email transport failed: ' . $e->getMessage(), [
                    'exception' => $e,
                    'email' => $user->getEmail()
                ]);
                $this->addFlash(
                    'warning',
                    'Inscription réussie, mais l\'email de bienvenue n\'a pas pu être envoyé (erreur technique).'
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
}