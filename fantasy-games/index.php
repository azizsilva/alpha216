<?php
$mk_fragment = isset($_GET['mk_fragment']);
if (!$mk_fragment) {
    require_once __DIR__ . '/../app/index.php';
    exit;
}
include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid" style="padding: 0; margin-top: 0; border-bottom: 2px solid #c37601;">
    <div class="row" style="margin: 0;">
        <div class="col-xs-12" style="padding: 0;">
            <img src="https://moneyking365.com/assets/images/fantasy-Games-Banner.png" onerror="this.src='https://moneyking365.com/assets/images/ace-casinobanner.jpg'" alt="Fantasy Games Banner" style="width: 100%; height: auto; display: block;">
        </div>
    </div>
</div>

<style>
  .mk-fantasy-tiles-row { margin-left: -8px; margin-right: -8px; }
  .mk-fantasy-tiles-col { padding-left: 8px; padding-right: 8px; }
  .mk-fantasy-tile {
    position: relative;
    width: 100%;
    border-radius: 2px;
    overflow: hidden;
    background: #111;
    border: 1px solid rgba(255,255,255,0.10);
    box-shadow: 0 10px 26px rgba(0,0,0,0.38);
  }
  .mk-fantasy-tile::before {
    content: "";
    display: block;
    padding-top: 42.64%;
  }
  @supports (aspect-ratio: 1 / 1) {
    .mk-fantasy-tile { aspect-ratio: 666 / 284; }
    .mk-fantasy-tile::before { display: none; }
  }
  .mk-fantasy-tile img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }
  .mk-fantasy-tile.mk-clickable { cursor: pointer; }
  .mk-fantasy-tile.mk-clickable:active { opacity: 0.98; }
</style>

<div class="container" style="margin-top: 14px;">
    <div class="row mk-fantasy-tiles-row">
        <div class="col-xs-12 col-sm-6 mk-fantasy-tiles-col" style="margin-bottom: 12px;">
            <div class="mk-fantasy-tile mk-clickable" onclick="launchGame('e391f7dbfbc9ded0ea4a487c39c1e8b8', 'Fantasy Games')">
                <img src="https://i.ibb.co/hJnz33kZ/debdfa33-4640-4aa0-933e-90acf3daf669.png" alt="Fantasy Banner">
            </div>
        </div>
        <div class="col-xs-12 col-sm-6 mk-fantasy-tiles-col" style="margin-bottom: 12px;">
            <div class="mk-fantasy-tile mk-clickable" onclick="launchGame('9b9cbc6675153399ca566c6ff725c947', 'Fantasy Games')">
                <img src="https://i.ibb.co/BHmHxPqw/7d77cb74-560a-4378-b843-9a8e1d50b3fb.png" alt="Fantasy Game">
            </div>
        </div>
    </div>
</div>

<script>
// Optional: add small preconnect on hover for faster launch
document.addEventListener('mouseover', function(e){
  var t = e.target && e.target.closest ? e.target.closest('.mk-fantasy-tile.mk-clickable') : null;
  if (!t) return;
  try {
    var img = t.querySelector('img');
    if (!img) return;
    var u = new URL(img.src);
    var link = document.createElement('link');
    link.rel = 'preconnect';
    link.href = u.origin;
    link.crossOrigin = 'anonymous';
    document.head.appendChild(link);
  } catch (er) {}
}, true);
</script>
<?php
include __DIR__ . '/../includes/footer.php';
?>
