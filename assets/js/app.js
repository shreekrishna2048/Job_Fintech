/* KaamKhoj front-end - vanilla JS, no dependencies */
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const KK = window.KK || { base: '', csrf: '' };
  const api = async (path, data, method = 'POST') => {
    const opt = { method, headers: { 'X-CSRF-Token': KK.csrf, 'Accept': 'application/json' }, credentials: 'same-origin' };
    if (data && method === 'POST') { const fd = new FormData(); Object.entries(data).forEach(([k, v]) => v != null && fd.append(k, v)); fd.append('_csrf', KK.csrf); opt.body = fd; }
    const res = await fetch(KK.base + '/api/' + path, opt);
    let json = {}; try { json = await res.json(); } catch (e) { json = { ok: false, error: 'Network error' }; }
    if (res.status === 401 && json.login) { location.href = KK.base + '/login'; throw new Error('login'); }
    return json;
  };
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  /* toasts */
  const toastBox = $('#toasts');
  const toast = (msg, type = 'success') => {
    if (!toastBox) return alert(msg);
    const t = document.createElement('div'); t.className = 'toast toast-' + type; t.textContent = msg; toastBox.appendChild(t);
    setTimeout(() => { t.classList.add('out'); setTimeout(() => t.remove(), 320); }, 3800);
  };
  $$('#toasts .toast').forEach((t, i) => setTimeout(() => { t.classList.add('out'); setTimeout(() => t.remove(), 320); }, 4500 + i * 400));
  window.kkToast = toast;

  /* topbar shadow */
  const top = $('#topbar');
  if (top) { const f = () => top.classList.toggle('scrolled', scrollY > 4); f(); addEventListener('scroll', f, { passive: true }); }

  /* dropdowns */
  document.addEventListener('click', e => {
    const tg = e.target.closest('[data-dd-toggle]');
    $$('[data-dd].open').forEach(d => { if (!tg || !d.contains(tg)) d.classList.remove('open'); });
    if (tg) tg.closest('[data-dd]').classList.toggle('open');
  });

  /* drawers (mobile nav, filters) */
  const openDrawer = id => { const d = document.getElementById(id); if (!d) return; d.classList.add('open'); d.setAttribute('aria-hidden', 'false'); document.body.style.overflow = 'hidden'; };
  const closeDrawers = () => { $$('.drawer.open,.filters.open').forEach(d => { d.classList.remove('open'); d.setAttribute('aria-hidden', 'true'); }); document.body.style.overflow = ''; };
  document.addEventListener('click', e => {
    const o = e.target.closest('[data-drawer-open]'); if (o) { e.preventDefault(); openDrawer(o.dataset.drawerOpen); return; }
    if (e.target.closest('[data-drawer-close]') || e.target.classList.contains('drawer')) closeDrawers();
  });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeDrawers(); $('.lightbox')?.remove(); } });

  /* reveal on scroll */
  const io = 'IntersectionObserver' in window ? new IntersectionObserver(es => es.forEach(en => { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } }), { rootMargin: '0px 0px -40px' }) : null;
  $$('.reveal').forEach(el => io ? io.observe(el) : el.classList.add('in'));

  /* count-up numbers */
  const countIO = 'IntersectionObserver' in window ? new IntersectionObserver(es => es.forEach(en => {
    if (!en.isIntersecting) return; countIO.unobserve(en.target);
    const el = en.target, end = +el.dataset.count || 0, t0 = performance.now(), dur = 1200;
    const step = t => { const p = Math.min(1, (t - t0) / dur), v = Math.round(end * (1 - Math.pow(1 - p, 3))); el.textContent = v.toLocaleString(); if (p < 1) requestAnimationFrame(step); };
    requestAnimationFrame(step);
  })) : null;
  $$('[data-count]').forEach(el => countIO ? countIO.observe(el) : (el.textContent = (+el.dataset.count).toLocaleString()));

  /* hero search tabs */
  $$('[data-sb-tab]').forEach(b => b.addEventListener('click', () => {
    $$('[data-sb-tab]').forEach(x => x.classList.toggle('on', x === b));
    $$('[data-sb-panel]').forEach(p => p.hidden = p.dataset.sbPanel !== b.dataset.sbTab);
    $(`[data-sb-panel="${b.dataset.sbTab}"] input`)?.focus();
  }));
  $$('[data-ai-search]').forEach(f => f.addEventListener('submit', async e => {
    e.preventDefault(); const btn = $('button', f), text = f.text.value.trim(); if (!text) return;
    btn.classList.add('is-loading');
    try { const r = await api('ai-search', { text }); location.href = r.url || (KK.base + '/jobs?q=' + encodeURIComponent(text)); }
    catch (err) { location.href = KK.base + '/jobs?q=' + encodeURIComponent(text); }
  }));

  /* autocomplete */
  $$('[data-suggest]').forEach(inp => {
    const box = inp.parentElement.querySelector('.suggest'); if (!box) return;
    let timer, idx = -1, ctrl;
    const hide = () => { box.hidden = true; idx = -1; };
    inp.addEventListener('input', () => {
      clearTimeout(timer); const q = inp.value.trim(); if (q.length < 2) return hide();
      timer = setTimeout(async () => {
        ctrl?.abort(); ctrl = new AbortController();
        try {
          const r = await fetch(KK.base + '/api/suggest?q=' + encodeURIComponent(q), { signal: ctrl.signal }).then(r => r.json());
          if (!r.items?.length) return hide();
          box.innerHTML = r.items.map(i => `<a href="${esc(i.url)}"><span>${esc(i.label)}</span><small>${esc(i.type)}</small></a>`).join('');
          box.hidden = false; idx = -1;
        } catch (e) {}
      }, 180);
    });
    inp.addEventListener('keydown', e => {
      const items = $$('a', box); if (box.hidden || !items.length) return;
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); idx = (idx + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length; items.forEach((a, i) => a.classList.toggle('act', i === idx)); }
      else if (e.key === 'Enter' && idx > -1) { e.preventDefault(); location.href = items[idx].href; }
      else if (e.key === 'Escape') hide();
    });
    inp.addEventListener('blur', () => setTimeout(hide, 180));
  });

  /* save job */
  document.addEventListener('click', async e => {
    const b = e.target.closest('[data-save]'); if (!b) return;
    e.preventDefault(); e.stopPropagation();
    if (!KK.logged) { location.href = KK.base + '/login'; return; }
    try {
      const r = await api('save', { job_id: b.dataset.save });
      if (!r.ok) return toast(r.error, 'error');
      $$(`[data-save="${b.dataset.save}"]`).forEach(x => { x.classList.toggle('on', r.saved); x.setAttribute('aria-pressed', r.saved); x.classList.add('pop'); setTimeout(() => x.classList.remove('pop'), 400); const s = $('span', x); if (s) s.textContent = r.saved ? 'Saved' : 'Save'; });
      toast(r.message);
    } catch (err) {}
  });

  /* follow company */
  document.addEventListener('click', async e => {
    const b = e.target.closest('[data-follow]'); if (!b) return; e.preventDefault();
    if (!KK.logged) { location.href = KK.base + '/login'; return; }
    try {
      const r = await api('follow', { company_id: b.dataset.follow });
      if (!r.ok) return toast(r.error, 'error');
      b.setAttribute('aria-pressed', r.following); const s = $('span', b); if (s) s.textContent = r.following ? 'Following' : 'Follow';
      b.classList.toggle('btn-brand', !r.following); b.classList.toggle('btn-ghost', r.following);
      $$('[data-follow-count]').forEach(c => c.textContent = r.count); toast(r.message);
    } catch (err) {}
  });

  /* job alert from search */
  document.addEventListener('click', async e => {
    const b = e.target.closest('[data-create-alert]'); if (!b) return;
    b.classList.add('is-loading');
    try { const r = await api('alert', JSON.parse(b.dataset.createAlert)); toast(r.ok ? r.message : r.error, r.ok ? 'success' : 'error'); if (r.ok) b.disabled = true; } catch (err) {}
    b.classList.remove('is-loading');
  });

  /* share */
  document.addEventListener('click', async e => {
    const b = e.target.closest('[data-share]'); if (!b) return;
    const url = b.dataset.share, title = b.dataset.title || document.title;
    if (navigator.share) { try { await navigator.share({ title, url }); } catch (err) {} return; }
    try { await navigator.clipboard.writeText(url); toast('Link copied - paste it anywhere'); } catch (err) { prompt('Copy this link:', url); }
  });

  /* report job */
  document.addEventListener('click', async e => {
    const b = e.target.closest('[data-report]'); if (!b) return;
    const reason = prompt('What is wrong with this job? (e.g. asks for money, fake, wrong details)'); if (!reason) return;
    const r = await api('report', { job_id: b.dataset.report, reason }); toast(r.ok ? r.message : r.error, r.ok ? 'success' : 'error');
  });
  $$('[data-track-apply]').forEach(a => a.addEventListener('click', () => { navigator.sendBeacon?.(KK.base + '/api/track-apply?job=' + a.dataset.trackApply); }));

  /* filters auto-submit */
  $$('form[data-autosubmit]').forEach(f => {
    const desktop = matchMedia('(min-width:960px)');
    f.addEventListener('change', () => { if (desktop.matches) f.submit(); });
  });

  /* loading state on submit */
  $$('form[data-loading]').forEach(f => f.addEventListener('submit', e => {
    const b = e.submitter || $('button:not([type=button])', f);
    if (b) setTimeout(() => b.classList.add('is-loading'), 0);
  }));

  /* tag input */
  $$('[data-tags]').forEach(w => {
    const hidden = $('input[type=hidden]', w), entry = $('[data-tag-entry]', w);
    const get = () => hidden.value.split(',').map(s => s.trim()).filter(Boolean);
    const render = () => {
      $$('.tag-chip', w).forEach(c => c.remove());
      get().forEach((t, i) => { const c = document.createElement('span'); c.className = 'tag-chip'; c.innerHTML = esc(t) + `<button type="button" aria-label="Remove ${esc(t)}">×</button>`; c.querySelector('button').onclick = () => { const a = get(); a.splice(i, 1); hidden.value = a.join(', '); render(); }; w.insertBefore(c, entry); });
    };
    const add = v => { v.split(',').map(s => s.trim()).filter(Boolean).forEach(x => { const a = get(); if (!a.some(y => y.toLowerCase() === x.toLowerCase()) && a.length < 30) { a.push(x.slice(0, 40)); hidden.value = a.join(', '); } }); entry.value = ''; render(); };
    entry.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); add(entry.value); }
      else if (e.key === 'Backspace' && !entry.value) { const a = get(); a.pop(); hidden.value = a.join(', '); render(); }
    });
    entry.addEventListener('blur', () => entry.value.trim() && add(entry.value));
    entry.addEventListener('paste', e => { const t = e.clipboardData.getData('text'); if (t.includes(',') || t.includes('\n')) { e.preventDefault(); add(t.replace(/\n/g, ',')); } });
    w.addEventListener('click', e => { if (e.target === w) entry.focus(); });
    w.reload = render; render();
  });

  /* password helpers */
  $$('[data-pw-toggle]').forEach(b => b.addEventListener('click', () => { const i = b.previousElementSibling; i.type = i.type === 'password' ? 'text' : 'password'; }));
  $$('[data-pw-meter]').forEach(i => i.addEventListener('input', () => {
    const v = i.value; let s = 0; if (v.length >= 8) s++; if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++; if (/\d/.test(v)) s++; if (/[^A-Za-z0-9]/.test(v) || v.length >= 12) s++;
    const bar = i.closest('.field').querySelector('.pw-meter span'); if (bar) { bar.style.width = (s * 25) + '%'; bar.style.background = ['#b91c1c', '#ea580c', '#ca8a04', '#16a34a', '#15803d'][s]; }
  }));

  /* register role switch */
  $$('[data-role-switch] [data-role]').forEach(b => b.addEventListener('click', () => {
    const role = b.dataset.role;
    $$('[data-role-switch] [data-role]').forEach(x => x.classList.toggle('on', x === b));
    $$('[data-role-input]').forEach(i => i.value = role);
    $$('[data-employer-only]').forEach(el => el.hidden = role !== 'employer');
    $$('[data-oauth-holder] a').forEach(a => { const u = new URL(a.href); u.searchParams.set('role', role); a.href = u; });
    history.replaceState(null, '', '?role=' + role);
  }));
  $$('[data-role-radio]').forEach(r => r.addEventListener('change', () => $$('[data-employer-only]').forEach(el => el.hidden = !$('[data-role-radio][value=employer]').checked)));

  $$('[data-create-role]').forEach(sel => { const f = () => $$('[data-for-role]', sel.form).forEach(el => el.hidden = el.dataset.forRole !== sel.value); sel.addEventListener('change', f); f(); });

  /* upload zones */
  $$('[data-upload-zone]').forEach(z => {
    const inp = $('input[type=file]', z), lbl = $('label[for]', z);
    if (!inp || !lbl) return;
    const show = () => { const n = [...inp.files].map(f => f.name).join(', '); let s = $('.file-chosen', z); if (!s) { s = document.createElement('small'); s.className = 'file-chosen'; lbl.appendChild(s); } s.textContent = n ? '✓ ' + n : ''; };
    inp.addEventListener('change', () => { show(); if (inp.hasAttribute('data-autosubmit-file') && inp.files.length) { inp.form.querySelector('button')?.classList.add('is-loading'); inp.form.submit(); } });
    ['dragenter', 'dragover'].forEach(ev => z.addEventListener(ev, e => { e.preventDefault(); z.classList.add('drag'); }));
    ['dragleave', 'drop'].forEach(ev => z.addEventListener(ev, e => { e.preventDefault(); z.classList.remove('drag'); }));
    z.addEventListener('drop', e => { if (e.dataTransfer.files.length) { inp.files = e.dataTransfer.files; inp.dispatchEvent(new Event('change')); } });
  });
  const syncResumeChoice = () => { const c = $('input[name=resume_choice]:checked'); $$('[data-show-when]').forEach(el => el.hidden = !c || c.value !== el.dataset.showWhen); };
  $$('input[name=resume_choice]').forEach(r => r.addEventListener('change', syncResumeChoice)); syncResumeChoice();

  /* image previews */
  $$('input[data-preview],input[data-preview-bg]').forEach(inp => inp.addEventListener('change', () => {
    const f = inp.files[0]; if (!f || !f.type.startsWith('image/')) return;
    const url = URL.createObjectURL(f);
    if (inp.dataset.previewBg) { const t = $(inp.dataset.previewBg); if (t) t.style.backgroundImage = `url('${url}')`; return; }
    const t = $(inp.dataset.preview); if (!t) return;
    if (t.tagName === 'IMG') t.src = url; else { const img = document.createElement('img'); img.src = url; img.className = t.className.replace('av-txt', ''); img.alt = ''; t.replaceWith(img); }
  }));

  /* tabs (company page) */
  $$('[data-tabs]').forEach(nav => {
    const links = $$('a', nav);
    const show = id => { if (!document.getElementById(id)) return; links.forEach(a => a.classList.toggle('on', a.getAttribute('href') === '#' + id)); $$('.tabpane').forEach(p => p.classList.toggle('on', p.id === id)); };
    links.forEach(a => a.addEventListener('click', e => { e.preventDefault(); const id = a.getAttribute('href').slice(1); show(id); history.replaceState(null, '', '#' + id); }));
    $$('[data-tab-link]').forEach(a => a.addEventListener('click', e => { e.preventDefault(); show(a.dataset.tabLink); nav.scrollIntoView({ behavior: 'smooth', block: 'start' }); }));
    if (location.hash) show(location.hash.slice(1));
  });

  /* lightbox */
  document.addEventListener('click', e => {
    const a = e.target.closest('[data-lightbox] a'); if (!a) return; e.preventDefault();
    const lb = document.createElement('div'); lb.className = 'lightbox';
    lb.innerHTML = `<img src="${esc(a.href)}" alt=""><button class="icon-btn" aria-label="Close">✕</button>`;
    lb.addEventListener('click', ev => { if (ev.target.tagName !== 'IMG') lb.remove(); }); document.body.appendChild(lb);
  });

  /* job form helpers */
  $$('[data-apply-method]').forEach(s => { const f = () => { const t = $('[data-apply-target]'); if (t) t.hidden = s.value === 'portal'; }; s.addEventListener('change', f); f(); });
  $$('[data-add-question]').forEach(b => b.addEventListener('click', () => {
    const box = $('[data-questions]'); const n = $$('.q-row', box).length; if (n >= 5) return toast('Up to 5 questions', 'info');
    const row = document.createElement('div'); row.className = 'q-row'; row.innerHTML = `<input name="q[]" placeholder="Your question"><label class="check"><input type="checkbox" name="q_req[${n}]" value="1"> Required</label>`; box.appendChild(row); $('input', row).focus();
  }));
  $$('[data-status-form] input[name=status]').forEach(r => r.addEventListener('change', () => { const f = $('[data-interview-field]'); if (f) f.hidden = r.value !== 'interview' || !r.checked; }));

  /* ---------- AI ---------- */
  const mdToHtml = t => {
    let out = '', list = false;
    t.split(/\n/).forEach(l => {
      l = l.trim(); const m = l.match(/^([-*•]|\d+[.)])\s+(.*)/);
      if (m) { if (!list) { out += '<ul>'; list = true; } out += '<li>' + esc(m[2]).replace(/\*\*(.+?)\*\*/g, '<b>$1</b>') + '</li>'; return; }
      if (list) { out += '</ul>'; list = false; } if (!l) return;
      out += /^#+\s/.test(l) ? '<h3>' + esc(l.replace(/^#+\s/, '')) + '</h3>' : '<p>' + esc(l).replace(/\*\*(.+?)\*\*/g, '<b>$1</b>') + '</p>';
    });
    return out + (list ? '</ul>' : '');
  };
  const fill = (el, v) => { if (!el) return; if ('value' in el && el.tagName !== 'DIV') { el.value = v; el.classList.remove('ai-filled'); void el.offsetWidth; el.classList.add('ai-filled'); el.dispatchEvent(new Event('input')); } else { el.innerHTML = mdToHtml(v); el.hidden = false; } };
  document.addEventListener('click', async e => {
    const b = e.target.closest('[data-ai]'); if (!b) return; e.preventDefault();
    const target = $(b.dataset.target), feature = b.dataset.ai;
    const own = target && 'value' in target && target.tagName !== 'DIV' ? target.value.trim() : '';
    let text = b.dataset.context || '';
    if (feature === 'improve') text = own;
    else if (own && feature !== 'interview_tips') text += '\n\nCurrent draft:\n' + own;
    if (own && feature !== 'improve' && feature !== 'interview_tips' && !confirm('Replace your current text with an AI version?')) return;
    b.classList.add('is-loading'); const label = b.lastChild.textContent; b.lastChild.textContent = ' Writing…';
    try {
      const r = await api('ai', { feature, text });
      if (!r.ok) toast(r.error, 'error'); else { fill(target, r.data.text || ''); toast('Done! Review and edit as you like.' + (r.remaining < 5 ? ` (${r.remaining} AI uses left today)` : '')); }
    } catch (err) {}
    b.classList.remove('is-loading'); b.lastChild.textContent = label;
  });
  $$('[data-ai-job]').forEach(b => b.addEventListener('click', async () => {
    const f = b.closest('form'); const title = f.title.value.trim();
    if (!title) { toast('Enter the job title first', 'error'); f.title.focus(); return; }
    if ((f.description.value.trim() || f.requirements.value.trim()) && !confirm('Replace the description, requirements and benefits with an AI draft?')) return;
    b.classList.add('is-loading');
    try {
      const r = await api('ai', { feature: 'job_description', title, company: b.dataset.company, notes: $('[data-ai-notes]', f)?.value || '', level: $('[data-ai-level]', f)?.selectedOptions[0]?.text, type: $('[data-ai-type]', f)?.selectedOptions[0]?.text });
      if (!r.ok) toast(r.error, 'error');
      else {
        const d = r.data; fill(f.description, d.description || ''); fill(f.requirements, d.requirements || ''); fill(f.benefits, d.benefits || '');
        if (d.skills) { const h = $('#skills'); h.value = d.skills; h.closest('[data-tags]')?.reload?.(); }
        toast('Draft ready - review and publish ✨');
      }
    } catch (err) {}
    b.classList.remove('is-loading');
  }));

  /* ---------- Kanban board ---------- */
  $$('[data-board]').forEach(board => {
    let drag = null;
    $$('.bcard', board).forEach(c => {
      c.addEventListener('dragstart', e => { drag = c; c.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; });
      c.addEventListener('dragend', () => { c.classList.remove('dragging'); drag = null; });
    });
    $$('[data-dropzone]', board).forEach(z => {
      z.addEventListener('dragover', e => { e.preventDefault(); z.classList.add('over'); });
      z.addEventListener('dragleave', () => z.classList.remove('over'));
      z.addEventListener('drop', async e => {
        e.preventDefault(); z.classList.remove('over'); if (!drag) return;
        const card = drag, from = card.parentElement, status = z.closest('.board-col').dataset.status;
        if (from === z) return;
        z.prepend(card); const upd = () => $$('.board-col', board).forEach(col => $('.count', col).textContent = $$('.bcard', col).length); upd();
        const r = await api('app-status', { app_id: card.dataset.app, status });
        if (!r.ok) { from.prepend(card); upd(); toast(r.error, 'error'); if (r.open) setTimeout(() => location.href = r.open, 700); }
        else toast(r.message + (status !== 'viewed' ? ' · candidate notified' : ''));
      });
    });
  });

  /* sticky apply bar on job page */
  const bar = $('.sticky-apply'), anchor = $('#apply-bar');
  if (bar && anchor && 'IntersectionObserver' in window) new IntersectionObserver(([en]) => bar.classList.toggle('show', !en.isIntersecting && en.boundingClientRect.top < 0)).observe(anchor);

  /* notification badge refresh */
  if (KK.logged && !document.body.classList.contains('admin-body')) setInterval(async () => {
    if (document.hidden) return;
    try { const r = await fetch(KK.base + '/api/notif-count').then(r => r.json()); const btn = $('a[aria-label=Notifications]'); if (!btn) return; let d = $('.dot-count', btn); if (r.count) { if (!d) { d = document.createElement('b'); d.className = 'dot-count'; btn.appendChild(d); } d.textContent = r.count > 9 ? '9+' : r.count; } else d?.remove(); } catch (e) {}
  }, 90000);
})();

