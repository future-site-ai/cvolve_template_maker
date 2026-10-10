<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AiConnectionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A designer's own AI provider account ("bring your own key"). The API key is stored
 * encrypted (see App\Service\Ai\SecretBox) and never shown again after saving.
 */
#[ORM\Entity(repositoryClass: AiConnectionRepository::class)]
class AiConnection implements OwnedInterface
{
    public const PROVIDERS = [
        'anthropic' => ['Anthropic (Claude)', 'claude-sonnet-5-5', true],
        'openai' => ['OpenAI (ChatGPT)', 'gpt-4o', true],
        'gemini' => ['Google Gemini', 'gemini-2.5-pro', true],
        'mistral' => ['Mistral', 'mistral-large-latest', true],
        'deepseek' => ['DeepSeek', 'deepseek-chat', true],
        'grok' => ['xAI Grok', 'grok-4', true],
        'ollama' => ['Ollama (local)', 'llama3.1', false],
        'openai_like' => ['OpenAI-compatible (OpenRouter, Groq, LM Studio…)', '', false],
    ];

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    #[ORM\Column(length: 80)]
    private string $label;

    #[ORM\Column(length: 30)]
    private string $provider;

    #[ORM\Column(length: 120)]
    private string $model;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $baseUrl = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $encryptedKey = null;

    #[ORM\Column(length: 12, nullable: true)]
    private ?string $keyHint = null;

    #[ORM\Column]
    private bool $isDefault = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastTestedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $owner, string $label, string $provider, string $model)
    {
        $this->owner = $owner;
        $this->label = $label;
        $this->provider = $provider;
        $this->model = $model;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getOwner(): User { return $this->owner; }
    public function getLabel(): string { return $this->label; }
    public function setLabel(string $label): static { $this->label = $label; return $this; }
    public function getProvider(): string { return $this->provider; }
    public function setProvider(string $provider): static { $this->provider = $provider; return $this; }
    public function getProviderName(): string { return self::PROVIDERS[$this->provider][0] ?? $this->provider; }
    public function getModel(): string { return $this->model; }
    public function setModel(string $model): static { $this->model = $model; return $this; }
    public function getBaseUrl(): ?string { return $this->baseUrl; }
    public function setBaseUrl(?string $url): static { $this->baseUrl = $url ?: null; return $this; }
    public function getEncryptedKey(): ?string { return $this->encryptedKey; }
    public function getKeyHint(): ?string { return $this->keyHint; }

    public function setEncryptedKey(?string $encrypted, ?string $hint): static
    {
        $this->encryptedKey = $encrypted;
        $this->keyHint = $hint;

        return $this;
    }

    public function isDefault(): bool { return $this->isDefault; }
    public function setDefault(bool $default): static { $this->isDefault = $default; return $this; }
    public function getLastTestedAt(): ?\DateTimeImmutable { return $this->lastTestedAt; }
    public function getLastError(): ?string { return $this->lastError; }

    public function recordTest(?string $error): void
    {
        $this->lastTestedAt = new \DateTimeImmutable();
        $this->lastError = $error;
    }

    public function needsKey(): bool { return self::PROVIDERS[$this->provider][2] ?? false; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
