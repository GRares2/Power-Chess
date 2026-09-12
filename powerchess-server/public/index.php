<?php
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// ══════════════════════════════════════════════════════════════
//  BAZA DE DATE: powerchess.db trebuie sa fie in folderul public/
//  Structura de foldere:
//    powerchess-server/
//      public/
//        index.php       <- asta
//        powerchess.db   <- copiaza fisierul db aici
//        ui.html
//        game.html
//      vendor/
//      composer.json
//
//  Pornire server:
//    php -S localhost:8080 -t public
// ══════════════════════════════════════════════════════════════
define('DB_PATH', __DIR__ . '/powerchess.db');

function db(): PDO {
    static $pdo = null;
    if (!$pdo) {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON;');
        $pdo->exec('PRAGMA journal_mode = WAL;');
    }
    return $pdo;
}

// ── MAPARE coloane DB snake_case → format JSON API camelCase ───
function mapPlayer(array $r): array {
    return [
        'id'          => $r['id'],
        'username'    => $r['username'],
        'email'       => $r['email'],
        'gamesPlayed' => (int)($r['games_played'] ?? 0),
        'wins'        => (int)($r['wins'] ?? 0),
        'createdAt'   => $r['created_at'],
    ];
}

function mapGame(array $r): array {
    return [
        'id'                 => $r['id'],
        'whitePlayerId'      => $r['white_player_id'],
        'blackPlayerId'      => $r['black_player_id'],
        'status'             => $r['status'],
        'currentTurn'        => $r['current_turn'],
        'winner'             => $r['winner'],
        'turnNumber'         => (int)$r['turn_number'],
        'powerSpawnInterval' => (int)$r['power_spawn_interval'],
        'createdAt'          => $r['created_at'],
        'updatedAt'          => $r['updated_at'],
    ];
}

function mapPiece(array $r): array {
    return [
        'id'           => $r['id'],
        'type'         => $r['type'],
        'color'        => $r['color'],
        'position'     => [
            'row'      => (int)$r['position_row'],
            'col'      => (int)$r['position_col'],
            'notation' => $r['position_notation'],
        ],
        'activePowers' => json_decode($r['active_powers'] ?? '[]', true) ?: [],
        'isAlive'      => (bool)$r['is_alive'],
    ];
}

function mapMove(array $r): array {
    return [
        'id'              => $r['id'],
        'gameId'          => $r['game_id'],
        'playerId'        => $r['player_id'],
        'pieceId'         => $r['piece_id'],
        'from'            => ['row' => (int)$r['from_row'], 'col' => (int)$r['from_col'], 'notation' => $r['from_notation']],
        'to'              => ['row' => (int)$r['to_row'],   'col' => (int)$r['to_col'],   'notation' => $r['to_notation']],
        'capturedPieceId' => $r['captured_piece_id'],
        'powerCollected'  => $r['power_collected'],
        'powerUsed'       => $r['power_used'],
        'turnNumber'      => (int)$r['turn_number'],
        'timestamp'       => $r['timestamp'],
    ];
}

function mapPower(array $r): array {
    return [
        'id'                 => $r['id'],
        'type'               => $r['type'],
        'position'           => ($r['position_row'] !== null) ? [
            'row'      => (int)$r['position_row'],
            'col'      => (int)$r['position_col'],
            'notation' => $r['position_notation'],
        ] : null,
        'collectedByPieceId' => $r['collected_by_piece_id'],
        'isUsed'             => (bool)$r['is_used'],
        'spawnedOnTurn'      => (int)$r['spawned_on_turn'],
    ];
}

// ── SLIM ───────────────────────────────────────────────────────
$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addErrorMiddleware(true, true, true);

// CORS
$app->add(function (Request $request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, DELETE, OPTIONS')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
});
$app->options('/{routes:.+}', function ($request, $response) { return $response; });

