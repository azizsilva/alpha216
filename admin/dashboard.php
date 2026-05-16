<?php
header("Location: dashboard/");
exit;

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Determine child role
$child_role = '';
if ($role === 'admin') $child_role = 'master';
elseif ($role === 'master') $child_role = 'agent';
elseif ($role === 'agent') $child_role = 'player';

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

?>

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

<!-- Search & Filters -->
<div class="row mb-3 align-items-center">
    <div class="col-md-6 col-12 mb-2 mb-md-0">
        <div class="d-flex align-items-center">
            <span class="badge badge-primary p-2 mr-2" style="font-size: 14px; background: #003366;"><?php echo strtoupper($role == 'admin' ? 'MA' : ($role == 'master' ? 'AG' : 'PL')); ?></span>
            <span style="font-weight: bold; font-size: 16px; color: #333;"><?php echo $_SESSION['username']; ?></span>
        </div>
    </div>
    <div class="col-md-6 col-12 text-md-right">
        <div class="form-inline justify-content-md-end justify-content-start flex-wrap">
            <label class="mr-2 font-weight-bold d-none d-sm-block">Status :</label>
            <select class="form-control form-control-sm mr-2 mb-2 mb-sm-0" style="width: 100px;">
                <option>ACTIVE</option>
                <option>LOCKED</option>
            </select>
            <button class="btn btn-warning btn-sm mr-2 mb-2 mb-sm-0" style="background: #ffcc00; color: #000; font-weight: bold;">Active Players</button>
            <button class="btn btn-warning btn-sm mb-2 mb-sm-0" style="background: #ffcc00; color: #000; font-weight: bold;"><i class="fa fa-refresh"></i></button>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-12 text-right">
        <div class="form-inline float-md-right float-none">
            <input type="text" class="form-control form-control-sm mr-2 mb-2 mb-sm-0 w-100 w-sm-auto" placeholder="Search for...">
            <button class="btn btn-light btn-sm border w-100 w-sm-auto">Search Downline</button>
        </div>
    </div>
</div>

<!-- Data Table -->
<div class="card" style="border: none; box-shadow: none;">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover custom-table">
                <thead>
                    <tr>
                        <th>Username <i class="fa fa-sort"></i></th>
                        <th>$ <i class="fa fa-sort"></i></th>
                        <th>Balance <i class="fa fa-sort"></i></th>
                        <th>Credit Ref. <i class="fa fa-sort"></i></th>
                        <th>Ref P/L <i class="fa fa-sort"></i></th>
                        <th>Exposure <i class="fa fa-sort"></i></th>
                        <th>Rate <i class="fa fa-sort"></i></th>
                        <th>Avail Bal. <i class="fa fa-sort"></i></th>
                        <th>Player Bal. <i class="fa fa-sort"></i></th>
                        <th>Status <i class="fa fa-sort"></i></th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($children) > 0): ?>
                        <?php foreach ($children as $child): 
                            $c_balance = $child['balance'] ?? $child['coins'] ?? 0;
                            $c_credit = $child['credit_ref'] ?? 0;
                            $c_exposure = $child['exposure'] ?? 0;
                            $c_rate = $child['rate'] ?? 100;
                            $c_avail = ($c_balance + $c_credit) - $c_exposure; // Simplified logic
                        ?>
                        <tr>
                            <td>
                                <span class="role-badge"><?php echo strtoupper(substr($child_role, 0, 1)); ?></span>
                                <a href="#" style="color: #007bff; text-decoration: none;"><?php echo htmlspecialchars($child['username']); ?></a>
                            </td>
                            <td>TND</td>
                            <td>
                                <strong><?php echo number_format($c_balance, 0); ?></strong>
                                <a href="banking.php" title="Quick Transfer"><i class="fa fa-plus-square" style="color: #003366; cursor: pointer;"></i></a>
                            </td>
                            <td>
                                <a href="#" style="text-decoration: none; color: #007bff;"><?php echo number_format($c_credit, 0); ?></a>
                            </td>
                            <td>0</td> <!-- Ref P/L Placeholder -->
                            <td class="text-danger"><?php echo number_format($c_exposure, 0); ?></td>
                            <td><?php echo number_format($c_rate, 3); ?></td>
                            <td><?php echo number_format($c_avail, 0); ?></td>
                            <td>-</td>
                            <td class="text-center">
                                <?php if (($child['status'] ?? 'active') == 'active'): ?>
                                    <i class="fa fa-check-circle status-active" style="font-size: 18px;"></i>
                                <?php else: ?>
                                    <i class="fa fa-times-circle status-locked" style="font-size: 18px;"></i>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="#" class="text-dark mr-2"><i class="fa fa-cog"></i></a>
                                <a href="#" class="text-dark"><i class="fa fa-user"></i></a>
                                <a href="banking.php" class="text-dark ml-2" title="Banking"><i class="fa fa-bank"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="11" class="text-center">No users found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
