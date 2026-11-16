<?php

declare(strict_types=1);

namespace App\Domains\Vacations\Controllers;

use App\Domains\Authentication\Services\AccessControlService;
use App\Domains\Vacations\Models\VacationRequest;
use App\Domains\Vacations\Models\VacationStatus;
use App\Domains\Vacations\Repositories\VacationsRepository;
use App\Domains\Vacations\Requests\CreateVacationRequest;
use Closure;
use DateMalformedStringException;
use JsonException;
use Laminas\Diactoros\Response\JsonResponse;
use League\Route\Http\Exception\ForbiddenException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

readonly class VacationsController
{
    public function __construct(private VacationsRepository $vacationsRepository, private AccessControlService $accessControl)
    {
    }

    /**
     * @throws DateMalformedStringException
     */
    public function list(ServerRequestInterface $request): ResponseInterface
    {
        // Just checking the isManagerFromRequest also validates that the user is authenticated. If we introduce
        // more roles in the future, we will need to adjust this logic.
        if ($this->accessControl->isManagerFromRequest($request)) {
            $vacationsCollection = $this->vacationsRepository->findPendingRequests();
        } else {
            $authenticatedUser = $this->accessControl->getAuthenticatedUserModelFromRequest($request);
            $vacationsCollection = $this->vacationsRepository->findUserRequests($authenticatedUser->getId());
        }

        return new JsonResponse($vacationsCollection, 200);
    }

    /**
     * @param array{id: int} $params
     * @throws ForbiddenException|DateMalformedStringException
     */
    public function approve(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return $this->decide($request, $params, fn (VacationRequest $vacation, int $managerId) => $vacation->approve($managerId));
    }

    /**
     * @param array{id: int} $params
     * @throws ForbiddenException|DateMalformedStringException
     */
    public function reject(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return $this->decide($request, $params, fn (VacationRequest $vacation, int $managerId) => $vacation->reject($managerId));
    }

    /**
     * @throws JsonException|ForbiddenException|DateMalformedStringException
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $this->requireEmployee($request);
        $authenticatedUser = $this->accessControl->getAuthenticatedUserModelFromRequest($request);

        $input = CreateVacationRequest::fromRequest($request);

        $vacationModel = new VacationRequest(
            null,
            $authenticatedUser->getId(),
            null,
            $input->getFrom(),
            $input->getTo(),
            $input->getReason(),
            VacationStatus::Pending
        );

        $vacationModel = $this->vacationsRepository->create($vacationModel);

        return new JsonResponse($vacationModel, 201);
    }

    /**
     * @param array{id: int} $params
     * @throws ForbiddenException|DateMalformedStringException
     */
    public function delete(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $this->requireEmployee($request);
        $authenticatedUser = $this->accessControl->getAuthenticatedUserModelFromRequest($request);
        $vacationId = (int) $params['id'];

        // Who may cancel, and when, is decided by the vacation request itself.
        $this->vacationsRepository->findById($vacationId)->cancel($authenticatedUser->getId());

        $this->vacationsRepository->delete($vacationId);

        return new JsonResponse('', 204);
    }

    /**
     * @param array{id: int} $params
     * @param Closure(VacationRequest, int): VacationRequest $decision applies the manager's decision to the request
     * @throws ForbiddenException|DateMalformedStringException
     */
    private function decide(ServerRequestInterface $request, array $params, Closure $decision): ResponseInterface
    {
        $this->requireManager($request);
        $managerId = $this->accessControl->getAuthenticatedUserModelFromRequest($request)->getId();
        $vacationId = (int) $params['id'];

        // The vacation request enforces that it is still pending; the conditional UPDATE in the
        // repository is what keeps two managers deciding at the same moment from both winning.
        $decided = $decision($this->vacationsRepository->findById($vacationId), $managerId);

        $saved = $this->vacationsRepository->updateStatus($vacationId, $managerId, $decided->getStatus());

        return new JsonResponse($saved, 200);
    }

    /**
     * @param ServerRequestInterface $request
     * @return void
     * @throws ForbiddenException
     */
    private function requireManager(ServerRequestInterface $request): void
    {
        if (!$this->accessControl->isManagerFromRequest($request)) {
            throw new ForbiddenException();
        }
    }

    /**
     * @param ServerRequestInterface $request
     * @return void
     * @throws ForbiddenException
     */
    private function requireEmployee(ServerRequestInterface $request): void
    {
        if (!$this->accessControl->isEmployeeFromRequest($request)) {
            throw new ForbiddenException();
        }
    }
}