$app->put('/games/{gameId}/pieces/{pieceId}', function (Request $request, Response $response, array $args) {
    $gStmt = db()->prepare('SELECT id FROM games WHERE id = ?');
    $gStmt->execute([$args['gameId']]);
    if (!$gStmt->fetch()) return not_found($response, 'Partida nu a fost gasita');

    $stmt = db()->prepare('SELECT * FROM pieces WHERE game_id = ? AND id = ?');
    $stmt->execute([$args['gameId'], $args['pieceId']]);
    $piece = $stmt->fetch();
    if (!$piece) return not_found($response, 'Piesa nu a fost gasita');

    $body = $request->getParsedBody();
    $allowed = ['queen','rook','bishop','knight'];
    if (!empty($body['type']) && in_array($body['type'], $allowed)) {
        db()->prepare('UPDATE pieces SET type=? WHERE id=?')
            ->execute([$body['type'], $args['pieceId']]);
    }

    $stmt->execute([$args['gameId'], $args['pieceId']]);
    return json_response($response, mapPiece($stmt->fetch()));
});

function json_response(Response $response, $data, int $status = 200): Response {
    $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
}
function not_found(Response $response, string $msg): Response {
    return json_response($response, ['code' => 404, 'message' => $msg], 404);
}

// ══════════════════════════════════════════════════════════════
//  PLAYERS
// ══════════════════════════════════════════════════════════════

$app->post('/players', function (Request $request, Response $response) {
    $body = $request->getParsedBody();
    if (empty($body['username']))
        return json_response($response, ['code' => 400, 'message' => 'username este obligatoriu'], 400);

    $id  = uniqid('player_');
    $now = date('c');
    db()->prepare('INSERT INTO players (id, username, email, games_played, wins, created_at) VALUES (?,?,?,0,0,?)')
        ->execute([$id, $body['username'], $body['email'] ?? null, $now]);

    $stmt = db()->prepare('SELECT * FROM players WHERE id = ?');
    $stmt->execute([$id]);
    return json_response($response, mapPlayer($stmt->fetch()), 201);
});

$app->get('/players/{playerId}', function (Request $request, Response $response, array $args) {
    $stmt = db()->prepare('SELECT * FROM players WHERE id = ?');
    $stmt->execute([$args['playerId']]);
    $p = $stmt->fetch();
    if (!$p) return not_found($response, 'Jucatorul nu a fost gasit');
    return json_response($response, mapPlayer($p));
});

// ══════════════════════════════════════════════════════════════
//  GAMES
// ══════════════════════════════════════════════════════════════

$app->post('/games', function (Request $request, Response $response) {
    $body = $request->getParsedBody();
    if (empty($body['whitePlayerId']) || empty($body['blackPlayerId']))
        return json_response($response, ['code' => 400, 'message' => 'whitePlayerId si blackPlayerId sunt obligatorii'], 400);

    $id  = uniqid('game_');
    $now = date('c');
    $psi = (int)($body['powerSpawnInterval'] ?? 3);

    db()->prepare("INSERT INTO games (id,white_player_id,black_player_id,status,current_turn,winner,turn_number,power_spawn_interval,created_at,updated_at) VALUES (?,?,?,'active','white',NULL,1,?,?,?)")
        ->execute([$id, $body['whitePlayerId'], $body['blackPlayerId'], $psi, $now, $now]);

    // Piese pozitie initiala completa (32 piese)
    $pieceDefs = [
        ['king','white',1,5,'e1'],  ['queen','white',1,4,'d1'],
        ['rook','white',1,1,'a1'],  ['rook','white',1,8,'h1'],
        ['bishop','white',1,3,'c1'],['bishop','white',1,6,'f1'],
        ['knight','white',1,2,'b1'],['knight','white',1,7,'g1'],
        ['pawn','white',2,1,'a2'],  ['pawn','white',2,2,'b2'],
        ['pawn','white',2,3,'c2'],  ['pawn','white',2,4,'d2'],
        ['pawn','white',2,5,'e2'],  ['pawn','white',2,6,'f2'],
        ['pawn','white',2,7,'g2'],  ['pawn','white',2,8,'h2'],
        ['king','black',8,5,'e8'],  ['queen','black',8,4,'d8'],
        ['rook','black',8,1,'a8'],  ['rook','black',8,8,'h8'],
        ['bishop','black',8,3,'c8'],['bishop','black',8,6,'f8'],
        ['knight','black',8,2,'b8'],['knight','black',8,7,'g8'],
        ['pawn','black',7,1,'a7'],  ['pawn','black',7,2,'b7'],
        ['pawn','black',7,3,'c7'],  ['pawn','black',7,4,'d7'],
        ['pawn','black',7,5,'e7'],  ['pawn','black',7,6,'f7'],
        ['pawn','black',7,7,'g7'],  ['pawn','black',7,8,'h8'],
    ];
    $stmt = db()->prepare("INSERT INTO pieces (id,game_id,type,color,position_row,position_col,position_notation,is_alive,active_powers) VALUES (?,?,?,?,?,?,?,1,'[]')");
    foreach ($pieceDefs as $p) {
        $stmt->execute([uniqid('pc_'), $id, $p[0], $p[1], $p[2], $p[3], $p[4]]);
    }

    $row = db()->prepare('SELECT * FROM games WHERE id = ?');
    $row->execute([$id]);
    return json_response($response, mapGame($row->fetch()), 201);
});

