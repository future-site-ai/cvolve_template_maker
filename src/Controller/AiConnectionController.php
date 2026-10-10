<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AiConnection;
use App\Repository\AiConnectionRepository;
use App\Service\Ai\AiClient;
use App\Service\Ai\SecretBox;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * "Bring your own key": each designer connects their own AI providers.
 */
#[Route('/settings/ai')]
class AiConnectionController extends BaseController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AiConnectionRepository $connections,
        private readonly SecretBox $secrets,
    ) {
    }

    #[Route('', name: 'ai_index')]
    public function index(): Response
    {
        return $this->render('settings/ai.html.twig', [
            'connections' => $this->connections->findBy(['owner' => $this->user()], ['id' => 'ASC']),
            'providers' => AiConnection::PROVIDERS,
        ]);
    }

    #[Route('/new', name: 'ai_new', methods: ['GET', 'POST'])]
    #[Route('/{id}/edit', name: 'ai_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, ?AiConnection $connection = null): Response
    {
        if ($connection !== null) {
            $this->own($connection);
        }
        $errors = [];
        if ($request->isMethod('POST')) {
            $this->checkCsrf($request, 'ai-connection');
            $provider = (string) $request->request->get('provider');
            $label = trim((string) $request->request->get('label'));
            $model = trim((string) $request->request->get('model'));
            $baseUrl = trim((string) $request->request->get('base_url'));
            $key = trim((string) $request->request->get('api_key'));

            if (!isset(AiConnection::PROVIDERS[$provider])) {
                $errors[] = 'Choose a provider.';
            }
            $model = $model !== '' ? $model : (string) (AiConnection::PROVIDERS[$provider][1] ?? '');
            if ($model === '') {
                $errors[] = 'Enter the model name.';
            }
            if ($provider === 'openai_like' && !filter_var($baseUrl, \FILTER_VALIDATE_URL)) {
                $errors[] = 'An OpenAI-compatible connection needs the API base URL (e.g. https://openrouter.ai/api/v1).';
            }
            if ($baseUrl !== '' && !preg_match('#^https?://#i', $baseUrl)) {
                $errors[] = 'The base URL must start with http:// or https://.';
            }
            $needsKey = AiConnection::PROVIDERS[$provider][2] ?? false;
            if ($needsKey && $key === '' && $connection?->getEncryptedKey() === null) {
                $errors[] = 'Enter your API key.';
            }

            if ($errors === []) {
                $connection ??= new AiConnection($this->user(), $label, $provider, $model);
                $connection->setLabel($label !== '' ? mb_substr($label, 0, 80) : (AiConnection::PROVIDERS[$provider][0]))
                    ->setProvider($provider)->setModel(mb_substr($model, 0, 120))->setBaseUrl($baseUrl);
                if ($key !== '') {
                    $connection->setEncryptedKey($this->secrets->encrypt($key), SecretBox::hint($key));
                }
                if ($this->connections->count(['owner' => $this->user()]) === 0 || $connection->getId() === null && $request->request->getBoolean('default')) {
                    $this->makeDefault($connection);
                }
                $this->em->persist($connection);
                $this->em->flush();
                $this->addFlash('success', 'Connection saved. Use "Test" to check it.');

                return $this->redirectToRoute('ai_index');
            }
        }

        return $this->render('settings/ai_form.html.twig', [
            'connection' => $connection,
            'providers' => AiConnection::PROVIDERS,
            'errors' => $errors,
        ], new Response(status: $errors === [] ? 200 : 422));
    }

    #[Route('/{id}/{action}', name: 'ai_action', requirements: ['id' => '\d+', 'action' => 'test|default|delete'], methods: ['POST'])]
    public function action(AiConnection $connection, string $action, Request $request, AiClient $client): Response
    {
        $this->own($connection);
        $this->checkCsrf($request, 'ai-'.$connection->getId());

        switch ($action) {
            case 'test':
                $error = $client->test($connection);
                $connection->recordTest($error);
                $error === null ? $this->addFlash('success', sprintf('%s answered. The connection works.', $connection->getLabel())) : $this->addFlash('error', 'Test failed: '.$error);
                break;
            case 'default':
                $this->makeDefault($connection);
                break;
            case 'delete':
                $this->em->remove($connection);
                $this->addFlash('success', 'Connection deleted.');
                break;
        }
        $this->em->flush();

        return $this->redirectToRoute('ai_index');
    }

    private function makeDefault(AiConnection $connection): void
    {
        foreach ($this->connections->findBy(['owner' => $connection->getOwner()]) as $other) {
            $other->setDefault(false);
        }
        $connection->setDefault(true);
    }
}
