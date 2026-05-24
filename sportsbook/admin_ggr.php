<?php
session_start();
require_once '../../includes/db.php';
if (!isset($_SESSION['user_id'])) { header('Location: /'); exit; }
$stmt = $pdo->prepare("SELECT role FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$me || !in_array($me['role'], ['admin','super_admin'])) { header('Location: /'); exit; }

// Ensure tables
$pdo->exec("
CREATE TABLE IF NOT EXISTS sportsbook_bets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    total_odds DECIMAL(10,4) NOT NULL,
    potential_returns DECIMAL(15,2) NOT NULL,
    slip JSON NOT NULL,
    status ENUM('pending','won','lost','refunded') DEFAULT 'pending',
    settled_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(user_id), INDEX(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS sportsbook_ggr (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bet_id INT NOT NULL,
    user_id INT NOT NULL,
    stake DECIMAL(15,2) NOT NULL,
    payout DECIMAL(15,2) DEFAULT 0,
    ggr DECIMAL(15,2) NOT NULL,
    result ENUM('won','lost','refunded') NOT NULL,
    settled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(settled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Summary stats
$summary = $pdo->query("
    SELECT 
        COUNT(*) total_bets,
        SUM(amount) total_stakes,
        SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) pending_count,
        SUM(CASE WHEN status='won'     THEN 1 ELSE 0 END) won_count,
        SUM(CASE WHEN status='lost'    THEN 1 ELSE 0 END) lost_count
    FROM sportsbook_bets
")->fetch(PDO::FETCH_ASSOC);

$ggr_summary = $pdo->query("
    SELECT 
        COALESCE(SUM(ggr),0) total_ggr,
        COALESCE(SUM(stake),0) settled_stakes,
        COALESCE(SUM(payout),0) total_payouts,
        COALESCE(ROUND(SUM(ggr)/NULLIF(SUM(stake),0)*100,2),0) margin_pct
    FROM sportsbook_ggr
")->fetch(PDO::FETCH_ASSOC);

// Pending bets
$pending = $pdo->query("
    SELECT sb.id, sb.user_id, u.username, sb.amount, sb.total_odds, sb.potential_returns, sb.slip, sb.created_at
    FROM sportsbook_bets sb
    JOIN users u ON sb.user_id=u.id
    WHERE sb.status='pending'
    ORDER BY sb.created_at DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

// Recent settled
$settled = $pdo->query("
    SELECT g.*, u.username, sb.total_odds
    FROM sportsbook_ggr g
    JOIN users u ON g.user_id=u.id
    JOIN sportsbook_bets sb ON g.bet_id=sb.id
    ORDER BY g.settled_at DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin - Sportsbook GGR Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0;font-family:"Segoe UI",system-ui,Arial,sans-serif}
body{background:#0f121a;color:#d0d4e0;font-size:13px}
.gg-wrap{max-width:1400px;margin:0 auto;padding:20px}
h1{font-size:22px;font-weight:700;color:#fff;margin-bottom:20px;display:flex;align-items:center;gap:10px}
h1 span{background:#12c156;color:#000;padding:4px 10px;border-radius:4px;font-size:14px}

/* Stat cards */
.gg-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:24px}
.gg-card{background:#171b26;border:1px solid #1e2330;border-radius:8px;padding:18px}
.gg-card-label{font-size:11px;color:#8a92a6;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px}
.gg-card-val{font-size:24px;font-weight:700;color:#fff}
.gg-card-val.green{color:#12c156}
.gg-card-val.red{color:#e02424}
.gg-card-val.blue{color:#1a73e8}

/* Tables */
.gg-section{background:#171b26;border:1px solid #1e2330;border-radius:8px;margin-bottom:20px;overflow:hidden}
.gg-section-head{padding:14px 18px;font-size:14px;font-weight:700;color:#fff;border-bottom:1px solid #1e2330;display:flex;justify-content:space-between;align-items:center}
table{width:100%;border-collapse:collapse}
th{background:#0f121a;color:#8a92a6;font-size:11px;text-transform:uppercase;padding:10px 14px;text-align:left;border-bottom:1px solid #1e2330}
td{padding:10px 14px;border-bottom:1px solid #0a0d14;color:#c0c4d0;vertical-align:top}
tr:hover td{background:#1a1f2e}
.badge{padding:3px 8px;border-radius:3px;font-size:11px;font-weight:700;display:inline-block}
.badge-pending{background:#2a3050;color:#8a92a6}
.badge-won{background:#0d4a2a;color:#12c156}
.badge-lost{background:#4a0d0d;color:#e02424}
.badge-refunded{background:#2a2010;color:#f59e0b}

/* Settle buttons */
.settle-btn{padding:5px 10px;border:none;border-radius:4px;cursor:pointer;font-size:12px;font-weight:600;margin-right:4px;transition:.15s}
.btn-won{background:#0d4a2a;color:#12c156}
.btn-won:hover{background:#12c156;color:#000}
.btn-lost{background:#4a0d0d;color:#e02424}
.btn-lost:hover{background:#e02424;color:#fff}
.btn-ref{background:#2a2010;color:#f59e0b}
.btn-ref:hover{background:#f59e0b;color:#000}

/* Slip preview */
.slip-preview{font-size:11px;color:#8a92a6;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
</style>
</head>
<body>
<div class="gg-wrap">
  <h1><i class="fa fa-bar-chart"></i> Sportsbook GGR Dashboard <span>Admin</span></h1>

  <!-- Stats -->
  <div class="gg-stats">
    <div class="gg-card">
      <div class="gg-card-label">Total Mises</div>
      <div class="gg-card-val"><?=number_format($summary['total_stakes']??0,2)?> TND</div>
    </div>
    <div class="gg-card">
      <div class="gg-card-label">Total Paris</div>
      <div class="gg-card-val blue"><?=(int)($summary['total_bets']??0)?></div>
    </div>
    <div class="gg-card">
      <div class="gg-card-label">En Attente</div>
      <div class="gg-card-val"><?=(int)($summary['pending_count']??0)?></div>
    </div>
    <div class="gg-card">
      <div class="gg-card-label">Gagnés / Perdus</div>
      <div class="gg-card-val"><?=(int)($summary['won_count']??0)?> / <?=(int)($summary['lost_count']??0)?></div>
    </div>
    <div class="gg-card">
      <div class="gg-card-label">GGR Total (Profit Net)</div>
      <div class="gg-card-val <?=(($ggr_summary['total_ggr']??0)>=0)?'green':'red'?>">
        <?=number_format($ggr_summary['total_ggr']??0,2)?> TND
      </div>
    </div>
    <div class="gg-card">
      <div class="gg-card-label">Marge Maison</div>
      <div class="gg-card-val green"><?=number_format($ggr_summary['margin_pct']??0,2)?>%</div>
    </div>
    <div class="gg-card">
      <div class="gg-card-label">Total Payouts</div>
      <div class="gg-card-val red"><?=number_format($ggr_summary['total_payouts']??0,2)?> TND</div>
    </div>
  </div>

  <!-- Pending Bets -->
  <div class="gg-section">
    <div class="gg-section-head">
      <span><i class="fa fa-clock-o"></i> Paris en Attente (<?=count($pending)?>)</span>
      <span style="font-size:11px;color:#8a92a6">Régler les paris après la fin du match</span>
    </div>
    <table>
      <tr>
        <th>#</th><th>Joueur</th><th>Mise</th><th>Cote</th><th>Gain Potentiel</th><th>Sélections</th><th>Date</th><th>Action</th>
      </tr>
      <?php if(empty($pending)): ?>
      <tr><td colspan="8" style="text-align:center;padding:30px;color:#8a92a6">Aucun pari en attente</td></tr>
      <?php else: foreach($pending as $b):
        $slip = json_decode($b['slip'], true) ?: [];
        $slipText = implode(', ', array_map(function($s){ return ($s['match']??'').' ('.$s['sel'].')'; }, $slip));
      ?>
      <tr>
        <td>#<?=$b['id']?></td>
        <td><strong style="color:#fff"><?=htmlspecialchars($b['username'])?></strong></td>
        <td><strong style="color:#12c156"><?=number_format($b['amount'],2)?> TND</strong></td>
        <td><?=number_format($b['total_odds'],2)?></td>
        <td><?=number_format($b['potential_returns'],2)?> TND</td>
        <td><div class="slip-preview" title="<?=htmlspecialchars($slipText)?>"><?=htmlspecialchars($slipText)?></div></td>
        <td style="color:#8a92a6"><?=date('d/m H:i', strtotime($b['created_at']))?></td>
        <td>
          <button class="settle-btn btn-won" onclick="settleBet(<?=$b['id']?>,'won')">✅ Gagné</button>
          <button class="settle-btn btn-lost" onclick="settleBet(<?=$b['id']?>,'lost')">❌ Perdu</button>
          <button class="settle-btn btn-ref" onclick="settleBet(<?=$b['id']?>,'refunded')">↩ Remb.</button>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </table>
  </div>

  <!-- Recent GGR -->
  <div class="gg-section">
    <div class="gg-section-head"><span><i class="fa fa-history"></i> Historique GGR Récent</span></div>
    <table>
      <tr>
        <th>#Pari</th><th>Joueur</th><th>Mise</th><th>Payout</th><th>GGR</th><th>Résultat</th><th>Date</th>
      </tr>
      <?php if(empty($settled)): ?>
      <tr><td colspan="7" style="text-align:center;padding:30px;color:#8a92a6">Aucun pari réglé</td></tr>
      <?php else: foreach($settled as $g): ?>
      <tr>
        <td>#<?=$g['bet_id']?></td>
        <td><?=htmlspecialchars($g['username'])?></td>
        <td><?=number_format($g['stake'],2)?> TND</td>
        <td><?=number_format($g['payout'],2)?> TND</td>
        <td style="color:<?=((float)$g['ggr']>=0)?'#12c156':'#e02424'?>;font-weight:700">
          <?=((float)$g['ggr']>=0)?'+':''?><?=number_format($g['ggr'],2)?> TND
        </td>
        <td>
          <span class="badge badge-<?=$g['result']?>">
            <?=strtoupper($g['result'])?>
          </span>
        </td>
        <td style="color:#8a92a6"><?=date('d/m H:i', strtotime($g['settled_at']))?></td>
      </tr>
      <?php endforeach; endif; ?>
    </table>
  </div>
</div>

<script>
function settleBet(betId, result) {
  var labels = {won:'GAGNÉ',lost:'PERDU',refunded:'REMBOURSÉ'};
  if(!confirm('Marquer le pari #'+betId+' comme '+labels[result]+'?')) return;
  fetch('/public_html/sportsbook/settle.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'action=settle&bet_id='+betId+'&result='+result
  })
  .then(r=>r.json())
  .then(d=>{
    if(d.success){
      var msg = '✅ Pari #'+betId+' réglé: '+labels[result];
      if(d.payout>0) msg += '\nPayout: '+d.payout.toFixed(2)+' TND';
      msg += '\nGGR: '+(d.ggr>=0?'+':'')+d.ggr.toFixed(2)+' TND';
      alert(msg);
      location.reload();
    } else {
      alert('❌ Erreur: '+d.message);
    }
  });
}
</script>
</body>
</html>
