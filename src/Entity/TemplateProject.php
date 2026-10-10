<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TemplateProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A template being designed: markup (Twig or Mustache), stylesheet, schema, manifest and assets.
 */
#[ORM\Entity(repositoryClass: TemplateProjectRepository::class)]
class TemplateProject implements OwnedInterface
{
    public const SYNTAX_TWIG = 'twig';
    public const SYNTAX_MUSTACHE = 'mustache';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(length: 12)]
    private string $syntax = self::SYNTAX_TWIG;

    #[ORM\Column(type: Types::TEXT)]
    private string $markup = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $css = '';

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $schema = [];

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $manifest = [];

    /** How the project started: blank, preset, import:<format>, ai. */
    #[ORM\Column(length: 40)]
    private string $origin = 'blank';

    /** @var list<string> validation problems from the last save */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $errors = [];

    /** @var Collection<int, ProjectAsset> */
    #[ORM\OneToMany(targetEntity: ProjectAsset::class, mappedBy: 'project', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['path' => 'ASC'])]
    private Collection $assets;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $owner, string $name)
    {
        $this->owner = $owner;
        $this->name = $name;
        $this->assets = new ArrayCollection();
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getOwner(): User { return $this->owner; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = trim($name); return $this; }
    public function getSyntax(): string { return $this->syntax; }
    public function setSyntax(string $syntax): static { $this->syntax = $syntax === self::SYNTAX_MUSTACHE ? $syntax : self::SYNTAX_TWIG; return $this; }
    public function getMarkup(): string { return $this->markup; }
    public function setMarkup(string $markup): static { $this->markup = $markup; return $this; }
    public function getCss(): string { return $this->css; }
    public function setCss(string $css): static { $this->css = $css; return $this; }
    /** @return array<string, mixed> */
    public function getSchema(): array { return $this->schema; }
    /** @param array<string, mixed> $schema */
    public function setSchema(array $schema): static { $this->schema = $schema; return $this; }
    /** @return array<string, mixed> */
    public function getManifest(): array { return $this->manifest; }
    /** @param array<string, mixed> $manifest */
    public function setManifest(array $manifest): static { $this->manifest = $manifest; return $this; }
    public function getOrigin(): string { return $this->origin; }
    public function setOrigin(string $origin): static { $this->origin = $origin; return $this; }
    /** @return list<string> */
    public function getErrors(): array { return $this->errors; }
    /** @param list<string> $errors */
    public function setErrors(array $errors): static { $this->errors = $errors; return $this; }
    public function isValid(): bool { return $this->errors === []; }

    /** @return Collection<int, ProjectAsset> */
    public function getAssets(): Collection { return $this->assets; }

    public function getAsset(string $path): ?ProjectAsset
    {
        foreach ($this->assets as $asset) {
            if ($asset->getPath() === $path) {
                return $asset;
            }
        }

        return null;
    }

    public function addAsset(ProjectAsset $asset): static
    {
        $existing = $this->getAsset($asset->getPath());
        if ($existing !== null) {
            $this->assets->removeElement($existing);
        }
        $this->assets->add($asset);

        return $this;
    }

    public function removeAsset(ProjectAsset $asset): static
    {
        $this->assets->removeElement($asset);

        return $this;
    }

    /** @return array<string, string> path => binary */
    public function getAssetFiles(): array
    {
        $files = [];
        foreach ($this->assets as $asset) {
            $files[$asset->getPath()] = $asset->getContent();
        }

        return $files;
    }

    /**
     * The editable files as one array (for AI, revisions and change previews).
     *
     * @return array{template: string, css: string, schema: array<string, mixed>, manifest: array<string, mixed>}
     */
    public function getFiles(): array
    {
        return ['template' => $this->markup, 'css' => $this->css, 'schema' => $this->schema, 'manifest' => $this->manifest];
    }

    /**
     * @param array<string, mixed> $files
     */
    public function applyFiles(array $files): void
    {
        if (\is_string($files['template'] ?? null)) {
            $this->markup = $files['template'];
        }
        if (\is_string($files['css'] ?? null)) {
            $this->css = $files['css'];
        }
        if (\is_array($files['schema'] ?? null)) {
            $this->schema = $files['schema'];
        }
        if (\is_array($files['manifest'] ?? null)) {
            $this->manifest = $files['manifest'];
        }
    }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function touch(): static { $this->updatedAt = new \DateTimeImmutable(); return $this; }
}
