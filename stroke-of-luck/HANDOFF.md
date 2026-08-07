# Stroke of Luck — design handoff

Everything a fresh session (or a designer) needs to pick this up cold. The
paste-ready brief is in [The prompt](#the-prompt); the rest is reference.

## Files to attach

**In this repo** (`stroke-of-luck/`):

| File | Why it's needed |
| --- | --- |
| `index.html` | The whole site — single file, inline CSS + JS, no build step |
| `assets/SOL_Logo_Light.webp` | Cream logo, `1024×455`, transparent. Used in header, hero, footer |
| `assets/SOL_Logo.webp` | Dark-green logo, same size. Not placed yet — for light backgrounds, favicon, share image |

**Not in the repo — attach from your own machine.** These are the photos shot of
the space. None are committed yet, and the page currently renders CSS-drawn
stand-ins in their place:

- The long bar looking down the room (brass ceiling, stools, stained concrete)
- The bar head-on with the TVs above it
- The green `Stroke of Luck` sign on the wall
- Storefront exteriors, including the `SIP / SWING / SCORE` window vinyl
- The front door with the hours placard
- **Still missing entirely: a simulator bay photo.** Nothing shot so far shows one.

Target filenames are in `assets/README.md`. Dropping a file in with the right
name makes it take over automatically — no code change.

## Brand reference

Palette taken from the actual room, not invented:

| Token | Hex | Source |
| --- | --- | --- |
| `--forest` / `--forest-deep` | `#0B3226` / `#062018` | Signage green, painted brick |
| `--kelly` / `--kelly-bright` | `#17A05C` / `#22C06E` | The `Luck` script on the door |
| `--cream` / `--cream-dim` | `#FBF7EA` / `#EFE7D2` | Logo, window decals |
| `--brass` | `#C79A3E` | Pressed-tin ceiling |
| `--slate` / `--slate-deep` | `#5C6E7E` / `#3A4855` | Painted columns |
| `--wood` / `--wood-light` | `#7A4A2B` / `#A9713F` | The bar itself |
| `--red` | `#E33B2E` | Triangles in the window vinyl, logo flag |

Type: **Outfit** (headings), **Inter** (body), **Grand Hotel** (script accents),
all via Google Fonts. Grand Hotel is *not* the logo's typeface — just a similar
retro script.

Tagline / motif: **SIP ▲ SWING ▲ SCORE**, lifted from the window decals.

## Hours (real — off the door placard)

| | |
| --- | --- |
| Mon – Wed | 2 pm – 9 pm |
| Thursday | 2 pm – 10 pm |
| Friday | 2 pm – 12 am |
| Saturday | 10 am – 12 am |
| Sunday | 12 pm – 9 pm |

## Still unverified — do not invent

Nine `TODO`s in `index.html`, each also rendered on the page as a dashed red
chip so nothing fake can ship unnoticed:

- Address — only the street number (**16**) is confirmed
- Phone + email
- Booking link (both `Book a Bay` buttons point at `#visit`)
- Bay count, hourly rate, simulator brand
- Drink/food menu link, happy hour
- League details
- Social URLs (Instagram, Facebook are `#`)
- Share image `assets/og.jpg`

---

## The prompt

> I'm working on the homepage for **Stroke of Luck**, a golf simulator bar. The
> site is one self-contained file — `index.html`, inline CSS and JS, no build
> step, no dependencies — and I'd like to keep it that way.
>
> **What exists.** A split-screen hero acts as a two-door entry: **GOLF** on the
> left, **BAR** on the right. Hovering opens one side up and narrows the other;
> clicking scrolls down to that section. It's a plain anchor link, so it works
> without JS. Below the hero: a `SIP ▲ SWING ▲ SCORE` strip, then Golf, Bar,
> Events, and Visit/Hours sections, then a footer.
>
> **Brand.** The palette comes from the actual room — forest green `#0B3226`,
> kelly green `#17A05C`, cream `#FBF7EA`, brass `#C79A3E`, slate blue `#5C6E7E`,
> warm wood `#7A4A2B`, red accent `#E33B2E`. Type is Outfit + Inter + Grand
> Hotel. The real logo is `assets/SOL_Logo_Light.webp` (cream, for dark
> backgrounds); `assets/SOL_Logo.webp` is the dark-green version for light
> backgrounds.
>
> **Two hard constraints.**
>
> 1. It's a **simulator** bar, not a real course. Never use photography or
>    imagery of actual golf courses, fairways or greens — the golf side should
>    read as a simulator bay: a lit projection screen in a dark room.
> 2. **Do not invent business facts.** Address, phone, pricing, bay count,
>    simulator brand, menu and booking URL are all still unconfirmed. They're
>    marked `TODO` in the file and rendered as visible dashed-red placeholders.
>    Leave them as placeholders — don't fill them with plausible-looking guesses,
>    because this is a real business and wrong details would go live.
>
> The hours **are** real, transcribed from the placard on the front door, and
> today's row highlights automatically.
>
> **Image handling.** No photos are committed yet. Every image slot is two
> layers: a hand-built CSS scene (`.art`) that always paints, and a photo layer
> (`.photo`) on top that's transparent until the file exists. So the page looks
> finished now, and dropping a real photo into `assets/` takes over with no code
> change. Please preserve that pattern.
>
> **One open question:** the script accent words in the headings (*indoors*,
> *stool*, *outings*) use Grand Hotel, which is a different script from the
> logo's. Should script be reserved for the logo alone, with those accents set in
> the display font instead?
>
> What I'd like from you: [**← say what you want here** — e.g. push the visual
> design further, add a menu or booking page, rework a specific section, make the
> mobile layout stronger, improve the hero.]

Replace that last line with the actual ask. Leaving it vague is the fastest way
to get changes you didn't want — including guesses at the facts above.
