<?php

declare(strict_types=1);

namespace App\Service\Project;

use App\Entity\ProjectAsset;
use App\Entity\ProjectRevision;
use App\Entity\TemplateProject;
use App\Entity\User;
use Cvolve\TemplateKit\Ai\TemplateAi;
use Cvolve\TemplateKit\Import\ConversionException;
use Cvolve\TemplateKit\Import\ImportResult;
use Cvolve\TemplateKit\Import\MustacheConverter;
use Cvolve\TemplateKit\Package\InvalidTemplatePackageException;
use Cvolve\TemplateKit\Package\TemplatePackage;
use Cvolve\TemplateKit\Package\TemplatePackageReader;
use Cvolve\TemplateKit\Package\TemplatePackageWriter;
use Cvolve\TemplateKit\Presets;
use Cvolve\TemplateKit\Render\DocumentRenderer;
use Cvolve\TemplateKit\Render\SampleCv;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Everything a template project goes through: create, compile (Mustache → Twig), validate,
 * preview with sample CVs, snapshot/restore and export as .cvolve.
 */
final class ProjectService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TemplatePackageReader $reader,
        private readonly TemplatePackageWriter $writer,
        private readonly MustacheConverter $mustache,
        private readonly DocumentRenderer $renderer,
    ) {
    }

    // ------------------------------------------------------------------ create

    public function createBlank(User $owner, string $name, string $syntax): TemplateProject
    {
        $project = new TemplateProject($owner, $name);
        $project->setSyntax($syntax)
            ->setMarkup($syntax === TemplateProject::SYNTAX_MUSTACHE ? Starters::MUSTACHE : Starters::TWIG)
            ->setCss(Starters::CSS)
            ->setSchema(Starters::schema())
            ->setManifest($this->manifest($owner, $name))
            ->setOrigin('blank');

        return $this->persistNew($project, 'Created from the starter');
    }

    public function createFromPreset(User $owner, string $slug): TemplateProject
    {
        $dir = Presets::all()[$slug] ?? throw new \InvalidArgumentException('Unknown preset.');
        $package = $this->reader->readDirectory($dir);
        $project = $this->fromPackage($owner, $package, $package->name().' (copy)', 'preset:'.$slug);
        $manifest = $project->getManifest();
        $manifest['slug'] = $package->slug().'-'.substr(bin2hex(random_bytes(3)), 0, 4);
        $manifest['author'] = $owner->getName();
        $project->setManifest($manifest);

        return $this->persistNew($project, 'Copied from preset '.$package->name());
    }

    public function createFromImport(User $owner, ImportResult $result): TemplateProject
    {
        $project = $this->fromPackage($owner, $result->package, $result->package->name(), 'import:'.$result->format);
        // Keep editing Mustache sources in Mustache
        if ($result->format === ImportResult::FORMAT_MUSTACHE && $result->source !== null) {
            $project->setSyntax(TemplateProject::SYNTAX_MUSTACHE)->setMarkup($result->source);
        }

        return $this->persistNew($project, 'Imported ('.$result->format.')');
    }

    public function createFromPackage(User $owner, TemplatePackage $package, string $origin): TemplateProject
    {
        return $this->persistNew($this->fromPackage($owner, $package, $package->name(), $origin), 'Created ('.$origin.')');
    }

    public function duplicate(TemplateProject $source): TemplateProject
    {
        $copy = new TemplateProject($source->getOwner(), mb_substr($source->getName().' (copy)', 0, 120));
        $manifest = $source->getManifest();
        $manifest['slug'] = mb_substr(($manifest['slug'] ?? 'template').'-copy', 0, 60);
        $manifest['name'] = $copy->getName();
        $copy->setSyntax($source->getSyntax())->setMarkup($source->getMarkup())->setCss($source->getCss())
            ->setSchema($source->getSchema())->setManifest($manifest)->setOrigin('copy');
        foreach ($source->getAssets() as $asset) {
            $copy->addAsset(new ProjectAsset($copy, $asset->getPath(), $asset->getContent()));
        }

        return $this->persistNew($copy, 'Duplicated from '.$source->getName());
    }

    // ------------------------------------------------------------------ compile & validate

    /**
     * Compile and validate the project as a .cvolve package.
     *
     * @return array{twig: ?string, package: ?TemplatePackage, errors: list<string>}
     */
    public function compile(TemplateProject $project): array
    {
        try {
            $twig = $project->getSyntax() === TemplateProject::SYNTAX_MUSTACHE ? $this->mustache->toTwig($project->getMarkup()) : $project->getMarkup();
        } catch (ConversionException $e) {
            return ['twig' => null, 'package' => null, 'errors' => $e->errors];
        }

        $manifest = $project->getManifest();
        $manifest['name'] = $project->getName();
        try {
            $package = $this->reader->fromFiles([
                'manifest.json' => (string) json_encode($manifest, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE),
                'schema.json' => (string) json_encode($project->getSchema(), \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE),
                'template.html.twig' => $twig,
                'style.css' => $project->getCss(),
            ] + $project->getAssetFiles());
        } catch (InvalidTemplatePackageException $e) {
            return ['twig' => $twig, 'package' => null, 'errors' => $e->errors];
        }

        return ['twig' => $twig, 'package' => $package, 'errors' => []];
    }

    /**
     * Save edited files and record whether they validate.
     *
     * @param array<string, mixed> $files template, css, schema, manifest (JSON strings or arrays)
     *
     * @return list<string> problems (also stored on the project)
     */
    public function save(TemplateProject $project, array $files): array
    {
        $errors = [];
        foreach (['schema', 'manifest'] as $key) {
            if (\is_string($files[$key] ?? null)) {
                $decoded = json_decode($files[$key], true);
                if (!\is_array($decoded)) {
                    $errors[] = sprintf('%s.json is not valid JSON: %s', $key, json_last_error_msg());
                    unset($files[$key]);
                } else {
                    $files[$key] = $decoded;
                }
            }
        }
        $project->applyFiles($files);
        if (\is_array($files['manifest'] ?? null) && \is_string($files['manifest']['name'] ?? null) && trim($files['manifest']['name']) !== '') {
            $project->setName(mb_substr(trim($files['manifest']['name']), 0, 120));
        }
        $errors = [...$errors, ...$this->compile($project)['errors']];
        $project->setErrors($errors)->touch();
        $this->em->flush();

        return $errors;
    }

    // ------------------------------------------------------------------ preview & export

    public function preview(TemplateProject $project, string $sample = 'default', string $mode = DocumentRenderer::MODE_SCREEN, string $locale = 'en'): string
    {
        $compiled = $this->compile($project);
        $twig = $compiled['twig'];
        if ($twig === null) {
            return $this->errorPage($compiled['errors']);
        }
        $assets = $project->getAssetFiles();
        $assetUri = static fn (string $path): ?string => isset($assets[$path]) ? DocumentRenderer::dataUri($path, $assets[$path]) : null;

        try {
            // Even with validation warnings, render what can be rendered so the designer sees progress
            return $this->renderer->render($twig, $project->getCss(), $project->getManifest(), SampleCv::context($project->getSchema(), $locale, $sample), \Closure::fromCallable($assetUri), $mode, $project->getName());
        } catch (\Throwable $e) {
            return $this->errorPage([$e->getMessage(), ...$compiled['errors']]);
        }
    }

    /**
     * Write the project as a .cvolve file and return its path.
     */
    public function export(TemplateProject $project): string
    {
        $compiled = $this->compile($project);
        if ($compiled['package'] === null) {
            throw new InvalidTemplatePackageException($compiled['errors']);
        }

        return $this->writer->toZip($compiled['package']);
    }

    // ------------------------------------------------------------------ revisions

    public function snapshot(TemplateProject $project, string $label, string $source = 'manual'): ProjectRevision
    {
        $revision = new ProjectRevision($project, $label, $source);
        $this->em->persist($revision);

        return $revision;
    }

    public function restore(ProjectRevision $revision): void
    {
        $project = $revision->getProject();
        $this->snapshot($project, sprintf('Before restoring "%s"', $revision->getLabel()), 'restore');
        $snapshot = $revision->getSnapshot();
        $project->applyFiles($snapshot);
        $project->setSyntax((string) ($snapshot['syntax'] ?? TemplateProject::SYNTAX_TWIG));
        $project->setErrors($this->compile($project)['errors'])->touch();
        $this->em->flush();
    }

    // ------------------------------------------------------------------ helpers

    /**
     * @return array<string, mixed>
     */
    public function manifest(User $owner, string $name): array
    {
        return [
            'format' => TemplatePackageReader::FORMAT,
            'formatVersion' => TemplatePackageReader::FORMAT_VERSION,
            'name' => $name,
            'slug' => TemplateAi::slug($name),
            'version' => '1.0.0',
            'author' => $owner->getName(),
            'licence' => 'MIT',
            'description' => 'A CVolve template.',
            'page' => 'A4',
            'maxPages' => 2,
            'fonts' => [],
            'docx' => ['accent' => '#2563EB', 'font' => 'Calibri'],
        ];
    }

    private function fromPackage(User $owner, TemplatePackage $package, string $name, string $origin): TemplateProject
    {
        $project = new TemplateProject($owner, mb_substr($name, 0, 120));
        $manifest = $package->manifest;
        $manifest['name'] = $project->getName();
        $project->setSyntax(TemplateProject::SYNTAX_TWIG)->setMarkup($package->twig)->setCss($package->css)
            ->setSchema($package->schema)->setManifest($manifest)->setOrigin($origin);
        foreach ($package->assets as $path => $contents) {
            $project->addAsset(new ProjectAsset($project, $path, $contents));
        }

        return $project;
    }

    private function persistNew(TemplateProject $project, string $label): TemplateProject
    {
        $project->setErrors($this->compile($project)['errors']);
        $this->em->persist($project);
        $this->snapshot($project, $label, 'create');
        $this->em->flush();

        return $project;
    }

    /**
     * @param list<string> $errors
     */
    private function errorPage(array $errors): string
    {
        $items = implode('', array_map(static fn (string $e) => '<li>'.htmlspecialchars($e).'</li>', $errors));

        return '<!DOCTYPE html><meta charset="utf-8"><body style="font:14px/1.5 system-ui;padding:24px;color:#991b1b;background:#fff">'
            .'<h3 style="margin:0 0 8px">The template cannot be rendered yet</h3><ul>'.$items.'</ul></body>';
    }
}
