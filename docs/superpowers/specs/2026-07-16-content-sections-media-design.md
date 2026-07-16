# Content Sections & Media Library — Design

Status: Draft, pending user review.
Date: 2026-07-16
Source: brainstorming 16 Juli 2026 (intro `/komunitas` vs `programs.description`).

## Context

Two public funnel pages present long-form introductory content today:

- `/program/{slug}` (`funnel/program.blade.php`) renders `programs.description` as one
  plain-text block (`nl2br`).
- `/komunitas` (`funnel/community.blade.php`) hard-codes its entire intro in Blade:
  three cards ("Komunitas belajar…", "Belajar daring dan luring." with the syllabus,
  "Komitmen dan etika belajar.").

The web-app concept team confirmed the community intro is its **own** content — today it
happens to mirror the flagship program's intro, but the two may diverge. The team is
non-technical and will edit this content **often**, including photos once material is
ready, without waiting for a deploy. A future promotional presence is planned; decision:
no separate promo page — the existing public `/program/{slug}` page becomes the promo
page once it renders rich sections, plus (later, out of scope here) a program-cards
section on the landing page.

## Decisions (from brainstorming)

1. **Structured sections, not a copied text blob** (option C): a `content_sections`
   table owned by a page, not a rich `programs.description`. `description` survives as
   the short one-paragraph summary (chooser cards, meta description).
2. **Community content is independent** of any program; the coincidental overlap with
   the program intro is content, not a relation.
3. **Rich text body** edited with a schema-constrained Tiptap editor (Vue 3), storing
   sanitized HTML. No font/size/color/heading controls — headings live in the
   `heading` column.
4. **True WYSIWYG** via one shared prose stylesheet applied to both the Tiptap content
   area (admin) and the public section cards. Editor renders on a light card canvas
   matching the public look, inside the dark admin shell.
5. **Media library is generic from day one** (user decision): admins will upload
   non-image files too. Images insert into the editor via a picker dialog (primary
   flow); every file gets a copy-link action (secondary flow). Non-image files can be
   inserted as links.
6. **Editor + media manager ship together** — building Tiptap without its image button
   and retrofitting later is double work.

## Data model

### `content_sections`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `page` | string | `'community'` or `'program'`. Extensible (e.g. a future `'home'`) without migration. |
| `program_id` | FK → `programs`, nullable, `cascadeOnDelete` | Required iff `page = 'program'`; always NULL for `'community'`. Enforced in the form request. |
| `heading` | string, nullable | Card title. Nullable so a page can open with a heading-less lead paragraph. |
| `body` | text | Sanitized HTML (see Sanitization). |
| `sort_order` | unsigned int | Position within its page. |
| timestamps | | |

Composite index `(page, program_id, sort_order)` — every read is "sections of page X in
order". No polymorphic relation: community is a page, not an Eloquent model.

### `media`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `path` | string | On `Storage::disk('public')`, under `media/`. |
| `original_name` | string | As uploaded, shown in the manager and used for search. |
| `mime_type` | string | Drives the image/file split in UI (picker shows images only). |
| `size` | unsigned bigint | Bytes, shown in the manager. |
| `alt_text` | string, nullable | Images only; default alt when inserted into the editor. |
| `uploaded_by` | FK → `users`, nullable, `nullOnDelete` | Audit. |
| timestamps | | |

`alt_text` is editable in the manager; `path`/`original_name` are immutable after upload
(replace = delete + re-upload).

Accepted uploads: images `jpeg,jpg,png,webp,gif` and documents `pdf` (max 5 MB each;
extend the allowlist when a real need appears — YAGNI on speculative types).

Files are referenced from section HTML by **relative URL** (`/storage/media/...`), never
the absolute `Storage::url()` form — absolute URLs bake `APP_URL` into content and break
on a domain move. The editor inserts relative URLs; `Media::url()` returns the relative
form. A trade-off to know: editing a media item's `alt_text` later does not update
copies already inserted into section HTML (the inserted `alt` is a snapshot).

**Deletion protection**: `DELETE /media/{id}` first checks whether the file's relative
path appears in any `content_sections.body` (matched on `media/<filename>`, not the full
URL). `programs.thumbnail_path` is unaffected — thumbnails stay a separate mechanism.
If referenced, the delete is rejected (422) with a message naming the section(s)
(page + heading) that use it.

### Models & relations

```php
// Program
public function sections(): HasMany  // hasMany(ContentSection)->orderBy('sort_order')

// ContentSection
public function program(): BelongsTo
public function scopeForCommunity(Builder $q): Builder  // where page=community, ordered

// Media
public function uploader(): BelongsTo  // belongsTo(User::class, 'uploaded_by')
public function isImage(): bool
public function url(): string          // relative: '/storage/'.$this->path
```

Factories for both models; `ContentSection` factory states `community()` and
`forProgram($program)`.

## Editor (admin SPA)

New dependencies (approved 2026-07-16): `@tiptap/vue-3`, `@tiptap/starter-kit` (trimmed),
`@tiptap/extension-link`, `@tiptap/extension-image`.

Schema allows exactly: paragraph, bold, italic, bulletList, orderedList, link, image.
Everything else (headings, colors, fonts, tables, raw HTML) is not in the schema and
cannot exist in the document; pasted rich content is normalized to the schema.
Toolbar (Indonesian labels): Tebal, Miring, Daftar, Daftar bernomor, Tautan, Gambar.

