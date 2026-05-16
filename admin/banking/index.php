<?php
$admin_base = '../';
$base_path = 'https://tanitbet216.com/ui/www.moneyking365.com/';
require '../includes/db.php';
require '../includes/auth.php';
require_admin_login($admin_base);

$page_title = 'Banking';
require '../includes/header.php';

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Determine child role
$child_role = admin_child_role($role);

// Handle Transaction
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_id = $_POST['target_id'] ?? 0;
    $amount = floatval($_POST['amount'] ?? 0);
    $type = $_POST['type'] ?? ''; // 'deposit' or 'withdrawal'
    $password = $_POST['password'] ?? ''; // Security check

    if ($amount <= 0) {
        $error = "Amount must be positive.";
    } elseif (!in_array($type, ['deposit', 'withdrawal'])) {
        $error = "Invalid transaction type.";
    } else {
        // Verify target
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND parent_id = ?");
        $stmt->execute([$target_id, $user_id]);
        $target = $stmt->fetch();

        if ($target) {
            $pdo->beginTransaction();
            try {
                $lock_ids = [(int)$user_id, (int)$target_id];
                sort($lock_ids);
                $stmt = $pdo->prepare("SELECT id, balance FROM users WHERE id IN (?, ?) FOR UPDATE");
                $stmt->execute($lock_ids);
                $locked_users = $stmt->fetchAll();
                $balances = [];
                foreach ($locked_users as $locked_user) {
                    $balances[(int)$locked_user['id']] = (float)$locked_user['balance'];
                }
                if (!isset($balances[(int)$user_id], $balances[(int)$target_id])) {
                    throw new Exception("User not found or permission denied.");
                }

                $parent_balance = $balances[(int)$user_id];
                $target_balance = $balances[(int)$target_id];
                $new_parent_balance = $parent_balance;
                $new_target_balance = $target_balance;

                if ($type === 'deposit') {
                    // Parent -> Child
                    if ($role !== 'admin' && $parent_balance < $amount) {
                        throw new Exception("Insufficient balance.");
                    }

                    if ($role !== 'admin') {
                        $new_parent_balance = $parent_balance - $amount;
                    }

                    $new_target_balance = $target_balance + $amount;
                    $sender_id = $user_id;
                    $receiver_id = $target_id;
                    $desc = "Deposit $amount TND";

                } elseif ($type === 'withdrawal') {
                    // Child -> Parent
                    if ($target_balance < $amount) {
                        throw new Exception("User has insufficient balance.");
                    }

                    $new_target_balance = $target_balance - $amount;

                    if ($role !== 'admin') {
                        $new_parent_balance = $parent_balance + $amount;
                    }

                    $sender_id = $target_id;
                    $receiver_id = $user_id;
                    $desc = "Withdrawal $amount TND";
                }

                // Insert the ledger row before setting final balances so any DB-side transaction triggers cannot double-credit users.
                $stmt = $pdo->prepare("INSERT INTO transactions (sender_id, receiver_id, amount, type, description) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$sender_id, $receiver_id, $amount, $type, $desc]);

                $stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
                $stmt->execute([$new_parent_balance, $user_id]);
                $stmt->execute([$new_target_balance, $target_id]);

                if ($role !== 'admin') {
                    $_SESSION['coins'] = $new_parent_balance;
                }

                $pdo->commit();
                $message = "Transaction successful: $desc";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = $e->getMessage();
            }
        } else {
            $error = "User not found or permission denied.";
        }
    }
}

// Fetch children
$children = [];
if ($child_role) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE parent_id = ? AND role = ?");
    $stmt->execute([$user_id, $child_role]);
    $children = $stmt->fetchAll();
}

$stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$my_balance_db = (float)$stmt->fetchColumn();
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <h5 class="mb-1">Banking</h5>
                    <div class="text-body-secondary">
                        <?php echo htmlspecialchars(strtoupper($role)); ?> → <?php echo htmlspecialchars(strtoupper($child_role)); ?>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="text-body-secondary">Available:</span>
                    <span class="badge bg-label-primary">
                        <?php if ($role === 'admin'): ?>
                            &infin;
                        <?php else: ?>
                            <?php echo number_format($my_balance_db, 2); ?> TND
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <table class="table table-hover custom-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Balance</th>
                                <th>Rate</th>
                                <th>Deposit / Withdraw</th>
                                <th>Credit Ref</th>
                                <th>Exposure</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($children) > 0): ?>
                                <?php foreach ($children as $child): ?>
                                <tr>
                                    <td>
                                        <span class="role-badge"><?php echo strtoupper(substr($child_role, 0, 1)); ?></span>
                                        <?php echo htmlspecialchars($child['username']); ?>
                                    </td>
                                    <td><strong><?php echo number_format($child['balance'], 2); ?></strong></td>
                                    <td><?php echo number_format($child['rate'], 2); ?></td>
                                    <td style="min-width: 360px;">
                                        <form method="POST" class="d-flex align-items-center gap-2 flex-wrap">
                                            <input type="hidden" name="target_id" value="<?php echo $child['id']; ?>">
                                            <div class="input-group input-group-sm" style="width: 200px;">
                                                <span class="input-group-text">TND</span>
                                                <input type="number" name="amount" class="form-control" placeholder="Amount" min="0.01" step="0.01" required>
                                            </div>
                                            <div class="btn-group btn-group-sm" role="group" aria-label="transfer">
                                                <button type="submit" name="type" value="deposit" class="btn btn-success font-weight-bold">Deposit</button>
                                                <button type="submit" name="type" value="withdrawal" class="btn btn-danger font-weight-bold">Withdraw</button>
                                            </div>
                                        </form>
                                    </td>
                                    <td><?php echo number_format($child['credit_ref'], 2); ?></td>
                                    <td class="text-danger"><?php echo number_format($child['exposure'], 2); ?></td>
                                    <td>
                                        <?php echo strtoupper($child['status']); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center">No users found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
            </div>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>