$app->get('/games', function (Request $request, Response $response) {
    $status = $request->getQueryParams()['status'] ?? null;
    if ($status) {
        $stmt = db()->prepare('SELECT * FROM games WHERE status = ? ORDER BY created_at DESC');
        $stmt->execute([$status]);
    } else {
        $stmt = db()->query('SELECT * FROM games ORDER BY created_at DESC');
    }
    return json_response($response, array_map('mapGame', $stmt->fetchAll()));
});

$app->get('/games/{gameId}', function (Request $request, Response $response, array $args) {
    $stmt = db()->prepare('SELECT * FROM games WHERE id = ?');
    $stmt->execute([$args['gameId']]);
    $g = $stmt->fetch();
    if (!$g) return not_found($response, 'Partida nu a fost gasita');
    return json_response($response, mapGame($g));
});

$app->delete('/games/{gameId}', function (Request $request, Response $response, array $args) {
    $stmt = db()->prepare('SELECT id FROM games WHERE id = ?');
    $stmt->execute([$args['gameId']]);
    if (!$stmt->fetch()) return not_found($response, 'Partida nu a fost gasita');
    // Sterge tot ce tine de partida (cascadat manual)
    foreach (['powers','moves','pieces'] as $tbl)
        db()->prepare("DELETE FROM $tbl WHERE game_id = ?")->execute([$args['gameId']]);
    db()->prepare('DELETE FROM games WHERE id = ?')->execute([$args['gameId']]);
    return $response->withStatus(204);
});

// ══════════════════════════════════════════════════════════════
//  BOARD
// ══════════════════════════════════════════════════════════════

$app->get('/games/{gameId}/board', function (Request $request, Response $response, array $args) {
    $gameId = $args['gameId'];
    $gStmt  = db()->prepare('SELECT id FROM games WHERE id = ?');
    $gStmt->execute([$gameId]);
    if (!$gStmt->fetch()) return not_found($response, 'Partida nu a fost gasita');

    $pStmt = db()->prepare('SELECT * FROM pieces WHERE game_id = ? AND is_alive = 1');
    $pStmt->execute([$gameId]);
    $pieces = array_map('mapPiece', $pStmt->fetchAll());

    $pwStmt = db()->prepare('SELECT * FROM powers WHERE game_id = ?');
    $pwStmt->execute([$gameId]);
    $powers = array_map('mapPower', $pwStmt->fetchAll());

    $pieceMap = $powerMap = [];
    foreach ($pieces as $pc)
        $pieceMap[$pc['position']['row'].','.$pc['position']['col']] = $pc;
    foreach ($powers as $pw)
        if ($pw['position'] && !$pw['collectedByPieceId'])
            $powerMap[$pw['position']['row'].','.$pw['position']['col']] = $pw;

    $cells = [];
    for ($row = 1; $row <= 8; $row++)
        for ($col = 1; $col <= 8; $col++)
            $cells[] = [
                'position' => ['row' => $row, 'col' => $col, 'notation' => chr(96+$col).$row],
                'piece'    => $pieceMap["$row,$col"] ?? null,
                'power'    => $powerMap["$row,$col"] ?? null,
            ];

    $activePowers = array_values(array_filter($powers, fn($p) => !$p['isUsed']));
    return json_response($response, ['gameId' => $gameId, 'cells' => $cells, 'activePowers' => $activePowers]);
});

