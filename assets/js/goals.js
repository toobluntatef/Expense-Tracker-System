(async () => {
  await initApp();
  let goals = [];

  async function load() {
    goals = (await api('goals.php')).goals;
    $('#list').innerHTML = goals.length ? goals.map(g => {
      const saved = Number(g.saved_amount), target = Number(g.target_amount);
      const pct = Math.min(100, Math.round(saved / target * 100));
      return `<div class="card">
        <div class="between"><h3 style="margin:0">${esc(g.title)}</h3>${pct >= 100 ? '<span class="badge ok">Reached 🎉</span>' : ''}</div>
        <p style="margin:10px 0 8px">${money(saved)} <span class="muted">/ ${money(target)}</span> <b class="green">${pct}%</b></p>
        <div class="bar"><i style="width:${pct}%"></i></div>
        <p class="muted" style="font-size:13px;margin-top:8px">${g.target_date ? 'Target date: ' + fmtDate(g.target_date) : 'No target date'}${pct < 100 ? ' · ' + money(target - saved) + ' to go' : ''}</p>
        <div class="actions" style="margin-top:12px"><button class="btn sm" data-add="${g.goal_id}">+ Add money</button>
          <button class="link-btn" data-edit="${g.goal_id}">Edit</button>|<button class="link-btn del" data-del="${g.goal_id}">Delete</button></div>
      </div>`;
    }).join('') : '<div class="card empty" style="grid-column:1/-1">No goals yet. Create one, like “New Laptop”.</div>';
  }
  const reload = () => load().catch(e => toast(e.message, 'error'));
  window.onTxnSaved = reload;

  function goalModal(g = null) {
    const m = openModal(`<h2>${g ? 'Edit' : 'New'} Savings Goal</h2><div id="gErr"></div>
      <div class="field"><label>Title</label><input id="gT" maxlength="100" placeholder="e.g. New Laptop" value="${g ? esc(g.title) : ''}"></div>
      <div class="row"><div class="field"><label>Target (${CUR})</label><input id="gA" type="number" min="1" step="0.01" value="${g ? g.target_amount : ''}"></div>
      <div class="field"><label>Target date (optional)</label><input id="gD" type="date" value="${g && g.target_date ? g.target_date : ''}"></div></div>
      <div class="btns"><button class="btn ghost" id="gNo">Cancel</button><button class="btn" id="gYes">Save</button></div>`);
    $('#gNo', m).onclick = closeModal;
    $('#gYes', m).onclick = async () => {
      const body = { title: $('#gT', m).value, target_amount: $('#gA', m).value, target_date: $('#gD', m).value };
      try {
        await api(g ? 'goals.php?id=' + g.goal_id : 'goals.php', { method: g ? 'PUT' : 'POST', body });
        closeModal(); toast('Goal saved.'); reload();
      } catch (e) { $('#gErr', m).innerHTML = `<div class="form-alert">${esc(e.message)}</div>`; }
    };
  }
  function moneyModal(g) {
    const m = openModal(`<h2>Add money to “${esc(g.title)}”</h2><div id="aErr"></div>
      <div class="field"><label>Amount (${CUR}) – use a negative number to withdraw</label><input id="aAmt" type="number" step="0.01" placeholder="0.00"></div>
      <div class="btns"><button class="btn ghost" id="aNo">Cancel</button><button class="btn" id="aYes">Add</button></div>`);
    $('#aNo', m).onclick = closeModal;
    $('#aYes', m).onclick = async () => {
      try {
        await api('goals.php?action=add_money', { method: 'POST', body: { goal_id: g.goal_id, amount: $('#aAmt', m).value } });
        closeModal(); toast('Savings updated.'); reload();
      } catch (e) { $('#aErr', m).innerHTML = `<div class="form-alert">${esc(e.message)}</div>`; }
    };
  }
  $('#addGoal').onclick = () => goalModal();
  $('#list').onclick = async e => {
    const a = e.target.closest('[data-add]'), ed = e.target.closest('[data-edit]'), del = e.target.closest('[data-del]');
    if (a) moneyModal(goals.find(g => g.goal_id == a.dataset.add));
    if (ed) goalModal(goals.find(g => g.goal_id == ed.dataset.edit));
    if (del && await confirmBox('Delete this goal and its saved progress?')) {
      try { await api('goals.php?id=' + del.dataset.del, { method: 'DELETE' }); toast('Goal deleted.'); reload(); } catch (err) { toast(err.message, 'error'); }
    }
  };
  reload();
})();
