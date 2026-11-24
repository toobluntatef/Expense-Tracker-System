(async () => {
  await initApp();
  $('#bm').value = thisMonth();

  async function load() {
    const month = $('#bm').value || thisMonth();
    const r = await api('budgets.php?month=' + month);
    $('#list').innerHTML = r.budgets.length ? r.budgets.map(b => `
      <div class="item">
        <div class="top"><b>${esc(b.category)}</b>
          <span>${money(b.spent)} / ${money(b.limit_amount)} <b class="${b.percent >= 100 ? 'red' : b.percent >= 80 ? 'yellow' : 'green'}">${b.percent}%</b></span></div>
        ${bar(b.percent)}
        ${b.percent >= 100 ? '<div class="red" style="font-size:13px;margin-top:6px">Over budget by ' + money(b.spent - b.limit_amount) + '</div>'
          : b.percent >= 80 ? '<div class="yellow" style="font-size:13px;margin-top:6px">Approaching your limit – ' + money(b.limit_amount - b.spent) + ' left</div>' : ''}
        <div class="actions"><button class="link-btn" data-edit="${b.category_id}" data-limit="${b.limit_amount}">Edit</button>|<button class="link-btn del" data-del="${b.budget_id}">Remove</button></div>
      </div>`).join('') : `<div class="empty">No budgets for ${monthLabel(month)} yet. Click “+ Add / Update Budget” to set one.</div>`;
  }
  const reload = () => load().catch(e => toast(e.message, 'error'));
  window.onTxnSaved = reload;

  function budgetModal(catId = '', limit = '') {
    const m = openModal(`<h2>Set Monthly Budget</h2><div id="bErr"></div>
      <div class="field"><label>Month</label><input id="mMonth" type="month" value="${$('#bm').value}"></div>
      <div class="field"><label>Category</label><select id="mCat">${catOptions('expense', catId)}</select></div>
      <div class="field"><label>Limit (${CUR})</label><input id="mLimit" type="number" min="1" step="0.01" value="${limit}"></div>
      <p class="muted" style="font-size:13px;margin-bottom:12px">Saving again for the same category and month updates its limit.</p>
      <div class="btns"><button class="btn ghost" id="mNo">Cancel</button><button class="btn" id="mYes">Save</button></div>`);
    $('#mNo', m).onclick = closeModal;
    $('#mYes', m).onclick = async () => {
      try {
        await api('budgets.php', { method: 'POST', body: { month: $('#mMonth', m).value, category_id: $('#mCat', m).value, limit_amount: $('#mLimit', m).value } });
        $('#bm').value = $('#mMonth', m).value; closeModal(); toast('Budget saved.'); reload();
      } catch (e) { $('#bErr', m).innerHTML = `<div class="form-alert">${esc(e.message)}</div>`; }
    };
  }
  $('#addBudget').onclick = () => budgetModal();
  $('#bm').onchange = reload;
  $('#list').onclick = async e => {
    const ed = e.target.closest('[data-edit]'), del = e.target.closest('[data-del]');
    if (ed) budgetModal(ed.dataset.edit, ed.dataset.limit);
    if (del && await confirmBox('Remove this budget?')) {
      try { await api('budgets.php?id=' + del.dataset.del, { method: 'DELETE' }); toast('Budget removed.'); reload(); } catch (err) { toast(err.message, 'error'); }
    }
  };
  reload();
})();