// ══════════════════════════════════════════════════════════════
//  PIECES
// ══════════════════════════════════════════════════════════════

$app->get('/games/{gameId}/pieces', function (Request $request, Response $response, array $args) {
    $gameId = $args['gameId'];
    $gStmt  = db()->prepare('SELECT id FROM games WHERE id = ?');
    $gStmt->execute([$gameId]);
    if (!$gStmt->fetch()) return not_found($response, 'Partida nu a fost gasita');

    $color = $request->getQueryParams()['color'] ?? null;
    if ($color) {
        $stmt = db()->prepare('SELECT * FROM pieces WHERE game_id = ? AND is_alive = 1 AND color = ?');
        $stmt->execute([$gameId, $color]);
    } else {
        $stmt = db()->prepare('SELECT * FROM pieces WHERE game_id = ? AND is_alive = 1');
        $stmt->execute([$gameId]);
    }
    return json_response($response, array_map('mapPiece', $stmt->fetchAll()));
});

$app->get('/games/{gameId}/pieces/{pieceId}', function (Request $request, Response $response, array $args) {
    $gStmt = db()->prepare('SELECT id FROM games WHERE id = ?');
    $gStmt->execute([$args['gameId']]);
    if (!$gStmt->fetch()) return not_found($response, 'Partida nu a fost gasita');

    $stmt = db()->prepare('SELECT * FROM pieces WHERE game_id = ? AND id = ?');
    $stmt->execute([$args['gameId'], $args['pieceId']]);
    $p = $stmt->fetch();
    if (!$p) return not_found($response, 'Piesa nu a fost gasita');
    return json_response($response, mapPiece($p));
});

// ══════════════════════════════════════════════════════════════
//  MOVES
// ══════════════════════════════════════════════════════════════

$app->get('/games/{gameId}/moves', function (Request $request, Response $response, array $args) {
    $gStmt = db()->prepare('SELECT id FROM games WHERE id = ?');
    $gStmt->execute([$args['gameId']]);
    if (!$gStmt->fetch()) return not_found($response, 'Partida nu a fost gasita');

    $stmt = db()->prepare('SELECT * FROM moves WHERE game_id = ? ORDER BY turn_number ASC');
    $stmt->execute([$args['gameId']]);
    return json_response($response, array_map('mapMove', $stmt->fetchAll()));
});

