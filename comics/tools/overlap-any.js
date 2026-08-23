// Generic version: any element past the viewport, any heading whose own line
// boxes overlap. Run against a URL: node overlap-any.js /collection/
const { chromium } = require('playwright');
const path = process.argv[2] || '/';
const WIDTHS = [320, 360, 375, 393, 412, 430, 540, 768, 900, 1024, 1180, 1340];
const probe = () => {
  const vw = document.documentElement.clientWidth, out = { past: [], lines: [] };
  document.querySelectorAll('*').forEach(el => {
    const r = el.getBoundingClientRect();
    if (r.right > vw + 1) out.past.push(el.tagName.toLowerCase() + '.' + String(el.className || '').split(' ')[0] + ' right=' + Math.round(r.right));
  });
  document.querySelectorAll('h1, h2, h3, h4').forEach(h => {
    const rects = [];
    const walk = n => { if (n.nodeType === 3) { const r = document.createRange(); r.selectNodeContents(n); for (const x of r.getClientRects()) rects.push(x); } else n.childNodes.forEach(walk); };
    walk(h); rects.sort((a, b) => a.top - b.top);
    for (let i = 1; i < rects.length; i++) {
      if (Math.abs(rects[i].top - rects[i - 1].top) < 2) continue;
      const ov = rects[i - 1].bottom - rects[i].top;
      if (ov > 2) out.lines.push('"' + h.textContent.trim().slice(0, 28) + '" overlaps ' + Math.round(ov) + 'px');
    }
  });
  out.hscroll = document.documentElement.scrollWidth - vw;
  return out;
};
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
  let bad = 0;
  for (const w of WIDTHS) {
    const ctx = await b.newContext({ viewport: { width: w, height: 900 }, deviceScaleFactor: 2, isMobile: w <= 480, hasTouch: w <= 480 });
    const p = await ctx.newPage();
    await p.goto('http://127.0.0.1:8899' + path, { waitUntil: 'networkidle' });
    if (path.startsWith('/app')) {
      await p.fill('#loginUser', 'Thanos'); await p.fill('#loginPass', 'Cra3653793!@#'); await p.click('#loginBtn');
      await p.waitForFunction(() => !document.getElementById('loginScreen').classList.contains('open'));
      await p.waitForTimeout(600);
    }
    await p.waitForTimeout(200);
    const r = await p.evaluate(probe);
    const issues = [...new Set([...r.past, ...r.lines])].concat(r.hscroll > 0 ? ['h-scroll ' + r.hscroll + 'px'] : []);
    if (issues.length) { bad++; console.log('FAIL ' + w + 'px: ' + issues.slice(0, 6).join(' | ')); }
    else console.log('  ok ' + w + 'px');
    await ctx.close();
  }
  console.log(bad ? '\n' + path + ': ' + bad + ' width(s) with problems' : '\n' + path + ': clean at every width');
  await b.close();
  process.exit(bad ? 1 : 0);
})();
