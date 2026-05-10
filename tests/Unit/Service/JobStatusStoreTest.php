<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\JobStatusStore;
use PHPUnit\Framework\TestCase;

class JobStatusStoreTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir().'/sfdl-jobstatus-'.uniqid('', true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir))
        {
            foreach (glob($this->tmpDir.'/*') ?: [] as $file)
            {
                @unlink($file);
            }
            @rmdir($this->tmpDir);
        }
    }

    public function testReadReturnsNullForUnknownJob(): void
    {
        self::assertNull($this->store()->read('missing'));
    }

    public function testMarkPendingPersistsRecord(): void
    {
        $store = $this->store();
        $store->markPending('job-1', 'https://example.com/v');

        $data = $store->read('job-1');

        self::assertNotNull($data);
        self::assertSame('job-1',                 $data['id']);
        self::assertSame('https://example.com/v', $data['url']);
        self::assertSame('pending',               $data['status']);
        self::assertNull($data['title']);
        self::assertNull($data['error']);
        self::assertNotEmpty($data['queuedAt']);
        self::assertNull($data['finishedAt']);
    }

    public function testMarkSuccessUpdatesStatusAndTitle(): void
    {
        $store = $this->store();
        $store->markPending('job-1', 'https://example.com/v');
        $store->markSuccess('job-1', 'My Video');

        $data = $store->read('job-1');

        self::assertSame('success',               $data['status']);
        self::assertSame('My Video',              $data['title']);
        self::assertSame('https://example.com/v', $data['url']);
        self::assertNull($data['error']);
        self::assertNotEmpty($data['finishedAt']);
    }

    public function testMarkFailedUpdatesStatusAndError(): void
    {
        $store = $this->store();
        $store->markPending('job-1', 'https://example.com/v');
        $store->markFailed('job-1', 'oops');

        $data = $store->read('job-1');

        self::assertSame('failed', $data['status']);
        self::assertSame('oops',   $data['error']);
        self::assertNull($data['title']);
        self::assertNotEmpty($data['finishedAt']);
    }

    public function testMarkSuccessWithoutPriorPendingStillCreatesRecord(): void
    {
        $store = $this->store();
        $store->markSuccess('job-orphan', 'Title');

        $data = $store->read('job-orphan');

        self::assertSame('success', $data['status']);
        self::assertSame('Title',   $data['title']);
        self::assertNull($data['url']);
    }

    public function testCreatesJobsDirOnFirstWrite(): void
    {
        self::assertDirectoryDoesNotExist($this->tmpDir);

        $this->store()->markPending('job-1', 'https://example.com/v');

        self::assertDirectoryExists($this->tmpDir);
    }

    public function testInvalidJobIdRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->store()->markPending('../etc/passwd', 'https://example.com/v');
    }

    public function testReadIgnoresGarbageFile(): void
    {
        if (!is_dir($this->tmpDir))
        {
            mkdir($this->tmpDir, 0775, true);
        }
        file_put_contents($this->tmpDir.'/job-1.json', 'this is not json');

        self::assertNull($this->store()->read('job-1'));
    }

    private function store(): JobStatusStore
    {
        return new JobStatusStore($this->tmpDir);
    }
}
