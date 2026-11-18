<?php

declare(strict_types=1);

namespace App\Domains\Vacations\Controllers;

use App\Domains\Authentication\Services\AccessControlService;
use App\Domains\Vacations\Models\VacationRequest;
use App\Domains\Vacations\Requests\CreateVacationRequest;
use App\Domains\Vacations\Services\CancelVacation;
use App\Domains\Vacations\Services\DecideVacation;
use App\Domains\Vacations\Services\ListVacations;
use App\Domains\Vacations\Services\RequestVacation;
use App\Shared\Events\EventDispatcher;
use App\Shared\Events\VacationApprovedEvent;
use App\Shared\Events\VacationRejectedEvent;
use App\Shared\Notifications\VacationDecision;
use App\Shared\Notifications\VacationDecisionNotification;
use App\Shared\RequestBody;
use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The HTTP side of vacation requests: read the request, call the use case for the caller, and
 * write the response. The rules (who may do what, and when) live in the use cases and the
 * vacation request itself.
 */
readonly class VacationsController
{
    public function __construct(
        private AccessControlService $accessControl,
        private ListVacations $listVacations,
        private RequestVacation $requestVacation,
        private DecideVacation $decideVacation,
        private CancelVacation $cancelVacation,
        private EventDispatcher $events,
    ) {
    }

    public function list(ServerRequestInterface $request): ResponseInterface
    {
        $vacations = ($this->listVacations)($this->accessControl->principalFromRequest($request));

        return new JsonResponse($vacations, 200);
    }

    /**
     * The body is optional; it may carry a "message" for the employee.
     *
     * @param array{id: int} $params
     * @throws JsonException
     */
    public function approve(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $message = $this->optionalMessage($request);

        $decided = $this->decideVacation->approve($this->accessControl->principalFromRequest($request), (int) $params['id']);

        $this->announce($decided, VacationDecision::Approved, $message);

        return new JsonResponse($decided, 200);
    }

    /**
     * The body is optional; it may carry a "message" for the employee.
     *
     * @param array{id: int} $params
     * @throws JsonException
     */
    public function reject(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $message = $this->optionalMessage($request);

        $decided = $this->decideVacation->reject($this->accessControl->principalFromRequest($request), (int) $params['id']);

        $this->announce($decided, VacationDecision::Rejected, $message);

        return new JsonResponse($decided, 200);
    }

    /**
     * @throws JsonException
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $input = CreateVacationRequest::fromRequest($request);

        $vacation = ($this->requestVacation)(
            $this->accessControl->principalFromRequest($request),
            $input->getFrom(),
            $input->getTo(),
            $input->getReason(),
        );

        return new JsonResponse($vacation, 201);
    }

    /**
     * @param array{id: int} $params
     */
    public function delete(ServerRequestInterface $request, array $params): ResponseInterface
    {
        ($this->cancelVacation)($this->accessControl->principalFromRequest($request), (int) $params['id']);

        return new JsonResponse('', 204);
    }

    /**
     * Read before the decision is made, so a malformed message is a 400 that changes nothing.
     * Clients that send no body at all are fine.
     *
     * @throws InvalidArgumentException when the message is not a string or is too long
     * @throws JsonException when a body is sent that is not valid JSON
     */
    private function optionalMessage(ServerRequestInterface $request): ?string
    {
        if (trim((string) $request->getBody()) === '') {
            return null;
        }

        $message = RequestBody::fromRequest($request)->optionalString('message');
        if ($message !== null && mb_strlen(trim($message)) > VacationDecisionNotification::MAX_MESSAGE_LENGTH) {
            throw new InvalidArgumentException("'message' must be at most " . VacationDecisionNotification::MAX_MESSAGE_LENGTH . ' characters.');
        }

        return $message;
    }

    /**
     * Tells whoever listens that the request was decided. The decision is already stored, and
     * the dispatcher does not let a failing listener change the answer to the manager.
     */
    private function announce(VacationRequest $decided, VacationDecision $decision, ?string $message): void
    {
        $employee = $decided->getEmployee();
        $id = $decided->getId();
        if ($employee === null || $id === null) {
            return; // a stored request always has both; there is nobody to tell otherwise
        }

        $notification = new VacationDecisionNotification(
            $employee->getId(),
            $employee->getName(),
            $employee->getEmail(),
            $id,
            DateTimeImmutable::createFromMutable($decided->getFrom()),
            DateTimeImmutable::createFromMutable($decided->getTo()),
            $decided->getDays(),
            $decided->getReason(),
            $decision,
            $message,
        );

        $this->events->dispatch(
            $decision === VacationDecision::Approved
                ? new VacationApprovedEvent($notification)
                : new VacationRejectedEvent($notification),
        );
    }
}
