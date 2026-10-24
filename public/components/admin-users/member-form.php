<form id="user-form" class="card form-card">
  <h2>สมาชิก</h2>
  <input type="hidden" name="id">
  <section class="admin-form-group"><h3>ข้อมูลบัญชี</h3><div class="form-row"><label>ชื่อ<input name="name" required autocomplete="name"></label><label>อีเมล<input type="email" name="email" required autocomplete="email"></label></div></section>
  <section class="admin-form-group"><h3>บทบาทและขอบเขต</h3><label>บทบาท<select name="role" id="user-role-select" required></select></label>
    <div class="admin-scope-fields" id="admin-user-scope-fields">
    <label>สังกัดที่รับผิดชอบ<select name="department_code" class="department-select optional"><option value="">ไม่ระบุ</option></select></label>
    <fieldset class="admin-sport-scope"><legend>กีฬาที่รับผิดชอบ</legend><div id="admin-user-sport-options" class="admin-sport-options"></div></fieldset>
  </div>
  <input type="hidden" name="sport_ids">
  <p class="admin-role-help" id="admin-user-role-help"></p>
  </section>
  <details class="admin-password-fields" id="admin-user-password-fields"><summary>รหัสผ่านสำหรับบัญชีที่สร้างโดยตรง</summary><p class="muted" id="admin-user-password-help"></p><div class="form-row"><label>รหัสผ่านใหม่<input type="password" name="password" autocomplete="new-password" minlength="8"></label><label>ยืนยันรหัสผ่านใหม่<input type="password" name="password_confirm" autocomplete="new-password" minlength="8"></label></div></details>
  <label class="checkbox-line admin-active-field"><input type="checkbox" name="is_active" checked> เปิดใช้งานบัญชี</label>
  <div class="actions"><span class="admin-form-feedback" id="admin-member-feedback" role="status"></span><button class="btn primary" type="submit">บันทึกสมาชิก</button></div>
</form>
