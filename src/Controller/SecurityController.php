<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationType;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authentication): Response
    {
        if ($this->getUser()) { return $this->redirectToRoute('app_dashboard'); }
        return $this->render('security/login.html.twig', [
            'last_username' => $authentication->getLastUsername(),
            'error' => $authentication->getLastAuthenticationError(),
        ]);
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher, RateLimiterFactoryInterface $registrationLimiter): Response
    {
        if ($this->getUser()) { return $this->redirectToRoute('app_dashboard'); }
        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);
        if ($request->isMethod('POST') && !$registrationLimiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
            return $this->render('security/limited.html.twig', [], new Response(status: 429));
        }
        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($hasher->hashPassword($user, $form->get('plainPassword')->getData()));
            try {
                $em->persist($user);
                $em->flush();
                $this->addFlash('success', 'Compte créé. Vous pouvez vous connecter.');
                return $this->redirectToRoute('app_login', status: 303);
            } catch (UniqueConstraintViolationException) {
                $form->get('username')->addError(new FormError('Cet identifiant est indisponible.'));
            }
        }
        return $this->render('security/register.html.twig', ['form' => $form], new Response(status: $form->isSubmitted() ? 422 : 200));
    }

    #[Route('/logout', name: 'app_logout', methods: ['POST'])]
    public function logout(): never { throw new \LogicException('Handled by the firewall.'); }
}

