<?php

declare(strict_types=1);

namespace App\Domains\Vacations\Controllers;

use App\Domains\Authentication\Services\AccessControlService;
use App\Domains\Vacations\Models\VacationRequest;
use App\Domains\Vacations\Requests\CreateVacationRequest;
use App\Domains\Vacations\Models\StatusFilter;
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
use OpenApi\Attributes as OA;
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

    /**
     * Optional query parameter `status`: pending, approved, rejected or all. Without it a manager gets
     * the requests waiting for a decision and an employee gets all of their own.
     *
     * @throws InvalidArgumentException when the status is not one of the known values
     */
    #[OA\Get(
        path: '/vacations',
        operationId: 'listVacations',
        description: 'A manager gets the requests of all employees (with who asked), by default only those waiting for a decision. '
            . 'An employee gets only their own requests, by default of every status. '
            . 'Pending requests are ordered by start date, earliest first; other lists, newest first.',
        summary: 'List vacation requests',
        security: [['BearerAuth' => []]],
        tags: ['Vacation'],
        parameters: [
            new OA\Parameter(
                name: 'status',
                description: 'Narrow the list to one status, or `all`.',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['pending', 'approved', 'rejected', 'all']),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The requests, possibly none.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Vacation')),
            ),
            new OA\Response(ref: '#/components/responses/400', response: 400),
            new OA\Response(ref: '#/components/responses/401', response: 401),
            new OA\Response(ref: '#/components/responses/500', response: 500),
        ],
    )]
    public function list(ServerRequestInterface $request): ResponseInterface
    {
        $vacations = ($this->listVacations)(
            $this->accessControl->principalFromRequest($request),
            $this->statusFilter($request),
        );

        return new JsonResponse($vacations, 200);
    }

    /**
     * The body is optional; it may carry a "message" for the employee.
     *
     * @param array{id: int} $params
     * @throws JsonException
     */
    #[OA\Put(
        path: '/vacations/{id}/approve',
        operationId: 'approveVacation',
        description: 'Manager only. Only a pending request can be approved. The employee is emailed the decision, with the message if one is given.',
        summary: 'Approve a vacation request',
        security: [['BearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(ref: '#/components/schemas/DecisionBody'),
        ),
        tags: ['Vacation'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/VacationId')],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The request as decided.',
                content: new OA\JsonContent(ref: '#/components/schemas/Vacation'),
            ),
            new OA\Response(ref: '#/components/responses/400', response: 400),
            new OA\Response(ref: '#/components/responses/401', response: 401),
            new OA\Response(ref: '#/components/responses/403', response: 403),
            new OA\Response(ref: '#/components/responses/404', response: 404),
            new OA\Response(ref: '#/components/responses/409', response: 409),
            new OA\Response(ref: '#/components/responses/500', response: 500),
        ],
    )]
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
    #[OA\Put(
        path: '/vacations/{id}/reject',
        operationId: 'rejectVacation',
        description: 'Manager only. Only a pending request can be rejected. The employee is emailed the decision, with the message if one is given.',
        summary: 'Reject a vacation request',
        security: [['BearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(ref: '#/components/schemas/DecisionBody'),
        ),
        tags: ['Vacation'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/VacationId')],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The request as decided.',
                content: new OA\JsonContent(ref: '#/components/schemas/Vacation'),
            ),
            new OA\Response(ref: '#/components/responses/400', response: 400),
            new OA\Response(ref: '#/components/responses/401', response: 401),
            new OA\Response(ref: '#/components/responses/403', response: 403),
            new OA\Response(ref: '#/components/responses/404', response: 404),
            new OA\Response(ref: '#/components/responses/409', response: 409),
            new OA\Response(ref: '#/components/responses/500', response: 500),
        ],
    )]
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
    #[OA\Post(
        path: '/vacations',
        operationId: 'createVacation',
        description: 'Employee only. The request starts out pending. It cannot start in the past, span more than 60 days, '
            . 'or overlap another pending or approved request of the same employee (409).',
        summary: 'Request a vacation',
        security: [['BearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/CreateVacationBody'),
        ),
        tags: ['Vacation'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'The new request.',
                content: new OA\JsonContent(ref: '#/components/schemas/Vacation'),
            ),
            new OA\Response(ref: '#/components/responses/400', response: 400),
            new OA\Response(ref: '#/components/responses/401', response: 401),
            new OA\Response(ref: '#/components/responses/403', response: 403),
            new OA\Response(ref: '#/components/responses/409', response: 409),
            new OA\Response(ref: '#/components/responses/500', response: 500),
        ],
    )]
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
    #[OA\Delete(
        path: '/vacations/{id}',
        operationId: 'cancelVacation',
        description: 'Employee only, and only their own request while it is still pending. '
            . 'Someone else\'s request is reported as not found, so its existence is not revealed.',
        summary: 'Cancel a pending vacation request',
        security: [['BearerAuth' => []]],
        tags: ['Vacation'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/VacationId')],
        responses: [
            new OA\Response(response: 204, description: 'The request was cancelled.'),
            new OA\Response(ref: '#/components/responses/401', response: 401),
            new OA\Response(ref: '#/components/responses/403', response: 403),
            new OA\Response(ref: '#/components/responses/404', response: 404),
            new OA\Response(ref: '#/components/responses/409', response: 409),
            new OA\Response(ref: '#/components/responses/500', response: 500),
        ],
    )]
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
    private function statusFilter(ServerRequestInterface $request): ?StatusFilter
    {
        $queryParams = $request->getQueryParams();
        if (!array_key_exists('status', $queryParams)) {
            return null;
        }

        $value = $queryParams['status'];
        $filter = is_string($value) ? StatusFilter::tryFrom($value) : null;
        if ($filter === null) {
            throw new InvalidArgumentException("'status' must be one of: " . StatusFilter::allowedValues() . '.');
        }

        return $filter;
    }

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
