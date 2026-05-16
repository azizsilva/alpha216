<?php
$admin_base = '../';
$base_path = 'https://tanitbet216.com/ui/www.moneyking365.com/';
require '../includes/db.php';
require '../includes/auth.php';
require_admin_login($admin_base);

$page_title = 'Risk Management';
require '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <h5 class="mb-1">Risk Management</h5>
                    <div class="text-body-secondary">Exposure and risk overview (placeholder)</div>
                </div>
            </div>
            <div class="card-body text-center py-5">
                <div class="text-body-secondary">No risk data available.</div>
            </div>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>
