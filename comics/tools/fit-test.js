// Checks nothing in the top bar gets pushed off the edge, across the phone
// widths that matter. Run the app on 127.0.0.1:8899, then: node tools/fit-test.js
// Chromium, not Safari — close enough for flexbox/grid, not a substitute for a real device.
const { chromium, devices } = require('playwright');
const SP = '/tmp/claude-0/-home-user-brascohub/169f776e-18f4-5450-9fee-3326c3d106b9/scratchpad';

// Widths that matter: the narrowest phone still in use, through the current
// iPhone range, up to Pro Max. An iPhone 17 lands in the 393-402 band.
const CASES = [
  ['iPhone SE (320)',        320, 568],
  ['iPhone SE 3rd (375)',    375, 667],
  ['iPhone 13 mini (375)',   375, 812],
  ['iPhone 14/15 (390)',     390, 844],
  ['iPhone 15 Pro (393)',    393, 852],
  ['iPhone 16 Pro (402)',    402, 874],
  ['iPhone 15 Pro Max (430)',430, 932],
  ['Pixel-ish (412)',        412, 915],
];

(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
  let worst = null, fails = 0;
  for (const [label, w, h] of CASES) {
    const ctx = await b.newContext({ viewport: { width: w, height: h }, deviceScaleFactor: 3, isMobile: true, hasTouch: true });
    const p = await ctx.newPage();
    await p.goto('http://127.0.0.1:8899/app/', { waitUntil: 'networkidle' });
    await p.fill('#loginUser', 'Thanos'); await p.fill('#loginPass', 'Cra3653793!@#'); await p.click('#loginBtn');
    await p.waitForFunction(() => !document.getElementById('loginScreen').classList.contains('open'));
    await p.waitForTimeout(500);

    const m = await p.evaluate(() => {
      const surface = document.querySelector('.surface').getBoundingClientRect();
      const cs = getComputedStyle(document.querySelector('.surface'));
      const padRight = parseFloat(cs.paddingRight);
      const inner = surface.right - padRight;
      const add = document.getElementById('addBtn').getBoundingClientRect();
      const gear = document.getElementById('settingsBtn').getBoundingClientRect();
      const right = document.querySelector('.top-right').getBoundingClientRect();
      const brand = document.querySelector('.brand').getBoundingClientRect();
      return {
        innerRight: Math.round(inner),
        addRight: Math.round(add.right), gearRight: Math.round(gear.right),
        rightGroupWidth: Math.round(right.width), brandWidth: Math.round(brand.width),
        // how far past the usable edge anything sticks out
        overflow: Math.round(Math.max(add.right, gear.right, right.right) - inner),
        pageScroll: document.documentElement.scrollWidth - document.documentElement.clientWidth,
        addText: document.getElementById('addBtn').textContent.trim(),
        pillVisible: getComputedStyle(document.getElementById('syncPill')).display !== 'none'
      };
    });
    const bad = m.overflow > 0 || m.pageScroll > 0;
    if (bad) fails++;
    if (!worst || m.overflow > worst.overflow) worst = { label, ...m };
    console.log((bad ? 'CUT OFF ' : '  ok    ') + label.padEnd(24),
      'overflow ' + String(m.overflow).padStart(4) + 'px',
      '| h-scroll ' + m.pageScroll,
      '| right group ' + m.rightGroupWidth + 'px, brand ' + m.brandWidth + 'px');
    if (bad) await p.screenshot({ path: SP + '/fit-' + w + '.png' });
    await ctx.close();
  }
  console.log(fails ? '\n' + fails + ' width(s) cut off; worst: ' + worst.label + ' by ' + worst.overflow + 'px'
                    : '\nnothing cut off at any width');
  await b.close();
  process.exit(fails ? 1 : 0);
})();
