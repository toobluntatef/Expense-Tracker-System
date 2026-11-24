(async () => {
  await initApp();
  $('#rm').value = thisMonth();

  async function load() {
    const month = $('#rm').value || thisMonth();
    const r = await api('reports.php?month=' + month);
    $('#rLabel').textContent = monthLabel(month);
    $('#sum').innerHTML = [
      ['Total income', `<b class="green">${money(r.income)}</b>`],
      ['Total expenses', `<b class="red">${money(r.expense)}</b>`],
      ['Net savings', `<b class="${r.net < 0 ? 'red' : 'green'}">${money(r.net)}</b>`],
      ['Top category', `<b>${esc(r.top_category)}</b>`],
      ['Avg. daily spend', `<b>${money(r.avg_daily)}</b>`]
    ].map(([k, v]) => `<div class="item between"><span class="muted">${k}</span>${v}</div>`).join('');
    barChart($('#bars'), r.series.map(s => ({ label: monthLabel(s.month).split(' ')[0], a: Number(s.income), b: Number(s.expense) })));
    donutChart($('#cats'), r.categories.map(c => ({ name: c.name, total: Number(c.total) })));
  }
  const reload = () => load().catch(e => toast(e.message, 'error'));
  window.onTxnSaved = reload;
  $('#gen').onclick = reload;
  $('#rm').onchange = reload;
  $('#csv').onclick = () => { location.href = 'api/reports.php?action=csv&month=' + ($('#rm').value || thisMonth()); };
  $('#pdf').onclick = () => window.print(); // choose "Save as PDF" in the print dialog
  reload();
})();
