<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$settings = AppService::settings();
$user = Auth::currentUser();
$csrf = Auth::csrfToken();
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars((string) $settings['app_name'], ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700;800&display=swap">
  <link rel="stylesheet" href="<?= htmlspecialchars(versioned_asset_url('public/assets/tailwind.css'), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(versioned_asset_url('public/assets/app.css'), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(versioned_asset_url('public/assets/workspace.css'), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(versioned_asset_url('public/assets/modules/news/news.css'), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(versioned_asset_url('public/assets/modules/tournaments.css'), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(versioned_asset_url('public/assets/modules/admin-users.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="tw:font-sans">
  <header class="topbar">
    <button class="btn icon-button" id="menu-toggle" type="button" aria-label="เปิดเมนู" aria-expanded="false" aria-controls="main-navigation"><i data-lucide="menu"></i></button>
    <button class="brand" type="button" data-view-button="public">
      <span>
        <strong>SPORT TOURNAMENT</strong>
        <small>จัดการแข่งขันสำหรับทุกงาน</small>
      </span>
    </button>
    <div class="official-topbar-heading"><strong>ศูนย์เลือกหน้าบันทึก</strong><small>เลือกแมตช์เพื่อเปิดหน้าเฉพาะตามรูปแบบกติกา</small></div>
    <nav class="tabs" id="main-navigation" aria-label="เมนูหลัก">
      <a class="tab active" href="./" data-view-button="public">ดูหน้าเว็บไซต์</a>
      <button class="tab" data-view-button="public-sports">กีฬา</button>
      <button class="tab" data-view-button="public-athletes">นักกีฬา</button>
      <button class="tab" data-view-button="public-schedule">ตารางแข่งขัน</button>
      <button class="tab" data-view-button="public-results">ผลการแข่งขัน</button>
      <button class="tab" data-view-button="public-standings">ตารางคะแนน</button>
      <button class="tab" data-view-button="public-bracket">สายการแข่งขัน</button>
      <button class="tab" data-view-button="public-news">ข่าวสาร</button>
      <button class="tab admin-link" data-view-button="dashboard" hidden>แดชบอร์ด</button>
      <button class="tab admin-link" data-view-button="sports" hidden>กีฬาและชุดกติกา</button>
      <button class="tab admin-link" data-view-button="students" hidden>นักกีฬา</button>
      <button class="tab admin-link" data-view-button="teams" hidden>ทีม</button>
      <button class="tab admin-link" data-view-button="matches" hidden>จัดการทัวร์นาเมนต์</button>
      <button class="tab admin-link" data-view-button="official" hidden><i data-lucide="monitor-play" aria-hidden="true"></i><span>ศูนย์ควบคุมการแข่งขัน</span></button>
      <button class="tab admin-link" data-view-button="news" hidden>ข่าวสาร</button>
      <button class="tab admin-link" data-view-button="users" hidden>ผู้ดูแลระบบ</button>
    </nav>
    <div class="session-box">
      <span id="current-user-label">ผู้เยี่ยมชม</span>
      <button class="btn small role-switch-button" id="role-switch-button" type="button" title="สลับมุมมองตามบทบาท" aria-label="สลับมุมมองตามบทบาท" hidden><i data-lucide="users-round"></i><span>สลับมุมมอง</span></button>
      <a class="btn small ghost frontend-link" id="frontend-view-button" href="./" data-view-button="public" title="ดูหน้าเว็บไซต์" aria-label="ดูหน้าเว็บไซต์" hidden><i data-lucide="monitor"></i><span>ดูหน้าเว็บไซต์</span></a>
      <button class="btn small icon-button" id="profile-toggle" type="button" title="บัญชีของฉัน" aria-label="บัญชีของฉัน" hidden><i data-lucide="user-cog"></i></button>
      <button class="btn small" id="login-toggle" type="button">เข้าสู่ระบบ</button>
      <button class="btn small ghost" id="logout-button" type="button" hidden>ออกจากระบบ</button>
    </div>
  </header>
  <aside class="role-preview-bar" id="role-preview-bar" aria-live="polite" hidden>
    <div><i data-lucide="scan-eye" aria-hidden="true"></i><span>โหมดพรีเซนต์</span><strong id="role-preview-label"></strong><small>จำลองเฉพาะมุมมอง บัญชีจริงยังเป็น System Admin</small></div>
    <button class="btn small" id="role-preview-exit" type="button"><i data-lucide="rotate-ccw"></i><span>กลับมุมมองจริง</span></button>
  </aside>
  <button id="navigation-backdrop" aria-label="ปิดเมนู" hidden></button>
  <div id="page-progress" hidden role="progressbar" aria-label="กำลังโหลดข้อมูล"></div>

  <main>
<?php require dirname(__DIR__) . '/app/views/frontend/home.php'; ?>

<?php require dirname(__DIR__) . '/app/views/frontend/sports.php'; ?>

<?php require dirname(__DIR__) . '/app/views/frontend/athletes.php'; ?>

<?php require dirname(__DIR__) . '/app/views/frontend/schedule.php'; ?>

<?php require dirname(__DIR__) . '/app/views/frontend/results.php'; ?>

<?php require dirname(__DIR__) . '/app/views/frontend/standings.php'; ?>

<?php require dirname(__DIR__) . '/app/views/frontend/bracket.php'; ?>

<?php require __DIR__ . '/pages/news/PublicNewsList.php'; ?>

<?php require __DIR__ . '/pages/news/PublicNewsDetail.php'; ?>

<?php require dirname(__DIR__) . '/app/views/staff/dashboard.php'; ?>

<?php require dirname(__DIR__) . '/app/views/staff/sports.php'; ?>

<?php require dirname(__DIR__) . '/app/views/staff/athletes.php'; ?>

<?php require dirname(__DIR__) . '/app/views/staff/teams.php'; ?>

    <section class="view app-panel" id="view-matches" hidden>
      <?php require __DIR__ . '/pages/tournaments/TournamentList.php'; ?>
      <?php require __DIR__ . '/pages/tournaments/TournamentCreate.php'; ?>
      <?php require __DIR__ . '/pages/tournaments/TournamentDashboard.php'; ?>
      <?php require __DIR__ . '/components/tournaments/TournamentForm.php'; ?>
    </section>

<?php require __DIR__ . '/pages/scoring/MatchSelector.php'; ?>

<?php require __DIR__ . '/pages/news/NewsManagement.php'; ?>
<?php require __DIR__ . '/pages/news/NewsEditor.php'; ?>
<?php require __DIR__ . '/pages/news/NewsPreview.php'; ?>
<?php require __DIR__ . '/components/news/banner-studio.php'; ?>

    <?php require __DIR__ . '/pages/admin-users/users.php'; ?>

    <section class="view app-panel" id="view-invite-accept" hidden>
      <div class="grid invite-accept-grid">
        <form class="card form-card" id="accept-invite-form">
          <h2>ตั้งค่าบัญชีผู้ดูแลระบบ</h2>
          <p class="muted" id="invite-preview-text">กำลังตรวจสอบคำเชิญ</p>
          <input type="hidden" name="token" id="invite-token-input">
          <label>อีเมล <input type="email" name="email" id="invite-email-input" readonly required></label>
          <label>ชื่อ <input name="name" id="invite-name-input" required></label>
          <label>รหัสผ่าน <input type="password" name="password" autocomplete="new-password" required></label>
          <label>ยืนยันรหัสผ่าน <input type="password" name="password_confirm" autocomplete="new-password" required></label>
          <div class="actions"><button class="btn primary" type="submit">ตั้งค่าบัญชี</button><button class="btn ghost" type="button" data-view-button="public">กลับหน้าหลัก</button></div>
        </form>
      </div>
    </section>

  </main>

  <footer class="site-footer">
    <div>
      <strong>SPORT TOURNAMENT</strong>
      <span>ติดตามการแข่งขันสำหรับทุกงาน</span>
      <small class="flaticon-credit">
        ไอคอนกีฬาโดย
        <a href="https://www.flaticon.com/authors/roundicons" target="_blank" rel="noopener">Roundicons</a>,
        <a href="https://www.flaticon.com/authors/muhammad-atif" target="_blank" rel="noopener">Muhammad Atif</a>,
        <a href="https://www.flaticon.com/authors/iconixar" target="_blank" rel="noopener">Iconixar</a>,
        <a href="https://www.flaticon.com/authors/ddara" target="_blank" rel="noopener">dDara</a>,
        <a href="https://www.flaticon.com/authors/dimas-anom" target="_blank" rel="noopener">Dimas Anom</a> และ
        <a href="https://www.flaticon.com/authors/amethystdesign" target="_blank" rel="noopener">AmethystDesign</a>
        จาก <a href="https://www.flaticon.com/" target="_blank" rel="noopener">Flaticon</a>
      </small>
    </div>
    <nav aria-label="เมนูลัดท้ายเว็บ">
      <button type="button" data-view-button="public-schedule">ตารางแข่งขัน</button>
      <button type="button" data-view-button="public-results">ผลการแข่งขัน</button>
      <button type="button" data-view-button="public-standings">ตารางคะแนน</button>
      <button type="button" data-view-button="public-news">ข่าวสาร</button>
    </nav>
  </footer>

  <dialog id="login-dialog" aria-labelledby="login-title">
    <form method="dialog" class="dialog-card" id="login-form">
      <div class="login-mark"><i data-lucide="shield-check"></i></div>
      <p class="eyebrow">SPORT TOURNAMENT</p>
      <h2 id="login-title">เข้าสู่ระบบผู้ดูแล</h2>
      <label>อีเมล <input name="email" type="email" autocomplete="username" placeholder="name@example.com" required></label>
      <label>รหัสผ่าน <input name="password" type="password" autocomplete="current-password" required></label>
      <div class="actions"><button class="btn" value="cancel" type="button" id="login-cancel">ยกเลิก</button><button class="btn primary" type="submit">เข้าสู่ระบบ</button></div>
    </form>
  </dialog>

  <dialog id="profile-dialog" aria-labelledby="profile-title">
    <form method="dialog" class="dialog-card" id="profile-form">
      <div class="login-mark"><i data-lucide="user-cog"></i></div>
      <p class="eyebrow">SPORT TOURNAMENT</p>
      <h2 id="profile-title">บัญชีของฉัน</h2>
      <label>อีเมล <input name="email" type="email" autocomplete="username" readonly></label>
      <label>ชื่อ <input name="name" autocomplete="name" required></label>
      <label>รหัสผ่านเดิม <input name="current_password" type="password" autocomplete="current-password"></label>
      <label>รหัสผ่านใหม่ <input name="new_password" type="password" autocomplete="new-password" minlength="8"></label>
      <label>ยืนยันรหัสผ่านใหม่ <input name="new_password_confirm" type="password" autocomplete="new-password" minlength="8"></label>
      <p class="muted">เว้นช่องรหัสผ่านไว้ หากต้องการแก้ไขเฉพาะชื่อบัญชี</p>
      <div class="actions"><button class="btn" value="cancel" type="button" id="profile-cancel">ยกเลิก</button><button class="btn primary" type="submit">บันทึกบัญชี</button></div>
    </form>
  </dialog>

  <dialog id="role-switch-dialog" class="role-switch-dialog" aria-labelledby="role-switch-title">
    <section class="role-switch-panel">
      <header class="role-switch-head">
        <div class="role-switch-icon"><i data-lucide="users-round"></i></div>
        <div>
          <p class="eyebrow">PRESENTATION MODE</p>
          <h2 id="role-switch-title">เลือกมุมมองตามบทบาท</h2>
          <p>สาธิตเมนูและความสามารถที่แต่ละ Role มองเห็น โดยไม่เปลี่ยนสิทธิ์บัญชีจริง</p>
        </div>
        <button class="btn icon-button" id="role-switch-close" type="button" aria-label="ปิด" title="ปิด"><i data-lucide="x"></i></button>
      </header>
      <div class="role-switch-status" id="role-switch-status"></div>
      <div class="role-switch-grid" id="role-switch-grid"></div>
      <footer class="role-switch-foot">
        <span><i data-lucide="shield-check"></i> API และข้อมูลยังใช้สิทธิ์ System Admin เดิม โหมดนี้ใช้สำหรับนำเสนอหน้าจอเท่านั้น</span>
        <button class="btn" id="role-switch-reset" type="button">กลับมุมมองจริง</button>
      </footer>
    </section>
  </dialog>



  <div class="toast" id="toast" role="status" aria-live="polite" hidden></div>
  <script>
    window.PRSPORT = {
      apiUrl: <?= json_encode(app_url('api.php'), JSON_UNESCAPED_SLASHES) ?>,
      assetBase: <?= json_encode(app_url('public/assets'), JSON_UNESCAPED_SLASHES) ?>,
      scoringBaseUrl: <?= json_encode(app_url('public/scoring'), JSON_UNESCAPED_SLASHES) ?>,
      csrf: <?= json_encode($csrf) ?>,
      user: <?= json_encode($user, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    };
  </script>
  <script src="<?= htmlspecialchars(versioned_asset_url('public/assets/lucide.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(versioned_asset_url('public/assets/workspace.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(versioned_asset_url('public/assets/banner-studio.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(versioned_asset_url('public/assets/modules/tournaments.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(versioned_asset_url('public/assets/modules/admin-users.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(versioned_asset_url('public/assets/modules/news/news-ai.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(versioned_asset_url('public/assets/modules/news/news-management.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(versioned_asset_url('public/assets/modules/news/news-public.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(versioned_asset_url('public/assets/modules/news/news-competition.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(versioned_asset_url('public/assets/modules/news/news-editor.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(versioned_asset_url('public/assets/modules/news/news-drafts.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(versioned_asset_url('public/assets/modules/news/news-honor.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(versioned_asset_url('public/assets/modules/news/news-composer.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(versioned_asset_url('public/assets/scoring/scoring-config.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(versioned_asset_url('public/assets/scoring/match-selector.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(versioned_asset_url('public/assets/app.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
