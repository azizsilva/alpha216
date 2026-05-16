<?php
$mk_fragment = isset($_GET['mk_fragment']);
if (!$mk_fragment) {
    require_once __DIR__ . '/app/index.php';
    exit;
}

require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/odds-api.php';

// Fetch Live Football Events
$params = [
    'apiKey' => odds_api_key(),
    'sport' => 'football'
];
$live_events = odds_api_get('/events/live', $params, 30); // Cache for 30s

$is_error = isset($live_events['__error']);
?>

<div class="live-football-container">
    <div class="live-header container">
        <div class="live-title">
            <span class="live-dot"></span>
            <h1>LIVE FOOTBALL</h1>
        </div>
        <div class="live-stats">
            <span><?php echo is_array($live_events) ? count($live_events) : 0; ?> Matches en direct</span>
        </div>
    </div>

    <div class="live-content container">
        <?php if ($is_error): ?>
            <div class="error-box">
                <i class="fa fa-exclamation-triangle"></i>
                <p>Impossible de charger les scores en direct. Veuillez réessayer plus tard.</p>
                <?php if (isset($live_events['message'])): ?>
                    <small><?php echo htmlspecialchars($live_events['message']); ?></small>
                <?php endif; ?>
            </div>
        <?php elseif (empty($live_events)): ?>
            <div class="no-events-box">
                <i class="fa fa-clock-o"></i>
                <p>Aucun match en direct pour le moment.</p>
            </div>
        <?php else: ?>
            <div class="live-matches-grid">
                <?php foreach ($live_events as $event): ?>
                    <div class="match-card">
                        <div class="match-league"><?php echo htmlspecialchars($event['leagueName'] ?? 'Football'); ?></div>
                        <div class="match-teams">
                            <div class="team team-home">
                                <span class="team-name"><?php echo htmlspecialchars($event['homeTeamName'] ?? 'Home'); ?></span>
                            </div>
                            <div class="match-score">
                                <span class="score"><?php echo $event['homeScore'] ?? '0'; ?></span>
                                <span class="separator">-</span>
                                <span class="score"><?php echo $event['awayScore'] ?? '0'; ?></span>
                            </div>
                            <div class="team team-away">
                                <span class="team-name"><?php echo htmlspecialchars($event['awayTeamName'] ?? 'Away'); ?></span>
                            </div>
                        </div>
                        <div class="match-info">
                            <span class="match-time"><i class="fa fa-clock-o"></i> <?php echo htmlspecialchars($event['matchTime'] ?? 'Live'); ?>'</span>
                        </div>
                        <div class="match-odds">
                            <div class="odd-item">
                                <span class="odd-label">1</span>
                                <span class="odd-val">2.10</span>
                            </div>
                            <div class="odd-item">
                                <span class="odd-label">X</span>
                                <span class="odd-val">3.40</span>
                            </div>
                            <div class="odd-item">
                                <span class="odd-label">2</span>
                                <span class="odd-val">4.20</span>
                            </div>
                        </div>
                        <button class="bet-now-btn" onclick="mkSafeLaunch('8a704858d5deb4af1ddc722092ac7614', 'Sports')">PARIER</button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.live-football-container {
    background: #0b0b0b;
    min-height: 100vh;
    padding: 30px 0;
    color: #fff;
}

.live-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 15px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.live-title {
    display: flex;
    align-items: center;
    gap: 15px;
}

.live-dot {
    width: 12px;
    height: 12px;
    background: #ff4444;
    border-radius: 50%;
    box-shadow: 0 0 10px #ff4444;
    animation: pulse-red 2s infinite;
}

@keyframes pulse-red {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(255, 68, 68, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(255, 68, 68, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(255, 68, 68, 0); }
}

.live-title h1 {
    font-size: 28px;
    font-weight: 900;
    margin: 0;
    letter-spacing: 1px;
}

.live-stats {
    color: #bfff00;
    font-weight: 700;
    font-size: 14px;
}

.live-matches-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.match-card {
    background: #151515;
    border: 1px solid rgba(255,255,255,0.05);
    border-radius: 12px;
    padding: 20px;
    transition: all 0.3s;
}

.match-card:hover {
    border-color: #bfff00;
    background: #1a1a1a;
}

.match-league {
    font-size: 11px;
    color: #888;
    text-transform: uppercase;
    font-weight: 700;
    margin-bottom: 15px;
}

.match-teams {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.team {
    flex: 1;
    text-align: center;
}

.team-name {
    font-size: 15px;
    font-weight: 700;
}

.match-score {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #000;
    padding: 5px 15px;
    border-radius: 20px;
    margin: 0 10px;
}

.score {
    font-size: 20px;
    font-weight: 900;
    color: #bfff00;
}

.separator {
    color: #444;
}

.match-info {
    text-align: center;
    margin-bottom: 15px;
}

.match-time {
    font-size: 12px;
    color: #ff4444;
    font-weight: 700;
}

.match-odds {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.odd-item {
    flex: 1;
    background: #222;
    padding: 8px;
    border-radius: 6px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    transition: background 0.2s;
}

.odd-item:hover {
    background: #333;
}

.odd-label {
    font-size: 11px;
    color: #888;
    font-weight: 700;
}

.odd-val {
    font-size: 13px;
    font-weight: 800;
    color: #bfff00;
}

.bet-now-btn {
    width: 100%;
    background: #bfff00;
    color: #000;
    border: none;
    padding: 10px;
    border-radius: 6px;
    font-weight: 800;
    margin-top: 15px;
    transition: transform 0.2s;
}

.bet-now-btn:hover {
    transform: scale(1.02);
}

.error-box, .no-events-box {
    text-align: center;
    padding: 100px 0;
    color: #555;
}

.error-box i, .no-events-box i {
    font-size: 50px;
    margin-bottom: 20px;
}

@media (max-width: 767px) {
    .live-matches-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