$app->post('/games/{gameId}/moves', function (Request $request, Response $response, array $args) {
    $gameId = $args['gameId'];
    $gStmt  = db()->prepare('SELECT * FROM games WHERE id = ?');
    $gStmt->execute([$gameId]);
    $game = $gStmt->fetch();
    if (!$game) return not_found($response, 'Partida nu a fost gasita');
    if ($game['status'] !== 'active')
        return json_response($response, ['code' => 400, 'message' => 'Partida nu este activa'], 400);

    $body = $request->getParsedBody();
    if (empty($body['playerId']) || empty($body['pieceId']) || empty($body['to']))
        return json_response($response, ['code' => 400, 'message' => 'playerId, pieceId si to sunt obligatorii'], 400);

    // Gaseste piesa
    $pStmt = db()->prepare('SELECT * FROM pieces WHERE game_id = ? AND id = ? AND is_alive = 1');
    $pStmt->execute([$gameId, $body['pieceId']]);
    $piece = $pStmt->fetch();
    if (!$piece) return not_found($response, 'Piesa nu a fost gasita');

    $to   = $body['to'];
    $from = ['row' => (int)$piece['position_row'], 'col' => (int)$piece['position_col'], 'notation' => $piece['position_notation']];
    $now  = date('c');
    $turnNo = (int)$game['turn_number'];

    // Captura
    $capStmt = db()->prepare('SELECT id FROM pieces WHERE game_id=? AND position_row=? AND position_col=? AND is_alive=1 AND color != ?');
    $capStmt->execute([$gameId, $to['row'], $to['col'], $piece['color']]);
    $captured   = $capStmt->fetch();
    $capturedId = $captured ? $captured['id'] : null;

    // Colectare putere
    $pwStmt = db()->prepare('SELECT id FROM powers WHERE game_id=? AND position_row=? AND position_col=? AND collected_by_piece_id IS NULL AND is_used=0');
    $pwStmt->execute([$gameId, $to['row'], $to['col']]);
    $pwRow       = $pwStmt->fetch();
    $pwCollected = $pwRow ? $pwRow['id'] : null;

    // Muta piesa
    db()->prepare('UPDATE pieces SET position_row=?,position_col=?,position_notation=? WHERE id=?')
        ->execute([$to['row'], $to['col'], $to['notation'], $body['pieceId']]);

    // Captura
    if ($capturedId)
        db()->prepare('UPDATE pieces SET is_alive=0 WHERE id=?')->execute([$capturedId]);

    // Colecteaza putere
    if ($pwCollected)
        db()->prepare('UPDATE powers SET collected_by_piece_id=?,position_row=NULL,position_col=NULL,position_notation=NULL WHERE id=?')
            ->execute([$body['pieceId'], $pwCollected]);

    // Salveaza mutarea
    $moveId = uniqid('move_');
    db()->prepare('INSERT INTO moves (id,game_id,player_id,piece_id,from_row,from_col,from_notation,to_row,to_col,to_notation,captured_piece_id,power_collected,power_used,turn_number,timestamp) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NULL,?,?)')
        ->execute([$moveId,$gameId,$body['playerId'],$body['pieceId'],$from['row'],$from['col'],$from['notation'],$to['row'],$to['col'],$to['notation'],$capturedId,$pwCollected,$turnNo,$now]);

    // Urmatorul tur
    $nextTurn  = $game['current_turn'] === 'white' ? 'black' : 'white';
    $newTurnNo = $turnNo + 1;
    db()->prepare('UPDATE games SET current_turn=?,turn_number=?,updated_at=? WHERE id=?')
        ->execute([$nextTurn, $newTurnNo, $now, $gameId]);

    // Spawn automat putere la interval
    $newPower = null;
    $psi      = (int)$game['power_spawn_interval'];
    if ($psi > 0 && ($newTurnNo % $psi === 0)) {
        $types  = ['teleport','extra_move','shield','freeze','double_jump','rule_break'];
        $type   = $types[array_rand($types)];
        $pRow   = rand(1,8); $pCol = rand(1,8);
        $powId  = uniqid('power_');
        db()->prepare('INSERT INTO powers (id,game_id,type,position_row,position_col,position_notation,collected_by_piece_id,is_used,spawned_on_turn) VALUES (?,?,?,?,?,?,NULL,0,?)')
            ->execute([$powId,$gameId,$type,$pRow,$pCol,chr(96+$pCol).$pRow,$newTurnNo]);
        $newPower = ['id'=>$powId,'type'=>$type,'position'=>['row'=>$pRow,'col'=>$pCol,'notation'=>chr(96+$pCol).$pRow],'spawnedOnTurn'=>$newTurnNo];
    }

    // Returneaza board actualizat (activePowers)
    $pwAllStmt = db()->prepare('SELECT * FROM powers WHERE game_id=?');
    $pwAllStmt->execute([$gameId]);
    $allPowers = array_map('mapPower', $pwAllStmt->fetchAll());

    $moveRow = db()->prepare('SELECT * FROM moves WHERE id=?');
    $moveRow->execute([$moveId]);

    return json_response($response, [
        'move'            => mapMove($moveRow->fetch()),
        'board'           => ['gameId'=>$gameId,'cells'=>[],'activePowers'=>array_values(array_filter($allPowers,fn($p)=>!$p['isUsed']))],
        'gameStatus'      => 'active',
        'newPowerSpawned' => $newPower,
    ]);
});

