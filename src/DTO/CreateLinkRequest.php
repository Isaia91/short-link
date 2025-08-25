<?php
// src/DTO/CreateLinkRequest.php
namespace App\DTO;
use Symfony\Component\Validator\Constraints as Assert;
class CreateLinkRequest
{
    #[Assert\NotBlank(message: "L'URL est obligatoire")]
    #[Assert\Url(message: "L'URL n'est pas valide")]
    #[Assert\Length(max: 2048, maxMessage: "L'URL ne peut pas dépasser {{ limit }} caractères")]
    public string $url;
    #[Assert\Length(max: 255, maxMessage: "La description ne peut pas dépasser {{ limit}} caractères")]
    public ?string $description = null;
    #[Assert\Length(min: 6, max: 10, minMessage: "Le slug doit contenir au moins {{ limit }} caractères", maxMessage: "Le slug ne peut pas dépasser {{ limit }} caractères")]
    #[Assert\Regex(pattern: '/^[a-zA-Z0-9]*$/', message: "Le slug ne peut contenir que des caractères alphanumériques")]
    public ?string $customSlug = null;
}
?>