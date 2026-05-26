<?php
session_start();
require_once '../includes/db.php';

// 1. Authentication & Authorization (Provider Only)
if (!isset($_SESSION['user_id'])) { 
    die("Accès refusé. Veuillez vous connecter."); 
}

$stmt = $pdo->prepare("SELECT id, username, email, role, balance FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$me || $me['role'] !== 'provider') { 
    die("<h1>403 Forbidden</h1><p>Seul le rôle Provider peut accéder à ce tableau de bord.</p>"); 
}

// 2. Handle POST Actions (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    
    if ($action === 'add_balance') {
        $target_id = (int)$_POST['user_id'];
        $amount = (float)$_POST['amount'];
        if ($amount > 0) {
            $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            if ($stmt->execute([$amount, $target_id])) {
                echo json_encode(['success' => true, 'msg' => "Solde ajouté avec succès !"]);
            } else {
                echo json_encode(['success' => false, 'msg' => "Erreur DB."]);
            }
        }
        exit;
    }
    
    if ($action === 'update_margin') {
        $margin = (float)$_POST['margin'];
        if ($margin >= 0 && $margin <= 50) {
            $stmt = $pdo->prepare("UPDATE provider_config SET setting_value = ? WHERE setting_key = 'global_margin_percent'");
            if ($stmt->execute([$margin])) {
                echo json_encode(['success' => true, 'msg' => "Marge GGR mise à jour à $margin% !"]);
            }
        }
        exit;
    if ($action === 'create_player') {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        
        if ($username && $email && $password) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, password_text, role, status, balance, mobile) VALUES (?, ?, ?, ?, 'player', 'active', 0, '00000000')");
            try {
                if ($stmt->execute([$username, $email, $hashed, $password])) {
                    echo json_encode(['success' => true, 'msg' => "Joueur créé avec succès !"]);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'msg' => "Erreur: Email ou Username existe déjà."]);
            }
        } else {
            echo json_encode(['success' => false, 'msg' => "Remplissez tous les champs."]);
        }
        exit;
    }
}

// 3. Fetch Data for Dashboard
// Configs
$configs = [];
$c_stmt = $pdo->query("SELECT setting_key, setting_value FROM provider_config");
while ($row = $c_stmt->fetch(PDO::FETCH_ASSOC)) {
    $configs[$row['setting_key']] = $row['setting_value'];
}
$margin = $configs['global_margin_percent'] ?? 11;

// Ensure tables exist to prevent 500 error
$pdo->exec("CREATE TABLE IF NOT EXISTS sportsbook_bets (id INT AUTO_INCREMENT PRIMARY KEY, amount DECIMAL(15,2) DEFAULT 0, status VARCHAR(20) DEFAULT 'pending')");
$pdo->exec("CREATE TABLE IF NOT EXISTS sportsbook_ggr (id INT AUTO_INCREMENT PRIMARY KEY, ggr DECIMAL(15,2) DEFAULT 0)");

// Global Stats
try {
    $stats = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM users WHERE role='player') as total_players,
            (SELECT COALESCE(SUM(balance),0) FROM users WHERE role='player') as total_player_balances,
            (SELECT COALESCE(SUM(amount),0) FROM sportsbook_bets WHERE status='pending') as current_exposure,
            (SELECT COALESCE(SUM(ggr),0) FROM sportsbook_ggr) as lifetime_ggr
    ")->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stats = ['total_players' => 0, 'total_player_balances' => 0, 'current_exposure' => 0, 'lifetime_ggr' => 0];
}

