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
