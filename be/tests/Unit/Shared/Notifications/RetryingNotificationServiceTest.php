<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Notifications;

use App\Shared\Events\VacationApprovedEvent;
use App\Shared\Events\VacationRejectedEvent;
use App\Shared\Logger;
use App\Shared\Notifications\NotificationFailed;
use App\Shared\Notifications\NotificationService;
use App\Shared\Notifications\RetryingNotificationService;
use App\Shared\Notifications\SendVacationApprovedNotificationHandler;
use App\Shared\Notifications\SendVacationRejectedNotificationHandler;
use App\Shared\Notifications\VacationDecision;
use App\Shared\Notifications\VacationDecisionNotification;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RetryingNotificationServiceTest extends TestCase
{
    /** @var list<string> */
    private array $warnings = [];
    /** @var list<int> */
    private array $sleeps = [];

    protected function setUp(): void
    {
        $this->warnings = [];
        $this->sleeps = [];
    }

    public function testASuccessfulSendIsNotRepeated(): void
    {
        $inner = $this->createMock(NotificationService::class);
        $inner->expects($this->once())->method('send');

        $this->service($inner)->send($this->notification());

        $this->assertSame([], $this->warnings);
        $this->assertSame([], $this->sleeps);
    }

    public function testATransientFailureIsRetriedWithGrowingWaitsUntilItSucceeds(): void
    {
        $calls = 0;
        $inner = $this->createMock(NotificationService::class);
        $inner->method('send')->willReturnCallback(function () use (&$calls): void {
            if (++$calls < 3) {
                throw NotificationFailed::transient('disk busy');
            }
        });

        $this->service($inner)->send($this->notification());

        $this->assertSame(3, $calls);
        $this->assertSame([100, 200], $this->sleeps);
        $this->assertCount(2, $this->warnings);
        $this->assertStringContainsString('attempt 1 of 3, will retry', $this->warnings[0]);
        $this->assertStringContainsString('vacation 5', $this->warnings[0]);
    }

    public function testItGivesUpAfterTheLastAttemptAndSaysSo(): void
    {
        $inner = $this->createMock(NotificationService::class);
        $inner->expects($this->exactly(3))->method('send')->willThrowException(NotificationFailed::transient('disk full'));

        try {
            $this->service($inner)->send($this->notification());
            $this->fail('Expected a NotificationFailed.');
        } catch (NotificationFailed $e) {
            $this->assertSame('disk full', $e->getMessage());
        }

        $this->assertSame([100, 200], $this->sleeps, 'no wait after the last attempt');
        $this->assertStringContainsString('attempt 3 of 3, giving up', $this->warnings[2]);
    }

    public function testAPermanentFailureIsNotRetried(): void
    {
        $inner = $this->createMock(NotificationService::class);
        $inner->expects($this->once())->method('send')->willThrowException(NotificationFailed::permanent('bad address'));

        $this->expectException(NotificationFailed::class);

        try {
            $this->service($inner)->send($this->notification());
        } finally {
            $this->assertSame([], $this->sleeps);
            $this->assertStringContainsString('giving up', $this->warnings[0]);
        }
    }

    public function testAtLeastOneAttemptIsRequired(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RetryingNotificationService($this->createMock(NotificationService::class), $this->createMock(Logger::class), 0);
    }

    public function testTheApprovedHandlerSendsTheNotificationOfTheEvent(): void
    {
        $notification = $this->notification();
        $service = $this->createMock(NotificationService::class);
        $service->expects($this->once())->method('send')->with($this->identicalTo($notification));

        (new SendVacationApprovedNotificationHandler($service))(new VacationApprovedEvent($notification));
    }

    public function testTheRejectedHandlerSendsTheNotificationOfTheEvent(): void
    {
        $notification = $this->notification(VacationDecision::Rejected);
        $service = $this->createMock(NotificationService::class);
        $service->expects($this->once())->method('send')->with($this->identicalTo($notification));

        (new SendVacationRejectedNotificationHandler($service))(new VacationRejectedEvent($notification));
    }

    public function testAHandlerLetsTheFailureOfTheServiceThroughForTheDispatcherToLog(): void
    {
        $service = $this->createMock(NotificationService::class);
        $service->method('send')->willThrowException(NotificationFailed::permanent('bad address'));

        $this->expectException(NotificationFailed::class);

        (new SendVacationApprovedNotificationHandler($service))(new VacationApprovedEvent($this->notification()));
    }

    private function service(NotificationService $inner): RetryingNotificationService
    {
        $logger = $this->createMock(Logger::class);
        $logger->method('warning')->willReturnCallback(function (string $message): void {
            $this->warnings[] = $message;
        });

        return new RetryingNotificationService($inner, $logger, 3, 100, function (int $ms): void {
            $this->sleeps[] = $ms;
        });
    }

    private function notification(VacationDecision $decision = VacationDecision::Approved): VacationDecisionNotification
    {
        return new VacationDecisionNotification(
            2,
            'Ada Lovelace',
            'ada@example.com',
            5,
            new DateTimeImmutable('2026-07-01'),
            new DateTimeImmutable('2026-07-03'),
            3,
            'Holiday',
            $decision,
        );
    }
}
