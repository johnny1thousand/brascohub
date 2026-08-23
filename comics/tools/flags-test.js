// Favorite + grail: the toggles, the shelf order, the dashboard shortcuts,
// and that a flag survives a round trip to the server.
const { chromium } = require('playwright');
const SP = '/tmp/claude-0/-home-user-brascohub/169f776e-18f4-5450-9fee-3326c3d106b9/scratchpad';
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
  const p = await b.newPage({ viewport: { width: 1340, height: 1100 } });
  const errs = []; p.on('pageerror', e => errs.push(e.message));
  const login = async () => {
    await p.goto('http://127.0.0.1:8899/', { waitUntil: 'networkidle' });
    if (await p.isVisible('#loginScreen.open')) {
      await p.fill('#loginUser','Thanos'); await p.fill('#loginPass','Cra3653793!@#'); await p.click('#loginBtn');
      await p.waitForFunction(()=>!document.getElementById('loginScreen').classList.contains('open'));
    }
    await p.waitForTimeout(700);
  };
  const sections = () => p.$$eval('#viewWrap .group-head h2', hs => hs.map(h => h.textContent.trim()));
  const cardsIn = (i) => p.$$eval('#viewWrap .group', gs =>
    gs.map(g => Array.from(g.querySelectorAll('.bcard-main .t')).map(t => t.textContent.trim()))).then(a => a[i]);
  const statTitles = () => p.$$eval('#stats .stat-title', t => t.map(x => x.textContent.trim()));

  await login();
  console.log('stat blocks:', (await statTitles()).join(', '));
  console.log('flat grid while nothing is flagged:', (await sections()).length === 0);

  // --- flag from the card
  const first = p.locator('#viewWrap .bcard').first();
  await first.locator('.flag--grail').click();
  await p.waitForTimeout(700);
  const grailTitle = (await cardsIn(0))[0];
  console.log('after tapping the grail icon -> sections:', (await sections()).join(' | '));
  console.log('  the book moved into Grails:', grailTitle);
  console.log('  icon shows as on:', await p.locator('#viewWrap .bcard').first().locator('.flag--grail').getAttribute('aria-pressed'));

  // tapping a flag must not open the book
  console.log('  detail modal did NOT open:', !(await p.isVisible('#detailModal.open')));

  // --- favourite a different book
  await p.locator('#viewWrap .group').nth(1).locator('.bcard .flag--favorite').first().click();
  await p.waitForTimeout(700);
  console.log('after hearting one -> sections:', (await sections()).join(' | '));

  // --- the shelf order is grails, favourites, everything else
  const order = await sections();
  console.log('order correct:', order[0].includes('Grails') && order[1].includes('Favorites') && order[2].includes('Everything else'));

  // --- dashboard counts
  const nums = await p.$$eval('#stats .stat', ss => ss.map(s => s.querySelector('.stat-title').textContent.trim() + '=' + s.querySelector('.stat-num').textContent.trim()));
  console.log('counts:', nums.join(' '));

  // --- the Grails block filters the shelf
  await p.click('#stats [data-go-flag="grail"]');
  await p.waitForTimeout(600);
  console.log('grails only -> cards:', await p.locator('#viewWrap .bcard').count(), '| chip:', (await p.textContent('#flagChip')).replace(/\s+/g,' ').trim());
  await p.click('#stats [data-go-flag="favorite"]');
  await p.waitForTimeout(600);
  console.log('favourites only -> cards:', await p.locator('#viewWrap .bcard').count());
  // a non-flag block clears it
  await p.click('#stats [data-go-view="characters"]');
  await p.waitForTimeout(600);
  console.log('after clicking Characters -> flag cleared:', !(await p.isVisible('#flagChip')));
  await p.click('.seg[data-view="library"]'); await p.waitForTimeout(400);
  await p.click('#stats [data-go-flag="grail"]'); await p.waitForTimeout(500);
  await p.click('#flagChip'); await p.waitForTimeout(500);
  console.log('the chip clears itself:', !(await p.isVisible('#flagChip')), '| cards back:', await p.locator('#viewWrap .bcard').count());

  // --- it survives the server round trip
  await login();
  console.log('after reload -> sections:', (await sections()).join(' | '));
  console.log('  same book still the grail:', (await cardsIn(0))[0] === grailTitle);

  // --- and the edit form agrees
  await p.locator('#viewWrap .bcard-main').first().click();
  await p.waitForSelector('#detailModal.open');
  console.log('detail shows the mark:', (await p.textContent('.detail-marks')).replace(/\s+/g,' ').trim());
  await p.click('#detailEditBtn'); await p.waitForSelector('#bookModal.open');
  console.log('form grail pressed:', await p.getAttribute('#fGrail','aria-pressed'), '| favourite:', await p.getAttribute('#fFavorite','aria-pressed'));
  // turn it off from the form and save
  await p.click('#fGrail'); await p.click('#saveBookBtn');
  await p.waitForFunction(()=>!document.getElementById('bookModal').classList.contains('open'));
  await p.waitForTimeout(1000);
  await login();
  console.log('unset from the form, after reload -> sections:', (await sections()).join(' | '));
  await p.screenshot({ path: SP + '/f1-library.png', fullPage: false });
  console.log(errs.length ? 'JS ERRORS:\n'+errs.join('\n') : 'no JS errors');
  await b.close();
})();
