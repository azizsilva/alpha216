<?php
$base_url = isset($base_url) ? $base_url : ((isset($absolute_base_url) ? $absolute_base_url : '../'));
$username = isset($username) ? $username : (string)($_SESSION['username'] ?? '');
$user_balance = isset($user_balance) ? $user_balance : 0.00;
$user_exposure = isset($user_exposure) ? $user_exposure : 0.00;
$is_logged_in = isset($is_logged_in) ? $is_logged_in : isset($_SESSION['user_id']);
$is_guest_or_demo = ($username === 'Guest' || stripos($username, 'demo') !== false);
$site_logo = '';
if (isset($web_settings) && isset($web_settings['site_logo'])) {
    $site_logo = (string)$web_settings['site_logo'];
}
if ($site_logo === '') {
    $site_logo = 'https://tanitbet216.com/tanitbet216.png';
}
$langs = [
    'en' => 'ENGLISH', 'hi' => 'हिन्दी', 'ta' => 'தமிழ்', 'te' => 'తెలుగు',
    'kn' => 'ಕನ್ನಡ', 'mr' => 'मराठी', 'gu' => 'ગુજરાતી', 'bn' => 'বাংলা', 'ml' => 'മലയാളം'
];
$curr = $_SESSION['language'] ?? 'en';
$req_path = strtolower((string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? ''));
$mk_section_title = 'PROFILE';
if (strpos($req_path, 'account-details') !== false) $mk_section_title = 'ACCOUNT DETAILS';
elseif (strpos($req_path, 'account-statement') !== false) $mk_section_title = 'ACCOUNT STATEMENT';
elseif (strpos($req_path, 'profit-loss') !== false) $mk_section_title = 'PROFIT AND LOSS';
elseif (strpos($req_path, 'bet-history') !== false) $mk_section_title = 'BET HISTORY';
elseif (strpos($req_path, 'activity-log') !== false) $mk_section_title = 'ACTIVITY LOG';
?>

<style>
html { margin: 0; padding: 0; }
body { margin: 0; }
html,
body.mk-account-mode {
    width: 100%;
    min-height: 100%;
    overflow-x: hidden;
}
body.mk-account-mode .custom-navbar,
body.mk-account-mode .secondary-nav,
body.mk-account-mode #mobile-footer-nav,
body.mk-account-mode #mobileLeftSidebar { display: none !important; }
body.mk-account-mode { padding-top: 0 !important; }
body.mk-account-mode { margin-top: 0 !important; }
body.mk-account-mode {
    background: #000 !important;
    color: #f8fafc !important;
}
body.mk-account-mode #mkApp {
    margin: 0 !important;
    padding: 0 !important;
}
body.mk-account-mode .mk-account-page {
    margin-top: 0 !important;
    padding-top: 56px !important;
}
body.mk-account-mode #userPopup.user-popup-container {
    top: 0 !important;
    height: 100vh !important;
    z-index: 10070 !important;
}
body.mk-account-mode #sidebarOverlay.sidebar-overlay {
    top: 0 !important;
    height: 100vh !important;
    z-index: 10065 !important;
}

.mk-profile-header {
    position: fixed !important;
    top: 0;
    left: 0;
    right: 0;
    height: 56px;
    background: #000;
    border-bottom: 1px solid rgba(195,118,1,0.65);
    z-index: 10060;
}
.mk-profile-header .mk-mobile-section-wrap {
    display: none;
}
    .mk-profile-header .mk-mobile-section-title {
        display: none;
    }
.mk-profile-header .mk-ph-inner {
    height: 100%;
    padding: 0 10px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}
