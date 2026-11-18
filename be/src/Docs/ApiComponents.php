<?php

declare(strict_types=1);

namespace App\Docs;

use OpenApi\Attributes as OA;

/**
 * This class holds all reusable components for the API specification.
 */
#[OA\Components(
    responses: [
        new OA\Response(
            response: 400,
            description: 'Bad Request. The input is malformed or breaks a rule; the message says which.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/JsonErrorResponse',
            ),
        ),
        new OA\Response(
            response: 401,
            description: 'Unauthorized. The token is missing, invalid or expired, or the account no longer exists.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/JsonErrorResponse',
            ),
        ),
        new OA\Response(
            response: 403,
            description: 'Forbidden. The caller\'s role does not allow this.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/JsonErrorResponse',
            ),
        ),
        new OA\Response(
            response: 404,
            description: 'Not Found. There is no such resource.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/JsonErrorResponse',
            ),
        ),
        new OA\Response(
            response: 409,
            description: 'Conflict. The request clashes with the current state, for example it was already decided or a value is already taken.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/JsonErrorResponse',
            ),
        ),
        new OA\Response(
            response: 500,
            description: 'Internal Server Error.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/JsonErrorResponse',
            ),
        ),
    ],
    parameters: [
        new OA\Parameter(
            parameter: 'UserId',
            name: 'id',
            description: 'The unique ID of the user.',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'integer', example: 42),
        ),
        new OA\Parameter(
            parameter: 'VacationId',
            name: 'id',
            description: 'The unique ID of the vacation request.',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'integer', example: 7),
        ),
    ],
    schemas: [
        new OA\Schema(
            schema: 'DecisionBody',
            description: 'Optional body of an approval or rejection.',
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'message',
                    description: 'A note for the employee, included in the email about the decision.',
                    type: 'string',
                    maxLength: 500,
                    example: 'Enjoy your trip.',
                ),
            ],
        ),
    ],
    securitySchemes: [
        new OA\SecurityScheme(
            securityScheme: 'BearerAuth',
            type: 'http',
            description: 'JWT based authentication',
            bearerFormat: 'JWT',
            scheme: 'bearer',
        ),
    ],
)]
class ApiComponents
{
    // This class is purely for holding documentation attributes
}
