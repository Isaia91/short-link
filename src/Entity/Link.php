<?php

namespace App\Entity;
use App\Repository\LinkRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
#[ORM\Entity(repositoryClass: LinkRepository::class)]
class Link
{
#[ORM\Id]
#[ORM\GeneratedValue]
#[ORM\Column]
private ?int $id = null;
    #[ORM\Column(length: 2048)]
    #[Assert\NotBlank(message: "L'URL est obligatoire")]
    #[Assert\Url(message: "L'URL n'est pas valide")]
    private ?string $url = null;
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-zA-Z0-9]{6,10}$/', message: "Le slug doit contenir
entre 6 et 10 caractères alphanumériques")]
    private ?string $slug = null;
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: "La description ne peut pas dépasser {{ limit
}} caractères")]
    private ?string $description = null;
    #[ORM\Column]
    private ?int $clickCount = null;
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;
    public function getId(): ?int
    {
        return $this->id;
    }
    public function getUrl(): ?string
    {
        return $this->url;
    }
    public function setUrl(string $url): static
    {
        $this->url = $url;
        return $this;
    }
    public function getSlug(): ?string
    {
        return $this->slug;
    }
    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }
    public function getDescription(): ?string
    {
        return $this->description;
    }
    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }
    public function getClickCount(): ?int
    {
        return $this->clickCount;
    }
    public function setClickCount(int $clickCount): static
    {
        $this->clickCount = $clickCount;
        return $this;
    }
    public function incrementClickCount(): static
    {
        $this->clickCount++;
        return $this;
    }
    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }
    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->clickCount = 0;
    }
    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }
}
