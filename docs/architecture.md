# Arcadia — the data model, the host and sign-in

Arcadia, the learning guides, is this application. Its product code is the `app/` slice;
`ArcadiaSite` (a singleton over `config/arcadia.php`) answers its paths per language, its roles and
who may sign in. This file is the shape of a guide in the database, then — at the end — how the
host and sign-in work. Migrations: `database/migrations/`, one file per table.
Every product table is `guide_`-prefixed; the accounts tables (`roles`, `memberships`,
`auth_providers`, `user_auth_providers`) are `2026_10_11_000000_create_accounts_tables.php`.

## A guide is versions, and each version is a full copy

```
guides ── guide_versions             1 = the base; each later one a copy of the one before
             ├─ guide_version_changes  typed log: new video · practice · exam · modified · removed · fixed
             ├─ guide_sections         prose: how to use · go further · must master · tips
             └─ guide_stages           a title, and what the reader can do when they finish
                  └─ guide_cards       one creator's material (`source`), or the `challenge`
                       └─ guide_items  one checkable line: video · playlist · site · note · practice
```

The final challenge is a **card** in the last stage, not a stage, so its tasks count toward
that stage's progress. A guide is one language (`locale`) and one `Level` — a fixed scale,
labelled from `lang/{es,en}/arcadia.php` — with a category, topics and one or more goals, the
last its own lines of text (ADR 0020).

**Why copies.** Content goes stale: a video is taken down, a creator's site dies, in the base
and in later additions alike. A later version must be able to add, modify, remove and reorder,
while a reader of an earlier one keeps exactly what they were given. A copy per version gives
both. Base-plus-additions was built first and replaced the same day, because it could only add.

- **Version 1 is created with the guide**, as a draft. `release()` publishes it.
- **A guide's `status` and its versions are two switches**, and a reader needs both: a published
  guide, with a released version. Releasing a guide's **first** version publishes a guide still
  in draft (`Editor\VersionController::release`), since that is the owner saying it is ready;
  after that, status is only changed by hand — to take a guide down, and keep it down through
  later releases. The guides list says when the two disagree.
- **`DraftNextVersion` copies the latest released version** into a draft, which may then be
  edited freely. If a draft already exists, that draft is returned: one version is edited at a
  time.
- **A released version is frozen.** Creating, editing or deleting its sections, stages, cards
  or items throws (`FrozenOnceReleased`). A change is a new version.
- **A reader is given `Guide::releasedVersion()`**, the latest released one. Pinning a buyer to
  the version they bought is later work.
- **The change log describes, it does not own.** `guide_version_changes` says what a version
  changed, typed, so the owner can decide whether an earlier buyer is offered it.

## Every node has a key; an item's is the reader's progress

