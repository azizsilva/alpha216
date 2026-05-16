<?php
$admin_base = '../';
$base_path = 'https://tanitbet216.com/ui/www.moneyking365.com/';
require '../includes/db.php';
require '../includes/auth.php';
require '../includes/hierarchy.php';
require_admin_login($admin_base);

$page_title = 'Dashboard';
require '../includes/header.php';

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Determine child role
$child_role = admin_child_role($role);

// Handle POST Actions (Status Change, Password Change)
$action_message = '';
$action_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action_type'])) {
        $target_id = $_POST['target_id'] ?? 0;
        
        // Verify target is a child of current user
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND parent_id = ?");
        $stmt->execute([$target_id, $user_id]);
        $target = $stmt->fetch();

        if ($target) {
            if ($_POST['action_type'] === 'change_password') {
                $new_pass = $_POST['new_password'];
                if (!empty($new_pass)) {
                    $hashed_pass = md5($new_pass);
                    $stmt = $pdo->prepare("UPDATE users SET password = ?, password_text = ? WHERE id = ?");
                    if ($stmt->execute([$hashed_pass, $new_pass, $target_id])) {
                        $action_message = "Password updated successfully for " . htmlspecialchars($target['username']);
                    } else {
                        $action_error = "Failed to update password.";
                    }
                } else {
                    $action_error = "Password cannot be empty.";
                }
            } elseif ($_POST['action_type'] === 'change_status') {
                $new_status = $_POST['new_status']; // 'active', 'locked', 'suspended'
                $allowed_statuses = ['active', 'locked', 'suspended'];
                if (in_array($new_status, $allowed_statuses)) {
                    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
                    if ($stmt->execute([$new_status, $target_id])) {
                        $action_message = "Status updated to " . strtoupper($new_status) . " for " . htmlspecialchars($target['username']);
                    } else {
                        $action_error = "Failed to update status.";
                    }
                } else {
                    $action_error = "Invalid status selected.";
                }
            }
        } else {
            $action_error = "User not found or permission denied.";
        }
    }
}

// Fetch children
$children = [];
if ($child_role) {
    // Note: Assuming 'balance', 'credit_ref', 'exposure', 'rate' exist in DB as per new schema
    // We select specific columns. If schema not fully migrated, 'coins' might be used.
    // For now, mapping 'coins' to 'balance' if needed, but assuming new schema fields.
    $stmt = $pdo->prepare("SELECT * FROM users WHERE parent_id = ? AND role = ?");
    $stmt->execute([$user_id, $child_role]);
    $children = $stmt->fetchAll();
}

// Calculate totals for Stats Bar
$total_balance = 0;
$total_exposure = 0;
$total_avail_balance = 0;
$ref_pnl = 0;

foreach ($children as $child) {
    $balance = $child['balance'] ?? $child['coins'] ?? 0;
    $exposure = $child['exposure'] ?? 0;
    $credit_ref = $child['credit_ref'] ?? 0;
    
    $total_balance += $balance;
    $total_exposure += $exposure;
    $total_avail_balance += ($balance + $credit_ref - $exposure); // Example formula
}

// Stats for current user
$my_balance = $_SESSION['coins'] ?? 0; // Or fetch from DB if real-time needed
$my_exposure = 0; // Fetch from DB
$my_avail_balance = $my_balance - $my_exposure; 

$overview = [];

