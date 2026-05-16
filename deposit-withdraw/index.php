<?php
$mk_fragment = isset($_GET['mk_fragment']);
if (!$mk_fragment) {
    require_once __DIR__ . '/../app/index.php';
    exit;
}
require_once __DIR__ . '/../includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../');
    exit;
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

$user_id = (int)$_SESSION['user_id'];
$user_banks = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM user_withdraw_banks WHERE user_id=? AND enabled=1 ORDER BY bank_slot ASC");
    $stmt->execute([$user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as $r) {
        $slot = (int)($r['bank_slot'] ?? 0);
        if (in_array($slot, [1,2,3], true)) $user_banks[$slot] = $r;
    }
} catch (Exception $e) {
    $user_banks = [];
}

$bank_names = [
    'Axis Bank',
    'Bank of Baroda',
    'Bank of India',
    'Canara Bank',
    'Central Bank of India',
    'Federal Bank',
    'HDFC Bank',
    'ICICI Bank',
    'IDBI Bank',
    'IDFC FIRST Bank',
    'Indian Bank',
    'Indian Overseas Bank',
    'IndusInd Bank',
    'Kotak Mahindra Bank',
    'Punjab National Bank',
    'State Bank of India',
    'UCO Bank',
    'Union Bank of India',
    'Yes Bank',
    'AU Small Finance Bank',
    'Bandhan Bank',
    'RBL Bank',
    'South Indian Bank',
    'Karnataka Bank',
    'Karur Vysya Bank',
    'City Union Bank',
    'DCB Bank',
    'Bank of Maharashtra',
    'Punjab & Sind Bank',
    'Jammu & Kashmir Bank',
    'HSBC',
    'Citibank',
    'Standard Chartered Bank',
];
sort($bank_names, SORT_NATURAL | SORT_FLAG_CASE);

$terms_title = 'Bonus Terms and Conditions';
$terms_html = <<<'HTML'
<p><b>Rolling:</b> <span>Rolling calculated daily 6-7am.</span><br>
To make things simpler for our players our rolling conditions and methods are designed and defined as follows When calculating the rolling amount Your profit/loss bet or stake whichever is lower is calculated as rolling:<br>
For example: You have taken 10% bonus on Rs100. You will be getting Rs 110 in your account, out of which Rs 10 will be in your bonus wallet That amount you can only withdraw after you completed the rolling condition of 10x. Which means you will have to play a stake of Rs 100 x 10 = Rs 1000.<br>
<b>Example 1:</b> A bet of Rs 100 on the odds of 1.30 and 1.32 If a player backs at: 1.30, The winning amount of Rs 30 will be considered towards rolling amount whereas If a player lays at 1:32, the winning amount of Rs 32 will be considered towards rolling amount.<br>
<b>Example 2:</b> A bet of Rs 100 on the odds of 1.98 and 2 If a player backs at 1.98, the winning amount of Rs 98 will be considered towards rolling amount whereas If a player lays at 2, the winning amount of Rs 100 will be considered towards rolling amount.<br>
<b>Example 3:</b> A bet of Rs 100 on the odds of 5.00 and 6.00 If a player backs at 5.00, the winning amount will be Rs 400 and stake would be Rs 100 hence your stake of Rs 100 will be considered towards rolling amount</p>
<p><b>1.</b> The bonus amount credited to the customer's wallet will depend upon the rolling plan customer opted for.</p>
<p><b>2.</b> The bonus will be credited to the customer's bonus wallet account automatically after Deposit but will not be activated unless the rolling conditions are completed.</p>
<p><b>3.</b> Withdrawal of funds from the customer account will only be possible after the rolling conditions are met.</p>
<p><b>4.</b> The number of times the amount is to be rolled will depend upon the bonus plan opted by the player.</p>
<p><b>5.</b> Bonus validity for rolling is 7 days and after the bonus will no longer be valid for the player.</p>
<p><b>6.</b> No new bonus will be credited unless the previous bonus is expired or used.</p>
<p><b>7.</b> The offer is not valid in conjunction with any other promotions or special offers.</p>
<p><b>8.</b> If the company suspects a customer misusing the bonus, foul play, and/or participation in strategies that the company in its sole discretion deems to be abusive the Company reserves the right to apply special wagering requirements to such customers, including bonus cancellation, without any explanation.</p>
<p><b>9.</b> Only one bonus is allowed per customer, per family, per address, per shared computer and shared IP address, and per any account details including e-mail address, bank account details, credit card information, and payment system account number.</p>
<p><b>10.</b> Any misuse of this bonus offer will lead to the cancelation of the bonus and all bonus winnings, or to the closure of the account.</p>
<p><b>11.</b> The offer is only available to customers with one user account. The company has the right to amend the terms of the offer, cancel or renew the offer, or refuse to allow participation at any time without prior notice.</p>
<p><b>12.</b> The bonus is deemed to have been wagered only after all the bets for the specified amount have been settled.</p>
<p><b>Your Free spin bonus will only be active when you play all your free spin.</b></p>
HTML;

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/profile-header.php';
?>

