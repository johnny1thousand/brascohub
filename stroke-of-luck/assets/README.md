# Image drop-in

Nothing here is committed yet. The page is built so it renders finished
**without** these files — each slot layers the real file over a hand-built
stand-in. Drop a file in with the exact name below and it takes over
automatically. No code changes needed.

## Logos — needed

The homepage has three logo placements (header, hero centre, footer) and all
three point at the same file:

| Filename | Notes |
| --- | --- |
| `logo-cream.svg` | **Cream/off-white version.** Used by all three placements, since every one of them sits on a dark background. `logo-cream.png` also works — the page tries `.svg` first, then `.png`. |
| `logo-green.svg` | **Dark green version.** Not placed yet; it's the one to use on any light background, and for the favicon and share image. |

SVG is strongly preferred over PNG for the logo — it stays sharp at every size
and on high-DPI screens, and the file is usually smaller. If you only have a
raster copy, export at 2000px wide or more on a transparent background.

The markup declares the logo as `1024×461` (the ratio of the files supplied) so
the browser reserves the right space before the image loads and the page doesn't
jump. If your actual file has a different ratio, update the `width`/`height`
attributes on the three `img.logo` tags in `index.html`.

**Until `logo-cream.svg` exists, the page falls back to a stand-in** — a simple
SVG glass-and-flag next to "Stroke of Luck" set in Grand Hotel. That stand-in is
*not* your logo. It's a different script, and the glass is drawn from scratch
rather than traced. It exists only so the page doesn't look broken. The real
files should replace it before anything goes live.

## Photos

| Filename | Where it shows | What to use |
| --- | --- | --- |
| `hero-golf.jpg` | Left hero panel | **Needed.** A simulator bay — screen lit up, ball on the mat. None of the photos supplied so far show a bay. |
| `hero-bar.jpg` | Right hero panel | The long bar shot looking down the room (brass ceiling + stools). |
| `bar-room.jpg` | Bar section | The head-on bar shot with the screens above it. |
| `bay.jpg` | Golf section | A second bay angle, or someone mid-swing. |
| `og.jpg` | Link previews | 1200×630 crop — the storefront or the green `Luck` sign wall. |

Landscape crops, ~2000px wide, compressed to a few hundred KB each.
