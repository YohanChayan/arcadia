# 0023 · The catalogue lists the guides open for enrollment

**Decided** 2026-10-23. **Status** accepted. **Supersedes** "Reached by link, never listed"
(`docs/architecture/arcadia.md`, 2026-10-12).

## Context

A guide was reached only by its link. ADR 0022 gave that link a presentation for anyone who may
not read it, so a guide could be sold. The landing page's "Explore guides" needed somewhere to go:
a list of what can be asked for. But many guides are made for one person (ADR 0021), and listing
every published guide would put a client's private guide on a public page.

## Decision

- **A catalogue at `/es/guias` · `/en/guides`**, one per language, as every guide is one language.
- **It lists the guides open for enrollment** — published, with a released version, and
  `open_for_enrollment` on (`Guide::scopeListed()`). Being open is the owner saying "anyone may
  ask for this", which is exactly what a catalogue shows. A guide made for one person is never
  opened, so it is never listed.
- **A card opens the guide's presentation** and carries only what the presentation shows: cover,
  title, level, category, pitch, goals. Nothing of the content.
- **Filtered by category**, among the categories a listed guide is in.
- **Not indexed yet**, as the presentations it links to are not (ADR 0022). Settled later.

## Rejected

- **A separate `listed` switch, off by default.** The safest by construction, but a second switch
  that means the same as the first for every guide that exists: one the owner sells and one he
  does not.
- **Every published guide.** Puts guides made for one person on a public page.
- **Level and search filters now.** With a handful of guides, a row of chips is all the choosing
  there is to do.
