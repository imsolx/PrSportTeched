// Reusable staging/review for database drafts, templates and AI. Nothing overwrites the editor implicitly.
function stageNewsDraft(draft, source, ai = false) {
  newsUi.generation++;
  newsUi.pendingDraft = { ...draft, ai };
  $('#news-draft-title').value = draft.title || '';
  $('#news-draft-excerpt').value = draft.excerpt || '';
  $('#news-draft-body').value = draft.body || '';
  $('#news-draft-source').textContent = source;
  $('#news-draft-verify-row').hidden = !ai;
  $('#news-draft-verified').checked = false;
  $('#news-draft-review').hidden = false;
  markNewsDirty();
  $('#news-draft-review').scrollIntoView({ behavior: 'instant', block: 'start' });
  $('#news-draft-title').focus({ preventScroll: true });
}

async function applyNewsDraft() {
  const draft = newsUi.pendingDraft;
  if (!draft) return;
  if (draft.ai && !$('#news-draft-verified').checked) throw new Error('กรุณาตรวจสอบข้อเท็จจริงและทำเครื่องหมายยืนยันก่อนนำร่าง AI มาใช้');
  if (draft.match_id) {
    if ($('#news-match-link').value !== String(draft.match_id)) throw new Error('แหล่งข้อมูลเปลี่ยนแล้ว กรุณาสร้างร่างจากแมตช์ที่เลือกใหม่');
    const fresh = await refreshNewsMatch(draft.match_id);
    if (newsMatchFingerprint(fresh) !== draft.sourceFingerprint) throw new Error('ข้อมูลการแข่งขันเปลี่ยนแล้ว กรุณาสร้างร่างใหม่เพื่อใช้ข้อมูลล่าสุด');
    if (draft.ai) validateNewsAiFacts({ title: $('#news-draft-title').value, excerpt: $('#news-draft-excerpt').value, body: $('#news-draft-body').value }, fresh);
  }
  const form = $('#news-form');
  if (['title', 'excerpt', 'body'].some(key => form.elements[key].value.trim())) {
    if (!await confirmAction('นำร่างมาแทนข้อความ?', 'หัวข้อ คำโปรย และเนื้อหาปัจจุบันจะถูกแทนที่ ส่วนข้อมูลการแข่งขัน รูปปก และหมวดหมู่เดิมยังอยู่', 'นำร่างมาใช้')) return;
  }
  form.elements.title.value = $('#news-draft-title').value;
  form.elements.excerpt.value = $('#news-draft-excerpt').value;
  form.elements.body.value = $('#news-draft-body').value;
  if (draft.news_type) form.elements.news_type.value = draft.news_type;
  newsUi.appliedDraft = draft.match_id ? { match_id: draft.match_id, sourceFingerprint: draft.sourceFingerprint } : null;
  syncBannerFromNews({ sport: draft.sport_name || '', headline: draft.banner_title || form.elements.title.value, subtitle: draft.banner_subtitle || form.elements.excerpt.value });
  bannerStudio.newsSyncSnapshot = newsBannerSourceSnapshot();
  newsUi.pendingDraft = null;
  $('#news-draft-review').hidden = true;
  syncNewsCategory();
  markNewsDirty();
  form.elements.title.focus();
  toast('นำร่างมาใช้แล้ว แก้ไขข้อความต่อได้ก่อนบันทึก');
}

function bindNewsDraftReview() {
  bindTaskButton('#news-draft-apply', applyNewsDraft);
  $('#news-draft-dismiss').addEventListener('click', () => {
    newsUi.pendingDraft = null;
    $('#news-draft-review').hidden = true;
    markNewsDirty();
  });
  $('#news-template-select').addEventListener('change', event => {
    const templates = {
      announcement: ['ประกาศประชาสัมพันธ์', 'แจ้งรายละเอียดกิจกรรมและช่องทางติดตามการแข่งขัน', 'รายละเอียดกิจกรรม:\nวันและเวลา:\nสถานที่:\nข้อมูลเพิ่มเติม:'],
      preview: ['เตรียมพบกับการแข่งขัน', 'เชิญติดตามรายการแข่งขันที่กำลังจะเริ่ม', 'รายการแข่งขัน:\nคู่แข่งขัน:\nวันและเวลา:\nสถานที่:\nช่องทางติดตามผล:'],
      result: ['สรุปผลการแข่งขัน', 'ผลการแข่งขันล่าสุดและผลงานของผู้เข้าร่วม', 'รายการแข่งขัน:\nคู่แข่งขัน:\nผลการแข่งขัน:\nผู้ทำผลงานโดดเด่น:\nการแข่งขันนัดถัดไป:'],
      honor: ['ขอแสดงความยินดีกับผู้ได้รับรางวัล', 'เชิดชูผลงานและความสำเร็จในการแข่งขัน', 'ผู้ได้รับรางวัล:\nประเภทรางวัล:\nรายการแข่งขัน:\nผลงานและความประทับใจ:'],
    };
    const chosen = templates[event.target.value];
    if (!chosen) return;
    stageNewsDraft({ title: chosen[0], excerpt: chosen[1], body: chosen[2], ...(event.target.value === 'honor' ? { news_type: 'hall_of_fame' } : {}) }, 'เทมเพลตข้อความ — เติมข้อมูลที่ตรวจสอบแล้วก่อนนำมาใช้');
  });
}
