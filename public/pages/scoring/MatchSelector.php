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

