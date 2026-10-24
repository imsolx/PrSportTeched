// Reusable staging/review for database drafts, templates and AI. Nothing overwrites the editor implicitly.
function stageNewsDraft(draft, source, ai = false) {
  newsUi.generation++;
  newsUi.pendingDraft = { ...draft, ai };
  $('#news-draft-title').value = draft.title || '';
  $('#news-draft-excerpt').value = draft.excerpt || '';
  $('#news-draft-body').value = draft.body || '';
  $('#news-draft-display-title').textContent = draft.title || '';
  $('#news-draft-display-body').textContent = draft.body || '';
  $('#news-draft-edit').open = false;
  $('#news-draft-source').textContent = source;
  $('#news-draft-verify-row').hidden = !ai;
  $('#news-draft-verified').checked = false;
  $('#news-draft-verify-label').textContent = draft.general ? 'ตรวจสอบชื่อ วันเวลา และข้อเท็จจริงกับข้อมูลที่ระบุแล้ว' : 'ตรวจสอบชื่อ คะแนน และข้อเท็จจริงกับข้อมูลการแข่งขันแล้ว';
  $('.news-review-note', $('#news-draft-review')).textContent = draft.general ? 'ร่างนี้ยังไม่เปลี่ยนข้อความ ตรวจสอบชื่อ วันเวลา และข้อมูลที่คุณระบุก่อนนำไปใช้' : 'ร่างนี้ยังไม่เปลี่ยนข้อความในฟอร์ม ตรวจสอบชื่อทีม คะแนน และสถานะผลจากข้อมูลอ้างอิงก่อนนำมาใช้';
  $('#news-draft-review').hidden = false;
  openNewsAiPanel(false);
  markNewsDirty();
  $('#news-draft-review').scrollIntoView({ behavior: 'instant', block: 'start' });
  $('#news-draft-edit > summary').focus({ preventScroll: true });
}

async function applyNewsDraft(mode = 'all') {
  const draft = newsUi.pendingDraft;
  if (!draft) return;
  if (draft.ai && !$('#news-draft-verified').checked) throw new Error('กรุณาตรวจสอบข้อเท็จจริงและทำเครื่องหมายยืนยันก่อนนำร่าง AI มาใช้');
  if (draft.match_id) {
    if ($('#news-match-link').value !== String(draft.match_id)) throw new Error('แหล่งข้อมูลเปลี่ยนแล้ว กรุณาสร้างร่างจากแมตช์ที่เลือกใหม่');
    const fresh = await refreshNewsMatch(draft.match_id);
    if (newsUi.pendingDraft !== draft || $('#news-match-link').value !== String(draft.match_id)) throw new Error('แหล่งข้อมูลเปลี่ยนระหว่างตรวจทาน กรุณาสร้างร่างใหม่');
    if (newsMatchFingerprint(fresh) !== draft.sourceFingerprint) throw new Error('ข้อมูลการแข่งขันเปลี่ยนแล้ว กรุณาสร้างร่างใหม่เพื่อใช้ข้อมูลล่าสุด');
    if (draft.ai) validateNewsAiFacts({ title: $('#news-draft-title').value, excerpt: $('#news-draft-excerpt').value, body: $('#news-draft-body').value }, fresh);
  }
  const form = $('#news-form');
  if (mode !== 'all' && draft.match_id && newsUi.appliedDraft?.match_id && String(draft.match_id) !== String(newsUi.appliedDraft.match_id)) {
    throw new Error('ข้อความเดิมอ้างอิงคนละแมตช์ กรุณาใช้ “ใช้ร่างนี้” เพื่อแทนหัวข้อ คำโปรย และเนื้อหาให้ตรงกัน');
  }
  const keys = mode === 'replace' ? ['body'] : ['title', 'excerpt', 'body'];
  if (mode !== 'append' && keys.some(key => form.elements[key].value.trim())) {
    if (!await confirmAction('นำร่างมาแทนข้อความ?', mode === 'replace' ? 'เนื้อหาข่าวปัจจุบันจะถูกแทนที่ หัวข้อ คำโปรย รูปปก และข้อมูลการแข่งขันยังอยู่' : 'หัวข้อ คำโปรย และเนื้อหาปัจจุบันจะถูกแทนที่ ส่วนข้อมูลการแข่งขัน รูปปก และหมวดหมู่เดิมยังอยู่', 'นำร่างมาใช้')) return;
  }
  if (mode === 'append') {
    form.elements.body.value = [form.elements.body.value.trimEnd(), $('#news-draft-body').value].filter(Boolean).join('\n\n');
  } else {
    keys.forEach(key => {form.elements[key].value = $(`#news-draft-${key}`).value;});
  }
  if (draft.news_type) form.elements.news_type.value = draft.news_type;
  newsUi.appliedDraft = draft.match_id ? { match_id: draft.match_id, sourceFingerprint: draft.sourceFingerprint } : mode !== 'all' ? newsUi.appliedDraft : null;
  syncBannerFromNews({ sport: draft.sport_name || '', headline: draft.banner_title || form.elements.title.value, subtitle: draft.banner_subtitle || form.elements.excerpt.value });
  bannerStudio.newsSyncSnapshot = newsBannerSourceSnapshot();
  newsUi.pendingDraft = null;
  $('#news-draft-review').hidden = true;
  syncNewsCategory();
  markNewsDirty();
  if (matchMedia('(max-width: 900px)').matches) closeNewsAiPanel();
  $('#news-excerpt-settings').open = Boolean(form.elements.excerpt.value);
  form.elements.title.focus();
  toast('นำร่างมาใช้แล้ว แก้ไขข้อความต่อได้ก่อนบันทึก');
}

function bindNewsDraftReview() {
  ['title', 'body'].forEach(key => $(`#news-draft-${key}`).addEventListener('input', event => {
    $(`#news-draft-display-${key}`).textContent = event.target.value;
  }));
  const review = async mode => {
    if (newsUi.reviewing || $('#news-form').dataset.busy === 'true' || $('#news-ai-panel').getAttribute('aria-busy') === 'true') return;
    newsUi.reviewing = true;
    const controls = $$('.news-draft-actions button, #news-draft-regenerate, #news-ai-button');
    const disabled = controls.map(button => button.disabled);
    controls.forEach(button => {button.disabled = true;});
    try { await applyNewsDraft(mode); }
    finally {
      newsUi.reviewing = false;
      controls.forEach((button, i) => {button.disabled = disabled[i];});
    }
  };
  bindTaskButton('#news-draft-apply', () => review('all'));
  bindTaskButton('#news-draft-replace', () => review('replace'));
  bindTaskButton('#news-draft-append', () => review('append'));
  $('#news-draft-regenerate').addEventListener('click', () => {
    if (newsUi.pendingDraft?.ai) $('#news-ai-button').click();
    else if (newsUi.pendingDraft?.match_id) $('#news-from-match-button').click();
    else $('#news-template-select').dispatchEvent(new Event('change', {bubbles:true}));
  });
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
