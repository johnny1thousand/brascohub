// The home page must be inert: no API calls, no third-party requests, nothing
// that could cost money or leak a key. The public shelf must read and no more.
const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });

  for (const [label, url] of [['home', 'http://127.0.0.1:8899/'], ['collection', 'http://127.0.0.1:8899/collection/']]) {
    const ctx = await b.newContext({ viewport: { width: 1340, height: 1000 }, deviceScaleFactor: 2 });
    const p = await ctx.newPage();
    const reqs = [], errs = [];
    p.on('request', r => reqs.push(r.method() + ' ' + r.url()));
    p.on('pageerror', e => errs.push(e.message));
    await p.goto(url, { waitUntil: 'networkidle' });
    await p.waitForTimeout(900);
    const offsite = reqs.filter(r => !r.includes('127.0.0.1:8899'));
    const api = reqs.filter(r => /\/api\//.test(r));
    const nonGet = reqs.filter(r => !r.startsWith('GET '));
    console.log('--- ' + label);
    console.log('  requests: ' + reqs.length + ' | off-site: ' + offsite.length + ' | /api/: ' + api.length + ' | non-GET: ' + nonGet.length);
    reqs.forEach(r => console.log('    ' + r.replace('http://127.0.0.1:8899', '')));
    if (offsite.length) console.log('  OFF-SITE: ' + offsite.join(', '));
    console.log('  title: ' + await p.title());
    console.log('  JS errors: ' + (errs.length ? errs.join('; ') : 'none'));
    await p.screenshot({ path: 'site-' + label + '.png', fullPage: true });
    // the phone view too
    await p.setViewportSize({ width: 393, height: 852 });
    await p.waitForTimeout(400);
    console.log('  h-scroll at 393px: ' + await p.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth));
    await p.screenshot({ path: 'site-' + label + '-phone.png', fullPage: true });
    await ctx.close();
  }
  await b.close();
})();
