# Image drop-in

Nothing here is committed yet. The page is built so it renders finished
**without** these files — each slot layers the real file over a hand-built
stand-in. Drop a file in with the exact name below and it takes over
automatically. No code changes needed.

## Logos — done ✅

Both colorways are committed. Lossless WebP, `1024×455`, transparent
background, and both verified rendering in Chromium.

| Filename | Ink | Used by |
| --- | --- | --- |
| `SOL_Logo_Light.webp` | Cream | **All three placements** — header, hero centre, footer. Every one sits on a dark background, so this is the correct colorway. |
| `SOL_Logo.webp` | Dark green | Not placed yet. This is the one for any light background (e.g. the cream events section), and the right source for a favicon and share image. |

Note the naming: `_Light` refers to the *ink* being light, so it's the file for
*dark* backgrounds. `SOL_Logo.webp` is the reverse.

The three `img.logo` tags declare `width="1024" height="455"` so the browser
reserves the right space and the page doesn't shift as the logo loads. If either
file is ever re-exported at a different ratio, update those attributes to match.

Sizes rendered: 46px tall in the header, 52px in the footer, up to 248px wide in
the hero — all well under the 1024px source width, so the mark stays crisp on
high-DPI screens.

If a vector (SVG) version of the logo ever turns up it's worth swapping in — it
would be resolution-independent and probably smaller. WebP is perfectly fine at
these sizes though; this is a nice-to-have, not a problem.

Each placement keeps a hidden stand-in lockup that only appears if the logo file
fails to load, so a broken path can never leave a blank header. That stand-in is
*not* the logo (different script, glass drawn from scratch) — it's a safety net.

## Photos

| Filename | Where it shows | What to use |
| --- | --- | --- |
| `hero-golf.jpg` | Left hero panel | **Needed.** A simulator bay — screen lit up, ball on the mat. None of the photos supplied so far show a bay. |
| `hero-bar.jpg` | Right hero panel | The long bar shot looking down the room (brass ceiling + stools). |
| `bar-room.jpg` | Bar section | The head-on bar shot with the screens above it. |
| `bay.jpg` | Golf section | A second bay angle, or someone mid-swing. |
| `og.jpg` | Link previews | 1200×630 crop — the storefront or the green `Luck` sign wall. |

Landscape crops, ~2000px wide, compressed to a few hundred KB each.
