<?php
/**
 * The public shelf — a read-only view of the collection.
 *
 * Deliberately narrow:
 *   - No login, and no way to sign in from the data side: this file only ever
 *     runs one SELECT.
 *   - It takes no query parameters at all, so there is nothing to inject.
 *   - It never touches identify.php or the Claude helpers, so no request from
 *     this page can ever spend money or reach the API key.
 *   - The private columns (value, paid, acquired, tags, notes) are not in the
 *     SELECT list, so they cannot leak into the HTML by accident later.
 *
 * Everything that changes the collection lives in /app/ behind require_login().
 */

require_once __DIR__ . '/../api/db.php';

const SHELF_TITLE = "Brian's Longbox";
const SHELF_BLURB = 'A comic collection, catalogued a cover at a time.';

/** @return array{books: array, ok: bool} */
function public_shelf() {
    try {
        $rows = db()->query(
            'SELECT character_name, series, issue, variant, publisher, year,
                    key_info, favorite, grail, cover_file, thumb_file
             FROM comics
             ORDER BY series ASC, issue_sort ASC, issue ASC, id ASC'
        )->fetchAll();
        return ['books' => $rows, 'ok' => true];
    } catch (Throwable $e) {
        // A database that is down is not worth a stack trace on a public page.
        return ['books' => [], 'ok' => false];
    }
}

function e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

function cover_url(array $b) {
    $file = $b['thumb_file'] !== '' ? $b['thumb_file'] : $b['cover_file'];
    if ($file === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $file)) return '';
    return '../uploads/covers/' . $file;
}

function book_title(array $b) {
    $name = trim($b['series']) !== '' ? $b['series'] : (trim($b['character_name']) !== '' ? $b['character_name'] : 'Untitled');
    return $b['issue'] !== '' ? $name . ' #' . $b['issue'] : $name;
}

$shelf = public_shelf();
$books = $shelf['books'];

$grails = $favorites = $rest = [];
foreach ($books as $b) {
    if (!empty($b['grail'])) $grails[] = $b;
    elseif (!empty($b['favorite'])) $favorites[] = $b;
    else $rest[] = $b;
}

$characters = $titles = [];
foreach ($books as $b) {
    if (trim($b['character_name']) !== '') $characters[strtolower(trim($b['character_name']))] = 1;
    if (trim($b['series']) !== '') $titles[strtolower(trim($b['series']))] = 1;
}

