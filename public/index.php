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
  <link rel="stylesheet" href="<?= htmlspecialchars(versioned_asset_url('public/assets/app.css'), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(versioned_asset_url('public/assets/workspace.css'), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(versioned_asset_url('public/assets/modules/news/news.css'), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(versioned_asset_url('public/assets/modules/tournaments.css'), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(versioned_asset_url('public/assets/modules/admin-users.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
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
    <section class="view public-hero" id="view-public">
      <div class="hero-copy">
        <p class="eyebrow">แพลตฟอร์มจัดการแข่งขัน</p>
        <h1>SPORT TOURNAMENT</h1>
        <p>ทุกการแข่งขัน ทุกความสำเร็จ<br>ติดตามรายการ ทีม และผลการแข่งขันในที่เดียว</p>
        <div class="actions">
          <button class="btn primary" type="button" data-view-button="public-schedule">ดูตารางแข่งขัน</button>
          <button class="btn" type="button" data-view-button="public-news">อ่านข่าวล่าสุด</button>
        </div>
      </div>
      <div class="stats-grid">
        <div><strong id="m-sports">0</strong><span>กีฬา</span></div>
        <div><strong id="m-schedule">0</strong><span>แมตช์รอแข่ง</span></div>
        <div><strong id="m-results">0</strong><span>ผลจบแล้ว</span></div>
        <div><strong id="m-news">0</strong><span>ข่าวเผยแพร่</span></div>
      </div>
    </section>

    <section class="view content-band" id="view-public-home-extra">
      <div class="section-head">
        <h2>รายการแข่งขันล่าสุด</h2>
        <div class="public-match-filters" aria-label="ตัวกรองการแข่งขัน">
          <label>ทัวร์นาเมนท์<select id="home-tournament-filter"></select></label>
          <label>กรองวันที่แข่งขัน
            <div class="calendar-picker" id="home-calendar-picker">
              <input id="home-date-filter" type="hidden">
              <button class="calendar-trigger" id="home-date-trigger" type="button" aria-expanded="false">เลือกวันที่</button>
              <div class="calendar-popover" id="home-calendar-popover" hidden></div>
            </div>
          </label>
          <label>ทีม<select id="home-department-filter"></select></label>
        </div>
      </div>
      <div class="split">
        <div class="upcoming-panel">
          <h3>ตารางใกล้ถึง</h3>
          <span class="panel-count" id="public-schedule-count">0 คู่</span>
          <div class="public-match-scroll" id="public-schedule"></div>
        </div>
        <div class="results-panel">
          <h3>ผลการแข่งขัน</h3>
          <span class="panel-count" id="public-results-count">0 คู่</span>
          <div class="public-match-scroll" id="public-home-results"></div>
        </div>
      </div>
      <div class="section-head">
        <h2>ข่าวล่าสุด</h2>
        <button class="btn small" type="button" data-view-button="public-news">ดูทั้งหมด</button>
      </div>
      <div class="news-grid" id="public-news"></div>
      <h2>Hall of Fame</h2>
      <div class="news-grid compact" id="public-hall"></div>
    </section>

    <section class="view content-band sports-showcase" id="view-public-sports" hidden>
      <header class="league-masthead"><div class="league-masthead-copy"><span>SPORT TOURNAMENT</span><h2>กีฬา</h2><p>ชนิดกีฬาและกติกาการแข่งขัน</p></div><i data-lucide="trophy" aria-hidden="true"></i></header>
      <div class="league-filter-bar"><strong><i data-lucide="sliders-horizontal"></i> ค้นหาชนิดกีฬา</strong><div class="public-catalog-filters"><select id="public-sport-gender-filter" aria-label="เลือกเพศ"><option value="">ทุกเพศ</option><option value="male">ชาย</option><option value="female">หญิง</option><option value="mixed">ผสม</option></select><select id="public-sport-format-filter" aria-label="เลือกรูปแบบ"><option value="">ทุกรูปแบบ</option><option value="team">ทีม</option><option value="single">เดี่ยว</option><option value="double">คู่</option></select></div></div>
      <div class="table-wrap" id="public-sports-table"></div>
    </section>

    <section class="view content-band athlete-page" id="view-public-athletes" hidden>
      <header class="athlete-page-head">
        <div>
          <h2>รายชื่อนักกีฬา</h2>
          <p>เลือกทัวร์นาเมนต์ กีฬา และทีมที่ต้องการดู</p>
        </div>
        <span id="athlete-total-count">0 นักกีฬา</span>
      </header>
      <nav class="athlete-flow" aria-label="ขั้นตอนค้นหานักกีฬา">
        <div class="active" data-athlete-flow-step="1"><span>1</span><strong>ทัวร์นาเมนต์</strong><small>เลือกรายการ</small></div>
        <div data-athlete-flow-step="2"><span>2</span><strong>กีฬา</strong><small>เลือกชนิดกีฬา</small></div>
        <div data-athlete-flow-step="3"><span>3</span><strong>ทีม</strong><small>เลือกทีม</small></div>
        <div data-athlete-flow-step="4"><span>4</span><strong>ผู้เล่น</strong><small>ดูรายชื่อนักกีฬา</small></div>
      </nav>
      <div class="athlete-filter-panel">
        <label><span>1. ทัวร์นาเมนต์</span><select id="athlete-faculty-filter" aria-label="เลือกทัวร์นาเมนต์"></select></label>
        <label><span>2. กีฬา</span><select id="athlete-department-filter" aria-label="เลือกกีฬา" disabled></select></label>
        <label><span>3. ทีม</span><select id="athlete-sport-filter" aria-label="เลือกทีม" disabled></select></label>
      </div>
      <div class="athlete-flow-notice" id="athlete-flow-notice"><i data-lucide="mouse-pointer-click"></i><span>เริ่มจากเลือกทัวร์นาเมนต์</span></div>
      <section class="athlete-results" id="athlete-results" hidden>
        <div class="athlete-results-head">
          <div><span>รายชื่อนักกีฬา</span><h3 id="athlete-results-title">ผู้เล่นทั้งหมด</h3></div>
          <label class="athlete-search"><i data-lucide="search"></i><input id="athlete-team-filter" type="search" placeholder="ค้นหาชื่อผู้เล่น รหัสนักกีฬา หรือทีม" aria-label="ค้นหานักกีฬา"></label>
        </div>
        <div class="athlete-board-list" id="athlete-board-list"></div>
      </section>
    </section>

    <section class="view content-band schedule-showcase" id="view-public-schedule" hidden>
      <header class="league-masthead"><div class="league-masthead-copy"><span>SPORT TOURNAMENT</span><h2>ตารางแข่งขัน</h2><p>โปรแกรม วันเวลา และสนามแข่งขัน</p></div><i data-lucide="calendar-days" aria-hidden="true"></i></header>
      <div class="league-filter-bar"><strong><i data-lucide="filter"></i> เลือกรายการแข่งขัน</strong><select id="schedule-tournament-filter" aria-label="เลือกรายการแข่งขัน"></select></div>
      <div class="match-list" id="public-schedule-page"></div>
    </section>

    <section class="view content-band results-showcase" id="view-public-results" hidden>
      <header class="league-masthead"><div class="league-masthead-copy"><span>SPORT TOURNAMENT</span><h2>ผลการแข่งขัน</h2><p>สกอร์และรายละเอียดการแข่งขัน</p></div><i data-lucide="trophy" aria-hidden="true"></i></header>
      <div class="league-filter-bar"><strong><i data-lucide="filter"></i> เลือกรายการแข่งขัน</strong><select id="results-tournament-filter" aria-label="เลือกรายการแข่งขัน"></select></div>
      <div class="match-list" id="public-results-page"></div>
      <section class="public-standings"><div class="league-section-title"><h3><i data-lucide="medal"></i><span>ตารางคะแนน</span></h3><button class="btn" type="button" data-view-button="public-standings">ดูตารางคะแนนทั้งหมด <i data-lucide="arrow-up-right"></i></button></div><p class="league-table-hint">เลื่อนตารางไปด้านข้างเพื่อดูคะแนน →</p><div class="table-wrap" id="public-standings-table"></div></section>
    </section>

    <section class="view content-band standings-showcase" id="view-public-standings" hidden>
      <header class="league-masthead"><div class="league-masthead-copy"><span>SPORT TOURNAMENT</span><h2>ตารางคะแนน</h2><p>อันดับทีมและคะแนนสะสม</p></div><i data-lucide="medal" aria-hidden="true"></i></header>
      <div class="league-filter-bar"><strong><i data-lucide="filter"></i> เลือกรายการแข่งขัน</strong><select id="public-standings-tournament-filter" aria-label="เลือกรายการแข่งขัน"></select></div>
      <div class="league-section-title"><h3>อันดับการแข่งขัน</h3><span>อัปเดตจากผลการแข่งขันในระบบ</span></div>
      <p class="league-table-hint">เลื่อนตารางไปด้านข้างเพื่อดูคะแนน →</p><div class="table-wrap" id="public-standings-page-table"></div>
    </section>

    <section class="view content-band bracket-page" id="view-public-bracket" hidden>
      <header class="bracket-page-head">
        <div><h2>สายการแข่งขัน</h2><p>เลือกทัวร์นาเมนต์และกีฬาเพื่อดูการจัดกลุ่มและคู่แข่งขัน</p></div>
        <div class="bracket-filter-bar">
          <label><span>1. ทัวร์นาเมนต์</span><select id="bracket-tournament-filter" aria-label="เลือกทัวร์นาเมนต์"></select></label>
          <label><span>2. กีฬา</span><select id="bracket-sport-filter" aria-label="เลือกกีฬา" disabled><option value="">เลือกกีฬาที่ต้องการดู</option></select></label>
        </div>
      </header>
      <div class="bracket-stage">
        <div class="bracket-stage-head" id="bracket-stage-head" hidden><span id="bracket-stage-sport"></span><h3 id="bracket-stage-title"></h3></div>
        <div class="public-bracket" id="public-bracket-rounds"></div>
      </div>
    </section>

