<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserPasswordHistory;
use App\Form\ChangePasswordFormType;
use App\Form\ImportFormType;
use App\Repository\UserPasswordHistoryRepository;
use App\Repository\UserRepository;
use App\Security\AppCustomAuthenticator;
use App\Service\MailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use App\Service\UserService;
use Symfony\Component\Mailer\MailerInterface;

final class UserController extends AbstractController
{
    public function __construct(private readonly UserService $userService,
                                private readonly MailService $mailService,
                                private readonly UserRepository $userRepository,
                                private readonly UserPasswordHistoryRepository $userPasswordHistoryRepository)
    {
    }

    #[Route(path: '/', name: 'dashboard')]
    public function dashboard(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        if ($this->userService->shouldPasswordBeChanged($this->getUser())) {
            return $this->redirectToRoute('userChangePassword');
        }

        return $this->render('user/dashboard.html.twig');
    }

    #[Route(path: '/user/import', name: 'userImport')]
    public function import(Request $request, UserPasswordHasherInterface $userPasswordHasher): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        if ($this->userService->shouldPasswordBeChanged($this->getUser())) {
            return $this->redirectToRoute('userChangePassword');
        }

        $form = $this->createForm(ImportFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('csv')->getData();

            if (($handle = fopen($file->getPathname(), "r")) !== false) {
                $errors = '';
                while (($data = fgetcsv($handle)) !== false) {
                    $skipAdd = false;
                    if (!preg_match('/^(?=(?:\D*\d){2})(?=(?:[^a-z]*[a-z]){2})(?=(?:[^A-Z]*[A-Z]){2})(?=(?:\w*\W){2})/', $data[1])) {
                        $errors .= 'Password for user with email ' . $data[0] . ' does not fulfill password requirements (2 lower case letters, 2 upper case letters, 2 digits and 2 special characters).' . PHP_EOL;
                        $skipAdd = true;
                    }
                    if (!$skipAdd) {
                        $user = $this->userRepository->findOneBy(['email' => $data[0]]);
                        if ($user) {
                            $errors .= 'User with email ' . $data[0] . ' already exists.' . PHP_EOL;
                            $skipAdd = true;
                        }
                    }
                    if (!$skipAdd) {
                        $user = new User();
                        $password = $userPasswordHasher->hashPassword($user, $data[1]);
                        $user = $this->userRepository->update($password, true, new \DateTime('now'), $user, $data[0]);

                        $this->userPasswordHistoryRepository->save($user, $data[1]);
                    }
                }
                fclose($handle);
                if ($errors === '') {
                    return $this->redirectToRoute('dashboard');
                }
            }
        }

        return $this->render('user/import.html.twig', [
            'importForm' => $form->createView(),
            'errors' => $errors ?? '',
        ]);
    }

    #[Route(path: '/user/changePassword', name: 'userChangePassword')]
    public function changePassword(Request $request,
                                   UserPasswordHasherInterface $userPasswordHasher,
                                   UserAuthenticatorInterface $userAuthenticator,
                                   AppCustomAuthenticator $authenticator,
                                   MailerInterface $mailer): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();
        $form = $this->createForm(ChangePasswordFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            if ($this->userService->passwordWasAlreadyUsed($user, $plainPassword)) {
                $error = 'This password was already used, please use different';
            }
            if (!isset($error)) {
                $password = $userPasswordHasher->hashPassword($user, $plainPassword);
                $user = $this->userRepository->update($password, false, new \DateTime('now'), $user);

                $this->userPasswordHistoryRepository->save($user, $plainPassword);

                $this->mailService->sendMail($user->getEmail(), 'Changed password', 'Your password has been changed successfully', $mailer);

                return $userAuthenticator->authenticateUser(
                    $user,
                    $authenticator,
                    $request
                );
            }
        }

        return $this->render('user/changePassword.html.twig', [
            'changePasswordForm' => $form->createView(),
            'error' => $error ?? '',
        ]);
    }
}
