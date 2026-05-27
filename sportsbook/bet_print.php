<?php
/**
 * Bet Receipt Printer — alpina216 branded
 *
 * Modes (via ?mode=):
 *   - 'copy'        (default): full COPY receipt with watermark (image 5 reference)
 *   - 'combinations': minimalist combination-only print layout (image 6 reference)
 *
 * Required: ?id={bet_id} — must belong to the logged-in user.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo 'Non autorisé. Veuillez vous connecter.'; exit;
}

$bet_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$mode   = isset($_GET['mode']) ? trim($_GET['mode']) : 'copy';
if ($bet_id <= 0) { http_response_code(400); echo 'Identifiant de pari manquant.'; exit; }

// DB connection
$pdo = null;
$db_paths = [
    dirname(__DIR__, 2) . '/forza/includes/db.php',
    __DIR__ . '/../includes/db.php',
    dirname(__DIR__)    . '/includes/db.php',
];
foreach ($db_paths as $p) {
    if (is_file($p)) { require_once $p; if (isset($pdo) && $pdo instanceof PDO) { break; } }
}
if (!$pdo) { http_response_code(500); echo 'Connexion DB indisponible.'; exit; }

try {
    $st = $pdo->prepare("SELECT b.id, b.amount, b.total_odds, b.potential_returns,
                                b.slip, b.status, b.created_at, b.settled_at,
                                u.username
                         FROM sportsbook_bets b
                         LEFT JOIN users u ON u.id = b.user_id
                         WHERE b.id = ? AND b.user_id = ?
                         LIMIT 1");
    $st->execute([$bet_id, (int)$_SESSION['user_id']]);
    $bet = $st->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) { $bet = null; }
if (!$bet) { http_response_code(404); echo 'Pari introuvable.'; exit; }

$slip       = json_decode($bet['slip'], true);
if (!is_array($slip)) $slip = [];
$created_ts = strtotime($bet['created_at']);
$date_short = date('d/m', $created_ts) . ' • ' . date('H:i', $created_ts);
$is_simple  = count($slip) <= 1;
$type_label = $is_simple ? 'SIMPLE' : 'COMBINÉ';
$terminal   = '90' . str_pad((string)(($bet['id'] * 73) % 1000000), 6, '0', STR_PAD_LEFT);
$username   = $bet['username'] ?? 'Client';

/**
 * Render one leg block (match header + selections).
 * The slip schema (from app.js):
 *   - match    : combined "Home vs Away"
 *   - sel      : selection text (e.g. "1", "Plus de 2.5")
 *   - market   : market label (e.g. "1x2", "Total")
 *   - val      : odds (already-boosted price for boost legs)
 *   - isBB     : true for BetBuilder (Same Game Multi)
 *   - legs[]   : { id, name, odds, market, handicap }
 *   - isBoost  : true for "Cotes Boostées"
 *   - boostReal: original (pre-boost) price for boost legs
 *   - league   : optional league name
 *   - time     : optional kickoff ts
 */
