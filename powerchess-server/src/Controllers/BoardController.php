<?php
declare(strict_types=1);

namespace PowerChess\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class BoardController
{
    public function getBoard(Request $request, Response $response, array $args): Response
    {
        $gameId = $args['gameId'];

        // Mock board - tabla initiala simplificata
        $board = [
            'gameId'       => $gameId,
            'cells'        => $this->buildInitialCells(),
            'activePowers' => [],
        ];

        $response->getBody()->write(json_encode($board));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    private function buildInitialCells(): array
    {
        $cells = [];
        for ($row = 1; $row <= 8; $row++) {
            for ($col = 1; $col <= 8; $col++) {
                $cells[] = [
                    'position' => [
                        'row'      => $row,
                        'col'      => $col,
                        'notation' => $this->toNotation($row, $col),
                    ],
                    'piece' => null,
                    'power' => null,
                ];
            }
        }
        return $cells;
    }

    private function toNotation(int $row, int $col): string
    {
        return chr(ord('a') + $col - 1) . $row;
    }
}
