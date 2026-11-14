# 0024 · An open guide may be kept out of the catalogue

**Decided** 2026-11-14. **Status** accepted. **Supersedes** one rejection of ADR 0023: "A separate
`listed` switch, off by default."

## Context

ADR 0023 listed every guide open for enrollment, on the reasoning that every guide was either one
the owner sells to anyone or one made for one person. That stopped being true: some guides are
finished and taken by request — sold by a link shared in an ad, or to a few people of one company —
without belonging on the public "Explore guides" page. With one switch, opening such a guide put it
in the catalogue, and keeping it out meant closing it, which turns away the people the link was
sent to.

## Decision

- **A second switch, `guides.in_catalogue`**, on the guide's Readers screen, shown only while the
  guide is open for enrollment. Off keeps the guide to its link: anyone who opens the link may still
  ask to enroll.
- **On by default.** Every guide keeps what it does today — an open guide is listed — so nothing is
  backfilled on deploy, and opening a guide still lists it unless the owner says otherwise.
- **Listed means open and in the catalogue** (`Guide::scopeListed()`). The switch means nothing
  while the guide is closed: a closed guide is never listed, whatever it says.
- **The sales assistant follows the catalogue**, not enrollment: it offers only listed guides, so a
  guide kept to its link is never named to a stranger. Changing the switch reindexes the guide.
- **A closed guide's link says it is not available for enrollment**, not that it is "not open yet":
  for a guide made for one person, "come back later" promised something that would never happen.

## Rejected

- **A listed-but-closed guide**, a showcase card nobody can ask for. A catalogue lists what can be
  asked for; a card that ends in "not available" is a dead end.
- **Off by default.** Safest for a new guide, but every open guide would leave the catalogue on
  deploy unless a backfill put it back, and that backfill would have to run exactly once.
- **The assistant offering guides kept out of the catalogue.** It answers anyone on the public
  site; naming a guide there is listing it by another door.
