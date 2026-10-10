<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One turn of the AI design chat. Assistant turns may carry proposed file changes
 * that the designer applies or discards.
 */
#[ORM\Entity]
class ChatMessage implements OwnedInterface
{
    public const STATUS_NONE = 'none';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_DISCARDED = 'discarded';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private TemplateProject $project;

    #[ORM\Column(length: 20)]
    private string $role;

    #[ORM\Column(type: Types::TEXT)]
    private string $content;

    /** @var array<string, mixed> proposed complete files (template, css, schema, manifest) */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $changes = [];

    /** @var list<string> validation problems the proposal still has */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $errors = [];

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_NONE;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $model = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /**
     * @param array<string, mixed> $changes
     * @param list<string>         $errors
     */
    public function __construct(TemplateProject $project, string $role, string $content, array $changes = [], array $errors = [], ?string $model = null)
    {
        $this->project = $project;
        $this->role = $role;
        $this->content = $content;
        $this->changes = $changes;
        $this->errors = $errors;
        $this->status = $changes !== [] ? self::STATUS_PENDING : self::STATUS_NONE;
        $this->model = $model;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getProject(): TemplateProject { return $this->project; }
    public function getOwner(): User { return $this->project->getOwner(); }
    public function getRole(): string { return $this->role; }
    public function getContent(): string { return $this->content; }
    /** @return array<string, mixed> */
    public function getChanges(): array { return $this->changes; }
    /** @return list<string> */
    public function getErrors(): array { return $this->errors; }
    public function getStatus(): string { return $this->status; }
    public function isPending(): bool { return $this->status === self::STATUS_PENDING; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getModel(): ?string { return $this->model; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