/* ---------- auth & admin extras ---------- */
(() => {
  const $ = (s, r = document) => r.querySelector(s), $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const base = (window.KK || {}).base || '';
  // caps lock warning
  $$('[data-capslock]').forEach(i => ['keydown', 'keyup'].forEach(ev => i.addEventListener(ev, e => { const w = i.closest('.field')?.querySelector('[data-caps]'); if (w && e.getModifierState) w.hidden = !e.getModifierState('CapsLock'); })));
  // register: live email check + typo suggestion
  $$('[data-email-check]').forEach(inp => {
    const msg = inp.closest('.field').querySelector('[data-email-msg]'); let t;
    const run = async () => {
      const v = inp.value.trim(); if (!v.includes('@')) { msg.hidden = true; return; }
      try {
        const r = await fetch(base + '/api/email-check?email=' + encodeURIComponent(v)).then(r => r.json());
        if (r.exists) { msg.className = 'err'; msg.innerHTML = 'This email already has an account' + (r.via?.length ? ' (' + r.via.filter(x => x !== 'password').join('/') + (r.via.includes('password') ? (r.via.length > 1 ? ' or ' : '') + 'password' : '') + ')' : '') + '. <a href="' + base + '/login">Sign in instead</a>'; msg.hidden = false; }
        else if (r.suggest) { msg.className = 'hint-warn'; msg.innerHTML = 'Did you mean <a href="#" data-fix>' + r.suggest.replace(/[<>&"]/g, '') + '</a>?'; msg.hidden = false; msg.querySelector('[data-fix]').onclick = e => { e.preventDefault(); inp.value = r.suggest; msg.hidden = true; }; }
        else if (r.valid === false) { msg.className = 'err'; msg.textContent = 'Check this email address.'; msg.hidden = false; }
        else msg.hidden = true;
      } catch (e) {}
    };
    inp.addEventListener('input', () => { clearTimeout(t); t = setTimeout(run, 500); });
    inp.addEventListener('blur', run);
  });
  // role cards also switch submit label, perks & oauth labels
  $$('[data-role-switch] [data-role]').forEach(b => b.addEventListener('click', () => {
    $$('[data-role-switch] [data-role]').forEach(x => x.setAttribute('aria-selected', x === b));
    $$('[data-seeker-only]').forEach(el => el.hidden = b.dataset.role !== 'seeker');
  }));
  // client-side login validation hint
  $$('form[data-validate]').forEach(f => f.addEventListener('submit', e => {
    const em = f.querySelector('input[type=email]'), err = em?.closest('.field').querySelector('[data-err]');
    if (em && !/^\S+@\S+\.\S+$/.test(em.value)) { e.preventDefault(); if (err) { err.textContent = 'Enter a valid email address.'; err.hidden = false; } em.focus(); f.querySelector('.is-loading')?.classList.remove('is-loading'); }
  }));
  // oauth buttons: show progress
  $$('[data-oauth]').forEach(a => a.addEventListener('click', () => { a.classList.add('is-going'); }));
  // admin copy buttons
  document.addEventListener('click', async e => {
    const b = e.target.closest('[data-copy]'); if (!b) return;
    try { await navigator.clipboard.writeText(b.dataset.copy); } catch (err) { const i = b.previousElementSibling; i?.select(); document.execCommand('copy'); }
    const t = b.textContent; b.textContent = 'Copied ✓'; setTimeout(() => b.textContent = t, 1500);
  });
})();

/* ---------- admin: bulk import & AI settings ---------- */
(() => {
  const $ = (s, r = document) => r.querySelector(s), $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const KK = window.KK || {}; const here = location.pathname;
  const post = async (data, path = here) => {
    const fd = new FormData(); Object.entries(data).forEach(([k, v]) => fd.append(k, v ?? '')); fd.append('_csrf', KK.csrf);
    const r = await fetch(path, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } });
    try { return await r.json(); } catch (e) { return { ok: false, error: 'Server error (HTTP ' + r.status + '). Check PHP time limit or try a smaller import.' }; }
  };
  const toast = (m, t) => window.kkToast ? window.kkToast(m, t) : alert(m);

  // AI parse progress
  const box = $('[data-import-parse]');
  if (box) (async () => {
    const batch = box.dataset.batch, total = +box.dataset.total; let found = 0, done = +box.dataset.start;
    const bar = $('.progress span', box), log = $('[data-ip-log]', box), st = $('[data-ip-status]', box);
    for (let i = done; i < total; i++) {
      st.textContent = `Reading part ${i + 1} of ${total}…`;
      let r, tries = 0;
      while (true) {
        r = await post({ action: 'parse', batch, chunk: i });
        if (!r.retry || tries++ >= 6) break;
        for (let s = r.wait; s > 0; s--) { st.textContent = `AI free-plan limit reached, waiting ${s}s then continuing part ${i + 1}…`; await new Promise(res => setTimeout(res, 1000)); }
        st.textContent = `Reading part ${i + 1} of ${total}…`;
      }
      if (r.stop) {
        const li = document.createElement('li'); li.className = 'err'; li.textContent = '⛔ ' + r.error; log.prepend(li);
        st.innerHTML = 'Stopped to protect your budget. <a href="?batch=' + batch + '&review=1">Review the ' + found + ' job(s) found so far</a>';
        return;
      }
      done++; bar.style.width = Math.round(done / total * 100) + '%'; $('[data-ip-done]', box).textContent = done;
      const li = document.createElement('li');
      if (r.ok) {
        found += r.found; const s = r.stats;
        li.textContent = `✓ ${box.dataset.finder ? 'Page' : 'Part'} ${i + 1}: ${r.found} job(s)` + (s ? [s.old ? ` · ${s.old} too old/closed` : '', s.dup ? ` · ${s.dup} already have` : '', s.abroad ? ` · ${s.abroad} other country` : '', s.skipped ? ` · skipped: ${s.skipped}` : ''].join('') : '');
      } else { li.className = 'err'; li.textContent = `✗ Part ${i + 1}: ${r.error}`; }
      log.prepend(li); $('[data-ip-found]', box).textContent = found;
    }
    st.textContent = 'Done! Opening review…'; setTimeout(() => location.reload(), 600);
  })();

  // Review form
  const form = $('[data-import-form]');
  if (form) {
    const items = $$('[data-item]', form);
    const count = () => { const n = $$('[data-include]:checked', form).length; const c = $('[data-sel-count]'); if (c) c.textContent = n + ' job(s) selected'; return n; };
    $$('[data-include]', form).forEach(c => c.addEventListener('change', () => { c.closest('[data-item]').classList.toggle('imp-off', !c.checked); count(); }));
    $$('[data-include]', form).forEach(c => c.closest('[data-item]').classList.toggle('imp-off', !c.checked));
    $('[data-check-all]')?.addEventListener('change', e => { $$('[data-include]', form).forEach(c => { c.checked = e.target.checked; c.dispatchEvent(new Event('change')); }); });
    // company name edited → no longer the matched company
    $$('[data-f=company]', form).forEach(i => i.addEventListener('input', () => { const h = i.parentNode.querySelector('[data-f=company_id]'); if (h) h.value = ''; }));
    count();
    let act = 'publish';
    $$('[data-act]', form).forEach(b => b.addEventListener('click', () => { act = b.dataset.act; }));
    form.addEventListener('submit', e => {
      const payload = items.map(el => { const d = {}; $$('[data-f]', el).forEach(f => d[f.dataset.f] = f.value); const inc = $('[data-include]', el); return { id: el.dataset.item, include: inc ? inc.checked : false, data: d }; });
      if (act === 'publish') {
        const sel = payload.filter(p => p.include);
        if (!sel.length) { e.preventDefault(); return toast('Select at least one job', 'error'); }
        const noCity = sel.filter(p => !p.data.location_id).length;
        const cos = new Set(sel.map(p => (p.data.company || '').toLowerCase().trim())).size;
        const newCos = new Set(sel.filter(p => !p.data.company_id).map(p => (p.data.company || '').toLowerCase().trim())).size;
        if (!confirm(`Publish ${sel.length} job(s) from ${cos} company(ies) now?\n\n• ${newCos} new company page(s) will be created\n• Jobs go live immediately and job alerts are sent\n• Search engines are notified${noCity ? `\n\n⚠ ${noCity} job(s) have no city - they will use the default city or none.` : ''}`)) { e.preventDefault(); return; }
      }
      form.querySelector('[name=action]').value = act; form.querySelector('[name=items_json]').value = JSON.stringify(payload);
      const b = e.submitter; if (b) setTimeout(() => b.classList.add('is-loading'), 0);
    });
    // contact finder
    const find = async el => {
      const btn = $('[data-find-contact]', el), out = $('[data-found]', el);
      btn.classList.add('is-loading');
      const r = await post({ action: 'contacts', company: $('[data-f=company]', el).value, website: $('[data-f=company_website]', el).value });
      btn.classList.remove('is-loading'); out.hidden = false;
      if (!r.ok) { out.innerHTML = '<span class="err">' + (r.error || 'Failed') + '</span>'; return false; }
      if (r.website && !$('[data-f=company_website]', el).value) $('[data-f=company_website]', el).value = r.website;
      if (r.phone && !$('[data-f=contact_phone]', el).value) $('[data-f=contact_phone]', el).value = r.phone;
      const em = $('[data-f=contact_email]', el);
      if (r.emails?.length) {
        if (!em.value) { em.value = r.emails[0]; em.classList.add('ai-filled'); $('[data-noemail]', el)?.remove(); }
        out.innerHTML = 'Found: ' + r.emails.slice(0, 5).map(x => `<a href="#" data-pick="${x}">${x}</a>`).join(' · ') + '<br><span class="muted">' + (r.source || []).join(' · ') + ' - please confirm before publishing.</span>';
        $$('[data-pick]', out).forEach(a => a.onclick = ev => { ev.preventDefault(); em.value = a.dataset.pick; });
        return true;
      }
      out.innerHTML = '<span class="muted">' + (r.source || ['Nothing found']).join(' · ') + '</span>';
      return false;
    };
    $$('[data-find-contact]', form).forEach(b => b.addEventListener('click', () => find(b.closest('[data-item]'))));
    $('[data-find-all]')?.addEventListener('click', async e => {
      const b = e.currentTarget; b.classList.add('is-loading'); let n = 0, got = 0;
      const todo = items.filter(el => $('[data-include]', el)?.checked && $('[data-f=contact_email]', el) && !$('[data-f=contact_email]', el).value);
      const seen = {};
      for (const el of todo) {
        const key = $('[data-f=company]', el).value.toLowerCase().trim();
        if (seen[key] !== undefined) { if (seen[key]) $('[data-f=contact_email]', el).value = seen[key]; continue; }
        n++; const ok = await find(el); seen[key] = ok ? $('[data-f=contact_email]', el).value : ''; if (ok) got++;
      }
      b.classList.remove('is-loading'); toast(`Checked ${n} companies · found emails for ${got}. Please review them.`);
    });
  }

  // AI settings page
  const seg = $('[data-prov-seg]');
  if (seg) {
    const sync = () => { const v = $('input:checked', seg)?.value; $$('[data-prov]').forEach(b => b.hidden = b.dataset.prov !== v); $$('label', seg).forEach(l => l.classList.toggle('on', $('input', l).checked)); };
    $$('input', seg).forEach(i => i.addEventListener('change', sync)); sync();
  }
  $$('[data-load-models]').forEach(b => b.addEventListener('click', async () => {
    b.classList.add('is-loading'); const r = await post({ action: 'models', provider: b.dataset.loadModels }); b.classList.remove('is-loading');
    if (!r.ok) return toast(r.error, 'error');
    const dl = document.getElementById('models-' + b.dataset.loadModels); dl.innerHTML = r.models.map(m => `<option value="${m}">`).join('');
    toast(r.models.length + ' models loaded - click the model box to choose'); b.previousElementSibling.focus();
  }));
  $$('[data-ai-test]').forEach(b => b.addEventListener('click', async () => {
    const out = $(`[data-test-out="${b.dataset.aiTest}"]`); b.classList.add('is-loading');
    const r = await post({ action: b.dataset.aiTest }); b.classList.remove('is-loading');
    out.className = 'small ' + (r.ok ? 'ok-txt' : 'err'); out.textContent = r.ok ? '✓ ' + r.message : '✗ ' + r.error;
  }));
})();
