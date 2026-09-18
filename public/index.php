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
  <link rel="stylesheet" href="<?= htmlspecialchars(app_url('public/assets/app.css'), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(app_url('public/assets/workspace.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
  <header class="topbar">
    <button class="btn icon-button" id="menu-toggle" type="button" aria-label="เปิดเมนู" aria-expanded="false" aria-controls="main-navigation"><i data-lucide="menu"></i></button>
    <button class="brand" type="button" data-view-button="public">
      <img src="<?= htmlspecialchars(app_url('public/assets/logo.png'), ENT_QUOTES, 'UTF-8') ?>" alt="RMUTT Education">
      <span>
        <strong>TECHED SPORT</strong>
        <small>ครุศาสตร์อุตสาหกรรม มทร.ธัญบุรี</small>
      </span>
    </button>
    <nav class="tabs" id="main-navigation" aria-label="เมนูหลัก">
      <button class="tab active" data-view-button="public">หน้าแรก</button>
      <button class="tab" data-view-button="public-sports">กีฬา</button>
      <button class="tab" data-view-button="public-athletes">นักกีฬา</button>
      <button class="tab" data-view-button="public-schedule">ตารางแข่งขัน</button>
      <button class="tab" data-view-button="public-results">ผลการแข่งขัน</button>
      <button class="tab" data-view-button="public-news">ข่าวสาร</button>
      <button class="tab admin-link" data-view-button="dashboard" hidden>แดชบอร์ด</button>
      <button class="tab admin-link" data-view-button="sports" hidden>ข้อมูลหลัก</button>
      <button class="tab admin-link" data-view-button="students" hidden>นักศึกษา</button>
      <button class="tab admin-link" data-view-button="teams" hidden>ทีม</button>
      <button class="tab admin-link" data-view-button="matches" hidden>การแข่งขัน</button>
      <button class="tab admin-link" data-view-button="news" hidden>ข่าวสาร</button>
      <button class="tab admin-link" data-view-button="tasks" hidden>แผนงาน</button>
      <button class="tab admin-link" data-view-button="users" hidden>ผู้ดูแลระบบ</button>
      <button class="tab admin-link" data-view-button="reports" hidden>รายงาน</button>
    </nav>
    <div class="session-box">
      <span id="current-user-label">ผู้เยี่ยมชม</span>
      <button class="btn small" id="login-toggle" type="button">เข้าสู่ระบบ</button>
      <button class="btn small ghost" id="logout-button" type="button" hidden>ออกจากระบบ</button>
    </div>
  </header>
  <button id="navigation-backdrop" aria-label="ปิดเมนู" hidden></button>
  <div id="page-progress" hidden role="progressbar" aria-label="กำลังโหลดข้อมูล"></div>

  <main>
    <section class="view public-hero" id="view-public">
      <div class="hero-copy">
        <p class="eyebrow">คณะครุศาสตร์อุตสาหกรรม มทร.ธัญบุรี</p>
        <h1>TECHED SPORT</h1>
        <p>ทุกการแข่งขัน ทุกความสำเร็จ<br>กีฬาคณะครุศาสตร์อุตสาหกรรม</p>
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
        <select id="home-tournament-filter"></select>
      </div>
      <div class="split">
        <div>
          <h3>กีฬาเปิดใช้งาน</h3>
          <div class="chip-list" id="public-sports"></div>
        </div>
        <div>
          <h3>ตารางใกล้ถึง</h3>
          <div class="match-list" id="public-schedule"></div>
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

    <section class="view content-band" id="view-public-sports" hidden>
      <div class="section-head"><h2>กีฬา</h2></div>
      <div class="table-wrap" id="public-sports-table"></div>
    </section>

    <section class="view content-band" id="view-public-athletes" hidden>
      <div class="section-head">
        <h2>แผนผังนักกีฬา</h2>
        <select id="athlete-sport-filter"></select>
      </div>
      <input class="search-input" id="athlete-team-filter" placeholder="ค้นหาชื่อทีม / สาขา">
      <div class="athlete-board-list" id="athlete-board-list"></div>
    </section>

    <section class="view content-band" id="view-public-schedule" hidden>
      <div class="section-head">
        <h2>ตารางแข่งขัน</h2>
        <select id="schedule-tournament-filter"></select>
      </div>
      <div class="match-list" id="public-schedule-page"></div>
    </section>

    <section class="view content-band" id="view-public-results" hidden>
      <div class="section-head">
        <h2>ผลการแข่งขัน</h2>
        <select id="results-tournament-filter"></select>
      </div>
      <div class="match-list" id="public-results-page"></div>
      <section class="public-standings"><h3>ตารางคะแนน</h3><div class="table-wrap" id="public-standings-table"></div></section>
    </section>

    <section class="view content-band" id="view-public-news" hidden>
      <div class="section-head"><h2>ข่าวสาร</h2></div>
      <div class="news-grid" id="public-news-page"></div>
    </section>

    <section class="view app-panel" id="view-dashboard" hidden>
      <div class="section-head">
        <h2>แดชบอร์ด</h2>
        <button class="btn small" id="dashboard-ai-button" type="button">สรุปงาน</button>
      </div>
      <div class="metric-grid">
        <div class="metric"><span>กีฬาเปิดใช้</span><strong id="d-active-sports">0</strong></div>
        <div class="metric"><span>ทีมอนุมัติ</span><strong id="d-approved-teams">0</strong></div>
        <div class="metric"><span>นักศึกษา</span><strong id="d-students">0</strong></div>
        <div class="metric"><span>แมตช์จบแล้ว</span><strong id="d-completed">0</strong></div>
        <div class="metric"><span>งานค้าง</span><strong id="d-open-tasks">0</strong></div>
      </div>
      <div class="dashboard-shortcuts" id="dashboard-shortcuts"></div>
      <section class="dashboard-summary"><h3>สรุปงาน</h3><div class="ai-output" id="dashboard-ai-output">ยังไม่มีสรุปงาน</div></section>
      <section class="data-section"><h3>กิจกรรมล่าสุด</h3><div class="table-wrap" id="audit-table"></div></section>
    </section>

    <section class="view app-panel" id="view-sports" hidden>
      <div class="grid">
        <form class="card form-card" id="sport-form">
          <h2>กีฬา</h2>
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
          <label>ไอคอน <input name="icon" placeholder="football"></label>
          <div class="form-row">
            <label>โลโก้กีฬา <input type="file" id="sport-logo-file" accept="image/jpeg,image/png,image/webp"></label>
            <label>ลิงก์โลโก้ <input name="logo_url"></label>
          </div>
          <label>กติกาย่อ <textarea name="rules"></textarea></label>
          <div class="actions"><button class="btn primary" type="submit">บันทึก</button><button class="btn ghost reset-form" type="button">ล้างฟอร์ม</button></div>
        </form>
        <div class="card wide"><h2>รายการกีฬา</h2><div class="table-wrap" id="sports-table"></div></div>
        <form class="card form-card" id="sport-rule-form">
          <h2>กติกากีฬา</h2>
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
          <label>ประเภทเหตุการณ์ <textarea name="event_types_text" placeholder="score|ทำคะแนน|1&#10;foul|ฟาวล์|0&#10;yellow_card|ใบเหลือง|0&#10;red_card|ใบแดง|0"></textarea></label>
          <label>กติกาเพิ่มเติม <textarea name="custom_rules"></textarea></label>
          <label class="checkbox-line"><input type="checkbox" name="is_active" checked> ใช้กติกาเวอร์ชันนี้</label>
          <div class="actions"><button class="btn primary" type="submit">บันทึกกติกา</button><button class="btn ghost reset-form" type="button">ล้างฟอร์ม</button></div>
        </form>
        <div class="card wide"><h2>เวอร์ชันกติกา</h2><div class="table-wrap" id="sport-rules-table"></div></div>
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
          <h2>นักศึกษา</h2>
          <input type="hidden" name="id">
          <label>รหัสนักศึกษา <input name="student_code" required></label>
          <div class="form-row">
            <label>คำนำหน้า <input name="prefix"></label>
            <label>ชื่อ <input name="first_name" required></label>
            <label>นามสกุล <input name="last_name" required></label>
          </div>
          <div class="form-row">
            <label>สาขา <select name="department_code" class="department-select" required></select></label>
            <label>ปีเข้า <input name="entry_year"></label>
            <label>เพศ <select name="gender"><option value="open">ไม่ระบุ</option><option value="male">ชาย</option><option value="female">หญิง</option></select></label>
          </div>
          <label>โทรศัพท์ <input name="phone"></label>
          <div class="actions"><button class="btn primary" type="submit">บันทึก</button><button class="btn ghost reset-form" type="button">ล้างฟอร์ม</button></div>
        </form>
        <div class="card wide"><h2>นักศึกษาล่าสุด</h2><div class="table-wrap" id="students-table"></div></div>
        <div class="card full">
          <h2>นำเข้า CSV นักศึกษา</h2>
          <textarea class="csv-input" id="students-csv" placeholder="student_code,prefix,first_name,last_name,department_code,entry_year,gender,phone"></textarea>
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
          <label>สาขา <select name="department_code" class="department-select" required></select></label>
          <div class="form-row"><label>โลโก้ทีม <input type="file" id="team-logo-file" accept="image/jpeg,image/png,image/webp"></label><label>ลิงก์โลโก้ <input name="logo_url"></label></div>
          <label>รหัสนักศึกษากัปตัน <input name="captain_student_id"></label>
          <label>สถานะ <select name="status"><option value="submitted">submitted</option><option value="approved">approved</option><option value="rejected">rejected</option><option value="locked">locked</option></select></label>
          <div class="actions"><button class="btn primary" type="submit">บันทึก</button><button class="btn ghost reset-form" type="button">ล้างฟอร์ม</button></div>
        </form>
        <div class="card wide"><h2>ทีมทั้งหมด</h2><div class="table-wrap" id="teams-table"></div></div>
        <form class="card form-card" id="member-form">
          <h2>รายชื่อนักกีฬา</h2>
          <input type="hidden" name="id">
          <label>ทีม <select name="team_id" id="member-team-select" required></select></label>
          <label>รหัสนักศึกษา <input name="student_code" required></label>
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
      <div class="grid">
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
            <label>ปีการศึกษา <input name="academic_year" value="69"></label>
          </div>
          <div class="form-row">
            <label>รูปแบบ <select name="format"><option value="knockout">knockout</option><option value="round_robin">round_robin</option><option value="group">group</option><option value="group_knockout">group_knockout</option></select></label>
            <label>สถานะ <select name="status"><option value="draft">draft</option><option value="registration">registration</option><option value="closed">closed</option><option value="in_progress">in_progress</option><option value="completed">completed</option><option value="cancelled">cancelled</option></select></label>
          </div>
          <div class="actions"><button class="btn primary" type="submit">บันทึกรายการ</button><button class="btn ghost reset-form" type="button">ล้างฟอร์ม</button></div>
        </form>
        <div class="card wide"><h2>รายการแข่งทั้งหมด</h2><div class="table-wrap" id="tournaments-table"></div></div>
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
          <div class="form-row">
            <label>ผล/คะแนนทีม 1 <input type="number" name="team1_score" required></label>
            <label>ผล/คะแนนทีม 2 <input type="number" name="team2_score" required></label>
          </div>
          <label>ผู้ชนะจาก Tie-break <select name="winner_team_id" id="result-winner-select"><option value="">ไม่ระบุ / เสมอ</option></select></label>
          <button class="btn primary" type="submit">บันทึกผล</button>
        </form>
        <div class="card full"><h2>แมตช์ทั้งหมด</h2><div class="table-wrap" id="matches-table"></div></div>
        <section class="match-center full" id="match-center-panel">
          <div class="data-heading"><div><p class="section-kicker">LIVE MATCH CENTER</p><h2>ควบคุมการแข่งขันและบันทึกเหตุการณ์</h2></div><select id="match-center-select" aria-label="เลือกแมตช์"></select></div>
          <div id="match-center-empty" class="empty-state">เลือกแมตช์เพื่อเริ่มบันทึกเวลาและเหตุการณ์</div>
          <div id="match-center-content" hidden>
            <div class="scoreboard"><div id="center-team1">ทีม 1</div><strong id="center-score">0 - 0</strong><div id="center-team2">ทีม 2</div></div>
            <div class="clock-bar"><strong id="center-clock">00:00</strong><span id="center-period">ช่วงที่ 1</span><div class="actions"><button class="btn primary" id="clock-start" type="button"><i data-lucide="play"></i><span>เริ่ม</span></button><button class="btn" id="clock-pause" type="button"><i data-lucide="pause"></i><span>พัก</span></button><button class="btn" id="clock-next" type="button">ช่วงถัดไป</button><button class="btn" id="clock-overtime" type="button">ต่อเวลา</button></div></div>
            <form id="match-event-form" class="event-entry">
              <input type="hidden" name="match_id">
              <label>เหตุการณ์ <select name="event_type" id="event-type" required></select></label>
              <label>ทีม <select name="team_id" id="event-team"></select></label>
              <label>นักกีฬา <select name="student_id" id="event-student"></select></label>
              <label>แต้ม <input type="number" name="points" id="event-points"></label>
              <label>ทดเวลา (นาที) <input type="number" name="added_time" min="0" value="0"></label>
              <label class="event-note">หมายเหตุ <input name="note"></label>
              <button class="btn primary" type="submit"><i data-lucide="plus"></i><span>บันทึกเหตุการณ์</span></button>
            </form>
            <div class="table-wrap" id="match-events-table"></div>
          </div>
        </section>
        <section class="data-section full"><div class="data-heading"><h2>ตารางคะแนน</h2><select id="standings-tournament-filter" aria-label="เลือกรายการแข่งขัน"></select></div><div class="table-wrap" id="standings-table"></div></section>
      </div>
    </section>

    <section class="view app-panel" id="view-news" hidden>
      <div class="grid">
        <form class="card form-card wide" id="news-form">
          <h2>ข่าวประชาสัมพันธ์</h2>
          <input type="hidden" name="id">
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
          <div class="form-row">
            <label>รูปปก <input type="file" id="news-file" accept="image/*"></label>
            <label>ลิงก์รูป <input name="cover_image_url" id="news-cover-url"></label>
          </div>
          <div class="cover-tools">
            <div class="cover-thumb" id="news-cover-preview"><i data-lucide="image"></i><span>ยังไม่มีรูปปก</span></div>
            <button class="btn" id="banner-open-button" type="button"><i data-lucide="layout-template"></i><span>สร้างแบนเนอร์</span></button>
          </div>
          <div class="form-row">
            <label>สถานะ <select name="status"><option value="published">published</option><option value="draft">draft</option><option value="archived">archived</option></select></label>
            <label class="checkbox-line"><input type="checkbox" name="is_pinned"> ปักหมุด</label>
          </div>
          <div class="actions"><button class="btn primary" type="submit">บันทึกข่าว</button><button class="btn ghost reset-form" type="button">ล้างฟอร์ม</button></div>
        </form>
        <div class="card full"><h2>ข่าวทั้งหมด</h2><div class="table-wrap" id="news-table"></div></div>
      </div>
    </section>

    <section class="view app-panel" id="view-users" hidden>
      <div class="grid">
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
          <label>รหัสผ่าน <input type="password" name="password" autocomplete="new-password"></label>
          <label>บทบาท <select name="role" id="user-role-select" required></select></label>
          <label>สาขา <select name="department_code" class="department-select optional"><option value="">ไม่จำกัด</option></select></label>
          <label>รหัสกีฬาที่รับผิดชอบ <input name="sport_ids" placeholder="1,2"></label>
          <label class="checkbox-line"><input type="checkbox" name="is_active" checked> เปิดใช้งาน</label>
          <div class="actions"><button class="btn primary" type="submit">บันทึกสมาชิก</button><button class="btn ghost reset-form" type="button">ล้างฟอร์ม</button></div>
        </form>
        <div class="card wide"><h2>สมาชิกและสิทธิ์</h2><div class="table-wrap" id="users-table"></div></div>
        <div class="card wide"><h2>คำเชิญที่สร้างไว้</h2><div class="table-wrap" id="invites-table"></div></div>
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

    <section class="view app-panel" id="view-tasks" hidden>
      <div class="grid">
        <form class="card form-card" id="task-form">
          <h2>แผนงาน</h2>
          <input type="hidden" name="id">
          <label>ชื่องาน <input name="title" required></label>
          <label>รายละเอียด <textarea name="description" class="tall"></textarea></label>
          <div class="form-row">
            <label>หมวดงาน <select name="category">
              <option value="general">ทั่วไป</option>
              <option value="registration">รับสมัคร</option>
              <option value="competition">การแข่งขัน</option>
              <option value="content">ข่าวสาร</option>
              <option value="report">รายงาน</option>
              <option value="system">ระบบ</option>
            </select></label>
            <label>ความสำคัญ <select name="priority">
              <option value="normal">ปกติ</option>
              <option value="low">ต่ำ</option>
              <option value="high">สูง</option>
              <option value="urgent">เร่งด่วน</option>
            </select></label>
          </div>
          <div class="form-row">
            <label>สถานะ <select name="status">
              <option value="todo">ต้องทำ</option>
              <option value="doing">กำลังทำ</option>
              <option value="blocked">ติดปัญหา</option>
              <option value="done">เสร็จแล้ว</option>
            </select></label>
            <label>กำหนดเสร็จ <input type="datetime-local" name="due_at"></label>
          </div>
          <label>ผู้รับผิดชอบ <select name="assignee_email" id="task-assignee-select"><option value="">ไม่ระบุ</option></select></label>
          <div class="form-row">
            <label>เกี่ยวข้องกับ <select name="related_type">
              <option value="">ไม่ระบุ</option>
              <option value="sport">กีฬา</option>
              <option value="tournament">รายการแข่งขัน</option>
              <option value="team">ทีม</option>
              <option value="match">แมตช์</option>
              <option value="news">ข่าว</option>
              <option value="report">รายงาน</option>
            </select></label>
            <label>รหัสอ้างอิง <input name="related_id"></label>
          </div>
          <div class="actions"><button class="btn primary" type="submit">บันทึกงาน</button><button class="btn ghost reset-form" type="button">ล้างฟอร์ม</button></div>
        </form>
        <div class="card full">
          <h2>งานที่ต้องจัดการ</h2>
          <div class="task-board" id="task-board"></div>
          <div class="table-wrap" id="tasks-table"></div>
        </div>
      </div>
    </section>

    <section class="view app-panel" id="view-reports" hidden>
      <div class="report-dashboard" id="report-dashboard">
        <div class="section-head report-page-head">
          <div><p class="section-kicker">ระบบเอกสารระดับมหาวิทยาลัย</p><h2>รายงานผลกีฬา</h2></div>
          <div class="actions">
            <span class="badge" id="ai-provider-status">กำลังตรวจสอบ AI</span>
            <button class="btn primary" id="report-create-button" type="button"><i data-lucide="plus"></i><span>สร้างรายงานใหม่</span></button>
          </div>
        </div>
        <div class="report-summary-strip" id="report-summary-strip"></div>
        <div class="report-filters" aria-label="ตัวกรองรายงาน">
          <label>ค้นหา<input id="report-search" type="search" placeholder="ชื่อรายงาน ผู้จัดทำ หรือหน่วยงาน"></label>
          <label>ปีการศึกษา<select id="report-year-filter"><option value="">ทุกปี</option></select></label>
          <label>หมวดหมู่<select id="report-category-filter"><option value="">ทุกหมวดหมู่</option></select></label>
          <label>ประเภทแบบฟอร์ม<select id="report-template-filter"><option value="">ทุกแบบฟอร์ม</option></select></label>
          <label>สถานะ<select id="report-status-filter"><option value="">ทุกสถานะ</option><option value="draft">แบบร่าง</option><option value="review">รอตรวจสอบ</option><option value="completed">เสร็จสมบูรณ์</option><option value="published">เผยแพร่แล้ว</option></select></label>
        </div>
        <div class="table-wrap data-table" id="reports-table"></div>
      </div>

      <div class="report-builder" id="report-builder" hidden>
        <div class="section-head report-page-head">
          <div><p class="section-kicker">จัดทำรายงานอย่างเป็นขั้นตอน</p><h2 id="report-builder-title">สร้างรายงานใหม่</h2></div>
          <button class="btn ghost" id="report-back-button" type="button"><i data-lucide="arrow-left"></i><span>กลับหน้ารายงาน</span></button>
        </div>
        <ol class="report-stepper" id="report-stepper" aria-label="ขั้นตอนการสร้างรายงาน">
          <li class="active" data-report-step-indicator="1"><span>1</span>เลือกหมวดหมู่</li>
          <li data-report-step-indicator="2"><span>2</span>เลือกแบบฟอร์ม</li>
          <li data-report-step-indicator="3"><span>3</span>กรอกข้อมูล</li>
          <li data-report-step-indicator="4"><span>4</span>ตัวอย่างรายงาน</li>
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
            <div class="report-form-section" data-report-section="general">
              <h3><span>1</span>ข้อมูลพื้นฐานของรายงาน</h3>
              <div class="report-form-grid">
                <label class="span-2">ชื่อรายงาน <span class="required">*</span><input name="title" maxlength="255" required></label>
                <label>ชื่อโครงการ/กิจกรรม<input name="project_name"></label>
                <label>วันที่ดำเนินการ<input name="operation_date" type="date"></label>
                <label>สถานที่<input name="location"></label>
                <label>ปีการศึกษา <span class="required">*</span><input name="academic_year" required></label>
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
                <label class="span-2">ผลต่อผู้เข้าร่วม/นักศึกษา<textarea name="participant_impact"></textarea></label>
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
            <div class="report-step-heading"><h3>ตัวอย่างรายงาน</h3><p>ตรวจสอบข้อมูลก่อนเปลี่ยนสถานะหรือ Export เอกสาร</p></div>
            <div class="report-preview-shell" id="stored-report-preview"></div>
          </section>

          <div class="report-wizard-actions">
            <button class="btn ghost" id="report-prev-step" type="button" hidden><i data-lucide="arrow-left"></i><span>ย้อนกลับ</span></button>
            <div class="actions">
              <button class="btn" id="report-save-draft" type="button" hidden><i data-lucide="save"></i><span>บันทึกแบบร่าง</span></button>
              <button class="btn primary" id="report-next-step" type="button"><span>ถัดไป</span><i data-lucide="arrow-right"></i></button>
            </div>
          </div>
        </form>
        <details class="report-ai-assist" id="report-ai-assist">
          <summary><i data-lucide="sparkles"></i><span>ผู้ช่วยเรียบเรียงรายงานด้วย AI</span></summary>
          <div class="actions report-ai-tools" id="report-ai-tools"></div>
          <form class="report-ai-form" id="report-form">
            <input type="hidden" name="report_type" value="executive">
            <input type="hidden" name="output_type" value="both">
            <input type="hidden" name="tournament_id">
            <input type="hidden" name="academic_year">
            <label>คำสั่งเพิ่มเติม<textarea name="instruction" placeholder="ระบุประเด็นที่ต้องการให้ AI ช่วยเขียนรายงาน เช่น เน้นประชาสัมพันธ์ผลการแข่งขัน บรรยากาศกิจกรรม หรือข้อเสนอแนะเชิงพัฒนา"></textarea></label>
            <button class="btn" type="submit"><i data-lucide="sparkles"></i><span>ให้ AI ช่วยกรอกรายงาน</span></button>
          </form>
          <div class="report-output" id="report-output">AI พร้อมช่วยร่างข้อความลงในแบบฟอร์มตามหมวดหมู่รายงานประชาสัมพันธ์กีฬา กรุณาตรวจทานความถูกต้องก่อนบันทึกหรือเผยแพร่</div>
        </details>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <div>
      <strong>TECHED SPORT</strong>
      <span>Faculty of Technical Education, RMUTT</span>
    </div>
    <nav aria-label="เมนูลัดท้ายเว็บ">
      <button type="button" data-view-button="public-schedule">ตารางแข่งขัน</button>
      <button type="button" data-view-button="public-results">ผลการแข่งขัน</button>
      <button type="button" data-view-button="public-news">ข่าวสาร</button>
    </nav>
  </footer>

  <dialog id="login-dialog" aria-labelledby="login-title">
    <form method="dialog" class="dialog-card" id="login-form">
      <div class="login-mark"><i data-lucide="shield-check"></i></div>
      <p class="eyebrow">TECHED SPORT</p>
      <h2 id="login-title">เข้าสู่ระบบผู้ดูแล</h2>
      <label>อีเมล <input name="email" type="email" autocomplete="username" placeholder="name@example.com" required></label>
      <label>รหัสผ่าน <input name="password" type="password" autocomplete="current-password" required></label>
      <div class="actions"><button class="btn" value="cancel" type="button" id="login-cancel">ยกเลิก</button><button class="btn primary" type="submit">เข้าสู่ระบบ</button></div>
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
  <script src="<?= htmlspecialchars(app_url('public/assets/lucide.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(app_url('public/assets/workspace.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(app_url('public/assets/banner-studio.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="<?= htmlspecialchars(app_url('public/assets/app.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
