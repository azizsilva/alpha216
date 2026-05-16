<?php
header("Location: create-member/");
exit;

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$role = $_SESSION['role'];
$allowed_create = false;
$target_role = '';

if ($role === 'admin') {
    $allowed_create = true;
    $target_role = 'master';
} elseif ($role === 'master') {
    $allowed_create = true;
    $target_role = 'agent';
} elseif ($role === 'agent') {
    $allowed_create = true;
    $target_role = 'player';
}

if (!$allowed_create) {
    echo "<div class='alert alert-danger'>You do not have permission to create users.</div>";
    require 'includes/footer.php';
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $credit_ref = floatval($_POST['credit_ref'] ?? 0);
    $rate = floatval($_POST['rate'] ?? 100);

    if (empty($username) || empty($password)) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if username exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = "Username already exists.";
        } else {
            // Create User
            $hashed_password = md5($password);
            // Default balance is 0. Credit Ref is the limit/reference.
            $stmt = $pdo->prepare("INSERT INTO users (username, password, password_text, role, parent_id, balance, credit_ref, rate, exposure, status) VALUES (?, ?, ?, ?, ?, 0, ?, ?, 0, 'active')");
            if ($stmt->execute([$username, $hashed_password, $password, $target_role, $_SESSION['user_id'], $credit_ref, $rate])) {
                $message = "User created successfully!";
            } else {
                $error = "Error creating user.";
            }
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header" style="background: #003366; color: #fff;">
                <h5 class="mb-0">Add New Member (<?php echo ucfirst($target_role); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Username</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Partnership Rate (%)</label>
                                <input type="number" name="rate" class="form-control" value="100" step="0.1" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Confirm Password</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Credit Reference (Limit)</label>
                                <input type="number" name="credit_ref" class="form-control" value="0">
                                <small class="text-muted">Initial credit limit for the user.</small>
                            </div>
                        </div>
                        <!-- Additional fields can be added here -->
                    </div>

                    <hr>
                    <button type="submit" class="btn btn-primary" style="background: #003366; border-color: #003366;">Create Member</button>
                    <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
