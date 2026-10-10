<?php

declare(strict_types=1);

namespace App\Entity;

interface OwnedInterface
{
    public function getOwner(): User;
}
