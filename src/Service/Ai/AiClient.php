<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Entity\AiConnection;
use NeuronAI\Agent\Agent;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Providers\Deepseek\Deepseek;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\Providers\Mistral\Mistral;
use NeuronAI\Providers\Ollama\Ollama;
use NeuronAI\Providers\OpenAI\OpenAI;
use NeuronAI\Providers\OpenAILike;
use NeuronAI\Providers\XAI\Grok;

/**
 * Calls a designer's own AI connection through Neuron AI (same providers as CVolve and Plate Plan).
 */
class AiClient
{
    private ?AIProviderInterface $override = null;

    public function __construct(private readonly SecretBox $secrets)
    {
    }

    public function complete(AiConnection $connection, string $system, string $prompt): string
    {
        set_time_limit(300);

        $state = Agent::make()
            ->setAiProvider($this->provider($connection))
            ->setInstructions($system)
            ->setThreadId(bin2hex(random_bytes(8)))
            ->chat(new UserMessage($prompt));

        return trim((string) $state->getMessage()?->getContent());
    }

    /**
     * The completion closure the template kit expects.
     *
     * @return \Closure(string, string): string
     */
    public function closure(AiConnection $connection): \Closure
    {
        return fn (string $system, string $prompt): string => $this->complete($connection, $system, $prompt);
    }

    /**
     * Send a tiny prompt and report the provider's error, if any.
     */
    public function test(AiConnection $connection): ?string
    {
        try {
            $answer = $this->complete($connection, 'You are a connection test. Answer with the single word OK.', 'Say OK.');

            return $answer === '' ? 'The provider returned an empty answer.' : null;
        } catch (\Throwable $e) {
            return mb_substr($e->getMessage(), 0, 500);
        }
    }

    public function provider(AiConnection $connection): AIProviderInterface
    {
        if ($this->override !== null) {
            return $this->override;
        }
        $key = $connection->getEncryptedKey() !== null ? $this->secrets->decrypt($connection->getEncryptedKey()) : '';
        $model = $connection->getModel();
        $url = (string) $connection->getBaseUrl();

        return match ($connection->getProvider()) {
            'anthropic' => new Anthropic(key: $key, model: $model),
            'openai' => new OpenAI(key: $key, model: $model),
            'gemini' => new Gemini(key: $key, model: $model),
            'mistral' => new Mistral(key: $key, model: $model),
            'deepseek' => new Deepseek(key: $key, model: $model),
            'grok' => new Grok(key: $key, model: $model),
            'ollama' => new Ollama(url: $url !== '' ? $url : 'http://host.docker.internal:11434/api', model: $model),
            'openai_like' => new OpenAILike(baseUri: $url, key: $key, model: $model),
            default => throw new \InvalidArgumentException(sprintf('Unknown provider "%s".', $connection->getProvider())),
        };
    }

    /**
     * Use a fixed provider (tests use Neuron's FakeAIProvider).
     */
    public function override(?AIProviderInterface $provider): void
    {
        $this->override = $provider;
    }
}
