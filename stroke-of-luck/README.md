# Stroke of Luck

Website for Stroke of Luck — a golf simulator bar. Sip. Swing. Score.

This folder is a **separate site** from the rest of this repository (Brasco Hub).
Everything for it lives under `stroke-of-luck/` and nothing here touches the
Brasco Hub files at the repo root.

## Files

```
stroke-of-luck/
├── index.html      the homepage — single file, inline CSS + JS, no build step
├── assets/         images (see assets/README.md for the drop-in list)
└── README.md
```

Open `index.html` in a browser to view it. No install, no build, no dependencies.

## The homepage

A split hero acts as a two-door entry: **GOLF** on the left, **BAR** on the
right. Hovering opens a side up; clicking scrolls down to that section. Below
that: golf, bar, events, and visit/hours.

The palette and motifs come from the actual space — forest green signage,
kelly-green script, cream, the brass tin ceiling, slate-blue columns, warm wood
bar, and the red triangles from the `SIP ▲ SWING ▲ SCORE` window vinyl.

Type is Outfit (headings) + Inter (body) + Grand Hotel (script accents), loaded
from Google Fonts. Grand Hotel stands in for the real logo script until the
logo file is added.

## Before this goes live

Search the file for `TODO` — each one is also shown on the page as a dashed red
placeholder so nothing fake ships by accident:

- **Address** — only the street number (16) is confirmed. Needs street, city, state, ZIP.
- **Phone + email**
- **Booking link** — both `Book a Bay` buttons currently point at `#visit`.
- **Simulator details** — bay count, hourly rate, simulator brand.
- **Menu link** — drinks/food, plus any happy hour.
- **League details**
- **Social URLs** — Instagram and Facebook are `#`.
- **Logo files** — `assets/logo-cream.svg` + `assets/logo-green.svg`. Until the cream one exists the page shows a stand-in lockup that is NOT the real logo.
- **Images** — see `assets/README.md`.

**Hours** are transcribed from the placard on the front door and are live on the
page (today's row highlights automatically). Worth a second look before launch:

| | |
| --- | --- |
| Mon – Wed | 2 pm – 9 pm |
| Thursday | 2 pm – 10 pm |
| Friday | 2 pm – 12 am |
| Saturday | 10 am – 12 am |
| Sunday | 12 pm – 9 pm |

## Deploying

The site root is `stroke-of-luck/`, **not** the repo root. On Vercel/Netlify/
Cloudflare Pages, set the project's root directory or publish directory to
`stroke-of-luck` — otherwise it will serve Brasco Hub's `index.html` instead.
