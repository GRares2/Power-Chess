<?php
declare(strict_types=1);

namespace PowerChess\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class GameController
{
    public function createGame(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        if (empty($body['whitePlayerId']) || empty($body['blackPlayerId'])) {
            $response->getBody()->write(json_encode([
                'code' => 400,
                'message' => 'whitePlayerId si blackPlayerId sunt obligatorii'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $game = [
            'id'                 => uniqid('game_'),
            'whitePlayerId'      => $body['whitePlayerId'],
            'blackPlayerId'      => $body['blackPlayerId'],
            'status'             => 'waiting',
            'currentTurn'        => 'white',
            'winner'             => null,
            'turnNumber'         => 0,
            'powerSpawnInterval' => $body['powerSpawnInterval'] ?? 3,
            'createdAt'          => date('c'),
            'updatedAt'          => date('c'),
        ];

        $response->getBody()->write(json_encode($game));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    public function listGames(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $status = $params['status'] ?? null;

        // Mock - lista goala
        $games = [];

        $response->getBody()->write(json_encode($games));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    public function getGame(Request $request, Response $response, array $args): Response
    {
        $gameId = $args['gameId'];

        $game = [
            'id'                 => $gameId,
            'whitePlayerId'      => 'player_001',
            'blackPlayerId'      => 'player_002',
            'status'             => 'active',
            'currentTurn'        => 'white',
            'winner'             => null,
            'turnNumber'         => 3,
            'powerSpawnInterval' => 3,
            'createdAt'          => '2024-01-01T10:00:00Z',
            'updatedAt'          => '2024-01-01T10:05:00Z',
        ];

        $response->getBody()->write(json_encode($game));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    public function deleteGame(Request $request, Response $response, array $args): Response
    {
        // 204 No Content - fara body
        return $response->withStatus(204);
    }
}
