<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class SecurityController extends BaseController
{
    #[Route('/', name: 'app_home')]
    public function home(): Response
    {
        return $this->getUser() ? $this->redirectToRoute('project_index') : $this->render('home/landing.html.twig');
    }

    #[Route('/docs', name: 'app_docs')]
    public function docs(): Response
    {
        return $this->render('home/docs.html.twig', ['contract' => \Cvolve\TemplateKit\Ai\Prompts::contract()]);
    }

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $utils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('project_index');
        }

        return $this->render('security/login.html.twig', ['last_username' => $utils->getLastUsername(), 'error' => $utils->getLastAuthenticationError()]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): never
    {
        throw new \LogicException('Handled by the firewall.');
    }

    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
        Security $security,
        #[Autowire('%env(bool:MAKER_OPEN_REGISTRATION)%')] bool $open,
    ): Response {
        if (!$open) {
            $this->addFlash('error', 'Sign-up is closed on this instance. Ask the administrator for an account.');

            return $this->redirectToRoute('app_login');
        }
        $errors = [];
        $values = ['name' => '', 'email' => ''];
        if ($request->isMethod('POST')) {
            $this->checkCsrf($request, 'register');
            $values = ['name' => trim((string) $request->request->get('name')), 'email' => trim((string) $request->request->get('email'))];
            $password = (string) $request->request->get('password');
            $user = (new User())->setName($values['name'])->setEmail($values['email']);
            foreach ($validator->validate($user) as $violation) {
                $errors[] = $violation->getMessage();
            }
            if (\strlen($password) < 8) {
                $errors[] = 'The password must have at least 8 characters.';
            }
            if ($errors === []) {
                $user->setPassword($hasher->hashPassword($user, $password));
                $em->persist($user);
                $em->flush();

                return $security->login($user, 'form_login', 'main') ?? $this->redirectToRoute('project_index');
            }
        }

        return $this->render('security/register.html.twig', ['errors' => $errors, 'values' => $values], new Response(status: $errors === [] ? 200 : 422));
    }
}
