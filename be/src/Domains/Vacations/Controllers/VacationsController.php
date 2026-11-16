<?php

declare(strict_types=1);

namespace App\Domains\Vacations\Controllers;

use App\Domains\Authentication\Services\AccessControlService;
use App\Domains\Vacations\Requests\CreateVacationRequest;
use App\Domains\Vacations\Services\CancelVacation;
use App\Domains\Vacations\Services\DecideVacation;
use App\Domains\Vacations\Services\ListVacations;
use App\Domains\Vacations\Services\RequestVacation;
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
    ) {
    }

    public function list(ServerRequestInterface $request): ResponseInterface
    {
        $vacations = ($this->listVacations)($this->accessControl->principalFromRequest($request));

        return new JsonResponse($vacations, 200);
    }

    /**
     * @param array{id: int} $params
     */
    public function approve(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $decided = $this->decideVacation->approve($this->accessControl->principalFromRequest($request), (int) $params['id']);

        return new JsonResponse($decided, 200);
    }

    /**
     * @param array{id: int} $params
     */
    public function reject(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $decided = $this->decideVacation->reject($this->accessControl->principalFromRequest($request), (int) $params['id']);

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
}
