<form id="invite-form" class="card form-card">
  <h2>คำเชิญทีมงาน</h2>
  <p class="muted">ผู้รับเชิญจะตั้งรหัสผ่านด้วยตนเองหลังเปิดลิงก์</p>
  <section class="admin-form-group"><h3>ผู้รับเชิญ</h3><div class="form-row"><label>ชื่อ (ถ้ามี)<input name="name" autocomplete="name"></label><label>อีเมล<input type="email" name="email" required autocomplete="email"></label></div></section>
  <section class="admin-form-group"><h3>บทบาทและขอบเขต</h3><label>บทบาท<select name="role" id="invite-role-select" required></select></label>
    <div class="admin-scope-fields" id="admin-invite-scope-fields">
    <label>สังกัดที่รับผิดชอบ<select name="department_code" class="department-select optional"><option value="">ไม่ระบุ</option></select></label>
    <fieldset class="admin-sport-scope"><legend>กีฬาที่รับผิดชอบ</legend><div id="admin-invite-sport-options" class="admin-sport-options"></div></fieldset>
  </div>
  <input type="hidden" name="sport_ids">
  <p class="admin-role-help" id="admin-invite-role-help"></p>
  </section>
  <p class="muted" id="admin-invite-lifetime"></p>
  <div class="actions"><span class="admin-form-feedback" id="admin-invite-feedback" role="status"></span><button class="btn primary" type="submit">สร้างลิงก์เชิญ</button></div>
</form>
