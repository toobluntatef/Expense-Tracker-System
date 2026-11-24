(async () => {
  await initApp();
  async function load() {
    const d = await api('dashboard.php');
    $('#sBal').textContent = money(d.balance);
    $('#sBal').className = 'value ' + (d.balance < 0 ? 'red' : '');
    $('#sInc').textContent = money(d.income);
    $('#sExp').textContent = money(d.expense);
    $('#sRate').textContent = d.income > 0 ? d.rate + '%' : '–';

    $('#alerts').innerHTML = d.alerts.map(a => a.percent >= 100
      ? `<div class="alert over">⚠ <span>Budget exceeded for <b>${esc(a.category)}</b>: ${money(a.spent)} of ${money(a.limit)} (${a.percent}%).</span></div>`
      : `<div class="alert warn">⚠ <span>You have used <b>${a.percent}%</b> of your <b>${esc(a.category)}</b> budget (${money(a.spent)} of ${money(a.limit)}).</span></div>`).join('');

    lineChart($('#trend'), d.trend.map(t => ({
      label: t.date.slice(8) + '/' + t.date.slice(5, 7), full: fmtDate(t.date), value: Number(t.total)
    })));
    donutChart($('#cats'), d.categories.map(c => ({ name: c.name, total: Number(c.total) })));

    $('#recent').innerHTML = d.recent.length ? d.recent.map(t => `
      <div class="item between">
        <span>${esc(t.note || t.category || 'Transaction')} <span class="muted">· ${esc(t.category || '')} · ${fmtDate(t.txn_date)}</span></span>
        <span class="${t.type === 'income' ? 'amt-in' : 'amt-out'}">${t.type === 'income' ? '+' : '-'}${money(t.amount)}</span>
      </div>`).join('') : '<div class="empty">No transactions yet. Click “+ Add Expense” to record your first one.</div>';
  }
  window.onTxnSaved = load;
  load().catch(e => toast(e.message, 'error'));
})();
