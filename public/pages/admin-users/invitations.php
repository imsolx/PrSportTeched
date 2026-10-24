<section id="admin-panel-invitations" role="tabpanel" aria-labelledby="admin-tab-invitations" hidden>
  <div class="admin-invite-notice" id="admin-invite-notice" role="status"></div>
  <section class="data-section admin-invitations-section">
    <div class="data-heading"><div><h2>คำเชิญทีมงาน</h2><p class="muted">ผู้รับเชิญตั้งรหัสผ่านเอง ลิงก์ใช้ได้ครั้งเดียว</p></div></div>
    <div class="invite-link-box admin-created-link" id="invite-link-box" hidden></div>
    <div class="admin-users-filters">
      <label class="admin-search">ค้นหาคำเชิญ<input id="admin-invite-search" type="search" placeholder="ค้นหาชื่อหรืออีเมล" autocomplete="off"></label>
      <label>สถานะคำเชิญ<select id="admin-invite-status-filter"><option value="">ทุกสถานะ</option><option value="pending">รอตอบรับ</option><option value="accepted">ใช้งานแล้ว</option><option value="expired">หมดอายุ</option><option value="revoked">ถูกเพิกถอน</option></select></label>
    </div>
    <div class="table-wrap" id="invites-table"></div>
    <p class="muted admin-table-note">แสดงคำเชิญล่าสุดไม่เกิน 100 รายการ</p>
  </section>
</section>
