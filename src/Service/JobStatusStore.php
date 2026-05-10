<?php declare(strict_types=1);

namespace App\Service;

final class JobStatusStore
{
    public function __construct(private readonly string $jobsDir)
    {
    }

    public function markPending(string $jobId, string $url): void
    {
        $this->write($jobId, [
            'id' => $jobId,
            'url' => $url,
            'status' => 'pending',
            'title' => null,
            'error' => null,
            'queuedAt' => $this->now(),
            'finishedAt' => null,
        ]);
    }

    public function markSuccess(string $jobId, ?string $title): void
    {
        $existing = $this->read($jobId) ?? [];
        $this->write($jobId, [
            'id' => $jobId,
            'url' => $existing['url'] ?? null,
            'status' => 'success',
            'title' => $title,
            'error' => null,
            'queuedAt' => $existing['queuedAt'] ?? $this->now(),
            'finishedAt' => $this->now(),
        ]);
    }

    public function markFailed(string $jobId, string $error): void
    {
        $existing = $this->read($jobId) ?? [];
        $this->write($jobId, [
            'id' => $jobId,
            'url' => $existing['url'] ?? null,
            'status' => 'failed',
            'title' => null,
            'error' => $error,
            'queuedAt' => $existing['queuedAt'] ?? $this->now(),
            'finishedAt' => $this->now(),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function read(string $jobId): ?array
    {
        $path = $this->pathFor($jobId);
        if (!is_file($path)) {
            return null;
        }
        $contents = @file_get_contents($path);
        if ($contents === false) {
            return null;
        }
        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function write(string $jobId, array $data): void
    {
        if (!is_dir($this->jobsDir)) {
            mkdir($this->jobsDir, 0775, true);
        }
        $path = $this->pathFor($jobId);
        $tmp = $path.'.tmp';
        file_put_contents($tmp, json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        rename($tmp, $path);
    }

    private function pathFor(string $jobId): string
    {
        if (preg_match('/^[a-zA-Z0-9_-]+$/', $jobId) !== 1) {
            throw new \InvalidArgumentException('Invalid job id');
        }

        return $this->jobsDir.'/'.$jobId.'.json';
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
    }
}
