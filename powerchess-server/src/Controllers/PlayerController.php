<?php
declare(strict_types=1);

namespace PowerChess\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PlayerController
{
    public function createPlayer(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        // Validare minima
        if (empty($body['username'])) {
            $response->getBody()->write(json_encode([
                'code' => 400,
                'message' => 'username este obligatoriu'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Mock response - in productie se salveaza in DB
        $player = [
            'id'          => uniqid('player_'),
            'username'    => $body['username'],
            'email'       => $body['email'] ?? null,
            'gamesPlayed' => 0,
            'wins'        => 0,
            'createdAt'   => date('c'),
        ];

        $response->getBody()->write(json_encode($player));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    public function getPlayer(Request $request, Response $response, array $args): Response
    {
        $playerId = $args['playerId'];

        // Mock response
        $player = [
            'id'          => $playerId,
            'username'    => 'player_demo',
            'email'       => 'demo@powerchess.io',
            'gamesPlayed' => 5,
            'wins'        => 2,
            'createdAt'   => '2024-01-01T00:00:00Z',
        ];

        $response->getBody()->write(json_encode($player));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }
}
