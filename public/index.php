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
      <button class="tab admin-link" data-view-button="official" hidden>ศูนย์ควบคุมการแข่งขัน</button>
      <button class="tab admin-link" data-view-button="news" hidden>ข่าวสาร</button>
      <button class="tab admin-link" data-view-button="users" hidden>ผู้ดูแลระบบ</button>
      <button class="tab admin-link" data-view-button="reports" hidden>รายงาน</button>
    </nav>
    <div class="session-box">
      <span id="current-user-label">ผู้เยี่ยมชม</span>
      <a class="btn small ghost frontend-link" id="frontend-view-button" href="./" data-view-button="public" title="ดูหน้าเว็บไซต์" aria-label="ดูหน้าเว็บไซต์" hidden><i data-lucide="monitor"></i><span>ดูหน้าเว็บไซต์</span></a>
      <button class="btn small icon-button" id="profile-toggle" type="button" title="บัญชีของฉัน" aria-label="บัญชีของฉัน" hidden><i data-lucide="user-cog"></i></button>
      <button class="btn small" id="login-toggle" type="button">เข้าสู่ระบบ</button>
      <button class="btn small ghost" id="logout-button" type="button" hidden>ออกจากระบบ</button>
    </div>
  </header>
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

    <section class="view content-band" id="view-public-news" hidden>
      <div class="section-head"><h2>ข่าวสาร</h2><select id="news-tournament-filter" aria-label="กรองข่าวตามทัวร์นาเมนต์"></select></div>
      <div class="news-grid" id="public-news-page"></div>
    </section>

    <section class="view article-page" id="view-public-news-detail" hidden>
      <div id="public-news-detail" aria-live="polite"></div>
    </section>

    <section class="view app-panel" id="view-dashboard" hidden>
      <div class="section-head">
        <h2>แดชบอร์ด</h2>
        <button class="btn small" id="dashboard-ai-button" type="button">สรุปภาพรวม</button>
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
            <label>ประเภทผู้เล่น <select name="player_type"><option value="team">ทีม</option><option value="individual">บุคคล</option></select></label>
            <label>จำนวนผู้เล่น <input type="number" name="team_size" min="1" value="1"></label>
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
      <div class="section-head tournament-workspace-heading">
        <div>
          <p class="section-kicker">SPORT TOURNAMENT / ศูนย์จัดการข้อมูลภายใน</p>
          <h2>จัดการทัวร์นาเมนต์</h2>
          <p class="muted">ไม่มีระบบสมัคร — ข้อมูลทั้งหมดนำเข้าและจัดการโดยเจ้าหน้าที่</p>
        </div>
        <div class="tournament-workspace-controls">
          <label>ทัวร์นาเมนต์
            <select id="workflow-tournament-select" aria-label="เลือกทัวร์นาเมนต์"></select>
          </label>
          <button class="btn" id="workflow-settings-button" type="button"><i data-lucide="settings-2"></i><span>ตั้งค่าขั้นนี้</span></button>
        </div>
      </div>
      <div class="tournament-context" id="tournament-context">
        <div><strong id="workflow-tournament-name">ยังไม่มีทัวร์นาเมนต์</strong><span id="workflow-tournament-meta">สร้างทัวร์นาเมนต์เพื่อเริ่มจัดการข้อมูล</span></div>
        <span class="workflow-status" id="workflow-tournament-status">ฉบับร่าง</span>
      </div>
      <div class="workflow-layout">
        <nav class="workflow-steps" id="workflow-steps" aria-label="ลำดับการจัดการทัวร์นาเมนต์">
          <div class="workflow-progress-summary">
            <span id="workflow-progress-label">ความคืบหน้า 0 จาก 11 ขั้น</span>
            <div class="workflow-progress-track" aria-hidden="true"><span id="workflow-progress-bar"></span></div>
          </div>
          <button type="button" data-workflow-step-button="1"><span>1</span><strong>ข้อมูลทัวร์นาเมนต์</strong><small>ชื่อ วันแข่งขัน และสถานะ</small></button>
          <button type="button" data-workflow-step-button="2"><span>2</span><strong>กีฬาที่จัด</strong><small>เลือกจากข้อมูลกีฬากลาง</small></button>
          <button type="button" data-workflow-step-button="3"><span>3</span><strong>กำหนดชุดกติกา</strong><small>เลือกกติกากลางที่ใช้</small></button>
          <button type="button" data-workflow-step-button="4"><span>4</span><strong>สังกัดที่เข้าร่วม (ถ้ามี)</strong><small>ใช้เฉพาะงานที่มีสังกัด</small></button>
          <button type="button" data-workflow-step-button="5"><span>5</span><strong>เพิ่มนักกีฬา</strong><small>เตรียมรายชื่อนักกีฬา</small></button>
          <button type="button" data-workflow-step-button="6"><span>6</span><strong>สร้างทีม</strong><small>แยกทีมตามกีฬา</small></button>
          <button type="button" data-workflow-step-button="7"><span>7</span><strong>จัดสมาชิกทีม</strong><small>เพิ่มนักกีฬาเข้าทีม</small></button>
          <button type="button" data-workflow-step-button="8"><span>8</span><strong>ตรวจความพร้อม</strong><small>เช็กข้อมูลก่อนแข่งขัน</small></button>
          <button type="button" data-workflow-step-button="9"><span>9</span><strong>ตารางแข่งขัน</strong><small>สร้างวันเวลาและคู่แข่ง</small></button>
          <button type="button" data-workflow-step-button="10"><span>10</span><strong>บันทึกผล</strong><small>คะแนนและเหตุการณ์แข่งขัน</small></button>
          <button type="button" data-workflow-step-button="11"><span>11</span><strong>ข่าวและรายงาน</strong><small>สรุปและเผยแพร่ผล</small></button>
        </nav>
        <div class="workflow-content">
          <header class="workflow-stage-head">
            <div>
              <span class="workflow-step-count" id="workflow-step-count">ขั้นที่ 1 จาก 11</span>
              <h3 id="workflow-step-title">ข้อมูลทัวร์นาเมนต์</h3>
              <p id="workflow-step-description">สร้างหรือแก้ไขข้อมูลหลักของทัวร์นาเมนต์ก่อนเริ่มขั้นตอนถัดไป</p>
            </div>
            <span class="workflow-step-state" id="workflow-step-state">กำลังทำ</span>
          </header>
          <div class="workflow-notice" id="workflow-notice"><i data-lucide="info"></i><span>เลือกทัวร์นาเมนต์ แล้วดำเนินการตามลำดับทีละขั้น</span></div>
          <div class="grid">
        <section class="data-section workflow-stage" id="workflow-sports-section" data-workflow-step="2" hidden>
          <div class="data-heading"><div><h2>กีฬาที่จัดในทัวร์นาเมนต์</h2><p class="muted">รายการนี้อ้างอิงจากข้อมูลทัวร์นาเมนต์ แก้ไขจากจุดเดียวเพื่อไม่ให้ข้อมูลซ้ำกัน</p></div><button class="btn" id="workflow-edit-sports" type="button"><i data-lucide="settings-2"></i><span>แก้ไขรายการกีฬา</span></button></div>
          <div class="table-wrap" id="workflow-sports-table"></div>
        </section>
        <form class="card form-card" id="tournament-sport-setting-form">
          <h2>กำหนดชุดกติกาที่ใช้</h2>
          <p class="muted">เลือกจากชุดกติกากลางของกีฬานี้ ระบบจะเก็บเวอร์ชันที่เลือกไว้กับทัวร์นาเมนต์และแมตช์</p>
          <input type="hidden" name="tournament_id">
          <input type="hidden" name="sport_id">
          <label>กีฬา <input name="sport_name" readonly></label>
          <label>ชุดกติกาที่ใช้ <select name="rule_version_id" id="workflow-rule-version" required></select></label>
          <label>สถานะการตั้งค่า <select name="status"><option value="pending">ยังตั้งค่าไม่ครบ</option><option value="ready">พร้อมใช้งาน</option><option value="locked">ล็อกการตั้งค่า</option></select></label>
          <label>หมายเหตุ <textarea name="notes" placeholder="รายละเอียดเฉพาะของกีฬานี้ในทัวร์นาเมนต์"></textarea></label>
          <div class="actions"><button class="btn primary" type="submit">ยืนยันชุดกติกาที่ใช้</button></div>
        </form>
        <section class="data-section workflow-stage" id="workflow-departments-section" data-workflow-step="4" hidden>
          <div class="data-heading"><div><h2>สังกัดที่เข้าร่วม (ถ้ามี)</h2><p class="muted">ใช้เมื่อรายการแข่งขันต้องการแยกทีมตามหน่วยงาน ขั้นตอนนี้เว้นได้</p></div></div>
          <div class="workflow-setting-layout">
            <div>
              <label>กีฬา <select id="workflow-department-sport"></select></label>
              <div class="department-checklist" id="workflow-department-options"></div>
              <div class="actions"><button class="btn primary" id="workflow-save-departments" type="button"><i data-lucide="check"></i><span>บันทึกสังกัดที่เข้าร่วม</span></button></div>
            </div>
            <aside class="workflow-summary" id="workflow-department-summary"></aside>
          </div>
        </section>
        <section class="data-section workflow-stage" id="workflow-readiness-section" data-workflow-step="8" hidden>
          <div class="data-heading"><div><h2>ตรวจสอบความพร้อม</h2><p class="muted">ตรวจข้อมูลที่ต้องมี ก่อนจัดสายและสร้างตารางแข่งขัน</p></div></div>
          <div id="workflow-readiness"></div>
        </section>
        <section class="data-section workflow-stage" id="workflow-publish-section" data-workflow-step="11" hidden>
          <div class="data-heading"><div><h2>ข่าวและรายงาน</h2><p class="muted">ใช้ข้อมูลการแข่งขันที่ยืนยันแล้วสำหรับประชาสัมพันธ์และจัดทำรายงาน</p></div></div>
          <div class="workflow-final-actions">
            <button class="workflow-action" type="button" data-workflow-view="news"><i data-lucide="newspaper"></i><strong>จัดทำข่าวประชาสัมพันธ์</strong><span>ร่างข่าว สร้างแบนเนอร์ และเผยแพร่</span></button>
            <button class="workflow-action" type="button" data-workflow-view="reports"><i data-lucide="file-chart-column"></i><strong>จัดทำรายงาน</strong><span>สรุปผล KPI งบประมาณ และเอกสารประกอบ</span></button>
          </div>
        </section>
        <form class="card form-card" id="tournament-form">
          <h2>รายการแข่ง</h2>
          <input type="hidden" name="id">
          <label>ชื่อรายการ <input name="name" required></label>
          <div class="form-row">
            <label>โลโก้รายการแข่งขัน <input type="file" id="tournament-logo-file" accept="image/jpeg,image/png,image/webp"></label>
            <label>ลิงก์โลโก้ <input name="logo_url"></label>
          </div>
          <div class="form-row tournament-meta-row">
            <fieldset class="sport-picker-fieldset">
              <legend>กีฬา <span class="required-mark">เลือกได้หลายรายการ</span></legend>
              <div class="sport-picker-toolbar">
                <label>ประเภท
                  <select id="tournament-sport-type" aria-label="กรองประเภทกีฬา">
                    <option value="">ทั้งหมด</option>
                    <option value="team">ทีม</option>
                    <option value="individual">บุคคล</option>
                  </select>
                </label>
              </div>
              <div class="sport-dropdown">
                <button class="dropdown-button" id="tournament-sports-toggle" type="button" aria-expanded="false" aria-controls="tournament-sports-options"><span id="tournament-sports-summary">เลือกกีฬา</span><i data-lucide="chevron-down"></i></button>
                <div id="tournament-sports-options" class="sport-picker" role="group" aria-label="เลือกกีฬาสำหรับทัวร์นาเมนท์" hidden></div>
              </div>
            </fieldset>
            <label>ปี / รุ่น (ถ้ามี) <input name="academic_year" placeholder="เช่น 2026"></label>
          </div>
          <div class="form-row"><label>วันเริ่มการแข่งขัน <input type="date" name="starts_at"></label><label>วันสิ้นสุดการแข่งขัน <input type="date" name="ends_at"></label></div>
          <div class="form-row">
            <label>รูปแบบ <select name="format"><option value="knockout">knockout</option><option value="round_robin">round_robin</option><option value="group">group</option><option value="group_knockout">group_knockout</option></select></label>
            <label>สถานะ <select name="status"><option value="draft">ฉบับร่าง</option><option value="data_entry">กำลังเตรียมข้อมูล</option><option value="ready">พร้อมแข่งขัน</option><option value="in_progress">กำลังแข่งขัน</option><option value="completed">จบการแข่งขัน</option><option value="cancelled">ยกเลิก</option></select></label>
          </div>
          <div class="actions"><button class="btn primary" type="submit">บันทึกรายการ</button><button class="btn ghost reset-form" type="button">ล้างฟอร์ม</button></div>
        </form>
        <div class="card wide"><h2>รายการแข่งทั้งหมด</h2><div class="table-wrap" id="tournaments-table"></div></div>
        <section class="card full workflow-stage" id="fixture-generator" data-workflow-step="9" hidden>
          <h2>จัดสายการแข่งขัน</h2>
          <p class="muted">สร้างคู่ตามรูปแบบที่กำหนดในทัวร์นาเมนต์ แล้วกำหนดวัน เวลา และสนามในแต่ละแมตช์ รอบที่ยังไม่ทราบทีมจะแสดงเป็นช่องรอทีมเพื่อให้เจ้าหน้าที่เติมภายหลัง</p>
          <div class="form-row"><label>กีฬา <select id="fixture-sport-select"></select></label><label>จำนวนกลุ่ม (สำหรับรอบแบ่งกลุ่ม) <input id="fixture-group-count" type="number" min="2" max="8" value="2"></label></div>
          <button class="btn primary" id="generate-fixtures-button" type="button">สร้างสายการแข่งขัน</button>
        </section>
        <form class="card form-card" id="match-form">
          <h2>แมตช์</h2>
          <input type="hidden" name="id">
          <label>รายการแข่ง <select name="tournament_id" id="match-tournament-select" required></select></label>
          <div class="form-row">
            <label>รอบ <input name="round_name" value="รอบแรก"></label>
            <label>เลขแมตช์ <input name="match_number"></label>
          </div>
          <div class="form-row">
            <label>ทีม 1 <select name="team1_id" id="match-team1-select"></select></label>
            <label>ทีม 2 <select name="team2_id" id="match-team2-select"></select></label>
          </div>
          <label>วันเวลา <input type="datetime-local" name="scheduled_at"></label>
          <label>สนาม <input name="venue"></label>
          <label>ผู้ตัดสิน <input name="referee"></label>
          <label>สถานะ <select name="status"><option value="scheduled">scheduled</option><option value="live">live</option><option value="completed">completed</option><option value="postponed">postponed</option><option value="cancelled">cancelled</option></select></label>
          <div class="actions"><button class="btn primary" type="submit">บันทึกแมตช์</button><button class="btn ghost reset-form" type="button">ล้างฟอร์ม</button></div>
        </form>
        <form class="card form-card" id="result-form">
          <h2>บันทึกผล</h2>
          <label>แมตช์ <select name="match_id" id="result-match-select" required></select></label>
          <p class="muted" id="result-mode-help">ระบบจะเปลี่ยนรูปแบบผลตามชุดกติกาของกีฬาที่เลือก</p>
          <div class="form-row">
            <label><span id="result-side1-label">ผล/คะแนนทีม 1</span> <input type="number" name="team1_score" min="0" required></label>
            <label><span id="result-side2-label">ผล/คะแนนทีม 2</span> <input type="number" name="team2_score" min="0" required></label>
          </div>
          <label>ผู้ชนะจาก Tie-break <select name="winner_team_id" id="result-winner-select"><option value="">ไม่ระบุ / เสมอ</option></select></label>
          <button class="btn primary" type="submit">บันทึกผล</button>
        </form>
        <div class="card full"><h2>แมตช์ทั้งหมด</h2><div class="table-wrap" id="matches-table"></div></div>
        <section class="match-center full" id="match-center-panel">
          <div class="data-heading"><div><p class="section-kicker">LIVE MATCH CENTER</p><h2>ควบคุมการแข่งขันและบันทึกเหตุการณ์</h2></div><select id="match-center-select" aria-label="เลือกแมตช์"></select></div>
          <div id="match-center-empty" class="empty-state">เลือกแมตช์เพื่อเริ่มบันทึกเวลาและเหตุการณ์</div>
          <div id="match-center-content" hidden>
            <div class="match-center-summary"><span class="badge" id="center-status">รอแข่งขัน</span><span id="center-rule-summary">ระบบคะแนนรวม</span></div>
            <div class="scoreboard"><div id="center-team1">ทีม 1</div><strong id="center-score">0 - 0</strong><div id="center-team2">ทีม 2</div></div>
            <div class="match-rosters" aria-label="รายชื่อนักกีฬาในแมตช์">
              <section><h3 id="center-roster1-title">รายชื่อนักกีฬาทีม 1</h3><div id="center-roster1" class="match-roster-list"></div></section>
              <section><h3 id="center-roster2-title">รายชื่อนักกีฬาทีม 2</h3><div id="center-roster2" class="match-roster-list"></div></section>
            </div>
            <div class="clock-bar"><strong id="center-clock">00:00</strong><span id="center-period">ช่วงที่ 1</span><div class="actions"><button class="btn primary" id="clock-start" type="button"><i data-lucide="play"></i><span>เริ่มการแข่งขัน</span></button><button class="btn" id="clock-pause" type="button"><i data-lucide="pause"></i><span>พัก</span></button><button class="btn" id="clock-next" type="button">ช่วงถัดไป</button><button class="btn" id="clock-overtime" type="button">ต่อเวลา</button><button class="btn danger" id="match-finish" type="button"><i data-lucide="flag"></i><span>จบการแข่งขัน</span></button></div></div>
            <form id="match-event-form" class="event-entry">
              <input type="hidden" name="match_id">
              <label>เหตุการณ์ <select name="event_type" id="event-type" required></select></label>
              <label>ทีม <select name="team_id" id="event-team"></select></label>
              <label>นักกีฬา <select name="student_id" id="event-student"></select></label>
              <label>แต้ม <input type="number" name="points" id="event-points"></label>
              <label>ทดเวลา (นาที) <input type="number" name="added_time" min="0" value="0"></label>
              <label class="event-note">หมายเหตุ <input name="note"></label>
              <button class="btn primary" type="submit"><i data-lucide="plus"></i><span>บันทึกเหตุการณ์</span></button>
              <p class="event-entry-help" id="event-entry-help">เริ่มการแข่งขันก่อนบันทึกคะแนนหรือเหตุการณ์</p>
            </form>
            <div class="table-wrap" id="match-events-table"></div>
          </div>
        </section>
        <section class="data-section full"><div class="data-heading"><h2>ตารางคะแนน</h2><select id="standings-tournament-filter" aria-label="เลือกรายการแข่งขัน"></select></div><div class="table-wrap" id="standings-table"></div></section>
          </div>
          <footer class="workflow-navigation" aria-label="ไปยังขั้นตอนก่อนหน้าหรือถัดไป">
            <button class="btn ghost" id="workflow-previous-button" type="button"><i data-lucide="arrow-left"></i><span>ย้อนกลับ</span></button>
            <span id="workflow-navigation-hint">บันทึกข้อมูลในขั้นนี้ให้เรียบร้อย แล้วไปขั้นถัดไป</span>
            <button class="btn primary" id="workflow-next-button" type="button"><span>ขั้นถัดไป</span><i data-lucide="arrow-right"></i></button>
          </footer>
        </div>
      </div>
    </section>

    <section class="view official-console" id="view-official" hidden>
      <header class="official-console-head">
        <div><h1>ศูนย์ควบคุมการแข่งขัน</h1><p>บันทึกคะแนน เหตุการณ์ และตรวจสอบนักกีฬาประจำแมตช์</p></div>
        <div class="official-user-mark"><i data-lucide="clipboard-pen-line"></i><span>ผู้บันทึกผลการแข่งขัน</span></div>
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
          <div class="empty-state">เลือกแมตช์จากรายการด้านซ้ายเพื่อเริ่มปฏิบัติงาน</div>
        </div>
        <aside class="official-event-rail">
          <div class="official-panel-heading"><h2>เหตุการณ์ล่าสุด</h2><span id="official-event-count">0 รายการ</span></div>
          <div id="official-event-feed" class="official-event-feed"><div class="empty-state">ยังไม่มีเหตุการณ์</div></div>
        </aside>
      </div>
    </section>

    <section class="view app-panel" id="view-news" hidden>
      <div class="grid">
        <form class="card form-card wide" id="news-form">
          <h2>ข่าวประชาสัมพันธ์</h2>
          <input type="hidden" name="id">
          <label>เริ่มจากเทมเพลตข่าว <select id="news-template-select"><option value="">เลือกเทมเพลต</option><option value="announcement">ประกาศทั่วไป</option><option value="preview">ก่อนการแข่งขัน</option><option value="result">สรุปผลการแข่งขัน</option><option value="honor">รางวัลเกียรติยศ</option></select></label>
          <section class="ai-news-builder" aria-labelledby="ai-news-title">
            <div class="tool-heading">
              <div>
                <span class="tool-kicker">AI NEWSROOM</span>
                <h3 id="ai-news-title">ผู้ช่วยเขียนข่าวกีฬา</h3>
              </div>
              <span class="badge" id="news-ai-status">พร้อมสร้างร่าง</span>
            </div>
            <div class="form-row">
              <label>กีฬา <select id="news-ai-sport"><option value="">ทุกกีฬา</option></select></label>
              <label>รายการแข่งขัน <select id="news-ai-tournament"><option value="">ทุกรายการ</option></select></label>
            </div>
            <label>แมตช์อ้างอิง <select id="news-ai-match"><option value="">เลือกแมตช์ล่าสุดอัตโนมัติ</option></select></label>
            <div class="form-row">
              <label>รูปแบบข่าว
                <select id="news-ai-story-type">
                  <option value="result">สรุปผลการแข่งขัน</option>
                  <option value="preview">ประชาสัมพันธ์ก่อนแข่ง</option>
                  <option value="highlight">ไฮไลต์และความประทับใจ</option>
                </select>
              </label>
              <label>โทนการเขียน
                <select id="news-ai-tone">
                  <option value="official">ทางการ กระชับ</option>
                  <option value="energetic">สนุก เร้าใจ</option>
                  <option value="inspiring">สร้างแรงบันดาลใจ</option>
                </select>
              </label>
            </div>
            <label>ประเด็นที่ต้องการเน้น <textarea id="news-ai-instruction" placeholder="เช่น ชื่นชมนักกีฬา บรรยากาศการแข่งขัน หรือเชิญชวนติดตามรอบถัดไป"></textarea></label>
            <div class="ai-builder-actions">
              <button class="btn primary" id="news-ai-button" type="button"><i data-lucide="sparkles"></i><span>สร้างร่างด้วย AI</span></button>
            </div>
          </section>
          <div class="form-row">
            <label>ประเภท <select name="news_type"><option value="general">ข่าวสารทั่วไป</option><option value="department">เนื้อหาหน่วย/สาขา</option><option value="hall_of_fame">Hall of Fame</option></select></label>
            <label>สาขา <select name="department_code" class="department-select optional"><option value="">ไม่ระบุ</option></select></label>
          </div>
          <fieldset><legend>เชื่อมโยงข่าวกับระบบ</legend><div class="form-row"><label>กีฬา <select name="sport_id" class="sport-select optional"></select></label><label>รายการแข่งขัน <select name="tournament_id" class="tournament-select optional"></select></label><label>ทีม <select name="team_id" id="news-team-link"></select></label><label>แมตช์ <select name="match_id" id="news-match-link"></select></label></div></fieldset>
          <label>หัวข้อ <input name="title" required></label>
          <label>ลิงก์ย่อ <input name="slug"></label>
          <label>คำโปรย <textarea name="excerpt"></textarea></label>
          <label>เนื้อหา <textarea name="body" class="tall"></textarea></label>
          <label>ลิงก์เว็บไซต์ที่เกี่ยวข้อง <input name="source_url" type="url" inputmode="url" placeholder="https://example.com"></label>
          <div class="form-row">
            <label>รูปปก <input type="file" id="news-file" accept="image/*"></label>
            <label>ลิงก์รูป <input name="cover_image_url" id="news-cover-url"></label>
          </div>
          <div class="cover-tools">
            <div class="cover-thumb" id="news-cover-preview"><i data-lucide="image"></i><span>ยังไม่มีรูปปก</span></div>
            <button class="btn" id="banner-open-button" type="button"><i data-lucide="layout-template"></i><span>สร้างแบนเนอร์</span></button>
          </div>
          <div class="form-row">
            <label>สถานะ <select name="status"><option value="draft">draft</option><option value="published">published</option><option value="archived">archived</option></select></label>
            <label class="checkbox-line"><input type="checkbox" name="is_pinned"> ปักหมุด</label>
          </div>
          <div class="actions"><button class="btn primary" type="submit">บันทึกข่าว</button><button class="btn ghost reset-form" type="button">ล้างฟอร์ม</button></div>
        </form>
        <div class="card full"><h2>ข่าวทั้งหมด</h2><div class="table-wrap" id="news-table"></div></div>
      </div>
    </section>

    <section class="view app-panel" id="view-users" hidden>
      <div class="grid">
        <div class="card full"><h2>การรับทีมงาน</h2><label class="checkbox-line"><input id="staff-invites-enabled" type="checkbox"> เปิดใช้ลิงก์คำเชิญทีมงาน</label><p class="muted">เมื่อปิด ลิงก์คำเชิญที่ยังไม่หมดอายุจะใช้ไม่ได้จนกว่าจะเปิดอีกครั้ง</p></div>
        <form class="card form-card" id="invite-form">
          <h2>เชิญทีมงานใหม่</h2>
          <p class="muted">ลิงก์คำเชิญใช้ได้ 45 นาที และใช้ได้ครั้งเดียว</p>
          <label>อีเมล <input type="email" name="email" required></label>
          <label>ชื่อ <input name="name"></label>
          <label>บทบาท <select name="role" id="invite-role-select" required></select></label>
          <label>สาขา <select name="department_code" class="department-select optional"><option value="">ไม่จำกัด</option></select></label>
          <label>รหัสกีฬาที่รับผิดชอบ <input name="sport_ids" placeholder="1,2"></label>
          <div class="actions"><button class="btn primary" type="submit">สร้างลิงก์เชิญ</button><button class="btn ghost reset-form" type="button">ล้างฟอร์ม</button></div>
          <div class="invite-link-box" id="invite-link-box" hidden></div>
        </form>
        <form class="card form-card" id="user-form">
          <h2>สมาชิกผู้ดูแล</h2>
          <input type="hidden" name="id">
          <label>อีเมล <input type="email" name="email" required></label>
          <label>ชื่อ <input name="name"></label>
          <label>รหัสผ่านใหม่ <input type="password" name="password" autocomplete="new-password" minlength="8"></label>
          <label>ยืนยันรหัสผ่านใหม่ <input type="password" name="password_confirm" autocomplete="new-password" minlength="8"></label>
          <label>บทบาท <select name="role" id="user-role-select" required></select></label>
          <label>สาขา <select name="department_code" class="department-select optional"><option value="">ไม่จำกัด</option></select></label>
          <label>รหัสกีฬาที่รับผิดชอบ <input name="sport_ids" placeholder="1,2"></label>
          <label class="checkbox-line"><input type="checkbox" name="is_active" checked> เปิดใช้งาน</label>
          <div class="actions"><button class="btn primary" type="submit">บันทึกสมาชิก</button><button class="btn ghost reset-form" type="button">ล้างฟอร์ม</button></div>
        </form>
        <div class="card wide"><h2>สมาชิกและสิทธิ์</h2><div class="table-wrap" id="users-table"></div></div>
        <div class="card wide"><h2>คำเชิญที่สร้างไว้</h2><div class="table-wrap" id="invites-table"></div></div>
        <div class="card full"><div class="section-head"><h2>ประวัติการใช้งานหลังบ้าน</h2><input id="audit-search" type="search" placeholder="ค้นหาอีเมล การดำเนินการ หรือข้อมูล" aria-label="ค้นหา Audit log"></div><div class="table-wrap" id="audit-full-table"></div></div>
      </div>
    </section>

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

    <section class="view app-panel" id="view-reports" hidden>
      <div class="report-dashboard" id="report-dashboard">
        <div class="section-head report-page-head">
          <div><p class="section-kicker">รายงานจากข้อมูลการแข่งขัน</p><h2>รายงานผลกีฬา</h2></div>
          <div class="actions">
            <span class="badge"><i data-lucide="database"></i> ใช้ข้อมูลจริงจากระบบ</span>
            <button class="btn primary" id="report-create-button" type="button"><i data-lucide="file-chart-column"></i><span>สรุปผลรายงาน</span></button>
          </div>
        </div>
        <div class="report-summary-strip" id="report-summary-strip"></div>
        <div class="report-filters" aria-label="ตัวกรองรายงาน">
          <label>ค้นหา<input id="report-search" type="search" placeholder="ชื่อรายงาน ผู้จัดทำ หรือหน่วยงาน"></label>
          <label>ปี / รุ่น<select id="report-year-filter"><option value="">ทุกปี</option></select></label>
          <label>หมวดหมู่<select id="report-category-filter"><option value="">ทุกหมวดหมู่</option></select></label>
          <label>ประเภทแบบฟอร์ม<select id="report-template-filter"><option value="">ทุกแบบฟอร์ม</option></select></label>
          <label>สถานะ<select id="report-status-filter"><option value="">ทุกสถานะ</option><option value="draft">แบบร่าง</option><option value="review">รอตรวจสอบ</option><option value="completed">เสร็จสมบูรณ์</option><option value="published">เผยแพร่แล้ว</option></select></label>
        </div>
        <div class="table-wrap data-table" id="reports-table"></div>
      </div>

      <div class="report-builder" id="report-builder" hidden>
        <div class="section-head report-page-head">
          <div><p class="section-kicker">รายงานทางการจากข้อมูลระบบ</p><h2 id="report-builder-title">สรุปผลรายงาน</h2></div>
          <button class="btn ghost" id="report-back-button" type="button"><i data-lucide="arrow-left"></i><span>กลับหน้ารายงาน</span></button>
        </div>
        <ol class="report-stepper" id="report-stepper" aria-label="ขั้นตอนการสร้างรายงาน">
          <li class="active" data-report-step-indicator="3"><span>1</span>กำหนดขอบเขตและสรุปผล</li>
          <li data-report-step-indicator="4"><span>2</span>ตรวจสอบและส่งออก PDF</li>
        </ol>

        <form id="report-editor-form">
          <input type="hidden" name="id">
          <input type="hidden" name="category_id">
          <input type="hidden" name="template_id">
          <input type="hidden" name="status" value="draft">

          <section class="report-step" data-report-step="1">
            <div class="report-step-heading"><h3>เลือกหมวดหมู่รายงาน</h3><p>เลือกวัตถุประสงค์หลักของรายงานฉบับนี้</p></div>
            <div class="report-choice-grid" id="report-category-choices"></div>
          </section>

          <section class="report-step" data-report-step="2" hidden>
            <div class="report-step-heading"><h3>เลือกประเภทแบบฟอร์ม</h3><p id="report-selected-category-label"></p></div>
            <div class="report-choice-grid" id="report-template-choices"></div>
          </section>

          <section class="report-step" data-report-step="3" hidden>
            <section class="report-summary-generator" aria-labelledby="report-summary-generator-title">
              <div>
                <span class="report-summary-icon"><i data-lucide="database-zap"></i></span>
                <div><h3 id="report-summary-generator-title">สรุปผลจากข้อมูลในระบบ</h3><p>ระบบจะรวบรวมกีฬา ทีม นักกีฬา การแข่งขัน ตารางคะแนน และข่าว แล้วเรียบเรียงลงในรูปแบบรายงานทางการตายตัว</p></div>
              </div>
              <div class="report-summary-form" id="report-form">
                <button class="btn primary" id="report-summary-button" type="button"><i data-lucide="file-check-2"></i><span>สรุปผลรายงาน</span></button>
              </div>
              <div class="report-output" id="report-output">เลือกขอบเขตรายการแข่งขันด้านล่าง หรือใช้ข้อมูลทั้งหมด แล้วกด “สรุปผลรายงาน” ระบบจะเติมข้อมูลลงในเล่มรายงานให้อัตโนมัติ</div>
            </section>
            <div class="report-form-section" data-report-section="general">
              <h3><span>1</span>ข้อมูลพื้นฐานของรายงาน</h3>
              <div class="report-form-grid">
                <label class="span-2">ชื่อรายงาน <span class="required">*</span><input name="title" maxlength="255" required></label>
                <label>ชื่อโครงการ/กิจกรรม<input name="project_name"></label>
                <label>วันที่ดำเนินการ<input name="operation_date" type="date"></label>
                <label>สถานที่<input name="location"></label>
                <label>ปี / รุ่น (ถ้ามี)<input name="academic_year"></label>
                <label>ปีงบประมาณ<input name="fiscal_year"></label>
                <label>ภาคการศึกษา<select name="semester"><option value="">ไม่ระบุ</option><option value="1">ภาคการศึกษาที่ 1</option><option value="2">ภาคการศึกษาที่ 2</option><option value="summer">ภาคฤดูร้อน</option></select></label>
                <label>วันที่เริ่มต้น<input name="start_date" type="date"></label>
                <label>วันที่สิ้นสุด<input name="end_date" type="date"></label>
                <label>วันที่จัดทำ<input name="report_date" type="date" required></label>
                <label class="span-2">รายการแข่งขันที่ใช้อ้างอิง<select name="tournament_id" id="report-tournament-select"><option value="">ข้อมูลการแข่งขันทั้งหมด</option></select></label>
                <label>หน่วยงาน<input name="department"></label>
                <label>ผู้รับผิดชอบ<input name="responsible_person"></label>
                <label class="span-2">ผู้จัดทำรายงาน <span class="required">*</span><input name="prepared_by" required></label>
              </div>
            </div>

            <div class="report-form-section" data-report-section="principles">
              <h3><span>2</span>หลักการและวัตถุประสงค์</h3>
              <div class="report-form-grid">
                <label class="span-2">หลักการและเหตุผล<textarea name="rationale"></textarea></label>
                <label>วัตถุประสงค์<textarea name="objectives"></textarea></label>
                <label>กลุ่มเป้าหมาย<textarea name="target_group"></textarea></label>
              </div>
            </div>

            <div class="report-form-section" data-report-section="performance">
              <h3><span>3</span>ข้อมูลการดำเนินงาน</h3>
              <div class="report-data-source"><i data-lucide="database"></i><div><strong>เชื่อมโยงข้อมูลกีฬาเดิม</strong><p id="report-source-summary">ระบบจะคำนวณจำนวนกีฬา การแข่งขัน หน่วยงาน และนักกีฬาให้อัตโนมัติ</p></div></div>
              <label>รายละเอียดการดำเนินงาน<textarea name="operation_detail"></textarea></label>
            </div>

            <div class="report-form-section" data-report-section="sports_results">
              <h3><span>4</span>ผลการแข่งขัน</h3>
              <p class="muted">ผลการแข่งขันจะดึงจากรายการแข่งขันที่เลือกโดยตรง และไม่สร้างข้อมูลซ้ำ</p>
              <div class="report-inline-preview" id="report-results-preview"></div>
            </div>

            <div class="report-form-section" data-report-section="kpi">
              <div class="report-section-title"><h3><span>5</span>ผลการดำเนินงานตามตัวชี้วัด</h3><button class="btn small" id="report-add-kpi" type="button"><i data-lucide="plus"></i><span>เพิ่ม KPI</span></button></div>
              <div class="kpi-editor" id="report-kpi-editor"></div>
            </div>

            <div class="report-form-section" data-report-section="budget">
              <h3><span>6</span>งบประมาณ</h3>
              <div class="report-form-grid budget-fields">
                <label>งบประมาณที่ได้รับ (บาท)<input name="budget_allocated" type="number" min="0" step="0.01" value="0"></label>
                <label>งบประมาณที่ใช้จริง (บาท)<input name="budget_actual" type="number" min="0" step="0.01" value="0"></label>
                <label>งบประมาณคงเหลือ<input name="budget_remaining" readonly value="0.00"></label>
                <label>ร้อยละการใช้จ่าย<input name="budget_usage" readonly value="0.00"></label>
                <label class="span-2">หมายเหตุ<textarea name="budget_note"></textarea></label>
              </div>
            </div>

            <div class="report-form-section" data-report-section="outcomes">
              <h3><span>7</span>ผลที่ได้รับ</h3>
              <div class="report-form-grid">
                <label>ผลการดำเนินงาน<textarea name="performance_result"></textarea></label>
                <label>ผลลัพธ์ที่เกิดขึ้นและประโยชน์ที่ได้รับ<textarea name="benefits"></textarea></label>
                <label class="span-2">ผลต่อผู้เข้าร่วม/นักกีฬา<textarea name="participant_impact"></textarea></label>
              </div>
            </div>

            <div class="report-form-section" data-report-section="problems">
              <h3><span>8</span>ปัญหาและข้อเสนอแนะ</h3>
              <div class="report-form-grid">
                <label>ปัญหาและอุปสรรค<textarea name="obstacles"></textarea></label>
                <label>วิธีการแก้ไข<textarea name="solutions"></textarea></label>
                <label class="span-2">ข้อเสนอแนะและแนวทางพัฒนาครั้งต่อไป<textarea name="recommendations"></textarea></label>
              </div>
            </div>

            <div class="report-form-section" data-report-section="executive">
              <h3><span>9</span>สรุปสำหรับผู้บริหาร</h3>
              <label>บทสรุปผู้บริหาร<textarea name="executive_summary" class="tall" placeholder="สรุปสาระสำคัญ ผลการดำเนินงาน และประเด็นที่ผู้บริหารควรทราบ"></textarea></label>
            </div>

            <div class="report-form-section" data-report-section="attachments">
              <h3><span>10</span>หลักฐานประกอบ</h3>
              <div class="report-form-grid">
                <label>เลือกไฟล์<input id="report-attachment-file" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx" multiple></label>
                <label>หมายเหตุไฟล์แนบ<input id="report-attachment-note"></label>
              </div>
              <p class="muted">รองรับ JPG, PNG, WEBP, PDF, DOC และ DOCX ขนาดไม่เกิน 8MB ต่อไฟล์ ระบบจะอัปโหลดหลังบันทึกรายงาน</p>
              <div id="report-attachments-list"></div>
            </div>

          </section>

          <section class="report-step" data-report-step="4" hidden>
            <div class="report-step-heading"><h3>ตัวอย่างรายงานแบบเป็นเล่ม</h3><p>ตรวจสอบรูปแบบเอกสารทางการก่อนดาวน์โหลดไฟล์ PDF</p></div>
            <div class="report-preview-shell" id="stored-report-preview"></div>
          </section>

          <div class="report-wizard-actions">
            <button class="btn ghost" id="report-prev-step" type="button" hidden><i data-lucide="arrow-left"></i><span>ย้อนกลับ</span></button>
            <div class="actions">
              <button class="btn" id="report-save-draft" type="button" hidden><i data-lucide="save"></i><span>บันทึกแบบร่าง</span></button>
              <button class="btn primary" id="report-next-step" type="button"><span>ตรวจสอบรูปเล่ม</span><i data-lucide="arrow-right"></i></button>
            </div>
          </div>
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

  <dialog id="banner-dialog" class="banner-dialog" aria-labelledby="banner-title">
    <header class="modal-head">
      <div class="modal-icon"><i data-lucide="layout-template"></i></div>
      <div><span class="tool-kicker">BANNER STUDIO</span><h2 id="banner-title">ออกแบบรูปปกข่าว</h2></div>
      <button class="btn icon-button" id="banner-close-button" type="button" aria-label="ปิด" title="ปิด"><i data-lucide="x"></i></button>
    </header>
    <div class="banner-studio">
      <aside class="banner-controls">
        <label>เทมเพลต
          <select id="banner-template">
            <option value="matchday">Match Day</option>
            <option value="result">Match Result</option>
            <option value="spotlight">Sport Spotlight</option>
          </select>
        </label>
        <label>รูปการแข่งขัน <input id="banner-image-file" type="file" accept="image/jpeg,image/png,image/webp"></label>
        <div class="form-row compact-row">
          <label>สีกิจกรรม <input id="banner-accent" type="color" value="#f4c400"></label>
          <label>การจัดข้อความ
            <select id="banner-align"><option value="left">ชิดซ้าย</option><option value="center">กึ่งกลาง</option><option value="right">ชิดขวา</option></select>
          </label>
        </div>
        <label>กีฬา <input id="banner-sport" maxlength="80" placeholder="ชื่อกีฬา"></label>
        <label>หัวเรื่อง <textarea id="banner-headline" maxlength="180"></textarea></label>
        <label>รายการ / รายละเอียด <textarea id="banner-subtitle" maxlength="220"></textarea></label>
        <div class="banner-control-group">
          <strong>จัดวางรูปภาพ</strong>
          <label>ขนาดภาพ <input id="banner-image-scale" type="range" min="100" max="220" value="100"></label>
          <div class="form-row compact-row">
            <label>แนวนอน <input id="banner-image-x" type="range" min="0" max="100" value="50"></label>
            <label>แนวตั้ง <input id="banner-image-y" type="range" min="0" max="100" value="50"></label>
          </div>
        </div>
        <div class="banner-control-group">
          <strong>จัดวางข้อความ</strong>
          <label>องค์ประกอบ
            <select id="banner-active-element"><option value="sport">ชื่อกีฬา</option><option value="headline">หัวเรื่อง</option><option value="subtitle">รายละเอียด</option></select>
          </label>
          <div class="form-row compact-row">
            <label>แนวนอน <input id="banner-element-x" type="range" min="3" max="97" value="8"></label>
            <label>แนวตั้ง <input id="banner-element-y" type="range" min="5" max="95" value="34"></label>
          </div>
        </div>
      </aside>
      <section class="banner-workspace" aria-label="พื้นที่ออกแบบแบนเนอร์">
        <div class="canvas-shell"><canvas id="banner-canvas" width="1200" height="675" tabindex="0" aria-label="ตัวอย่างแบนเนอร์ สามารถลากข้อความเพื่อจัดตำแหน่งได้"></canvas></div>
        <p class="canvas-hint">ลากข้อความบนภาพเพื่อจัดตำแหน่ง หรือใช้ตัวเลื่อนสำหรับปรับอย่างละเอียด</p>
        <div class="banner-actions">
          <button class="btn" id="banner-download-button" type="button"><i data-lucide="download"></i><span>ดาวน์โหลด PNG</span></button>
          <button class="btn primary" id="banner-use-button" type="button"><i data-lucide="image-check"></i><span>ใช้เป็นรูปปกข่าว</span></button>
        </div>
      </section>
    </div>
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
  <?php $html2pdfAsset = is_file(PUBLIC_PATH . '/assets/html2pdf.bundle.min.js') ? 'public/assets/html2pdf.bundle.min.js' : 'node_modules/html2pdf.js/dist/html2pdf.bundle.min.js'; ?>
  <script src="<?= htmlspecialchars(versioned_asset_url($html2pdfAsset), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(versioned_asset_url('public/assets/app.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
