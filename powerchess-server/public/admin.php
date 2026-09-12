<?php

define('DB_PATH', __DIR__ . '/powerchess.db');

function db(): PDO {
    static $pdo = null;
    if (!$pdo) {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
    return $pdo;
}

$players = db()->query('SELECT * FROM players ORDER BY created_at DESC')->fetchAll();
$games   = db()->query('SELECT * FROM games ORDER BY created_at DESC')->fetchAll();
$powers  = db()->query('SELECT * FROM powers ORDER BY spawned_on_turn DESC LIMIT 50')->fetchAll();
$moves   = db()->query('SELECT * FROM moves ORDER BY timestamp DESC LIMIT 50')->fetchAll();

$totalPlayers = count($players);
$totalGames   = count($games);
$activeGames  = count(array_filter($games, fn($g) => $g['status'] === 'active'));
$totalMoves   = db()->query('SELECT COUNT(*) as c FROM moves')->fetch()['c'];
?>
<!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PowerChess — Admin</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700;900&family=Crimson+Text:ital,wght@0,400;0,600;1,400&display=swap');

  :root {
    --gold: #c9a84c;
    --gold-light: #e8c97a;
    --dark: #0a0a0f;
    --dark-2: #12121a;
    --dark-3: #1a1a28;
    --dark-4: #22223a;
    --border: #2a2a45;
    --text: #e8e0d0;
    --text-dim: #8a8070;
    --success: #27ae60;
    --error: #c0392b;
    --warning: #e67e22;
  }

  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Crimson Text', Georgia, serif;
    background: var(--dark);
    color: var(--text);
    min-height: 100vh;
  }

  /* SIDEBAR */
  .sidebar {
    position: fixed;
    left: 0; top: 0; bottom: 0;
    width: 220px;
    background: var(--dark-2);
    border-right: 1px solid var(--border);
    padding: 32px 0;
    z-index: 10;
  }
  .sidebar-logo {
    text-align: center;
    padding: 0 20px 32px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 24px;
  }
  .sidebar-logo .icon { font-size: 32px; display: block; }
  .sidebar-logo h2 {
    font-family: 'Cinzel', serif;
    font-size: 14px;
    letter-spacing: 3px;
    background: linear-gradient(135deg, var(--gold), var(--gold-light));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-top: 6px;
  }
  .sidebar-logo p { font-size: 11px; color: var(--text-dim); letter-spacing: 1px; margin-top: 2px; }

  .nav-item {
    display: block;
    padding: 12px 24px;
    color: var(--text-dim);
    text-decoration: none;
    font-size: 13px;
    letter-spacing: 1px;
    text-transform: uppercase;
    transition: all 0.2s;
    border-left: 3px solid transparent;
  }
  .nav-item:hover, .nav-item.active {
    color: var(--gold);
    border-left-color: var(--gold);
    background: rgba(201,168,76,0.05);
  }
  .nav-item .nav-icon { margin-right: 10px; }

  /* MAIN */
  .main {
    margin-left: 220px;
    padding: 32px;
  }

  .page-header {
    margin-bottom: 32px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .page-header h1 {
    font-family: 'Cinzel', serif;
    font-size: 22px;
    color: var(--gold);
    letter-spacing: 3px;
  }
  .page-header .timestamp { font-size: 12px; color: var(--text-dim); }

  /* STATS GRID */
  .stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 32px;
  }
  .stat-card {
    background: var(--dark-2);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 20px 24px;
    position: relative;
    overflow: hidden;
  }
  .stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 2px;
    background: linear-gradient(90deg, var(--gold), transparent);
  }
  .stat-card .stat-icon { font-size: 28px; margin-bottom: 8px; display: block; }
  .stat-card .stat-value {
    font-family: 'Cinzel', serif;
    font-size: 32px;
    color: var(--gold);
    display: block;
  }
  .stat-card .stat-label { font-size: 11px; color: var(--text-dim); letter-spacing: 2px; text-transform: uppercase; margin-top: 4px; }

  /* SECTION */
  .section { margin-bottom: 40px; }
  .section-title {
    font-family: 'Cinzel', serif;
    font-size: 14px;
    color: var(--gold);
    letter-spacing: 3px;
    text-transform: uppercase;
    margin-bottom: 16px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--border);
  }

  /* TABLE */
  .table-wrap { overflow-x: auto; border-radius: 10px; border: 1px solid var(--border); }
  table { width: 100%; border-collapse: collapse; }
  thead tr { background: var(--dark-3); }
  thead th {
    padding: 12px 16px;
    text-align: left;
    font-family: 'Cinzel', serif;
    font-size: 10px;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: var(--text-dim);
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
  }
  tbody tr { border-bottom: 1px solid rgba(42,42,69,0.5); transition: background 0.15s; }
  tbody tr:last-child { border-bottom: none; }
  tbody tr:hover { background: rgba(201,168,76,0.03); }
  tbody td {
    padding: 11px 16px;
    font-size: 14px;
    color: var(--text);
    vertical-align: middle;
  }

  /* BADGES */
  .badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    letter-spacing: 1px;
    text-transform: uppercase;
    font-family: 'Cinzel', serif;
  }
  .badge-active  { background: rgba(39,174,96,0.15);  color: #2ecc71; border: 1px solid rgba(39,174,96,0.3); }
  .badge-finished{ background: rgba(201,168,76,0.15); color: var(--gold); border: 1px solid rgba(201,168,76,0.3); }
  .badge-waiting { background: rgba(52,152,219,0.15); color: #3498db; border: 1px solid rgba(52,152,219,0.3); }
  .badge-white   { background: rgba(240,236,224,0.1); color: #f0ece0; border: 1px solid rgba(240,236,224,0.2); }
  .badge-black   { background: rgba(30,30,60,0.5);    color: #9090c0; border: 1px solid rgba(107,107,154,0.3); }
  .badge-used    { background: rgba(192,57,43,0.15);  color: #e74c3c; border: 1px solid rgba(192,57,43,0.3); }
  .badge-free    { background: rgba(39,174,96,0.15);  color: #2ecc71; border: 1px solid rgba(39,174,96,0.3); }

  .id-cell { font-family: monospace; font-size: 12px; color: var(--text-dim); }
  .empty-state { text-align: center; padding: 40px; color: var(--text-dim); font-size: 14px; }
</style>
</head>
<body>

<div class="sidebar">
  <div class="sidebar-logo">
    <span class="icon">♟</span>
    <h2>PowerChess</h2>
    <p>Admin Panel</p>
  </div>
  <a href="#stats"   class="nav-item active"><span class="nav-icon">📊</span>Dashboard</a>
  <a href="#players" class="nav-item"><span class="nav-icon">👤</span>Jucători</a>
  <a href="#games"   class="nav-item"><span class="nav-icon">♟</span>Partide</a>
  <a href="#powers"  class="nav-item"><span class="nav-icon">⚡</span>Puteri</a>
  <a href="#moves"   class="nav-item"><span class="nav-icon">🎯</span>Mutări</a>
  <a href="menu.html" class="nav-item" style="margin-top:auto;position:absolute;bottom:20px;left:0;right:0;"><span class="nav-icon">🏠</span>Înapoi la joc</a>
</div>

<div class="main">

  <div class="page-header">
    <h1>Dashboard Admin</h1>
    <span class="timestamp">Actualizat: <?= date('d.m.Y H:i:s') ?></span>
  </div>

  <!-- STATS -->
  <div class="stats-grid" id="stats">
    <div class="stat-card">
      <span class="stat-icon">👤</span>
      <span class="stat-value"><?= $totalPlayers ?></span>
      <div class="stat-label">Jucători înregistrați</div>
    </div>
    <div class="stat-card">
      <span class="stat-icon">♟</span>
      <span class="stat-value"><?= $totalGames ?></span>
      <div class="stat-label">Total partide</div>
    </div>
    <div class="stat-card">
      <span class="stat-icon">🟢</span>
      <span class="stat-value"><?= $activeGames ?></span>
      <div class="stat-label">Partide active</div>
    </div>
    <div class="stat-card">
      <span class="stat-icon">🎯</span>
      <span class="stat-value"><?= $totalMoves ?></span>
      <div class="stat-label">Total mutări</div>
    </div>
  </div>

  <!-- PLAYERS -->
  <div class="section" id="players">
    <div class="section-title">Jucători (<?= $totalPlayers ?>)</div>
    <div class="table-wrap">
      <?php if (empty($players)): ?>
        <div class="empty-state">Nu există jucători înregistrați</div>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Email</th>
            <th>Partide jucate</th>
            <th>Victorii</th>
            <th>Creat la</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($players as $p): ?>
          <tr>
            <td class="id-cell"><?= htmlspecialchars($p['id']) ?></td>
            <td><strong><?= htmlspecialchars($p['username']) ?></strong></td>
            <td><?= htmlspecialchars($p['email'] ?? '—') ?></td>
            <td><?= (int)($p['games_played'] ?? 0) ?></td>
            <td><?= (int)($p['wins'] ?? 0) ?></td>
            <td><?= htmlspecialchars($p['created_at'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- GAMES -->
  <div class="section" id="games">
    <div class="section-title">Partide (<?= $totalGames ?>)</div>
    <div class="table-wrap">
      <?php if (empty($games)): ?>
        <div class="empty-state">Nu există partide</div>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Jucător Alb</th>
            <th>Jucător Negru</th>
            <th>Status</th>
            <th>Tura curentă</th>
            <th>Nr. ture</th>
            <th>Câștigător</th>
            <th>Creat la</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($games as $g): ?>
          <tr>
            <td class="id-cell"><?= htmlspecialchars($g['id']) ?></td>
            <td><?= htmlspecialchars($g['white_player_id']) ?></td>
            <td><?= htmlspecialchars($g['black_player_id']) ?></td>
            <td>
              <span class="badge badge-<?= $g['status'] ?>">
                <?= htmlspecialchars($g['status']) ?>
              </span>
            </td>
            <td>
              <span class="badge badge-<?= $g['current_turn'] ?>">
                <?= $g['current_turn'] === 'white' ? 'Alb' : 'Negru' ?>
              </span>
            </td>
            <td><?= (int)$g['turn_number'] ?></td>
            <td><?= $g['winner'] ? htmlspecialchars($g['winner']) : '—' ?></td>
            <td><?= htmlspecialchars($g['created_at'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- POWERS -->
  <div class="section" id="powers">
    <div class="section-title">Puteri recente (ultimele 50)</div>
    <div class="table-wrap">
      <?php if (empty($powers)): ?>
        <div class="empty-state">Nu există puteri generate</div>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Tip</th>
            <th>Partida</th>
            <th>Poziție</th>
            <th>Colectată de</th>
            <th>Status</th>
            <th>Tura spawn</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($powers as $pw): ?>
          <tr>
            <td class="id-cell"><?= htmlspecialchars($pw['id']) ?></td>
            <td><strong><?= htmlspecialchars($pw['type']) ?></strong></td>
            <td class="id-cell"><?= htmlspecialchars($pw['game_id']) ?></td>
            <td><?= $pw['position_notation'] ? htmlspecialchars($pw['position_notation']) : '—' ?></td>
            <td class="id-cell"><?= $pw['collected_by_piece_id'] ? htmlspecialchars($pw['collected_by_piece_id']) : '—' ?></td>
            <td>
              <span class="badge <?= $pw['is_used'] ? 'badge-used' : 'badge-free' ?>">
                <?= $pw['is_used'] ? 'Folosită' : 'Activă' ?>
              </span>
            </td>
            <td><?= (int)$pw['spawned_on_turn'] ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- MOVES -->
  <div class="section" id="moves">
    <div class="section-title">Mutări recente (ultimele 50)</div>
    <div class="table-wrap">
      <?php if (empty($moves)): ?>
        <div class="empty-state">Nu există mutări înregistrate</div>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Partida</th>
            <th>Jucător</th>
            <th>Piesă</th>
            <th>De la</th>
            <th>La</th>
            <th>Capturat</th>
            <th>Tura</th>
            <th>Timestamp</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($moves as $m): ?>
          <tr>
            <td class="id-cell"><?= htmlspecialchars($m['id']) ?></td>
            <td class="id-cell"><?= htmlspecialchars($m['game_id']) ?></td>
            <td class="id-cell"><?= htmlspecialchars($m['player_id']) ?></td>
            <td class="id-cell"><?= htmlspecialchars($m['piece_id']) ?></td>
            <td><?= htmlspecialchars($m['from_notation'] ?? '—') ?></td>
            <td><?= htmlspecialchars($m['to_notation'] ?? '—') ?></td>
            <td class="id-cell"><?= $m['captured_piece_id'] ? htmlspecialchars($m['captured_piece_id']) : '—' ?></td>
            <td><?= (int)$m['turn_number'] ?></td>
            <td><?= htmlspecialchars($m['timestamp'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

</div>
</body>
</html>