The image toolbar button opens the media picker dialog (grid of image media + upload
drop zone), built on the existing shadcn-vue Dialog components in the admin SPA.
Choosing an image inserts `<img src alt>` at the cursor with a **relative** URL, `alt`
prefilled from `media.alt_text`. A "Sisipkan sebagai tautan" action on non-image files
inserts an `<a>` with the file name.

### Shared prose stylesheet

The admin SPA already imports the same `resources/css/app.css` as the public pages
(`resources/js/admin/main.js:1`) — there is exactly one stylesheet. Class `.kh-prose`
(paragraph rhythm, list styles, link style, `img` sizing/rounding) is defined there
once and applied to:

- the Tiptap `EditorContent` element (admin), rendered on a white card canvas, and
- the section card `<div>` on public pages.

Acceptance check: the same section HTML looks identical (typography, spacing, lists,
images) in the editor and on the public page.

## Sanitization (server-side)

New backend dependency (approved 2026-07-16): `symfony/html-sanitizer` — chosen over
`mews/purifier` because it is framework-agnostic (no coupling to the Laravel major
version; this app is on Laravel 13, which wrapper packages may lag behind) and actively
maintained. On every create/update, `body` passes through a sanitizer config whose
allowlist mirrors the editor schema exactly: `p, strong, em, ul, ol, li, a[href],
img[src|alt]`, no inline styles, no classes, `a href` allows http/https and relative
URLs (file links from the media library are relative), `img src` relative URLs only. Editors are constrained client-side, but the API must not
trust the client. Public pages render `{!! body !!}` (safe because writes are
sanitized). Wrapped in one small app service (e.g. `SectionBodySanitizer`) so the
allowlist lives in a single place and tests target it directly.

## API (all under the existing staff group in `routes/api.php`)

New permission: `content.manage` (sections **and** media — one hat; splitting is YAGNI).
Added in `PermissionSeeder` via the existing `findOrCreate` list and granted to the
`admin` role only (`mentor` keeps its current scope; grant later if the PO asks).

| Route | Purpose |
|---|---|
| `GET /content-sections?page=community` or `?page=program&program_id=N` | List, ordered |
| `POST /content-sections` | Create (appends to end of its page) |
| `PATCH /content-sections/{section}` | Update heading/body |
| `DELETE /content-sections/{section}` | Delete |
| `PATCH /content-sections-order` | Persist reorder: `{page, program_id?, ids: []}` |
| `GET /media?type=image&search=` | Paginated list, newest first. `type=image` filters `mime_type LIKE 'image/%'` (used by the picker); omitted = all files. `search` matches `original_name`. |
| `POST /media` | Upload (multipart), returns media incl. `url` |
| `PATCH /media/{media}` | Update `alt_text` |
| `DELETE /media/{media}` | Delete (with usage check above) |

Form request `StoreContentSectionRequest` / `UpdateContentSectionRequest` enforce:
`page in community,program`; `program_id` required-if page=program, prohibited
otherwise, must exist; `body` required, sanitized in `passedValidation()`.

## Admin UI (Vue, Indonesian copy, English paths)

- **`/content` — "Konten Halaman"** (new view, `content.manage`): page switcher
  (Komunitas | each program) → ordered list of section cards with heading, edit
  (opens Tiptap form), delete (confirm), and up/down reorder buttons. "Tambah Section"
  appends. Reorder persists via the order endpoint.
- **`/media` — "Media"** (new view, `content.manage`): grid (image thumbs / file-type
  tiles), upload button + drag-drop, per-item: copy-link, edit alt text, delete.
  The same grid component is reused as the picker dialog inside the editor.
- Sidebar gets both entries under the existing permission-gated pattern.

## Public rendering

Shared Blade partial `funnel/partials/content-sections.blade.php`: for each section, a
card in the existing style (`rounded-3xl border … bg-white/70`), optional `<h2>` from
`heading`, body inside `div.kh-prose`.

- `funnel/community.blade.php`: the three hard-coded cards are replaced by the partial
  fed with `ContentSection::forCommunity()->get()`, in the same position — inside the
  existing `@unless ($focusedEdit)` block, so the focused-edit flow keeps hiding the
  intro. The hero block (logo, h1, welcome paragraph) stays hard-coded — it is layout,
  not managed content. With zero sections (env where the seeder has not run) the page
  degrades to hero + form, no error.
- `funnel/program.blade.php`: renders the partial with `$program->sections`; falls back
  to the current `description` card when a program has no sections yet (no flash-empty
  pages during rollout).

**Content migration**: `ContentSectionSeeder` inserts the current hard-coded community
cards (idempotent: skips when community sections already exist). Deploy step: run the
seeder once after migrating.

## Testing

Feature tests (PHPUnit, factories):

- Section CRUD happy paths + permission denial without `content.manage`.
- Validation edges: program_id required/prohibited by page; unknown page rejected.
- Sanitization (targeting the sanitizer service): `<script>`, inline styles, event
  attributes, disallowed tags stripped; allowed markup kept; relative `img src` kept,
  absolute/javascript URLs dropped.
- Reorder endpoint persists order; ids from another page rejected.
- Media upload (Storage::fake): stores file, records metadata; wrong type/size rejected.
- Media delete: blocked with 422 when URL referenced in a section body; allowed and
  file removed otherwise.
- Public pages: community renders seeded sections; program page renders its sections
  and falls back to `description` when none.

## Out of scope (explicitly)

- Landing-page "Program Kami" cards section (next iteration; data model already serves it).
- Video/embeds, section templates, drafts/publishing workflow, image resizing pipeline.
- Replacing `programs.thumbnail_path` with the media library.
