<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Notifications;

use App\Shared\Notifications\EmailMessage;
use App\Shared\Notifications\FileEmailTransport;
use App\Shared\Notifications\NotificationFailed;
use PHPUnit\Framework\TestCase;

class FileEmailTransportTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vacation-notifications-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);
    }

    public function testEachEmailIsItsOwnFileInTheDirectoryWhichIsCreatedOnDemand(): void
    {
        $transport = new FileEmailTransport($this->directory, 'Portal <no-reply@example.com>');

        $transport->deliver(new EmailMessage('ada@example.com', 'Ada', 'First', 'one'));
        $transport->deliver(new EmailMessage('ada@example.com', 'Ada', 'Second', 'two'));

        $files = glob($this->directory . DIRECTORY_SEPARATOR . '*.eml') ?: [];
        $this->assertCount(2, $files);
    }

    public function testTheFileHoldsTheHeadersAndTheBody(): void
    {
        (new FileEmailTransport($this->directory, 'Portal <no-reply@example.com>'))
            ->deliver(new EmailMessage('ada@example.com', 'Ada Lovelace', 'Your vacation request was approved', "Hello\r\n"));

        $files = glob($this->directory . DIRECTORY_SEPARATOR . '*.eml') ?: [];
        $content = (string) file_get_contents($files[0]);

        $this->assertStringContainsString("From: Portal <no-reply@example.com>\r\n", $content);
        $this->assertStringContainsString("To: Ada Lovelace <ada@example.com>\r\n", $content);
        $this->assertStringContainsString("Subject: Your vacation request was approved\r\n", $content);
        $this->assertStringEndsWith("\r\n\r\nHello\r\n", $content);
    }

    public function testTheFileNameNeverContainsAnythingTheUserTyped(): void
    {
        (new FileEmailTransport($this->directory, 'a@example.com'))
            ->deliver(new EmailMessage('ada@example.com', '../../evil', '../../evil', 'x'));

        $files = glob($this->directory . DIRECTORY_SEPARATOR . '*') ?: [];
        $this->assertCount(1, $files);
        $this->assertMatchesRegularExpression('/^\d{8}-\d{6}-[0-9a-f]{8}\.eml$/', basename($files[0]));
    }

    public function testADirectoryThatCannotBeCreatedIsATransientFailure(): void
    {
        // A file stands where the directory should be, so it can neither be created nor used.
        file_put_contents($this->directory, 'not a directory');

        try {
            (new FileEmailTransport($this->directory . DIRECTORY_SEPARATOR . 'sub', 'a@example.com'))
                ->deliver(new EmailMessage('ada@example.com', 'Ada', 'Hi', 'x'));
            $this->fail('Expected a NotificationFailed.');
        } catch (NotificationFailed $e) {
            $this->assertTrue($e->isTransient());
        } finally {
            unlink($this->directory);
        }
    }
}