.mk-profile-header .mk-ph-left {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}
.mk-profile-header .mk-ph-logo img {
    height: 42px;
    width: auto;
    display: block;
}
.mk-profile-header .mk-ph-dashboard {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    color: #fff;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
    user-select: none;
}
.mk-profile-header .mk-ph-dashboard i {
    color: #c37601;
    font-size: 18px;
}
.mk-profile-header .mk-ph-search {
    width: min(520px, 44vw);
    max-width: 520px;
    min-width: 240px;
    position: relative;
    margin-left: 6px;
}
.mk-profile-header .mk-ph-search input {
    width: 100%;
    height: 34px;
    background: #181818;
    border: 1px solid #c37601;
    border-radius: 4px;
    outline: none;
    color: #fff;
    padding: 5px 34px 5px 10px;
    font-weight: 700;
    font-size: 13px;
    line-height: 34px;
}
.mk-profile-header .mk-ph-search i {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: #c37601;
    font-size: 15px;
    pointer-events: none;
}
.mk-profile-header .mk-ph-right {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 0 0 auto;
}
.mk-profile-header .mk-ph-deposit {
    background: #c37601;
    color: #fff;
    border: none;
    border-radius: 4px;
    padding: 6px 14px;
    height: 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 900;
    text-transform: uppercase;
    text-decoration: none;
    letter-spacing: 0.4px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.35);
    transition: background 0.2s, transform 0.12s, box-shadow 0.2s;
}
.mk-profile-header .mk-ph-deposit:hover {
    background: #d48632;
    color: #fff;
}
.mk-profile-header .mk-ph-deposit:active {
    transform: scale(0.98);
}
.mk-profile-header .mk-ph-deposit:focus,
.mk-profile-header .mk-ph-deposit:active:focus {
    outline: none;
    box-shadow: 0 2px 6px rgba(0,0,0,0.35);
}
.mk-profile-header .mk-ph-balbox {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 157px;
    text-align: right;
    padding-top: 2px;
}
.mk-profile-header .lang-box {
    flex: 0 0 auto;
}
.mk-profile-header .custom-lang-dropdown {
    width: 157px;
    height: 34px;
}
.mk-profile-header .selected-view {
    border-radius: 4px;
}
.mk-profile-header .current-text {
    min-width: 0;
    padding: 0 8px;
    text-align: center;
}
.mk-profile-header #mkLangDropdown .selected-view {
    height: 34px;
}
.mk-profile-header #mkLangDropdown .current-text {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 34px;
    line-height: 34px;
    color: #fff;
    font-size: 14px;
    font-weight: 800;
    flex: 1 !important;
    opacity: 1 !important;
    visibility: visible !important;
}
.mk-profile-header #mkLangDropdown .selected-view {
    display: flex !important;
    background: #000 !important;
    border: 1px solid #c37601 !important;
}
.mk-profile-header #mkLangDropdown .icon-box {
    background: #c37601 !important;
    color: #fff !important;
}
.mk-profile-header #mkLangDropdown .dropdown-list {
    top: 36px;
    right: 0;
    left: auto;
    z-index: 10071;
}
.mk-profile-header .mk-ph-ci-row {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 6px;
    line-height: 1;
    font-weight: 900;
    font-size: 12px;
    color: #fff;
}
.mk-profile-header .mk-ph-exp-row,
.mk-profile-header [data-live-balance="exposure"],
.mk-profile-header [data-translate="exp"] {
    display: none !important;
    visibility: hidden !important;
}
.mk-profile-header .mk-ph-ci-row .mk-ph-ci-label {
    color: rgba(255,255,255,0.8);
    min-width: 28px;
    text-align: left;
}
.mk-profile-header .mk-ph-lang-select {
    position: relative;
    height: 34px;
    width: 157px;
}
.mk-profile-header .mk-ph-lang-select select {
    width: 100%;
    height: 34px;
    border-radius: 4px;
    border: 1px solid #c37601;
    background: #000;
    color: #fff;
    font-weight: 900;
    padding: 0 28px 0 10px;
    border: none;
    outline: none;
    appearance: none;
    -webkit-appearance: none;
}
.mk-profile-header .mk-ph-lang-select i {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: #fff;
    pointer-events: none;
    font-size: 14px;
}
.mk-profile-header .mk-ph-apk a {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 34px;
}
.mk-profile-header .mk-ph-bell,
.mk-profile-header .mk-ph-userlink {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    height: 34px;
    color: #fff;
    text-decoration: none;
    font-weight: 900;
    white-space: nowrap;
}
.mk-profile-header .mk-ph-bell {
    padding: 0 4px;
}
.mk-profile-header .mk-ph-bell i {
    color: #c37601;
    font-size: 18px;
}
.mk-profile-header .mk-ph-userlink {
    padding: 0 2px;
    max-width: 140px;
}
.mk-profile-header .mk-ph-userlink span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.mk-profile-header .mk-ph-userlink .mk-ph-arrow {
    color: #c37601;
    font-size: 18px;
}

