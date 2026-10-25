# 0019 · Studia is teal on navy

**Decided** 2026-10-15. **Status** accepted. **Supersedes** the hue of ADR 0016 — its mechanism
stands.

## Context

Studia was rebranded: a new mark — a "Y" in a teal → cyan → blue ramp, drawn for a navy tile —
and a landing mock in the same colours, a photograph at dusk behind white type with one teal word
and teal pill buttons. ADR 0016 had fixed the guide hosts' hue as royal blue, with an aurora that
spread it into violet, pink and amber. Next to the new mark, that palette read as another
product's.

## Decision

**Only the colour changes. Everything ADR 0016 set up stays:** a scoped override in the
`.dark[data-site="studia"]` blocks of `app.css`, dark only, no literal in a component, and the
reader's `--guide-*` tokens for the guide page.

- **The ground is navy** — the logo tile's, a step deeper so text carries (`#061A33` →
  `#030D1C`). Every ink tier holds at least the contrast it has in the studio's dark theme.
- **The accent is the mock's teal-mint**, `#2EE6B8`, at 10.9:1 on the ground. **Text on it is
  dark**: white on this teal is 1.50:1.
- **The signal and focus ring are cyan**, `#2DD6F5`, where the mark's strokes cross.
- **The mark's ramp is a token of its own** (`--mark-a` · `-b` · `-c`), for the one decorative
  gradient a page may carry — not a second accent.
- **The aurora keeps only the mark's hues.** Violet, pink and amber are retired; its six slots
  are blue, cyan, mint, teal, the mark's light-green tip and a deeper blue.
- **Teal is Studia's own accent for a reader** (`Accent::OWN`): a guide or a reader who picked
  nothing gets it, and royal blue stays in the list as one choice among the others.

ADR 0002 still governs the studio, which does not change.

## Rejected

- **Keeping the royal blue and adding teal beside it.** Two accents on one host, and the mark
  would disagree with every button.
- **Light text on the teal**, as the mock draws it. 1.50:1 fails every floor; dark text is
  11:1.
- **A palette for the landing only.** The landing would be teal on a host that is blue
  everywhere else, the sign-in and the guide page included.

## Consequences

- One edit to the scoped blocks moved every Studia page; no component changed for the colour.
- The editor is a calmer slate navy (2026-10-15): the same tokens, moved while the editor is open
  (`data-surface="editor"` on `<html>`, so teleported dialogs follow). Two green grounds were tried
  first the same day and rejected: under the teal accent they read as green on green.
- `Palette::STUDIA`'s reference colours follow the ground, so `GuideAppearanceTest` checks every
  accent against the navy a reader actually sees.