function render_leg_block(array $leg): string {
    $match_str = trim((string)($leg['match'] ?? ''));
    $league    = trim((string)($leg['league'] ?? ''));
    $kick_ts   = !empty($leg['time']) ? (int)$leg['time'] : 0;
    $kick      = $kick_ts > 0 ? date('d/m', $kick_ts) . ' • ' . date('H:i', $kick_ts) : '';

    // Bet Builder (Same Game Multi) ticket has legs[]
    if (!empty($leg['isBB']) && !empty($leg['legs']) && is_array($leg['legs'])) {
        $parts = [];
        foreach ($leg['legs'] as $L) {
            $mk = trim((string)($L['market'] ?? ''));
            $pk = trim((string)($L['name']   ?? $L['pick'] ?? ''));
            if ($mk !== '' || $pk !== '') {
                $parts[] = ($mk !== '' ? $mk . ': ' : '') . $pk;
            }
        }
        $sel_html = '<b>BetBuilder:</b> ' . htmlspecialchars(implode(' | ', $parts));
    } else {
        $mk = trim((string)($leg['market'] ?? ''));
        $pk = trim((string)($leg['sel']    ?? $leg['pick'] ?? ''));
        $sel_html = ($mk !== '' ? '<b>' . htmlspecialchars($mk) . ':</b> ' : '') . htmlspecialchars($pk);
    }

    // Boosted line "5.33 >> 5.50"
    $price_extra = '';
    if (!empty($leg['isBoost']) && !empty($leg['boostReal']) && !empty($leg['val'])) {
        $price_extra = '<div class="boost-line"><b>Cotes:</b> <s>'
            . number_format((float)$leg['boostReal'], 2)
            . '</s> &gt;&gt; ' . number_format((float)$leg['val'], 2)
            . '<br>Cotes Boostées</div>';
    }

    $hdr = $league;
    if ($kick !== '') $hdr .= ($hdr !== '' ? ' ' . $kick : $kick);

    return '<div class="leg">'
         . ($hdr !== '' ? '<div class="leg-hdr">' . htmlspecialchars($hdr) . '</div>' : '')
         . ($match_str !== '' ? '<div class="leg-teams">' . htmlspecialchars($match_str) . '</div>' : '')
         . '<div class="leg-sel">' . $sel_html . '</div>'
         . $price_extra
         . '</div>';
}
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>alpina216.com — Ticket <?= htmlspecialchars((string)$bet['id']) ?></title>
<style>
  * { box-sizing: border-box; }
  body{
    margin:0; padding:24px;
    font-family: Arial, Helvetica, sans-serif;
    color:#000; background:#fff; font-size:13px; line-height:1.35;
  }
  .head{ display:flex; justify-content:space-between; align-items:center;
         font-size:11px; color:#555; margin-bottom:16px; }
  .container{ max-width: 640px; margin: 0 auto; position:relative; }
  .wm{
    position:absolute; top:50%; left:50%;
    transform: translate(-50%, -50%) rotate(-28deg);
    font-size: 72px; color: rgba(0,0,0,0.06); pointer-events:none;
    font-weight: 800; letter-spacing: 8px; white-space: nowrap;
  }

  /* ── COPY layout (image 5) ── */
  .ticket{ border:1.5px solid #000; padding:0; }
  .ticket .title{
    text-align:center; font-weight:800; padding:6px 0; border-bottom:1.5px solid #000;
    font-size:14px; letter-spacing:1px;
  }
  table.meta{ width:100%; border-collapse:collapse; }
  table.meta td{ padding:5px 10px; border-bottom:1px dashed #888; vertical-align:top; }
  table.meta td.k{ font-weight:700; width:48%; }
  table.meta td.v{ text-align:right; }
  .legs{ padding:6px 10px; border-bottom:1.5px solid #000; }
  .leg{ padding:8px 0; border-bottom:1px dashed #888; }
  .leg:last-child{ border-bottom:0; }
  .leg-hdr{ font-size:11px; color:#444; }
  .leg-teams{ font-weight:700; margin:2px 0 4px; }
  .leg-sel{ font-size:12px; }
  .boost-line{ margin-top:3px; font-size:12px; }
  .boost-line s{ color:#d11; }
  table.totals{ width:100%; border-collapse:collapse; }
  table.totals td{ padding:6px 10px; border-bottom:1px dashed #888; }
  table.totals td.k{ font-weight:700; }
  table.totals td.v{ text-align:right; font-weight:700; }
  table.totals tr.gain td{ background:#eee; font-size:15px; padding:9px 10px; border-bottom:0;}

  /* ── COMBINATIONS layout (image 6) ── */
  .combo-head{ margin-bottom: 12px; }
  .combo-head div{ margin: 1px 0; }
  table.combo{ width:100%; border-collapse:collapse; margin-top:8px; }
  table.combo td{ border:1px solid #000; padding:8px 10px; vertical-align:top; }
  table.combo td.match{ width:36%; }
  table.combo td.sel  { width:50%; }
  table.combo td.odds { width:14%; text-align:right; font-weight:700; }

  @media print {
    body{ padding:0; }
    .head{ position: fixed; top:0; left:0; right:0; padding: 6px 12px; }
    .no-print{ display:none !important; }
  }
  .no-print{
    text-align:center; margin: 18px 0 0;
  }
  .no-print button{
    padding: 9px 20px; font-size: 14px; cursor:pointer;
    background:#2bcd62; border:0; color:#000; border-radius:4px; font-weight:700;
  }
</style>
</head>
<body onload="setTimeout(function(){ window.print(); }, 250);">
<div class="head">
  <span><?= date('n/j/y, g:i A', time()) ?></span>
  <span>alpina216.com<?= $mode === 'combinations' ? '' : '   _' . htmlspecialchars((string)$bet['id']) ?></span>
</div>

<div class="container">

<?php if ($mode === 'combinations'): ?>
  <div class="combo-head">
    <div>couponno: <?= htmlspecialchars((string)$bet['id']) ?></div>
    <div>Date: <?= htmlspecialchars($date_short) ?></div>
    <div>Client: <?= htmlspecialchars($username) ?></div>
    <div><?= htmlspecialchars($type_label) ?></div>
  </div>
  <table class="combo">
    <?php foreach ($slip as $leg): ?>
      <tr>
        <td class="match">
          <?= htmlspecialchars((string)($leg['match'] ?? '')) ?>
        </td>
        <td class="sel">
          <?php if (!empty($leg['isBB']) && !empty($leg['legs']) && is_array($leg['legs'])): ?>
            <b>BetBuilder :</b>
            <?php
            $parts = [];
            foreach ($leg['legs'] as $L) {
                $mk = trim((string)($L['market'] ?? ''));
                $pk = trim((string)($L['name']   ?? $L['pick'] ?? ''));
                $parts[] = ($mk !== '' ? $mk . ': ' : '') . $pk;
            }
            echo htmlspecialchars(implode(' | ', $parts));
            ?>
          <?php else: ?>
            <?php
            $mk = trim((string)($leg['market'] ?? ''));
            $pk = trim((string)($leg['sel']    ?? $leg['pick'] ?? ''));
            echo htmlspecialchars(($mk !== '' ? $mk . ': ' : '') . $pk);
            ?>
          <?php endif; ?>
        </td>
        <td class="odds"><?= htmlspecialchars(number_format((float)($leg['val'] ?? $bet['total_odds']), 2)) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php else: ?>
  <div class="wm">COPY</div>
  <div class="ticket">
    <div class="title">COPY</div>
    <table class="meta">
      <tr><td class="k">TERMINAL</td><td class="v">(<?= htmlspecialchars($terminal) ?>)</td></tr>
      <tr><td class="k">NUMÉRO ID DU TICKET DE PARI</td><td class="v"><?= htmlspecialchars((string)$bet['id']) ?></td></tr>
      <tr><td class="k">DATE</td><td class="v"><?= htmlspecialchars($date_short) ?></td></tr>
      <tr><td class="k">TYPE DE PARI</td><td class="v"><?= htmlspecialchars($type_label) ?></td></tr>
    </table>
    <div class="legs">
      <?php foreach ($slip as $leg) echo render_leg_block($leg); ?>
    </div>
    <table class="totals">
      <tr><td class="k">MISE</td>   <td class="v">TND <?= number_format((float)$bet['amount'], 2) ?></td></tr>
      <tr><td class="k">COTES</td>  <td class="v"><?= number_format((float)$bet['total_odds'], 2) ?></td></tr>
      <tr class="gain"><td class="k">GAIN TOTAL:</td><td class="v">TND <?= number_format((float)$bet['potential_returns'], 2) ?></td></tr>
    </table>
  </div>
<?php endif; ?>

<div class="no-print">
  <button onclick="window.print()">Imprimer</button>
</div>

</div>
</body>
</html>
