<?php
declare(strict_types=1);

namespace PowerChess\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PieceController
{
    public function listPieces(Request $request, Response $response, array $args): Response
    {
        $gameId = $args['gameId'];
        $params = $request->getQueryParams();
        $color  = $params['color'] ?? null;

        $pieces = [
            [
                'id'           => 'piece_wk',
                'type'         => 'king',
                'color'        => 'white',
                'position'     => ['row' => 1, 'col' => 5, 'notation' => 'e1'],
                'activePowers' => [],
                'isAlive'      => true,
            ],
            [
                'id'           => 'piece_bk',
                'type'         => 'king',
                'color'        => 'black',
                'position'     => ['row' => 8, 'col' => 5, 'notation' => 'e8'],
                'activePowers' => [],
                'isAlive'      => true,
            ],
        ];

        if ($color) {
            $pieces = array_values(array_filter($pieces, fn($p) => $p['color'] === $color));
        }

        $response->getBody()->write(json_encode($pieces));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    public function getPiece(Request $request, Response $response, array $args): Response
    {
        $pieceId = $args['pieceId'];

        $piece = [
            'id'           => $pieceId,
            'type'         => 'pawn',
            'color'        => 'white',
            'position'     => ['row' => 2, 'col' => 1, 'notation' => 'a2'],
            'activePowers' => [],
            'isAlive'      => true,
        ];

        $response->getBody()->write(json_encode($piece));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }
}
