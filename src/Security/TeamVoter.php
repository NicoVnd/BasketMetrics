<?php

namespace App\Security;

use App\Entity\Team;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class TeamVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool { return $attribute === 'TEAM_MANAGE' && $subject instanceof Team; }
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        return $user instanceof User && $subject->getOwner()->getId() === $user->getId();
    }
}
