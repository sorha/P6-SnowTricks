<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\AccountType;
use App\Entity\PasswordUpdate;
use App\Form\RegistrationType;
use App\Form\PasswordResetType;
use App\Form\PasswordForgotType;
use App\Form\PasswordUpdateType;
use App\Repository\UserRepository;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class AccountController extends AbstractController
{
    /**
     * @Route("/registration", name="account_registration")
     */
    public function registration(Request $request, ObjectManager $manager, UserPasswordEncoderInterface $encoder, \Swift_Mailer $mailer)
    {
        $user = new User();

        $form = $this->createForm(RegistrationType::class, $user);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $file = $user->getFile();
            // Créer un nom unique pour le fichier
            $name = md5(uniqid()) . '.' . $file->guessExtension();
            // Déplace le fichier
            $path = 'img/users';
            $file->move($path, $name);
            // Donner le path et le nom au fichier dans la base de données
            $user->setImagePath($path);
            $user->setImageName($name);

            $password = $encoder->encodePassword($user, $user->getPassword());
            $user->setPassword($password)
                 ->setCreatedAt(new \DateTime)
                 ->setActivated(false)
                 ->setToken(md5(random_bytes(10)));

            $manager->persist($user);
            $manager->flush();
            
            $message = (new \Swift_Message('Validation de votre compte SnowTricks'))
            ->setFrom('noreply@snowtricks.com')
            ->setTo($user->getEmail())
            ->setBody(
                $this->renderView('emails/validation.html.twig', [
                        'user' => $user
                    ]),
                    'text/html'
                )
            ;

            $mailer->send($message);

            $this->addFlash(
                'success',
                "Compte crée avec succès ! Veuillez valider votre compte via le mail qui vous a été envoyé pour pouvoir vous connecter !"
            );

            return $this->redirectToRoute('account_login');
        }

        return $this->render('account/registration.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * Validation de l'email après une inscription
     *
     * @Route("/email-validation/{username}/{token}", name="email_validation")
     */
    public function emailValidation(UserRepository $repo, $username, $token, ObjectManager $manager)
    {
        $user = $repo->findOneByUsername($username);

        if($token != null && $token === $user->getToken())
        {
            $user->setActivated(true);
            $manager->persist($user);
            $manager->flush();

            $this->addFlash(
                'success',
                "Votre compte a été activé avec succès ! Vous pouvez désormais vous connecter !"
            );
        }
        else
        {
            $this->addFlash(
                'danger',
                "La validation de votre compte a échoué. Le lien de validation a expiré !"
            );   
        }

        return $this->redirectToRoute('account_login'); 
    }

    /**
     * Afficher et traiter le formulaire de modification de profil
     * 
     * @Route("/account/profile", name="account_profile")
     * @IsGranted("ROLE_USER")
     */
    public function profile(Request $request, ObjectManager $manager)
    {
        $user = $this->getUser();

        $form = $this->createForm(AccountType::class, $user);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            //$file = $user->getFile();
            // Créer un nom unique pour le fichier
           // $name = md5(uniqid()) . '.' . $file->getClientOriginalExtension();
            // Déplace le fichier
            //$path = 'img/users';
            //$file->move($path, $name);
            
            // Donner le path et le nom au fichier dans la base de données
            //$user->setImagePath($path);
            //$user->setImageName($name);

            $manager->persist($user);
            $manager->flush();

            $this->addFlash(
                'success',
                'Les modifications du profil ont été enregistrées avec succès !'
            );
        }

        return $this->render('account/profile.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * Permet de modifier le mot de passe
     *
     * @Route("/account/password-update", name="account_password_update")
     * @IsGranted("ROLE_USER")
     */
    public function passwordUpdate(Request $request, UserPasswordEncoderInterface $encoder, ObjectManager $manager) 
    {
        $passwordUpdate = new PasswordUpdate();

        $user = $this->getUser();

        $form = $this->createForm(PasswordUpdateType::class, $passwordUpdate);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid())
        {
            if(!password_verify($passwordUpdate->getOldPassword(), $user->getPassword()))
            {
                $form->get('oldPassword')->addError(new FormError("Le mot de passe que vous avez tapé n'est pas votre mot de passe actuel !"));
            }
            else
            {
                $newPassword = $passwordUpdate->getNewPassword();
                $password = $encoder->encodePassword($user, $newPassword);

                $user->setPassword($password);

                $manager->persist($user);
                $manager->flush();

                $this->addFlash(
                    'success',
                    "Votre mot de passe a été modifié avec succès !"
                );

                return $this->redirectToRoute('home'); 
            }
        }

        return $this->render('account/password-update.html.twig', [
            'form' => $form->createView()
        ]);
    }

    
    /**
     * Demande d'une réinitialisation du mot de passe car oublié par l'utilisateur
     * 
     * @Route("/account/password-forgot", name="account_password_forgot")
     */
    public function passwordForgot(Request $request, ObjectManager $manager, UserRepository $repo, \Swift_Mailer $mailer)
    {
        $form = $this->createForm(PasswordForgotType::class);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) 
        {
            $username = $form->getData('username');
            $user = $repo->findOneByUsername($username);

            if($user !== null)
            {
                $user->setToken(md5(random_bytes(10)));

                $manager->persist($user);
                $manager->flush();

                $message = (new \Swift_Message('SnowTricks - Réinitilisation du mot de passe'))
                ->setFrom('noreply@snowtricks.com')
                ->setTo($user->getEmail())
                ->setBody(
                    $this->renderView('emails/reset.html.twig', [
                            'user' => $user
                        ]),
                        'text/html'
                    )
                ;

                $mailer->send($message);

                $this->addFlash(
                    'success',
                    "Un email de réinitilisation de mot de passe a été envoyé sur l'email lié à votre compte !"
                );
            }
            else 
            {
                $this->addFlash(
                    'danger',
                    "Cet utilisateur n'existe pas !"
                );
            }
        }

        return $this->render('account/password-forgot.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * Réinitilisation du mot de passe si le token est correct
     * 
     * @Route("/account/password-reset/{username}/{token}", name="account_password_reset")
     */
    public function passwordReset(Request $request, UserRepository $repo, UserPasswordEncoderInterface $encoder, ObjectManager $manager, $username, $token)
    {
        $user = $repo->findOneByUsername($username);

        $form = $this->createForm(PasswordResetType::class, $user);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) 
        {
            if($user->getToken() === $token)
            {
                $password = $encoder->encodePassword($user, $user->getPassword());
                $user->setPassword($password);

                $manager->persist($user);
                $manager->flush();

                $this->addFlash(
                    'success',
                    "Mot de passe modifié avec succès !"
                );

                return $this->redirectToRoute('account_login');
            }
            else 
            {
                $this->addFlash(
                    'danger',
                    "La modification du mot de passe a échoué ! Le lien de validation a expiré !"
                );
            }
        }

        return $this->render('account/password-reset.html.twig', [
            'form' => $form->createView()
        ]);
    }
    
}