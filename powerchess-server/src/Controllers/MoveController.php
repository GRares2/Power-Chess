<?php
declare(strict_types=1);

namespace PowerChess\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class MoveController
{
    public function getMoves(Request $request, Response $response, array $args): Response
    {
        $gameId = $args['gameId'];

        $moves = [
            [
                'id'              => 'move_001',
                'gameId'          => $gameId,
                'playerId'        => 'player_001',
                'pieceId'         => 'piece_wp1',
                'from'            => ['row' => 2, 'col' => 5, 'notation' => 'e2'],
                'to'              => ['row' => 4, 'col' => 5, 'notation' => 'e4'],
                'capturedPieceId' => null,
                'powerCollected'  => null,
                'powerUsed'       => null,
                'turnNumber'      => 1,
                'timestamp'       => '2024-01-01T10:01:00Z',
            ]
        ];

        $response->getBody()->write(json_encode($moves));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    public function makeMove(Request $request, Response $response, array $args): Response
    {
        $gameId = $args['gameId'];
        $body   = $request->getParsedBody();

        if (empty($body['playerId']) || empty($body['pieceId']) || empty($body['to'])) {
            $response->getBody()->write(json_encode([
                'code'    => 400,
                'message' => 'playerId, pieceId si to sunt obligatorii'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $result = [
            'move' => [
                'id'              => uniqid('move_'),
                'gameId'          => $gameId,
                'playerId'        => $body['playerId'],
                'pieceId'         => $body['pieceId'],
                'from'            => ['row' => 2, 'col' => 5, 'notation' => 'e2'],
                'to'              => $body['to'],
                'capturedPieceId' => null,
                'powerCollected'  => null,
                'powerUsed'       => null,
                'turnNumber'      => 1,
                'timestamp'       => date('c'),
            ],
            'board'           => ['gameId' => $gameId, 'cells' => [], 'activePowers' => []],
            'gameStatus'      => 'active',
            'newPowerSpawned' => null,
        ];

        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    public function getValidMoves(Request $request, Response $response, array $args): Response
    {
        $params  = $request->getQueryParams();
        $pieceId = $params['pieceId'] ?? null;

        if (!$pieceId) {
            $response->getBody()->write(json_encode(['code' => 400, 'message' => 'pieceId este obligatoriu']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Mock valid moves pentru un pion din e2
        $positions = [
            ['row' => 3, 'col' => 5, 'notation' => 'e3'],
            ['row' => 4, 'col' => 5, 'notation' => 'e4'],
        ];

        $response->getBody()->write(json_encode($positions));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }
}
