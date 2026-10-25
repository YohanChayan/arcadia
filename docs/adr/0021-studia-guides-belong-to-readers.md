# 0021 · Studia guides belong to readers

**Decided** 2026-10-14 to 2026-10-17. **Status** accepted. **Supersedes** reading a guide by its
link alone (2026-10-13).

## Context

A Studia guide was opened by its private link: anyone holding it could read, progress lived in the
browser, and nothing said who a guide was for. A guide is made for a person, and sometimes for
several; each must read only their own, keep their progress across phone and laptop, and not hand
the guide on by sharing a login.

## Decision

- **A guide has readers, by email** (`guide_readers`). The owner adds them; an account that later
  signs in with that verified email is linked. An account not on the list sees nothing of the guide.
- **Reading needs a signed-in, verified account** — Google, or Studia's own email and password.
  An email is only a claim until verified: nothing is linked to or opened for an unverified one.
- **A role is per site, through memberships** (`site_memberships`): Studia's `reader` and `admin`
  are not the studio's `users.role_id`, and a reader is never approved.
- **Colours, progress and notes are each reader's**, on their account, keyed by a node's lineage key
  so a new version keeps them.
- **A device limit per account** — three by default, a reader's own limit set by the owner — with a
  swap the reader makes themselves once every thirty days; a device unused for ninety days frees
  its slot; the owner can disconnect one.
- **Each site has its own queue and its own account emails**, so a Studia reader never receives the
  studio's mail and a backlog on one never delays the other.
- **Asking for a guide needs no account**; a signed-in, verified one is recorded with the request.

How each works is in `docs/architecture/studia.md` and `docs/architecture/access-control.md`.

## Rejected

- **A `users` table per host.** One guard and one user model carry the whole sign-in stack —
  Fortify, Socialite, sessions, verification; a second would duplicate all of it.
- **One site key on the user.** One person on two sites with two roles — a studio client who is
  also a reader — is exactly what memberships allow and a single key cannot.
- **Reusing the studio's `client` role for readers.** It would admit every studio client to Studia.
- **Reading signed out, by the link alone.** A forwarded link would open the guide for anyone.
- **Only the owner frees a device slot.** The strongest against sharing a login, but every honest
  reader who clears cookies or changes phone is locked out until the owner answers.
- **Signing out the oldest device, silently.** Nobody is ever blocked, so two people sharing a login
  just keep signing each other out: annoying, not prevented.
- **Requiring sign-in to ask for a guide.** Built on 2026-10-16 and reversed the same day: a
  stranger asked to sign in first is one who does not ask.
- **Verifying the reader's phone by a code over WhatsApp or SMS.** It costs per message, needs a
  messaging provider and Meta's business verification, and still does not give the email sign-in
  needs.
- **A queue per feature.** Today's jobs are all mail, a few a day, failing the same way; one queue
  per site is the line that matters.
