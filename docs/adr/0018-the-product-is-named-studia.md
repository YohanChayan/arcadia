# 0018 · The learning product is named Studia

**Decided** 2026-10-14. **Status** accepted.

## Context

The learning guides shipped under their hosts' names, `aprende.yhndevside.com` and
`learning.yhndevside.com` — two words, one per language, and neither a name. Moving them to one
host (ADR 0017) needed one name that reads the same in Spanish and English.

The name was also chosen for what comes next: an AI assistant on the same site that builds study
plans, guides and courses for a client. So it has to work as someone you ask — "Ask ___" ·
"Pregúntale a ___" — as well as a place you visit, and it has to be easy to say, spell after
hearing it, and remember.

## Decision

**Studia**, on `studia.yhndevside.com`, and the same name for the future assistant.

- *study* in English, *estudia* ("study!") in Spanish, Latin *studia* — the guides, the courses
  and the planning to study, in one word;
- the ending *-ia* is **IA**, Spanish for AI, while English hears an ordinary name ending
  (Olivia, Sofia) — the assistant is in the name for one audience and invisible to the other;
- spelled and said the same in both languages; "Pregúntale a Studia" and "Ask Studia" both run.

The code names the product `App\Studia` (branded, not neutral): the code reads like the product,
and a later rebrand is accepted to mean another rename. *Guide* stays the name of the thing sold —
models, tables, the `/guia` · `/guide` slugs.

**No trademark search was done.** Studia is a subdomain product, not a registered brand; that risk
was accepted on 2026-10-14 and is to be revisited before any wider launch or paid promotion.

## Rejected

| Name | Why not |
|---|---|
| Atlas | OpenAI's "ChatGPT Atlas" AI browser (October 2025) — the worst clash for an AI product; "Pregúntale a Atlas" runs two vowels together |
| menty | One letter from Menti / Mentimeter, a known education tool; can be heard as Spanish *mentí*, "I lied" |
| Mapy | Mapy.cz / mapy.com, a large map service; says navigation app, not study |
| mindy, roady, navi | A person's name; a band's *roadie*; a crowded name (an esports team, *Avatar*) |
| Polaris | Shopify's Polaris design system, known to the developers this studio sells to; three syllables |
| Vega | A common word, hard to own; Vega-Lite, the charting library |
| Tutoria, Rutia | A real Spanish word (*tutoría*), hard to own in search; *ruta*'s meaning is lost in English |
| Cursia, Mentia, Mapia, Lumia | *-ia* names that misfire: *cursi* (tacky); *mentía* ("I was lying"); one letter from *mafia*; Nokia's Lumia |
| A neutral code name (`App\Learning`) with the brand only in copy | Keeps a rebrand cheap, but the code would not read like the product; chosen against knowingly |

## Consequences

- The name is in the copy, the meta, the sign-in, the manifest, the mail subject and the artwork
  (a "Y" over an open book, by YHN); `LandingTest` fails if the old host names return to the copy.
- The future assistant inherits the name; nothing about it is built yet.
