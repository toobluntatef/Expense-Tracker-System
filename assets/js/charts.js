/* Spendly – tiny dependency-free SVG charts */
const PALETTE = ['#2fe0a0', '#5aa9ff', '#ffc233', '#ff6b6b', '#b388ff', '#ff9e5e', '#4dd0e1', '#f06292'];

function tipShow(e, text) {
  let t = $('#tip');
  if (!t) { t = document.createElement('div'); t.id = 'tip'; t.className = 'tip'; document.body.appendChild(t); }
  t.textContent = text; t.style.left = (e.clientX + 12) + 'px'; t.style.top = (e.clientY + 12) + 'px';
}
function tipHide() { const t = $('#tip'); if (t) t.remove(); }

function niceMax(v) {
  if (v <= 0) return 10;
  const p = Math.pow(10, Math.floor(Math.log10(v)));
  const n = v / p;
  return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 5 ? 5 : 10) * p;
}

/* Line chart: points = [{label, value}] */
function lineChart(el, points) {
  const W = 560, H = 240, L = 46, R = 12, T = 12, B = 28;
  if (!points.length || points.every(p => p.value === 0)) { el.innerHTML = '<div class="empty">No spending in the last 30 days.</div>'; return; }
  const max = niceMax(Math.max(...points.map(p => p.value)));
  const x = i => L + (points.length === 1 ? 0 : i * (W - L - R) / (points.length - 1));
  const y = v => T + (H - T - B) * (1 - v / max);
  let g = '';
  for (let i = 0; i <= 4; i++) {
    const v = max * i / 4;
    g += `<line x1="${L}" x2="${W - R}" y1="${y(v)}" y2="${y(v)}" stroke="#1f2c47"/><text x="${L - 6}" y="${y(v) + 4}" fill="#8a99b8" font-size="11" text-anchor="end">${v >= 1000 ? (v / 1000) + 'k' : Math.round(v)}</text>`;
  }
  const step = Math.ceil(points.length / 6);
  points.forEach((p, i) => { if (i % step === 0 || i === points.length - 1) g += `<text x="${x(i)}" y="${H - 8}" fill="#8a99b8" font-size="11" text-anchor="middle">${esc(p.label)}</text>`; });
  const path = points.map((p, i) => (i ? 'L' : 'M') + x(i).toFixed(1) + ' ' + y(p.value).toFixed(1)).join(' ');
  const area = path + ` L${x(points.length - 1)} ${y(0)} L${x(0)} ${y(0)} Z`;
  el.innerHTML = `<svg viewBox="0 0 ${W} ${H}"><defs><linearGradient id="lg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2fe0a0" stop-opacity=".35"/><stop offset="1" stop-color="#2fe0a0" stop-opacity="0"/></linearGradient></defs>
    ${g}<path d="${area}" fill="url(#lg)"/><path d="${path}" fill="none" stroke="#2fe0a0" stroke-width="2.5" stroke-linejoin="round"/>
    ${points.map((p, i) => `<circle class="pt" data-i="${i}" cx="${x(i)}" cy="${y(p.value)}" r="${p.value ? 4 : 0}" fill="#2fe0a0"/>`).join('')}
    ${points.map((p, i) => `<rect class="hit" data-i="${i}" x="${x(i) - (W - L - R) / points.length / 2}" y="${T}" width="${(W - L - R) / points.length}" height="${H - T - B}" fill="transparent"/>`).join('')}</svg>`;
  $$('.hit', el).forEach(r => {
    r.onmousemove = e => { const p = points[r.dataset.i]; tipShow(e, `${p.full || p.label}: ${money(p.value)}`); };
    r.onmouseleave = tipHide;
  });
}

