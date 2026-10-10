<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A bundled file of a template: fonts/*.woff2… or thumbnail.png.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(fields: ['project', 'path'])]
class ProjectAsset
{
    public const MAX_SIZE = 3_000_000;
    public const ALLOWED = '#^(fonts/[A-Za-z0-9._-]+\.(woff2?|ttf|otf)|thumbnail\.(png|jpe?g))$#';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'assets'), ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private TemplateProject $project;

    #[ORM\Column(length: 120)]
    private string $path;

    /** Base64 so the column stays portable (PostgreSQL, SQLite in tests). */
    #[ORM\Column(type: Types::TEXT)]
    private string $data;

    #[ORM\Column]
    private int $size;

    public function __construct(TemplateProject $project, string $path, string $content)
    {
        if (!preg_match(self::ALLOWED, $path)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not an allowed asset path (fonts/<name>.woff2|woff|ttf|otf or thumbnail.png).', $path));
        }
        if (\strlen($content) > self::MAX_SIZE) {
            throw new \InvalidArgumentException('Assets must be smaller than 3 MB.');
        }
        $this->project = $project;
        $this->path = $path;
        $this->data = base64_encode($content);
        $this->size = \strlen($content);
    }

    public function getId(): ?int { return $this->id; }
    public function getProject(): TemplateProject { return $this->project; }
    public function getPath(): string { return $this->path; }
    public function getContent(): string { return (string) base64_decode($this->data, true); }
    public function getSize(): int { return $this->size; }
    public function isFont(): bool { return str_starts_with($this->path, 'fonts/'); }
}
