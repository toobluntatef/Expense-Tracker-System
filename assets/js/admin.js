(async () => {
  await initApp({ admin: true });
  let timer;

  async function stats() {
    const s = await api('admin.php?action=stats');
    $('#aU').textContent = s.users; $('#aA').textContent = s.active_today; $('#aT').textContent = s.transactions.toLocaleString();
  }
  async function users() {
    const r = await api('admin.php?action=users&' + qs({ q: $('#aq').value.trim() }));
    $('#uRows').innerHTML = r.users.length ? r.users.map(u => {
      const self = Number(u.user_id) === Number(App.user.user_id);
      return `<tr><td>${esc(u.name)}</td><td>${esc(u.email)}</td>
        <td>${u.role === 'admin' ? 'Admin' : 'User'}</td>
        <td><span class="badge ${u.status === 'active' ? 'ok' : 'off'}">${u.status === 'active' ? 'Active' : 'Inactive'}</span></td>
        <td class="muted">${u.last_login ? esc(u.last_login) : 'Never'}</td>
        <td>${self ? '<span class="muted">(you)</span>' : `
          <button class="link-btn ${u.status === 'active' ? 'del' : ''}" data-id="${u.user_id}" data-to="${u.status === 'active' ? 'inactive' : 'active'}">${u.status === 'active' ? 'Deactivate' : 'Activate'}</button>
          | <button class="link-btn" data-reset="${u.user_id}" data-name="${esc(u.name)}">Reset password</button>`}</td></tr>`;
    }).join('') : '<tr><td colspan="6" class="empty">No users found.</td></tr>';
  }
  async function defaults() {
    const r = await api('admin.php?action=categories');
    $('#dList').innerHTML = r.categories.map(c => `<div class="item between"><span>${esc(c.name)} <span class="badge">${c.type}</span></span>
      <span><button class="link-btn" data-ren="${c.category_id}" data-name="${esc(c.name)}">Rename</button>|<button class="link-btn del" data-del="${c.category_id}">Delete</button></span></div>`).join('');
  }
  const run = fn => fn().catch(e => toast(e.message, 'error'));

  $('#aq').oninput = () => { clearTimeout(timer); timer = setTimeout(() => run(users), 300); };
  $('#uRows').onclick = async e => {
    const b = e.target.closest('[data-id]'), r = e.target.closest('[data-reset]');
    if (b) {
      try { await api('admin.php?action=set_status', { method: 'POST', body: { user_id: b.dataset.id, status: b.dataset.to } }); toast('User updated.'); users(); }
      catch (err) { toast(err.message, 'error'); }
    }
    if (r) {
      const m = openModal(`<h2>Reset password for ${esc(r.dataset.name)}</h2><div id="rErr"></div>
        <div class="field"><label>New temporary password (min. 8 characters)</label><input id="rPw" type="text" autocomplete="off"></div>
        <div class="btns"><button class="btn ghost" id="rNo">Cancel</button><button class="btn" id="rYes">Reset</button></div>`);
      $('#rNo', m).onclick = closeModal;
      $('#rYes', m).onclick = async () => {
        try { await api('admin.php?action=reset_password', { method: 'POST', body: { user_id: r.dataset.reset, password: $('#rPw', m).value } }); closeModal(); toast('Password reset. Tell the user their new password.'); }
        catch (err) { $('#rErr', m).innerHTML = `<div class="form-alert">${esc(err.message)}</div>`; }
      };
    }
  };
  $('#dAdd').onclick = async () => {
    try { await api('admin.php?action=categories', { method: 'POST', body: { name: $('#dName').value, type: $('#dType').value } }); $('#dName').value = ''; toast('Default category added.'); defaults(); }
    catch (e) { toast(e.message, 'error'); }
  };
  $('#dList').onclick = async e => {
    const d = e.target.closest('[data-del]'), n = e.target.closest('[data-ren]');
    if (d && await confirmBox('Delete this default category? It will also disappear from every user’s budgets, and their transactions in it become uncategorised.')) {
      try { await api('admin.php?action=categories&id=' + d.dataset.del, { method: 'DELETE' }); toast('Category deleted.'); defaults(); } catch (err) { toast(err.message, 'error'); }
    }
    if (n) {
      const m = openModal(`<h2>Rename category</h2><div class="field"><input id="nName" maxlength="60" value="${esc(n.dataset.name)}"></div>
        <div class="btns"><button class="btn ghost" id="nNo">Cancel</button><button class="btn" id="nYes">Save</button></div>`);
      $('#nNo', m).onclick = closeModal;
      $('#nYes', m).onclick = async () => {
        try { await api('admin.php?action=categories&id=' + n.dataset.ren, { method: 'PUT', body: { name: $('#nName', m).value } }); closeModal(); toast('Category renamed.'); defaults(); }
        catch (err) { toast(err.message, 'error'); }
      };
    }
  };
  run(stats); run(users); run(defaults);
})();
