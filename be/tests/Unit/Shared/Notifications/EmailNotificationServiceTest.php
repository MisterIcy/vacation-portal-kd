<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Notifications;

use App\Shared\Notifications\EmailMessage;
use App\Shared\Notifications\EmailNotificationService;
use App\Shared\Notifications\EmailTransport;
use App\Shared\Notifications\NotificationFailed;
use App\Shared\Notifications\VacationDecision;
use App\Shared\Notifications\VacationDecisionNotification;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class EmailNotificationServiceTest extends TestCase
{
    public function testAnApprovalIsAnEmailToTheEmployeeWithTheDetails(): void
    {
        $sent = $this->sendThroughARecordingTransport($this->notification(VacationDecision::Approved));

        $this->assertCount(1, $sent);
        $this->assertSame('ada@example.com', $sent[0]->to);
        $this->assertSame('Ada Lovelace', $sent[0]->toName);
        $this->assertSame('Your vacation request was approved', $sent[0]->subject);
        $this->assertStringContainsString('Hello Ada Lovelace,', $sent[0]->body);
        $this->assertStringContainsString('request #5 from 2026-07-01 to 2026-07-03 (3 days) has been approved.', $sent[0]->body);
        $this->assertStringContainsString('Your reason: Holiday', $sent[0]->body);
        $this->assertStringNotContainsString('Message from your manager', $sent[0]->body);
    }

    public function testARejectionSaysSoAndIncludesTheManagersMessage(): void
    {
        $sent = $this->sendThroughARecordingTransport($this->notification(VacationDecision::Rejected, 'Team is short that week.'));

        $this->assertSame('Your vacation request was rejected', $sent[0]->subject);
        $this->assertStringContainsString('has been rejected.', $sent[0]->body);
        $this->assertStringContainsString('Message from your manager: Team is short that week.', $sent[0]->body);
    }

    public function testOneDayIsNotWrittenAsDays(): void
    {
        $notification = new VacationDecisionNotification(
            2,
            'Ada',
            'ada@example.com',
            5,
            new DateTimeImmutable('2026-07-01'),
            new DateTimeImmutable('2026-07-01'),
            1,
            'Dentist',
            VacationDecision::Approved,
        );

        $sent = $this->sendThroughARecordingTransport($notification);

        $this->assertStringContainsString('(1 day)', $sent[0]->body);
    }

    public function testAnAddressThatIsNotValidIsAPermanentFailureAndNothingIsSent(): void
    {
        $transport = $this->createMock(EmailTransport::class);
        $transport->expects($this->never())->method('deliver');

        $notification = new VacationDecisionNotification(
            2,
            'Ada',
            'not-an-address',
            5,
            new DateTimeImmutable('2026-07-01'),
            new DateTimeImmutable('2026-07-03'),
            3,
            'Holiday',
            VacationDecision::Approved,
        );

        try {
            (new EmailNotificationService($transport))->send($notification);
            $this->fail('Expected a NotificationFailed.');
        } catch (NotificationFailed $e) {
            $this->assertFalse($e->isTransient());
            $this->assertStringNotContainsString('not-an-address', $e->getMessage(), 'the address is personal data');
        }
    }

    public function testATransportFailureReachesTheCaller(): void
    {
        $transport = $this->createMock(EmailTransport::class);
        $transport->method('deliver')->willThrowException(NotificationFailed::transient('disk full'));

        $this->expectException(NotificationFailed::class);
        $this->expectExceptionMessage('disk full');

        (new EmailNotificationService($transport))->send($this->notification(VacationDecision::Approved));
    }

    public function testLineBreaksCannotSmuggleHeadersIntoAnEmail(): void
    {
        $message = new EmailMessage('ada@example.com', "Ada\r\nBcc: boss@example.com", "Hi\nBcc: x@example.com", 'Body');

        $this->assertStringNotContainsString("\n", $message->toName);
        $this->assertStringNotContainsString("\r", $message->toName);
        $this->assertStringNotContainsString("\n", $message->subject);
    }

    public function testAMessageThatIsTooLongIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->notification(VacationDecision::Rejected, str_repeat('x', VacationDecisionNotification::MAX_MESSAGE_LENGTH + 1));
    }

    public function testABlankMessageCountsAsNoMessage(): void
    {
        $this->assertNull($this->notification(VacationDecision::Rejected, "  \n ")->message);
    }

    /**
     * @return list<EmailMessage>
     */
    private function sendThroughARecordingTransport(VacationDecisionNotification $notification): array
    {
        $sent = [];
        $transport = $this->createMock(EmailTransport::class);
        $transport->method('deliver')->willReturnCallback(function (EmailMessage $message) use (&$sent): void {
            $sent[] = $message;
        });

        (new EmailNotificationService($transport))->send($notification);

        return $sent;
    }

    private function notification(VacationDecision $decision, ?string $message = null): VacationDecisionNotification
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
            $message,
        );
    }
}
