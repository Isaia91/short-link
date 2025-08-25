<?php
// src/Service/SlugGeneratorService.php
namespace App\Service;
use App\Repository\LinkRepository;
class SlugGeneratorService
{
    private const CHARACTERS =
        'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    private const DEFAULT_LENGTH = 6;
    private const MAX_ATTEMPTS = 10;

    public function __construct(
        private LinkRepository $linkRepository
    )
    {
    }

    public function generateUniqueSlug(?string $customSlug = null, int $length =
    self::DEFAULT_LENGTH): string
    {
        // Si un slug personnalisé est fourni et qu'il est unique, l'utiliser
        if ($customSlug && !$this->slugExists($customSlug)) {
            return $customSlug;
        }
        // Sinon, générer un slug aléatoire unique
        $attempts = 0;
        do {
            $slug = $this->generateRandomSlug($length);
            $attempts++;

            // Augmenter la longueur si trop de tentatives
            if ($attempts >= self::MAX_ATTEMPTS) {
                $length++;
                $attempts = 0;
            }
        } while ($this->slugExists($slug) && $attempts < self::MAX_ATTEMPTS * 3);
        if ($this->slugExists($slug)) {
            throw new \Exception('Impossible de générer un slug unique après plusieurs
tentatives');
        }
        return $slug;
    }

    private function generateRandomSlug(int $length): string
    {
        $slug = '';
        $charactersLength = strlen(self::CHARACTERS);

        for ($i = 0; $i < $length; $i++) {
            $slug .= self::CHARACTERS[random_int(0, $charactersLength - 1)];
        }

        return $slug;
    }

    private function slugExists(string $slug): bool
    {
        return $this->linkRepository->findOneBy(['slug' => $slug]) !== null;
    }
    public function validateCustomSlug(string $slug): array
    {
        $errors = [];

        if (strlen($slug) < 6 || strlen($slug) > 10) {
            $errors[] = 'Le slug doit contenir entre 6 et 10 caractères';
        }

        if (!preg_match('/^[a-zA-Z0-9]+$/', $slug)) {
            $errors[] = 'Le slug ne peut contenir que des caractères alphanumériques';
        }

        if ($this->slugExists($slug)) {
            $errors[] = 'Ce slug est déjà utilisé';
        }

        return $errors;
    }
}
?>