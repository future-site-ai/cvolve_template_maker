<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\ChatMessage;
use App\Service\Project\DesignChat;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class AppExtension extends AbstractExtension
{
    public function __construct(private readonly DesignChat $chat)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('chat_diff', fn (ChatMessage $m) => $this->chat->diff($m))];
    }

    public function getFilters(): array
    {
        return [new TwigFilter('diff_compact', [$this, 'diffCompact'])];
    }

    /**
     * Keep changed lines with some context; fold long unchanged runs.
     *
     * @param list<array{type: string, line: string}> $diff
     *
     * @return list<array{type: string, line: string}>
     */
    public function diffCompact(array $diff, int $context = 2): array
    {
        $keep = [];
        foreach ($diff as $i => $row) {
            if ($row['type'] !== 'same') {
                for ($j = max(0, $i - $context); $j <= min(\count($diff) - 1, $i + $context); ++$j) {
                    $keep[$j] = true;
                }
            }
        }
        $out = [];
        $skipped = 0;
        foreach ($diff as $i => $row) {
            if (isset($keep[$i])) {
                if ($skipped > 0) {
                    $out[] = ['type' => 'collapsed', 'line' => sprintf('… %d unchanged lines', $skipped)];
                    $skipped = 0;
                }
                $out[] = $row;
            } else {
                ++$skipped;
            }
        }
        if ($skipped > 0) {
            $out[] = ['type' => 'collapsed', 'line' => sprintf('… %d unchanged lines', $skipped)];
        }

        return $out;
    }
}
