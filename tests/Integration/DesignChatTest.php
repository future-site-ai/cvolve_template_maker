<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\ChatMessage;
use App\Entity\ProjectRevision;
use App\Entity\TemplateProject;
use App\Service\Ai\AiClient;
use App\Service\Project\DesignChat;
use App\Service\Project\ProjectService;
use App\Tests\MakerTestCase;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Testing\FakeAIProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The design assistant with a scripted model: changes arrive as a pending diff,
 * invalid answers get one repair round, applying saves a revision first.
 */
final class DesignChatTest extends KernelTestCase
{
    use MakerTestCase;

    public function testProposeRepairAndApply(): void
    {
        self::bootKernel();
        $em = $this->resetDatabase();
        $user = $this->createUser();
        $connection = $this->createConnection($user);
        $project = static::getContainer()->get(ProjectService::class)->createBlank($user, 'Chat', TemplateProject::SYNTAX_MUSTACHE);

        $broken = json_encode(['message' => 'Made the name teal.', 'files' => ['template' => '{{#items}}unclosed', 'css' => 'h1 { color: #0f766e; }']]);
        $fixed = json_encode(['message' => 'Made the name teal (fixed).', 'files' => ['template' => '<h1>{{header.name}}</h1>{{#regions.main}}<h2>{{title}}</h2>{{/regions.main}}', 'css' => 'h1 { color: #0f766e; }']]);
        static::getContainer()->get(AiClient::class)->override(new FakeAIProvider(new AssistantMessage((string) $broken), new AssistantMessage((string) $fixed)));

        $chat = static::getContainer()->get(DesignChat::class);
        $reply = $chat->ask($project, $connection, 'Make the name teal');

        self::assertSame('Made the name teal (fixed).', $reply->getContent());
        self::assertTrue($reply->isPending());
        self::assertSame([], $reply->getErrors());
        self::assertStringNotContainsString('#0f766e', $project->getCss(), 'Nothing changes before Apply');
        $files = array_column($chat->diff($reply), 'file');
        self::assertSame(['template', 'style.css'], $files);

        $chat->apply($reply);
        self::assertSame(ChatMessage::STATUS_APPLIED, $reply->getStatus());
        self::assertStringContainsString('#0f766e', $project->getCss());
        self::assertSame([], $project->getErrors());
        $labels = array_map(static fn (ProjectRevision $r) => $r->getLabel(), $em->getRepository(ProjectRevision::class)->findBy(['project' => $project]));
        self::assertContains('Before AI: Made the name teal (fixed).', $labels);

        $this->expectException(\LogicException::class);
        $chat->apply($reply);
    }

    public function testProviderErrorsBecomeChatMessages(): void
    {
        self::bootKernel();
        $this->resetDatabase();
        $user = $this->createUser();
        $project = static::getContainer()->get(ProjectService::class)->createBlank($user, 'Chat', TemplateProject::SYNTAX_TWIG);
        // An empty fake provider throws on the first call
        static::getContainer()->get(AiClient::class)->override(new FakeAIProvider());

        $reply = static::getContainer()->get(DesignChat::class)->ask($project, $this->createConnection($user), 'Anything');

        self::assertStringStartsWith('⚠️', $reply->getContent());
        self::assertFalse($reply->isPending());
    }
}