<?php require __DIR__ . '/pages/news/PublicNewsList.php'; ?>

<?php require __DIR__ . '/pages/news/PublicNewsDetail.php'; ?>

    <section class="view app-panel" id="view-dashboard" hidden>
      <div class="section-head">
        <h2>แดชบอร์ด</h2>
        <button class="btn small" id="dashboard-ai-button" type="button">สร้างสรุปด้วย AI</button>
      </div>
      <div class="metric-grid">
        <div class="metric"><span>กีฬาเปิดใช้</span><strong id="d-active-sports">0</strong></div>
        <div class="metric"><span>ทีมอนุมัติ</span><strong id="d-approved-teams">0</strong></div>
        <div class="metric"><span>นักกีฬา</span><strong id="d-students">0</strong></div>
        <div class="metric"><span>แมตช์จบแล้ว</span><strong id="d-completed">0</strong></div>
      </div>
      <div class="dashboard-shortcuts" id="dashboard-shortcuts"></div>
      <section class="dashboard-summary"><h3>สรุปภาพรวม</h3><div class="ai-output" id="dashboard-ai-output">ยังไม่มีข้อมูลสรุป</div></section>
      <section class="data-section"><h3>กิจกรรมล่าสุด</h3><div class="table-wrap" id="audit-table"></div></section>
    </section>

    <section class="view app-panel" id="view-sports" hidden>
      <div class="grid">
        <form class="card form-card" id="sport-form">
          <h2>ข้อมูลกีฬา</h2>
          <input type="hidden" name="id">
          <label>ชื่อกีฬา <input name="name" required></label>
          <div class="form-row tournament-meta-row">
            <label>ประเภท <select name="type"><option value="team">ทีม</option><option value="individual">รายบุคคล</option></select></label>
            <label>เพศ <select name="gender"><option value="open">ทั่วไป</option><option value="male">ชาย</option><option value="female">หญิง</option><option value="mixed">ผสม</option></select></label>
          </div>
          <label>รูปแบบการบันทึกผล <select name="scoring_type" id="sport-scoring-type" required>
            <option value="" selected disabled>เลือกวิธีบันทึกและตัดสินผล</option>
            <option value="SCORE">คะแนนสะสม (SCORE)</option><option value="SET">คะแนนเป็นเซต (SET)</option><option value="ROUND">คะแนนเป็นรอบ (ROUND)</option>
            <option value="TIME">จับเวลา (TIME)</option><option value="DISTANCE">วัดระยะ (DISTANCE)</option><option value="HEIGHT">วัดความสูง (HEIGHT)</option>
            <option value="JUDGED">คะแนนกรรมการ (JUDGED)</option><option value="TARGET">คะแนนเป้าหมาย (TARGET)</option><option value="COUNT">นับจำนวนครั้ง (COUNT)</option>
            <option value="LOWEST">ตัวเลขต่ำสุด (LOWEST)</option><option value="DECISION">ชนะด้วยเงื่อนไข (DECISION)</option><option value="COMBINED">ผลรวมหลายรายการ (COMBINED)</option>
          </select><small class="field-hint" id="sport-scoring-help">รูปแบบที่เลือกจะสร้างเป็นกติกาหลัก และแมตช์ใหม่จะใช้แบบบันทึกตามกติกานี้</small></label>
          <div class="form-row">
            <label>ผู้เล่นต่ำสุด <input type="number" name="min_players" min="1" value="1"></label>
            <label>ผู้เล่นสูงสุด <input type="number" name="max_players" min="1" value="1"></label>
            <label>ตัวสำรอง <input type="number" name="reserve_players" min="0" value="0"></label>
          </div>
          <fieldset class="sport-icon-field">
            <legend>ไอคอนกีฬา</legend>
            <input type="hidden" name="icon" value="general">
            <div class="sport-icon-picker" id="sport-icon-picker" role="group" aria-label="เลือกไอคอนกีฬา"></div>
          </fieldset>
          <div class="form-row">
            <label>โลโก้กีฬา <input type="file" id="sport-logo-file" accept="image/jpeg,image/png,image/webp"></label>
            <label>ลิงก์โลโก้ <input name="logo_url"></label>
          </div>
          <label>คำอธิบายกีฬาโดยย่อ <textarea name="rules" placeholder="ข้อมูลสำหรับแสดงผลทั่วไป ไม่ใช่ชุดกติกาที่ใช้คำนวณผลการแข่งขัน"></textarea></label>
          <div class="actions"><button class="btn primary" type="submit">บันทึกกีฬา</button><button class="btn ghost reset-form" type="button">ล้างฟอร์ม</button></div>
        </form>
        <div class="card wide"><h2>รายการกีฬา</h2><div class="table-wrap" id="sports-table"></div></div>
        <form class="card form-card" id="sport-rule-form">
          <h2>ชุดกติกากีฬา</h2>
          <p class="muted">สร้างไว้เป็นข้อมูลกลาง แล้วเลือกใช้ซ้ำในแต่ละทัวร์นาเมนต์ เมื่อกติกาที่ใช้งานอยู่ต้องเปลี่ยน ให้สร้างเป็นเวอร์ชันใหม่เพื่อรักษาประวัติเดิม</p>
          <input type="hidden" name="id">
          <label>กีฬา <select name="sport_id" class="sport-select" required></select></label>
          <label>ชื่อชุดกติกา <input name="name" value="กติกามาตรฐาน" required></label>
          <div class="form-row">
            <label>หมวดกีฬา <input name="category" placeholder="กีฬาทีม / กีฬาต่อสู้"></label>
            <label>ประเภทผู้เล่น <select name="player_type"><option value="team">ทีม</option><option value="individual">บุคคล</option><option value="pair">คู่</option><option value="relay_team">ทีมผลัด</option></select></label>
            <label>จำนวนผู้เล่น <input type="number" name="team_size" min="1" value="1"></label>
          </div>
          <div class="form-row">
            <label>ประเภทการคิดผล <select name="scoring_type" id="scoring-type"><option value="SCORE">คะแนนสะสม (SCORE)</option><option value="SET">คะแนนเป็นเซต (SET)</option><option value="ROUND">คะแนนเป็นรอบ (ROUND)</option><option value="TIME">จับเวลา (TIME)</option><option value="DISTANCE">วัดระยะ (DISTANCE)</option><option value="HEIGHT">วัดความสูง (HEIGHT)</option><option value="JUDGED">คะแนนกรรมการ (JUDGED)</option><option value="TARGET">คะแนนเป้าหมาย (TARGET)</option><option value="COUNT">นับจำนวนครั้ง (COUNT)</option><option value="LOWEST">ตัวเลขต่ำสุด (LOWEST)</option><option value="DECISION">ชนะด้วยเงื่อนไข (DECISION)</option><option value="COMBINED">ผลรวมหลายรายการ (COMBINED)</option><optgroup data-legacy-modes hidden label="รูปแบบเดิมของแมตช์ที่มีอยู่"><option value="PERIOD_SCORE">คะแนนแยกช่วงเดิม</option><option value="END_SCORE">คะแนนแต่ละ End เดิม</option><option value="POINT_RANKING">คะแนนกรรมการเดิม</option><option value="COMBAT">กีฬาต่อสู้เดิม</option><option value="RACE_POSITION">อันดับเข้าเส้นชัยเดิม</option><option value="STROKE">Stroke เดิม</option><option value="MAP">Game / Map เดิม</option></optgroup></select></label>
            <label class="checkbox-line"><input type="checkbox" name="include_in_standings" checked> นำผลไปรวมตารางคะแนนทีม</label>
          </div>
          <div class="form-row">
            <label>ระบบคะแนน <select name="scoring_mode"><option value="points">คะแนนรวม</option><option value="sets">เซต</option><option value="time">เวลา</option><option value="rank">อันดับ</option></select></label>
            <label>ตัดสินผู้ชนะ <select name="win_by"><option value="highest">คะแนนสูงสุด</option><option value="lowest">คะแนน/เวลาต่ำสุด</option><option value="sets">ชนะเซต</option></select></label>
            <label>แต้มต่อเหตุการณ์ <input type="number" name="event_points" min="0" value="1"></label>
          </div>
          <div class="form-row">
            <label>จำนวนช่วง/ครึ่ง/เซต <input type="number" name="period_count" min="1" value="2"></label>
            <label>นาทีต่อช่วง <input type="number" name="period_minutes" min="0" value="45"></label>
            <label class="checkbox-line"><input type="checkbox" name="allow_draw"> อนุญาตผลเสมอ</label>
          </div>
          <fieldset id="scoring-config-fields">
            <legend>ค่ากติกาสำหรับการคำนวณผล</legend>
            <div class="form-row">
              <label data-config-for="SCORE PERIOD_SCORE">ปุ่มเพิ่มแต้ม <input name="quick_points" value="1" placeholder="1,2,3"><small class="field-hint">คั่นด้วยจุลภาค เช่น 1,2,3</small></label>
              <label data-config-for="SET MAP">Best of <input type="number" name="best_of" min="1" value="3"></label>
              <label data-config-for="SET">คะแนนชนะเซต <input type="number" name="points_to_win" min="0" value="21"></label>
              <label data-config-for="SET">ชนะห่าง <input type="number" name="set_win_by" min="1" value="2"></label>
              <label data-config-for="SET">คะแนนสูงสุด <input type="number" name="max_points" min="0" value="30"></label>
            </div>
            <div class="form-row">
              <label data-config-for="SET">เซตปกติ <input type="number" name="normal_set_points" min="0" value="0"></label>
              <label data-config-for="SET">เซตสุดท้าย <input type="number" name="final_set_points" min="0" value="0"></label>
              <label data-config-for="ROUND END_SCORE">คะแนนเป้าหมาย (0 = ไม่กำหนด) <input type="number" name="target_score" min="0" value="0"></label>
              <label data-config-for="JUDGED TARGET POINT_RANKING">รวมคะแนน <select name="aggregation"><option value="SUM">ผลรวม</option><option value="AVERAGE">ค่าเฉลี่ย</option><option value="DROP_HIGHEST_LOWEST">ตัดสูงสุดและต่ำสุด</option></select></label>
              <label data-config-for="COUNT COMBINED">ผู้ชนะ <select name="numeric_win_by"><option value="HIGHEST">ค่ามากกว่า</option><option value="LOWEST">ค่าน้อยกว่า</option></select></label>
            </div>
          </fieldset>
          <label>Scoring Config ขั้นสูง <textarea name="scoring_config" id="scoring-config-json" rows="5" placeholder='{"tie_break":["best_result","second_best_result"]}'></textarea><small class="field-hint">ใช้เพิ่มค่าเฉพาะทางในรูปแบบ JSON โดยค่าจากช่องด้านบนจะมีผลเหนือกว่า</small></label>
          <div class="form-row">
            <label class="checkbox-line"><input type="checkbox" name="overtime_enabled"> มีการต่อเวลา</label>
            <label>จำนวนช่วงต่อเวลา <input type="number" name="overtime_periods" min="1" value="2"></label>
            <label>นาทีต่อช่วง <input type="number" name="overtime_minutes" min="1" value="15"></label>
          </div>
          <div class="form-row">
            <label>ตัดสินเสมอ <select name="tie_break_method"><option value="draw">เสมอ</option><option value="penalty">ยิงจุดโทษ</option><option value="golden_score">Golden score</option><option value="judge">กรรมการตัดสิน</option></select></label>
            <label>แต้มชนะ <input type="number" name="win_points" value="3"></label>
            <label>แต้มเสมอ <input type="number" name="draw_points" value="1"></label>
            <label>แต้มแพ้ <input type="number" name="loss_points" value="0"></label>
          </div>
          <label>ประเภทเหตุการณ์ <textarea name="event_types_text" placeholder="score|ทำคะแนน|1|athlete&#10;foul|ฟาวล์|0|athlete&#10;timeout|ขอเวลานอก|0|team"></textarea><small class="field-hint">รูปแบบ: รหัส | ชื่อ | แต้ม | ผู้เกี่ยวข้อง (athlete, team หรือ optional)</small></label>
          <label>กติกาเพิ่มเติม <textarea name="custom_rules"></textarea></label>
          <label class="checkbox-line"><input type="checkbox" name="is_active" checked> ตั้งเป็นชุดกติกาหลักสำหรับทัวร์นาเมนต์ใหม่</label>
          <div class="actions"><button class="btn primary" type="submit">บันทึกชุดกติกา</button><button class="btn ghost reset-form" type="button">ล้างฟอร์ม</button></div>
        </form>
        <div class="card wide"><h2>ชุดกติกาทั้งหมด</h2><div class="table-wrap" id="sport-rules-table"></div></div>
        <form class="card form-card" id="organization-form">
          <h2>มหาวิทยาลัย คณะ และสาขา</h2>
          <input type="hidden" name="id">
          <label>ระดับ <select name="entity_type" id="organization-type"><option value="university">มหาวิทยาลัย</option><option value="faculty">คณะ</option><option value="department">สาขา</option></select></label>
          <label>มหาวิทยาลัยต้นสังกัด <select name="university_id" id="organization-university"></select></label>
          <label>คณะต้นสังกัด <select name="faculty_id" id="organization-faculty"></select></label>
          <div class="form-row"><label>รหัส <input name="code" required></label><label>ชื่อ <input name="name" required></label></div>
          <div class="form-row"><label>โลโก้ <input type="file" id="organization-logo-file" accept="image/jpeg,image/png,image/webp"></label><label>ลิงก์โลโก้ <input name="logo_url"></label></div>
          <label class="checkbox-line"><input type="checkbox" name="is_active" checked> เปิดใช้งาน</label>
          <div class="actions"><button class="btn primary" type="submit">บันทึกหน่วยงาน</button><button class="btn ghost reset-form" type="button">ล้างฟอร์ม</button></div>
        </form>
        <div class="card wide"><h2>โครงสร้างหน่วยงาน</h2><div class="table-wrap" id="organizations-table"></div></div>
      </div>
    </section>

    <section class="view app-panel" id="view-students" hidden>
      <div class="grid">
        <form class="card form-card" id="student-form">
          <h2>นักกีฬา</h2>
          <input type="hidden" name="id">
          <label>รหัสนักกีฬา <input name="student_code" placeholder="เว้นว่างเพื่อสร้างรหัสอัตโนมัติ"></label>
          <div class="form-row">
            <label>คำนำหน้า <input name="prefix"></label>
            <label>ชื่อ <input name="first_name" required></label>
            <label>นามสกุล (ถ้ามี) <input name="last_name"></label>
          </div>
          <div class="form-row">
            <label>หน่วยงาน / สังกัด (ถ้ามี) <select name="department_code" class="department-select"></select></label>
            <label>รายละเอียดเพิ่มเติม <input name="entry_year"></label>
            <label>เพศ <select name="gender"><option value="open">ไม่ระบุ</option><option value="male">ชาย</option><option value="female">หญิง</option></select></label>
          </div>
          <input type="hidden" name="photo_url" id="student-photo-url"><input type="file" id="student-photo-file" hidden><div id="student-photo-preview" hidden></div>
          <label>โทรศัพท์ <input name="phone"></label>
          <div class="actions"><button class="btn primary" type="submit">บันทึก</button><button class="btn ghost reset-form" type="button">ล้างฟอร์ม</button></div>
        </form>
        <div class="card wide">
          <h2>จัดการนักกีฬา</h2>
          <div class="student-scope" id="student-scope">
            <div class="student-scope-heading">
              <div><span>ตัวกรองเพิ่มเติม</span><strong>เลือกสังกัดหรือกีฬาเมื่อต้องการกรองข้อมูล</strong></div>
              <p id="student-scope-status" aria-live="polite">แสดงนักกีฬาทั้งหมด</p>
            </div>
            <div class="student-scope-fields">
              <label><span><b>1</b> องค์กร (ถ้ามี)</span><select id="student-scope-faculty" aria-label="เลือกองค์กร"></select></label>
              <label><span><b>2</b> สังกัดย่อย (ถ้ามี)</span><select id="student-scope-department" aria-label="เลือกสังกัดย่อย" disabled></select></label>
              <label><span><b>3</b> กีฬา (ถ้าต้องการ)</span><select id="student-scope-sport" aria-label="เลือกกีฬา" disabled></select></label>
            </div>
          </div>
          <div class="table-wrap" id="students-table"></div>
        </div>
        <div class="card full">
          <h2>นำเข้า CSV นักกีฬา</h2>
          <textarea class="csv-input" id="students-csv" placeholder="student_code,first_name,last_name,department_code,gender,phone"></textarea>
          <div class="actions"><button class="btn" id="students-csv-check" type="button">ตรวจ CSV</button><button class="btn primary" id="students-csv-import" type="button">นำเข้า</button></div>
          <div class="ai-output compact" id="students-import-result"></div>
        </div>
      </div>
    </section>

    <section class="view app-panel" id="view-teams" hidden>
      <div class="grid">
        <form class="card form-card" id="team-form">
          <h2>ทีม</h2>
          <input type="hidden" name="id">
          <label>ชื่อทีม <input name="name" required></label>
          <label>รายการแข่ง <select name="tournament_id" class="tournament-select" required></select></label>
          <label>กีฬา <select name="sport_id" class="sport-select" required></select></label>
          <label>หน่วยงาน / สังกัด (ถ้ามี) <select name="department_code" class="department-select"></select></label>
          <div class="form-row"><label>โลโก้ทีม <input type="file" id="team-logo-file" accept="image/jpeg,image/png,image/webp"></label><label>ลิงก์โลโก้ <input name="logo_url"></label></div>
          <label>รหัสนักกีฬากัปตัน <input name="captain_student_id"></label>
          <label>สถานะ <select name="status"><option value="submitted">submitted</option><option value="approved">approved</option><option value="rejected">rejected</option><option value="locked">locked</option></select></label>
          <div class="actions"><button class="btn primary" type="submit">บันทึก</button><button class="btn ghost reset-form" type="button">ล้างฟอร์ม</button></div>
        </form>
        <div class="card wide"><h2>ทีมทั้งหมด</h2><div class="table-wrap" id="teams-table"></div></div>
        <form class="card form-card" id="member-form">
          <h2>รายชื่อนักกีฬา</h2>
          <input type="hidden" name="id">
          <label>ทีม <select name="team_id" id="member-team-select" required></select></label>
          <label>รหัสนักกีฬา <input name="student_code" required></label>
          <div class="form-row">
            <label>บทบาท <select name="role"><option value="player">นักกีฬา</option><option value="captain">กัปตัน</option><option value="coach">ผู้ฝึกสอน</option><option value="substitute">สำรอง</option></select></label>
            <label>เบอร์เสื้อ <input name="shirt_number"></label>
            <label>ตำแหน่ง <input name="field_position" placeholder="GK, CB, CM, ST"></label>
          </div>
          <div class="actions"><button class="btn primary" type="submit">บันทึก</button><button class="btn ghost reset-form" type="button">ล้างฟอร์ม</button></div>
        </form>
        <div class="card wide"><h2>สมาชิกทีม</h2><div class="table-wrap" id="members-table"></div></div>
        <div class="card full">
          <h2>นำเข้า CSV รายชื่อนักกีฬา</h2>
          <textarea class="csv-input" id="members-csv" placeholder="team_id,student_code,role,shirt_number,field_position"></textarea>
          <div class="actions"><button class="btn" id="members-csv-check" type="button">ตรวจ CSV</button><button class="btn primary" id="members-csv-import" type="button">นำเข้า</button></div>
          <div class="ai-output compact" id="members-import-result"></div>
        </div>
      </div>
    </section>

    <section class="view app-panel" id="view-matches" hidden>
      <?php require __DIR__ . '/pages/tournaments/TournamentList.php'; ?>
      <?php require __DIR__ . '/pages/tournaments/TournamentCreate.php'; ?>
      <?php require __DIR__ . '/pages/tournaments/TournamentDashboard.php'; ?>
      <?php require __DIR__ . '/components/tournaments/TournamentForm.php'; ?>
    </section>

    <section class="view official-console" id="view-official" hidden>
      <header class="official-console-head">
        <div><h1>เลือกหน้าบันทึกการแข่งขัน</h1><p>ระบบจะเปิดหน้าเฉพาะตามรูปแบบกติกาที่ผูกกับแมตช์</p></div>
        <div class="official-head-actions"><button class="btn" id="official-back-button" type="button" aria-label="กลับหน้าจัดการ"><i data-lucide="layout-dashboard"></i><span>กลับหน้าจัดการ</span></button><div class="official-user-mark"><i data-lucide="clipboard-pen-line"></i><span>ผู้บันทึกผลการแข่งขัน</span></div></div>
      </header>
      <div class="official-console-layout">
        <aside class="official-match-rail">
          <div class="official-panel-heading"><h2>แมตช์ที่รับผิดชอบ</h2><button class="icon-button" id="official-refresh" type="button" aria-label="โหลดรายการแมตช์ใหม่"><i data-lucide="refresh-cw"></i></button></div>
          <h3>กำลังแข่งขัน</h3>
          <div class="official-match-list" id="official-live-matches"></div>
          <h3>กำลังจะเริ่ม</h3>
          <div class="official-match-list" id="official-upcoming-matches"></div>
          <h3>จบแล้วล่าสุด</h3>
          <div class="official-match-list" id="official-completed-matches"></div>
        </aside>
        <div class="official-workbench" id="official-center-host">
          <div class="official-routing-empty"><i data-lucide="mouse-pointer-click"></i><h2>เลือกแมตช์จากรายการ</h2><p>แมตช์ SCORE และ SET จะเปิดหน้าบันทึกแยกทันที ส่วนรูปแบบอื่นจะแจ้งว่ายังอยู่ระหว่างจัดทำ</p></div>
        </div>
      </div>
    </section>

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
  <script src="<?= htmlspecialchars(versioned_asset_url('public/assets/app.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
