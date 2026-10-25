# 0022 · A guide is enrolled in by request, paid outside Studia

**Decided** 2026-10-17 to 2026-10-18. **Status** accepted. **Supersedes** one bullet of ADR 0021:
"An account not on the list sees nothing of the guide."

## Context

A guide was opened only by the readers the owner added. To sell one, someone who is not yet a
reader has to be able to find out it exists from its link and ask for it. Guides are paid, but
the owner takes payment himself, by agreement with each buyer; Studia has no price on a guide's
page, no payment provider and no invoicing.

## Decision

- **A guide is open for enrollment, or not** (`guides.open_for_enrollment`), **off by default**,
  so no guide changes until the owner switches it on.
- **Enrollment is paid, and the payment happens outside Studia.** The owner arranges it with the
  requester **by email**. Studia shows no price on the guide and stores **no payment, card or
  billing data**.
- **Asking needs a signed-in, verified account**, once per guide, and asks for **nothing beyond
  the account's email and an optional message**. The owner is emailed for each request.
- **Approving a request is what grants access**; it is the record that the requester paid. Until
  then a request grants nothing. **The owner's note on a request is his record of the payment**,
  never shown to the requester.
- **A decline stands**: the account is told it has no access and is offered nothing more. A
  reader removed by the owner is likewise told it has no access.
- **A reader the owner adds by hand still reads at once**, as before.
- **Requests are answered, never deleted**, so the editor can count them.
- **A guide page's visits are counted on the server**: a number per guide per day, once per
  browser per day, without readers, the owner or crawlers, and **nothing about the visitor**. The
  privacy note says so. Studia still runs no analytics tool.

- **The link presents the guide to anyone who may not read it**, signed out included: its cover,
  title, level, category, goals and a pitch the owner writes, with its own link preview — and
  whether they may ask, that they asked, or that it is not open yet. It shows **none of the
  guide's content** and no price, and is **not indexed** for now. Looking at it spends no device
  slot.

ADR 0021's rejection of reading signed out, by the link alone, stands: the content stays closed.

How it works is in `docs/architecture/studia.md` § Readers.

## Rejected

- **A price, a payment link or a payment provider in Studia.** The owner takes payment himself;
  a provider is cost, verification and a refund flow for a handful of sales.
- **Billing data — address, tax id, legal name.** No invoice is issued in the app: personal data
  with no use.
- **A WhatsApp number as the contact.** The account's email is verified, already known, and is
  where the owner writes.
- **A new enrollments table beside access requests.** Two queues for one approval, on one screen.
- **Deleting a dismissed request**, as it was. A dismissed account could ask again forever, and
  nothing could count what was declined.
- **Every guide open by default.** Each existing guide would start taking requests on deploy.
- **Showing the presentation only to signed-in accounts.** A link shared in an ad would open on a
  sign-in page, and its preview would be Studia's, not the guide's.
- **Indexing the presentation page now.** Settled later, with the catalogue.
- **Google Analytics, or any tag, on Studia.** A consent banner and a third party for three
  numbers the server already sees; the studio's page-view pipeline (ADR 0014) is the studio's.