<?php
$mk_user_banks_out = [];
for ($slot = 1; $slot <= 3; $slot++) {
    $b = $user_banks[$slot] ?? null;
    if (!is_array($b)) continue;
    $mk_user_banks_out[(string)$slot] = [
        'bank_slot' => (int)($b['bank_slot'] ?? $slot),
        'bank_name' => (string)($b['bank_name'] ?? ''),
        'ifsc_swift' => (string)($b['ifsc_swift'] ?? ''),
        'account_no' => (string)($b['account_no'] ?? ''),
        'account_holder' => (string)($b['account_holder'] ?? ''),
    ];
}
?>
<script>
  document.body.classList.add('mk-account-mode');
  window.__MK_BUILD = 'dw-20260322-1';
  window.MK_WD_BANKS = <?php echo json_encode($mk_user_banks_out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  window.MK_BANK_NAMES = <?php echo json_encode($bank_names, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>

<div class="mk-account-page">
  <div class="mk-account-layout">
    <aside class="mk-account-sidebar">
      <div class="mk-side-title" data-translate="profile">PROFILE</div>
      <ul class="mk-side-menu">
        <li><a href="<?php echo htmlspecialchars($base_url); ?>account-details/"><i class="fa fa-user"></i> <span data-translate="account_detail">ACCOUNT DETAILS</span></a></li>
        <li><a href="<?php echo htmlspecialchars($base_url); ?>account-statement/"><i class="fa fa-file-text-o"></i> <span data-translate="account_statement">ACCOUNT STATEMENT</span></a></li>
        <li><a href="#"><i class="fa fa-university"></i> <span data-translate="bank_transfer">BANK TRANSFER</span></a></li>
        <li class="active"><a href="<?php echo htmlspecialchars($base_url); ?>deposit-withdraw/"><i class="fa fa-exchange"></i> <span data-translate="deposit_withdraw">DEPOSIT AND WITHDRAW</span></a></li>
        <li><a href="#"><i class="fa fa-line-chart"></i> <span data-translate="profit_loss">PROFIT AND LOSS</span></a></li>
        <li><a href="<?php echo htmlspecialchars($base_url); ?>bet-history/"><i class="fa fa-history"></i> <span data-translate="bet_history">BET HISTORY</span></a></li>
        <li><a href="#"><i class="fa fa-list"></i> <span data-translate="activity_log">ACTIVITY LOG</span></a></li>
        <li><a href="#"><i class="fa fa-bell-o"></i> <span data-translate="notification_history">NOTIFICATION HISTORY</span></a></li>
        <li><a href="#"><i class="fa fa-gift"></i> <span data-translate="bonus_history">BONUS HISTORY</span></a></li>
      </ul>
    </aside>

    <main class="mk-account-content">
      <div class="mk-dw-shell">
        <div class="mk-dw-card">
          <div class="mk-dw-tabs">
            <button class="mk-dw-tab active" type="button" data-tab="deposit">DEPOSIT</button>
            <button class="mk-dw-tab" type="button" data-tab="withdraw">WITHDRAW</button>
          </div>

          <div class="mk-dw-body">
            <div class="mk-dw-pane" data-pane="deposit">
              <div id="mkDwStep1" class="mk-dw-step mk-dw-step1">
                <div class="mk-dw-note">
                  <span>Please enter amount to view bank details</span><br>
                  <span>कृपया बैंक विवरण देखने के लिए राशि दर्ज करें</span>
                </div>

                <div class="mk-dw-amount">
                  <div class="mk-dw-amount-label">Enter Amount</div>
                  <input id="mkDwAmount" class="mk-dw-amount-input" type="number" min="1" step="0.01" placeholder="Enter Amount" inputmode="decimal">
                  <div class="mk-dw-quick">
                    <?php foreach ([100,200,500,1000,2000,5000] as $q): ?>
                      <button type="button" class="mk-dw-quick-btn" data-add="<?php echo (int)$q; ?>">+<?php echo (int)$q; ?></button>
                    <?php endforeach; ?>
                  </div>
                  <div id="mkDwStepMsg" class="mk-dw-stepmsg" style="display:none;"></div>
                </div>

                <button id="mkDwNext" class="mk-dw-next" type="button">NEXT STEP</button>
                <button class="mk-dw-terms mk-terms-link" type="button">Terms and Conditions</button>
              </div>

              <div id="mkDwStep2" class="mk-dw-step mk-dw-step2">
                <div class="mk-dw-step2-top">
                  <div class="mk-dw-summary">
                    <span>Amount</span>
                    <strong id="mkDwAmountLabel">0</strong>
                  </div>
                  <button id="mkDwBack" class="mk-dw-back" type="button">CHANGE</button>
                </div>
                <div class="mk-dw-amountbar">
                  <input id="mkDwAmountBar" type="text" value="" readonly>
                </div>

                <div class="mk-dw-merchant-row">
                  <button type="button" class="mk-dw-merchant" data-merchant="upi" aria-label="UPI">
                    <img src="https://moneyking365.com/assets/images/UPI.png" alt="UPI">
                  </button>
                  <button type="button" class="mk-dw-merchant" data-merchant="crypto" aria-label="Crypto">
                    <img src="https://moneyking365.com/assets/images/CRYPTO.png" alt="Crypto">
                  </button>
                  <?php if (!empty($web_settings['whatsapp_link']) && $web_settings['whatsapp_link'] !== '#'): ?>
                    <a class="mk-dw-merchant" href="<?php echo htmlspecialchars((string)$web_settings['whatsapp_link']); ?>" target="_blank" rel="noopener" aria-label="WhatsApp">
                      <img src="https://moneyking365.com/assets/images/whatsappsupport.png" alt="WhatsApp">
                    </a>
                  <?php else: ?>
                    <a class="mk-dw-merchant" href="https://wa.me/" target="_blank" rel="noopener" aria-label="WhatsApp">
                      <img src="https://moneyking365.com/assets/images/whatsappsupport.png" alt="WhatsApp">
                    </a>
                  <?php endif; ?>
                </div>

                <select id="mkDwMethodId" class="mk-dw-select" style="display:none;">
                  <option value="">Select</option>
                  <?php foreach (($player_deposit_methods ?? []) as $mOpt): ?>
                    <?php
                      $chOpt = (string)($mOpt['channel'] ?? '');
                      $dOpt = [];
                      if (!empty($mOpt['details_json'])) {
                        $tmp = json_decode((string)$mOpt['details_json'], true);
                        if (is_array($tmp)) $dOpt = $tmp;
                      }
                      $labelOpt = (string)($mOpt['label'] ?? '');
                      $addrOpt = (string)($dOpt['wallet_number'] ?? ($dOpt['upi_id'] ?? ($dOpt['account_number'] ?? '')));
                      $netOpt = (string)($dOpt['wallet_network'] ?? '');
                      $qrOpt = trim((string)($dOpt['qr_path'] ?? ($dOpt['qr_url'] ?? '')));
                      $qrHref = $qrOpt;
                      $qrAlt = '';
                      if ($qrHref !== '' && !preg_match('#^https?://#i', $qrHref) && $qrHref[0] !== '/') {
                        $qrAlt = rtrim($base_url, '/') . '/admin/' . $qrHref;
                        $qrHref = $base_url . $qrHref;
                      }
                      $optText = $labelOpt !== '' ? $labelOpt : strtoupper($chOpt);
                      if ($chOpt === 'wallet' && $netOpt !== '') $optText = strtoupper($netOpt);
                    ?>
                    <option
                      value="<?php echo (int)$mOpt['id']; ?>"
                      data-channel="<?php echo htmlspecialchars($chOpt); ?>"
                      data-network="<?php echo htmlspecialchars($netOpt); ?>"
                      data-addr="<?php echo htmlspecialchars($addrOpt); ?>"
                      data-qr="<?php echo htmlspecialchars($qrHref); ?>"
                      data-qr-alt="<?php echo htmlspecialchars($qrAlt); ?>"
                      data-label="<?php echo htmlspecialchars($labelOpt); ?>"
                      data-upi="<?php echo htmlspecialchars((string)($dOpt['upi_id'] ?? '')); ?>"
                      data-holder="<?php echo htmlspecialchars((string)($dOpt['holder_name'] ?? '')); ?>"
                      data-bank="<?php echo htmlspecialchars((string)($dOpt['bank_name'] ?? '')); ?>"
                      data-branch="<?php echo htmlspecialchars((string)($dOpt['branch'] ?? '')); ?>"
                      data-acname="<?php echo htmlspecialchars((string)($dOpt['account_name'] ?? '')); ?>"
                      data-acno="<?php echo htmlspecialchars((string)($dOpt['account_number'] ?? '')); ?>"
                      data-ifsc="<?php echo htmlspecialchars((string)($dOpt['ifsc'] ?? '')); ?>"
                      data-wallet="<?php echo htmlspecialchars((string)($dOpt['wallet_name'] ?? '')); ?>"
                      data-instructions="<?php echo htmlspecialchars((string)($dOpt['instructions'] ?? '')); ?>"
                      data-notes="<?php echo htmlspecialchars((string)($dOpt['notes'] ?? '')); ?>"
                      data-url="<?php echo htmlspecialchars((string)($dOpt['url'] ?? '')); ?>"
                    >
                      <?php echo htmlspecialchars($optText); ?>
                    </option>
                  <?php endforeach; ?>
                </select>

                <div id="mkDwMethodCards" class="mk-dw-methods"></div>

                <h5 id="mkDwDetailsTitle" class="mk-dw-qrtitle">QR CODE FOR PAYMENT</h5>
                <div class="mk-ui-divider"></div>

                <div id="mkDwUpiBox" class="mk-dw-upibox" style="display:none;"></div>
                <div id="mkDwBankBox" class="mk-dw-bankbox" style="display:none;"></div>
                <div id="mkDwNoteBox" class="mk-dw-notebox" style="display:none;"></div>

                <div id="mkDwQrBox" class="mk-dw-qrbox">
                  <div class="mk-ui-qr">
                    <div class="mk-ui-scanbox">
                      <img id="mkDwQrImg" class="mk-ui-scanimg" src="" alt="scan-code">
                    </div>
                    <div class="mk-ui-qr-right">
                      <label id="mkDwRightLabel" class="mk-ui-qrlabel">Crypto Currency</label>
                      <span id="mkDwNetworkChip" class="mk-ui-accountName">-</span>
                      <div id="mkDwAddrText" class="mk-ui-address" style="display:none;"></div>
                      <button id="mkDwCopy" class="mk-ui-btn mk-ui-btn-outline" type="button">
                        <i class="fa fa-files-o" aria-hidden="true"></i>
                        Copy Address
                      </button>
                      <button id="mkDwDownload" class="mk-ui-btn mk-ui-btn-primary" type="button">
                        <i class="fa fa-download" aria-hidden="true"></i>
                        Download QR
                      </button>
                    </div>
                  </div>
                </div>

                <div class="mk-ui-submit">
                  <input id="mkDwTxnId" class="mk-ui-txn" type="text" maxlength="100" autocomplete="off" placeholder="ENTER TRANSACTION UPI / UTR / REFERENCE NO.">

                  <input id="mkDwProof" class="mk-ui-proof" type="file" accept="image/png,image/jpeg,image/webp">
                  <label class="mk-ui-attach" for="mkDwProof">
                    <span id="mkDwProofLabel">ATTACH SCREENSHOT</span>
                    <i class="fa fa-camera" aria-hidden="true"></i>
                  </label>

                  <button id="mkDwSubmit" class="mk-ui-deposit" type="button">DEPOSIT</button>
                  <div id="mkDwMsg" class="mk-dw-msg" style="display:none;"></div>
                </div>
              </div>
            </div>

            <div class="mk-dw-pane" data-pane="withdraw" style="display:none;">
              <div class="mk-wd-wrap">
                <div class="mk-wd-topnote">Kindly check the bank details before withdrawal</div>
                <div id="mkWdListView">
                  <div class="mk-wd-head">Choose a bank to Withdraw into</div>

                  <div class="mk-wd-list">
                    <?php for ($slot = 1; $slot <= 3; $slot++): ?>
                      <?php $b = $user_banks[$slot] ?? null; ?>
                      <button type="button" class="mk-wd-item mk-wd-slot <?php echo is_array($b) ? '' : 'mk-wd-empty'; ?>" data-slot="<?php echo $slot; ?>" aria-label="Tap to add a bank">
                        <?php if (is_array($b)): ?>
                          <div class="mk-wd-slot-info">
                            <div class="mk-wd-slot-title">BANK <?php echo $slot; ?></div>
                            <div class="mk-wd-slot-sub"><?php echo htmlspecialchars((string)($b['bank_name'] ?? '')); ?></div>
                            <?php
                              $acc = (string)($b['account_no'] ?? '');
                              $last4 = $acc !== '' ? substr($acc, max(0, strlen($acc) - 4)) : '';
                            ?>
                            <?php if ($last4 !== ''): ?>
                              <div class="mk-wd-slot-sub">XXXX<?php echo htmlspecialchars($last4); ?></div>
                            <?php endif; ?>
                          </div>
                          <span class="mk-wd-item-btn">EDIT BANK</span>
                        <?php else: ?>
                          <span class="mk-wd-item-btn">TAP TO ADD A BANK</span>
                        <?php endif; ?>
                      </button>
                    <?php endfor; ?>
                  </div>

                  <div class="mk-wd-reqbox">
                    <div class="mk-wd-reqtitle">Withdraw Request</div>
                    <div class="mk-wd-reqgrid">
                      <div class="mk-wd-reqfield">
                        <label class="mk-wd-label">Select Bank</label>
                        <select id="mkWdReqSlot" class="mk-wd-input">
                          <option value="">Select</option>
                        </select>
                      </div>
                      <div class="mk-wd-reqfield">
                        <label class="mk-wd-label">Amount</label>
                        <input id="mkWdReqAmount" class="mk-wd-input" type="number" min="1" step="0.01" placeholder="Enter amount" inputmode="decimal">
                      </div>
                      <div class="mk-wd-reqfield mk-wd-reqfull">
                        <button type="button" class="mk-wd-savebtn" id="mkWdReqBtn">REQUEST WITHDRAW</button>
                        <div id="mkWdReqMsg" class="mk-dw-msg" style="display:none;"></div>
                      </div>
                    </div>
                  </div>

                  <button class="mk-wd-terms mk-terms-link" type="button">Terms and Conditions</button>
                </div>

                <div id="mkWdEditView" style="display:none;">
                  <div class="mk-wd-edit-head">
                    <button type="button" class="mk-wd-backbtn" id="mkWdBackBtn" aria-label="Back">
                      <svg fill="none" height="24" viewBox="0 0 16 24" width="16" xmlns="http://www.w3.org/2000/svg">
                        <path d="M11.6364 0L0 12L11.6364 24L16 19.5L8.72727 12L16 4.5L11.6364 0Z" fill="#08182F"></path>
                      </svg>
                    </button>
                    <div class="mk-wd-title" id="mkWdBankTitle">BANK 1</div>
                  </div>

                  <div class="mk-wd-formbox">
                    <div class="mk-wd-formgroup">
                      <label class="mk-wd-label">IFSC/SWIFT</label>
                      <input id="mkWdIfsc" class="mk-wd-input" type="text" placeholder="Enter IFSC/SWIFT" autocomplete="off">
                    </div>

                    <div class="mk-wd-formgroup mk-wd-bankgroup">
                      <label class="mk-wd-label">Bank Name</label>
                      <button type="button" class="mk-wd-selectbtn" id="mkWdBankSelect">
                        <span id="mkWdBankSelectText">Select One</span>
                        <i class="fa fa-chevron-down" aria-hidden="true"></i>
                      </button>
                      <div id="mkBankModal" class="mk-bank-modal" style="display:none;">
                        <div class="mk-bank-sheet">
                          <div class="mk-bank-top">
                            <div class="mk-bank-top-title">Select Bank</div>
                            <button type="button" class="mk-bank-close" id="mkBankClose" aria-label="Close">&times;</button>
                          </div>
                          <input id="mkBankSearch" class="mk-bank-search" type="text" placeholder="Search bank..." autocomplete="off">
                          <div id="mkBankList" class="mk-bank-list"></div>
                          <div id="mkBankEmpty" class="mk-dw-empty" style="display:none;">No matching bank found.</div>
                        </div>
                      </div>
                    </div>

                    <div class="mk-wd-formgroup">
                      <label class="mk-wd-label">Account No</label>
                      <input id="mkWdAccNo" class="mk-wd-input" type="text" placeholder="Enter Account No" autocomplete="off" inputmode="numeric">
                    </div>

                    <div class="mk-wd-formgroup">
                      <label class="mk-wd-label">Account Holder Name</label>
                      <input id="mkWdAccName" class="mk-wd-input" type="text" placeholder="Enter Account holder Name" autocomplete="off">
                    </div>

                    <button type="button" class="mk-wd-savebtn" id="mkWdSaveBtn">ADD BANK</button>
                    <div id="mkWdMsg" class="mk-dw-msg" style="display:none;"></div>
                  </div>

                  <button class="mk-wd-terms mk-terms-link" type="button">Terms and Conditions</button>
                </div>

              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>

<div id="mkCopyToast" class="mk-toast" style="display:none;">
  <div class="mk-toast-left">
    <i class="fa fa-check" aria-hidden="true"></i>
  </div>
  <div class="mk-toast-text" id="mkCopyToastText">Copied</div>
  <button class="mk-toast-close" type="button" aria-label="Close">&times;</button>
</div>

<div id="mkTermsModal" class="mk-terms-modal" style="display:none;">
  <div class="mk-terms-backdrop" data-mk-close="1"></div>
  <div class="mk-terms-dialog" role="dialog" aria-modal="true" aria-labelledby="mkTermsTitle">
    <div class="mk-terms-head">
      <div class="mk-terms-title" id="mkTermsTitle"><?php echo htmlspecialchars((string)$terms_title); ?></div>
      <button type="button" class="mk-terms-close" id="mkTermsClose" aria-label="Close">&times;</button>
    </div>
    <div class="mk-terms-body">
      <?php echo $terms_html; ?>
    </div>
  </div>
</div>

<style>
.mk-dw-shell {
  min-height: calc(100vh - var(--mk-account-header-height, 56px));
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 22px 14px;
}
.mk-dw-card {
  width: min(760px, 100%);
  background: #fff;
  border-radius: 10px;
  border: 1px solid rgba(0,0,0,0.10);
  box-shadow: 0 3px 14px rgba(0,0,0,0.10);
  overflow: hidden;
}
.mk-dw-tabs {
  display: grid;
  grid-template-columns: 1fr 1fr;
}
.mk-dw-tab {
  border: 0;
  background: #fff;
  padding: 14px 10px;
  font-weight: 900;
  letter-spacing: 0.3px;
  color: #0b2d5c;
  border-bottom: 3px solid transparent;
  transition: border-color 160ms ease, background 160ms ease;
}
.mk-dw-tab.active { border-bottom-color: #c37601; }
.mk-dw-body { padding: 16px 16px 18px; }
.mk-dw-body, .mk-dw-body * { color: #111; }
.mk-dw-note {
  text-align: center;
  color: #333;
  font-weight: 800;
  font-size: 12px;
  opacity: 0.95;
  margin-bottom: 12px;
}
.mk-dw-step {
  overflow: hidden;
  transition: max-height 260ms ease, opacity 200ms ease, transform 260ms ease;
  will-change: max-height, opacity, transform;
}
.mk-dw-step1 {
  max-height: 520px;
  opacity: 1;
  transform: translateY(0);
}
.mk-dw-step2 {
  max-height: 0;
  opacity: 0;
  transform: translateY(10px);
  margin-top: 0;
  border-top: 0;
  padding-top: 0;
}
.mk-dw-card.is-step2 .mk-dw-step1 {
  max-height: 0;
  opacity: 0;
  transform: translateY(-10px);
}
.mk-dw-card.is-step2 .mk-dw-step2 {
  max-height: 2600px;
  opacity: 1;
  transform: translateY(0);
  margin-top: 16px;
  border-top: 1px solid rgba(0,0,0,0.08);
  padding-top: 14px;
}
.mk-dw-amount-label {
  font-weight: 900;
  font-size: 12px;
  color: #0b2d5c;
  margin-bottom: 8px;
}
.mk-dw-amount-input {
  width: 100%;
  height: 38px;
  border-radius: 6px;
  border: 1px solid rgba(0,0,0,0.14);
  padding: 0 10px;
  outline: none;
  background: #000;
  color: #fff;
}
.mk-dw-amount-input::placeholder { color: rgba(255,255,255,0.72); }
.mk-dw-stepmsg {
  margin-top: 10px;
  font-size: 12px;
  font-weight: 900;
  color: #b00020;
}
.mk-dw-quick {
  margin-top: 10px;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.mk-dw-quick-btn {
  border: 1px solid rgba(0,0,0,0.14);
  background: #fff;
  border-radius: 4px;
  padding: 6px 10px;
  font-weight: 800;
  font-size: 12px;
}
.mk-dw-next {
  width: 100%;
  margin-top: 14px;
  height: 44px;
  border-radius: 6px;
  border: 0;
  background: #c37601;
  color: #fff;
  font-weight: 900;
  letter-spacing: 0.6px;
}
.mk-dw-terms {
  text-align: center;
  margin-top: 10px;
  font-size: 12px;
  font-weight: 700;
  color: #2b3d55;
  opacity: 0.9;
}
.mk-terms-link {
  border: 0;
  background: transparent;
  padding: 0;
  width: 100%;
}
.mk-terms-link:active { opacity: 0.75; }
.mk-dw-step2-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 10px;
}
.mk-dw-amountbar {
  width: 100%;
  margin: 6px 0 10px;
}
.mk-dw-amountbar input {
  width: 100%;
  height: 44px;
  border-radius: 6px;
  border: 1px solid rgba(0,0,0,0.14);
  background: #000;
  color: #fff;
  font-weight: 900;
  font-size: 16px;
  padding: 0 14px;
}
.mk-dw-merchant-row {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 6px 0 14px;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}
.mk-dw-merchant-row::-webkit-scrollbar { display: none; }
.mk-dw-merchant-row { scrollbar-width: none; }
.mk-dw-merchant {
  width: 54px;
  height: 54px;
  border-radius: 10px;
  border: 2px solid rgba(0,0,0,0.18);
  background: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
}
.mk-dw-merchant.active { border-color: #c37601; }
.mk-dw-merchant img { width: 30px; height: 30px; object-fit: contain; display: block; }
.mk-dw-merchant i { font-size: 26px; color: #333; }
.mk-dw-methods { margin: 10px 5% 0; }
.mk-dw-upibox {
  margin: 0 5% 14px;
  border: 1px solid rgba(0,0,0,0.12);
  border-radius: 10px;
  background: #fff;
  overflow: hidden;
}
.mk-dw-upiitem {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 14px;
  border-bottom: 1px solid rgba(0,0,0,0.10);
}
.mk-dw-upiitem:last-child { border-bottom: 0; }
.mk-dw-upilabel {
  font-size: 12px;
  color: #9a9a9a;
  font-weight: 700;
  margin: 0 0 6px;
}
.mk-dw-upivalue {
  font-size: 16px;
  font-weight: 900;
  color: #0b2d5c;
  word-break: break-word;
}
.mk-dw-upiiconbtn {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  border: 1px solid rgba(0,0,0,0.12);
  background: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
}
.mk-dw-upiiconbtn:active { transform: scale(0.99); }
.mk-dw-upiiconbtn i { font-size: 20px; color: #111; }
.mk-dw-viewqr {
  width: 100%;
  border: 0;
  background: #f5f5f5;
  padding: 14px 14px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  font-weight: 900;
  color: #9a9a9a;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}
.mk-dw-viewqr i { color: #111; font-size: 22px; }
.mk-dw-qrtitle {
  padding: 0 5%;
  margin: 12px 0 10px;
  font-weight: 900;
  letter-spacing: 0.4px;
  color: #0b2d5c;
}
.mk-ui-divider {
  display: none;
  height: 0;
  background: transparent;
  margin: 0;
}
.mk-dw-bankbox {
  margin: 0 5% 14px;
  border: 1px solid rgba(0,0,0,0.08);
  border-radius: 12px;
  background: #fff;
  padding: 14px;
}
.mk-dw-notebox {
  margin: 0 5% 14px;
  border: 1px solid rgba(0,0,0,0.08);
  border-radius: 12px;
  background: #fff;
  padding: 12px 14px;
  font-weight: 800;
  color: #111;
}
.mk-dw-notebox .mk-dw-note-line {
  font-size: 12px;
  font-weight: 800;
  color: #111;
  margin: 6px 0;
  word-break: break-word;
}
.mk-dw-notebox .mk-dw-note-head {
  font-size: 11px;
  font-weight: 900;
  color: #666;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  margin: 0 0 6px;
}
.mk-dw-bankgrid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px 14px;
}
.mk-dw-bankkv {
  font-size: 12px;
  font-weight: 900;
  color: #111;
}
.mk-dw-bankkv span {
  display: block;
  font-size: 11px;
  font-weight: 900;
  color: #666;
  margin-bottom: 4px;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}
.mk-dw-bankkv strong {
  display: block;
  font-size: 13px;
  font-weight: 900;
  color: #111;
  word-break: break-word;
}
.mk-dw-addrtxt {
  border: 1px dashed rgba(0,0,0,0.18);
  border-radius: 10px;
  padding: 10px 12px;
  margin-bottom: 10px;
  font-weight: 900;
  color: #111;
  word-break: break-word;
  background: rgba(0,0,0,0.02);
}
.mk-dw-qrbox {
  margin: 0 5% 14px;
  border: 0;
  border-radius: 12px;
  background: #fff;
  padding: 18px 16px;
}
.mk-ui-qr {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 18px;
}
.mk-ui-scanbox {
  width: min(420px, 100%);
  aspect-ratio: 3 / 4;
  border-radius: 12px;
  border: 1px solid rgba(0,0,0,0.10);
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: none;
  overflow: hidden;
}
.mk-ui-scanimg {
  width: 100%;
  height: 100%;
  object-fit: contain;
  display: block;
  padding: 0;
}
.mk-ui-qr-right {
  width: min(320px, 100%);
  display: flex;
  flex-direction: column;
  gap: 12px;
  align-items: stretch;
}
.mk-ui-qrlabel {
  font-weight: 800;
  color: #444;
  margin: 0;
}
.mk-ui-accountName {
  background: #000;
  color: #fff;
  font-weight: 900;
  border-radius: 6px;
  padding: 12px 14px;
  text-transform: uppercase;
}
.mk-ui-address {
  border: 1px solid rgba(0,0,0,0.12);
  border-radius: 8px;
  padding: 10px 12px;
  font-weight: 800;
  color: #111;
  background: #fff;
  word-break: break-word;
}
.mk-ui-btn {
  height: 46px;
  border-radius: 6px;
  font-weight: 900;
  border: 1px solid rgba(0,0,0,0.18);
  background: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  width: 100%;
}
.mk-ui-btn-outline { border-color: #c37601; color: #c37601; }
.mk-ui-btn-primary { border: 0; background: #c37601; color: #fff; }
.mk-ui-btn:disabled { opacity: 0.55; cursor: not-allowed; }

.mk-btn-label {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0;
}
.mk-dots {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  transform: translateY(1px);
}
.mk-dot {
  width: 5px;
  height: 5px;
  border-radius: 999px;
  background: currentColor;
  opacity: 0.25;
  animation: mk-dot-bounce 920ms infinite;
}
.mk-dot:nth-child(2) { animation-delay: 140ms; }
.mk-dot:nth-child(3) { animation-delay: 280ms; }
@keyframes mk-dot-bounce {
  0%, 80%, 100% { opacity: 0.25; transform: translateY(0); }
  40% { opacity: 0.95; transform: translateY(-2px); }
}

.mk-ui-submit {
  margin: 0 5% 0;
}
.mk-ui-txn {
  width: 100%;
  height: 54px;
  border-radius: 6px;
  border: 1px solid rgba(0,0,0,0.18);
  padding: 0 16px;
  font-weight: 800;
  color: #111;
  background: #fff;
  text-transform: uppercase;
  margin-top: 14px;
}
.mk-ui-txn::placeholder { color: rgba(17,17,17,0.38); font-weight: 900; }
.mk-ui-proof { position: absolute; left: -9999px; width: 1px; height: 1px; opacity: 0; }
.mk-ui-attach {
  margin-top: 14px;
  width: 100%;
  height: 56px;
  border-radius: 6px;
  background: #e0e0e0;
  color: #0b2d5c;
  font-weight: 900;
  letter-spacing: 0.6px;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  cursor: pointer;
  user-select: none;
  text-transform: uppercase;
}
.mk-ui-attach i {
  position: absolute;
  right: 16px;
  font-size: 18px;
  color: #0b2d5c;
}
.mk-ui-deposit {
  margin-top: 14px;
  width: 100%;
  height: 58px;
  border-radius: 6px;
  border: 0;
  background: #c37601;
  color: #fff;
  font-weight: 900;
  letter-spacing: 0.6px;
  text-transform: uppercase;
}

.mk-toast {
  position: fixed;
  top: 16px;
  right: 16px;
  z-index: 99999;
  display: flex;
  align-items: stretch;
  width: min(360px, calc(100vw - 32px));
  background: #fff;
  border: 1px solid rgba(0,0,0,0.16);
  border-radius: 6px;
  box-shadow: 0 8px 24px rgba(0,0,0,0.18);
  transform: translateX(120%);
  opacity: 0;
  transition: transform 420ms cubic-bezier(0.16, 1, 0.3, 1), opacity 320ms ease;
  overflow: hidden;
}
.mk-toast.mk-show {
  transform: translateX(0);
  opacity: 1;
}
.mk-toast-left {
  width: 62px;
  background: #0b7a1f;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  font-weight: 900;
}
.mk-toast-text {
  flex: 1 1 auto;
  padding: 14px 14px;
  font-weight: 800;
  color: #111;
  display: flex;
  align-items: center;
  min-height: 54px;
}
.mk-toast-close {
  width: 46px;
  border: 0;
  background: #fff;
  border-left: 1px solid rgba(0,0,0,0.12);
  font-size: 22px;
  line-height: 1;
  color: #111;
}
.mk-toast-close:active { opacity: 0.7; }

.mk-terms-modal {
  position: fixed;
  inset: 0;
  z-index: 100000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px 12px;
}
.mk-terms-backdrop {
  position: absolute;
  inset: 0;
  background: rgba(0,0,0,0.55);
}
.mk-terms-dialog {
  position: relative;
  width: min(980px, calc(100vw - 24px));
  max-height: calc(100vh - 40px);
  background: #fff;
  border-radius: 6px;
  overflow: hidden;
  box-shadow: 0 22px 70px rgba(0,0,0,0.35);
  transform: translateY(-24px);
  opacity: 0;
  transition: transform 360ms cubic-bezier(0.16, 1, 0.3, 1), opacity 260ms ease;
}
.mk-terms-modal.mk-show .mk-terms-dialog {
  transform: translateY(0);
  opacity: 1;
}
.mk-terms-head {
  background: #000;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 14px;
}
.mk-terms-title {
  font-weight: 900;
  font-size: 20px;
  color: #fff;
}
.mk-terms-close {
  border: 0;
  background: transparent;
  color: #fff;
  font-size: 26px;
  line-height: 1;
  width: 40px;
  height: 40px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
}
.mk-terms-close:active { opacity: 0.8; }
.mk-terms-body {
  padding: 14px;
  overflow: auto;
  max-height: calc(100vh - 140px);
  color: #666;
  font-weight: 500;
}
.mk-terms-body p { margin: 0 0 10px; }
.mk-terms-body b, .mk-terms-body strong { color: #555; font-weight: 900; }

.mk-wd-wrap {
  padding: 0 2%;
}
.mk-wd-topnote {
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #000;
  color: #fff;
  font-weight: 800;
  font-size: 12px;
  border-radius: 4px;
  margin-bottom: 14px;
}
.mk-wd-head {
  font-size: 26px;
  font-weight: 900;
  color: #333;
  margin: 0 0 14px;
}
.mk-wd-list {
  display: grid;
  gap: 14px;
}
.mk-wd-item {
  width: 100%;
  border: 1px dashed rgba(0,0,0,0.30);
  border-radius: 8px;
  background: #f1f1f1;
  min-height: 160px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px;
  gap: 14px;
  transition: transform 160ms ease, background 160ms ease, border-color 160ms ease;
  text-align: left;
}
.mk-wd-item.mk-wd-empty { justify-content: center; }
.mk-wd-item:active { transform: scale(0.99); }
.mk-wd-slot-info {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.mk-wd-slot-title {
  font-weight: 900;
  font-size: 22px;
  color: #08182F;
  letter-spacing: 0.3px;
}
.mk-wd-slot-sub {
  font-weight: 800;
  font-size: 13px;
  color: #333;
  word-break: break-word;
}
.mk-wd-item-btn {
  background: #cfcfcf;
  color: #0b2d5c;
  font-weight: 900;
  padding: 12px 22px;
  border-radius: 6px;
  letter-spacing: 0.4px;
  text-transform: uppercase;
  flex: 0 0 auto;
}
.mk-wd-edit-head {
  display: flex;
  align-items: center;
  gap: 12px;
  font-weight: 900;
  font-size: 22px;
  color: #08182F;
  margin: 0 0 14px;
}
.mk-wd-backbtn {
  border: 0;
  background: transparent;
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
}
.mk-wd-backbtn:active { background: rgba(0,0,0,0.06); }
.mk-wd-title { font-weight: 900; }
.mk-wd-formbox {
  border: 1px solid rgba(0,0,0,0.10);
  border-radius: 10px;
  background: #fff;
  padding: 14px;
}
.mk-wd-formgroup { margin-bottom: 12px; }
.mk-wd-bankgroup { position: relative; }
.mk-wd-reqbox {
  margin-top: 16px;
  border: 1px solid rgba(0,0,0,0.10);
  border-radius: 10px;
  background: #fff;
  padding: 14px;
}
.mk-wd-reqtitle {
  font-weight: 900;
  color: #08182F;
  margin: 0 0 10px;
  font-size: 14px;
}
.mk-wd-reqgrid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.mk-wd-reqfull { grid-column: 1 / -1; }
.mk-wd-label {
  display: block;
  font-weight: 900;
  font-size: 12px;
  color: #08182F;
  margin: 0 0 6px;
  letter-spacing: 0.2px;
}
.mk-wd-input {
  width: 100%;
  height: 42px;
  border-radius: 10px;
  border: 1px solid rgba(0,0,0,0.14);
  padding: 0 14px;
  font-weight: 800;
  color: #111;
  background: #fff;
  outline: none;
}
.mk-wd-selectbtn {
  width: 100%;
  height: 42px;
  border-radius: 10px;
  border: 1px solid rgba(0,0,0,0.14);
  padding: 0 14px;
  font-weight: 800;
  color: #111;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}
.mk-wd-savebtn {
  width: 100%;
  height: 58px;
  border-radius: 6px;
  border: 0;
  background: #c37601;
  color: #fff;
  font-weight: 900;
  letter-spacing: 0.6px;
  text-transform: uppercase;
  margin-top: 8px;
}
.mk-bank-modal {
  position: fixed;
  left: 0;
  top: 0;
  z-index: 99999;
  background: transparent;
  padding: 0;
  box-sizing: border-box;
  width: min(760px, calc(100vw - 20px));
}
.mk-bank-sheet {
  width: 100%;
  background: #fff;
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid rgba(0,0,0,0.14);
  box-shadow: 0 10px 26px rgba(0,0,0,0.16);
  box-sizing: border-box;
  max-height: calc(100vh - 20px);
}
.mk-bank-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 12px 0;
  border-bottom: 0;
}
.mk-bank-top-title {
  font-weight: 900;
  font-size: 13px;
  color: #08182F;
}
.mk-bank-close {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  border: 0;
  background: rgba(0,0,0,0.04);
  font-size: 22px;
  line-height: 1;
  color: #111;
}
.mk-bank-close:active { opacity: 0.8; }
.mk-bank-search {
  width: calc(100% - 20px);
  margin: 10px 10px 8px;
  height: 40px;
  border-radius: 10px;
  border: 1px solid rgba(0,0,0,0.14);
  padding: 0 12px;
  font-weight: 800;
  font-size: 13px;
  outline: none;
}
.mk-bank-list {
  max-height: 320px;
  overflow: auto;
  padding: 0 10px 10px;
  display: grid;
  gap: 6px;
}
.mk-bank-modal .mk-dw-empty {
  padding: 8px 12px 12px;
  font-size: 12px;
  font-weight: 800;
  color: #555;
}
.mk-bank-item {
  width: 100%;
  height: 40px;
  border: 0;
  border-radius: 10px;
  background: #f3f3f3;
  padding: 0 12px;
  font-weight: 900;
  font-size: 13px;
  color: #111;
  text-align: left;
  display: flex;
  align-items: center;
  justify-content: flex-start;
  cursor: pointer;
  transition: background 140ms ease, color 140ms ease, transform 120ms ease;
  appearance: none;
  -webkit-appearance: none;
}
.mk-bank-item:hover {
  background: #c37601;
  color: #fff;
}
.mk-bank-item:focus-visible {
  outline: none;
  box-shadow: 0 0 0 3px rgba(195,118,1,0.22);
}
.mk-bank-item:active { transform: scale(0.99); }
.mk-wd-terms {
  text-align: center;
  margin-top: 18px;
  font-size: 14px;
  font-weight: 800;
  color: #0b2d5c;
}
.mk-dw-summary {
  display: inline-flex;
  align-items: baseline;
  gap: 8px;
  font-weight: 900;
  color: #111;
}
.mk-dw-summary span { font-size: 12px; color: #666; font-weight: 900; text-transform: uppercase; letter-spacing: 0.3px; }
.mk-dw-summary strong { font-size: 14px; }
.mk-dw-back {
  height: 34px;
  border-radius: 8px;
  border: 1px solid rgba(0,0,0,0.14);
  background: #fff;
  font-weight: 900;
  padding: 0 12px;
}
.mk-dw-step2-head {
  font-weight: 900;
  color: #111;
  margin-bottom: 10px;
}
.mk-dw-form {
  border: 1px solid rgba(0,0,0,0.08);
  border-radius: 10px;
  padding: 12px;
  background: #fff;
}
.mk-dw-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}
.mk-dw-field label {
  display: block;
  font-size: 12px;
  font-weight: 900;
  color: #222;
  margin-bottom: 6px;
}
.mk-dw-field input,
.mk-dw-field select {
  width: 100%;
  height: 36px;
  border-radius: 8px;
  border: 1px solid rgba(0,0,0,0.14);
  padding: 0 10px;
  outline: none;
}
.mk-dw-field input[type="file"] { height: auto; padding: 8px 10px; }
.mk-dw-grid .mk-full { grid-column: 1 / -1; }
.mk-dw-actions {
  display: flex;
  justify-content: flex-end;
  margin-top: 10px;
}
.mk-dw-submit {
  height: 36px;
  border-radius: 8px;
  border: 0;
  background: #111;
  color: #fff;
  font-weight: 900;
  padding: 0 14px;
}
.mk-dw-msg {
  margin-top: 10px;
  font-size: 12px;
  font-weight: 900;
}
.mk-dw-msg.err { color: #b00020; }
.mk-dw-methods { margin-top: 12px; display: grid; gap: 10px; }
.mk-dw-method {
  width: 100%;
  border: 1px solid rgba(0,0,0,0.18);
  border-radius: 12px;
  background: #fff;
  padding: 12px 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  text-align: left;
  cursor: pointer;
  transition: transform 120ms ease, border-color 160ms ease, box-shadow 160ms ease;
}
.mk-dw-method:active { transform: scale(0.99); }
.mk-dw-method.selected {
  border-color: rgba(195,118,1,0.65);
  box-shadow: 0 0 0 2px rgba(195,118,1,0.18);
}
.mk-dw-method-left { display: flex; gap: 10px; }
.mk-dw-method-icon {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  background: rgba(195,118,1,0.12);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: #c37601;
}
.mk-dw-method-title { font-weight: 900; color: #111; line-height: 1.1; }
.mk-dw-method-meta { margin-top: 6px; display: flex; gap: 10px; align-items: center; }
.mk-dw-method-tag { font-weight: 900; color: #111; min-width: 44px; }
.mk-dw-method-val { font-weight: 800; color: #111; word-break: break-word; }
.mk-dw-note2 { margin-top: 6px; font-size: 12px; font-weight: 700; color: #333; opacity: 0.9; }
.mk-dw-qr img { width: 92px; height: 92px; object-fit: cover; border-radius: 12px; border: 1px solid rgba(0,0,0,0.10); background: #fff; }
.mk-dw-empty { text-align: center; color: #555; font-weight: 800; padding: 10px; }

.mk-dw-card .mk-dw-body * {
  font-weight: 500;
}
.mk-dw-tabs .mk-dw-tab {
  font-weight: 900;
}

@media (max-width: 767px) {
  .mk-dw-shell { padding: 14px 10px; }
  .mk-dw-grid { grid-template-columns: 1fr; }
  .mk-dw-methods { margin: 10px 0 0; }
  .mk-dw-qrtitle { padding: 0; }
  .mk-dw-bankbox { margin: 0 0 14px; }
  .mk-dw-notebox { margin: 0 0 14px; }
  .mk-dw-bankgrid { grid-template-columns: 1fr; }
  .mk-ui-divider { margin: 0 0 14px; }
  .mk-dw-qrbox { margin: 0 0 14px; }
  .mk-ui-qr { flex-direction: column; align-items: stretch; }
  .mk-ui-scanbox { width: min(340px, 100%); margin: 0 auto; aspect-ratio: 3 / 4; }
  .mk-ui-qr-right { width: 100%; }
  .mk-ui-submit { margin: 0; }
  .mk-toast { top: 10px; right: 10px; width: calc(100vw - 20px); }
  .mk-terms-modal { padding: 10px; }
  .mk-terms-dialog { width: calc(100vw - 20px); max-height: calc(100vh - 20px); border-radius: 6px; }
  .mk-terms-head { padding: 10px 12px; }
  .mk-terms-title { font-size: 16px; }
  .mk-terms-body { padding: 12px; font-size: 13px; line-height: 1.5; max-height: calc(100vh - 120px); }
  .mk-wd-wrap { padding: 0; }
  .mk-wd-head { font-size: 20px; }
  .mk-wd-item { min-height: 130px; }
  .mk-bank-modal { width: calc(100vw - 20px); }
  .mk-bank-list { max-height: 280px; }
  .mk-wd-reqgrid { grid-template-columns: 1fr; }
}
</style>

<script>
(function() {
  var tabs = document.querySelectorAll('.mk-dw-tab');
  var panes = document.querySelectorAll('.mk-dw-pane');
  function setTab(name) {
    for (var i = 0; i < tabs.length; i++) tabs[i].classList.toggle('active', tabs[i].getAttribute('data-tab') === name);
    for (var j = 0; j < panes.length; j++) panes[j].style.display = panes[j].getAttribute('data-pane') === name ? '' : 'none';
  }

  function setBtnLoading(btn, loading, labelText) {
    if (!btn) return;
    if (loading) {
      if (btn.dataset && btn.dataset.mkLoading === '1') return;
      if (btn.dataset) {
        btn.dataset.mkLoading = '1';
        btn.dataset.mkHtml = btn.innerHTML;
      }
      btn.disabled = true;
      btn.setAttribute('aria-busy', 'true');
      btn.textContent = '';
      var wrap = document.createElement('span');
      wrap.className = 'mk-btn-label';
      var dots = document.createElement('span');
      dots.className = 'mk-dots';
      for (var i = 0; i < 3; i++) {
        var d = document.createElement('span');
        d.className = 'mk-dot';
        dots.appendChild(d);
      }
      wrap.appendChild(dots);
      btn.appendChild(wrap);
      return;
    }

    if (btn.dataset && btn.dataset.mkLoading !== '1') return;
    if (btn.dataset && typeof btn.dataset.mkHtml === 'string') {
      btn.innerHTML = btn.dataset.mkHtml;
      btn.dataset.mkLoading = '0';
    }
    btn.disabled = false;
    btn.removeAttribute('aria-busy');
  }

  function setPillLoading(pill, loading) {
    if (!pill) return;
    if (loading) {
      if (pill.dataset && pill.dataset.mkLoading === '1') return;
      if (pill.dataset) {
        pill.dataset.mkLoading = '1';
        pill.dataset.mkText = pill.textContent || '';
      }
      pill.textContent = '';
      var dots = document.createElement('span');
      dots.className = 'mk-dots';
      for (var i = 0; i < 3; i++) {
        var d = document.createElement('span');
        d.className = 'mk-dot';
        dots.appendChild(d);
      }
      pill.appendChild(dots);
      return;
    }
    if (pill.dataset && pill.dataset.mkLoading !== '1') return;
    pill.textContent = (pill.dataset && typeof pill.dataset.mkText === 'string') ? pill.dataset.mkText : 'EDIT BANK';
    if (pill.dataset) pill.dataset.mkLoading = '0';
  }

  for (var k = 0; k < tabs.length; k++) {
    tabs[k].addEventListener('click', function() { setTab(this.getAttribute('data-tab')); });
  }

  var termsModal = document.getElementById('mkTermsModal');
  var termsClose = document.getElementById('mkTermsClose');
  var termsBusy = false;
  function openTerms() {
    if (!termsModal || termsBusy) return;
    termsBusy = true;
    termsModal.style.display = '';
    termsModal.classList.remove('mk-show');
    void termsModal.offsetWidth;
    termsModal.classList.add('mk-show');
    setTimeout(function() { termsBusy = false; }, 380);
  }
  function closeTerms() {
    if (!termsModal || termsBusy) return;
    termsBusy = true;
    termsModal.classList.remove('mk-show');
    setTimeout(function() {
      termsModal.style.display = 'none';
      termsBusy = false;
    }, 360);
  }
  var termLinks = document.querySelectorAll('.mk-terms-link');
  for (var ti = 0; ti < termLinks.length; ti++) termLinks[ti].addEventListener('click', openTerms);
  if (termsClose) termsClose.addEventListener('click', closeTerms);
  if (termsModal) {
    termsModal.addEventListener('click', function(e) {
      var t = e && e.target ? e.target : null;
      if (t && t.getAttribute && t.getAttribute('data-mk-close') === '1') closeTerms();
    });
  }
  document.addEventListener('keydown', function(e) {
    if (!termsModal || termsModal.style.display === 'none') return;
    if (e && (e.key === 'Escape' || e.key === 'Esc')) closeTerms();
  });

  var amount = document.getElementById('mkDwAmount');
  var next = document.getElementById('mkDwNext');
  var step2 = document.getElementById('mkDwStep2');
  var step1 = document.getElementById('mkDwStep1');
  var card = document.querySelector('.mk-dw-card');
  var back = document.getElementById('mkDwBack');
  var stepMsg = document.getElementById('mkDwStepMsg');
  var amountLabel = document.getElementById('mkDwAmountLabel');
  var methodSel = document.getElementById('mkDwMethodId');
  var txn = document.getElementById('mkDwTxnId');
  var proof = document.getElementById('mkDwProof');
  var submit = document.getElementById('mkDwSubmit');
  var msg = document.getElementById('mkDwMsg');
  var proofLabel = document.getElementById('mkDwProofLabel');
  var amountBar = document.getElementById('mkDwAmountBar');
  var qrImg = document.getElementById('mkDwQrImg');
  var netChip = document.getElementById('mkDwNetworkChip');
  var btnCopy = document.getElementById('mkDwCopy');
  var btnDownload = document.getElementById('mkDwDownload');
  var detailsTitle = document.getElementById('mkDwDetailsTitle');
  var bankBox = document.getElementById('mkDwBankBox');
  var noteBox = document.getElementById('mkDwNoteBox');
  var qrBox = document.getElementById('mkDwQrBox');
  var rightLabel = document.getElementById('mkDwRightLabel');
  var addrText = document.getElementById('mkDwAddrText');
  var toast = document.getElementById('mkCopyToast');
  var toastText = document.getElementById('mkCopyToastText');
  var currentAddr = '';
  var currentQr = '';
  var currentQrAlt = '';
  var currentCopyMsg = 'Copied';
  var merchantBtns = document.querySelectorAll('.mk-dw-merchant-row .mk-dw-merchant[data-merchant]');
  var methodCards = document.getElementById('mkDwMethodCards');
  var activeMerchant = 'upi';

  var wdListView = document.getElementById('mkWdListView');
  var wdEditView = document.getElementById('mkWdEditView');
  var wdBackBtn = document.getElementById('mkWdBackBtn');
  var wdTitle = document.getElementById('mkWdBankTitle');
  var wdIfsc = document.getElementById('mkWdIfsc');
  var wdAccNo = document.getElementById('mkWdAccNo');
  var wdAccName = document.getElementById('mkWdAccName');
  var wdBankSelect = document.getElementById('mkWdBankSelect');
  var wdBankSelectText = document.getElementById('mkWdBankSelectText');
  var wdSaveBtn = document.getElementById('mkWdSaveBtn');
  var wdMsg = document.getElementById('mkWdMsg');
  var wdReqSlot = document.getElementById('mkWdReqSlot');
  var wdReqAmount = document.getElementById('mkWdReqAmount');
  var wdReqBtn = document.getElementById('mkWdReqBtn');
  var wdReqMsg = document.getElementById('mkWdReqMsg');
  var bankModal = document.getElementById('mkBankModal');
  var bankClose = document.getElementById('mkBankClose');
  var bankSearch = document.getElementById('mkBankSearch');
  var bankList = document.getElementById('mkBankList');
  var bankEmpty = document.getElementById('mkBankEmpty');
  var bankSheet = bankModal && bankModal.querySelector ? bankModal.querySelector('.mk-bank-sheet') : null;

  var wdActiveSlot = 1;
  var wdSelectedBank = '';
  var wdBanks = window.MK_WD_BANKS || {};
  var bankNames = Array.isArray(window.MK_BANK_NAMES) ? window.MK_BANK_NAMES : [];

  var bankModalAttached = false;
  var bankModalPosBound = false;
  function positionBankModal() {
    if (!bankModal || !wdBankSelect) return;
    if (bankModal.style.display === 'none') return;
    var r = wdBankSelect.getBoundingClientRect();
    var vw = Math.max(320, window.innerWidth || 0);
    var maxW = Math.min(760, vw - 20);
    var width = Math.min(maxW, Math.max(Math.ceil(r.width), 520));
    var left = Math.floor(r.left);
    if (left + width > vw - 10) left = Math.max(10, vw - 10 - width);
    if (left < 10) left = 10;
    var top = Math.floor(r.bottom) + 8;
    bankModal.style.left = left + 'px';
    bankModal.style.top = top + 'px';
    bankModal.style.width = width + 'px';

    var vh = Math.max(320, window.innerHeight || 0);
    var available = Math.max(220, vh - top - 10);
    if (bankSheet) bankSheet.style.maxHeight = available + 'px';
    if (bankList) {
      var topEl = bankSheet && bankSheet.querySelector ? bankSheet.querySelector('.mk-bank-top') : null;
      var searchEl = bankSheet && bankSheet.querySelector ? bankSheet.querySelector('.mk-bank-search') : null;
      var used = (topEl ? topEl.offsetHeight : 44) + (searchEl ? searchEl.offsetHeight : 40) + 34;
      var listH = Math.max(160, available - used);
      bankList.style.maxHeight = listH + 'px';
    }
  }

  function setStepMsg(text) {
    if (!stepMsg) return;
    stepMsg.style.display = text ? '' : 'none';
    stepMsg.textContent = text || '';
  }

  function setMsg(text, isErr) {
    if (!msg) return;
    msg.style.display = text ? '' : 'none';
    msg.textContent = text || '';
    msg.classList.toggle('err', !!isErr);
  }

  function setWdMsg(text, isErr) {
    if (!wdMsg) return;
    wdMsg.style.display = text ? '' : 'none';
    wdMsg.textContent = text || '';
    wdMsg.classList.toggle('err', !!isErr);
  }

  function setWdReqMsg(text, isErr) {
    if (!wdReqMsg) return;
    wdReqMsg.style.display = text ? '' : 'none';
    wdReqMsg.textContent = text || '';
    wdReqMsg.classList.toggle('err', !!isErr);
  }

  var toastTimer = null;
  function showToast(text) {
    if (!toast || !toastText) return;
    toastText.textContent = text || 'Copied';
    toast.style.display = '';
    toast.classList.remove('mk-show');
    void toast.offsetWidth;
    toast.classList.add('mk-show');
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(function() {
      toast.classList.remove('mk-show');
      setTimeout(function() { toast.style.display = 'none'; }, 420);
    }, 2600);
  }

  if (toast) {
    var close = toast.querySelector('.mk-toast-close');
    if (close) {
      close.addEventListener('click', function() {
        toast.classList.remove('mk-show');
        setTimeout(function() { toast.style.display = 'none'; }, 260);
      });
    }
  }

  function copyText(text, okMsg) {
    var t = (text || '').trim();
    if (!t) return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(t).then(function() {
        showToast(okMsg || 'Copied');
      }, function() {
        showToast('Copy failed');
      });
      return;
    }
    try {
      var ta = document.createElement('textarea');
      ta.value = t;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      ta.remove();
      showToast(okMsg || 'Copied');
    } catch (e) {
      showToast('Copy failed');
    }
  }

  function escHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function(m) {
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]);
    });
  }

  function merchantForOption(opt) {
    if (!opt) return 'upi';
    var ch = (opt.getAttribute('data-channel') || '').toLowerCase();
    if (ch === 'upi') return 'upi';
    if (ch === 'wallet') return 'crypto';
    if (ch === 'bank') return 'upi';
    return 'upi';
  }

  function getOptionsForMerchant(m) {
    if (!methodSel || !methodSel.options) return [];
    var out = [];
    for (var i = 0; i < methodSel.options.length; i++) {
      var opt = methodSel.options[i];
      if (!opt || !opt.value) continue;
      if (merchantForOption(opt) === m) out.push(opt);
    }
    return out;
  }

  function setMerchantActive(m) {
    activeMerchant = m;
    for (var i = 0; i < merchantBtns.length; i++) {
      merchantBtns[i].classList.toggle('active', merchantBtns[i].getAttribute('data-merchant') === m);
    }
  }

  function renderMethodCards() {
    if (!methodCards) return;
    var opts = getOptionsForMerchant(activeMerchant);
    if (!opts.length) {
      methodCards.innerHTML = '<div class="mk-dw-empty">No methods available.</div>';
      return;
    }
    var html = '';
    for (var i = 0; i < opts.length; i++) {
      var opt = opts[i];
      var id = opt.value;
      var ch = (opt.getAttribute('data-channel') || '').toLowerCase();
      var label = (opt.getAttribute('data-label') || '').trim() || (opt.textContent || '').trim();
      var metaL = '';
      var metaV = '';
      if (ch === 'upi') {
        metaL = 'UPI';
        metaV = (opt.getAttribute('data-upi') || opt.getAttribute('data-addr') || '').trim();
      } else if (ch === 'wallet') {
        metaL = 'USDT';
        metaV = (opt.getAttribute('data-network') || label || '').trim();
      } else if (ch === 'bank') {
        metaL = 'BANK';
        metaV = (opt.getAttribute('data-bank') || label || '').trim();
      } else {
        metaL = 'METHOD';
        metaV = label;
      }
      var selected = methodSel && methodSel.value === id ? ' selected' : '';
      html += ''
        + '<button type="button" class="mk-dw-method' + selected + '" data-id="' + escHtml(id) + '">'
        +   '<div class="mk-dw-method-left">'
          +     '<span class="mk-dw-method-icon"><i class="fa fa-credit-card" aria-hidden="true"></i></span>'
        +     '<div>'
          +       '<div class="mk-dw-method-title">' + escHtml((ch === 'upi') ? 'UPI ID' : label) + '</div>'
          +       '<div class="mk-dw-method-meta"><span class="mk-dw-method-tag">' + escHtml(metaL) + '</span><span class="mk-dw-method-val">' + escHtml(metaV) + '</span></div>'
        +     '</div>'
        +   '</div>'
        + '</button>';
    }
    methodCards.innerHTML = html;
  }

  function applySelectedMethod() {
    if (!methodSel) return;
    var opt = methodSel.options && methodSel.selectedIndex >= 0 ? methodSel.options[methodSel.selectedIndex] : null;
    var channel = opt ? (opt.getAttribute('data-channel') || '') : '';
    var qr = opt ? (opt.getAttribute('data-qr') || '') : '';
    var net = opt ? (opt.getAttribute('data-network') || '') : '';
    var addr = opt ? (opt.getAttribute('data-addr') || '') : '';
    var qrAlt = opt ? (opt.getAttribute('data-qr-alt') || '') : '';
    var label = opt ? (opt.getAttribute('data-label') || '') : '';
    var upi = opt ? (opt.getAttribute('data-upi') || '') : '';
    var holder = opt ? (opt.getAttribute('data-holder') || '') : '';
    var bank = opt ? (opt.getAttribute('data-bank') || '') : '';
    var branch = opt ? (opt.getAttribute('data-branch') || '') : '';
    var acname = opt ? (opt.getAttribute('data-acname') || '') : '';
    var acno = opt ? (opt.getAttribute('data-acno') || '') : '';
    var ifsc = opt ? (opt.getAttribute('data-ifsc') || '') : '';
    var wallet = opt ? (opt.getAttribute('data-wallet') || '') : '';
    var instructions = opt ? (opt.getAttribute('data-instructions') || '') : '';
    var notes = opt ? (opt.getAttribute('data-notes') || '') : '';
    var url = opt ? (opt.getAttribute('data-url') || '') : '';

    function fmtNet(n) {
      if (!n) return '';
      var u = String(n).toUpperCase();
      if (u === 'TRC20') return 'TRC-20';
      if (u === 'BEP20') return 'BEP-20';
      return u;
    }

    currentAddr = addr;
    currentQr = qr;
    currentQrAlt = qrAlt;
    currentCopyMsg = 'Copied';

    if (detailsTitle) {
      if (channel === 'bank') detailsTitle.textContent = 'BANK DETAILS';
      else if (channel === 'upi') detailsTitle.textContent = 'UPI DETAILS';
      else if (channel === 'wallet') detailsTitle.textContent = 'QR CODE FOR PAYMENT';
      else detailsTitle.textContent = 'DETAILS';
    }

    var upiBox = document.getElementById('mkDwUpiBox');
    if (upiBox) upiBox.style.display = 'none';
    if (bankBox) bankBox.style.display = 'none';
    if (noteBox) noteBox.style.display = 'none';
    if (qrBox) qrBox.style.display = 'none';

    if (noteBox && (instructions || notes)) {
      var html = '<div class="mk-dw-note-head">Notes</div>';
      if (instructions) html += '<div class="mk-dw-note-line">' + escHtml(instructions) + '</div>';
      if (notes) html += '<div class="mk-dw-note-line">' + escHtml(notes) + '</div>';
      noteBox.innerHTML = html;
      noteBox.style.display = '';
    }

    if (channel === 'bank') {
      if (bankBox) {
        var rows = [];
        function kv(k, v) {
          if (!v) return;
          rows.push('<div class="mk-dw-bankkv"><span>' + escHtml(k) + '</span><strong>' + escHtml(v) + '</strong></div>');
        }
        kv('Bank', bank);
        kv('Branch', branch);
        kv('Account Name', acname);
        kv('Account Number', acno);
        kv('IFSC', ifsc);
        if (acno) {
          rows.push('<div class="mk-dw-bankkv" style="grid-column:1/-1;"><button type="button" class="mk-ui-btn mk-ui-btn-outline mk-ui-copy-inline" data-copy="' + escHtml(acno) + '"><i class="fa fa-files-o" aria-hidden="true"></i> Copy Account No</button></div>');
        }
        if (instructions) rows.push('<div class="mk-dw-bankkv" style="grid-column:1/-1;"><span>Instructions</span><strong>' + escHtml(instructions) + '</strong></div>');
        if (notes) rows.push('<div class="mk-dw-bankkv" style="grid-column:1/-1;"><span>Notes</span><strong>' + escHtml(notes) + '</strong></div>');
        if (url) rows.push('<div class="mk-dw-bankkv" style="grid-column:1/-1;"><span>URL</span><strong>' + escHtml(url) + '</strong></div>');
        bankBox.innerHTML = '<div class="mk-dw-bankgrid">' + (rows.length ? rows.join('') : '<div class="mk-dw-empty">No details available.</div>') + '</div>';
        bankBox.style.display = '';
      }
      if (btnCopy) btnCopy.disabled = true;
      if (btnDownload) btnDownload.disabled = true;
      if (addrText) addrText.style.display = 'none';
      return;
    }

    if (channel === 'upi') {
      if (upiBox) {
        var rows = '';
        function row(lbl, val) {
          if (!val) return;
          rows += ''
            + '<div class="mk-dw-upiitem">'
            +   '<div>'
            +     '<div class="mk-dw-upilabel">' + escHtml(lbl) + '</div>'
            +     '<div class="mk-dw-upivalue">' + escHtml(val) + '</div>'
            +   '</div>'
            +   '<button type="button" class="mk-dw-upiiconbtn mk-copy-icon" data-copy="' + escHtml(val) + '" aria-label="Copy">'
            +     '<i class="fa fa-copy" aria-hidden="true"></i>'
            +   '</button>'
            + '</div>';
        }
        row('UPI ID', upi || addr);
        if (qr) {
          rows += ''
            + '<button type="button" class="mk-dw-viewqr" id="mkDwViewQrBtn">'
            +   'VIEW OR DOWNLOAD QR CODE'
            +   '<i class="fa fa-qrcode" aria-hidden="true"></i>'
            + '</button>';
        }
        upiBox.innerHTML = rows || '<div class="mk-dw-empty">No details available.</div>';
        upiBox.style.display = '';
      }
      if (qrBox) qrBox.style.display = 'none';
      if (btnCopy) btnCopy.disabled = true;
      if (btnDownload) btnDownload.disabled = true;
      if (addrText) addrText.style.display = 'none';
      if (rightLabel) rightLabel.textContent = 'UPI';
      if (netChip) netChip.textContent = 'UPI';
      if (qrImg) {
        qrImg.src = qr || '';
        qrImg.style.opacity = qr ? '1' : '0.35';
      }
      currentCopyMsg = 'UPI ID copied';
      return;
    }

    if (channel === 'wallet') {
      if (rightLabel) rightLabel.textContent = wallet ? wallet : 'Crypto Currency';
      if (netChip) netChip.textContent = fmtNet(net) || (wallet ? wallet : (label || (opt ? (opt.textContent || '-') : '-')));
      if (addrText) {
        addrText.textContent = addr || '';
        addrText.style.display = addr ? '' : 'none';
      }
      if (qrImg) {
        qrImg.src = qr || '';
        qrImg.style.opacity = qr ? '1' : '0.35';
      }
      if (btnCopy) btnCopy.disabled = !addr;
      if (btnDownload) btnDownload.disabled = !qr;
      if (qrBox) qrBox.style.display = '';
      currentCopyMsg = 'Wallet address copied';
      return;
    }

    if (bankBox) {
      var content = [];
      if (label) content.push('<div class="mk-dw-bankkv"><span>Method</span><strong>' + escHtml(label) + '</strong></div>');
      if (instructions) content.push('<div class="mk-dw-bankkv" style="grid-column:1/-1;"><span>Instructions</span><strong>' + escHtml(instructions) + '</strong></div>');
      if (notes) content.push('<div class="mk-dw-bankkv" style="grid-column:1/-1;"><span>Notes</span><strong>' + escHtml(notes) + '</strong></div>');
      if (url) content.push('<div class="mk-dw-bankkv" style="grid-column:1/-1;"><span>URL</span><strong>' + escHtml(url) + '</strong></div>');
      bankBox.innerHTML = '<div class="mk-dw-bankgrid">' + (content.length ? content.join('') : '<div class="mk-dw-empty">No details available.</div>') + '</div>';
      bankBox.style.display = '';
    }
    if (btnCopy) btnCopy.disabled = true;
    if (btnDownload) btnDownload.disabled = true;
    if (addrText) addrText.style.display = 'none';
  }

  var quick = document.querySelectorAll('.mk-dw-quick-btn');
  for (var q = 0; q < quick.length; q++) {
    quick[q].addEventListener('click', function() {
      if (!amount) return;
      var add = Number(this.getAttribute('data-add') || 0);
      var cur = Number(amount.value || 0);
      amount.value = String((isFinite(cur) ? cur : 0) + (isFinite(add) ? add : 0));
    });
  }

  if (next) {
    next.addEventListener('click', function() {
      setStepMsg('');
      var a = Number(amount && amount.value ? amount.value : 0);
      if (!isFinite(a) || a <= 0) {
        setStepMsg('Please enter valid amount.');
        return;
      }
      setBtnLoading(next, true, 'NEXT STEP');
      setTimeout(function() {
        if (amountLabel) amountLabel.textContent = String(a);
        if (amountBar) amountBar.value = String(a);
        if (card) card.classList.add('is-step2');
        if (methodSel && !methodSel.value) {
          var pref = getOptionsForMerchant('upi');
          if (pref.length) { setMerchantActive('upi'); methodSel.value = pref[0].value; }
          else {
            pref = getOptionsForMerchant('crypto');
            if (pref.length) { setMerchantActive('crypto'); methodSel.value = pref[0].value; }
            else {
              if (methodSel.options && methodSel.options.length > 1) methodSel.selectedIndex = 1;
            }
          }
        } else if (methodSel) {
          setMerchantActive(merchantForOption(methodSel.options[methodSel.selectedIndex]));
        }
        applySelectedMethod();
        renderMethodCards();
        if (step2 && step2.scrollIntoView) step2.scrollIntoView({behavior:'smooth', block:'start'});
        setBtnLoading(next, false);
      }, 260);
    });
  }

  if (back) {
    back.addEventListener('click', function() {
      setMsg('', false);
      setStepMsg('');
      if (card) card.classList.remove('is-step2');
      if (step1 && step1.scrollIntoView) step1.scrollIntoView({behavior:'smooth', block:'start'});
    });
  }

  if (methodSel) {
    methodSel.addEventListener('change', function() {
      applySelectedMethod();
      renderMethodCards();
    });
  }

  for (var mb = 0; mb < merchantBtns.length; mb++) {
    merchantBtns[mb].addEventListener('click', function() {
      var m = this.getAttribute('data-merchant') || 'upi';
      setMerchantActive(m);
      var opts = getOptionsForMerchant(m);
      if (methodSel && opts.length) {
        methodSel.value = opts[0].value;
        applySelectedMethod();
      } else {
        if (methodSel) methodSel.value = '';
      }
      renderMethodCards();
    });
  }

  if (methodCards) {
    methodCards.addEventListener('click', function(e) {
      var btn = e && e.target && e.target.closest ? e.target.closest('.mk-dw-method') : null;
      if (!btn) return;
      var id = btn.getAttribute('data-id') || '';
      if (!id || !methodSel) return;
      methodSel.value = id;
      applySelectedMethod();
      renderMethodCards();
    });
  }

  if (btnCopy) {
    btnCopy.addEventListener('click', function() {
      copyText(currentAddr, currentCopyMsg);
    });
  }

  document.addEventListener('click', function(e) {
    var btn = e.target && e.target.closest ? e.target.closest('.mk-ui-copy-inline') : null;
    if (!btn) return;
    var val = btn.getAttribute('data-copy') || '';
    copyText(val, 'Bank account copied');
  });

  document.addEventListener('click', function(e) {
    var btn = e.target && e.target.closest ? e.target.closest('.mk-copy-icon') : null;
    if (!btn) return;
    var val = btn.getAttribute('data-copy') || '';
    copyText(val, 'Copied');
  });

  document.addEventListener('click', function(e) {
    var btn = e.target && e.target.closest ? e.target.closest('#mkDwViewQrBtn') : null;
    if (!btn) return;
    if (qrBox) qrBox.style.display = '';
    if (qrBox && qrBox.scrollIntoView) qrBox.scrollIntoView({behavior:'smooth', block:'start'});
  });

  if (btnDownload) {
    btnDownload.addEventListener('click', function() {
      if (!qrImg || !qrImg.src) return;
      setBtnLoading(btnDownload, true);
      var a = document.createElement('a');
      a.href = qrImg.src;
      a.download = 'payment-qr';
      document.body.appendChild(a);
      a.click();
      a.remove();
      setTimeout(function() { setBtnLoading(btnDownload, false); }, 420);
    });
  }

  if (proof) {
    proof.addEventListener('change', function() {
      if (!proofLabel) return;
      var f = proof.files && proof.files[0] ? proof.files[0] : null;
      proofLabel.textContent = f ? 'SCREENSHOT ATTACHED' : 'ATTACH SCREENSHOT';
    });
  }

  if (qrImg) {
    qrImg.addEventListener('error', function() {
      if (currentQrAlt && qrImg.src !== currentQrAlt) {
        qrImg.src = currentQrAlt;
        return;
      }
      try {
        var s = String(qrImg.src || '');
        if (s.indexOf('/admin/uploads/qr/') === -1 && s.indexOf('/uploads/qr/') !== -1) {
          qrImg.src = s.replace('/uploads/qr/', '/admin/uploads/qr/');
          return;
        }
      } catch (e) {
      }
    });
  }

  if (submit) {
    submit.addEventListener('click', function() {
      setMsg('', false);
      var a = Number(amount && amount.value ? amount.value : 0);
      var m = methodSel ? methodSel.value : '';
      var t = (txn && txn.value ? txn.value : '').trim();
      var f = proof && proof.files && proof.files[0] ? proof.files[0] : null;
      if (!m) return setMsg('Select a payment method.', true);
      if (!isFinite(a) || a <= 0) return setMsg('Enter valid amount.', true);
      if (!t) return setMsg('Enter Txn ID.', true);
      if (!f) return setMsg('Upload payment proof.', true);

      setBtnLoading(submit, true, 'DEPOSIT');
      var fd = new FormData();
      fd.append('method_id', m);
      fd.append('amount', String(a));
      fd.append('txn_id', t);
      fd.append('proof_file', f);

      fetch('<?php echo htmlspecialchars($base_url); ?>api/submit_deposit.php', { method:'POST', body: fd, credentials:'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(json){
          if (json && json.success) {
            setMsg(json.message || 'Submitted.', false);
            if (txn) txn.value = '';
            if (proof) proof.value = '';
          } else {
            setMsg((json && json.message) ? json.message : 'Failed.', true);
          }
        })
        .catch(function(){ setMsg('Network error.', true); })
        .finally(function(){ setBtnLoading(submit, false); });
    });
  }

  function showWdView(which) {
    if (wdListView) wdListView.style.display = which === 'list' ? '' : 'none';
    if (wdEditView) wdEditView.style.display = which === 'edit' ? '' : 'none';
  }

  function setWdSlot(slot) {
    wdActiveSlot = slot;
    if (wdTitle) wdTitle.textContent = 'BANK ' + String(slot);
    setWdMsg('', false);

    var existing = wdBanks && wdBanks[String(slot)] ? wdBanks[String(slot)] : null;
    wdSelectedBank = existing && existing.bank_name ? String(existing.bank_name) : '';
    if (wdIfsc) wdIfsc.value = existing && existing.ifsc_swift ? String(existing.ifsc_swift) : '';
    if (wdAccNo) wdAccNo.value = existing && existing.account_no ? String(existing.account_no) : '';
    if (wdAccName) wdAccName.value = existing && existing.account_holder ? String(existing.account_holder) : '';
    if (wdBankSelectText) wdBankSelectText.textContent = wdSelectedBank ? wdSelectedBank : 'Select One';
    if (wdSaveBtn) wdSaveBtn.textContent = existing ? 'UPDATE BANK' : 'ADD BANK';
  }

  function renderWdSlotButton(slot, bank) {
    if (bank && bank.bank_name) {
      var acc = String(bank.account_no || '');
      var last4 = acc ? acc.slice(Math.max(0, acc.length - 4)) : '';
      return ''
        + '<div class="mk-wd-slot-info">'
        +   '<div class="mk-wd-slot-title">BANK ' + escHtml(slot) + '</div>'
        +   '<div class="mk-wd-slot-sub">' + escHtml(bank.bank_name || '') + '</div>'
        +   (last4 ? '<div class="mk-wd-slot-sub">XXXX' + escHtml(last4) + '</div>' : '')
        + '</div>'
        + '<span class="mk-wd-item-btn">EDIT BANK</span>';
    }
    return '<span class="mk-wd-item-btn">TAP TO ADD A BANK</span>';
  }

  function updateWdSlotUI(slot) {
    var btn = document.querySelector('.mk-wd-slot[data-slot="' + String(slot) + '"]');
    if (!btn) return;
    var b = wdBanks && wdBanks[String(slot)] ? wdBanks[String(slot)] : null;
    btn.innerHTML = renderWdSlotButton(slot, b);
    btn.classList.toggle('mk-wd-empty', !b);
    btn.setAttribute('aria-label', b ? 'Edit bank' : 'Tap to add a bank');
  }

  function refreshWdReqSlots() {
    if (!wdReqSlot) return;
    var selected = wdReqSlot.value || '';
    var html = '<option value="">Select</option>';
    var any = false;
    for (var s = 1; s <= 3; s++) {
      var b = wdBanks && wdBanks[String(s)] ? wdBanks[String(s)] : null;
      if (!b || !b.bank_name) continue;
      any = true;
      var acc = String(b.account_no || '');
      var last4 = acc ? acc.slice(Math.max(0, acc.length - 4)) : '';
      var label = 'BANK ' + s + ' - ' + String(b.bank_name || '');
      if (last4) label += ' (XXXX' + last4 + ')';
      html += '<option value="' + String(s) + '">' + escHtml(label) + '</option>';
    }
    wdReqSlot.innerHTML = html;
    if (selected && wdReqSlot.querySelector('option[value="' + selected + '"]')) wdReqSlot.value = selected;
    if (!any) wdReqSlot.value = '';
  }

  function openBankModal() {
    if (!bankModal || !bankList) return;
    if (bankModal.style.display !== 'none') {
      closeBankModal();
      return;
    }
    if (!bankModalAttached && bankModal.parentNode !== document.body) {
      document.body.appendChild(bankModal);
      bankModalAttached = true;
    }
    bankModal.style.display = '';
    positionBankModal();
    if (!bankModalPosBound) {
      window.addEventListener('resize', positionBankModal, { passive: true });
      window.addEventListener('scroll', positionBankModal, true);
      bankModalPosBound = true;
    }
    renderBankList('');
    if (bankSearch) {
      bankSearch.value = '';
      setTimeout(function() { try { bankSearch.focus(); } catch (e) {} }, 0);
    }
  }

  function closeBankModal() {
    if (!bankModal) return;
    bankModal.style.display = 'none';
    if (bankSearch) bankSearch.value = '';
    if (bankEmpty) bankEmpty.style.display = 'none';
    if (bankModalPosBound) {
      window.removeEventListener('resize', positionBankModal, { passive: true });
      window.removeEventListener('scroll', positionBankModal, true);
      bankModalPosBound = false;
    }
  }

  var bankRendered = false;
  function renderBankList(q) {
    if (!bankList) return;
    var query = String(q || '').trim().toLowerCase();
    if (!bankRendered) {
      if (!bankNames.length) {
        bankList.innerHTML = '';
        if (bankEmpty) {
          bankEmpty.textContent = 'No banks available.';
          bankEmpty.style.display = '';
        }
        bankRendered = true;
        return;
      }
      var html = '';
      for (var i = 0; i < bankNames.length; i++) {
        var name = String(bankNames[i] || '').trim();
        if (!name) continue;
        html += '<button type="button" class="mk-bank-item" data-name="' + escHtml(name) + '">' + escHtml(name) + '</button>';
      }
      bankList.innerHTML = html || '';
      bankRendered = true;
    }
    var kids = bankList.children;
    var any = false;
    for (var k = 0; k < kids.length; k++) {
      var el = kids[k];
      if (!el || !el.getAttribute) continue;
      var nm = (el.getAttribute('data-name') || '').toLowerCase();
      var ok = !query || nm.indexOf(query) !== -1;
      el.style.display = ok ? '' : 'none';
      if (ok) any = true;
    }
    if (bankEmpty) {
      if (query && !any) {
        bankEmpty.textContent = 'No matching bank found.';
        bankEmpty.style.display = '';
      } else {
        bankEmpty.style.display = 'none';
      }
    }
  }

  var bankSearchTimer = null;
  if (bankSearch) {
    bankSearch.addEventListener('input', function() {
      if (bankSearchTimer) clearTimeout(bankSearchTimer);
      var val = bankSearch.value;
      bankSearchTimer = setTimeout(function() { renderBankList(val); }, 90);
    });
  }

  if (bankClose) bankClose.addEventListener('click', closeBankModal);
  document.addEventListener('click', function(e) {
    if (!bankModal || bankModal.style.display === 'none') return;
    var t = e && e.target ? e.target : null;
    if (!t) return;
    if (wdBankSelect && (t === wdBankSelect || (wdBankSelect.contains && wdBankSelect.contains(t)))) return;
    if (bankModal.contains && bankModal.contains(t)) return;
    closeBankModal();
  });
  document.addEventListener('keydown', function(e) {
    if (!bankModal || bankModal.style.display === 'none') return;
    if (e && (e.key === 'Escape' || e.key === 'Esc')) closeBankModal();
  });

  if (bankList) {
    bankList.addEventListener('click', function(e) {
      var btn = e && e.target && e.target.closest ? e.target.closest('.mk-bank-item') : null;
      if (!btn) return;
      var name = (btn.getAttribute('data-name') || '').trim();
      if (!name) return;
      wdSelectedBank = name;
      if (wdBankSelectText) wdBankSelectText.textContent = name;
      closeBankModal();
    });
  }

  if (wdBankSelect) wdBankSelect.addEventListener('click', openBankModal);
  if (wdBackBtn) wdBackBtn.addEventListener('click', function() { showWdView('list'); });

  document.addEventListener('click', function(e) {
    var slotBtn = e && e.target && e.target.closest ? e.target.closest('.mk-wd-slot') : null;
    if (!slotBtn) return;
    var slot = parseInt(slotBtn.getAttribute('data-slot') || '0', 10);
    if (!slot || slot < 1 || slot > 3) return;
    var pill = slotBtn.querySelector ? slotBtn.querySelector('.mk-wd-item-btn') : null;
    slotBtn.disabled = true;
    if (pill) setPillLoading(pill, true);
    else setBtnLoading(slotBtn, true);
    setTimeout(function() {
      setWdSlot(slot);
      showWdView('edit');
      if (pill) setPillLoading(pill, false);
      else setBtnLoading(slotBtn, false);
      slotBtn.disabled = false;
      if (wdEditView && wdEditView.scrollIntoView) wdEditView.scrollIntoView({behavior:'smooth', block:'start'});
    }, 220);
  });

  if (wdSaveBtn) {
    wdSaveBtn.addEventListener('click', function() {
      setWdMsg('', false);
      var ifsc = (wdIfsc && wdIfsc.value ? wdIfsc.value : '').trim().toUpperCase();
      var accNo = (wdAccNo && wdAccNo.value ? wdAccNo.value : '').replace(/\s+/g, '');
      var holder = (wdAccName && wdAccName.value ? wdAccName.value : '').trim();
      var bankName = (wdSelectedBank || '').trim();

      if (!ifsc) return setWdMsg('Enter IFSC/SWIFT.', true);
      if (!bankName) return setWdMsg('Select a bank.', true);
      if (!accNo) return setWdMsg('Enter account number.', true);
      if (!holder) return setWdMsg('Enter account holder name.', true);

      setBtnLoading(wdSaveBtn, true, (wdSaveBtn.textContent || '').trim() || 'SAVE');
      var fd = new FormData();
      fd.append('bank_slot', String(wdActiveSlot));
      fd.append('bank_name', bankName);
      fd.append('ifsc_swift', ifsc);
      fd.append('account_no', accNo);
      fd.append('account_holder', holder);

      fetch('<?php echo htmlspecialchars($base_url); ?>api/save_withdraw_bank.php', { method:'POST', body: fd, credentials:'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(json){
          if (json && json.success && json.bank) {
            wdBanks[String(wdActiveSlot)] = json.bank;
            updateWdSlotUI(wdActiveSlot);
            refreshWdReqSlots();
            showToast('Bank saved');
            showWdView('list');
          } else {
            setWdMsg((json && json.message) ? json.message : 'Failed.', true);
          }
        })
        .catch(function(){ setWdMsg('Network error.', true); })
        .finally(function(){ setBtnLoading(wdSaveBtn, false); });
    });
  }

  refreshWdReqSlots();

  if (wdReqBtn) {
    wdReqBtn.addEventListener('click', function() {
      setWdReqMsg('', false);
      var slot = parseInt(wdReqSlot && wdReqSlot.value ? wdReqSlot.value : '0', 10);
      var amt = Number(wdReqAmount && wdReqAmount.value ? wdReqAmount.value : 0);
      if (!slot || slot < 1 || slot > 3) return setWdReqMsg('Select a bank.', true);
      if (!isFinite(amt) || amt <= 0) return setWdReqMsg('Enter valid amount.', true);

      setBtnLoading(wdReqBtn, true, 'REQUEST');
      var fd = new FormData();
      fd.append('bank_slot', String(slot));
      fd.append('amount', String(amt));
      fetch('<?php echo htmlspecialchars($base_url); ?>api/submit_withdrawal.php', { method:'POST', body: fd, credentials:'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(json){
          if (json && json.success) {
            setWdReqMsg(json.message || 'Submitted.', false);
            if (wdReqAmount) wdReqAmount.value = '';
            showToast('Withdraw request sent');
          } else {
            setWdReqMsg((json && json.message) ? json.message : 'Failed.', true);
          }
        })
        .catch(function(){ setWdReqMsg('Network error.', true); })
        .finally(function(){ setBtnLoading(wdReqBtn, false); });
    });
  }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
