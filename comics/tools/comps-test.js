// The comps button: the URL it builds, the "checked" stamp round-tripping to the
// server, and the stale-value nudge on the dashboard.
const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
  const p = await b.newPage({ viewport: { width: 1340, height: 1100 }, deviceScaleFactor: 2 });
  const errs = []; p.on('pageerror', e => errs.push(e.message));
  await p.goto('http://127.0.0.1:8899/app/', { waitUntil: 'networkidle' });
  await p.fill('#loginUser','Thanos'); await p.fill('#loginPass','Cra3653793!@#'); await p.click('#loginBtn');
  await p.waitForFunction(()=>!document.getElementById('loginScreen').classList.contains('open'));
  await p.waitForTimeout(900);

  // the URL, built from the book
  const urls = await p.evaluate(() => {
    const f = window.__ctComps.compsUrl;
    return [
      f({ series: 'The Amazing Spider-Man', issue: '300', character: 'Spider-Man' }),
      f({ series: '', issue: '', character: 'Batman' }),
      f({ series: 'Love & Rockets', issue: '1.5', character: '' }),
      f({ series: '', issue: '', character: '' })
    ];
  });
  urls.forEach(u => console.log('   ' + u));
  const ok = urls.every(u => u.startsWith('https://www.ebay.com/sch/i.html?_nkw=') && u.includes('LH_Sold=1') && u.includes('LH_Complete=1') && u.includes('_sop=13'));
  const encoded = urls[2].includes('Love%20%26%20Rockets') || urls[2].includes('Love+%26+Rockets') || urls[2].includes('%26');
  console.log('all four are well-formed sold searches: ' + ok + ' | ampersands encoded: ' + encoded);

  // open a book, check the row
  await p.click('#viewWrap .bcard-main');
  await p.waitForSelector('#detailModal.open');
  const href = await p.getAttribute('.btn-comps', 'href');
  console.log('button on the detail: ' + href.slice(0, 96) + '…');
  console.log('opens in a new tab, no referrer leak: ' + await p.evaluate(() => {
    const a = document.querySelector('.btn-comps');
    return a.target === '_blank' && a.rel.includes('noopener') && a.rel.includes('noreferrer');
  }));
  console.log('note before: "' + (await p.textContent('.comps-note')).trim() + '"');

  // mark checked -> server stamps a date -> it survives a reload
  await p.click('.btn-checked');
  await p.waitForTimeout(1200);
  await p.reload({ waitUntil: 'networkidle' });
  await p.waitForTimeout(1200);
  await p.click('#viewWrap .bcard-main');
  await p.waitForSelector('#detailModal.open');
  console.log('note after marking + reload: "' + (await p.textContent('.comps-note')).trim() + '"');
  const stamp = await p.evaluate(() => {
    const id = document.getElementById('detailModal').getAttribute('data-id');
    const raw = JSON.parse(localStorage.getItem('comicTracker.v1'));
    const b = raw.books.find(x => x.client_id === id);
    return { checked: b.value_checked, flagCleared: !b.valueChecked };
  });
  console.log('server stamped: ' + stamp.checked + ' | the request flag was cleared: ' + stamp.flagCleared);

  // every wording branch, including the stale one
  const ago = d => new Date(Date.now() - d * 86400000).toISOString().slice(0, 19).replace('T', ' ');
  const notes = await p.evaluate(stamps => {
    const strip = h => { const d = document.createElement('div'); d.innerHTML = h; return {
      note: d.querySelector('.comps-note').textContent.trim(),
      stale: d.querySelector('.comps-note').classList.contains('is-stale') }; };
    return {
      today:      strip(window.__ctComps.compsRow({ client_id: 'x', value: '100', value_checked: stamps.today })),
      recent:     strip(window.__ctComps.compsRow({ client_id: 'x', value: '100', value_checked: stamps.recent })),
      old:        strip(window.__ctComps.compsRow({ client_id: 'x', value: '100', value_checked: stamps.old })),
      neverValue: strip(window.__ctComps.compsRow({ client_id: 'x', value: '100', value_checked: '' })),
      neverNone:  strip(window.__ctComps.compsRow({ client_id: 'x', value: '', value_checked: '' }))
    };
  }, { today: ago(0), recent: ago(30), old: ago(400) });
  Object.entries(notes).forEach(([k, v]) => console.log('   ' + k.padEnd(11) + (v.stale ? '[stale] ' : '        ') + v.note));
  const wording = notes.today.note === 'Checked today.' && !notes.today.stale
    && notes.recent.note.includes('30 days ago') && !notes.recent.stale
    && notes.old.stale && notes.old.note.includes('worth a look')
    && notes.neverValue.note.includes('never checked')
    && notes.neverNone.note.includes('No value recorded');
  console.log('all five wordings correct, stale flagged only past 180 days: ' + wording);

  // and the edit form carries the same link
  await p.click('#detailEditBtn'); await p.waitForSelector('#bookModal.open');
  console.log('form link: ' + (await p.getAttribute('#fCompsLink','href')).slice(0, 70) + '…');
  console.log(errs.length ? 'JS ERRORS: ' + errs.join('; ') : 'no JS errors');
  await b.close();
})();
