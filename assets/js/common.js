/* Spendly – shared front-end helpers (API client, layout shell, modal, toasts) */
const CUR = 'Tk';
const App = { user: null, csrf: '', cats: null };

/* ---------- utilities ---------- */
function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
function money(n) {
  n = Number(n) || 0;
  return CUR + ' ' + n.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}
function fmtDate(d) {
  if (!d) return '';
  const [y, m, day] = d.split('-').map(Number);
  return new Date(y, m - 1, day).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}
function todayStr() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}
function thisMonth() { return todayStr().slice(0, 7); }
function monthLabel(m) {
  const [y, mo] = m.split('-').map(Number);
  return new Date(y, mo - 1, 1).toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
}
const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];

/* ---------- API ---------- */
async function api(path, { method = 'GET', body } = {}) {
  const opt = { method, headers: { 'X-CSRF-Token': App.csrf }, credentials: 'same-origin' };
  if (body !== undefined) { opt.headers['Content-Type'] = 'application/json'; opt.body = JSON.stringify(body); }
  let res, data;
  try { res = await fetch('api/' + path, opt); } catch (e) { throw new Error('Cannot reach the server. Is Apache running?'); }
  try { data = await res.json(); } catch (e) { throw new Error('Unexpected server response. Check PHP errors and api/config.php.'); }
  if (res.status === 401 && !document.body.dataset.public) { location.href = 'login.html'; throw new Error('Please log in.'); }
  if (!res.ok) throw new Error(data.error || 'Something went wrong.');
  return data;
}
const qs = o => new URLSearchParams(Object.entries(o).filter(([, v]) => v !== '' && v != null)).toString();

/* ---------- toasts ---------- */
function toast(msg, kind = '') {
  let box = $('#toasts');
  if (!box) { box = document.createElement('div'); box.id = 'toasts'; document.body.appendChild(box); }
  const t = document.createElement('div');
  t.className = 'toast ' + kind;
  t.textContent = msg;
  box.appendChild(t);
  setTimeout(() => t.remove(), 4500);
}
function budgetToast(w) {
  if (!w) return;
  const over = w.percent >= 100;
  toast(over ? `Budget exceeded for ${w.category}: ${money(w.spent)} of ${money(w.limit)} (${w.percent}%)`
             : `Careful – you have used ${w.percent}% of your ${w.category} budget.`, over ? 'error' : 'warn');
}

/* ---------- modal ---------- */
function openModal(html) {
  closeModal();
  const bg = document.createElement('div');
  bg.className = 'modal-bg'; bg.id = 'modal';
  bg.innerHTML = `<div class="modal" role="dialog" aria-modal="true">${html}</div>`;
  bg.addEventListener('mousedown', e => { if (e.target === bg) closeModal(); });
  document.body.appendChild(bg);
  const first = $('input,select', bg); if (first) first.focus();
  return bg;
}
function closeModal() { const m = $('#modal'); if (m) m.remove(); }
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

function confirmBox(msg) {
  return new Promise(res => {
    const m = openModal(`<h2>Please confirm</h2><p class="muted" style="text-align:center;margin-bottom:20px">${esc(msg)}</p>
      <div class="btns"><button class="btn ghost" id="cNo">Cancel</button><button class="btn danger" id="cYes">Yes, delete</button></div>`);
    $('#cNo', m).onclick = () => { closeModal(); res(false); };
    $('#cYes', m).onclick = () => { closeModal(); res(true); };
  });
}

/* ---------- categories cache ---------- */
async function loadCats(force = false) {
  if (!App.cats || force) App.cats = (await api('categories.php')).categories;
  return App.cats;
}
function catOptions(type, selected) {
  return (App.cats || []).filter(c => c.type === type)
    .map(c => `<option value="${c.category_id}" ${Number(selected) === c.category_id ? 'selected' : ''}>${esc(c.name)}</option>`).join('');
}

/* ---------- layout shell ---------- */
function buildShell() {
  const page = document.body.dataset.page;
  const title = document.body.dataset.title || '';
  const links = [['dashboard', 'Dashboard'], ['transactions', 'Transactions'], ['budgets', 'Budgets'], ['goals', 'Goals'], ['reports', 'Reports'], ['settings', 'Settings']];
  if (App.user.role === 'admin') links.push(['admin', 'Admin Panel']);
  $('#sidebar').innerHTML = `
    <div class="logo">Spendly</div>
    <nav class="nav">${links.map(([k, n]) => `<a href="${k}.html" class="${k === page ? 'active' : ''}">${n}</a>`).join('')}</nav>
    <div class="who">${esc(App.user.name)}<br>${esc(App.user.email)}</div>
    <button class="logout" id="logoutBtn">Logout</button>`;
  const addBtn = page === 'admin' || page === 'settings' ? '' :
    `<button class="btn add-top" id="addTop">+ <span class="long">Add&nbsp;</span>Expense</button>`;
  $('#topbar').innerHTML = `<div style="display:flex;align-items:center;gap:12px"><button class="burger" id="burger" aria-label="Menu">☰</button><h1>${esc(title)}</h1></div>${addBtn}`;
  const scrim = document.createElement('div'); scrim.className = 'scrim'; document.body.appendChild(scrim);
  const toggle = () => { $('#sidebar').classList.toggle('open'); scrim.classList.toggle('on'); };
  $('#burger').onclick = toggle; scrim.onclick = toggle;
  $('#logoutBtn').onclick = async () => { try { await api('auth.php?action=logout', { method: 'POST', body: {} }); } catch (e) {} location.href = 'login.html'; };
  if ($('#addTop')) $('#addTop').onclick = () => openTxnModal();
}

