// The gear must open Settings and every row in it must work. This is the test
// that was missing when f8503c0 broke the handler.
const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
  const p = await b.newPage({ viewport: { width: 1340, height: 1000 }, acceptDownloads: true });
  const errs = []; p.on('pageerror', e => errs.push(e.message));
  await p.goto('http://127.0.0.1:8899/app/', { waitUntil: 'networkidle' });
  await p.fill('#loginUser','Thanos'); await p.fill('#loginPass','Cra3653793!@#'); await p.click('#loginBtn');
  await p.waitForFunction(()=>!document.getElementById('loginScreen').classList.contains('open'));
  await p.waitForTimeout(600);

  for (const view of ['library','characters','series']) {
    await p.click(`.seg[data-view="${view}"]`); await p.waitForTimeout(300);
    await p.click('#settingsBtn'); await p.waitForTimeout(400);
    const open = (await p.getAttribute('#settingsModal','class')).includes('open');
    console.log('from ' + view.padEnd(11) + ' settings opens: ' + open + ' | note: ' + (await p.textContent('#settingsNote')).trim().slice(0,44));
    if (!open) { console.log('FAIL'); process.exit(1); }
    await p.click('#settingsModal [data-close]'); await p.waitForTimeout(250);
  }
  await p.click('#settingsBtn'); await p.waitForTimeout(400);
  console.log('auto-read row visible:', await p.isVisible('#autoReadRow'), '| no stray resolution row:', await p.locator('#hiResRow').count() === 0);
  const dl = p.waitForEvent('download');
  await p.click('#exportBtn');
  console.log('CSV export:', (await dl).suggestedFilename());
  await p.click('#settingsBtn'); await p.waitForTimeout(400);
  await p.click('#retryBtn'); await p.waitForTimeout(900);
  console.log('sync now ->', (await p.textContent('#syncText')).trim());
  await p.click('#settingsBtn'); await p.waitForTimeout(400);
  await p.click('#logoutBtn'); await p.waitForTimeout(900);
  console.log('log out -> login screen back:', await p.isVisible('#loginScreen.open'));
  console.log(errs.length ? 'JS ERRORS:\n'+errs.join('\n') : 'no JS errors');
  await b.close();
})();
