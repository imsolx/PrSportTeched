// Optional AI newsroom. Mirrored selectors share the original competition controls.
function syncNewsAiOptions() {
  syncNewsCompetitionOptions();
}

function validateNewsAiFacts(draft, match) {
  const text = [draft.title, draft.excerpt, draft.body].join(' ');
  const facts = JSON.stringify({ code: match.match_code, tournament: match.tournament_name, sport: match.sport_name, team1: match.team1_id ? match.team1_name : '', team2: match.team2_id ? match.team2_name : '', score: newsMatchIsOfficial(match) ? match.score_label : '', date: match.scheduled_at, venue: match.venue, result: newsMatchIsOfficial(match) ? match.result_summary : {} });
  const known = new Set((facts.match(/\d+/g) || []).map(value => String(Number(value))));
  const year = Number(String(match.scheduled_at || '').slice(0, 4));
  if (year > 1900) known.add(String(year + 543));
  const unknown = (text.match(/\d+/g) || []).find(value => !known.has(String(Number(value))));
  if (unknown) throw new Error('ร่าง AI มีตัวเลขที่ตรวจสอบกับแมตช์ไม่ได้ กรุณาใช้ร่างจากข้อมูลการแข่งขัน หรือสร้างร่างใหม่');
  // This numeric check is only a safety net. Named entities/prose still require explicit human review.
}

function bindNewsAi() {
  bindTaskButton('#news-ai-button', async () => {
    $('#news-ai-error').hidden = true;
    const reviewControls = $$('button,input,textarea', $('#news-draft-review'));
    reviewControls.forEach(control => {control.disabled = true;});
    $('#news-draft-review').hidden = true;
    $('#news-ai-panel').setAttribute('aria-busy', 'true');
    $('#news-ai-status').textContent = 'กำลังสร้างร่าง…';
    try {
      await generateNewsAiDraft();
    } catch (error) {
      $('#news-ai-status').textContent = 'สร้างร่างไม่สำเร็จ — ลองใหม่หรือใช้ข้อมูลจากระบบ';
      $('#news-ai-error').textContent = error.message;
      $('#news-ai-error').hidden = false;
      throw error;
    } finally {
      reviewControls.forEach(control => {control.disabled = false;});
      $('#news-draft-review').hidden = !newsUi.pendingDraft;
      $('#news-ai-panel').removeAttribute('aria-busy');
    }
  });
}

async function generateNewsAiDraft() {
    const mode = $('#news-ai-mode').value;
    const token = ++newsUi.generation;
    const length = {short:'สั้น กระชับประมาณหนึ่งย่อหน้า',medium:'ปานกลางประมาณสองถึงสามย่อหน้า',long:'รายละเอียดครบประมาณสี่ถึงห้าย่อหน้า'}[$('#news-ai-length').value];
    const emphasis = $('#news-ai-instruction').value.trim();
    if (mode === 'topic') {
      const topic = $('#news-ai-topic').value.trim();
      if (!topic) throw new Error('กรุณาระบุหัวข้อหรือข้อมูลจริงก่อนสร้างร่างข่าว');
      const instruction = `[GENERAL_TOPIC]\n${topic}\n\nประเด็นเน้น: ${emphasis}\nความยาว: ${length}`;
      const result = await request('ai.news', {match_id:'', sport_id:'', tournament_id:'', story_type:'preview', tone:$('#news-ai-tone').value, instruction});
      if (!newsUi.editing || token !== newsUi.generation || $('#news-ai-mode').value !== mode || $('#news-ai-topic').value.trim() !== topic) {
        $('#news-ai-status').textContent = 'ข้อมูลเปลี่ยนแล้ว กรุณาสร้างร่างใหม่';
        return;
      }
      const usedAi = result.provider === 'google-gemini';
      $('#news-ai-status').textContent = usedAi ? `Gemini: ${result.model}` : 'ใช้แม่แบบในระบบ — ไม่ได้สร้างด้วย AI';
      stageNewsDraft({...result, match_id:'', general:true}, usedAi ? `Gemini · ${result.model} · ข้อมูลหัวข้อที่คุณระบุ` : 'แม่แบบในระบบ · local-summary · ข้อมูลหัวข้อที่คุณระบุ', true);
      return;
    }
    const id = $('#news-match-link').value;
    if (!id) {
      $('[data-news-reference=match_id]').focus();
      throw new Error('กรุณาเลือกแมตช์อ้างอิงก่อนใช้ AI');
    }
    const match = await refreshNewsMatch(id);
    const story = $('#news-ai-story-type').value;
    if (match.status === 'completed' && !newsMatchIsOfficial(match)) throw new Error('แมตช์จบแล้วแต่ผลยังไม่ยืนยัน กรุณาใช้ร่างข้อมูลจากระบบก่อนยืนยันผล');
    if (story === 'result' && !newsMatchIsOfficial(match)) throw new Error('ผลยังไม่ยืนยัน จึงสร้างข่าวสรุปผลทางการไม่ได้ กรุณาเลือกรูปแบบก่อนแข่งหรือไฮไลต์');
    if (!match.team1_id || !match.team2_id) throw new Error('AI เดิมไม่มีข้อมูลทีมครบสำหรับแมตช์นี้ กรุณาใช้ “สร้างร่างจากแมตช์” เพื่ออ้างข้อมูลจริงโดยไม่สร้างรายชื่อขึ้นเอง');
    const fingerprint = newsMatchFingerprint(match);
      const result = await request('ai.news', {
        sport_id: match.sport_id || '',
        tournament_id: match.tournament_id || '',
        match_id: id,
        story_type: story,
        tone: $('#news-ai-tone').value,
        instruction: `${emphasis}\nความยาว: ${length}`,
      });
      if (!newsUi.editing || token !== newsUi.generation || $('#news-match-link').value !== id || $('#news-ai-mode').value !== mode) {
        toast('แหล่งข้อมูลหรือหน้าที่ใช้งานเปลี่ยนแล้ว จึงไม่นำร่างเก่ามาแสดง', true);
        $('#news-ai-status').textContent = 'ข้อมูลเปลี่ยนแล้ว กรุณาสร้างร่างใหม่';
        return;
      }
      const fresh = await refreshNewsMatch(id);
      if (newsMatchFingerprint(fresh) !== fingerprint) throw new Error('ผลการแข่งขันเปลี่ยนระหว่างสร้างร่าง กรุณาสร้างใหม่');
      validateNewsAiFacts(result, fresh);
      const usedAi = result.provider === 'google-gemini';
      $('#news-ai-status').textContent = usedAi ? `Gemini: ${result.model}` : 'ใช้แม่แบบในระบบ — ไม่ได้สร้างด้วย AI';
      stageNewsDraft({ ...result, match_id: id, sourceFingerprint: fingerprint }, `${usedAi ? `Gemini · ${result.model}` : 'แม่แบบในระบบ · local-summary'} · ${newsMatchPair(match)}`, true);
}