// Player List
$players = $pdo->query("
    SELECT id, username, email, balance, status, created_at 
    FROM users 
    WHERE role='player' 
    ORDER BY id DESC LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Provider Dashboard - Control Center</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #0a0e17;
            --bg-card: #151a28;
            --border: #232a3b;
            --primary: #12c156;
            --text-main: #e2e8f0;
            --text-muted: #94a3b8;
            --danger: #ef4444;
            --accent: #3b82f6;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', -apple-system, sans-serif; }
        body { background: var(--bg-dark); color: var(--text-main); display: flex; height: 100vh; overflow: hidden; }
        
        /* Sidebar */
        .sidebar { width: 260px; background: var(--bg-card); border-right: 1px solid var(--border); display: flex; flex-direction: column; }
        .sidebar-header { padding: 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 12px; }
        .sidebar-header i { font-size: 24px; color: var(--primary); }
        .sidebar-header h2 { font-size: 16px; font-weight: 700; letter-spacing: 0.5px; }
        .nav-links { list-style: none; padding: 20px 0; flex: 1; }
        .nav-item { padding: 12px 24px; color: var(--text-muted); cursor: pointer; display: flex; align-items: center; gap: 12px; transition: 0.2s; }
        .nav-item:hover, .nav-item.active { background: rgba(18, 193, 86, 0.1); color: var(--primary); border-right: 3px solid var(--primary); }
        
        /* Main Content */
        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .header { height: 70px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 0 30px; background: var(--bg-card); }
        .user-profile { display: flex; align-items: center; gap: 10px; }
        .user-profile .role-badge { background: var(--primary); color: #000; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        
        .container { padding: 30px; }
        
        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 20px; display: flex; align-items: center; gap: 20px; }
        .stat-icon { width: 50px; height: 50px; border-radius: 12px; background: rgba(18, 193, 86, 0.1); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .stat-info h4 { color: var(--text-muted); font-size: 13px; font-weight: 500; margin-bottom: 4px; }
        .stat-info .val { font-size: 24px; font-weight: 700; color: #fff; }
        
        /* Sections */
        .section-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 24px; margin-bottom: 30px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .section-header h3 { font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .section-header h3 i { color: var(--primary); }
        
        /* Tables */
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px 16px; color: var(--text-muted); font-size: 12px; text-transform: uppercase; border-bottom: 1px solid var(--border); }
        td { padding: 16px; border-bottom: 1px solid var(--border); font-size: 14px; color: #cbd5e1; }
        tr:hover td { background: rgba(255,255,255,0.02); }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .badge.active { background: rgba(18, 193, 86, 0.15); color: var(--primary); }
        
        /* Controls */
        .btn { background: var(--primary); color: #000; border: none; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn:hover { opacity: 0.9; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .btn-outline { background: transparent; border: 1px solid var(--primary); color: var(--primary); }
        .btn-outline:hover { background: var(--primary); color: #000; }
        
        input[type="number"] { background: var(--bg-dark); border: 1px solid var(--border); color: #fff; padding: 8px 12px; border-radius: 6px; outline: none; }
        input[type="number"]:focus { border-color: var(--primary); }
        
        .flex-row { display: flex; gap: 10px; align-items: center; }

        /* Modals and Tabs */
        .page-section { display: none; }
        .page-section.active { display: block; animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: var(--bg-card); padding: 30px; border-radius: 12px; width: 100%; max-width: 400px; border: 1px solid var(--border); }
        .modal-content h3 { margin-bottom: 20px; color: #fff; }
        .modal-content .form-group { margin-bottom: 15px; }
        .modal-content label { display: block; margin-bottom: 5px; color: var(--text-muted); font-size: 13px; }
        .modal-content input { width: 100%; background: var(--bg-dark); border: 1px solid var(--border); padding: 10px; color: #fff; border-radius: 6px; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <i class="fa-solid fa-bolt"></i>
            <h2>PROVIDER HUB</h2>
        </div>
        <ul class="nav-links">
            <li class="nav-item active" onclick="switchTab('dashboard', this)"><i class="fa-solid fa-chart-pie"></i> Dashboard</li>
            <li class="nav-item" onclick="switchTab('credit', this)"><i class="fa-solid fa-users"></i> Credit Management</li>
            <li class="nav-item" onclick="switchTab('odds', this)"><i class="fa-solid fa-sliders"></i> Odds Configurator</li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <div class="search-bar">
                <span style="color:var(--text-muted); font-size:14px;"><i class="fa-solid fa-lock"></i> Provider Level Access</span>
            </div>
            <div class="user-profile">
                <span class="role-badge">PROVIDER</span>
                <span><?=htmlspecialchars($me['email'])?></span>
            </div>
        </div>

        <div class="container">
            
            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
                    <div class="stat-info">
                        <h4>Total Players</h4>
                        <div class="val"><?=(int)$stats['total_players']?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="color:var(--accent); background:rgba(59, 130, 246, 0.1)"><i class="fa-solid fa-wallet"></i></div>
                    <div class="stat-info">
                        <h4>Circulating Credits</h4>
                        <div class="val"><?=number_format($stats['total_player_balances'], 2)?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="color:var(--danger); background:rgba(239, 68, 68, 0.1)"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    <div class="stat-info">
                        <h4>Pending Exposure</h4>
                        <div class="val"><?=number_format($stats['current_exposure'], 2)?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa-solid fa-sack-dollar"></i></div>
                    <div class="stat-info">
                        <h4>Lifetime GGR</h4>
                        <div class="val"><?=number_format($stats['lifetime_ggr'], 2)?></div>
                    </div>
                </div>
            </div>

            <!-- Configuration & Risk -->
            <div id="odds" class="page-section section-card">
                <div class="section-header">
                    <h3><i class="fa-solid fa-gears"></i> Odds Engine (GGR Margin)</h3>
                </div>
                <p style="color:var(--text-muted); font-size:14px; margin-bottom:15px;">
                    Control the global overround applied to raw BetsAPI odds. A higher margin increases your GGR but lowers odds for players. Default is 11%.
                </p>
                <div class="flex-row">
                    <input type="number" id="ggr_margin" value="<?=$margin?>" min="0" max="50" step="0.1" style="width:100px;">
                    <span style="font-size:18px; color:var(--text-muted);">%</span>
                    <button class="btn" onclick="updateMargin()"><i class="fa-solid fa-save"></i> Save Global Margin</button>
                </div>
            </div>

            <!-- Player Credit Distribution -->
            <div id="credit" class="page-section section-card">
                <div class="section-header">
                    <h3><i class="fa-solid fa-building-columns"></i> Central Bank (Credit Distribution)</h3>
                    <button class="btn btn-outline" onclick="openCreatePlayerModal()"><i class="fa-solid fa-user-plus"></i> Create Player</button>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Current Balance</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($players as $p): ?>
                        <tr>
                            <td>#<?=$p['id']?></td>
                            <td style="color:#fff; font-weight:500;"><?=htmlspecialchars($p['username'])?></td>
                            <td><?=htmlspecialchars($p['email'])?></td>
                            <td><span class="badge active"><?=strtoupper($p['status'])?></span></td>
                            <td style="color:var(--primary); font-weight:700; font-family:monospace; font-size:15px;">
                                <?=number_format($p['balance'], 2)?> TND
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline" onclick="addBalance(<?=$p['id']?>, '<?=htmlspecialchars($p['username'])?>')">
                                    <i class="fa-solid fa-plus"></i> Add Credit
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($players)): ?>
                        <tr><td colspan="6" style="text-align:center;">Aucun joueur trouvé.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <!-- Create Player Modal -->
    <div id="createPlayerModal" class="modal">
        <div class="modal-content">
            <h3>Create New Player</h3>
            <div class="form-group">
                <label>Username</label>
                <input type="text" id="new_username" placeholder="player123">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" id="new_email" placeholder="player@email.com">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="text" id="new_password" placeholder="password">
            </div>
            <div class="modal-actions">
                <button class="btn btn-outline" onclick="closeModal()">Cancel</button>
                <button class="btn" onclick="submitCreatePlayer()">Create</button>
            </div>
        </div>
    </div>

    <script>
        // Init active tab
        document.querySelectorAll('.page-section').forEach(el => el.classList.add('active'));

        function switchTab(tabId, element) {
            document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
            element.classList.add('active');

            if(tabId === 'dashboard') {
                document.querySelectorAll('.page-section').forEach(el => el.classList.add('active'));
            } else {
                document.querySelectorAll('.page-section').forEach(el => el.classList.remove('active'));
                document.getElementById(tabId).classList.add('active');
            }
        }

        function openCreatePlayerModal() {
            document.getElementById('createPlayerModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('createPlayerModal').classList.remove('active');
        }

        function submitCreatePlayer() {
            let user = document.getElementById('new_username').value;
            let email = document.getElementById('new_email').value;
            let pass = document.getElementById('new_password').value;

            if(!user || !email || !pass) return alert("Remplissez tout!");

            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=create_player&username=${encodeURIComponent(user)}&email=${encodeURIComponent(email)}&password=${encodeURIComponent(pass)}`
            })
            .then(r => r.json())
            .then(res => {
                alert(res.msg);
                if (res.success) location.reload();
            });
        }
        function addBalance(userId, username) {
            let amount = prompt("Entrez le montant de crédit à ajouter pour " + username + " :");
            if (amount && !isNaN(amount)) {
                fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=add_balance&user_id=' + userId + '&amount=' + amount
                })
                .then(r => r.json())
                .then(res => {
                    alert(res.msg);
                    if (res.success) location.reload();
                });
            }
        }

        function updateMargin() {
            let margin = document.getElementById('ggr_margin').value;
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=update_margin&margin=' + margin
            })
            .then(r => r.json())
            .then(res => {
                alert(res.msg);
                if (res.success) location.reload();
            });
        }
    </script>
</body>
</html>
