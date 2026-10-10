<?php

declare(strict_types=1);

namespace App\Service\Project;

use App\Entity\AiConnection;
use App\Entity\ChatMessage;
use App\Entity\TemplateProject;
use App\Service\Ai\AiClient;
use Cvolve\TemplateKit\Ai\TemplateAi;
use Cvolve\TemplateKit\Util\TextDiff;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The AI design assistant: the designer asks for a change, the model returns complete files,
 * the designer reviews a diff and applies (with a revision taken first) or discards it.
 */
final class DesignChat
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AiClient $ai,
        private readonly TemplateAi $templateAi,
        private readonly ProjectService $projects,
    ) {
    }

    public function ask(TemplateProject $project, AiConnection $connection, string $request): ChatMessage
    {
        $history = [];
        foreach ($this->em->getRepository(ChatMessage::class)->findBy(['project' => $project], ['id' => 'DESC'], 6) as $message) {
            array_unshift($history, ['role' => $message->getRole(), 'content' => $message->getContent()]);
        }
        $this->em->persist(new ChatMessage($project, 'user', $request));

        try {
            $result = $this->templateAi->edit(
                $this->ai->closure($connection),
                $project->getFiles(),
                $request,
                $project->getAssetFiles(),
                $history,
                $project->getSyntax(),
            );
            $reply = new ChatMessage($project, 'assistant', $result['message'], $result['files'], $result['errors'], $connection->getLabel().' · '.$connection->getModel());
        } catch (\Throwable $e) {
            $reply = new ChatMessage($project, 'assistant', '⚠️ '.$connection->getProviderName().' error: '.mb_substr($e->getMessage(), 0, 600), [], [], $connection->getModel());
        }
        $this->em->persist($reply);
        $this->em->flush();

        return $reply;
    }

    public function apply(ChatMessage $message): void
    {
        if (!$message->isPending()) {
            throw new \LogicException('This change was already '.$message->getStatus().'.');
        }
        $project = $message->getProject();
        $this->projects->snapshot($project, 'Before AI: '.mb_substr($message->getContent(), 0, 120), 'ai');
        $message->setStatus(ChatMessage::STATUS_APPLIED);
        $this->projects->save($project, $message->getChanges());
    }

    public function discard(ChatMessage $message): void
    {
        if ($message->isPending()) {
            $message->setStatus(ChatMessage::STATUS_DISCARDED);
            $this->em->flush();
        }
    }

    /**
     * Line diff for every file the message changes.
     *
     * @return list<array{file: string, diff: list<array{type: string, line: string}>}>
     */
    public function diff(ChatMessage $message): array
    {
        $current = $message->getProject()->getFiles();
        $names = ['template' => 'template', 'css' => 'style.css', 'schema' => 'schema.json', 'manifest' => 'manifest.json'];
        $out = [];
        foreach ($message->getChanges() as $key => $new) {
            $before = self::lines($current[$key] ?? '');
            $after = self::lines($new);
            $out[] = ['file' => $names[$key] ?? $key, 'diff' => TextDiff::lines($before, $after)];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private static function lines(mixed $value): array
    {
        $text = \is_array($value) ? (string) json_encode($value, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE) : (string) $value;

        return preg_split('/\R/', $text) ?: [];
    }
}