A reader's checkmarks are stored on their account, against each item's `key` and never a
version (§ The reader's own pages). So the key is the line's identity **across versions**:

- **minted by `Item`** when a line is first written, not fillable, and it refuses to change
  (`LogicException`);
- **copied unchanged** into every later version, so a reader moved to a new version keeps
  every checkmark on a line that survived. Editing a line keeps its key; a genuinely new line
  gets a new one, never a key used anywhere before;
- **unique within a version**, `unique(version_id, key)`, and shared by the copies across them.

A new key would clear that line for every reader with no error anywhere — the way a
hand-numbered id would have failed.

**Sections, stages and cards carry the same kind of key** (`HasLineageKey`, on all four
models), for a different reason: it is what two versions are compared on. Matched by key, a
renamed stage is that stage renamed, not one removed and another added. A card also carries its
own `version_id`, as an item does, so its key is unique within its version even when a later
version moves it to another stage.

## A dead link is fixed everywhere at once

`FixItemEverywhere` repairs one line, by key, in **every** version that carries it — the
frozen, released copies included, because a buyer's guide must not rot. It is the one edit a
released version accepts (`Version::whileFixing()`), and it may change only what a repair needs:
`url`, `title`, `hint`, `body`, `is_available`, and the clip (below). Never the key, the kind or the place: a
different line is a new version's job. `is_available` marks a line whose source has gone, so the
editor can show what needs replacing.

**A link carries its brief** (`guide_items.brief`, JSON, `Item::BRIEF_FIELDS`): what it teaches —
a summary, the topics it `covers`, level, creator, length, language, and the words to `search`
for another — written by the research AI with the link (§ Importing an AI's answer). It
describes the lesson, not the one video: a new version copies it, and a repair swaps the link and
keeps it, since it is what the replacement must teach. The owner sees it under each link
(`ItemBrief`), open by itself on a line marked unavailable and in the fix dialog, with a YouTube
search in its own words and a replacement prompt to paste into an AI — offered on every link, since
a line is replaced when it is poor as well as when it is gone. The prompt asks for the guide's
step length — its research prompt's `step_minutes`, else the host's setting — an evidenced
`segment` from a longer video, and never the same link. **Never sent to a reader**:
`GuidePresenter` lists the fields a reader gets. A line written by hand has none; it cannot be
edited in the editor yet.

## Reached by link, and listed while open

A guide's `link_token` — 24 lowercase characters, minted on creation, never taken from input,
unique — is its route key. `rotateLinkToken()` revokes a link that travelled too far. `status` is
`draft` until the owner publishes.

**The catalogue** (`/es/guias` · `/en/guides`, `CatalogueController`, ADR 0023) lists a language's
guides that `Guide::scopeListed()` admits: published, with a released version, **and open for
enrollment**. There is no separate "listed" switch: a guide anyone may ask for is one worth
showing, and a guide made for one person is never opened, so it never appears. Each card links to
the guide's presentation and carries only what that page shows — cover (`share` crop), title,
level, category, pitch and goals. The categories offered are only those a listed guide is in. Not
indexed yet, as the presentations are not.

## The reader's page

`/guia/{token}` in Spanish, `/guide/{token}` in English (`ArcadiaSite::guidePath()`,
`GuidePageController`). It serves **only a published guide of that slug's language, and only its
latest released version** — a draft is never shown. A wrong or rotated token, a draft, an
unreleased guide, the other language's guide: all the same 404, so a link says nothing about
what else exists. It is `noindex`, with its own canonical.

- **One presenter, two doors.** `GuidePresenter` builds the page's data for a version; the public
  link and the editor's **Preview** (`/editor/guides/{id}/preview?version=N`, any version, a draft
  included) both render it into `pages/Guide.vue`, so a preview is the page, not a copy.
- **YouTube ids are extracted on the server** (`GuidePresenter::embed()`): watch, `youtu.be`,
  shorts, embed and playlist links. Anything else is shown as a link, never embedded.
- **A video line can play a clip** (`guide_items.clip_start` · `clip_end`, seconds from the
  video's start, and `clip_evidence`). The embed passes them as YouTube's own `start` and `end`
  parameters, the "open on YouTube" link starts at the clip too, and the line says which part
  plays. A clip with an end plays through YouTube's IFrame Player API (`VideoEmbed`, its script
  loaded only after the click): the page counts down under the player, pauses at the end itself,
  and offers **keep watching** past it or **next**, which ticks the line and scrolls to the one
  after. All of it below the player — YouTube forbids anything in front of an embedded player —
  and the plain player with `start` and `end` if the API does not load. Nothing stops a reader
  scrubbing outside the clip. The evidence is for
  the owner, never sent to a reader. Import keeps an item's `segment` only with evidence
  (`VideoClip`): the research AI cannot watch a video, and without evidence it invents
  timestamps. The owner reviews it under the line, and sets, changes or clears it in the item form
  or the fix dialog as the video's times (`14:00`), never without evidence. Unlike the brief, a
  clip belongs to the video: any save that changes the link without changing the clip clears it
  (`Item::booted()`), so a repair does it in every copy.
- **Click to load.** Nothing from YouTube — not even a thumbnail — is on the page until the
  reader presses play: even `youtube-nocookie.com` writes to the browser's storage when its
  iframe loads. The player is never modified (YouTube's policies forbid it); an "open on
  YouTube" link is always beside it.
- **Progress on the account**, arriving with the page as `progress` — the keys the reader ticked
  and where a tick is saved — so the server renders it and nothing flips after mount
  (`useGuideProgress`). Keyed by item key, so a reader moved to a new version keeps every tick on
  a line that survived; by guide id, not the token, so rotating a link keeps it too. The owner
  reading as-is and the editor's preview get no `progress`: their ticks last for the visit.
- **Stages fold, and open in order** (`guides.unlock_in_order`, on by default, switched on the
  guide's form). The switch reaches the page only inside a reader's `progress` (`unlockInOrder`),
  so the owner reading as-is and the preview never see a lock. **The lock is derived in the page,
  never stored**: a stage is open when it is the first, when the one before it is open and
  complete, or when it or any later stage already holds a tick — so a later version that adds
  lines or stages behind a reader never shuts a stage they were in, and nothing is migrated when
  stages change. The last stage open to the reader starts expanded, the rest folded; with nothing
  locked, the first with a line left. A locked stage still opens, to its lines under a layer that
  takes no clicks (`inert`), with how many steps and roughly how long — each line's `minutes`,
  from its clip's length, else its brief's `duration`, never `hint` — and the stage to finish
  first. A clip's **next** unfolds the stage of the line after it, and stops at a locked one. The
  server takes a tick on any line of the version all the same: the lock guides, it does not guard.
- **A stage completed by a tick cheers** — in that visit only, so a reload never replays it. The
  stage ends in a card: complete (the guide, on the last), "you can now" and the stage's goal, a
  phrase, and the way on to the next stage, popping in unless the reader asked for less motion.
  The phrases arrive with the page as `celebration` (`MotivationPicker::allForGuide()`): every one
  the guide may show for `PhraseMoment::STAGE_DONE`, and for `DONE` on the last stage, worded for
  the reader — the done phrases say the guide is finished, so a stage never uses them. None for the
  owner or the preview, who get the card without a phrase.
- **Its own components, its own colours.** `components/reader/` read only the
  `--guide-*` tokens (`app.css`), never `--accent` or a literal, and the page sets them on its
  root from its `appearance` prop — see § A guide's colours. **The one exception is the video's
  click-to-load frame** (`VideoEmbed`), on Arcadia's own tokens: a reader's colours cover the
  page's layout and content, never the video.

In the editor, the guides list offers **Copy link** once a reader could open it (published, with
a release); a guide's edit screen shows its link whatever the status, and **Change link**
rotates it.

## Readers

A guide is for any number of **readers**, one `guide_readers` row each (`Reader`): the guide,
an email, the account once one exists with it (`user_id`), and who assigned them. A reader is
assigned **by the email of their Google account, before they have an account** — they will sign
in with it — so the address is stored lower-cased and trimmed, and one address is one reader per
guide. Adding a reader links the account already registered with that email, if any. A guide in
each language is its own record, so a reader of both is assigned to both. Removing a reader
deletes the row and keeps the account; deleting a guide deletes its readers.

This is Arcadia's first record ownership, and the assignment is the permission: **a guide opens
only for a signed-in reader of it, or the owner** (`GuidePageController`). The link token is the
guide's address, no longer its permission.

Anyone else is shown **what the guide is about** (ADR 0022), on `GuidePresentation`
(`GuidePresentation`): its cover, title, level, category, goals and **pitch** (`guides.pitch`, a
few lines the owner writes on the guide's Presentation screen — not the greeting, which is written
to a reader). **Never what is in it**: no section, stage or item, no greeting, no readers, and no
price. It answers 200, `noindex`, with its own canonical, and its link preview is the cover's
`share` crop once there is one — Arcadia's own image until then. By `state`:

- **Signed out** → `sign_in`: a **Sign in** button (`?signin=1`) that comes back to the link —
  to the guide for one of its readers, to the request for anyone else.
- **A reader removed from it** → `removed`, "no longer available to you". A removal soft-deletes
  the row so this case can be told apart; adding the email back restores it, colours and all
  (`AssignReader`).
- **An account whose enrollment request was declined** → `declined`, "you don't have access",
  and nothing more to ask.
- **Any other account** → a request to enroll with an optional message while the guide is **open
  for enrollment** (`open`), the request already made (`pending`), or "not open yet" (`closed`) —
  with the account named and "use another Google account", the usual reason for being here.

**Visits are counted, and nothing about the visitor is kept** (`GuideVisits`, `guide_visits`): the
presentation shown adds one to the guide's counter for the day, once per browser session per day,
never for a reader taken off it — the owner and readers are shown the guide, not this page — nor
for a link-preview fetcher or crawler, by user agent. No IP, no extra cookie, no analytics tool;
the privacy note says so (§ Visits to a guide's page).

**Looking spends no device slot.** The guide route carries no `arcadia.device`: the controller runs
`RecogniseDevice::check()` only for a reader about to read, so a stranger viewing an advert is not
counted. The editor previews the page at `/editor/guides/{id}/presentation`, as a visitor who may
ask, with nothing sent. Changing the link (`rotateLinkToken`) now also breaks every ad or post
that shared it; the editor's confirmation says so.

**Enrollment** (ADR 0022) is paid, and the payment happens outside Arcadia:

- **`guides.open_for_enrollment`**, off by default, switched on the guide's Readers screen. A
  closed guide takes no requests; a reader of it, or one removed from it, has nothing to ask.
- **A request** (`guide_access_requests`, `AccessRequest`) is once per guide per account, with
  an optional `message`, and the account's verified email is the contact — nothing else is asked.
  Creating one emails the owner (`SendAccessRequestNotificationJob` → `AccessRequestedMail`, to
  `mail.business_address`, on Arcadia's queue, in Spanish), replying to the requester.
- **It is answered, never deleted:** `status` is `pending`, `approved` or `declined`, with
  `decided_at` and `decided_by`. Approving assigns the account's email and sends "you now have
  access"; declining stands, so the link reads "you don't have access" from then on. An answered
  request cannot be answered again. Adding the email by hand approves its request too
  (`AssignReader`) — a reader the owner adds still reads at once.
- **`owner_note`** is the owner's record of the payment, on the Readers screen only. The
  requester never sees it.
- The editor home counts pending requests in its language.

**A reader may have a name** (`guide_readers.name`): what the guide calls them, set by the owner
when adding them or in the row, else the first name on their account (`Reader::displayName()`).
The guide page gets it as `readerName` — so does the editor's preview as that reader — and
`ReaderGreeting` puts it, highlighted in the accent, where the greeting writes `{nombre}` or
`{name}`; a greeting with neither opens with "¡Hola, María!". With no name, the placeholder is
taken out and the sentence closed up; the owner's preview with no reader shows the slot marked.
The greeting itself is the guide's, one for all its readers; only the name is theirs.

**A reader is told by email that a guide is theirs** (`TellReaders` → `SendGuideSharedJob` →
`GuideSharedMail`, on Arcadia's queue, in the guide's language and Arcadia's brand through the
`mail/account` layout): greeted by name when there is one, the guide's title, its link, and the
address to sign in with — which is
what keeps a reader with several Google accounts off "not shared with this account".

- **When:** on being added, or on an accepted request (worded "you now have access") — if the
  guide can be opened (`Guide::isReadable()`: published, with a release). Otherwise they wait,
  and are told when it can be: on its first release, or when it is set to published with a
  release already there.
- **Once.** `guide_readers.notified_at` records it; a reader removed and added back is not told
  again. The owner's **Reenviar** sends it again (`readers.notify`), only for a guide that can be
  opened.
- **Not at all** when the owner unticks "Avisarle por correo" when adding (`notify`); they can
  still send it from the row.
- The job checks again when it runs — a reader removed meanwhile, or a guide taken down, gets
  nothing — and a failed dispatch never turns adding a reader into an error.

`GuideSharedMailTest` holds each rule.

## A guide's colours

Each reader reads in **their own colours**: a `Palette` (ground, surfaces, text, rules) and an
`Accent`, stored by key on their `guide_readers` row — never a colour, so a palette can be
retuned without touching rows. Both are curated enums, not free colours, and
`GuideAppearanceTest` holds every palette and every palette × accent pairing to the contrast
floors (text 4.5:1, ink 7:1), so no pick makes a guide unreadable. `Palette::ARCADIA` and
`Accent::OWN` (teal, ADR 0019) set no tokens at all: the page keeps the site's own look, glow
included. Dark only, as Arcadia is.

- **The guide's default** (`guides.default_palette`, `default_accent`) is copied into a reader's
  row when they are assigned, and **never inherited after**: changing it repaints nobody already
  assigned.
- **Who last saved them** is `appearance_by` — `admin`, `reader`, or NULL for a row untouched
  since it was assigned — with `appearance_at`. The editor's badge reads it: **Me**, **Reader**,
  or nothing. An owner's save over a reader's own choice needs `overwrite`, which the screen asks
  for first (`Editor\AppearanceController`), so a reader's choice is never undone by accident.
- **The editor** sets both from the guide's Readers screen, in `AppearanceDialog`: the picker
  (`components/AppearancePicker.vue`, shared with the reader's own controls) beside the
  preview page in a frame. The preview takes `?reader=` for that reader's saved colours and
  `?palette=` · `?accent=` for colours being picked — it writes nothing, and anything outside the
  enums is ignored. **Framing it needs the preview to be framable from its own origin**: a
  `X-Frame-Options: DENY` added in nginx would blank it; `SAMEORIGIN` is fine.
- **The public link** opens in a signed-in reader's own colours when the account is a reader of
  that guide, with a **Colours** button that repaints the page as they pick and saves to their
  assignment (`readerAppearance`); for anyone else, the guide's default.

## A guide's images

A guide's images are grouped by purpose, its `ImageSlot` — `cover` and `flyer` — in
`guide_images`. **The slot says how many it holds** (`ImageSlot::maxImages()`): one cover, up to
twelve flyers — one per network, per campaign, per prompt version. Uploading to a slot of one
replaces its image (`StoreGuideImage`); uploading to a slot of many adds a row beside the others,
and a full one refuses the upload (`GuideImageRequest`). The table is indexed, not unique, on
guide and slot since 2026-10-20, so the cover's "only one" is the action's, not the schema's. An
image is removed **by its id**, bound within its guide (`images.destroy`). The image is the
**guide's, not a version's**: a cover is presentation, as the title is, and changing it needs no
release.

- **One master, several crops.** The owner uploads the image as generated; `StoreGuideImage`
  keeps it as it came (`original.<ext>`) and `ImageCrops` cuts, with GD, the JPEG crops the slot
  names (`ImageSlot::crops()`). For `cover`: `share` 1200×630 (the link preview,
  `summary_large_image`), `hero` the whole frame at most 1600 wide, and `thumb` 320×320 —
  the fixed shapes cut from the centre, never upscaled. For `flyer`: only `post`, the whole
  frame at most 1080 wide — a flyer carries text to its edges, so nothing is cut from it. A
  transparent upload is laid on navy.
- **The slot is the rule** (`GuideImageRequest`): JPEG, PNG or WebP, at most
  `ImageSlot::MAX_KILOBYTES`, at least the slot's minimum size, and landscape within its ratio
  band — for `cover`, 1200 × 675 and 1.4 to 1.91 (3:2 and 16:9 both fit; a square does not, as
  its share card would need upscaling); for `flyer`, 1080 × 1080 and 0.55 to 1.0, square or
  portrait, the 1:1, 4:5 and 9:16 its prompt offers. The ratio message is the slot's own
  (`arcadia.editor.images.slots.{slot}.ratio`), as is the rule the editor shows. The composition that survives every crop — subject in
  the centre, no text — is the generating prompt's job, not the server's.
- **Files** sit on the `public` disk under `guides/{guide}/{random}/` — covers uploaded before the
  rename to Arcadia (2026-10-21) stay under `studia/guides/…`, as their row records. **Every upload gets
  a new directory**, so a cover's address cannot be guessed and a cached link preview of the
  previous one is never served as the new; the previous directory is deleted once the new row is
  saved. Deleting the row, or the guide, deletes its files (`GuideImage`'s `deleted` hook, and
  `Guide`'s `deleting`, which deletes images one by one — a cascade alone would leave the files).
- **The editor sends one file per request.** Flyers are chosen several at once, or dropped on
  their section, and go up one after another: PHP's `post_max_size` is 25 MB, two images at the
  slot maximum, so a batch in one request would be refused whole. Each refused file is listed by
  name under its slot.
- **Where readers see it.** The guide page — and the editor's preview, the same page — shows the
  `hero` atop the header, with its size so the page holds its place (`GuidePresenter`,
  `GuideImage::toPage()`); each "My guides" card shows the `thumb` beside the title
  (`Reader\DashboardController`). A guide with no cover shows neither. Flyers are shown to no
  reader: they are for the owner to post. Where guides are listed, only the cover is loaded.
- **Addresses are root-relative** (`/storage/…`, `GuideImage::url()`), because `SeoHead`
  prefixes a share image with the site's own host.
- **The server's limits must allow it.** PHP's `upload_max_filesize` and `post_max_size` and
  nginx's `client_max_body_size` have to be above the slot's maximum, and GD must read JPEG, PNG
  and WebP; the Docker image is built to production's limits (`docker/php/Dockerfile`).

`GuideImageTest` holds each rule here.

## The reader's own pages

`routes/reader.php`: `/es/mis-guias` · `/en/my-guides` (`ArcadiaSite::readerPath()`),
named `<locale>.reader.*`, behind the floor and `role:reader` — the reader role of a
Arcadia membership — and **not** `approved`, since the membership is a reader's admission.

- **The dashboard** (`Reader\DashboardController`, `pages/reader/Dashboard.vue`) lists
  every guide assigned to the account in this language, each card in that reader's colours,
  with its link once the guide is published and released ("being prepared" until then), and
  where the reader stands on it (`GuideProgressSummary`, against the released version by each
  line's key, as the guide page counts): lines, videos, links and practices done of total, stages
  finished, and the last tick — or a line saying nothing is ticked yet, because progress is only
  what they tick. A reader removed from every guide still reaches it, to an empty list.
- **The streak** (`ReadingStreak`, `streak`): days in a row with at least one line ticked, across
  every guide on the account, by **America/Caracas** day — the app clock is UTC, which would end
  a reader's day at 20:00. Read from `guide_ticks`, so unticking a line takes its day with it. It
  stands while today has no tick yet and ends once a whole day passes without one; the page shows
  the run, and how to keep it today or start one, once the reader has a guide.
- **Motivation phrases** on the dashboard (`MotivationPicker`): one welcoming them under the
  title, and one on each readable guide's card for where they stand on it — `PhraseMoment`, from
  its progress: not started, under way, nearly done (three quarters), done. A sixth, **stage done**, is shown only on the guide page (§ The reader's page). One at random each
  visit, from the guide's own choice for that moment when it made one (`guide_phrase_guide`), else
  from every phrase of the language; none shown when there is none. `{name}` becomes the reader's
  name (`Phrase::forReader()`), or is taken out. Kept on the editor's **Frases** screen
  (`Editor\PhraseController`), short — up to `Phrase::MAX_LENGTH` — and chosen per guide in its
  form, for every moment but the welcome (`PhraseMoment::perGuide()`): the welcome greets all of a
  reader's guides at once, so it always draws on every welcome phrase; `GuidePhraseSeeder` writes twenty-four a language, once. `MotivationPhraseTest` holds each rule.
- **The same phrase on the guide page**, as a popup (`GuidePageController`, `motivation`): the
  phrase for where the reader stands on that guide, sent only on their first visit to it in a
  session — the session keeps `arcadia.motivated.<guide id>` — and shown once as a toast. The page
  also remembers it in `sessionStorage`, since going Back restores the props it was sent with.
  None for the owner, and none is remembered while there is no phrase to show.
- **Help** (`Reader\HelpController`, `pages/reader/Help.vue`): `/es/ayuda` · `/en/help`
  (`ArcadiaSite::helpPath()`) — short answers, which are copy (`arcadia.help`), and the address in
  `contact_email`. Only the account's device limit and that address come from the server.
- **The reader's bar** (`ReaderLayout`, picked by `shellFor`) sits above "My guides", help
  and a guide its reader opens — not the device limit, nor a guide the owner reads or previews.
- **Their colours** are saved by `Reader\AppearanceController` — from a dashboard card or the
  guide page, through `ReaderColoursSheet` — on their own assignment only: anyone else's, or the
  other language's, is a 404. The editor's badge then reads **Reader**.
- **Their ticks** are saved by `Reader\ProgressController` from the guide page: one idempotent
  `PUT {reader}/progress` with `{ key, done }` per tap, shown at once and taken back with a
  message if it fails. One `guide_ticks` row per ticked line — account, guide, item key, when.
  Same ownership rule as the colours, and only a guide the reader can open and a line of its
  released version (a 422 otherwise). The rows hang off the account and the guide, not the
  assignment, so a reader removed and added back finds their ticks; two readers sharing a phone
  never share them. `GuideProgressTest` holds each rule.
- **Their notes** (`Reader\NoteController`, `{reader}/notes`, JSON): a reader's own notes on a
  line or a whole stage, one `guide_notes` row each — account, guide, `target_kind` (`item` ·
  `stage`), that node's lineage key, the text (up to `Note::MAX_LENGTH`). Keyed like a tick, so
  they survive a new version on whatever survived. Same ownership rule as ticks; another
  reader's note is a 404 and the owner gets none. They arrive with the guide page as `notes` and
  are kept in place by `useGuideNotes`. From `lg` up the guide moves left and `NotesAside`
  stands beside it, a collapsible toggle per stage and per line. It follows the reader on its
  own: held beside the guide, and scrolled — itself, never the page — to the stage being read;
  opening a line's notes there never moves the page. Below `lg`, the buttons under each
  line (never over its video) and at each stage's header open `NotesSheet` from the bottom.
  `GuideNotesTest` holds each rule.

## Taxonomy is one record per locale

`guide_categories` and `guide_topics` each carry a `locale`, rather than the JSON-per-locale
columns a shared vocabulary would use: a Spanish and an English guide are separate
products, with their own vocabularies. A guide may name its `counterpart` in the other language.
They are what guides are grouped by.

**Goals are not a vocabulary** (ADR 0020): `guides.goals` is the guide's own list of lines —
*"Cerrar ventas por WhatsApp"* — tidied by `Guide::goalLines()` (trimmed, blanks and repeats
dropped, at most `Guide::MAX_GOALS`) wherever they come from: the form, an AI's answer, a
request's prompt. They were registered terms until 2026-10-16.

## The editor

The owner's screens, once per language, under `/es/editor` and `/en/editor`
(`routes/editor.php`, named `<locale>.editor.*`, behind `role:admin`). **Each
editor edits its own language only**: the locale is the URL's, so the Spanish editor lists,
opens and files under Spanish guides and terms — whatever language the owner's account is in —
and another language's guide is a 404 there, whatever id is in the URL. Each editor's nav links
to the other.

- **Dashboard** (`Editor\EditorHomeController`, `/es/editor` · `/en/editor`), where a sign-in
  lands, in the language last used on Arcadia: new and total
  requests, published and draft guides, the price, the five latest requests with their reply
  links and the five guides last edited — every figure counted from this host's rows. Below them,
  **Guide pages**: for each guide open for enrollment or with any figure, its presentation's
  **visits**, **requests** and **enrolled** (requests approved), over the last 30 days or since the
  start (`?window=all`), and the requests still waiting — see § Readers.
- **Guides** (`Editor\GuideController`, `SaveGuide`): the list — cards, each with its link
  page's **visits**, **requests** and **enrolled** over the last 30 days
  (`GuideVisits::forGuides`, one query per count for the page) — and a guide's description —
  title, level, status, category, topics, and its goals one per line. Its content is edited elsewhere. A guide is
  addressed by **id** in the editor, never by its link token, so rotating the public link never
  breaks the editor's own URLs.
- **Readers** (`Editor\ReaderController`, `/editor/guides/{id}/readers`): who the guide is for,
  added and removed by email — see § Readers. Each row also shows where that reader stands on the
  released version — lines and stages done, last tick — counted by `GuideProgressSummary`, as
  their own dashboard counts it; a dash before they have an account or before any release.
- **Presentation** (`Editor\ImageController`, `Editor\PresentationController`,
  `/editor/guides/{id}/images`): one section under another — the guide's pitch, its images by
  slot, the flyers' gallery below the cover — with a preview of what its link shows a visitor. The
  cover and flyer prompts open in a popup from the header, both filled from the guide in
  whichever version the owner picks (`VersionedPromptFiller`). Its figures are on its card in the
  list — see § Readers and § A guide's images.
- **Phrases** (`Editor\PhraseController`, `/editor/phrases`): this language's motivation
  phrases, by moment — see § The reader's own pages.
- **Vocabulary** (`Editor\VocabularyController`): categories and topics, one tab each,
  served by one controller through the `Vocabulary` enum. A category or topic's slug is
  derived from its name on every save, and names are unique by slug within a language
  ("Excel" and "excel" are one term). Deleting a term unfiles it from its guides; the guides
  stay.
- **Content** (`Editor\ContentController`, `/editor/guides/{id}/content`): the content tree of
  the guide's **latest version**, edited in place — sections, and stage → card → item, each
  added, edited, deleted and moved up or down among its siblings. One controller serves the four
  kinds of node through the `ContentNode` enum; `AppendContent` puts a new node at the end of
  its parent, and `MoveContent` swaps it with a neighbour. **Only a draft is editable**: on a
  released version the screen is read-only and a write answers 409. Every write is held to this
  guide's draft — a row from another guide or version, or a parent from one, is a 404. A link
  item needs an `http(s)` URL; a note or practice does not.
- **Versions** (`Editor\VersionController`), on the same screen:
  - **Release** the draft (with a warning that it freezes) and **New version**, which runs
    `DraftNextVersion` and opens the draft. Both act on the latest version only.
  - **Change log**, typed, kept while the version is a draft and frozen with it.
  - **Earlier versions** open read-only with `?version=N`; only the latest draft is editable.
  - **Fix in every version** on any line, released versions included: a dialog runs
    `FixItemEverywhere` by key and first says how many versions it will touch (`copies`).
- **Compare** (`Editor\CompareController`, `/editor/guides/{id}/compare?from=1&to=3`): two
  versions node by node, through `VersionDiff`. Each node is added, removed or changed (field by
  field, before → after), moved to another parent, or reordered among the siblings it kept —
  the longest run of siblings that kept their order stays put, so one item moving up does not
  flag every item after it. Beside it, the change logs of the versions compared, so a change
  nobody logged shows before a release. Defaults to the latest against the one before it;
  "since version 1" compares from the base.
- **Prompts** (`Editor\PromptTemplateController`, `PromptSectionController`,
  `PromptVersionController`, `/editor/prompts`): the owner's AI research prompts. See
  § Research prompts below.
- **Requests** (`Editor\InquiryController`, `/editor/requests`): what visitors asked for on this
  host, newest first and ten to a page, each with the account's email when one was recorded — the
  one to assign the guide to — a link that opens the answer and a delete. Searchable by topic,
  contact, typed name or account, on Enter or the search button, and filtered by contact kind and
  by **stage** (`InquiryStage`, read from the work and never set by hand: `pending` → `guide` once
  one is started; `Inquiry::stage()` and `scopeAtStage()` read it the same way). Each row's one
  button is its next step: **Crear guía** (`InquiryGuideController`, `StartGuideFromInquiry`)
  starts a draft titled by the topic and opens its content, where the research prompt is
  prepared. It replaced a read/unread marker on 2026-10-21, which said a row had been seen, not
  answered. A middle stage, `prompted`, went on 2026-10-22, when the prompt moved onto the guide;
  a request's `prompt_*` columns still hold what was copied before then, and a guide started from
  such a request inherits it. See below.
- **Settings** (`Editor\SettingsController`, `/editor/settings`): this host's `guide_settings` row
  — the **price** a guide is offered at, in dollars, shown beside the landing page's button and
  in its popup, and an optional **price note** (`price_note`, up to 24 characters) shown beside
  it wherever it appears, through `components/landing/Price.vue` — `BCV`, so a reader in
  Venezuela reads the dollar at the central bank's rate. New display options join this row and
  form. The site charges nothing; it states the price. A host with no row reads
  `Setting::DEFAULT_PRICE` ($3); `GuideSettingsSeeder` writes both rows and never overwrites one.
  The row also holds the **step length** (`step_min_minutes` · `step_max_minutes`, 4 and 8 by
  default): how long one step of a new guide should take to watch. It is a default, not a rule —
  the research prompt's `step_length` variable starts at it, and whatever a guide's prompt was
  filled with is that guide's.
- **Front end**: `pages/editor/`, in `EditorLayout` — `DashboardShell` with its own
  nav, its brand leading to the editor's home, and no language or theme controls: the URL decides
  the language and Arcadia is dark only. Every screen builds from the component library
  (`components/ui`, `components/common`); guides-only parts live in `components/`, and
  anything reusable the library lacked went into `common/` — `CheckboxGroup`, for topics. The content blocks are in
  `components/editor/content/`, and `NodeForm` renders any node's form from one field
  list (`fields.js`) — a new field is an entry there and a rule in `ContentNodeRequest`. Route names go through
  `useEditorRoute()`, which adds the host's locale prefix, so no screen spells `arcadia.es`.

## Research prompts

The owner researches a guide with an AI outside the application — their own, by copy and paste —
and keeps the prompt here (`guide_prompt_templates` → `guide_prompt_versions` →
`guide_prompt_sections`).

- **One template serves both hosts.** It is not filed under a locale: the language is one of its
  variables, so the research routine is written once. Both editors list the same templates.
- **A template is created with version 1**, as a guide is (`PromptTemplate::booted()`).
- **A version is ordered, typed sections** (`PromptSectionKind`): the AI's role, who is
  learning, how to research, what to reject, the stages, the wording, practice — and the
  **output contract**, the shape the answer comes back in. A body carries `{{variable}}`
  placeholders.
- **Only the latest version is edited.** Earlier versions open read-only with `?version=N`, and a
  section of any other version is a 404 to every write. **New version**
  (`DraftNextPromptVersion`) copies the latest, at any time.
- **A version declares its variables** (`guide_prompt_variables`, `PromptVariableType`): the name
  its placeholders use — **always English**, whatever the host's language, since a request's
  values arrive by those names; only the label is translated — a label, a type — text, long text, or a choice of level, language, or this
  host's categories — a **price**, text that starts at the price this host's settings offer a
  guide at — a **step length**, text that starts at this host's `4 a 8` — a **phone**, rendered grouped with dashes (`04128023555` → `0412-802-3555`) so an
  image shows it legibly without the model regrouping its digits — whether it is required, and a **default value** its field starts filled with
  (`default_value`, frozen with the version). A goal is text (ADR 0020). The fill-in form is generated from
  them, in their order. The template screen names any placeholder no variable declares, one click
  from declaring it.
- **One renderer** (`PromptRenderer`) is behind the live preview, the copy and every record, so
  what was shown is what was copied. Sections join in order, each under its heading; a choice
  renders as its words (`basic` → "Básico"); an optional blank left empty reads as a dash —
  or, written `{{?name}}`, **takes its whole line with it**, for a prompt whose text is drawn
  rather than read (an image model drew the dash); a
  required one left empty, and an undeclared placeholder, stay visible and are listed. The
  preview is `POST /editor/prompts/{id}/fill` (`PromptFillController`, `FilledPromptResource`),
  debounced by the form, and changes nothing.
- **From a guide's content screen**, "Prompt de investigación" opens the research template
  (`PromptTemplate::RESEARCH`) in a popup, in whichever of its versions the owner picks
  (`VersionedPromptFiller`). Never copied, it opens on the latest, its variables filled by name
  from the guide and the request it answers (`PromptFormOptions::researchValues()`: `topic`,
  `purpose`, `learner_name`, `level`, `goal`, and `language` from the guide). Copying records the
  version and the values on the guide (`GuidePromptController`: `prompt_version_id`,
  `prompt_values` — the same columns its provenance reads), refused while a required blank is
  empty; afterwards it reopens on exactly that version and those values. It lived on the requests
  screen until 2026-10-22, which made researching a guide again impossible once it existed. The
  template screen's **Probar** panel is the same form, recording nothing. Every form also carries **Instrucciones extra** (`PromptRenderer::EXTRA`), whatever the
  template declares: added at the end under its own heading and kept with the values, so a
  record still reproduces the exact text. No variable may take its name.
- **A version is frozen once used** — copied for a guide, or for a request before 2026-10-22
  (`frozen_at`). Its sections and variables then refuse every write, in the model
  (`FrozenOnceUsed`) as well as at the route (409), and a template with a frozen version cannot
  be deleted. Sections and variables reorder through `MoveContent`, the same swap the guide
  content uses.
- **`PromptTemplateSeeder`** writes an optional starter template, in Spanish: the method of the
  owner's first hand-made route, its ten variables declared. Its output contract uses the guide
  tables' own field names with a local `ref` per item — keys are minted by the app on import —
  plus a `greeting` for the learner and `curator_notes` for the owner alone, and a `brief` on
  every link. It is not in `DatabaseSeeder`: it is content to edit, not data the application
  needs. Run where the template exists, it writes nothing — except to bring it up to the changes
  in its `UPDATES` (the brief, 2026-10-16; the greeting's `{name}`, 2026-10-17): a section of the
  latest version that lacks its change takes the seeder's text, in that version while it is
  unused, else in a new one. Its greeting asks the AI to write `{name}` where the reader's name
  goes, never a name — one guide can be for several readers (§ Readers).

- **`CoverPromptTemplateSeeder`** writes a second optional template, "Portada de guía — imagen":
  the prompt for a guide's cover, for ChatGPT or Gemini. It asks for a **proportion, never
  pixels** — landscape, 3:2 or 16:9, inside the `cover` upload rule — the subject centred in the
  middle third with the sides and the top and bottom bands left as background, so every crop keeps
  it, and **no text and no brand**. **No reference image**: the landing photograph, attached at
  first, was redrawn for every topic. The scene comes from the topic — the concrete moment of doing
  it, never a generic metaphor — and the covers are one family through a **house look in words**:
  a deep navy ambient and one teal or cyan accent light, in the `style` the owner picks (a
  realistic photo when blank). Its blanks are the guide's own: title, level, category, pitch,
  goals, and an optional scene and style. Idempotent by name; run where the template exists, it
  brings it to the current text once (a marker in the role's text), in a new version if the latest
  was used. Not in `DatabaseSeeder`.
  **Each guide's Presentation screen offers it filled from that guide** — title, level, category,
  pitch and goals; the scene is the owner's — in the same `PromptFiller`, found by its name
  (`PromptTemplate::COVER`). Copying there records nothing and freezes no version.

- **`ArcadiaFlyerPromptTemplateSeeder`** writes a third optional template, "Flyer de Arcadia —
  imagen": a social media flyer that sells **Arcadia, not one guide**. Its rule is that **people
  buy results, not processes** — a gym sells someone fit, not hours of weights — and a guide is
  the key of a subject, for growing at work, in one's own life or to start something. So the
  headline speaks of **growth that fits every result**, short, imperative and decided
  ("Decídete. Aprende. Transfórmate."), never of one area such as a job, one to three words
  highlighted in the accent; three **results** float as
  cards around one person **who has grown**, with no tool or object of any subject — the cards
  carry what is concrete — never studying and never at a screen; process words (estudia, curso,
  clase…) stay out of the image; six to ten **mini artefacts** — line icons on glass chips, a
  flat layer over the photo like the cards, a little of everything unless the owner names them —
  fill the background's gaps; and
  "Arcadia" only signs it, small. Left blank, the headline and the results are the model's, by
  that formula. Unlike the cover it **carries text**: each quoted string is drawn exactly, and
  each optional text line is `{{?name}}`, so an empty price or contact never reaches the model —
  an early version asked it to skip a dash, and it drew the dash. It **starts filled** — the
  results excepted: left empty, the model writes one per area of growth (work, starting
  something, the personal), none tied to a topic — with the site's address, an invitation, 4:5, and a `price` blank at
  the host's price. A proportion, never pixels. Tied to no guide, it is filled from its own
  template screen. Idempotent by name; run where the template lacks its current text, it writes
  it as a new version, so each approach stays to compare. Not in `DatabaseSeeder`.

- **`GuideFlyerPromptTemplateSeeder`** writes a fourth, "Flyer de guía — imagen": the same kind of
  image selling **one guide**, by the same rule, offered on that guide's Presentation screen
  filled from it (`PromptTemplate::GUIDE_FLYER`) — title, category, pitch, goals and the guide's
  language; the rest from its defaults and the host's price. The headline is **the result the
  guide promises**, written by the model from the title and goals unless the owner gives one —
  not the title, which names the topic — in the same decided tone, with mini artefacts of the
  guide's own subject; its **goals become the cards, as results**, the only
  other text the model words itself. The same `{{?name}}` lines, the same proportion rule, the
  same seeding rule. Not in `DatabaseSeeder`.

- **A guide started from a request** ("Crear guía", `InquiryGuideController` →
  `StartGuideFromInquiry`) is a draft made through `SaveGuide`: titled by the prompt's `topic`, at
  its `level`, with its `goal` as the guide's first goal line, under its `category` when that
  names a term of the host's language. It
  inherits the request's prompt version and values (`guides.inquiry_id`, `prompt_version_id`,
  `prompt_values`), and its edit screen shows them under "Hecha con". One guide per request;
  starting the guide marks the request read. A guide written by hand has no provenance.

`PromptTemplateTest`, `PromptFillTest` and `GuideProvenanceTest` hold each rule here.

## Importing an AI's answer

The research comes back as **JSON** in the shape of the template's output contract, uploaded
as a **`.json` file** (500 KB at most) on the content screen of a guide's **draft** ("Importar de
la IA", `Editor\ImportController`). The file is only read for its answer, never stored; both the
check and the write receive it. A released version answers 409.

- **Checked before anything is written** (`GuideImport`, `POST …/import/preview`). It finds the
  object however an AI wraps it — a ```json fence, a sentence before or after — and reports every
  problem at its place in the guide ("Etapa 2 · tarjeta 1 · ítem 3 · url"): a missing field, a
  kind that does not exist, a link without an http(s) URL, a `ref` used twice. With none, it
  summarises what will be created, goals included as they will be stored.
- **Written all at once** (`ImportGuideContent`, in a transaction): sections, then stage → card
  → item in the answer's order, every node's key minted by the app; the AI's `ref`s were only
  there to be checked. An AI's `""` is stored as nothing. A draft that already has content is
  **replaced only when the owner ticks it**, never mixed.
- **A link's `brief`** is optional — an older answer has none — and kept only on links, its
  known fields only, empty ones dropped (`ImportGuideContent::brief()`). The preview counts the
  links that brought one.
- **On the guide:** the `greeting` — the personal opening, shown first on the reader page and
  editable in the guide's form — and the `curator_notes` — what the research discarded and could
  not verify — shown to the owner under "Hecha con", **never sent to the reader**. The title,
  level and goals are taken from the answer only when asked — the goals as written.
- **Nothing is released.** The guide stays a draft for review.

`GuideImportTest` holds each rule here.

## Requests for a guide

The landing page's one action is a popup (`components/landing/RequestDialog.vue`) that
posts to its language's path — `POST /pedir` from `/es`, `/ask` from `/en`
(`ArcadiaSite::inquiryPath()`) — into `guide_inquiries` (`Inquiry`), in that language.

**Anyone may ask — no account is needed** (decided 2026-10-16, reversing a sign-in requirement
built the same day: a stranger asked to sign in first is one who does not ask).
When the visitor is signed in with a verified email, the request records the account
(`guide_inquiries.user_id`) and the popup shows it — that is the account to assign the guide
to. An unverified account is not recorded: its email is only a claim. The page learns who that
is from `requester` (`name`, `email`, or null).

What the popup asks for:

- **What to learn**, one line, required. The topic tiles and the popup's own idea chips fill it
  in one tap.
- **One contact field**, required — a WhatsApp number or an email. `contact_kind` is derived on
  every save from the `@`, never sent by the form. `Inquiry::replyUrl()` opens the answer: a
  `mailto:`, or `wa.me` with the number made international — a number typed from its trunk `0`
  (`0414 …`) is taken as Venezuelan (`58`); one with `+` or `00` keeps its own code.

"What for" (`purpose`, one of `InquiryPurpose`) and a name were asked until 2026-10-16 and
dropped as taps too many; the columns stay, read by the editor and the prompt popup on the
requests that carry them. `Inquiry::requesterName()` reads the account's name, else a typed one.

The popup shows Arcadia's contact address (`ArcadiaSite::contactEmail()`) under the form and on
its thank-you, which also repeats the contact typed and, when recorded, the account — the way
out for someone who typed their number or address wrong.

The owner can write a request too, from the editor's Requests screen ("Crear solicitud",
`Editor\InquiryController::store`, the same `InquiryRequest`): for a guide of their own, or one
asked for by phone, so it goes through the same prompt and "Crear guía". It mails nobody.

`InquiryController` stores the row, then mails the owner (`SendInquiryNotificationJob` →
`InquiryReceivedMail`, to `mail.business_address`, in Spanish, reply-to the recorded account's
email, else the email typed as the contact), the dispatch `rescue()`d so a mail outage never
loses a stored request. Bots are
held off by a hidden `website` field that must stay empty and by `throttle:5,10` per address.

`InquiryTest` holds each rule here.

## One site, on any host

Arcadia is the whole application, so it answers whatever host reaches it: its routes are bound
to no host (`routes/web.php`). Its links, canonicals, emails and Google
callback are built on `APP_URL` (`ArcadiaSite::url()`), never on the request's host.

**Which hosts reach it is the edge's decision, not the app's.** The proxy in front — Caddy in
production — lists the real hosts, refuses the rest, and owns any redirect from a former host;
the app refuses nothing by host.

## Who may sign in, and how

Three ways in, one rule. **Admission is `ArcadiaSite::admits()`**: an `admin` membership on an
approved account (the owner), or a `reader` membership. No membership, no entry. Every way in asks
it before the user is signed in, never after:

- **Email and password** — Fortify's `authenticateUsing` (`FortifyServiceProvider`). A refused
  account fails exactly as a wrong password does, so the form says nothing about which accounts
  exist.
- **Google** — `AdmitGoogleAccount`, asked before anything is written; a first-time account gets a
  reader membership. See *Readers* above for how an assignment links to it.
- **A passkey** — Laravel's passkey controller signs the user in itself and never reaches
  `authenticateUsing`, so `Passkeys::authorizeLoginUsing()` asks `admits()` too. A passkey
  registered before a membership was removed does not outlive it.

**Passkeys are optional twice.** `PASSKEYS_ENABLED` (default on) adds Fortify's passkey feature,
and with it the routes; every passkey control — the sign-in button, the password confirmation,
Settings › Security, `.well-known/passkey-endpoints` — follows `Features::enabled()`. Each user then
chooses to add one. The passkey URLs in the frontend are written out rather than imported from
Wayfinder, so a build never depends on the switch.

**The account settings** (`pages/settings/*`, the kit's Profile and Security) render inside the
shell their owner works in — the editor's for the owner, the reader's for a reader
(`lib/shell-for.js`) — in both languages (`arcadia.account.*`), and confirm a save beside its
button through the session's `status`, not a toast, so neither shell has to carry one.

`GuideSignInTest`, `GuideSignInGateTest`, `PasskeysTest` and `SaveConfirmationTest` hold these.

## Not here yet

- **Checking links automatically** — a periodic pass that marks a dead source unavailable — is
  not built; `is_available` is set by hand today.
- **Preview items are stored, not enforced.** `is_preview` is edited with the item; the paywall
  that reads it is later work.
- **Buyers** — purchases, a personal greeting — are later work too.

`GuideModelTest` holds each rule above.
