<?php
header("Location: banking/");
exit;

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$child_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Verify ownership
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND parent_id = ?");
$stmt->execute([$child_id, $user_id]);
$child = $stmt->fetch();

if (!$child) {
    echo "<div class='alert alert-danger'>User not found or you do not have permission.</div>";
    require 'includes/footer.php';
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action']; // 'add' or 'deduct'
    $amount = floatval($_POST['amount']);

    if ($amount <= 0) {
        $error = "Amount must be positive.";
    } else {
        $pdo->beginTransaction();
        try {
            // Refresh balances
            $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $parent_balance = $stmt->fetchColumn();

            $stmt->execute([$child_id]);
            $child_balance = $stmt->fetchColumn();

            if ($action === 'add') {
                if ($role !== 'admin' && $parent_balance < $amount) {
                    throw new Exception("Insufficient balance.");
                }

                if ($role !== 'admin') {
                    $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                    $stmt->execute([$amount, $user_id]);
                    $_SESSION['coins'] -= $amount; // Update session
                }

                $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                $stmt->execute([$amount, $child_id]);
                $message = "Added $amount coins to " . htmlspecialchars($child['username']);

                // Log
                $stmt = $pdo->prepare("INSERT INTO transactions (sender_id, receiver_id, amount, type, description) VALUES (?, ?, ?, 'deposit', ?)");
                $stmt->execute([$user_id, $child_id, $amount, "Deposit of $amount TND"]);

            } elseif ($action === 'deduct') {
                if ($child_balance < $amount) {
                    throw new Exception("User has insufficient balance to deduct.");
                }

                $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                $stmt->execute([$amount, $child_id]);

                if ($role !== 'admin') {
                    $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                    $stmt->execute([$amount, $user_id]);
                    $_SESSION['coins'] += $amount;
                }
                $message = "Deducted $amount coins from " . htmlspecialchars($child['username']);

                // Log
                $stmt = $pdo->prepare("INSERT INTO transactions (sender_id, receiver_id, amount, type, description) VALUES (?, ?, ?, 'withdrawal', ?)");
                $stmt->execute([$user_id, $child_id, $amount, "Withdrawal of $amount TND"]);
            }

            $pdo->commit();
            // Refresh child data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$child_id]);
            $child = $stmt->fetch();

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary text-white" style="background: #003366 !important;">
                Manage Coins for <?php echo htmlspecialchars($child['username']); ?>
            </div>
            <div class="card-body">
                <p>Current Balance: <strong><?php echo number_format($child['balance'], 2); ?></strong></p>
                <hr>
                
                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label>Action</label>
                        <select name="action" class="form-control">
                            <option value="add">Add Coins (Deposit)</option>
                            <option value="deduct">Deduct Coins (Withdraw)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Amount</label>
                        <input type="number" name="amount" step="0.01" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block" style="background: #003366;">Submit Transaction</button>
                    <a href="dashboard.php" class="btn btn-secondary btn-block">Back to Dashboard</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
