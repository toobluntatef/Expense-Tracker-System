(async () => {
  const u = await initApp();
  $('#pName').value = u.name; $('#pEmail').value = u.email;
  const msg = (id, text, ok) => $(id).innerHTML = `<div class="${ok ? 'form-ok' : 'form-alert'}">${esc(text)}</div>`;

  $('#pSave').onclick = async () => {
    try {
      await api('profile.php?action=update', { method: 'POST', body: { name: $('#pName').value, email: $('#pEmail').value } });
      msg('#pMsg', 'Profile updated.', true);
      setTimeout(() => location.reload(), 700);
    } catch (e) { msg('#pMsg', e.message); }
  };
  $('#wSave').onclick = async () => {
    try {
      await api('profile.php?action=password', { method: 'POST', body: { current: $('#wCur').value, password: $('#wNew').value, confirm: $('#wNew2').value } });
      msg('#wMsg', 'Password changed.', true);
      $('#wCur').value = $('#wNew').value = $('#wNew2').value = '';
    } catch (e) { msg('#wMsg', e.message); }
  };

  async function cats() {
    await loadCats(true);
    const mine = App.cats.filter(c => !Number(c.is_default));
    $('#cList').innerHTML = mine.length ? mine.map(c => `<div class="item between"><span>${esc(c.name)} <span class="badge">${c.type}</span></span>
      <button class="link-btn del" data-del="${c.category_id}">Delete</button></div>`).join('') : '<p class="muted">You have not added any categories yet.</p>';
  }
  $('#cAdd').onclick = async () => {
    try { await api('categories.php', { method: 'POST', body: { name: $('#cName').value, type: $('#cType').value } }); $('#cName').value = ''; toast('Category added.'); cats(); }
    catch (e) { toast(e.message, 'error'); }
  };
  $('#cList').onclick = async e => {
    const b = e.target.closest('[data-del]');
    if (b && await confirmBox('Delete this category? Transactions using it will become uncategorised.')) {
      try { await api('categories.php?id=' + b.dataset.del, { method: 'DELETE' }); toast('Category deleted.'); cats(); } catch (err) { toast(err.message, 'error'); }
    }
  };
  cats();
})();
