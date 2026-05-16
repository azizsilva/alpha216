<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/db.php';

$asset_path = 'https://tanitbet216.com/ui/www.moneyking365.com/';
$is_logged_in = isset($_SESSION['user_id']);
$username = $is_logged_in ? ($_SESSION['username'] ?? 'User') : '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Royalwinbet</title>
  <link rel="icon" type="image/x-icon" href="https://tanitbet216.com/tanitbet.jpg">
  
  <!-- CSS Assets -->
  <link href="<?php echo $asset_path; ?>assets/css/bootstrap.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Work+Sans&display=swap" rel="stylesheet">
  <link href="<?php echo $asset_path; ?>assets/css/style.css" rel="stylesheet">
  <link href="<?php echo $asset_path; ?>assets/css/design-structure.css" rel="stylesheet">
  <link href="<?php echo $asset_path; ?>assets/css/style-whitelabale.css" rel="stylesheet">
  <link href="<?php echo $asset_path; ?>assets/css/score-theme.css" rel="stylesheet">
  <link href="<?php echo $asset_path; ?>assets/style-glob.css" rel="stylesheet">
  <link href="<?php echo $asset_path; ?>styles.447e2c5daf6415369575.css" rel="stylesheet">
  <link href="<?php echo $asset_path; ?>assets/owlcarousel/assets/owl.carousel.css" rel="stylesheet">
  <link href="<?php echo $asset_path; ?>assets/owlcarousel/assets/owl.theme.default.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.css" rel="stylesheet">

  <style>
      body {
          background-color: #000;
          color: #fff;
          font-family: 'Work Sans', sans-serif;
      }
      .modal-backdrop { z-index: 1040 !important; }
      .modal { z-index: 1050 !important; }
  </style>
</head>
<body class="bgColor">

<app-d1-landing-dashboard class="ng-star-inserted">
<div class="landingpage bgColor">
<app-caino-header>
<app-d1-casino-header class="ng-tns-c9-0 ng-star-inserted">
<div class="skin-1">
<div class="ng-tns-c9-0" header="">
<div class="ng-tns-c9-0" headerbg="">
<nav class="navbar navbar-default navbar-fixed-top custom-navbar isLoginFixedSidebar" style="z-index: 9999;">
<div class="container-fluid container navmain">
<div class="navbar-header" style="width: 100%;">
<button class="navbar-toggle collapsed" aria-expanded="false" data-target="#bs-example-navbar-collapse-1" data-toggle="collapse" type="button">
<span class="sr-only">Toggle navigation</span>
<span class="icon-bar"></span>
<span class="icon-bar"></span>
<span class="icon-bar"></span>
</button>
<div class="ng-tns-c9-0" style="display: flex; align-items: center;justify-content: space-between; width: 100%;">
<a class="navbar-brand" href="index.php">
<img class="ng-tns-c9-0" height="50" src="https://tanitbet216.com/tanitbet216.png">
</a>
<div class="header-front-search-filter navbar-nleft">
<div class="langHeader login-lng">
<div class="ng-tns-c9-0" style="position: relative;">
<select class="ng-tns-c9-0">
<option class="ng-tns-c9-0 ng-star-inserted" value="English">English</option>
<option class="ng-tns-c9-0 ng-star-inserted" value="Hindi">हिन्दी</option>
</select>
<i class="fa fa-caret-down" aria-hidden="true"></i>
</div>
</div>
<ul class="ng-tns-c9-0">
<li class="ng-tns-c9-0">
<ng2-completer class="ng-tns-c9-0" style="z-index: 9999999;">
<div class="completer-holder" ctrcompleter="">
<input class="completer-input" ctrinput="" type="search" placeholder="Search by Event/Game">
</div>
</ng2-completer>
</li>
</ul>
</div>
</div>
</div>

<button class="depositclass fillRedBtn btn ng-star-inserted depbtn ng-tns-c9-0">Deposit</button>
<div class="loginbox">
<?php if ($is_logged_in): ?>
    <!-- Logged In State -->
    <div class="ng-tns-c9-0 ng-star-inserted">
        <h5 class="ng-tns-c9-0" style="text-transform: uppercase;"><?php echo htmlspecialchars($username); ?></h5>
        <p class="ng-tns-c9-0" style="display: flex; font-weight: 600; font-size: 12px; width: 120px; justify-content: flex-end;">
            <span class="ng-tns-c9-0 ng-star-inserted">0.00</span>
            <span class="ng-tns-c9-0" style="width: 28px; color:#fff; text-transform: uppercase;"> TND </span>
        </p>
    </div>
    <div class="ng-tns-c9-0" id="profileMenu">
        <button class="ng-tns-c9-0 ng-star-inserted">
            <i class="fa fa-chevron-down"></i>
        </button>
    </div>
<?php else: ?>
    <!-- Logged Out State -->
    <button class="btn btn-warning" data-toggle="modal" data-target="#login" style="margin-top: 10px; font-weight: bold;">LOGIN</button>
<?php endif; ?>
</div>

</div>
</nav>
</div>
</div>
<div class="row" style="border: none;">
<ul class="menuiitemm">
<li class="ng-tns-c9-0 ng-star-inserted">
<div class="ng-tns-c9-0">
<img alt="Icon" src="<?php echo $asset_path; ?>assets/images/In Play.svg" style="margin-right: 6px; width: 18px;">
<a class="ng-tns-c9-0">In-Play</a>
</div>
</li>
<li class="ng-tns-c9-0 ng-star-inserted">
<div class="ng-tns-c9-0">
<img alt="Icon" src="<?php echo $asset_path; ?>assets/images/sportbook.svg" style="margin-right: 8px;width: 16px;">
<a class="ng-tns-c9-0">Sports</a>
</div>
</li>
<li class="ng-tns-c9-0 ng-star-inserted">
<div class="ng-tns-c9-0">
<img alt="Icon" src="<?php echo $asset_path; ?>assets/images/Live Casino.svg" style="margin-right: 8px; width: 16px;">
<a class="ng-tns-c9-0">Live Casino</a>
</div>
</li>
</ul>
</div>
</div>
</app-d1-casino-header>
</app-caino-header>

<!-- Spacer for fixed header -->
<div style="margin-top: 100px;"></div>
