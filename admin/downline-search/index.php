<?php
$admin_base = '../';
$base_path = 'https://tanitbet216.com/ui/www.moneyking365.com/';
require '../includes/db.php';
require '../includes/auth.php';
require_admin_login($admin_base);

$page_title = 'Downline Search';
require '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <h5 class="mb-1">Downline Search</h5>
                    <div class="text-body-secondary">Search username within your downline</div>
                </div>
            </div>
            <div class="card-body">
                <form action="../dashboard/" method="GET" class="row g-3 align-items-end justify-content-center">
                    <div class="col-12 col-md-6">
                        <label class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-search"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Search Username..." required>
                            <button class="btn btn-primary" type="submit">Search</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>
