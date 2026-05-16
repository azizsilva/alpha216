<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'player') {
    header("Location: index.php");
    exit;
}

// Refresh User Data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$_SESSION['coins'] = $user['balance'];

require_once 'includes/header.php'; 
?>

<!-- Main Content -->
<div class="container mk-home-container" style="margin-top: 30px;">
    <div class="row">
        <!-- Sidebar (Left) -->
        <div class="col-md-2 d-none d-md-block">
            <div class="list-group">
                <a href="#" class="list-group-item list-group-item-action active">All Sports</a>
                <a href="#" class="list-group-item list-group-item-action">Cricket <span class="badge badge-light float-right">12</span></a>
                <a href="#" class="list-group-item list-group-item-action">Soccer <span class="badge badge-light float-right">45</span></a>
                <a href="#" class="list-group-item list-group-item-action">Tennis <span class="badge badge-light float-right">8</span></a>
            </div>
        </div>

        <!-- Center Content -->
        <div class="col-md-7">
            <!-- Carousel Placeholder -->
            <div id="owl-carousel" class="owl-carousel owl-theme mb-3">
                <div class="item"><img src="<?php echo $asset_path; ?>assets/images/slider/1.jpg" alt="Banner 1" style="height:150px; background:#333; color:#fff; display:flex; align-items:center; justify-content:center;">Banner 1</div>
                <div class="item"><img src="<?php echo $asset_path; ?>assets/images/slider/2.jpg" alt="Banner 2" style="height:150px; background:#444; color:#fff; display:flex; align-items:center; justify-content:center;">Banner 2</div>
            </div>

            <!-- Live Matches -->
            <h5 class="mb-3">Live Matches</h5>
            
            <!-- Match 1 -->
            <div class="game-card">
                <div class="game-header">
                    <span>Cricket - T20 World Cup</span>
                    <span class="text-danger"><i class="fa fa-circle"></i> Live</span>
                </div>
                <div class="game-body">
                    <div class="match-info">
                        <div class="team">
                            <div class="team-name">India</div>
                            <small>145/3 (15.2)</small>
                        </div>
                        <div class="odds d-flex">
                            <div class="mx-1">
                                <div class="odds-btn text-primary">1.85</div>
                                <small class="d-block text-center">Back</small>
                            </div>
                            <div class="mx-1">
                                <div class="odds-btn text-danger">1.86</div>
                                <small class="d-block text-center">Lay</small>
                            </div>
                        </div>
                        <div class="team text-right">
                            <div class="team-name">Australia</div>
                            <small>Yet to Bat</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Match 2 -->
            <div class="game-card">
                <div class="game-header">
                    <span>Soccer - Premier League</span>
                    <span class="text-danger"><i class="fa fa-circle"></i> Live</span>
                </div>
                <div class="game-body">
                    <div class="match-info">
                        <div class="team">
                            <div class="team-name">Man Utd</div>
                            <small>1 - 0</small>
                        </div>
                        <div class="odds d-flex">
                            <div class="mx-1">
                                <div class="odds-btn text-primary">2.10</div>
                            </div>
                            <div class="mx-1">
                                <div class="odds-btn text-secondary">3.40</div>
                            </div>
                            <div class="mx-1">
                                <div class="odds-btn text-danger">4.50</div>
                            </div>
                        </div>
                        <div class="team text-right">
                            <div class="team-name">Chelsea</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Sidebar (Bet Slip) -->
        <div class="col-md-3">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    Bet Slip
                </div>
                <div class="card-body text-center">
                    <p class="text-muted">Click on odds to add selections to the betslip.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
