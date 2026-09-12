<?php
declare(strict_types=1);

namespace PowerChess\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PowerController
{
    private array $powerTypes = ['teleport', 'extra_move', 'shield', 'freeze', 'double_jump', 'rule_break'];

    public function listPowers(Request $request, Response $response, array $args): Response
    {
        $gameId = $args['gameId'];

        $powers = [
            [
                'id'                 => 'power_001',
                'type'               => 'shield',
                'position'           => ['row' => 4, 'col' => 3, 'notation' => 'c4'],
                'collectedByPieceId' => null,
                'isUsed'             => false,
                'spawnedOnTurn'      => 3,
            ]
        ];

        $response->getBody()->write(json_encode($powers));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    public function spawnPower(Request $request, Response $response, array $args): Response
    {
        $gameId = $args['gameId'];

        $power = [
            'id'                 => uniqid('power_'),
            'type'               => $this->powerTypes[array_rand($this->powerTypes)],
            'position'           => [
                'row'      => rand(1, 8),
                'col'      => rand(1, 8),
                'notation' => chr(ord('a') + rand(0, 7)) . rand(1, 8),
            ],
            'collectedByPieceId' => null,
            'isUsed'             => false,
            'spawnedOnTurn'      => rand(1, 10),
        ];

        $response->getBody()->write(json_encode($power));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    public function collectPower(Request $request, Response $response, array $args): Response
    {
        $powerId = $args['powerId'];
        $body    = $request->getParsedBody();

        if (empty($body['pieceId'])) {
            $response->getBody()->write(json_encode(['code' => 400, 'message' => 'pieceId este obligatoriu']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $power = [
            'id'                 => $powerId,
            'type'               => 'shield',
            'position'           => null,
            'collectedByPieceId' => $body['pieceId'],
            'isUsed'             => false,
            'spawnedOnTurn'      => 3,
        ];

        $response->getBody()->write(json_encode($power));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    public function usePower(Request $request, Response $response, array $args): Response
    {
        $powerId = $args['powerId'];
        $body    = $request->getParsedBody();

        if (empty($body['pieceId'])) {
            $response->getBody()->write(json_encode(['code' => 400, 'message' => 'pieceId este obligatoriu']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $effect = [
            'powerId'         => $powerId,
            'type'            => 'shield',
            'description'     => 'Piesa a fost protejata de urmatoarea captura.',
            'affectedPieceId' => $body['pieceId'],
            'board'           => ['gameId' => $args['gameId'], 'cells' => [], 'activePowers' => []],
        ];

        $response->getBody()->write(json_encode($effect));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }
}
