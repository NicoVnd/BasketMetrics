<?php

namespace App\Service\Import;

use App\Entity\Player;

final class PlayerMatcher
{
    public function __construct(private IdentityNormalizer $normalizer) {}

    /** Never use shirt numbers or fuzzy name matching as identity. */
    public function match(array $row, array $players): array
    {
        $license = $this->normalizer->license($row['licenseNumber'] ?? null);
        if ($license !== null) {
            $byLicense = array_values(array_filter($players, fn(Player $p) => $p->getLicenseNumber() === $license));
            if (count($byLicense) === 1) { return ['id' => $byLicense[0]->getId(), 'reason' => 'Même licence FFBB']; }
        }
        $byName = array_values(array_filter($players, fn(Player $p) =>
            $this->normalizer->name($p->getFirstName()) === $this->normalizer->name($row['firstName'])
            && $this->normalizer->name($p->getLastName()) === $this->normalizer->name($row['lastName'])
            && ($license === null || $p->getLicenseNumber() === null || $p->getLicenseNumber() === $license)
        ));
        if (count($byName) === 1) { return ['id'=>$byName[0]->getId(), 'reason'=>'Même nom et prénom, à confirmer']; }
        $similar = array_filter($players, fn(Player $p) => $this->normalizer->name($p->getLastName()) === $this->normalizer->name($row['lastName'])
            && $this->normalizer->name(mb_substr($p->getFirstName(),0,1)) === $this->normalizer->name(mb_substr($row['firstName'],0,1))
            && ($license === null || $p->getLicenseNumber() === null || $p->getLicenseNumber() === $license));
        return ['id'=>null, 'needsChoice'=>count($byName)>1 || count($similar)>0, 'reason'=>count($byName)>1 || count($similar)>0 ? 'Identité proche ou homonymes : choisissez explicitement le joueur' : 'Nouveau joueur proposé ; vérifiez les joueurs existants'];
    }
}
