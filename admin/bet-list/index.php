<?php
$admin_base = '../';
$base_path = 'https://tanitbet216.com/ui/www.moneyking365.com/';
require '../includes/db.php';
require '../includes/auth.php';
require_admin_login($admin_base);

$page_title = 'Reports';
require '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <h5 class="mb-1">Bet List</h5>
                    <div class="text-body-secondary">Search and filter bets</div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary btn-sm" type="button" disabled>Export</button>
                </div>
            </div>
            <div class="card-body">
                <form class="row g-3 align-items-end mb-3">
                    <div class="col-12 col-md-3">
                        <label class="form-label">Sport</label>
                        <select class="form-select">
                            <option>All Sports</option>
                            <option>Cricket</option>
                            <option>Football</option>
                            <option>Tennis</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Status</label>
                        <select class="form-select">
                            <option>Settled</option>
                            <option>Unsettled</option>
                            <option>Void</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Search</label>
                        <input class="form-control" placeholder="Bet ID / Username / Event" />
                    </div>
                    <div class="col-12 col-md-3 d-flex gap-2">
                        <button class="btn btn-primary w-100" type="button">Filter</button>
                        <button class="btn btn-outline-secondary w-100" type="button">Reset</button>
                    </div>
                </form>
                
                    <table class="table table-hover custom-table">
                        <thead>
                            <tr>
                                <th>Bet ID</th>
                                <th>Event</th>
                                <th>Market</th>
                                <th>Selection</th>
                                <th>Odds</th>
                                <th>Stake</th>
                                <th>P/L</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <div class="mk-empty-state"><img src="https://moneyking365.com/assets/images/norecode.png" alt="No records"></div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
            </div>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>
