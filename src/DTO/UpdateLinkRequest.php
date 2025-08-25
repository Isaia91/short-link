<?php

namespace App\DTO;
use Symfony\Component\Validator\Constraints as Assert;
class UpdateLinkRequest
{
    #[Assert\Length(max: 255, maxMessage: "La description ne peut pas dépasser {{ limit }} caractères")]
    public ?string $description = null;
}