$app->get('/games/{gameId}/moves/valid', function (Request $request, Response $response, array $args) {
    $gameId  = $args['gameId'];
    $gStmt   = db()->prepare('SELECT id FROM games WHERE id = ?');
    $gStmt->execute([$gameId]);
    if (!$gStmt->fetch()) return not_found($response, 'Partida nu a fost gasita');

    $pieceId = $request->getQueryParams()['pieceId'] ?? null;
    if (!$pieceId) return json_response($response, ['code'=>400,'message'=>'pieceId este obligatoriu'], 400);

    $stmt = db()->prepare('SELECT * FROM pieces WHERE game_id=? AND id=? AND is_alive=1');
    $stmt->execute([$gameId, $pieceId]);
    $piece = $stmt->fetch();
    if (!$piece) return not_found($response, 'Piesa nu a fost gasita');

    // Pozitii ocupate de piesele proprii
    $ownStmt = db()->prepare('SELECT position_row,position_col FROM pieces WHERE game_id=? AND color=? AND is_alive=1');
    $ownStmt->execute([$gameId, $piece['color']]);
    $ownPos = [];
    foreach ($ownStmt->fetchAll() as $op) $ownPos[$op['position_row'].','.$op['position_col']] = true;

    $moves = [];
    $row   = (int)$piece['position_row'];
    $col   = (int)$piece['position_col'];

    $add = function(int $r, int $c) use (&$moves, $ownPos) {
        if ($r >= 1 && $r <= 8 && $c >= 1 && $c <= 8 && !isset($ownPos["$r,$c"]))
            $moves[] = ['row'=>$r,'col'=>$c,'notation'=>chr(96+$c).$r];
    };

    // Alunecare (rook/bishop/queen)
    $slide = function(int $dr, int $dc) use ($row, $col, $gameId, $piece, &$moves, $ownPos) {
        $r = $row+$dr; $c = $col+$dc;
        $blk = db()->prepare('SELECT color FROM pieces WHERE game_id=? AND position_row=? AND position_col=? AND is_alive=1');
        while ($r>=1&&$r<=8&&$c>=1&&$c<=8) {
            if (isset($ownPos["$r,$c"])) break;
            $moves[] = ['row'=>$r,'col'=>$c,'notation'=>chr(96+$c).$r];
            $blk->execute([$gameId,$r,$c]);
            if ($blk->fetch()) break;
            $r+=$dr; $c+=$dc;
        }
    };

    switch ($piece['type']) {
        case 'pawn':
            $dir  = $piece['color']==='white' ? 1 : -1;
            $fwd  = db()->prepare('SELECT id FROM pieces WHERE game_id=? AND position_row=? AND position_col=? AND is_alive=1');
            $fwd->execute([$gameId,$row+$dir,$col]);
            if ($row+$dir>=1&&$row+$dir<=8&&!$fwd->fetch()) {
                $add($row+$dir,$col);
                if (($piece['color']==='white'&&$row===2)||($piece['color']==='black'&&$row===7)) {
                    $fwd2 = db()->prepare('SELECT id FROM pieces WHERE game_id=? AND position_row=? AND position_col=? AND is_alive=1');
                    $fwd2->execute([$gameId,$row+2*$dir,$col]);
                    if (!$fwd2->fetch()) $add($row+2*$dir,$col);
                }
            }
            foreach ([-1,1] as $dc) {
                $cap = db()->prepare('SELECT color FROM pieces WHERE game_id=? AND position_row=? AND position_col=? AND is_alive=1');
                $cap->execute([$gameId,$row+$dir,$col+$dc]);
                $c = $cap->fetch();
                if ($c&&$c['color']!==$piece['color']) $add($row+$dir,$col+$dc);
            }
            break;
        case 'king':
            foreach ([[-1,-1],[-1,0],[-1,1],[0,-1],[0,1],[1,-1],[1,0],[1,1]] as [$dr,$dc]) $add($row+$dr,$col+$dc);
            break;
        case 'knight':
            foreach ([[-2,-1],[-2,1],[-1,-2],[-1,2],[1,-2],[1,2],[2,-1],[2,1]] as [$dr,$dc]) $add($row+$dr,$col+$dc);
            break;
        case 'rook':
            foreach ([[1,0],[-1,0],[0,1],[0,-1]] as [$dr,$dc]) $slide($dr,$dc);
            break;
        case 'bishop':
            foreach ([[1,1],[1,-1],[-1,1],[-1,-1]] as [$dr,$dc]) $slide($dr,$dc);
            break;
        case 'queen':
            foreach ([[1,0],[-1,0],[0,1],[0,-1],[1,1],[1,-1],[-1,1],[-1,-1]] as [$dr,$dc]) $slide($dr,$dc);
            break;
    }
    return json_response($response, $moves);
});

