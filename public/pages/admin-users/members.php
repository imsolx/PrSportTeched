<section id="admin-panel-members" role="tabpanel" aria-labelledby="admin-tab-members">
  <section class="data-section admin-members-section">
    <div class="data-heading"><div><h2>สมาชิกและสิทธิ์</h2><p class="muted">สร้างบัญชีโดยตรง หรือเชิญให้ทีมงานตั้งรหัสผ่านด้วยตนเอง</p></div></div>
    <div class="admin-users-filters">
      <label class="admin-search">ค้นหาสมาชิก<input id="admin-member-search" type="search" placeholder="ค้นหาชื่อหรืออีเมล" autocomplete="off"></label>
      <label>บทบาท<select id="admin-member-role-filter"><option value="">ทุกบทบาท</option></select></label>
      <label>สถานะบัญชี<select id="admin-member-status-filter"><option value="">ทุกสถานะ</option><option value="active">เปิดใช้งาน</option><option value="inactive">ระงับบัญชี</option></select></label>
    </div>
    <div class="table-wrap" id="users-table"></div>
  </section>
  <p class="admin-scope-note"><i data-lucide="info"></i><span>ขอบเขตแสดงตามสิทธิ์ปัจจุบันของระบบ บทบาท Tournament Organizer ยังไม่รองรับการจำกัดเฉพาะทัวร์นาเมนต์</span></p>
</section>
