<?php

namespace App\DTO;
class LinkResponse
{
    public int $id;
    public string $url;
    public string $slug;
    public string $shortUrl;
    public ?string $description;
    public int $clickCount;
    public string $createdAt;
    public function __construct(
        int $id,
        string $url,
        string $slug,
        string $shortUrl,
        ?string $description,
        int $clickCount,
        \DateTimeInterface $createdAt
    ) {
        $this->id = $id;
        $this->url = $url;
        $this->slug = $slug;
        $this->shortUrl = $shortUrl;
        $this->description = $description;
        $this->clickCount = $clickCount;
        $this->createdAt = $createdAt->format('Y-m-d H:i:s');
    }
}