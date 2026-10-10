<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A snapshot of a project's files (taken on import, before AI changes, before restores, or on demand).
 */
#[ORM\Entity]
class ProjectRevision implements OwnedInterface
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private TemplateProject $project;

    #[ORM\Column(length: 200)]
    private string $label;

    #[ORM\Column(length: 20)]
    private string $source;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $snapshot;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(TemplateProject $project, string $label, string $source)
    {
        $this->project = $project;
        $this->label = mb_substr($label, 0, 200);
        $this->source = $source;
        $this->snapshot = $project->getFiles() + ['syntax' => $project->getSyntax()];
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getProject(): TemplateProject { return $this->project; }
    public function getOwner(): User { return $this->project->getOwner(); }
    public function getLabel(): string { return $this->label; }
    public function getSource(): string { return $this->source; }
    /** @return array<string, mixed> */
    public function getSnapshot(): array { return $this->snapshot; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
