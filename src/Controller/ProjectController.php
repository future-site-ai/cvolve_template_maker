<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AiConnection;
use App\Entity\ChatMessage;
use App\Entity\ProjectAsset;
use App\Entity\ProjectRevision;
use App\Entity\TemplateProject;
use App\Repository\AiConnectionRepository;
use App\Repository\TemplateProjectRepository;
use App\Service\Ai\AiClient;
use App\Service\Export\GotenbergClient;
use App\Service\Project\DesignChat;
use App\Service\Project\ProjectService;
use Cvolve\TemplateKit\Ai\TemplateAi;
use Cvolve\TemplateKit\Import\ConversionException;
use Cvolve\TemplateKit\Import\TemplateImporter;
use Cvolve\TemplateKit\Package\InvalidTemplatePackageException;
use Cvolve\TemplateKit\Presets;
use Cvolve\TemplateKit\Render\DocumentRenderer;
use Cvolve\TemplateKit\Render\SampleCv;
use Cvolve\TemplateKit\SectionTypes;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/projects')]
class ProjectController extends BaseController
{
    /** The preview iframe runs no scripts and loads nothing from outside. */
    private const PREVIEW_CSP = "default-src 'none'; style-src 'unsafe-inline'; img-src data:; font-src data:; frame-ancestors 'self'; base-uri 'none'; form-action 'none'";

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProjectService $projects,
        private readonly TemplateProjectRepository $repository,
        private readonly AiConnectionRepository $connections,
    ) {
    }

    #[Route('', name: 'project_index')]
    public function index(): Response
    {
        return $this->render('project/index.html.twig', [
            'projects' => $this->repository->findBy(['owner' => $this->user()], ['updatedAt' => 'DESC']),
            'presets' => array_map(static fn (string $dir) => json_decode((string) file_get_contents($dir.'/manifest.json'), true), Presets::all()),
            'has_ai' => $this->connections->count(['owner' => $this->user()]) > 0,
        ]);
    }

    #[Route('/new', name: 'project_new', methods: ['POST'])]
    public function new(Request $request): Response
    {
        $this->checkCsrf($request, 'project-new');
        $name = trim((string) $request->request->get('name')) ?: 'Untitled template';
        $preset = (string) $request->request->get('preset');
        $project = $preset !== ''
            ? $this->projects->createFromPreset($this->user(), $preset)
            : $this->projects->createBlank($this->user(), mb_substr($name, 0, 120), (string) $request->request->get('syntax', TemplateProject::SYNTAX_TWIG));

        return $this->redirectToRoute('project_editor', ['id' => $project->getId()]);
    }

    #[Route('/import', name: 'project_import', methods: ['GET', 'POST'])]
    public function import(Request $request, TemplateImporter $importer, AiClient $ai): Response
    {
        $connections = $this->connections->findBy(['owner' => $this->user()], ['isDefault' => 'DESC', 'id' => 'ASC']);
        $errors = $warnings = [];

        if ($request->isMethod('POST')) {
            $this->checkCsrf($request, 'project-import');
            $connection = $this->connection($request, $connections);
            $complete = $connection !== null ? $ai->closure($connection) : null;
            $manifest = array_filter(['name' => trim((string) $request->request->get('name')), 'author' => $this->user()->getName()]);
            set_time_limit(300);

            try {
                /** @var UploadedFile|null $file */
                $file = $request->files->get('file');
                $html = trim((string) $request->request->get('html'));
                if ($file instanceof UploadedFile && $file->isValid()) {
                    $result = $importer->importFile($file->getPathname(), (string) $file->getClientOriginalName(), $complete, $manifest);
                } elseif ($html !== '') {
                    $result = $importer->importFiles(['index.html' => $html], $complete, $manifest + ['name' => 'Pasted template']);
                } else {
                    throw new ConversionException(['Upload a file or paste HTML.']);
                }
                $project = $this->projects->createFromImport($this->user(), $result);
                foreach ($result->warnings as $warning) {
                    $this->addFlash('warning', $warning);
                }
                $this->addFlash('success', sprintf('Imported as "%s".', $project->getName()));

                return $this->redirectToRoute('project_editor', ['id' => $project->getId()]);
            } catch (ConversionException $e) {
                [$errors, $warnings] = [$e->errors, $e->warnings];
            } catch (\Throwable $e) {
                $errors = [$e->getMessage()];
            }
        }

        return $this->render('project/import.html.twig', ['connections' => $connections, 'errors' => $errors, 'warnings' => $warnings], new Response(status: $errors === [] ? 200 : 422));
    }

    #[Route('/generate', name: 'project_generate', methods: ['GET', 'POST'])]
    public function generate(Request $request, TemplateAi $templateAi, AiClient $ai): Response
    {
        $connections = $this->connections->findBy(['owner' => $this->user()], ['isDefault' => 'DESC', 'id' => 'ASC']);
        $errors = [];
        $brief = trim((string) $request->request->get('brief'));

        if ($request->isMethod('POST')) {
            $this->checkCsrf($request, 'project-generate');
            $connection = $this->connection($request, $connections);
            if ($connection === null) {
                $errors[] = 'Add an AI connection first (Settings → AI connections).';
            } elseif ($brief === '') {
                $errors[] = 'Describe the template you want.';
            } else {
                $name = trim((string) $request->request->get('name')) ?: 'AI template';
                set_time_limit(300);
                try {
                    $package = $templateAi->generate($ai->closure($connection), $brief, $this->projects->manifest($this->user(), mb_substr($name, 0, 120)));
                    $project = $this->projects->createFromPackage($this->user(), $package, 'ai');
                    $this->em->persist(new ChatMessage($project, 'user', 'Design brief: '.$brief));
                    $this->em->persist(new ChatMessage($project, 'assistant', sprintf('I created "%s" from your brief. Ask me for any change.', $project->getName()), [], [], $connection->getModel()));
                    $this->em->flush();

                    return $this->redirectToRoute('project_editor', ['id' => $project->getId()]);
                } catch (InvalidTemplatePackageException $e) {
                    $errors = ['The AI answer did not pass validation even after a repair attempt:', ...$e->errors];
                } catch (\Throwable $e) {
                    $errors[] = $connection->getProviderName().': '.$e->getMessage();
                }
            }
        }

        return $this->render('project/generate.html.twig', ['connections' => $connections, 'errors' => $errors, 'brief' => $brief], new Response(status: $errors === [] ? 200 : 422));
    }

    #[Route('/{id}', name: 'project_editor', requirements: ['id' => '\d+'])]
    public function editor(TemplateProject $project): Response
    {
        $this->own($project);
        $messages = $this->em->getRepository(ChatMessage::class)->findBy(['project' => $project], ['id' => 'ASC']);

        return $this->render('project/editor.html.twig', [
            'project' => $project,
            'messages' => $messages,
            'connections' => $this->connections->findBy(['owner' => $this->user()], ['isDefault' => 'DESC', 'id' => 'ASC']),
            'samples' => SampleCv::VARIANTS,
            'locales' => SectionTypes::LOCALES,
            'files' => [
                'template' => $project->getMarkup(),
                'css' => $project->getCss(),
                'schema' => json_encode($project->getSchema(), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE),
                'manifest' => json_encode($project->getManifest(), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE),
            ],
        ]);
    }

    #[Route('/{id}/save', name: 'project_save', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function save(TemplateProject $project, Request $request): JsonResponse
    {
        $this->own($project);
        $this->checkCsrf($request, 'project-'.$project->getId());
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return new JsonResponse(['error' => 'Invalid request.'], 400);
        }
        if (\in_array($data['syntax'] ?? null, [TemplateProject::SYNTAX_TWIG, TemplateProject::SYNTAX_MUSTACHE], true)) {
            $project->setSyntax($data['syntax']);
        }
        $errors = $this->projects->save($project, array_intersect_key($data, array_flip(['template', 'css', 'schema', 'manifest'])));

        return new JsonResponse(['errors' => $errors, 'name' => $project->getName(), 'saved_at' => $project->getUpdatedAt()->format('H:i:s')]);
    }

    #[Route('/{id}/files', name: 'project_files', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function files(TemplateProject $project): JsonResponse
    {
        $this->own($project);
        $files = $project->getFiles();

        return new JsonResponse([
            'template' => $files['template'],
            'css' => $files['css'],
            'schema' => json_encode($files['schema'], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE),
            'manifest' => json_encode($files['manifest'], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE),
            'syntax' => $project->getSyntax(),
            'errors' => $project->getErrors(),
        ]);
    }

    #[Route('/{id}/preview', name: 'project_preview', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function preview(TemplateProject $project, Request $request): Response
    {
        $this->own($project);
        $sample = $request->query->getString('sample', 'default');
        $locale = $request->query->getString('locale', 'en');

        return new Response(
            $this->projects->preview($project, isset(SampleCv::VARIANTS[$sample]) ? $sample : 'default', DocumentRenderer::MODE_SCREEN, isset(SectionTypes::LOCALES[$locale]) ? $locale : 'en'),
            200,
            ['Content-Security-Policy' => self::PREVIEW_CSP, 'X-Frame-Options' => 'SAMEORIGIN', 'Cache-Control' => 'no-store'],
        );
    }

    #[Route('/{id}/preview.pdf', name: 'project_pdf', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function pdf(TemplateProject $project, Request $request, GotenbergClient $gotenberg): Response
    {
        $this->own($project);
        $sample = $request->query->getString('sample', 'default');
        try {
            $pdf = $gotenberg->htmlToPdf($this->projects->preview($project, isset(SampleCv::VARIANTS[$sample]) ? $sample : 'default', DocumentRenderer::MODE_PRINT, $request->query->getString('locale', 'en')));
        } catch (\Throwable $e) {
            return new Response('PDF rendering is unavailable: '.$e->getMessage(), 503, ['Content-Type' => 'text/plain']);
        }

        return new Response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="preview.pdf"']);
    }

    #[Route('/{id}/export', name: 'project_export', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function export(TemplateProject $project): Response
    {
        $this->own($project);
        try {
            $path = $this->projects->export($project);
        } catch (InvalidTemplatePackageException $e) {
            $this->addFlash('error', 'Fix the validation problems before exporting: '.implode(' ', $e->errors));

            return $this->redirectToRoute('project_editor', ['id' => $project->getId()]);
        }
        $manifest = $project->getManifest();
        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, sprintf('%s-%s.cvolve', $manifest['slug'] ?? 'template', $manifest['version'] ?? '1.0.0'));
        $response->headers->set('Content-Type', 'application/zip');
        $response->deleteFileAfterSend();

        return $response;
    }

    #[Route('/{id}/{action}', name: 'project_action', requirements: ['id' => '\d+', 'action' => 'duplicate|delete|thumbnail|snapshot'], methods: ['POST'])]
    public function action(TemplateProject $project, string $action, Request $request, GotenbergClient $gotenberg): Response
    {
        $this->own($project);
        $this->checkCsrf($request, 'project-'.$project->getId());

        switch ($action) {
            case 'duplicate':
                return $this->redirectToRoute('project_editor', ['id' => $this->projects->duplicate($project)->getId()]);
            case 'delete':
                $this->em->remove($project);
                $this->em->flush();
                $this->addFlash('success', sprintf('"%s" was deleted.', $project->getName()));

                return $this->redirectToRoute('project_index');
            case 'snapshot':
                $this->projects->snapshot($project, trim((string) $request->request->get('label')) ?: 'Saved '.(new \DateTimeImmutable())->format('d M H:i'));
                $this->em->flush();
                $this->addFlash('success', 'Revision saved.');

                return $this->redirectToRoute('project_revisions', ['id' => $project->getId()]);
            default:
                try {
                    $png = $gotenberg->screenshot($this->projects->preview($project, 'default', DocumentRenderer::MODE_PRINT));
                    $project->addAsset(new ProjectAsset($project, 'thumbnail.png', $png));
                    $project->touch();
                    $this->em->flush();
                    $this->addFlash('success', 'Thumbnail rendered from the sample CV.');
                } catch (\Throwable $e) {
                    $this->addFlash('error', 'Could not render the thumbnail: '.$e->getMessage());
                }

                return $this->redirectToRoute('project_editor', ['id' => $project->getId()]);
        }
    }

    #[Route('/{id}/thumbnail', name: 'project_thumbnail', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function thumbnail(TemplateProject $project): Response
    {
        $this->own($project);
        $asset = $project->getAsset('thumbnail.png') ?? $project->getAsset('thumbnail.jpg') ?? throw $this->createNotFoundException();

        return new Response($asset->getContent(), 200, ['Content-Type' => str_ends_with($asset->getPath(), 'png') ? 'image/png' : 'image/jpeg', 'Cache-Control' => 'private, max-age=60']);
    }

    #[Route('/{id}/assets', name: 'project_asset_upload', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function uploadAsset(TemplateProject $project, Request $request): Response
    {
        $this->own($project);
        $this->checkCsrf($request, 'project-'.$project->getId());
        $added = [];
        foreach ((array) $request->files->get('assets', []) as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }
            $name = (string) preg_replace('/[^A-Za-z0-9._-]/', '-', (string) $file->getClientOriginalName());
            $path = preg_match('/\.(png|jpe?g)$/i', $name) ? 'thumbnail.'.strtolower(pathinfo($name, \PATHINFO_EXTENSION)) : 'fonts/'.$name;
            try {
                $project->addAsset(new ProjectAsset($project, $path, (string) file_get_contents($file->getPathname())));
                $added[] = $path;
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }
        if ($added !== []) {
            // Declare uploaded fonts in the manifest so they are embedded
            $manifest = $project->getManifest();
            $fonts = \is_array($manifest['fonts'] ?? null) ? $manifest['fonts'] : [];
            foreach ($added as $path) {
                if (str_starts_with($path, 'fonts/') && !\in_array($path, array_column($fonts, 'file'), true)) {
                    $base = (string) preg_replace('/\.(woff2?|ttf|otf)$/i', '', basename($path));
                    $fonts[] = [
                        'family' => ucwords(trim((string) preg_replace('/[-_](\d{3}|regular|bold|italic|medium|light|semibold|normal|latin|variable).*$/i', '', $base)) ?: $base),
                        'file' => $path,
                        'weight' => preg_match('/(\d{3})/', $base, $w) ? $w[1] : (preg_match('/bold/i', $base) ? '700' : '400'),
                        'style' => preg_match('/italic/i', $base) ? 'italic' : 'normal',
                    ];
                }
            }
            $manifest['fonts'] = $fonts;
            $this->projects->save($project, ['manifest' => $manifest]);
            $this->addFlash('success', 'Added '.implode(', ', $added).'. Fonts were declared in manifest.json: use the family name in your CSS.');
        }

        return $this->redirectToRoute('project_editor', ['id' => $project->getId()]);
    }

    #[Route('/{id}/assets/delete', name: 'project_asset_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteAsset(TemplateProject $project, Request $request): Response
    {
        $this->own($project);
        $this->checkCsrf($request, 'project-'.$project->getId());
        $asset = $project->getAsset((string) $request->request->get('path'));
        if ($asset !== null) {
            $project->removeAsset($asset);
            $manifest = $project->getManifest();
            $manifest['fonts'] = array_values(array_filter(\is_array($manifest['fonts'] ?? null) ? $manifest['fonts'] : [], static fn ($f) => \is_array($f) && ($f['file'] ?? null) !== $asset->getPath()));
            $this->projects->save($project, ['manifest' => $manifest]);
        }

        return $this->redirectToRoute('project_editor', ['id' => $project->getId()]);
    }

    #[Route('/{id}/revisions', name: 'project_revisions', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function revisions(TemplateProject $project): Response
    {
        $this->own($project);

        return $this->render('project/revisions.html.twig', [
            'project' => $project,
            'revisions' => $this->em->getRepository(ProjectRevision::class)->findBy(['project' => $project], ['id' => 'DESC']),
        ]);
    }

    #[Route('/revisions/{id}/restore', name: 'project_revision_restore', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function restore(ProjectRevision $revision, Request $request): Response
    {
        $this->own($revision);
        $this->checkCsrf($request, 'project-'.$revision->getProject()->getId());
        $this->projects->restore($revision);
        $this->addFlash('success', sprintf('Restored "%s".', $revision->getLabel()));

        return $this->redirectToRoute('project_editor', ['id' => $revision->getProject()->getId()]);
    }

    // ------------------------------------------------------------------ AI chat

    #[Route('/{id}/chat', name: 'project_chat', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function chat(TemplateProject $project, Request $request, DesignChat $chat): JsonResponse
    {
        $this->own($project);
        $this->checkCsrf($request, 'project-'.$project->getId());
        $connection = $this->connection($request, $this->connections->findBy(['owner' => $this->user()], ['isDefault' => 'DESC']));
        $text = trim((string) $request->request->get('message'));
        if ($connection === null) {
            return new JsonResponse(['error' => 'Add an AI connection in Settings → AI connections first.'], 422);
        }
        if ($text === '') {
            return new JsonResponse(['error' => 'Type a request first.'], 422);
        }
        $reply = $chat->ask($project, $connection, mb_substr($text, 0, 6000));

        return new JsonResponse(['html' => $this->renderView('project/_message.html.twig', ['message' => $reply, 'diff' => $chat->diff($reply)])]);
    }

    #[Route('/chat/{id}/{action}', name: 'project_chat_resolve', requirements: ['id' => '\d+', 'action' => 'apply|discard'], methods: ['POST'])]
    public function resolve(ChatMessage $message, string $action, Request $request, DesignChat $chat): JsonResponse
    {
        $this->own($message);
        $this->checkCsrf($request, 'project-'.$message->getProject()->getId());
        $diff = $chat->diff($message);
        try {
            $action === 'apply' ? $chat->apply($message) : $chat->discard($message);
        } catch (\LogicException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 409);
        }

        return new JsonResponse([
            'status' => $message->getStatus(),
            'html' => $this->renderView('project/_message.html.twig', ['message' => $message, 'diff' => $diff]),
        ]);
    }

    /**
     * @param list<AiConnection> $connections the user's connections, default first
     */
    private function connection(Request $request, array $connections): ?AiConnection
    {
        $id = $request->request->getInt('connection');
        foreach ($connections as $connection) {
            if ($id === 0 || $connection->getId() === $id) {
                return $connection;
            }
        }

        return null;
    }
}
