<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\OwnedInterface;
use App\Entity\User;
use App\Security\OwnerVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

abstract class BaseController extends AbstractController
{
    protected function user(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }

    protected function own(OwnedInterface $subject): void
    {
        $this->denyAccessUnlessGranted(OwnerVoter::OWNER, $subject);
    }

    protected function checkCsrf(Request $request, string $id): void
    {
        $token = (string) ($request->headers->get('X-CSRF-Token') ?? $request->request->get('_token'));
        if (!$this->isCsrfTokenValid($id, $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token. Reload the page.');
        }
    }
}