$HEART = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20.7l-1.3-1.2C5.9 15.2 3 12.6 3 9.3 3 6.6 5.1 4.5 7.8 4.5c1.6 0 3.1.7 4.2 2 1.1-1.3 2.6-2 4.2-2C18.9 4.5 21 6.6 21 9.3c0 3.3-2.9 5.9-7.7 10.2L12 20.7z"/></svg>';
$GRAIL = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5.5 3h13v3.2c0 3.4-2.3 6.2-5.4 6.7v4.6h3.4V20H7.5v-2.5h3.4v-4.6C7.8 12.4 5.5 9.6 5.5 6.2V3zm2 2v1.2c0 2.6 2 4.6 4.5 4.6s4.5-2 4.5-4.6V5h-9z"/></svg>';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: public, max-age=300');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#EAE3D9">
<title><?= e(SHELF_TITLE) ?> — LongBox</title>
<meta name="description" content="<?= e(SHELF_BLURB) ?> Catalogued with LongBox.">
<link rel="icon" type="image/webp" href="../assets/icon.webp">
<link rel="apple-touch-icon" href="../assets/icon.webp">
<link rel="stylesheet" href="../assets/brand.css">
<style>
  .head { padding: 44px 34px 0; }
  .head h1 { font-size: clamp(34px, 4.6vw, 58px); }
  .counts { display: flex; gap: 30px; flex-wrap: wrap; margin: 26px 0 0; padding: 0; list-style: none; }
  .counts div { min-width: 96px; }
  .counts b { display: block; font-family: var(--display); font-size: 34px; line-height: 1; }
  .counts span { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .16em; color: var(--muted); }

  .section-head { display: flex; align-items: center; gap: 10px; background: var(--red); color: #fff; border-radius: var(--radius-sm); padding: 11px 16px; margin: 34px 0 14px; }
  .section-head h2 { font-size: 17px; }
  .section-head svg { width: 19px; height: 19px; fill: currentColor; }
  .section-head span { margin-left: auto; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .12em; opacity: .9; }
  .section-head--plain { background: var(--ink); }

  .covers { display: grid; grid-template-columns: repeat(auto-fill, minmax(152px, 1fr)); gap: 14px; }
  .bcard { position: relative; background: var(--card); border: 1px solid var(--line); border-radius: var(--radius-sm); display: flex; flex-direction: column; overflow: hidden; }
  .bcard .cover { position: relative; aspect-ratio: 2 / 3; background: #EFE9E0; display: grid; place-items: center; overflow: hidden; }
  .bcard .cover img { width: 100%; height: 100%; object-fit: cover; display: block; }
  .bcard .ph { width: 58%; opacity: .32; }
  .issue-badge { position: absolute; top: 0; right: 0; background: var(--red); color: #fff; font-size: 11px; font-weight: 700; letter-spacing: .04em; padding: 5px 9px; border-bottom-left-radius: var(--radius-sm); }
  .mark { position: absolute; top: 7px; left: 7px; width: 27px; height: 27px; border-radius: 50%; display: grid; place-items: center; background: var(--red); border: 1px solid var(--red-dark); }
  .mark svg { width: 15px; height: 15px; fill: #fff; }
  .mark--grail { background: var(--ink); border-color: var(--ink); }
  .meta { padding: 11px 12px 13px; display: flex; flex-direction: column; gap: 5px; border-top: 1px solid var(--line); min-width: 0; }
  .meta .t { font-family: var(--display); text-transform: uppercase; font-size: 12.5px; line-height: 1.15; }
  .meta .s { font-size: 10px; color: var(--red); font-weight: 700; text-transform: uppercase; letter-spacing: .11em; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .meta .k { font-size: 11.5px; color: var(--muted); line-height: 1.35; }

  .empty { background: var(--card); border: 1px dashed var(--line); border-radius: var(--radius); padding: 44px 26px; text-align: center; margin: 30px 0 0; }
  .empty h2 { font-size: 24px; }
  .empty p { color: var(--muted); max-width: 46ch; margin: 10px auto 0; }

  .plug { display: flex; gap: 16px; align-items: center; flex-wrap: wrap; justify-content: space-between; background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 22px 26px; margin-top: 40px; }
  .plug p { margin: 0; color: var(--muted); font-size: 14.5px; max-width: 58ch; }
  .plug strong { color: var(--ink); }

  @media (max-width: 860px) {
    .head { padding: 30px 16px 0; }
    .section, .plug { margin-left: 0; margin-right: 0; }
    .counts { gap: 20px; }
    .counts b { font-size: 28px; }
  }
</style>
</head>
<body>

<div class="wrap">
  <div class="surface">

    <header class="topbar">
      <a href="../" title="LongBox"><img class="brand-mark" src="../assets/wordmark.webp" alt="LongBox — My Comics"></a>
      <nav>
        <a class="btn btn--ghost" href="../">What is this?</a>
        <a class="btn" href="../app/">Sign in</a>
      </nav>
    </header>

    <div class="head pad">
      <p class="eyebrow">A public shelf</p>
      <h1><?= e(SHELF_TITLE) ?></h1>
      <p class="lede"><?= e(SHELF_BLURB) ?> Read-only — condition, values and private notes stay behind the login.</p>
      <div class="counts">
        <div><b><?= count($books) ?></b><span>Books</span></div>
        <div><b><?= count($characters) ?></b><span>Characters</span></div>
        <div><b><?= count($titles) ?></b><span>Book titles</span></div>
        <div><b><?= count($grails) ?></b><span>Grails</span></div>
      </div>
    </div>

    <div class="pad">
<?php if (!$shelf['ok']): ?>
      <div class="empty">
        <h2>The shelf is offline</h2>
        <p>The collection could not be loaded just now. Nothing is lost — try again in a minute.</p>
      </div>
<?php elseif (!$books): ?>
      <div class="empty">
        <h2>Nothing on the shelf yet</h2>
        <p>The first books are on their way in.</p>
      </div>
<?php else: ?>
<?php
      $sections = [
          ['Grails', $GRAIL, $grails, ''],
          ['Favorites', $HEART, $favorites, ''],
          ['Everything else', '', $rest, 'section-head--plain'],
      ];
      // With nothing flagged there is only one section, and a heading over the
      // whole shelf reads as noise.
      $showHeads = $grails || $favorites;
      foreach ($sections as list($title, $svg, $list, $extra)):
          if (!$list) continue; ?>
<?php     if ($showHeads): ?>
      <div class="section-head <?= $extra ?>">
        <?= $svg ?>
        <h2><?= e($title) ?></h2>
        <span><?= count($list) ?> book<?= count($list) === 1 ? '' : 's' ?></span>
      </div>
<?php     endif; ?>
      <div class="covers">
<?php     foreach ($list as $b):
            $src = cover_url($b);
            $sub = array_filter([trim($b['character_name']), $b['year'] !== null && $b['year'] !== '' ? $b['year'] : '']);
            if (trim($b['variant']) !== '') $sub[] = $b['variant'];
            $sub = $sub ? implode(' · ', $sub) : (trim($b['publisher']) !== '' ? $b['publisher'] : '—'); ?>
        <div class="bcard">
          <div class="cover">
<?php         if ($src !== ''): ?>
            <img src="<?= e($src) ?>" alt="<?= e(book_title($b)) ?>" loading="lazy">
<?php         else: ?>
            <svg class="ph" viewBox="0 0 120 170" fill="none" stroke="#14110F" stroke-width="4" aria-hidden="true"><rect x="8" y="6" width="104" height="158" rx="4"/><path d="M26 6v158"/><path d="M44 40h50M44 58h50"/><path d="M69 86l7 15 16 2-12 11 3 16-14-8-14 8 3-16-12-11 16-2z"/></svg>
<?php         endif; ?>
<?php         if (!empty($b['grail'])): ?><span class="mark mark--grail"><?= $GRAIL ?></span>
<?php         elseif (!empty($b['favorite'])): ?><span class="mark"><?= $HEART ?></span>
<?php         endif; ?>
<?php         if ($b['issue'] !== ''): ?><span class="issue-badge">#<?= e($b['issue']) ?></span><?php endif; ?>
          </div>
          <div class="meta">
            <span class="t"><?= e(trim($b['series']) !== '' ? $b['series'] : (trim($b['character_name']) !== '' ? $b['character_name'] : 'Untitled')) ?></span>
            <span class="s"><?= e($sub) ?></span>
<?php         if (trim((string) $b['key_info']) !== ''): ?>
            <span class="k"><?= e($b['key_info']) ?></span>
<?php         endif; ?>
          </div>
        </div>
<?php     endforeach; ?>
      </div>
<?php endforeach; ?>
<?php endif; ?>

      <div class="plug">
        <p><strong>Catalogued with LongBox.</strong> Photograph a cover and it fills in the character,
          the book and the issue, straightens the shot, and files it. Accounts are coming soon.</p>
        <a class="btn btn--red" href="../">See how it works</a>
      </div>
    </div>

    <footer class="foot">
      <span>LongBox — built by Ironmane Labs.</span>
      <span>This page is read-only.</span>
      <a href="../app/">Sign in</a>
    </footer>

  </div>
</div>

</body>
</html>
