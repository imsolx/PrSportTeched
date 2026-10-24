<details class="news-editor-section news-disclosure" id="news-ai-panel">
  <summary><i data-lucide="sparkles"></i><span>AI Newsroom <small>(ถ้ามี)</small></span><i data-lucide="chevron-down"></i></summary>
  <div class="news-disclosure-body">
    <p class="news-field-help" id="news-ai-source">ใช้แมตช์เดียวกับส่วนเชื่อมข้อมูลการแข่งขัน</p>
    <div class="form-row">
      <label>รูปแบบข่าว<select id="news-ai-story-type"><option value="result">สรุปผลการแข่งขัน</option><option value="preview">ประชาสัมพันธ์ก่อนแข่ง</option><option value="highlight">ไฮไลต์และความประทับใจ</option></select></label>
      <label>โทนการเขียน<select id="news-ai-tone"><option value="official">ทางการ กระชับ</option><option value="energetic">สนุก เร้าใจ</option><option value="inspiring">สร้างแรงบันดาลใจ</option></select></label>
    </div>
    <label>ประเด็นสำคัญ<textarea id="news-ai-instruction" maxlength="800" rows="3" placeholder="เช่น ชื่นชมนักกีฬา หรือเชิญชวนติดตามรอบถัดไป"></textarea></label>
    <p class="news-field-help">AI ใช้ข้อมูลการแข่งขันจากระบบเป็นแหล่งอ้างอิง ร่างยังต้องตรวจทานข้อเท็จจริงก่อนนำมาใช้และเผยแพร่</p>
    <div class="news-ai-actions"><span id="news-ai-status" role="status">พร้อมสร้างร่าง</span><button class="btn primary" id="news-ai-button" type="button"><i data-lucide="sparkles"></i><span>สร้างร่างด้วย AI</span></button></div>
  </div>
</details>
