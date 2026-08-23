// The character donut: geometry, the cap at four characters plus Other, the
// legend numbers, and that a slice filters the shelf.
const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
  const p = await b.newPage({ viewport: { width: 1340, height: 1100 }, deviceScaleFactor: 2 });
  const errs = []; p.on('pageerror', e => errs.push(e.message));
  await p.goto('http://127.0.0.1:8899/app/', { waitUntil: 'networkidle' });
  await p.fill('#loginUser','Thanos'); await p.fill('#loginPass','Cra3653793!@#'); await p.click('#loginBtn');
  await p.waitForFunction(()=>!document.getElementById('loginScreen').classList.contains('open'));
  await p.waitForTimeout(900);

  const legend = await p.$$eval('.lg-row', rs => rs.map(r => r.textContent.replace(/\s+/g,' ').trim()));
  console.log('legend rows:'); legend.forEach(l => console.log('   ' + l));
  const slices = await p.$$eval('.donut .slice', ps => ps.map(x => ({ fill: x.getAttribute('fill'), char: x.getAttribute('data-char'), title: x.querySelector('title').textContent })));
  console.log('slices: ' + slices.length);
  slices.forEach(s => console.log('   ' + s.fill + '  ' + s.title + (s.char ? '  -> ' + s.char : '  (not a filter)')));

  // the arithmetic: percentages must sum to ~100 and the centre must be the total
  const sums = await p.evaluate(() => {
    const pct = Array.from(document.querySelectorAll('.lg-pct')).map(e => parseInt(e.textContent));
    const n = Array.from(document.querySelectorAll('.lg-n')).map(e => parseInt(e.textContent));
    return { pct: pct.reduce((a,b)=>a+b,0), counts: n.reduce((a,b)=>a+b,0), centre: parseInt(document.querySelector('.donut-num').textContent) };
  });
  console.log('percentages sum to ' + sums.pct + '% | slice counts ' + sums.counts + ' | centre ' + sums.centre + ' | agree: ' + (sums.counts === sums.centre));

  // every slice must be a closed path with a real sweep
  const geom = await p.evaluate(() => Array.from(document.querySelectorAll('.donut .slice')).map(s => ({ len: Math.round(s.getTotalLength()), closed: s.getAttribute('d').trim().endsWith('Z') })));
  console.log('paths closed: ' + geom.every(g => g.closed) + ' | all have length: ' + geom.every(g => g.len > 20));

  // clicking a slice filters, clicking again clears
  const before = await p.locator('#viewWrap .bcard').count();
  // click a point ON the ring, not the centre of the path's bounding box (that
  // lands in the hole)
  const at = await p.evaluate(() => {
    const s = document.querySelector('.donut .slice[data-char]:not([data-char=""])');
    // mid-ring point of this slice: half way round its sweep, half way between
    // the inner and outer radius
    const box0 = s.getBBox();
    const mid = s.getPointAtLength(s.getTotalLength() * 0.5);
    const cx = 80, cy = 80, R = (68 + 42) / 2;
    const a = Math.atan2(mid.x - cx, cy - mid.y);
    const pt = { x: cx + R * Math.sin(a), y: cy - R * Math.cos(a) };
    const box = document.querySelector('.donut').getBoundingClientRect();
    return { x: box.left + (pt.x / 160) * box.width, y: box.top + (pt.y / 160) * box.height };
  });
  await p.mouse.click(at.x, at.y);
  await p.waitForTimeout(500);
  const after = await p.locator('#viewWrap .bcard').count();
  const filt = await p.inputValue('#filterChar');
  console.log('clicked the biggest slice -> filter "' + filt + '", cards ' + before + ' -> ' + after);
  await p.click('.lg-row--go');
  await p.waitForTimeout(400);
  console.log('clicking the same legend row again clears it: ' + ((await p.inputValue('#filterChar')) === ''));

  // it stays out of the way where it does not belong
  for (const v of ['characters','series']) {
    await p.click(`.seg[data-view="${v}"]`); await p.waitForTimeout(350);
    console.log(v + ' view shows the donut: ' + await p.locator('.donut').count());
  }
  await p.click('.seg[data-view="library"]'); await p.waitForTimeout(400);
  await p.locator('.chart-card').screenshot({ path: 'chart-app.png' });
  console.log(errs.length ? 'JS ERRORS: ' + errs.join('; ') : 'no JS errors');
  await b.close();
})();