async function initApp(opts = {}) {
  const r = await fetch('api/auth.php?action=me', { credentials: 'same-origin' }).then(x => x.json()).catch(() => null);
  if (!r) { document.body.innerHTML = '<p style="padding:40px">Cannot reach the server. Make sure Apache and MySQL are running and the project is inside htdocs.</p>'; throw new Error('server'); }
  App.csrf = r.csrf;
  if (!r.user) { location.href = 'login.html'; throw new Error('redirect'); }
  App.user = r.user;
  if (opts.admin && App.user.role !== 'admin') { location.href = 'dashboard.html'; throw new Error('redirect'); }
  buildShell();
  await loadCats();
  return App.user;
}

async function initPublic() {
  document.body.dataset.public = '1';
  const r = await fetch('api/auth.php?action=me', { credentials: 'same-origin' }).then(x => x.json()).catch(() => null);
  if (r && r.csrf) { App.csrf = r.csrf; return r.user || null; }
  const c = await fetch('api/auth.php?action=csrf', { credentials: 'same-origin' }).then(x => x.json()).catch(() => null);
  if (c) App.csrf = c.csrf;
  return null;
}

/* ---------- add / edit transaction modal (shared by all pages) ---------- */
function openTxnModal(txn = null) {
  let type = txn ? txn.type : 'expense';
  const m = openModal(`
    <h2>${txn ? 'Edit' : 'Add'} Transaction</h2>
    <div class="seg"><button type="button" data-t="expense">Expense</button><button type="button" data-t="income">Income</button></div>
    <div id="tErr"></div>
    <div class="row">
      <div class="field"><label>Amount</label><input id="tAmt" type="number" min="0.01" step="0.01" placeholder="0.00" value="${txn ? txn.amount : ''}"></div>
      <div class="field"><label>Date</label><input id="tDate" type="date" value="${txn ? txn.txn_date : todayStr()}"></div>
    </div>
    <div class="row">
      <div class="field"><label>Category</label><select id="tCat"></select></div>
      <div class="field"><label>Payment method</label>
        <select id="tPay">${['Cash', 'Card', 'bKash', 'Nagad', 'Bank transfer', 'Other'].map(p => `<option ${txn && txn.payment_method === p ? 'selected' : ''}>${p}</option>`).join('')}</select></div>
    </div>
    <div class="field"><label>Note (optional)</label><textarea id="tNote" rows="2" maxlength="255">${txn ? esc(txn.note) : ''}</textarea></div>
    <div class="field"><label class="check"><input type="checkbox" id="tRec" ${txn && Number(txn.is_recurring) ? 'checked' : ''}> Repeat monthly</label></div>
    <div class="btns"><button class="btn ghost" id="tCancel">Cancel</button><button class="btn" id="tSave">Save</button></div>`);
  const setType = t => {
    type = t;
    $$('.seg button', m).forEach(b => { b.className = b.dataset.t === t ? 'on ' + (t === 'expense' ? 'exp' : 'inc') : ''; });
    $('#tCat', m).innerHTML = catOptions(t, txn && txn.type === t ? txn.category_id : null);
  };
  $$('.seg button', m).forEach(b => b.onclick = () => setType(b.dataset.t));
  setType(type);
  $('#tCancel', m).onclick = closeModal;
  $('#tSave', m).onclick = async () => {
    const body = {
      type, amount: $('#tAmt', m).value, txn_date: $('#tDate', m).value, category_id: $('#tCat', m).value,
      payment_method: $('#tPay', m).value, note: $('#tNote', m).value, is_recurring: $('#tRec', m).checked
    };
    if (!(Number(body.amount) > 0)) { $('#tErr', m).innerHTML = '<div class="form-alert">Enter an amount greater than zero.</div>'; return; }
    $('#tSave', m).disabled = true;
    try {
      const r = txn ? await api('transactions.php?id=' + txn.txn_id, { method: 'PUT', body })
                    : await api('transactions.php', { method: 'POST', body });
      closeModal();
      toast('Transaction saved.');
      budgetToast(r.warning);
      if (window.onTxnSaved) window.onTxnSaved();
    } catch (e) {
      $('#tErr', m).innerHTML = `<div class="form-alert">${esc(e.message)}</div>`;
      $('#tSave', m).disabled = false;
    }
  };
}

function bar(pct) {
  const cls = pct >= 100 ? 'over' : pct >= 80 ? 'warn' : '';
  return `<div class="bar ${cls}"><i style="width:${Math.min(100, pct)}%"></i></div>`;
}
