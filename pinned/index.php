<?php
$mk_fragment = isset($_GET['mk_fragment']);
if (!$mk_fragment) {
    require_once __DIR__ . '/../app/index.php';
    exit;
}
require_once __DIR__ . '/../includes/header.php';
?>

<script>
try { document.body.classList.add('mk-pinned-mode'); } catch (e) {}
</script>

<section class="mk-pinned-menu-page">
  <div class="mk-pinned-menu-list">
    <a class="mk-pinned-menu-item" href="#" data-game-id="8a704858d5deb4af1ddc722092ac7614" onclick="launchGame('8a704858d5deb4af1ddc722092ac7614', 'In-Play'); return false;">
      <i class="fa fa-clock-o"></i>
      <span data-translate="in_play">IN-PLAY</span>
      <i class="fa fa-chevron-right mk-pinned-arrow"></i>
    </a>
    <a class="mk-pinned-menu-item" href="<?php echo $base_url; ?>casino-games/">
      <i class="fa fa-diamond"></i>
      <span data-translate="ace_casino">ACE CASINO</span>
      <i class="fa fa-chevron-right mk-pinned-arrow"></i>
    </a>
    <a class="mk-pinned-menu-item" href="<?php echo $base_url; ?>casino-games/live-casino/">
      <i class="fa fa-dot-circle-o"></i>
      <span data-translate="live_casino">LIVE CASINO</span>
      <i class="fa fa-chevron-right mk-pinned-arrow"></i>
    </a>
    <a class="mk-pinned-menu-item" href="<?php echo $base_url; ?>sports/">
      <i class="fa fa-futbol-o"></i>
      <span data-translate="sports">SPORTS</span>
      <i class="fa fa-chevron-right mk-pinned-arrow"></i>
    </a>
    <a class="mk-pinned-menu-item" href="#" data-game-id="8a704858d5deb4af1ddc722092ac7614" onclick="launchGame('8a704858d5deb4af1ddc722092ac7614', 'Sports Book'); return false;">
      <i class="fa fa-book"></i>
      <span data-translate="sports_book">SPORTS BOOK</span>
      <i class="fa fa-chevron-right mk-pinned-arrow"></i>
    </a>
    <a class="mk-pinned-menu-item" href="<?php echo $base_url; ?>casino-games/virtual-sports/">
      <i class="fa fa-desktop"></i>
      <span data-translate="virtual_sports">VIRTUAL SPORTS</span>
      <i class="fa fa-chevron-right mk-pinned-arrow"></i>
    </a>
    <a class="mk-pinned-menu-item" href="<?php echo $base_url; ?>casino-games/slot-games/">
      <i class="fa fa-th"></i>
      <span data-translate="slot_games">SLOT GAMES</span>
      <i class="fa fa-chevron-right mk-pinned-arrow"></i>
    </a>
  </div>
</section>

<style>
  .mk-pinned-menu-page {
    min-height: calc(100vh - 153px);
    padding: 0 0 76px;
    background: #000;
  }
  .mk-pinned-menu-list {
    width: min(100%, 360px);
    margin: 0;
    background: #000;
  }
  .mk-pinned-menu-item {
    height: 44px;
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 0 18px 0 18px;
    border-bottom: 1px solid #191919;
    color: #fff !important;
    font-size: 14px;
    font-weight: 900;
    letter-spacing: .01em;
    text-decoration: none !important;
    text-transform: uppercase;
  }
  .mk-pinned-menu-item > i:first-child {
    width: 18px;
    flex: 0 0 18px;
    color: #c37601;
    font-size: 15px;
    text-align: center;
  }
  .mk-pinned-menu-item span {
    flex: 1 1 auto;
  }
  .mk-pinned-arrow {
    margin-left: auto;
    flex: 0 0 auto;
    color: #c37601;
    font-size: 13px;
  }
  .mk-pinned-menu-item:hover,
  .mk-pinned-menu-item:focus {
    background: #080808;
    color: #fff !important;
  }
  @media (max-width: 767px) {
    .mk-pinned-menu-page {
      min-height: calc(100dvh - 120px);
      padding-top: 60px;
    }
    .mk-pinned-menu-list {
      width: 100%;
    }
  }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
