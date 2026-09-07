<?php
$current_page = basename((string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ''));
$staff_name = $_SESSION['staffName'] ?? $_SESSION['staff_name'] ?? 'Admin';
$profile_pic = $profile_pic ?? ($_SESSION['profilePic'] ?? $_SESSION['profile_pic'] ?? '');

if (!function_exists('activeMenu')) {
    function activeMenu(string $pageName, string $currentPage): string
    {
        return $currentPage === $pageName ? 'active' : '';
    }
}

$initial = strtoupper(substr(trim((string)$staff_name), 0, 1));
if ($initial === '') {
    $initial = 'A';
}
?>

<style>
/* =========================================================
   AL AMIN EDU OASIS — SIGNED-IN HEADER (UI HANDOFF V3)
========================================================= */
#appTopbar.topbar {
    position: sticky !important;
    top: 0 !important;
    z-index: 1020 !important;
    width: 100% !important;
    height: 58px !important;
    min-height: 58px !important;
    padding: 0 !important;
    margin: 0 !important;
    display: block !important;
    grid-template-columns: none !important;
    font-weight: 400 !important;
    border-bottom: 1px solid #e2e8f0;
    background: rgba(255,255,255,.96);
    box-shadow: 0 1px 10px rgba(15,23,42,.04);
    backdrop-filter: blur(10px);
    font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

.topbar-inner {
    width: min(100%,1880px) !important;
    height: 100%;
    margin-inline: auto;
    padding-inline: clamp(16px,2.25vw,42px);
    display: flex;
    align-items: center;
    min-width: 0;
}

.brand {
    display: inline-flex;
    align-items: center;
    flex: 0 0 auto;
    min-width: 0;
    margin-right: 24px;
    text-decoration: none;
}

.brand-logo {
    display: block;
    width: auto;
    height: 50px;
    max-width: 56px;
    max-height: 52px;
    object-fit: contain;
    object-position: left center;
}

.topbar-menu {
    display: flex;
    align-items: center;
    height: 100%;
    min-width: 0;
}

.topbar-menu a {
    height: 58px;
    padding: 0 clamp(11px,.95vw,16px);
    border-bottom: 2px solid transparent;
    color: #64748b;
    display: flex;
    align-items: center;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    white-space: nowrap;
    transition: background-color .18s ease,color .18s ease,border-color .18s ease;
}

.topbar-menu a:hover {
    background: #F8FAFC;
    color: #111827;
}

.topbar-menu a.active {
    border-bottom-color: #2563EB;
    background: #EFF6FF;
    color: #2563EB;
}

.topbar-right {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}

.profile-link {
    min-width: 0;
    max-width: clamp(150px,18vw,260px);
    padding-left: 16px;
    border-left: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    color: inherit;
    text-decoration: none;
}

.profile-text {
    min-width: 0;
    text-align: right;
    line-height: 1.12;
}

.profile-text strong {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #0f172a;
    font-size: 12.5px;
    font-weight: 750;
}

.profile-text span {
    display: block;
    margin-top: 2px;
    color: #64748b;
    font-size: 11px;
    font-weight: 600;
}

.profile-img,
.profile-avatar {
    width: 38px;
    height: 38px;
    flex: 0 0 38px;
    border: 1px solid #EFF6FF;
    border-radius: 10px;
    background: #EFF6FF;
}

.profile-img {
    display: block;
    object-fit: cover;
}

.profile-avatar {
    color: #2563EB;
    display: grid;
    place-items: center;
    font-size: 12.5px;
    font-weight: 750;
}

.logout-icon-link {
    width: 40px;
    height: 40px;
    flex: 0 0 40px;
    border-radius: 10px;
    color: #64748b;
    display: grid;
    place-items: center;
    text-decoration: none;
    transition: background .18s ease,color .18s ease;
}

.logout-icon-link:hover {
    background: #F8FAFC;
    color: #334155;
}