if ($role === 'admin') {
    $overview['partners'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='partner'")->fetchColumn();
    $overview['super_masters'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='super_master'")->fetchColumn();
    $overview['masters'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='master'")->fetchColumn();
    $overview['agents'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='agent'")->fetchColumn();
    $overview['players'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='player'")->fetchColumn();
}

function ta_empty_months() {
    return array_fill(1, 12, 0.0);
}

function ta_fetch_monthly_sales(PDO $pdo, $role, $user_id) {
    $year = (int)date('Y');
    $monthly = ta_empty_months();

    try {
        $where = "WHERE p.status='completed' AND p.type IN ('deposit','adjustment') AND YEAR(COALESCE(p.completed_at, p.created_at)) = ?";
        $params = [$year];
        if ($role !== 'admin') {
            $where .= " AND (p.payer_id=? OR p.payee_id=? OR p.created_by=?)";
            $params[] = $user_id;
            $params[] = $user_id;
            $params[] = $user_id;
        }
        $stmt = $pdo->prepare("SELECT MONTH(COALESCE(p.completed_at, p.created_at)) AS month_no, COALESCE(SUM(p.amount), 0) AS total_amount FROM payments p $where GROUP BY MONTH(COALESCE(p.completed_at, p.created_at))");
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $m = (int)($row['month_no'] ?? 0);
            if ($m >= 1 && $m <= 12) {
                $monthly[$m] = (float)($row['total_amount'] ?? 0);
            }
        }
    } catch (Exception $e) {
    }

    if (array_sum($monthly) <= 0) {
        try {
            $where = "WHERE t.type='deposit' AND YEAR(t.created_at) = ?";
            $params = [$year];
            if ($role !== 'admin') {
                $where .= " AND (t.sender_id=? OR t.receiver_id=?)";
                $params[] = $user_id;
                $params[] = $user_id;
            }
            $stmt = $pdo->prepare("SELECT MONTH(t.created_at) AS month_no, COALESCE(SUM(t.amount), 0) AS total_amount FROM transactions t $where GROUP BY MONTH(t.created_at)");
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $m = (int)($row['month_no'] ?? 0);
                if ($m >= 1 && $m <= 12) {
                    $monthly[$m] = (float)($row['total_amount'] ?? 0);
                }
            }
        } catch (Exception $e) {
        }
    }

    return $monthly;
}

?>

<!-- Messages -->
<?php if ($action_message): ?>
    <div class="alert alert-success alert-dismissible fade show m-3">
        <?php echo $action_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($action_error): ?>
    <div class="alert alert-danger alert-dismissible fade show m-3">
        <?php echo $action_error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php
  $ta_primary_count = $role === 'admin' ? (int)($overview['players'] ?? 0) : count($children);
  $ta_secondary_count = $role === 'admin'
      ? ((int)($overview['partners'] ?? 0) + (int)($overview['super_masters'] ?? 0) + (int)($overview['masters'] ?? 0) + (int)($overview['agents'] ?? 0))
      : count($children);
  $ta_monthly_sales = ta_fetch_monthly_sales($pdo, $role, (int)$user_id);
  $ta_sales_max = max(1.0, max($ta_monthly_sales));
?>
<div class="ta-dashboard-showcase">
  <div class="ta-metric-grid">
    <div class="ta-metric-card">
      <div class="ta-card-icon"><?php echo admin_svg_icon('ri-team-line'); ?></div>
      <div class="ta-metric-label"><?php echo $role === 'admin' ? 'Players' : admin_role_label($child_role ?: 'Users'); ?></div>
      <div class="ta-metric-row">
        <strong><?php echo number_format($ta_primary_count); ?></strong>
        <span class="ta-trend up"><svg width="16" height="16" viewBox="0 0 20 20" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path d="M10 15V5m0 0L5.5 9.5M10 5l4.5 4.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg> 11.01%</span>
      </div>
    </div>
    <div class="ta-metric-card">
      <div class="ta-card-icon"><?php echo admin_svg_icon('ri-stack-line'); ?></div>
      <div class="ta-metric-label"><?php echo $role === 'admin' ? 'Network' : 'Downline'; ?></div>
      <div class="ta-metric-row">
        <strong><?php echo number_format($ta_secondary_count); ?></strong>
        <span class="ta-trend down"><svg width="16" height="16" viewBox="0 0 20 20" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path d="M10 5v10m0 0 4.5-4.5M10 15l-4.5-4.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg> 9.05%</span>
      </div>
    </div>
  </div>

  <div class="ta-panel-card ta-sales-card">
    <div class="ta-card-head">
      <div>
        <h3>Monthly Sales</h3>
      </div>
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path d="M12 6h.01M12 12h.01M12 18h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
    </div>
    <div class="ta-bars" aria-label="Monthly sales for <?php echo (int)date('Y'); ?>">
      <?php foreach ($ta_monthly_sales as $amount): ?>
        <?php $height = $amount > 0 ? max(8, (int)round(($amount / $ta_sales_max) * 92)) : 0; ?>
        <span style="height: <?php echo (int)$height; ?>%;" title="<?php echo number_format((float)$amount, 2); ?> TND"></span>
      <?php endforeach; ?>
    </div>
    <div class="ta-bar-labels">
      <?php foreach (['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'] as $month): ?>
        <span><?php echo $month; ?></span>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Stats Bar -->
<div class="stats-bar">
    <div class="stat-item">Total Balance: <span><?php echo number_format($total_balance, 0); ?> TND</span></div>
    <div class="stat-item red">Total Exposure: <span>(<?php echo number_format($total_exposure, 0); ?>) TND</span></div>
    <div class="stat-item">Total Avail. Balance: <span><?php echo number_format($total_avail_balance, 0); ?> TND</span></div>
    <?php if($role !== 'admin'): ?>
        <div class="stat-item">Balance: <span><?php echo number_format($my_balance, 0); ?> TND</span></div>
        <div class="stat-item">Avail. Balance: <span><?php echo number_format($my_avail_balance, 0); ?> TND</span></div>
    <?php endif; ?>
    <div class="stat-item">Ref Pnl: <span><?php echo number_format($ref_pnl, 0); ?> TND</span></div>
</div>

<?php if ($role === 'admin'): ?>
  <div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
      <div class="card">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <div class="text-body-secondary">Partners</div>
              <h4 class="mb-0"><?php echo (int)($overview['partners'] ?? 0); ?></h4>
            </div>
            <a class="btn btn-sm btn-primary" href="../masters/?role=partner">View</a>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="card">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <div class="text-body-secondary">Super Masters</div>
              <h4 class="mb-0"><?php echo (int)($overview['super_masters'] ?? 0); ?></h4>
            </div>
            <a class="btn btn-sm btn-primary" href="../masters/?role=super_master">View</a>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="card">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <div class="text-body-secondary">Masters</div>
              <h4 class="mb-0"><?php echo (int)($overview['masters'] ?? 0); ?></h4>
            </div>
            <a class="btn btn-sm btn-primary" href="../masters/">View</a>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="card">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <div class="text-body-secondary">Agents</div>
              <h4 class="mb-0"><?php echo (int)($overview['agents'] ?? 0); ?></h4>
            </div>
            <a class="btn btn-sm btn-primary" href="../agents/">View</a>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="card">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <div class="text-body-secondary">Players</div>
              <h4 class="mb-0"><?php echo (int)($overview['players'] ?? 0); ?></h4>
            </div>
            <a class="btn btn-sm btn-primary" href="../players/">View</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php if (false): ?>
  <div class="row g-3">
    <div class="col-12 col-xl-6">
      <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div>
            <h5 class="mb-1">Latest Partners</h5>
            <div class="text-body-secondary">Newest partner accounts</div>
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-primary" href="../create-member/?create_role=partner">Create Partner</a>
            <a class="btn btn-outline-secondary" href="../masters/?role=partner">All Partners</a>
          </div>
        </div>
        <div class="card-body">
          <div class="table-responsive">
          <table class="table table-hover custom-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Partner</th>
                <th>Password</th>
                <th>Super Masters</th>
                <th>Masters</th>
                <th>Agents</th>
                <th>Players</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($recent_partners)): ?>
                <?php foreach ($recent_partners as $p): ?>
                  <tr>
                    <td><?php echo (int)$p['id']; ?></td>
                    <td><?php echo htmlspecialchars($p['username']); ?></td>
                    <td><span class="ta-password-chip"><?php echo htmlspecialchars(admin_display_password($p)); ?></span></td>
                    <td><?php echo (int)($p['super_masters_count'] ?? 0); ?></td>
                    <td><?php echo (int)($p['masters_count'] ?? 0); ?></td>
                    <td><?php echo (int)($p['agents_count'] ?? 0); ?></td>
                    <td><?php echo (int)($p['players_count'] ?? 0); ?></td>
                    <td><?php echo htmlspecialchars(strtoupper($p['status'] ?? 'active')); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="8" class="text-center">No partners found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-6">
      <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div>
            <h5 class="mb-1">Latest Super Masters</h5>
            <div class="text-body-secondary">Newest super master accounts</div>
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-primary" href="../create-member/?create_role=super_master">Create Super Master</a>
            <a class="btn btn-outline-secondary" href="../masters/?role=super_master">All Super Masters</a>
          </div>
        </div>
        <div class="card-body">
          <div class="table-responsive">
          <table class="table table-hover custom-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Super Master</th>
                <th>Password</th>
                <th>Partner</th>
                <th>Masters</th>
                <th>Agents</th>
                <th>Players</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($recent_super_masters)): ?>
                <?php foreach ($recent_super_masters as $sm): ?>
                  <tr>
                    <td><?php echo (int)$sm['id']; ?></td>
                    <td><?php echo htmlspecialchars($sm['username']); ?></td>
                    <td><span class="ta-password-chip"><?php echo htmlspecialchars(admin_display_password($sm)); ?></span></td>
                    <td><?php echo htmlspecialchars($sm['partner_name'] ?? '-'); ?></td>
                    <td><?php echo (int)($sm['masters_count'] ?? 0); ?></td>
                    <td><?php echo (int)($sm['agents_count'] ?? 0); ?></td>
                    <td><?php echo (int)($sm['players_count'] ?? 0); ?></td>
                    <td><?php echo htmlspecialchars(strtoupper($sm['status'] ?? 'active')); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="8" class="text-center">No super masters found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div>
            <h5 class="mb-1">Latest Masters</h5>
            <div class="text-body-secondary">Full hierarchy overview</div>
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-primary" href="../create-member/?create_role=master">Create Master</a>
            <a class="btn btn-outline-secondary" href="../masters/">All Masters</a>
          </div>
        </div>
        <div class="card-body">
          <table class="table table-hover custom-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Master</th>
                <th>Password</th>
                <th>Commission %</th>
                <th>Balance</th>
                <th>Credit Limit</th>
                <th>Agents</th>
                <th>Players</th>
                <th>Status</th>
                <th class="text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($recent_masters)): ?>
                <?php foreach ($recent_masters as $m): ?>
                  <tr>
                    <td><?php echo (int)$m['id']; ?></td>
                    <td><?php echo htmlspecialchars($m['username']); ?></td>
                    <td><span class="ta-password-chip"><?php echo htmlspecialchars(admin_display_password($m)); ?></span></td>
                    <td><?php echo number_format((float)($m['rate'] ?? 0), 2); ?></td>
                    <td><?php echo number_format((float)($m['balance'] ?? 0), 2); ?></td>
                    <td><?php echo number_format((float)($m['credit_ref'] ?? 0), 2); ?></td>
                    <td><?php echo (int)($m['agents_count'] ?? 0); ?></td>
                    <td><?php echo (int)($m['players_count'] ?? 0); ?></td>
                    <td><?php echo htmlspecialchars(strtoupper($m['status'] ?? 'active')); ?></td>
                    <td class="text-right">
                      <a class="btn btn-sm btn-outline-secondary" href="../agents/?master_id=<?php echo (int)$m['id']; ?>">Agents</a>
                      <a class="btn btn-sm btn-outline-secondary" href="../players/?master_id=<?php echo (int)$m['id']; ?>">Players</a>
                      <a class="btn btn-sm btn-outline-secondary" href="../my-account/?user_id=<?php echo (int)$m['id']; ?>">Sessions</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="10" class="text-center">No masters found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-6">
      <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div>
            <h5 class="mb-1">Latest Agents</h5>
            <div class="text-body-secondary">Recently created agents</div>
          </div>
          <a class="btn btn-outline-secondary" href="../agents/">All Agents</a>
        </div>
        <div class="card-body">
          <table class="table table-hover custom-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Agent</th>
                <th>Password</th>
                <th>Master</th>
                <th>Players</th>
                <th class="text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($recent_agents)): ?>
                <?php foreach ($recent_agents as $a): ?>
                  <tr>
                    <td><?php echo (int)$a['id']; ?></td>
                    <td><?php echo htmlspecialchars($a['username']); ?></td>
                    <td><span class="ta-password-chip"><?php echo htmlspecialchars(admin_display_password($a)); ?></span></td>
                    <td><?php echo htmlspecialchars($a['master_name'] ?? '-'); ?></td>
                    <td><?php echo (int)($a['players_count'] ?? 0); ?></td>
                    <td class="text-right">
                      <a class="btn btn-sm btn-outline-secondary" href="../players/?agent_id=<?php echo (int)$a['id']; ?>">Players</a>
                      <a class="btn btn-sm btn-outline-secondary" href="../my-account/?user_id=<?php echo (int)$a['id']; ?>">Sessions</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="6" class="text-center">No agents found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-6">
      <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div>
            <h5 class="mb-1">Latest Players</h5>
            <div class="text-body-secondary">Recently created players</div>
          </div>
          <a class="btn btn-outline-secondary" href="../players/">All Players</a>
        </div>
        <div class="card-body">
          <table class="table table-hover custom-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Player</th>
                <th>Password</th>
                <th>Agent</th>
                <th>Master</th>
                <th>Balance</th>
                <th>Status</th>
                <th class="text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($recent_players)): ?>
                <?php foreach ($recent_players as $p): ?>
                  <tr>
                    <td><?php echo (int)$p['id']; ?></td>
                    <td><?php echo htmlspecialchars($p['username']); ?></td>
                    <td><span class="ta-password-chip"><?php echo htmlspecialchars(admin_display_password($p)); ?></span></td>
                    <td><?php echo htmlspecialchars($p['agent_name'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($p['master_name'] ?? '-'); ?></td>
                    <td><?php echo number_format((float)($p['balance'] ?? 0), 2); ?></td>
                    <td><?php echo htmlspecialchars(strtoupper($p['status'] ?? 'active')); ?></td>
                    <td class="text-right">
                      <a class="btn btn-sm btn-outline-secondary" href="../players/?agent_id=<?php echo (int)($p['parent_id'] ?? 0); ?>">Open</a>
                      <a class="btn btn-sm btn-outline-secondary" href="../my-account/?user_id=<?php echo (int)$p['id']; ?>">Sessions</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="8" class="text-center">No players found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
<?php else: ?>
  <div class="card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div>
        <h5 class="mb-1"><?php echo htmlspecialchars(ucfirst($child_role)); ?> List</h5>
        <div class="text-body-secondary">Manage your downline</div>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-primary" href="../create-member/">Create <?php echo htmlspecialchars(ucfirst($child_role)); ?></a>
        <a class="btn btn-outline-secondary" href="../banking/">Banking</a>
      </div>
    </div>
    <div class="card-body">
      <table class="table table-hover custom-table">
        <thead>
          <tr>
            <th>Username</th>
            <th>Password</th>
            <th>Balance</th>
            <th>Credit Ref</th>
            <th>Exposure</th>
            <th>Rate</th>
            <th>Avail</th>
            <th>Status</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($children) > 0): ?>
            <?php foreach ($children as $child): ?>
              <?php
                $c_balance = $child['balance'] ?? $child['coins'] ?? 0;
                $c_credit = $child['credit_ref'] ?? 0;
                $c_exposure = $child['exposure'] ?? 0;
                $c_rate = $child['rate'] ?? 100;
                $c_avail = ($c_balance + $c_credit) - $c_exposure;
              ?>
              <tr>
                <td><span class="role-badge"><?php echo strtoupper(substr($child_role, 0, 1)); ?></span><?php echo htmlspecialchars($child['username']); ?></td>
                <td><span class="ta-password-chip"><?php echo htmlspecialchars(admin_display_password($child)); ?></span></td>
                <td><?php echo number_format((float)$c_balance, 2); ?></td>
                <td><?php echo number_format((float)$c_credit, 2); ?></td>
                <td class="text-danger"><?php echo number_format((float)$c_exposure, 2); ?></td>
                <td><?php echo number_format((float)$c_rate, 2); ?></td>
                <td><?php echo number_format((float)$c_avail, 2); ?></td>
                <td><?php echo htmlspecialchars(strtoupper($child['status'] ?? 'active')); ?></td>
                <td class="text-right">
                  <a href="#" class="btn btn-sm btn-outline-primary action-password" data-id="<?php echo (int)$child['id']; ?>" data-username="<?php echo htmlspecialchars($child['username']); ?>">Password</a>
                  <a href="#" class="btn btn-sm btn-outline-primary action-status" data-id="<?php echo (int)$child['id']; ?>" data-username="<?php echo htmlspecialchars($child['username']); ?>" data-status="<?php echo htmlspecialchars($child['status'] ?? 'active'); ?>">Status</a>
                  <a class="btn btn-sm btn-outline-secondary" href="../banking/?target_id=<?php echo (int)$child['id']; ?>">Banking</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="9" class="text-center">No users found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<!-- Password Modal -->
<div class="modal fade" id="passwordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white" style="background: #003366 !important;">
                <h5 class="modal-title">Change Password</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action_type" value="change_password">
                    <input type="hidden" name="target_id" id="pass_target_id">
                    <p>Changing password for: <strong id="pass_username"></strong></p>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: #003366; border-color: #003366;">Update Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Status Modal -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark" style="background: #ffcc00 !important;">
                <h5 class="modal-title">Change Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action_type" value="change_status">
                    <input type="hidden" name="target_id" id="status_target_id">
                    <p>Changing status for: <strong id="status_username"></strong></p>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="new_status" id="status_select" class="form-control">
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                            <option value="locked">Locked</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning font-weight-bold" style="background: #ffcc00; border-color: #ffcc00;">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var passwordModalEl = document.getElementById('passwordModal');
    var statusModalEl = document.getElementById('statusModal');
    var passwordModal = passwordModalEl ? new bootstrap.Modal(passwordModalEl) : null;
    var statusModal = statusModalEl ? new bootstrap.Modal(statusModalEl) : null;

    document.querySelectorAll('.action-password').forEach(function(el) {
        el.addEventListener('click', function(e) {
            e.preventDefault();
            var id = el.getAttribute('data-id');
            var username = el.getAttribute('data-username');
            var target = document.getElementById('pass_target_id');
            var label = document.getElementById('pass_username');
            if (target) target.value = id || '';
            if (label) label.textContent = username || '';
            if (passwordModal) passwordModal.show();
        });
    });

    document.querySelectorAll('.action-status').forEach(function(el) {
        el.addEventListener('click', function(e) {
            e.preventDefault();
            var id = el.getAttribute('data-id');
            var username = el.getAttribute('data-username');
            var status = el.getAttribute('data-status');
            var target = document.getElementById('status_target_id');
            var label = document.getElementById('status_username');
            var select = document.getElementById('status_select');
            if (target) target.value = id || '';
            if (label) label.textContent = username || '';
            if (select && status) select.value = status;
            if (statusModal) statusModal.show();
        });
    });
});
</script>

<?php require '../includes/footer.php'; ?>
