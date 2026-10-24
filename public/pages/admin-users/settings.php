<section id="admin-panel-settings" role="tabpanel" aria-labelledby="admin-tab-settings" hidden>
  <div class="admin-settings-layout">
    <form id="admin-invite-settings-form" class="admin-settings-form">
      <h2>ตั้งค่าการรับทีมงาน</h2>
      <label class="admin-settings-switch"><input id="staff-invites-enabled" name="enabled" type="checkbox"><span><strong>เปิดใช้คำเชิญทีมงาน</strong><small>อนุญาตให้สร้างคำเชิญและให้ผู้รับเชิญตั้งค่าบัญชี</small></span></label>
      <p class="muted">เมื่อปิด จะสร้างคำเชิญใหม่และใช้คำเชิญที่รอตอบรับไม่ได้ คำเชิญเดิมยังนับเวลาหมดอายุต่อไป ส่วนบัญชีที่ตอบรับแล้วใช้งานได้ตามปกติ</p>
      <label>อายุคำเชิญใหม่ (นาที)<input name="expiry_minutes" id="staff-invite-expiry" type="number" min="5" max="1440" step="1" required aria-describedby="staff-invite-expiry-help"></label>
      <p class="muted" id="staff-invite-expiry-help">กำหนดได้ 5–1,440 นาที (สูงสุด 24 ชั่วโมง) การเปลี่ยนค่านี้ไม่เปลี่ยนวันหมดอายุของคำเชิญที่สร้างไว้แล้ว</p>
      <p class="form-error" role="alert" hidden></p>
      <div class="actions"><span class="admin-form-feedback" id="admin-settings-feedback" role="status"></span><button class="btn primary" type="submit"><i data-lucide="check"></i><span>บันทึกการตั้งค่า</span></button></div>
    </form>
    <aside class="admin-settings-help"><h3>แยกจากการเพิ่มสมาชิก</h3><p>การปิดคำเชิญไม่ระงับบัญชีเดิม และไม่ปิดการสร้างบัญชีโดยตรงโดย System Admin</p><p>การเปลี่ยนบทบาทหรือระงับบัญชี ให้ทำในแท็บสมาชิกและสิทธิ์</p></aside>
  </div>
  <details class="admin-audit" id="admin-audit-details">
    <summary>ประวัติการใช้งานหลังบ้าน</summary>
    <div class="data-section"><div class="section-head"><h3>Audit log</h3><input id="audit-search" type="search" placeholder="ค้นหาอีเมล การดำเนินการ หรือข้อมูล" aria-label="ค้นหา Audit log"></div><p class="muted" id="admin-audit-state" role="status"></p><div class="table-wrap" id="audit-full-table"></div></div>
  </details>
</section>