// ══════════════════════════════════════════════════════════════
//  POWERS
// ══════════════════════════════════════════════════════════════

$app->get('/games/{gameId}/powers', function (Request $request, Response $response, array $args) {
    $gStmt = db()->prepare('SELECT id FROM games WHERE id = ?');
    $gStmt->execute([$args['gameId']]);
    if (!$gStmt->fetch()) return not_found($response, 'Partida nu a fost gasita');

    $stmt = db()->prepare('SELECT * FROM powers WHERE game_id = ?');
    $stmt->execute([$args['gameId']]);
    return json_response($response, array_map('mapPower', $stmt->fetchAll()));
});

$app->post('/games/{gameId}/powers/spawn', function (Request $request, Response $response, array $args) {
    $gameId = $args['gameId'];
    $gStmt  = db()->prepare('SELECT turn_number FROM games WHERE id = ?');
    $gStmt->execute([$gameId]);
    $game = $gStmt->fetch();
    if (!$game) return not_found($response, 'Partida nu a fost gasita');

    $types = ['teleport','extra_move','shield','freeze','double_jump','rule_break'];
    $type  = $types[array_rand($types)];
    $row   = rand(1,8); $col = rand(1,8);
    $id    = uniqid('power_');
    db()->prepare('INSERT INTO powers (id,game_id,type,position_row,position_col,position_notation,collected_by_piece_id,is_used,spawned_on_turn) VALUES (?,?,?,?,?,?,NULL,0,?)')
        ->execute([$id,$gameId,$type,$row,$col,chr(96+$col).$row,(int)$game['turn_number']]);

    $stmt = db()->prepare('SELECT * FROM powers WHERE id = ?');
    $stmt->execute([$id]);
    return json_response($response, mapPower($stmt->fetch()), 201);
});

$app->post('/games/{gameId}/powers/{powerId}/collect', function (Request $request, Response $response, array $args) {
    $gStmt = db()->prepare('SELECT id FROM games WHERE id = ?');
    $gStmt->execute([$args['gameId']]);
    if (!$gStmt->fetch()) return not_found($response, 'Partida nu a fost gasita');

    $body = $request->getParsedBody();
    if (empty($body['pieceId'])) return json_response($response, ['code'=>400,'message'=>'pieceId este obligatoriu'], 400);

    $stmt = db()->prepare('SELECT * FROM powers WHERE id=? AND game_id=?');
    $stmt->execute([$args['powerId'],$args['gameId']]);
    if (!$stmt->fetch()) return not_found($response, 'Puterea nu a fost gasita');

    db()->prepare('UPDATE powers SET collected_by_piece_id=?,position_row=NULL,position_col=NULL,position_notation=NULL WHERE id=?')
        ->execute([$body['pieceId'],$args['powerId']]);

    $stmt->execute([$args['powerId'],$args['gameId']]);
    return json_response($response, mapPower($stmt->fetch()));
});

$app->post('/games/{gameId}/powers/{powerId}/use', function (Request $request, Response $response, array $args) {
    $gStmt = db()->prepare('SELECT id FROM games WHERE id = ?');
    $gStmt->execute([$args['gameId']]);
    if (!$gStmt->fetch()) return not_found($response, 'Partida nu a fost gasita');

    $body = $request->getParsedBody();
    if (empty($body['pieceId'])) return json_response($response, ['code'=>400,'message'=>'pieceId este obligatoriu'], 400);

    $stmt = db()->prepare('SELECT * FROM powers WHERE id=? AND game_id=?');
    $stmt->execute([$args['powerId'],$args['gameId']]);
    $power = $stmt->fetch();
    if (!$power) return not_found($response, 'Puterea nu a fost gasita');

    db()->prepare('UPDATE powers SET is_used=1 WHERE id=?')->execute([$args['powerId']]);

    return json_response($response, [
        'powerId'         => $power['id'],
        'type'            => $power['type'],
        'description'     => 'Puterea a fost aplicata cu succes.',
        'affectedPieceId' => $body['pieceId'],
        'board'           => ['gameId'=>$args['gameId'],'cells'=>[],'activePowers'=>[]],
    ]);
});

$app->run();
