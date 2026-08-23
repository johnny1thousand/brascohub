// "Main characters": the bars, their scaling, and that a row filters the shelf.
const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
  const p = await b.newPage({ viewport: { width: 1340, height: 1200 }, deviceScaleFactor: 2 });
  const errs = []; p.on('pageerror', e => errs.push(e.message));
  await p.goto('http://127.0.0.1:8899/app/', { waitUntil: 'networkidle' });
  await p.fill('#loginUser','Thanos'); await p.fill('#loginPass','Cra3653793!@#'); await p.click('#loginBtn');
  await p.waitForFunction(()=>!document.getElementById('loginScreen').classList.contains('open'));
  await p.waitForTimeout(900);

  const rows = await p.$$eval('.cbar', rs => rs.map(r => ({
    name: r.querySelector('.cbar-name').textContent,
    n: parseInt(r.querySelector('.cbar-n').textContent),
    width: parseFloat(r.querySelector('.cbar-fill').style.width),
    title: r.getAttribute('title')
  })));
  console.log('rows: ' + rows.length + ' (cap is 5)');
  rows.forEach(r => console.log('   ' + r.name.padEnd(14) + String(r.n).padStart(3) + '  bar ' + r.width + '%  "' + r.title + '"'));

  // biggest first, and the bars are scaled to the biggest character
  const sorted = rows.every((r, i) => i === 0 || rows[i-1].n >= r.n);
  const topFull = rows[0].width === 100;
  const scaled = rows.every(r => Math.abs(r.width - Math.max(4, Math.round(r.n / rows[0].n * 100))) < 0.6);
  console.log('sorted biggest first: ' + sorted + ' | top bar is full width: ' + topFull + ' | every bar scaled to it: ' + scaled);

  const card = await p.locator('.chart-card').boundingBox();
  console.log('card is ' + Math.round(card.width) + 'x' + Math.round(card.height) + 'px | no key present: ' + (await p.locator('.legend, .lg-row').count() === 0));

  // a row filters the shelf, and clicking it again clears
  const before = await p.locator('#viewWrap .bcard').count();
  await p.click('.cbar');
  await p.waitForTimeout(500);
  console.log('clicked "' + rows[0].name + '" -> filter "' + await p.inputValue('#filterChar') + '", cards ' + before + ' -> ' + await p.locator('#viewWrap .bcard').count());
  await p.click('.cbar');
  await p.waitForTimeout(400);
  console.log('clicking it again clears the filter: ' + ((await p.inputValue('#filterChar')) === ''));

  // it stays out of the grouped views
  for (const v of ['characters','series']) {
    await p.click(`.seg[data-view="${v}"]`); await p.waitForTimeout(300);
    console.log(v + ' view shows the card: ' + await p.locator('.chart-card').count());
  }
  console.log(errs.length ? 'JS ERRORS: ' + errs.join('; ') : 'no JS errors');
  await b.close();
})();