/* Donut chart: items = [{name, total}] */
function donutChart(el, items) {
  if (!items.length) { el.innerHTML = '<div class="empty">No expenses this month yet.</div>'; return; }
  const total = items.reduce((s, i) => s + Number(i.total), 0);
  const R = 70, r = 46, C = 90;
  let a0 = -Math.PI / 2, paths = '';
  items.forEach((it, i) => {
    const frac = Number(it.total) / total;
    const a1 = a0 + frac * Math.PI * 2 - (frac === 1 ? 0.0001 : 0);
    const big = a1 - a0 > Math.PI ? 1 : 0;
    const p = (rad, a) => [C + rad * Math.cos(a), C + rad * Math.sin(a)];
    const [x0, y0] = p(R, a0), [x1, y1] = p(R, a1), [x2, y2] = p(r, a1), [x3, y3] = p(r, a0);
    paths += `<path class="slice" data-i="${i}" d="M${x0} ${y0} A${R} ${R} 0 ${big} 1 ${x1} ${y1} L${x2} ${y2} A${r} ${r} 0 ${big} 0 ${x3} ${y3} Z" fill="${PALETTE[i % PALETTE.length]}"/>`;
    a0 += frac * Math.PI * 2;
  });
  el.innerHTML = `<div class="donut-wrap"><svg viewBox="0 0 180 180" style="width:170px;flex:none">${paths}
      <text x="90" y="86" text-anchor="middle" fill="#8a99b8" font-size="10">Total</text>
      <text x="90" y="104" text-anchor="middle" fill="#e8eefc" font-size="13" font-weight="700">${money(total)}</text></svg>
    <div class="legend">${items.map((it, i) => `<span><i style="background:${PALETTE[i % PALETTE.length]}"></i>${esc(it.name)} <b>${Math.round(it.total / total * 100)}%</b></span>`).join('')}</div></div>`;
  $$(".slice", el).forEach(p => {
    const it = items[p.dataset.i];
    p.onmousemove = e => tipShow(e, `${it.name}: ${money(it.total)}`);
    p.onmouseleave = tipHide;
  });
}

/* Grouped bar chart: rows = [{label, a, b}] (a = income, b = expense) */
function barChart(el, rows) {
  const W = 560, H = 250, L = 50, R = 10, T = 12, B = 40;
  if (!rows.length || rows.every(r => !r.a && !r.b)) { el.innerHTML = '<div class="empty">No data for this period.</div>'; return; }
  const max = niceMax(Math.max(...rows.flatMap(r => [r.a, r.b])));
  const y = v => T + (H - T - B) * (1 - v / max);
  const gw = (W - L - R) / rows.length, bw = Math.min(26, gw / 3);
  let g = '';
  for (let i = 0; i <= 4; i++) { const v = max * i / 4; g += `<line x1="${L}" x2="${W - R}" y1="${y(v)}" y2="${y(v)}" stroke="#1f2c47"/><text x="${L - 6}" y="${y(v) + 4}" fill="#8a99b8" font-size="11" text-anchor="end">${v >= 1000 ? (v / 1000) + 'k' : Math.round(v)}</text>`; }
  rows.forEach((r, i) => {
    const cx = L + gw * i + gw / 2;
    g += `<rect class="b" data-t="${esc(r.label)} income: ${money(r.a)}" x="${cx - bw - 2}" y="${y(r.a)}" width="${bw}" height="${Math.max(0, y(0) - y(r.a))}" rx="4" fill="#2fe0a0"/>
          <rect class="b" data-t="${esc(r.label)} expenses: ${money(r.b)}" x="${cx + 2}" y="${y(r.b)}" width="${bw}" height="${Math.max(0, y(0) - y(r.b))}" rx="4" fill="#ff6b6b"/>
          <text x="${cx}" y="${H - 22}" fill="#8a99b8" font-size="11" text-anchor="middle">${esc(r.label)}</text>`;
  });
  g += `<g font-size="11" fill="#8a99b8"><rect x="${W / 2 - 70}" y="${H - 12}" width="9" height="9" fill="#2fe0a0" rx="2"/><text x="${W / 2 - 57}" y="${H - 4}">Income</text><rect x="${W / 2 + 10}" y="${H - 12}" width="9" height="9" fill="#ff6b6b" rx="2"/><text x="${W / 2 + 23}" y="${H - 4}">Expenses</text></g>`;
  el.innerHTML = `<svg viewBox="0 0 ${W} ${H}">${g}</svg>`;
  $$('.b', el).forEach(b => { b.onmousemove = e => tipShow(e, b.dataset.t); b.onmouseleave = tipHide; });
}
