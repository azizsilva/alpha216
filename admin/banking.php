<?php
header("Location: banking/");
exit;

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
                // Refresh Parent Balance
                $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $parent_balance = $stmt->fetchColumn();

                if ($type === 'deposit') {
                    // Parent -> Child
                    if ($role !== 'admin' && $parent_balance < $amount) {
                        throw new Exception("Insufficient balance.");
                    }
                    
                    if ($role !== 'admin') {
                        $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                        $stmt->execute([$amount, $user_id]);
                        $_SESSION['coins'] -= $amount;
                    }

                    $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                    $stmt->execute([$amount, $target_id]);

                } elseif ($type === 'withdrawal') {
                    // Child -> Parent
                    if ($target['balance'] < $amount) {
                        throw new Exception("User has insufficient balance.");
                    }

                    $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                    $stmt->execute([$amount, $target_id]);

                    if ($role !== 'admin') {
                        $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                        $stmt->execute([$amount, $user_id]);
                        $_SESSION['coins'] += $amount;
                    }
                }

                // Log Transaction
                $stmt = $pdo->prepare("INSERT INTO transactions (sender_id, receiver_id, amount, type, description) VALUES (?, ?, ?, ?, ?)");
                $desc = ucfirst($type) . " of $amount TND";
                $stmt->execute([$user_id, $target_id, $amount, $type, $desc]);

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
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center" style="background: #003366 !important;">
                <h5 class="mb-0">Banking</h5>
                <?php if ($role !== 'admin'): ?>
                    <span>Your Balance: <strong><?php echo number_format($_SESSION['coins'], 2); ?></strong></span>
                <?php else: ?>
                    <span>Your Balance: <strong>&infin;</strong></span>
                <?php endif; ?>
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

                <div class="table-responsive">
                    <table class="table table-bordered table-hover custom-table">
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
                                    <td style="min-width: 300px;">
                                        <form method="POST" class="form-inline">
                                            <input type="hidden" name="target_id" value="<?php echo $child['id']; ?>">
                                            <input type="number" name="amount" class="form-control form-control-sm mr-2" style="width: 120px;" placeholder="Amount" min="0.01" step="0.01" required>
                                            <button type="submit" name="type" value="deposit" class="btn btn-success btn-sm mr-1 font-weight-bold">D</button>
                                            <button type="submit" name="type" value="withdrawal" class="btn btn-danger btn-sm font-weight-bold">W</button>
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
</div>

<?php require 'includes/footer.php'; ?>