.logout-icon-link svg {
    width: 21px;
    height: 21px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.mobile-menu-toggle {
    display: none;
    min-width: 42px;
    min-height: 40px;
    padding: 0 10px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    background: #fff;
    color: #475569;
    font-size: 20px;
    font-weight: 700;
    cursor: pointer;
}

.topbar :is(a,button):focus-visible {
    outline: 3px solid rgba(37,99,235,.3);
    outline-offset: 2px;
}

.mobile-menu-panel {
    display: none;
}

@media (max-width:1199.98px) {
    .topbar-menu a {
        padding-inline: 9px;
        font-size: 13px;
    }

    .brand { margin-right: 16px; }
    .profile-link { max-width: 180px; }
}

@media (max-width:991.98px) {
    #appTopbar.topbar { height: auto !important; min-height: 58px !important; }

    .topbar-inner {
        min-height: 58px;
        padding-inline: 16px;
    }

    .topbar-menu { display: none; }
    .mobile-menu-toggle { display: inline-flex; align-items:center; justify-content:center; }
    .topbar-right { gap: 8px; }

    .mobile-menu-panel {
        border-top: 1px solid #f1f5f9;
        background: #fff;
        padding: 8px 16px 12px;
    }

    .topbar.mobile-open .mobile-menu-panel {
        display: grid;
        grid-template-columns: repeat(3,minmax(0,1fr));
        gap: 6px;
    }

    .mobile-menu-panel a {
        min-height: 40px;
        padding: 9px 11px;
        border-radius: 9px;
        color: #64748b;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 700;
        text-decoration: none;
    }

    .mobile-menu-panel a.active {
        background: #EFF6FF;
        color: #2563EB;
    }
}

@media (max-width:575.98px) {
    .brand-logo { height: 44px; }
    .profile-text { display: none; }
    .profile-link { padding-left: 10px; }
    .topbar-right { gap: 5px; }
    .topbar.mobile-open .mobile-menu-panel { grid-template-columns: repeat(2,minmax(0,1fr)); }
}

@media (prefers-reduced-motion: reduce) {
    #appTopbar.topbar * { transition-duration: .01ms !important; animation-duration: .01ms !important; }
}

/* HIGH-SPECIFICITY HEADER LOCK — prevents older page CSS changing the topbar */
#appTopbar .topbar-inner{height:58px;display:flex;align-items:center;min-width:0}
#appTopbar .brand{display:inline-flex;align-items:center;flex:0 0 auto;min-width:0;margin-right:24px;text-decoration:none}
#appTopbar .brand-logo{display:block;width:auto;height:50px;max-width:56px;max-height:52px;object-fit:contain}
#appTopbar .topbar-menu{display:flex;align-items:center;height:58px;min-width:0}
#appTopbar .topbar-menu a{display:flex;align-items:center;height:58px;padding:0 clamp(11px,.95vw,16px);border-bottom:2px solid transparent;background:transparent;color:#64748b;font-size:14px;font-weight:600;text-decoration:none;white-space:nowrap}
#appTopbar .topbar-menu a.active{border-bottom-color:#2563EB;background:#EFF6FF;color:#2563EB}
#appTopbar .topbar-right{margin-left:auto;display:flex;align-items:center;gap:12px;min-width:0}
#appTopbar .profile-link{min-width:0;max-width:260px;padding-left:16px;border-left:1px solid #e2e8f0;display:flex;align-items:center;justify-content:flex-end;gap:10px;text-decoration:none}
#appTopbar .profile-text{display:block;min-width:0;text-align:right;line-height:1.12}
#appTopbar .profile-text strong{display:block;color:#0f172a;font-size:12px;font-weight:750;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
#appTopbar .profile-text span{display:block;margin-top:2px;color:#64748b;font-size:10.5px;font-weight:600}
#appTopbar .profile-img,#appTopbar .profile-avatar{width:38px;height:38px;min-width:38px;max-width:38px;border:1px solid #EFF6FF;border-radius:10px;background:#EFF6FF}
#appTopbar .profile-avatar{display:grid;place-items:center;color:#2563EB;font-size:12.5px;font-weight:750}
#appTopbar .logout-icon-link{width:40px;height:40px;min-width:40px;display:grid;place-items:center;border-radius:10px;color:#64748b;text-decoration:none}
#appTopbar .logout-icon-link svg{width:21px;height:21px;fill:none;stroke:currentColor;stroke-width:1.8}
#appTopbar .mobile-menu-toggle{display:none}
@media(max-width:991.98px){#appTopbar .topbar-menu{display:none}#appTopbar .mobile-menu-toggle{display:inline-flex;align-items:center;justify-content:center}#appTopbar .topbar-inner{height:58px}#appTopbar .profile-link{max-width:180px}}
@media(max-width:575.98px){#appTopbar .profile-text{display:none}#appTopbar .brand-logo{height:44px}}

/* FINAL HEADER GEOMETRY LOCK — identical on every signed-in page */
#appTopbar.topbar{height:58px!important;min-height:58px!important;padding:0!important;margin:0!important;display:block!important}
#appTopbar .topbar-inner{width:min(100%,1880px)!important;height:58px!important;min-height:58px!important;margin:0 auto!important;padding:0 clamp(16px,2.25vw,42px)!important;display:flex!important;align-items:center!important;box-sizing:border-box!important}
#appTopbar .brand{margin:0 24px 0 0!important;padding:0!important;gap:0!important;flex:0 0 auto!important}
#appTopbar .brand-logo{width:auto!important;height:50px!important;max-width:56px!important;max-height:52px!important;margin:0!important}
#appTopbar .topbar-menu{height:58px!important;display:flex!important;align-items:center!important;justify-content:flex-start!important;gap:0!important;margin:0!important;padding:0!important;flex:0 1 auto!important}
#appTopbar .topbar-menu a{height:58px!important;margin:0!important;padding:0 14px!important;display:flex!important;align-items:center!important;justify-content:center!important;border-width:0 0 2px!important;border-style:solid!important;border-color:transparent!important;background:transparent!important;color:#64748b!important;font-size:14px!important;font-weight:600!important;line-height:1!important;box-sizing:border-box!important}
#appTopbar .topbar-menu a:hover{background:#EFF6FF!important;color:#2563EB!important}
#appTopbar .topbar-menu a.active{background:#EFF6FF!important;color:#2563EB!important;border-bottom-color:#2563EB!important}
#appTopbar .topbar-right{margin-left:auto!important;padding:0!important;display:flex!important;align-items:center!important;justify-content:flex-end!important;gap:12px!important;height:58px!important}
#appTopbar .profile-link{margin:0!important;padding:0 0 0 16px!important;gap:10px!important;height:40px!important;max-width:260px!important;border-left:1px solid #e2e8f0!important}
#appTopbar .profile-img,#appTopbar .profile-avatar{width:38px!important;height:38px!important;min-width:38px!important;max-width:38px!important;border-color:#EFF6FF!important;background:#EFF6FF!important}
#appTopbar .profile-text strong{font-size:11.5px!important;line-height:1.15!important}
#appTopbar .profile-text span{font-size:10px!important;line-height:1.15!important}
#appTopbar .logout-icon-link{width:40px!important;height:40px!important;min-width:40px!important;margin:0!important;padding:0!important}
@media(max-width:1199.98px){#appTopbar .topbar-menu a{padding:0 10px!important;font-size:13px!important}#appTopbar .brand{margin-right:16px!important}}
@media(max-width:991.98px){#appTopbar .topbar-menu{display:none!important}#appTopbar .mobile-menu-toggle{display:inline-flex!important}#appTopbar .topbar-right{height:58px!important}}

/* GLOBAL UI FEEDBACK + VALIDATION */
.required-label::after,label[data-required-marker="1"]::after{content:" *";color:#dc2626;font-weight:800}.is-invalid,input.is-invalid,select.is-invalid,textarea.is-invalid{border-color:#dc2626!important;box-shadow:0 0 0 3px rgba(220,38,38,.10)!important}.inline-field-error{display:block;margin-top:6px;color:#dc2626;font-size:11px;font-weight:650;line-height:1.35}.global-ui-overlay{position:fixed;inset:0;z-index:12000;display:none;align-items:center;justify-content:center;padding:20px;background:rgba(15,23,42,.5);backdrop-filter:blur(5px)}.global-ui-overlay.show{display:flex}.global-ui-dialog{width:min(390px,94vw);border:1px solid #e2e8f0;border-radius:18px;background:#fff;box-shadow:0 26px 70px rgba(15,23,42,.24);padding:24px;text-align:center}.global-ui-dialog-icon{width:48px;height:48px;margin:0 auto 12px;border-radius:50%;display:grid;place-items:center;background:#EFF6FF;color:#2563EB;font-size:22px;font-weight:900}.global-ui-dialog.success .global-ui-dialog-icon{background:#ecfdf5;color:#059669}.global-ui-dialog.error .global-ui-dialog-icon,.global-ui-dialog.confirm .global-ui-dialog-icon{background:#fef2f2;color:#dc2626}.global-ui-dialog h3{margin:0 0 8px;color:#0f172a;font-size:18px;font-weight:800}.global-ui-dialog p{margin:0;color:#64748b;font-size:13px;line-height:1.6}.global-ui-dialog-actions{display:flex;justify-content:center;gap:10px;margin-top:20px}.global-ui-dialog-actions button{min-width:105px;height:40px;border:0;border-radius:10px;color:#fff;font-size:12px;font-weight:800;cursor:pointer}.global-ui-dialog-actions .ui-ok,.global-ui-dialog-actions .ui-confirm{background:#2563EB}.global-ui-dialog-actions .ui-cancel{background:#475569}.global-ui-dialog-actions .ui-confirm-danger{background:#dc2626}body.ui-modal-open{overflow:hidden!important}

/* Logout visibility + modal layer consistency */
#appTopbar .logout-icon-link{width:auto!important;min-width:82px!important;padding:0 10px!important;display:flex!important;gap:7px!important;color:#475569!important;border:1px solid #e2e8f0!important;background:#fff!important}
#appTopbar .logout-icon-link:hover{background:#EFF6FF!important;color:#2563EB!important;border-color:#BFDBFE!important}
#appTopbar .logout-label{display:inline!important;font-size:11.5px!important;font-weight:800!important;white-space:nowrap!important}
body.ui-modal-open #appTopbar,body.modal-open #appTopbar,body.has-modal-open #appTopbar{filter:blur(3px)!important;pointer-events:none!important;visibility:visible!important;opacity:1!important}
body.trainhub-app .modal{z-index:5000!important}
body.trainhub-app :is(.flash-popup-backdrop,.delete-confirm-backdrop,.confirm-popup-backdrop){z-index:5100!important}
@media(max-width:575.98px){#appTopbar .logout-icon-link{min-width:40px!important;width:40px!important;padding:0!important}.logout-label{display:none!important}}

</style>

<header class="topbar" id="appTopbar">
    <div class="topbar-inner">
        <a class="brand" href="dashboard.php" aria-label="Al Amin Edu Oasis dashboard">
            <img class="brand-logo" src="assets/images/al-amin-edu-oasis-logo.png" alt="Al Amin Edu Oasis">
        </a>

        <nav class="topbar-menu" aria-label="Main navigation">
            <a href="dashboard.php" class="<?php echo activeMenu('dashboard.php', $current_page); ?>">Dashboard</a>
            <a href="teacher.php" class="<?php echo activeMenu('teacher.php', $current_page); ?>">Teacher</a>
            <a href="course.php" class="<?php echo activeMenu('course.php', $current_page); ?>">Training</a>
            <a href="trainer.php" class="<?php echo activeMenu('trainer.php', $current_page); ?>">Trainer</a>
            <a href="feedback.php" class="<?php echo activeMenu('feedback.php', $current_page); ?>">Feedback</a>
            <a href="certificate.php" class="<?php echo activeMenu('certificate.php', $current_page); ?>">Certificate</a>
        </nav>

        <div class="topbar-right">
            <button type="button" class="mobile-menu-toggle" id="mobileMenuToggle" aria-expanded="false" aria-controls="mobileMenuPanel">☰</button>

            <a class="profile-link" href="settings.php" title="My Profile">
                <div class="profile-text">
                    <strong><?php echo htmlspecialchars((string)$staff_name, ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span>Administrator</span>
                </div>

                <?php if (!empty($profile_pic)) { ?>
                    <img src="<?php echo htmlspecialchars((string)$profile_pic, ENT_QUOTES, 'UTF-8'); ?>" class="profile-img" alt="Profile">
                <?php } else { ?>
                    <span class="profile-avatar" aria-hidden="true"><?php echo htmlspecialchars($initial, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php } ?>
            </a>

            <a class="logout-icon-link" href="logout.php" aria-label="Log out" title="Log out">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M9 5H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h4"/>
                    <path d="M16 17l5-5-5-5"/>
                    <path d="M21 12H9"/>
                </svg>
                <span class="logout-label">Log out</span>
            </a>
        </div>
    </div>

    <nav class="mobile-menu-panel" id="mobileMenuPanel" aria-label="Mobile navigation">
        <a href="dashboard.php" class="<?php echo activeMenu('dashboard.php', $current_page); ?>">Dashboard</a>
        <a href="teacher.php" class="<?php echo activeMenu('teacher.php', $current_page); ?>">Teacher</a>
        <a href="course.php" class="<?php echo activeMenu('course.php', $current_page); ?>">Training</a>
        <a href="trainer.php" class="<?php echo activeMenu('trainer.php', $current_page); ?>">Trainer</a>
        <a href="feedback.php" class="<?php echo activeMenu('feedback.php', $current_page); ?>">Feedback</a>
        <a href="certificate.php" class="<?php echo activeMenu('certificate.php', $current_page); ?>">Certificate</a>
    </nav>
</header>


<div class="global-ui-overlay" id="globalNoticeModal" aria-hidden="true"><div class="global-ui-dialog" id="globalNoticeDialog" role="dialog" aria-modal="true"><div class="global-ui-dialog-icon" id="globalNoticeIcon">✓</div><h3 id="globalNoticeTitle">Successful</h3><p id="globalNoticeMessage"></p><div class="global-ui-dialog-actions"><button type="button" class="ui-ok" id="globalNoticeOk">OK</button></div></div></div>
<div class="global-ui-overlay" id="globalConfirmModal" aria-hidden="true"><div class="global-ui-dialog confirm" role="dialog" aria-modal="true"><div class="global-ui-dialog-icon">!</div><h3>Are you sure?</h3><p id="globalConfirmMessage">Are you sure you want to continue?</p><div class="global-ui-dialog-actions"><button type="button" class="ui-cancel" id="globalConfirmCancel">Cancel</button><button type="button" class="ui-confirm" id="globalConfirmProceed">Yes, Continue</button></div></div></div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const topbar = document.getElementById('appTopbar');
    const toggle = document.getElementById('mobileMenuToggle');

    if (!topbar || !toggle) return;

    toggle.addEventListener('click', function () {
        const isOpen = topbar.classList.toggle('mobile-open');
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        toggle.textContent = isOpen ? '×' : '☰';
    });
});

/* Shared scroll restore */
(function(){const p='trainhub-scroll:'+location.pathname,m='trainhub-preserve:'+location.pathname;try{if('scrollRestoration'in history)history.scrollRestoration='manual';const n=performance.getEntriesByType?performance.getEntriesByType('navigation'):[],t=n.length?n[0].type:'',s=Number(sessionStorage.getItem(p)||0);if((sessionStorage.getItem(m)==='1'||t==='reload'||t==='back_forward')&&s>0){requestAnimationFrame(()=>requestAnimationFrame(()=>{scrollTo({top:s,left:0,behavior:'auto'});sessionStorage.removeItem(m)}))}addEventListener('beforeunload',()=>sessionStorage.setItem(p,String(scrollY||0)));document.addEventListener('click',e=>{const a=e.target.closest('a[href]');if(!a)return;try{const u=new URL(a.href,location.href);if(u.origin===location.origin&&u.pathname===location.pathname){sessionStorage.setItem(p,String(scrollY||0));sessionStorage.setItem(m,'1')}}catch(_){}},true);document.addEventListener('submit',()=>{sessionStorage.setItem(p,String(scrollY||0));sessionStorage.setItem(m,'1')},true)}catch(_){}})();
function openGlobalNotice(message,type='success',title=''){const o=document.getElementById('globalNoticeModal'),d=document.getElementById('globalNoticeDialog');if(!o||!d)return;d.classList.remove('success','error');d.classList.add(type==='error'?'error':'success');document.getElementById('globalNoticeIcon').textContent=type==='error'?'!':'✓';document.getElementById('globalNoticeTitle').textContent=title||(type==='error'?'Please check':'Successful');document.getElementById('globalNoticeMessage').textContent=message||'';o.classList.add('show');o.setAttribute('aria-hidden','false');document.body.classList.add('ui-modal-open')}
function closeGlobalNotice(){const o=document.getElementById('globalNoticeModal');o?.classList.remove('show');o?.setAttribute('aria-hidden','true');document.body.classList.remove('ui-modal-open')}
document.getElementById('globalNoticeOk')?.addEventListener('click',closeGlobalNotice);document.getElementById('globalNoticeModal')?.addEventListener('click',e=>{if(e.target.id==='globalNoticeModal')closeGlobalNotice()});
function convertLegacyAlerts(){document.querySelectorAll('.alert').forEach(b=>{if(b.dataset.popupConverted==='1')return;const msg=(b.textContent||'').trim();if(!msg)return;b.dataset.popupConverted='1';const err=b.classList.contains('alert-danger')||b.classList.contains('alert-error')||b.classList.contains('error');b.hidden=true;setTimeout(()=>openGlobalNotice(msg,err?'error':'success'),80)})}
let globalPendingForm=null;function openGlobalConfirm(form,message,danger=false){globalPendingForm=form;document.getElementById('globalConfirmMessage').textContent=message||'Are you sure you want to continue?';const b=document.getElementById('globalConfirmProceed');b.className=danger?'ui-confirm-danger':'ui-confirm';b.textContent=danger?'Yes, Delete':'Yes, Continue';document.getElementById('globalConfirmModal')?.classList.add('show');document.body.classList.add('ui-modal-open')}
function closeGlobalConfirm(){globalPendingForm=null;document.getElementById('globalConfirmModal')?.classList.remove('show');document.body.classList.remove('ui-modal-open')}
document.getElementById('globalConfirmCancel')?.addEventListener('click',closeGlobalConfirm);document.getElementById('globalConfirmProceed')?.addEventListener('click',()=>{if(!globalPendingForm)return;const f=globalPendingForm;globalPendingForm=null;f.dataset.globalConfirmed='1';document.getElementById('globalConfirmModal')?.classList.remove('show');document.body.classList.remove('ui-modal-open');f.submit()});document.getElementById('globalConfirmModal')?.addEventListener('click',e=>{if(e.target.id==='globalConfirmModal')closeGlobalConfirm()});
function setupGlobalConfirmForms(){document.querySelectorAll('form.confirm-form').forEach(f=>{if(f.dataset.confirmReady==='1'||f.dataset.globalConfirmReady==='1')return;f.dataset.globalConfirmReady='1';f.addEventListener('submit',e=>{if(f.dataset.globalConfirmed==='1'){delete f.dataset.globalConfirmed;return}e.preventDefault();openGlobalConfirm(f,f.dataset.confirm||f.dataset.deleteMessage||'Are you sure you want to continue?',f.classList.contains('delete-form')||f.dataset.deleteTitle)})})}
function findFieldLabel(c){if(!c)return null;if(c.id){const l=document.querySelector('label[for="'+CSS.escape(c.id)+'"]');if(l)return l}const w=c.closest('.field,.form-group,.form-wide,.capacity-field,.checkbox-field,.input-group');return w?w.querySelector(':scope > label'):null}
function refreshRequiredMarkers(root=document){root.querySelectorAll('input[required],select[required],textarea[required]').forEach(c=>{if(c.type==='hidden')return;const l=findFieldLabel(c);if(l)l.dataset.requiredMarker='1'})}
function fieldError(c){let e=c.nextElementSibling;if(e?.classList?.contains('field-error')||e?.classList?.contains('inline-field-error'))return e;e=document.createElement('small');e.className='inline-field-error';c.insertAdjacentElement('afterend',e);return e}
function setFieldError(c,msg){if(!c)return;const e=fieldError(c);if(msg){c.classList.add('is-invalid');c.setAttribute('aria-invalid','true');e.textContent=msg;e.hidden=false}else{c.classList.remove('is-invalid');c.removeAttribute('aria-invalid');e.textContent='';e.hidden=true}}
function basicMsg(c){if(!c||c.disabled||c.type==='hidden')return'';const v=(c.value||'').trim();if(c.required){if(c.type==='radio'&&c.name){const g=c.form?Array.from(c.form.querySelectorAll('input[type="radio"][name="'+CSS.escape(c.name)+'"]')):[];if(g.length&&!g.some(r=>r.checked))return'Please select an option.'}else if(c.type==='checkbox'&&!c.checked)return'Please select this required option.';else if(c.type==='file'&&(!c.files||!c.files.length))return'Please upload the required file.';else if(!['radio','checkbox','file'].includes(c.type)&&v==='')return'Please fill in this required field.'}if(v&&c.type==='email'&&!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v))return'Please enter a valid email address.';if(v&&c.type==='url'){try{new URL(v)}catch(_){return'Please enter a valid link.'}}if(v&&c.type==='number'){const n=Number(v);if(!Number.isFinite(n))return'Please enter a valid number.';if(n<0)return'Negative numbers are not allowed.';if(c.min!==''&&n<Number(c.min))return'The value is below the minimum allowed.';if(c.max!==''&&n>Number(c.max))return'The value is above the maximum allowed.'}if(v&&c.pattern){try{if(!(new RegExp('^(?:'+c.pattern+')$')).test(v))return'Please use the required format.'}catch(_){}}return''}
function addDays(s,n){const d=new Date(s+'T00:00:00');if(Number.isNaN(d.getTime()))return'';d.setDate(d.getDate()+n);return d.toISOString().slice(0,10)}
function datePair(f,a,b){const s=f.querySelector('[name="'+a+'"]'),e=f.querySelector('[name="'+b+'"]');if(!s||!e)return true;if(s.value)e.min=addDays(s.value,1);else e.removeAttribute('min');if(s.value&&e.value&&e.value<=s.value){setFieldError(e,'End date must be after start date.');return false}if(!basicMsg(e))setFieldError(e,'');return true}
function timePairs(f){let ok=true;f.querySelectorAll('input[name^="endTime"]').forEach(e=>{const w=e.closest('.session-block,.session-edit-grid,.session-grid,.form-grid')||f,s=w.querySelector('input[name^="startTime"]');if(s&&s.value&&e.value&&e.value<=s.value){setFieldError(e,'End time must be after start time.');ok=false}else if(!basicMsg(e))setFieldError(e,'')});return ok}
function validateFormInline(f){let ok=true;f.querySelectorAll('input,select,textarea').forEach(c=>{if(c.type==='hidden'||c.disabled)return;const m=basicMsg(c);if(m){setFieldError(c,m);ok=false}else if(!c.classList.contains('is-invalid'))setFieldError(c,'')});[['observerStartDate','observerEndDate'],['externalStartDate','externalEndDate'],['observerAssignedDate','observerAssignmentEndDate'],['externalAssignedDate','externalAssignmentEndDate']].forEach(x=>{if(!datePair(f,x[0],x[1]))ok=false});if(!timePairs(f))ok=false;return ok}
function refreshGlobalFormEnhancements(){document.querySelectorAll('input[type="number"]').forEach(i=>{if(i.min==='')i.min='0'});refreshRequiredMarkers()}document.addEventListener('DOMContentLoaded',()=>{convertLegacyAlerts();setupGlobalConfirmForms();refreshGlobalFormEnhancements();new MutationObserver(()=>refreshGlobalFormEnhancements()).observe(document.body,{subtree:true,childList:true,attributes:true,attributeFilter:['required']})});document.addEventListener('input',e=>{const c=e.target.closest('input,select,textarea');if(!c)return;setFieldError(c,basicMsg(c));if(c.form){timePairs(c.form);[['observerStartDate','observerEndDate'],['externalStartDate','externalEndDate'],['observerAssignedDate','observerAssignmentEndDate'],['externalAssignedDate','externalAssignmentEndDate']].forEach(x=>datePair(c.form,x[0],x[1]))}});document.addEventListener('change',e=>{const c=e.target.closest('input,select,textarea');if(c){setFieldError(c,basicMsg(c));if(c.form)validateFormInline(c.form)}});document.addEventListener('submit',e=>{const f=e.target;if(!(f instanceof HTMLFormElement))return;if(!validateFormInline(f)){e.preventDefault();e.stopImmediatePropagation();const c=f.querySelector('.is-invalid');if(c){c.focus({preventScroll:true});c.scrollIntoView({behavior:'smooth',block:'center'})}openGlobalNotice('Please correct the highlighted field(s) before continuing.','error')}},true);


/* Keep the header behind/blurred whenever any application popup is open. */
(function(){
    function visible(el){
        if(!el || el.hidden) return false;
        const cs=getComputedStyle(el);
        return cs.display!=='none' && cs.visibility!=='hidden' && parseFloat(cs.opacity||'1')>0;
    }
    function sync(){
        const selectors=['.modal','.global-ui-overlay','.flash-popup-backdrop','.delete-confirm-backdrop','.confirm-popup-backdrop','.attendance-popup-backdrop','.confirm-modal','.response-detail-modal','.certificate-list-modal','.assignment-modal','.response-modal'];
        const open=selectors.some(sel=>Array.from(document.querySelectorAll(sel)).some(visible));
        document.body.classList.toggle('has-modal-open',open);
        if(open) document.body.classList.add('ui-modal-open');
        else if(!document.body.classList.contains('modal-open')) document.body.classList.remove('ui-modal-open');
    }
    document.addEventListener('DOMContentLoaded',function(){
        sync();
        const observer=new MutationObserver(sync);
        observer.observe(document.body,{subtree:true,attributes:true,attributeFilter:['class','style','hidden','aria-hidden'],childList:true});
        document.addEventListener('click',()=>setTimeout(sync,0),true);
        document.addEventListener('submit',()=>setTimeout(sync,0),true);
        window.trainhubSyncModalState=sync;
    });
})();
</script>
