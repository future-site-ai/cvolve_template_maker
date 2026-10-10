<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\ProjectRevision;
use App\Entity\TemplateProject;
use App\Service\Ai\SecretBox;
use App\Service\Project\ProjectService;
use App\Tests\MakerTestCase;
use Cvolve\TemplateKit\Import\TemplateImporter;
use Cvolve\TemplateKit\Package\TemplatePackageReader;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ProjectServiceTest extends KernelTestCase
{
    use MakerTestCase;

    private ProjectService $projects;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->resetDatabase();
        $this->projects = static::getContainer()->get(ProjectService::class);
    }

    public function testStartersAndPresetsAreValid(): void
    {
        $user = $this->createUser();
        foreach ([TemplateProject::SYNTAX_MUSTACHE, TemplateProject::SYNTAX_TWIG] as $syntax) {
            $project = $this->projects->createBlank($user, 'Starter '.$syntax, $syntax);
            self::assertSame([], $project->getErrors(), $syntax);
            foreach (['default', 'long', 'minimal'] as $sample) {
                self::assertStringContainsString('Alex Morgan', $this->projects->preview($project, $sample));
            }
        }
        foreach (['cv-software', 'classic', 'modern'] as $slug) {
            self::assertSame([], $this->projects->createFromPreset($user, $slug)->getErrors(), $slug);
        }
    }

    public function testSaveReportsProblemsAndExportRoundTrips(): void
    {
        $project = $this->projects->createBlank($this->createUser(), 'Round trip', TemplateProject::SYNTAX_MUSTACHE);

        $errors = $this->projects->save($project, ['template' => '{{#items}}never closed', 'schema' => '{not json']);
        self::assertStringContainsString('schema.json is not valid JSON', implode(' ', $errors));
        self::assertStringContainsString('never closed', implode(' ', $errors));
        self::assertFalse($project->isValid());
        self::assertStringContainsString('cannot be rendered', $this->projects->preview($project));

        $errors = $this->projects->save($project, ['template' => '<h1>{{header.name}}</h1>{{#regions.main}}<h2>{{title}}</h2>{{/regions.main}}', 'manifest' => '{"format":"cvolve-template","formatVersion":1,"name":"Renamed","slug":"renamed","version":"2.0.0"}']);
        self::assertSame([], $errors);
        self::assertSame('Renamed', $project->getName());

        $path = $this->projects->export($project);
        $package = static::getContainer()->get(TemplatePackageReader::class)->readZip($path);
        self::assertSame('renamed', $package->slug());
        self::assertStringContainsString('as_list', $package->twig, 'Mustache is compiled to Twig in the export');

        // CVolve's importer accepts the exported file
        $result = static::getContainer()->get(TemplateImporter::class)->importFile($path, 'renamed.cvolve');
        self::assertSame('cvolve', $result->format);
    }

    public function testRevisionsRestore(): void
    {
        $project = $this->projects->createBlank($this->createUser(), 'Revisions', TemplateProject::SYNTAX_TWIG);
        $original = $project->getCss();
        $this->projects->save($project, ['css' => 'body { color: red; }']);
        $first = static::getContainer()->get('doctrine')->getRepository(ProjectRevision::class)->findOneBy(['project' => $project], ['id' => 'ASC']);
        \assert($first !== null);

        $this->projects->restore($first);

        self::assertSame($original, $project->getCss());
        self::assertCount(2, static::getContainer()->get('doctrine')->getRepository(ProjectRevision::class)->findBy(['project' => $project]));
    }

    public function testApiKeysAreEncrypted(): void
    {
        $connection = $this->createConnection($this->createUser());
        self::assertStringNotContainsString('secret-key', (string) $connection->getEncryptedKey());
        self::assertSame('sk-ant-secret-key-123', static::getContainer()->get(SecretBox::class)->decrypt((string) $connection->getEncryptedKey()));
        self::assertSame('sk-a…-123', $connection->getKeyHint());
    }
}
