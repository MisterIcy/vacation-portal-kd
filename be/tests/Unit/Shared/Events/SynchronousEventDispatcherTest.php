<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Events;

use App\Shared\Events\SynchronousEventDispatcher;
use App\Shared\Events\VacationApprovedEvent;
use App\Shared\Events\VacationRejectedEvent;
use App\Shared\Logger;
use App\Shared\Notifications\VacationDecision;
use App\Shared\Notifications\VacationDecisionNotification;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SynchronousEventDispatcherTest extends TestCase
{
    public function testAListenerReceivesTheEventsItRegisteredFor(): void
    {
        $received = [];
        $dispatcher = new SynchronousEventDispatcher($this->createMock(Logger::class));
        $dispatcher->listen(VacationApprovedEvent::class, function (VacationApprovedEvent $e) use (&$received): void {
            $received[] = $e;
        });

        $approved = new VacationApprovedEvent($this->notification(VacationDecision::Approved));
        $dispatcher->dispatch($approved);
        $dispatcher->dispatch(new VacationRejectedEvent($this->notification(VacationDecision::Rejected)));

        $this->assertSame([$approved], $received);
    }

    public function testListenersRunInTheOrderTheyWereRegistered(): void
    {
        $order = [];
        $dispatcher = new SynchronousEventDispatcher($this->createMock(Logger::class));
        $dispatcher->listen(VacationApprovedEvent::class, function () use (&$order): void {
            $order[] = 'first';
        });
        $dispatcher->listen(VacationApprovedEvent::class, function () use (&$order): void {
            $order[] = 'second';
        });

        $dispatcher->dispatch(new VacationApprovedEvent($this->notification(VacationDecision::Approved)));

        $this->assertSame(['first', 'second'], $order);
    }

    public function testAnEventWithoutListenersIsFine(): void
    {
        $logger = $this->createMock(Logger::class);
        $logger->expects($this->never())->method('error');

        (new SynchronousEventDispatcher($logger))->dispatch(new VacationApprovedEvent($this->notification(VacationDecision::Approved)));
    }

    public function testAFailingListenerIsLoggedAndDoesNotStopTheNextOne(): void
    {
        $errors = [];
        $logger = $this->createMock(Logger::class);
        $logger->method('error')->willReturnCallback(function (string $message) use (&$errors): void {
            $errors[] = $message;
        });
        $ran = false;

        $dispatcher = new SynchronousEventDispatcher($logger);
        $dispatcher->listen(VacationApprovedEvent::class, function (): void {
            throw new RuntimeException('disk full');
        });
        $dispatcher->listen(VacationApprovedEvent::class, function () use (&$ran): void {
            $ran = true;
        });

        $dispatcher->dispatch(new VacationApprovedEvent($this->notification(VacationDecision::Approved)));

        $this->assertTrue($ran);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString(VacationApprovedEvent::class, $errors[0]);
        $this->assertStringContainsString('disk full', $errors[0]);
        $this->assertStringNotContainsString('ada@example.com', $errors[0], 'personal data stays out of the log');
    }

    private function notification(VacationDecision $decision): VacationDecisionNotification
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
