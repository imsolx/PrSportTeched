    <section class="view app-panel" id="view-students" hidden>
      <div class="grid">
        <form class="card form-card" id="student-form">
          <h2>นักกีฬา</h2>
          <input type="hidden" name="id">
          <label>รหัสนักกีฬา <input name="student_code" placeholder="เว้นว่างเพื่อสร้างรหัสอัตโนมัติ"></label>
          <div class="form-row">
            <label>คำนำหน้า <input name="prefix"></label>
            <label>ชื่อ <input name="first_name" required></label>
            <label>นามสกุล (ถ้ามี) <input name="last_name"></label>
          </div>
          <div class="form-row">
            <label>หน่วยงาน / สังกัด (ถ้ามี) <select name="department_code" class="department-select"></select></label>
            <label>รายละเอียดเพิ่มเติม <input name="entry_year"></label>
            <label>เพศ <select name="gender"><option value="open">ไม่ระบุ</option><option value="male">ชาย</option><option value="female">หญิง</option></select></label>
          </div>
          <input type="hidden" name="photo_url" id="student-photo-url"><input type="file" id="student-photo-file" hidden><div id="student-photo-preview" hidden></div>
          <label>โทรศัพท์ <input name="phone"></label>
          <div class="actions"><button class="btn primary" type="submit">บันทึก</button><button class="btn ghost reset-form" type="button">ล้างฟอร์ม</button></div>
        </form>
        <div class="card wide">
          <h2>จัดการนักกีฬา</h2>
          <div class="student-scope" id="student-scope">
            <div class="student-scope-heading">
              <div><span>ตัวกรองเพิ่มเติม</span><strong>เลือกสังกัดหรือกีฬาเมื่อต้องการกรองข้อมูล</strong></div>
              <p id="student-scope-status" aria-live="polite">แสดงนักกีฬาทั้งหมด</p>
            </div>
            <div class="student-scope-fields">
              <label><span><b>1</b> องค์กร (ถ้ามี)</span><select id="student-scope-faculty" aria-label="เลือกองค์กร"></select></label>
              <label><span><b>2</b> สังกัดย่อย (ถ้ามี)</span><select id="student-scope-department" aria-label="เลือกสังกัดย่อย" disabled></select></label>
              <label><span><b>3</b> กีฬา (ถ้าต้องการ)</span><select id="student-scope-sport" aria-label="เลือกกีฬา" disabled></select></label>
            </div>
          </div>
          <div class="table-wrap" id="students-table"></div>
        </div>
        <div class="card full">
          <h2>นำเข้า CSV นักกีฬา</h2>
          <textarea class="csv-input" id="students-csv" placeholder="student_code,first_name,last_name,department_code,gender,phone"></textarea>
          <div class="actions"><button class="btn" id="students-csv-check" type="button">ตรวจ CSV</button><button class="btn primary" id="students-csv-import" type="button">นำเข้า</button></div>
          <div class="ai-output compact" id="students-import-result"></div>
        </div>
      </div>
    </section>
