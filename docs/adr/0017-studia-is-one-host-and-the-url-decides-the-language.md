# 0017 · Studia is one host, and the URL decides the language

**Decided** 2026-10-14. **Status** accepted. **Supersedes** the parts of ADR 0015 that make the
host decide the language — "its language — forced for the whole host", "one class configured
per language" and "one sign-in per host" as two sign-ins — and ADR 0016's wording of "the guide
hosts", which is now one host.

## Context

The learning guides shipped on two hosts, `aprende.yhndevside.com` in Spanish and
`learning.yhndevside.com` in English, each a `GuidesSite` that forced its language on every
request (ADR 0015). That made one product two sites: two sign-ins, two editors, two names.

The product was renamed **Studia** — a name that reads the same in both languages and that a
future AI assistant on the site will share — and it needed one address to carry that name. One
host cannot let the host pick the language. The studio already solves the same problem on www:
every page is registered once per language, inside a route group that sets the language from
the URL.

Two constraints came with it. **Spanish is the default**: Studia's market speaks Spanish. And
**Studia must never conflict with the studio's language preference**, which lives in the
studio's session and in `users.locale`.

## Decision

### One site, one host, the language in the URL

`config/sites.php` holds one `studia` entry on `studia.yhndevside.com`, with its slugs per
language. Each public page is registered once per language, inside `spanish-lang` or
`english-lang`: the landing page at `/es` · `/en`, a guide at `/guia/{token}` · `/guide/{token}`,
the privacy note at `/privacidad` · `/privacy`, a request for a guide at `/pedir` · `/ask`. Route
names keep the language **in a segment** — `studia.es.guide`, never `studia.guide.es` — so the
studio's `localeAlternates()` can never pair a Spanish guide's token with the English slug
(`docs/landmines.md`).

### Where no URL decides, Studia's own memory, else Spanish

`/`, Fortify's sign-in and a 404 have no language in their URL. For those the Site chooses:
`Site::localeFor(?string $remembered)` replaces `forcedLocale()`. Studia answers with the
language this browser last used on Studia — the route groups remember it in the session — and
otherwise Spanish. It never consults the browser's `Accept-Language` nor the account's
`users.locale`. The studio answers null and keeps its own chain: session, account, browser.

A site that answers `localeFor()` also reports the request's own language as the shared
`locale` prop, never the account's: an English account at `/es/editor` is in the Spanish editor,
and the editor's locale-named routes match the page.

### No conflict with the studio, by construction

Sessions are host-only (`SESSION_DOMAIN` unset, ADR 0015), so the language Studia remembers is
in Studia's session and the studio's is in the studio's. Studia reads neither the studio's
session nor `users.locale`, and writes neither. `LanguageTest` holds both.

### One editor per language, behind one sign-in

The editor is registered once per language, at `/es/editor` and `/en/editor`, inside the same
route groups. Every editor query was already scoped by `app()->getLocale()`, so the controllers
did not change. The owner signs in once, at `/login`, and lands in the editor of the language
last used on Studia. Each editor's nav links to the other.

## Rejected

- **Keep two sites, both on `studia.`** `SiteResolver` returns the first site that answers a
  host; the second would be dead configuration.
- **The studio's suffix names (`studia.guide.es`).** The landmine: the suffix makes two routes
  one page, and the English sibling of a Spanish token is another guide or a 404, silently.
- **One URL per page, the language from the session or the browser.** The URL would no longer
  say which language a link is in, and a shared link would open in the receiver's language.
- **The browser's language at `/`.** Considered and dropped for Spanish by default: the market
  is Spanish-speaking, and an English reader is one link away.
- **A language switch in the editor, the content language kept apart from the screens'.** It
  splits one value into two across every editor query for a one-person editor, and a switch
  kept in the session is state the URL does not show.

## Consequences

- `ResolveLocale` still asks the Site first, but for a choice, not a force. A later site that
  needs a whole host in one language returns that language from `localeFor()` whatever is
  remembered.
- The former hosts must stay claimed — a host no site claims is the studio's, and would mirror
  it — and answer with permanent redirects to Studia in their language.
- A guide link is now `studia.yhndevside.com/guia/{token}`; the editor's links follow.
