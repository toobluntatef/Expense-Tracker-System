(async () => {
  await initApp();
  const st = { page: 1, per: 10, sort: 'date', dir: 'desc' };
  let rows = [];

  $('#fcat').innerHTML = '<option value="">All</option>' +
    ['expense', 'income'].map(t => `<optgroup label="${t === 'expense' ? 'Expense' : 'Income'}">${catOptions(t)}</optgroup>`).join('');

  const filters = () => ({
    q: $('#fq').value.trim(), category_id: $('#fcat').value, type: $('#ftype').value,
    from: $('#ffrom').value, to: $('#fto').value, min: $('#fmin').value, max: $('#fmax').value
  });

  async function load() {
    const r = await api('transactions.php?' + qs({ ...filters(), page: st.page, per: st.per, sort: st.sort, dir: st.dir }));
    rows = r.transactions;
    $('#rows').innerHTML = rows.length ? rows.map(t => `
      <tr>
        <td>${fmtDate(t.txn_date)}</td>
        <td>${esc(t.note || '–')} ${Number(t.is_recurring) || t.parent_id ? '<span class="badge" title="Repeats monthly">↻</span>' : ''}</td>
        <td><span class="badge">${esc(t.category || '–')}</span></td>
        <td class="num ${t.type === 'income' ? 'amt-in' : 'amt-out'}">${t.type === 'income' ? '+' : '-'}${money(t.amount)}</td>
        <td><button class="link-btn" data-edit="${t.txn_id}">Edit</button>|<button class="link-btn del" data-del="${t.txn_id}">Delete</button></td>
      </tr>`).join('') : '<tr><td colspan="5" class="empty">No transactions match your filters.</td></tr>';

    const pages = Math.max(1, Math.ceil(r.total / st.per));
    $('#count').textContent = r.total ? `Showing ${(st.page - 1) * st.per + 1}–${Math.min(st.page * st.per, r.total)} of ${r.total}` : '0 results';
    let pg = `<button class="btn ghost sm" data-p="${st.page - 1}" ${st.page <= 1 ? 'disabled' : ''}>‹</button>`;
    const from = Math.max(1, st.page - 2), to = Math.min(pages, st.page + 2);
    for (let i = from; i <= to; i++) pg += `<button class="btn sm ${i === st.page ? '' : 'ghost'}" data-p="${i}">${i}</button>`;
    pg += `<button class="btn ghost sm" data-p="${st.page + 1}" ${st.page >= pages ? 'disabled' : ''}>›</button>`;
    $('#pg').innerHTML = pg;

    $$('th.sortable').forEach(th => th.dataset.arrow = th.dataset.sort === st.sort ? (st.dir === 'asc' ? ' ▲' : ' ▼') : '');
    $$('th.sortable').forEach(th => th.textContent = th.textContent.replace(/ [▲▼]$/, '') + th.dataset.arrow);
  }
  const reload = () => load().catch(e => toast(e.message, 'error'));
  window.onTxnSaved = reload;

  $('#fapply').onclick = () => { st.page = 1; reload(); };
  $('#fq').addEventListener('keydown', e => { if (e.key === 'Enter') { st.page = 1; reload(); } });
  $('#freset').onclick = () => { $$('.filters input').forEach(i => i.value = ''); $$('.filters select').forEach(s => s.value = ''); st.page = 1; reload(); };
  $$('th.sortable').forEach(th => th.onclick = () => {
    if (st.sort === th.dataset.sort) st.dir = st.dir === 'asc' ? 'desc' : 'asc'; else { st.sort = th.dataset.sort; st.dir = 'desc'; }
    st.page = 1; reload();
  });
  $('#pg').onclick = e => { const b = e.target.closest('button[data-p]'); if (b && !b.disabled) { st.page = Number(b.dataset.p); reload(); } };
  $('#rows').onclick = async e => {
    const ed = e.target.closest('[data-edit]'), del = e.target.closest('[data-del]');
    if (ed) openTxnModal(rows.find(r => r.txn_id == ed.dataset.edit));
    if (del && await confirmBox('Delete this transaction? This cannot be undone.')) {
      try { await api('transactions.php?id=' + del.dataset.del, { method: 'DELETE' }); toast('Transaction deleted.'); reload(); }
      catch (err) { toast(err.message, 'error'); }
    }
  };
  reload();
})();
