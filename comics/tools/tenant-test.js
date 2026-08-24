// The test that matters: two accounts, and neither can see or touch the other's
// collection. Everything else here is the signup and invite flow around it.
const { chromium } = require('playwright');
const BASE = 'http://127.0.0.1:8899';
const { execSync } = require('child_process');
// A fresh name per run, so the test can be run twice in a row.
const NAME = 'tester' + Math.floor(Math.random() * 1e6);
const cleanup = () => {
  try {
    execSync(`mysql --socket=/tmp/cm.sock -uroot u526894368_comictracker -e "` +
      `DELETE c FROM comics c JOIN users u ON u.id = c.user_id WHERE u.username LIKE 'tester%'; ` +
      `DELETE FROM invites WHERE used_by IN (SELECT id FROM users WHERE username LIKE 'tester%'); ` +
      `DELETE FROM users WHERE username LIKE 'tester%'; ` +
      `DELETE FROM comics WHERE client_id = 'friendbook1';"`);
  } catch (e) { console.log('(cleanup skipped: ' + e.message.split('\n')[0] + ')'); }
  // The signup limiter is 10 tries per IP per half hour, and this test spends
  // several deliberately. Clear its counter so the suite can be re-run.
  try { execSync('rm -f /tmp/comictracker_signup_attempts.json /tmp/comictracker_login_attempts.json'); } catch (e) {}
};

const api = async (ctx, path, body) => {
  const r = await ctx.request.post(BASE + '/api/' + path, { data: body || {}, failOnStatusCode: false });
  let json = null; try { json = await r.json(); } catch (e) {}
  return { status: r.status(), json };
};

(async () => {
  cleanup();
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
  let fails = 0;
  const check = (label, ok, detail) => { if (!ok) fails++; console.log((ok ? '  ok    ' : 'FAIL    ') + label + (detail ? ' — ' + detail : '')); };

  // --- the owner signs in and mints an invite
  const owner = await b.newContext();
  let r = await api(owner, 'login.php', { username: 'Thanos', password: 'Cra3653793!@#' });
  check('owner signs in', r.status === 200 && r.json.user.is_owner === true);
  const ownerBooks = (await api(owner, 'list.php')).json.books.length;
  console.log('        owner has ' + ownerBooks + ' books');

  r = await api(owner, 'account.php', { action: 'invite', note: 'test friend' });
  const code = r.json && r.json.code;
  check('owner mints an invite code', !!code, code);

  // --- a stranger cannot mint one
  const stranger = await b.newContext();
  r = await api(stranger, 'account.php', { action: 'invite' });
  check('signed-out cannot mint invites', r.status === 401);

  // --- signing up needs a real, unused code
  r = await api(stranger, 'signup.php', { invite: 'AAAAA-BBBBB', username: NAME + 'imp', password: 'longenoughpw' });
  check('a made-up invite is refused', r.status === 403, 'status ' + r.status);
  r = await api(stranger, 'signup.php', { invite: code, username: 'x', password: 'longenoughpw' });
  check('a 1-character username is refused', r.status === 400);
  r = await api(stranger, 'signup.php', { invite: code, username: 'friend', password: 'short' });
  check('a short password is refused', r.status === 400);

  // --- the real signup
  const friend = await b.newContext();
  r = await api(friend, 'signup.php', { invite: code, username: NAME, password: 'a-good-long-password', display_name: 'Friend' });
  check('signs up with the invite', r.status === 200 && r.json.user.username === NAME, 'handle ' + (r.json.user && r.json.user.handle));
  check('the new account is not the owner', r.json.user.is_owner === false);
  check('the new shelf starts private', r.json.user.public_shelf === false);
  check('the new account has a read allowance', r.json.user.reads_limit > 0, r.json.user.reads_limit + ' per month');

  // --- the code is now spent
  const second = await b.newContext();
  r = await api(second, 'signup.php', { invite: code, username: NAME + 'crash', password: 'a-good-long-password' });
  check('the same code cannot be used twice', r.status === 403);

  // --- isolation: the friend sees an empty shelf
  r = await api(friend, 'list.php');
  check('the friend sees zero books', r.json.books.length === 0, 'saw ' + r.json.books.length);

  // --- the friend adds a book; the owner must not see it
  await api(friend, 'save.php', { client_id: 'friendbook1', character: 'Judge Dredd', series: '2000 AD', issue: '2' });
  r = await api(friend, 'list.php');
  check('the friend sees their own book', r.json.books.length === 1);
  r = await api(owner, 'list.php');
  check('the owner does not see it', r.json.books.length === ownerBooks && !r.json.books.some(x => x.client_id === 'friendbook1'));

  // --- the same client_id in both accounts stays two separate books
  await api(owner, 'save.php', { client_id: 'friendbook1', character: 'Batman', series: 'Detective Comics', issue: '999' });
  const f = (await api(friend, 'list.php')).json.books.find(x => x.client_id === 'friendbook1');
  const o = (await api(owner, 'list.php')).json.books.find(x => x.client_id === 'friendbook1');
  check('a colliding book id does not overwrite across accounts', f.series === '2000 AD' && o.series === 'Detective Comics',
    'friend: ' + f.series + ' | owner: ' + o.series);

  // --- the friend cannot delete the owner's book
  const victim = (await api(owner, 'list.php')).json.books.find(x => x.client_id !== 'friendbook1');
  r = await api(friend, 'delete.php', { client_id: victim.client_id });
  const stillThere = (await api(owner, 'list.php')).json.books.some(x => x.client_id === victim.client_id);
  check("the friend's delete cannot reach the owner's book", stillThere, 'delete returned ' + r.status);

  // --- and cannot edit it either
  await api(friend, 'save.php', { client_id: victim.client_id, character: 'Hacked', series: 'Hacked', issue: '0' });
  const after = (await api(owner, 'list.php')).json.books.find(x => x.client_id === victim.client_id);
  check("the friend's save cannot overwrite the owner's book", after.character !== 'Hacked', 'owner sees ' + after.character);

  // --- a private shelf is not readable
  let page = await (await b.newContext()).newPage();
  await page.goto(BASE + '/collection/?u=' + NAME);
  const priv = await page.content();
  check('the private shelf shows nothing', priv.includes('This shelf is private') && !priv.includes('2000 AD'));

  // --- the friend makes it public, and only then does it show
  await api(friend, 'account.php', { action: 'profile', display_name: 'Friend', public_shelf: 1 });
  await page.goto(BASE + '/collection/?u=' + NAME);
  const pub = await page.content();
  check('once public, the shelf shows their book', pub.includes('2000 AD') && !pub.includes('This shelf is private'));
  check("and it shows only their book, not the owner's", !pub.includes('Detective Comics'));

  // --- password change requires the current one
  r = await api(friend, 'account.php', { action: 'password', current: 'wrong', next: 'another-long-password' });
  check('a wrong current password is refused', r.status === 403);
  r = await api(friend, 'account.php', { action: 'password', current: 'a-good-long-password', next: 'another-long-password' });
  check('the right one works', r.status === 200);
  r = await api(await b.newContext(), 'login.php', { username: NAME, password: 'another-long-password' });
  check('the new password signs in', r.status === 200);

  cleanup();
  console.log(fails ? '\n' + fails + ' FAILED' : '\nevery account is sealed off from every other');
  await b.close();
  process.exit(fails ? 1 : 0);
})();
