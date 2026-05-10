<?php declare(strict_types=1);

namespace App\YoutubeDl;

final class OutputParser
{
    public const PROGRESS_PATTERN = '#\[download\]\s+(?<percentage>\d+(?:\.\d+)?%)\s+of\s+~?\s?(?<size>[~]?\d+(?:\.\d+)?(?:K|M|G)iB)(?:\s+at\s+(?<speed>(\d+(?:\.\d+)?(?:K|M|G)iB/s)|Unknown speed))?(?:\s+ETA\s+(?<eta>([\d:]{2,8}|Unknown ETA)))?(\s+in\s+(?<totalTime>[\d:]{2,8}))?#i';

    public const TITLE_DESTINATION_PATTERN = '/\[(download|ffmpeg|ExtractAudio)] Destination: (?<file>.+)/';

    public const TITLE_ALREADY_DOWNLOADED_PATTERN = '/\[download] (?<file>.+) has already been downloaded/';

    public function parseTitle(string $buffer): ?string
    {
        if (preg_match(self::TITLE_DESTINATION_PATTERN, $buffer, $match) === 1
            || preg_match(self::TITLE_ALREADY_DOWNLOADED_PATTERN, $buffer, $match) === 1)
        {
            return basename($match['file']);
        }

        return null;
    }

    /**
     * @return list<array{percentage: string, size: string, speed: ?string, eta: ?string, totalTime: ?string}>
     */
    public function parseProgress(string $buffer): array
    {
        if (preg_match_all(self::PROGRESS_PATTERN, $buffer, $matches, PREG_SET_ORDER) === false)
        {
            return [];
        }

        $progresses = [];

        foreach ($matches as $match)
        {
            $progresses[] = [
                'percentage' => str_replace('%', '', $match['percentage']),
                'size'       => $match['size'],
                'speed'      => self::nullIfEmpty($match['speed']     ?? null),
                'eta'        => self::nullIfEmpty($match['eta']       ?? null),
                'totalTime'  => self::nullIfEmpty($match['totalTime'] ?? null),
            ];
        }

        return $progresses;
    }

    private static function nullIfEmpty(?string $value): ?string
    {
        return ($value === null || $value === '') ? null : $value;
    }
}
