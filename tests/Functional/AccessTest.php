<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\TemplateProject;
use App\Service\Project\ProjectService;
use App\Tests\MakerTestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AccessTest extends WebTestCase
{
    use MakerTestCase;

    public function testPagesAndOwnership(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->resetDatabase();

        $client->request('GET', '/projects');
        self::assertResponseRedirects('/login');
        $client->request('GET', '/docs');
        self::assertResponseIsSuccessful();

        $owner = $this->createUser();
        $project = static::getContainer()->get(ProjectService::class)->createBlank($owner, 'Mine', TemplateProject::SYNTAX_TWIG);

        $client->loginUser($owner);
        foreach (['/projects', '/projects/'.$project->getId(), '/projects/import', '/projects/generate', '/settings/ai', '/settings/ai/new', '/projects/'.$project->getId().'/revisions'] as $url) {
            $client->request('GET', $url);
            self::assertResponseIsSuccessful($url);
        }
        $client->request('GET', '/projects/'.$project->getId().'/preview?sample=long&locale=ar');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString("default-src 'none'", (string) $client->getResponse()->headers->get('Content-Security-Policy'));
        self::assertStringContainsString('dir="rtl"', (string) $client->getResponse()->getContent());

        $client->request('GET', '/projects/'.$project->getId().'/export');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/zip');

        $client->loginUser($this->createUser('other@example.com'));
        $client->request('GET', '/projects/'.$project->getId());
        self::assertResponseStatusCodeSame(403);
        $client->request('GET', '/projects/'.$project->getId().'/export');
        self::assertResponseStatusCodeSame(403);
    }
}