@media (max-width: 992px) {
    .mk-profile-header .mk-ph-search { min-width: 200px; width: min(360px, 36vw); }
    .mk-profile-header .mk-ph-balbox { display: none; }
    .mk-profile-header .custom-lang-dropdown { width: 140px; }
}
@media (max-width: 767px) {
    body.mk-account-mode .mk-account-page {
        padding-top: 52px !important;
    }
    body.mk-account-mode { padding-top: 0 !important; }
    .mk-profile-header { height: 52px; }
    .mk-profile-header .mk-ph-inner {
        position: relative;
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        justify-content: normal;
        align-items: center;
    }
    .mk-profile-header .mk-mobile-section-wrap {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-width: 0;
        max-width: 100%;
        color: #fff;
        cursor: pointer;
        -webkit-tap-highlight-color: transparent;
    }
    .mk-profile-header .mk-mobile-section-wrap i {
        color: #c37601;
        font-size: 14px;
        flex: 0 0 auto;
    }
    .mk-profile-header .mk-mobile-section-title {
        display: block;
        color: #fff;
        font-size: 12px;
        font-weight: 900;
        text-transform: uppercase;
        white-space: nowrap;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .mk-profile-header .mk-ph-logo {
        display: none !important;
    }
    .mk-profile-header .mk-ph-logo img { height: 36px; }
    .mk-profile-header .mk-ph-deposit { padding: 6px 10px; }
    .mk-profile-header .mk-ph-search,
    .mk-profile-header .mk-ph-dashboard,
    .mk-profile-header .mk-ph-balbox,
    .mk-profile-header .lang-box,
    .mk-profile-header .mk-ph-bell,
    .mk-profile-header .mk-ph-userlink .mk-ph-arrow { display: none !important; }
    .mk-profile-header .custom-lang-dropdown { width: 105px; }
    .mk-profile-header .mk-ph-userlink {
        display: none !important;
        max-width: 100%;
        padding-right: 0;
        justify-content: flex-end;
        width: 100%;
    }
    .mk-profile-header .mk-ph-userlink span {
        max-width: 96px;
        text-align: right;
    }
    .mk-profile-header .mk-ph-inner { gap: 6px; padding: 0 8px; }
    .mk-profile-header .mk-ph-left {
        gap: 0;
        min-width: 0;
        grid-column: 1;
    }
    .mk-profile-header .mk-ph-dashboard { gap: 7px; font-size: 13px; }
    .mk-profile-header .mk-ph-right {
        display: none !important;
    }
    .mk-profile-header .mk-ph-userlink { margin-left: auto; }
    .mk-profile-header .mk-ph-userlink,
    .mk-profile-header .mk-ph-userlink span {
        direction: rtl;
        text-align: right;
    }
}
</style>

<div class="mk-profile-header">
    <div class="mk-ph-inner">
        <div class="mk-ph-left">
            <span class="mk-mobile-section-wrap" id="mkProfileBackBtn" role="button" tabindex="0" aria-label="Back">
                <i class="fa fa-chevron-left" aria-hidden="true"></i>
                <span class="mk-mobile-section-title" id="mkMobileSectionTitle"><?php echo htmlspecialchars($mk_section_title); ?></span>
            </span>
            <a class="mk-ph-logo" href="<?php echo htmlspecialchars($base_url); ?>index.php">
                <img src="<?php echo htmlspecialchars($site_logo); ?>" alt="Logo">
            </a>
            <a class="mk-ph-dashboard" href="<?php echo htmlspecialchars($base_url); ?>index.php">
                <i class="fa fa-chevron-left"></i>
                <span data-translate="dashboard">DASHBOARD</span>
            </a>
            <div class="mk-ph-search">
                <input class="completer-input" type="search" placeholder="Search by Event/Game" data-translate="search_placeholder" />
                <i class="fa fa-search" aria-hidden="true"></i>
            </div>
        </div>

        <div class="mk-ph-right">
            <div class="mk-ph-balbox">
                <div class="mk-ph-ci-row">
                    <span class="mk-ph-ci-val" data-live-balance="available"><?php echo number_format((float)$user_balance, 2); ?></span>
                    <span class="mk-ph-ci-label">TND</span>
                </div>
            </div>

            <div class="lang-box">
                <div class="custom-lang-dropdown" id="mkLangDropdown">
                    <div class="selected-view">
                        <span class="current-text" id="mkCurrentLang" data-default-lang="<?php echo htmlspecialchars($langs[$curr] ?? 'ENGLISH'); ?>" data-init-lang-code="<?php echo htmlspecialchars($curr); ?>"><?php echo htmlspecialchars($langs[$curr] ?? 'ENGLISH'); ?></span>
                        <div class="icon-box"><i class="fa fa-caret-down"></i></div>
                    </div>
                    <div class="dropdown-list">
                        <ul id="mkLangList">
                            <?php foreach ($langs as $code => $name): ?>
                                <li data-lang="<?php echo htmlspecialchars($code); ?>" <?php echo $curr === $code ? 'class="active-lang"' : ''; ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <a href="#" class="mk-ph-bell" aria-label="Notifications"><i class="fa fa-bell"></i></a>

            <a href="#" class="mk-ph-userlink" aria-label="Profile Menu" onclick="if (typeof openSidebar === 'function') openSidebar(); return false;">
                <span><?php echo htmlspecialchars($username); ?></span>
                <i class="fa fa-chevron-right mk-ph-arrow"></i>
            </a>
        </div>
    </div>
</div>

<script>
(function(){
    function isProfilePath(path) {
        path = String(path || '').toLowerCase();
        return path.indexOf('/account-details') === 0 ||
            path.indexOf('/account-statement') === 0 ||
            path.indexOf('/profit-loss') === 0 ||
            path.indexOf('/bet-history') === 0 ||
            path.indexOf('/activity-log') === 0;
    }
    function profileBack() {
        try {
            var target = sessionStorage.getItem('mk_last_non_profile_path') || '';
            if (!target && document.referrer) {
                var ref = new URL(document.referrer, window.location.origin);
                if (ref.origin === window.location.origin && !isProfilePath(ref.pathname)) {
                    target = ref.pathname + ref.search + ref.hash;
                }
            }
            if (!target || isProfilePath((new URL(target, window.location.origin)).pathname)) target = '/';
            window.location.href = target;
        } catch (e) {
            window.location.href = '/';
        }
    }
    function centerProfileTab(link) {
        try {
            var menu = link && link.closest ? link.closest('.mk-side-menu') : null;
            if (!menu || menu.scrollWidth <= menu.clientWidth) return;
            var left = link.offsetLeft - (menu.clientWidth / 2) + (link.offsetWidth / 2);
            menu.scrollTo({ left: Math.max(0, left), behavior: 'smooth' });
        } catch (e) {}
    }
    function initProfileTabs() {
        try {
            var active = document.querySelector('.mk-side-menu li.active a');
            var title = document.getElementById('mkMobileSectionTitle');
            if (active && title) {
                var txt = active.querySelector('span') ? active.querySelector('span').textContent : active.textContent;
                if (txt && txt.trim()) title.textContent = txt.trim();
            }
            if (active) setTimeout(function(){ centerProfileTab(active); }, 80);
            var links = document.querySelectorAll('.mk-side-menu a');
            Array.prototype.forEach.call(links, function(a){
                if (a.getAttribute('data-mk-tab-bound') === '1') return;
                a.setAttribute('data-mk-tab-bound', '1');
                a.addEventListener('click', function(){
                    var t = document.getElementById('mkMobileSectionTitle');
                    var s = a.querySelector('span') ? a.querySelector('span').textContent : a.textContent;
                    if (t && s && s.trim()) t.textContent = s.trim();
                    centerProfileTab(a);
                }, true);
            });
        } catch (e) {}
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initProfileTabs);
    else initProfileTabs();
    try {
        var backBtn = document.getElementById('mkProfileBackBtn');
        if (backBtn && backBtn.getAttribute('data-mk-back-bound') !== '1') {
            backBtn.setAttribute('data-mk-back-bound', '1');
            backBtn.addEventListener('click', profileBack);
            backBtn.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    profileBack();
                }
            });
        }
    } catch (eBack) {}
    window.MK_CENTER_PROFILE_TABS = initProfileTabs;
})();
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var dropdown = document.getElementById('mkLangDropdown');
    var currentText = document.getElementById('mkCurrentLang');
    var langList = document.getElementById('mkLangList');

    if (currentText && (!currentText.innerText || !currentText.innerText.trim())) {
        currentText.innerText = currentText.getAttribute('data-default-lang') || 'ENGLISH';
    }

    if (dropdown) {
        dropdown.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('active');
        });
        document.addEventListener('click', function(e) {
            if (!dropdown.contains(e.target)) {
                dropdown.classList.remove('active');
            }
        });
    }

    function setLangUI(code) {
        if (!langList) return;
        var items = langList.getElementsByTagName('li');
        var found = false;
        for (var i = 0; i < items.length; i++) {
            items[i].classList.remove('active-lang');
            if (items[i].getAttribute('data-lang') === code) {
                items[i].classList.add('active-lang');
                if (currentText) currentText.innerText = items[i].innerText;
                found = true;
            }
        }
        if (currentText && (!currentText.innerText || !currentText.innerText.trim())) {
            var fallback = currentText.getAttribute('data-default-lang') || '';
            if (!fallback && items.length) fallback = items[0].innerText;
            currentText.innerText = fallback;
        }
        if (!found && currentText && code && items.length) {
            currentText.innerText = currentText.getAttribute('data-default-lang') || currentText.innerText;
        }
    }

    if (langList) {
        var items = langList.getElementsByTagName('li');
        for (var i = 0; i < items.length; i++) {
            items[i].addEventListener('click', function(e) {
                e.stopPropagation();
                var code = this.getAttribute('data-lang');
                if (typeof changeLanguage === 'function') {
                    changeLanguage(code);
                }
                setLangUI(code);
                if (dropdown) dropdown.classList.remove('active');
            });
        }
    }

    var lang = (typeof isLoggedIn !== 'undefined' && isLoggedIn && typeof sessionLang !== 'undefined' && sessionLang) ? sessionLang : (localStorage.getItem('selected_language') || '');
    if (!lang && currentText) lang = currentText.getAttribute('data-init-lang-code') || 'en';
    if (!lang) lang = 'en';
    setLangUI(lang);
    setTimeout(function() { setLangUI(lang); }, 0);

    if (typeof window.changeLanguage === 'function') {
        var _orig = window.changeLanguage;
        if (!window.__mkProfileHeaderLangPatched) {
            window.__mkProfileHeaderLangPatched = true;
            window.changeLanguage = function(l) {
                _orig(l);
                setLangUI(l);
            };
        }
    }

    function ensureMoney(el) {
        if (!el) return;
        var t = (el.innerText || '').trim();
        var n = Number(String(t).replace(/,/g, ''));
        if (!t || !isFinite(n)) el.innerText = '0.00';
    }
    ensureMoney(document.querySelector('[data-live-balance="available"]'));
});
</script>

