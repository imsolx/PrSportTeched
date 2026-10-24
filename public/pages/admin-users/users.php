<section class="view app-panel admin-users-page" id="view-users" hidden>
  <header class="section-head admin-users-heading"><div><h2>ผู้ดูแลระบบ</h2><p class="muted">จัดการสมาชิก บทบาท และคำเชิญทีมงาน</p></div><button class="btn" id="admin-users-refresh" type="button"><i data-lucide="refresh-cw"></i><span>โหลดข้อมูลใหม่</span></button></header>
  <nav class="admin-users-tabs" role="tablist" aria-label="จัดการผู้ดูแลระบบ">
    <button type="button" role="tab" id="admin-tab-members" aria-controls="admin-panel-members" aria-selected="true" data-admin-tab="members">สมาชิกและสิทธิ์</button>
    <button type="button" role="tab" id="admin-tab-invitations" aria-controls="admin-panel-invitations" aria-selected="false" tabindex="-1" data-admin-tab="invitations">คำเชิญทีมงาน</button>
    <button type="button" role="tab" id="admin-tab-settings" aria-controls="admin-panel-settings" aria-selected="false" tabindex="-1" data-admin-tab="settings">ตั้งค่าการรับทีมงาน</button>
  </nav>
  <div class="admin-users-state" id="admin-users-loading" role="status" hidden><i data-lucide="loader-circle"></i><span>กำลังโหลดข้อมูลผู้ดูแลระบบ…</span></div>
  <div class="admin-users-error" id="admin-users-error" role="alert" hidden><p></p><button class="btn" id="admin-users-retry" type="button">ลองอีกครั้ง</button></div>
  <?php require __DIR__ . '/members.php'; ?>
  <?php require __DIR__ . '/invitations.php'; ?>
  <?php require __DIR__ . '/settings.php'; ?>
  <?php require dirname(__DIR__, 2) . '/components/admin-users/member-form.php'; ?>
  <?php require dirname(__DIR__, 2) . '/components/admin-users/invitation-form.php'; ?>
</section>
<div id="admin-user-action-menu" class="admin-users-menu" popover="auto" role="menu" aria-label="จัดการสมาชิก">
  <button type="button" role="menuitem" data-admin-member-action="edit"><i data-lucide="pencil"></i>แก้ไขสมาชิก</button>
  <button type="button" role="menuitem" data-admin-member-action="status"></button>
  <button type="button" role="menuitem" class="danger" data-admin-member-action="delete"><i data-lucide="trash-2"></i>ลบบัญชี</button>
</div>
