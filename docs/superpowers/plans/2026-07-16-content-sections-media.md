# Content Sections & Media Library Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Team-editable rich-text content sections (with images from a generic media library) powering the `/komunitas` and `/program/{slug}` public pages, managed from the admin SPA.

**Architecture:** New `content_sections` table (owned by a `page`: `community` or a specific program) rendered by a shared Blade partial; a generic `media` table + file manager; a schema-constrained Tiptap editor in the Vue admin whose content area shares one `.kh-prose` stylesheet with the public pages (true WYSIWYG); server-side sanitization via `symfony/html-sanitizer`.

**Tech Stack:** Laravel 13, PHP 8.4, PHPUnit 12, spatie/laravel-permission, Vue 3 `<script setup>` (plain JS — project convention), shadcn-vue, Tailwind v4, Tiptap.

**Spec:** `docs/superpowers/specs/2026-07-16-content-sections-media-design.md`

## Global Constraints

- Code 100% English (identifiers, routes, comments, commits); UI copy 100% Indonesian, warm register ("kamu"), no em-dashes.
- REQUIRED skills per task: PHP tasks → `laravel-best-practices`; Vue/CSS tasks → `vue-best-practices` + `frontend-design:frontend-design`; all tasks → karpathy-guidelines (surgical changes, YAGNI).
- New dependencies (approved 2026-07-16): `symfony/html-sanitizer` (composer); `@tiptap/vue-3`, `@tiptap/starter-kit`, `@tiptap/extension-link`, `@tiptap/extension-image` (npm). No others.
- After modifying PHP files: `vendor/bin/pint --dirty --format agent`.
- Media/image URLs in stored HTML are ALWAYS relative (`/storage/media/...`), never `Storage::url()` absolute form.
- Admin API routes live in the existing staff group in `routes/api.php` behind `permission:content.manage`.
- Branch: work on `feat/content-sections` (create from `main` at execution start).
- Every migration: `php artisan make:migration` / `make:model`; never hand-create files that artisan generates.

---

### Task 1: Sanitizer service (`SectionBodySanitizer`)

**Files:**
- Create: `app/Support/SectionBodySanitizer.php`
- Test: `tests/Feature/SectionBodySanitizerTest.php`
- Modify: `composer.json` (via composer require)

**Interfaces:**
- Produces: `App\Support\SectionBodySanitizer::sanitize(string $html): string` — returns HTML restricted to `p, strong, em, ul, ol, li, a[href], img[src|alt]`; `a href` http/https/relative; `img src` relative only. Later tasks resolve it from the container (method injection).

- [ ] **Step 1: Install the dependency**

```bash
composer require symfony/html-sanitizer --no-interaction
```

Expected: package added, no version conflicts.

- [ ] **Step 2: Write the failing test**

```bash
php artisan make:test --phpunit SectionBodySanitizerTest --no-interaction
```

```php
<?php

namespace Tests\Feature;

use App\Support\SectionBodySanitizer;
use Tests\TestCase;

class SectionBodySanitizerTest extends TestCase
{
    private SectionBodySanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new SectionBodySanitizer;
    }

    public function test_allowed_markup_is_kept(): void
    {
        $html = '<p>Halo <strong>kuat</strong> dan <em>miring</em></p><ul><li>Satu</li></ul><ol><li>Dua</li></ol>';

        $this->assertSame($html, $this->sanitizer->sanitize($html));
    }

    public function test_scripts_styles_and_event_handlers_are_stripped(): void
    {
        $dirty = '<p onclick="x()" style="color:red">Aman</p><script>alert(1)</script><h1>Judul</h1>';
        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('style=', $clean);
        $this->assertStringNotContainsString('<h1', $clean);
        $this->assertStringContainsString('Aman', $clean);
    }

    public function test_relative_img_src_kept_absolute_dropped(): void
    {
        $clean = $this->sanitizer->sanitize('<img src="/storage/media/a.jpg" alt="Foto kelas">');
        $this->assertStringContainsString('src="/storage/media/a.jpg"', $clean);
        $this->assertStringContainsString('alt="Foto kelas"', $clean);

        $external = $this->sanitizer->sanitize('<img src="https://evil.example/a.jpg">');
        $this->assertStringNotContainsString('evil.example', $external);
    }

    public function test_links_allow_https_and_relative_but_not_javascript(): void
    {
        $this->assertStringContainsString(
            'href="https://kheedma.id"',
            $this->sanitizer->sanitize('<a href="https://kheedma.id">situs</a>')
        );
        $this->assertStringContainsString(
            'href="/storage/media/panduan.pdf"',
            $this->sanitizer->sanitize('<a href="/storage/media/panduan.pdf">panduan</a>')
        );
        $this->assertStringNotContainsString(
            'javascript',
            $this->sanitizer->sanitize('<a href="javascript:alert(1)">x</a>')
        );
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/SectionBodySanitizerTest.php`
Expected: FAIL — class `App\Support\SectionBodySanitizer` not found.

- [ ] **Step 4: Write the implementation**

```php
<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Sanitizes content-section bodies to the exact schema the admin Tiptap
 * editor produces. The editor constrains input client-side, but the API
 * must not trust the client. Image URLs stay relative so stored content
 * never bakes in APP_URL.
 */
class SectionBodySanitizer
{
    private HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig)
            ->allowElement('p')
            ->allowElement('strong')
            ->allowElement('em')
            ->allowElement('ul')
            ->allowElement('ol')
            ->allowElement('li')
            ->allowElement('a', ['href'])
            ->allowElement('img', ['src', 'alt'])
            ->allowLinkSchemes(['http', 'https'])
            ->allowRelativeLinks()
            ->allowMediaSchemes([])
            ->allowRelativeMedias()
            ->withMaxInputLength(50000);

        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function sanitize(string $html): string
    {
        return $this->sanitizer->sanitize($html);
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/SectionBodySanitizerTest.php`
Expected: PASS (4 tests).
If a config method name differs (`allowMediaSchemes([])` rejecting empty array is the likely candidate), read the installed `HtmlSanitizerConfig` source in `vendor/symfony/html-sanitizer/HtmlSanitizerConfig.php` and adjust the config — the TEST is the contract; do not weaken assertions.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: section body sanitizer with tiptap-schema allowlist"
```

---

### Task 2: `content_sections` migration, model, factory

**Files:**
- Create: migration `create_content_sections_table`, `app/Models/ContentSection.php`, `database/factories/ContentSectionFactory.php`
- Modify: `app/Models/Program.php` (add `sections()`)
- Test: `tests/Feature/ContentSectionModelTest.php`

**Interfaces:**
- Produces: `ContentSection` model — fillable `page, program_id, heading, body, sort_order`; `program(): BelongsTo`; `scopeForCommunity(Builder): Builder` (page=community, ordered by sort_order). `Program::sections(): HasMany` ordered by sort_order. Factory: default = community section; state `forProgram(?Program $program = null)`.

- [ ] **Step 1: Generate files**

```bash
php artisan make:model ContentSection -mf --no-interaction
```

- [ ] **Step 2: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\ContentSection;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentSectionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_community_scope_returns_only_community_sections_in_order(): void
    {
        $program = Program::factory()->create();
        ContentSection::factory()->forProgram($program)->create();
        $second = ContentSection::factory()->create(['sort_order' => 2]);
        $first = ContentSection::factory()->create(['sort_order' => 1]);

        $sections = ContentSection::forCommunity()->get();

        $this->assertSame([$first->id, $second->id], $sections->pluck('id')->all());
    }

    public function test_program_sections_relation_is_ordered(): void
    {
        $program = Program::factory()->create();
        $b = ContentSection::factory()->forProgram($program)->create(['sort_order' => 2]);
        $a = ContentSection::factory()->forProgram($program)->create(['sort_order' => 1]);

        $this->assertSame([$a->id, $b->id], $program->sections()->pluck('id')->all());
    }

    public function test_deleting_program_cascades_to_sections(): void
    {
        $program = Program::factory()->create();
        $section = ContentSection::factory()->forProgram($program)->create();

        $program->delete();

        $this->assertDatabaseMissing('content_sections', ['id' => $section->id]);
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/ContentSectionModelTest.php`
Expected: FAIL — table/columns missing.

- [ ] **Step 4: Implement migration, model, factory, relation**

Migration `up()`:

```php
Schema::create('content_sections', function (Blueprint $table) {
    $table->id();
    $table->string('page'); // 'community' | 'program'
    $table->foreignId('program_id')->nullable()->constrained()->cascadeOnDelete();
    $table->string('heading')->nullable();
    $table->text('body');
    $table->unsignedInteger('sort_order')->default(0);
    $table->timestamps();

    $table->index(['page', 'program_id', 'sort_order']);
});
```

`app/Models/ContentSection.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'page',
        'program_id',
        'heading',
        'body',
        'sort_order',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** Sections of the community join page, in display order. */
    public function scopeForCommunity(Builder $query): Builder
    {
        return $query->where('page', 'community')->orderBy('sort_order');
    }
}
```

`database/factories/ContentSectionFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContentSectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'page' => 'community',
            'program_id' => null,
            'heading' => fake()->sentence(3),
            'body' => '<p>'.fake()->paragraph().'</p>',
            'sort_order' => 0,
        ];
    }

    public function forProgram(?Program $program = null): static
    {
        return $this->state(fn () => [
            'page' => 'program',
            'program_id' => $program?->id ?? Program::factory(),
        ]);
    }
}
```

In `app/Models/Program.php`, after `applications()`:

```php
public function sections(): HasMany
{
    return $this->hasMany(ContentSection::class)->orderBy('sort_order');
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/ContentSectionModelTest.php`
Expected: PASS (3 tests).

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: content_sections table, model, factory"
```

---

### Task 3: `media` migration, model, factory

**Files:**
- Create: migration `create_media_table`, `app/Models/Media.php`, `database/factories/MediaFactory.php`
- Test: `tests/Feature/MediaModelTest.php`

**Interfaces:**
- Produces: `Media` model — fillable `path, original_name, mime_type, size, alt_text, uploaded_by`; `uploader(): BelongsTo` (User via `uploaded_by`); `isImage(): bool`; `url(): string` returning RELATIVE `/storage/{path}`. Factory default = jpeg image under `media/`; state `pdf()`.

- [ ] **Step 1: Generate files**

```bash
php artisan make:model Media -mf --no-interaction
```

- [ ] **Step 2: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_url_is_relative(): void
    {
        $media = Media::factory()->create(['path' => 'media/foto.jpg']);

        $this->assertSame('/storage/media/foto.jpg', $media->url());
    }

    public function test_is_image_by_mime_type(): void
    {
        $this->assertTrue(Media::factory()->create()->isImage());
        $this->assertFalse(Media::factory()->pdf()->create()->isImage());
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/MediaModelTest.php`
Expected: FAIL — table/columns missing.

- [ ] **Step 4: Implement migration, model, factory**

Migration `up()`:

```php
Schema::create('media', function (Blueprint $table) {
    $table->id();
    $table->string('path');
    $table->string('original_name');
    $table->string('mime_type');
    $table->unsignedBigInteger('size');
    $table->string('alt_text')->nullable();
    $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
});
```

`app/Models/Media.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Media extends Model
{
    use HasFactory;

    protected $fillable = [
        'path',
        'original_name',
        'mime_type',
        'size',
        'alt_text',
        'uploaded_by',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /** Relative URL — absolute URLs would bake APP_URL into stored content. */
    public function url(): string
    {
        return '/storage/'.$this->path;
    }
}
```

`database/factories/MediaFactory.php`:

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MediaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'path' => 'media/'.Str::uuid().'.jpg',
            'original_name' => fake()->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(10_000, 500_000),
            'alt_text' => null,
            'uploaded_by' => null,
        ];
    }

    public function pdf(): static
    {
        return $this->state(fn () => [
            'path' => 'media/'.Str::uuid().'.pdf',
            'original_name' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
        ]);
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/MediaModelTest.php`
Expected: PASS (2 tests).

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: media table, model, factory"
```

---

### Task 4: `content.manage` permission

**Files:**
- Modify: `database/seeders/PermissionSeeder.php` (add `'content.manage'` to the `$permissions` array, after `'community.view'`)
- Test: `tests/Feature/PermissionSeederTest.php` (extend)

**Interfaces:**
- Produces: permission `content.manage` exists and is granted to role `admin` only (the seeder's `syncPermissions($permissions)` for admin picks it up automatically; the mentor list is NOT touched).

- [ ] **Step 1: Write the failing test**

Read `tests/Feature/PermissionSeederTest.php` first and follow its existing style. Add:

```php
public function test_content_manage_granted_to_admin_only(): void
{
    $this->assertTrue(Role::findByName('admin', 'web')->hasPermissionTo('content.manage'));
    $this->assertFalse(Role::findByName('mentor', 'web')->hasPermissionTo('content.manage'));
}
```

(If the existing test class seeds in `setUp`, rely on that; otherwise seed `RoleSeeder` + `PermissionSeeder` at the top of the method, matching siblings.)

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=test_content_manage_granted_to_admin_only`
Expected: FAIL — permission does not exist.

- [ ] **Step 3: Add the permission**

In `database/seeders/PermissionSeeder.php`, `$permissions` array:

```php
'community.view',
'content.manage',
'data.export',
```

- [ ] **Step 4: Run the whole seeder test file**

Run: `php artisan test --compact tests/Feature/PermissionSeederTest.php`
Expected: PASS (including pre-existing tests — if one asserts the exact permission list, update it to include `content.manage`).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: content.manage permission for admins"
```

---

### Task 5: Content sections admin API

**Files:**
- Create: `app/Http/Controllers/Api/Admin/ContentSectionController.php`, `app/Http/Requests/StoreContentSectionRequest.php`, `app/Http/Requests/UpdateContentSectionRequest.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/ContentSectionAdminTest.php`

**Interfaces:**
- Consumes: `SectionBodySanitizer::sanitize()` (Task 1), `ContentSection` (Task 2), permission `content.manage` (Task 4).
- Produces endpoints (staff group, `permission:content.manage`):
  - `GET /api/admin/content-sections?page=community` | `?page=program&program_id=N` → `{sections: [{id, page, program_id, heading, body, sort_order}]}` ordered
  - `POST /api/admin/content-sections` `{page, program_id?, heading?, body}` → 201 `{section}`; appends `sort_order`
  - `PATCH /api/admin/content-sections/{section}` `{heading?, body}` → `{section}`
  - `DELETE /api/admin/content-sections/{section}` → `{ok: true}`
  - `PATCH /api/admin/content-sections-order` `{page, program_id?, ids: []}` → `{ok: true}`; 422 when ids don't exactly match the page's sections

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Models\ContentSection;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentSectionAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_requires_content_manage_permission(): void
    {
        $mentor = User::factory()->mentor()->create();

        $this->actingAs($mentor)->getJson('/api/admin/content-sections?page=community')->assertForbidden();
        $this->actingAs($mentor)->postJson('/api/admin/content-sections', [])->assertForbidden();
    }

    public function test_lists_sections_of_a_page_in_order(): void
    {
        $admin = User::factory()->admin()->create();
        $b = ContentSection::factory()->create(['sort_order' => 2]);
        $a = ContentSection::factory()->create(['sort_order' => 1]);
        ContentSection::factory()->forProgram()->create(); // other page — excluded

        $this->actingAs($admin)->getJson('/api/admin/content-sections?page=community')
            ->assertOk()
            ->assertJsonPath('sections.0.id', $a->id)
            ->assertJsonPath('sections.1.id', $b->id)
            ->assertJsonCount(2, 'sections');
    }

    public function test_create_appends_and_sanitizes_body(): void
    {
        $admin = User::factory()->admin()->create();
        ContentSection::factory()->create(['sort_order' => 0]);

        $response = $this->actingAs($admin)->postJson('/api/admin/content-sections', [
            'page' => 'community',
            'heading' => 'Jadwal belajar',
            'body' => '<p>Aman</p><script>alert(1)</script>',
        ])->assertCreated();

        $response->assertJsonPath('section.sort_order', 1);
        $this->assertStringNotContainsString('<script', $response->json('section.body'));
        $this->assertStringContainsString('Aman', $response->json('section.body'));
    }

    public function test_program_id_required_for_program_page_and_prohibited_for_community(): void
    {
        $admin = User::factory()->admin()->create();
        $program = Program::factory()->create();

        $this->actingAs($admin)->postJson('/api/admin/content-sections', [
            'page' => 'program', 'body' => '<p>x</p>',
        ])->assertStatus(422)->assertJsonValidationErrors('program_id');

        $this->actingAs($admin)->postJson('/api/admin/content-sections', [
            'page' => 'community', 'program_id' => $program->id, 'body' => '<p>x</p>',
        ])->assertStatus(422)->assertJsonValidationErrors('program_id');

        $this->actingAs($admin)->postJson('/api/admin/content-sections', [
            'page' => 'program', 'program_id' => $program->id, 'body' => '<p>x</p>',
        ])->assertCreated();
    }

    public function test_update_sanitizes_and_saves(): void
    {
        $admin = User::factory()->admin()->create();
        $section = ContentSection::factory()->create();

        $this->actingAs($admin)->patchJson("/api/admin/content-sections/{$section->id}", [
            'heading' => 'Baru',
            'body' => '<p onclick="x()">Bersih</p>',
        ])->assertOk();

        $section->refresh();
        $this->assertSame('Baru', $section->heading);
        $this->assertStringNotContainsString('onclick', $section->body);
    }

    public function test_delete_removes_section(): void
    {
        $admin = User::factory()->admin()->create();
        $section = ContentSection::factory()->create();

        $this->actingAs($admin)->deleteJson("/api/admin/content-sections/{$section->id}")->assertOk();

        $this->assertDatabaseMissing('content_sections', ['id' => $section->id]);
    }

    public function test_reorder_persists_new_order(): void
    {
        $admin = User::factory()->admin()->create();
        $a = ContentSection::factory()->create(['sort_order' => 0]);
        $b = ContentSection::factory()->create(['sort_order' => 1]);

        $this->actingAs($admin)->patchJson('/api/admin/content-sections-order', [
            'page' => 'community',
            'ids' => [$b->id, $a->id],
        ])->assertOk();

        $this->assertSame(0, $b->fresh()->sort_order);
        $this->assertSame(1, $a->fresh()->sort_order);
    }

    public function test_reorder_rejects_id_set_mismatch(): void
    {
        $admin = User::factory()->admin()->create();
        $a = ContentSection::factory()->create();
        $other = ContentSection::factory()->forProgram()->create();

        $this->actingAs($admin)->patchJson('/api/admin/content-sections-order', [
            'page' => 'community',
            'ids' => [$a->id, $other->id],
        ])->assertStatus(422);
    }
}
```

Note: check `database/factories/UserFactory.php` for the `admin()` / `mentor()` states used by sibling tests (e.g. `ProgramThumbnailTest`); reuse exactly what exists.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/ContentSectionAdminTest.php`
Expected: FAIL — 404s (routes missing).

- [ ] **Step 3: Implement requests, controller, routes**

`app/Http/Requests/StoreContentSectionRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContentSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // permission:content.manage middleware guards the route
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'page' => ['required', Rule::in(['community', 'program'])],
            'program_id' => [
                'required_if:page,program',
                'prohibited_if:page,community',
                'nullable',
                'exists:programs,id',
            ],
            'heading' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:50000'],
        ];
    }
}
```

`app/Http/Requests/UpdateContentSectionRequest.php` (page/program ownership is immutable — moving sections between pages is YAGNI):

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContentSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // permission:content.manage middleware guards the route
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'heading' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:50000'],
        ];
    }
}
```

`app/Http/Controllers/Api/Admin/ContentSectionController.php`:

```php
<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContentSectionRequest;
use App\Http\Requests\UpdateContentSectionRequest;
use App\Models\ContentSection;
use App\Support\SectionBodySanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContentSectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'page' => ['required', Rule::in(['community', 'program'])],
            'program_id' => ['required_if:page,program', 'nullable', 'exists:programs,id'],
        ]);

        return response()->json([
            'sections' => $this->pageQuery($data)->orderBy('sort_order')->get()->map($this->payload(...)),
        ]);
    }

    public function store(StoreContentSectionRequest $request, SectionBodySanitizer $sanitizer): JsonResponse
    {
        $data = $request->validated();
        $data['body'] = $sanitizer->sanitize($data['body']);
        $data['sort_order'] = ($this->pageQuery($data)->max('sort_order') ?? -1) + 1;

        $section = ContentSection::create($data);

        return response()->json(['section' => $this->payload($section)], 201);
    }

    public function update(
        UpdateContentSectionRequest $request,
        ContentSection $section,
        SectionBodySanitizer $sanitizer,
    ): JsonResponse {
        $data = $request->validated();
        $data['body'] = $sanitizer->sanitize($data['body']);

        $section->update($data);

        return response()->json(['section' => $this->payload($section)]);
    }

    public function destroy(ContentSection $section): JsonResponse
    {
        $section->delete();

        return response()->json(['ok' => true]);
    }

    /** Persist a full reorder of one page's sections (array index = new sort_order). */
    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'page' => ['required', Rule::in(['community', 'program'])],
            'program_id' => ['required_if:page,program', 'prohibited_if:page,community', 'nullable', 'exists:programs,id'],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $current = $this->pageQuery($data)->pluck('id');
        if ($current->count() !== count($data['ids']) || $current->diff($data['ids'])->isNotEmpty()) {
            return response()->json(['message' => 'Daftar section tidak cocok dengan isi halaman ini. Muat ulang dulu ya.'], 422);
        }

        foreach (array_values($data['ids']) as $index => $id) {
            ContentSection::whereKey($id)->update(['sort_order' => $index]);
        }

        return response()->json(['ok' => true]);
    }

    /** @param array{page:string,program_id?:int|null} $data */
    private function pageQuery(array $data): Builder
    {
        return ContentSection::query()
            ->where('page', $data['page'])
            ->when($data['page'] === 'program', fn (Builder $q) => $q->where('program_id', $data['program_id']));
    }

    /** @return array{id:int,page:string,program_id:?int,heading:?string,body:string,sort_order:int} */
    private function payload(ContentSection $section): array
    {
        return [
            'id' => $section->id,
            'page' => $section->page,
            'program_id' => $section->program_id,
            'heading' => $section->heading,
            'body' => $section->body,
            'sort_order' => $section->sort_order,
        ];
    }
}
```

In `routes/api.php`, import the controller and add inside the `admin` prefix group (after the `programs.manage` group):

```php
Route::middleware('permission:content.manage')->group(function () {
    Route::get('/content-sections', [ContentSectionController::class, 'index']);
    Route::post('/content-sections', [ContentSectionController::class, 'store']);
    Route::patch('/content-sections-order', [ContentSectionController::class, 'reorder']);
    Route::patch('/content-sections/{section}', [ContentSectionController::class, 'update']);
    Route::delete('/content-sections/{section}', [ContentSectionController::class, 'destroy']);
});
```

(Route param `{section}` binds `ContentSection` via the type-hint.)

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/ContentSectionAdminTest.php`
Expected: PASS (8 tests).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: content sections admin CRUD + reorder API"
```

---

### Task 6: Media admin API

**Files:**
- Create: `app/Http/Controllers/Api/Admin/MediaController.php`
- Modify: `routes/api.php` (inside the `content.manage` group from Task 5)
- Test: `tests/Feature/MediaAdminTest.php`

**Interfaces:**
- Consumes: `Media` (Task 3), `ContentSection` (Task 2).
- Produces endpoints:
  - `GET /api/admin/media?type=image&search=x&page=1` → Laravel paginator JSON; items `{id, url, original_name, mime_type, size, alt_text, is_image, created_at}`
  - `POST /api/admin/media` multipart `file` → 201 `{media: {...}}`
  - `PATCH /api/admin/media/{media}` `{alt_text}` → `{media}`
  - `DELETE /api/admin/media/{media}` → `{ok: true}` or 422 with Indonesian message when the file is referenced by section bodies

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Models\ContentSection;
use App\Models\Media;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Storage::fake('public');
    }

    public function test_requires_content_manage_permission(): void
    {
        $mentor = User::factory()->mentor()->create();

        $this->actingAs($mentor)->getJson('/api/admin/media')->assertForbidden();
    }

    public function test_upload_stores_file_and_metadata(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/api/admin/media', [
            'file' => UploadedFile::fake()->image('kelas.jpg', 800, 600),
        ], ['Accept' => 'application/json'])->assertCreated();

        $media = Media::sole();
        Storage::disk('public')->assertExists($media->path);
        $this->assertSame('kelas.jpg', $media->original_name);
        $this->assertSame($admin->id, $media->uploaded_by);
        $this->assertStringStartsWith('/storage/media/', $response->json('media.url'));
    }

    public function test_pdf_allowed_but_docx_and_oversize_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/api/admin/media', [
            'file' => UploadedFile::fake()->create('panduan.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->actingAs($admin)->post('/api/admin/media', [
            'file' => UploadedFile::fake()->create('doc.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->actingAs($admin)->post('/api/admin/media', [
            'file' => UploadedFile::fake()->image('besar.jpg')->size(6000),
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_list_filters_images_and_searches_by_name(): void
    {
        $admin = User::factory()->admin()->create();
        $image = Media::factory()->create(['original_name' => 'foto-kelas.jpg']);
        Media::factory()->pdf()->create(['original_name' => 'panduan.pdf']);

        $this->actingAs($admin)->getJson('/api/admin/media?type=image')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $image->id);

        $this->actingAs($admin)->getJson('/api/admin/media?search=panduan')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_update_alt_text(): void
    {
        $admin = User::factory()->admin()->create();
        $media = Media::factory()->create();

        $this->actingAs($admin)->patchJson("/api/admin/media/{$media->id}", [
            'alt_text' => 'Suasana kelas offline',
        ])->assertOk();

        $this->assertSame('Suasana kelas offline', $media->fresh()->alt_text);
    }

    public function test_delete_blocked_while_referenced_by_a_section(): void
    {
        $admin = User::factory()->admin()->create();
        $media = Media::factory()->create(['path' => 'media/dipakai.jpg']);
        Storage::disk('public')->put($media->path, 'x');
        ContentSection::factory()->create([
            'heading' => 'Belajar daring dan luring.',
            'body' => '<p>Lihat:</p><img src="/storage/media/dipakai.jpg" alt="">',
        ]);

        $this->actingAs($admin)->deleteJson("/api/admin/media/{$media->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'Belajar daring dan luring.'));

        $this->assertModelExists($media);
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_delete_removes_unreferenced_file_and_row(): void
    {
        $admin = User::factory()->admin()->create();
        $media = Media::factory()->create();
        Storage::disk('public')->put($media->path, 'x');

        $this->actingAs($admin)->deleteJson("/api/admin/media/{$media->id}")->assertOk();

        $this->assertDatabaseMissing('media', ['id' => $media->id]);
        Storage::disk('public')->assertMissing($media->path);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/MediaAdminTest.php`
Expected: FAIL — 404s (routes missing).

- [ ] **Step 3: Implement controller + routes**

`app/Http/Controllers/Api/Admin/MediaController.php`:

```php
<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentSection;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $media = Media::query()
            ->when($request->query('type') === 'image', fn ($q) => $q->where('mime_type', 'like', 'image/%'))
            ->when($request->filled('search'), fn ($q) => $q->where('original_name', 'like', '%'.$request->string('search').'%'))
            ->latest('id')
            ->paginate(24)
            ->through($this->payload(...));

        return response()->json($media);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:jpeg,jpg,png,webp,gif,pdf', 'max:5120'],
        ]);

        $file = $request->file('file');
        $media = Media::create([
            'path' => $file->store('media', 'public'),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json(['media' => $this->payload($media)], 201);
    }

    public function update(Request $request, Media $media): JsonResponse
    {
        $data = $request->validate([
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);

        $media->update($data);

        return response()->json(['media' => $this->payload($media)]);
    }

    /** Refuses deletion while any section body still references the file. */
    public function destroy(Media $media): JsonResponse
    {
        $usedIn = ContentSection::query()
            ->where('body', 'like', '%'.$media->path.'%')
            ->get(['page', 'heading']);

        if ($usedIn->isNotEmpty()) {
            $names = $usedIn
                ->map(fn (ContentSection $s) => $s->heading ?: ($s->page === 'community' ? 'Komunitas' : 'Program'))
                ->unique()
                ->implode(', ');

            return response()->json([
                'message' => "File ini masih dipakai di konten: {$names}. Hapus dulu dari kontennya sebelum menghapus file.",
            ], 422);
        }

        Storage::disk('public')->delete($media->path);
        $media->delete();

        return response()->json(['ok' => true]);
    }

    /** @return array{id:int,url:string,original_name:string,mime_type:string,size:int,alt_text:?string,is_image:bool,created_at:string} */
    private function payload(Media $media): array
    {
        return [
            'id' => $media->id,
            'url' => $media->url(),
            'original_name' => $media->original_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'alt_text' => $media->alt_text,
            'is_image' => $media->isImage(),
            'created_at' => $media->created_at->toISOString(),
        ];
    }
}
```

Add inside the Task 5 `content.manage` route group:

```php
Route::get('/media', [MediaController::class, 'index']);
Route::post('/media', [MediaController::class, 'store']);
Route::patch('/media/{media}', [MediaController::class, 'update']);
Route::delete('/media/{media}', [MediaController::class, 'destroy']);
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/MediaAdminTest.php`
Expected: PASS (7 tests).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: media library API with in-use deletion guard"
```

---

### Task 7: Community content seeder

**Files:**
- Create: `database/seeders/ContentSectionSeeder.php`
- Test: `tests/Feature/ContentSectionSeederTest.php`

**Interfaces:**
- Produces: `ContentSectionSeeder` — idempotent; inserts the 3 community cards currently hard-coded in `funnel/community.blade.php`. NOT registered in `DatabaseSeeder` (run once manually at deploy: `php artisan db:seed --class=ContentSectionSeeder --force`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\ContentSection;
use Database\Seeders\ContentSectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentSectionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_three_community_sections_once(): void
    {
        $this->seed(ContentSectionSeeder::class);
        $this->seed(ContentSectionSeeder::class); // idempotent

        $sections = ContentSection::forCommunity()->get();
        $this->assertCount(3, $sections);
        $this->assertSame('Komunitas belajar, bukan sekadar kelas jualan.', $sections[0]->heading);
        $this->assertStringContainsString('Silabus program', $sections[1]->body);
        $this->assertStringContainsString('Komitmen dan etika belajar.', $sections[2]->heading);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/ContentSectionSeederTest.php`
Expected: FAIL — seeder class missing.

- [ ] **Step 3: Implement the seeder**

```bash
php artisan make:seeder ContentSectionSeeder --no-interaction
```

```php
<?php

namespace Database\Seeders;

use App\Models\ContentSection;
use Illuminate\Database\Seeder;

/**
 * Moves the community intro that used to be hard-coded in
 * funnel/community.blade.php into managed content. Run once at deploy;
 * skips when community sections already exist so re-runs never duplicate
 * or overwrite team edits.
 */
class ContentSectionSeeder extends Seeder
{
    public function run(): void
    {
        if (ContentSection::forCommunity()->exists()) {
            return;
        }

        $sections = [
            [
                'heading' => 'Komunitas belajar, bukan sekadar kelas jualan.',
                'body' => '<p>Kami mendampingimu membangun habit dan rutinitas harian sebagai affiliator yang solid, konsisten, dan berkelanjutan.</p>'
                    .'<ul><li><strong>Mentor pribadi, gratis.</strong> Dedicated personal manager yang membimbing, membantu mengurai kendala affiliate, dan menjaga konsistensi konten kreatifmu.</li>'
                    .'<li><strong>Akses komunitas, gratis.</strong> Grup koordinasi tanpa biaya supaya kamu selalu up to date dengan program strategis yang akan dijalankan ke depannya.</li></ul>',
            ],
            [
                'heading' => 'Belajar daring dan luring.',
                'body' => '<ul><li>Sesi Pagi Daring (Perempuan): 09.30 WIB</li>'
                    .'<li>Sesi Siang Luring (Laki-laki): 13.30 WIB</li>'
                    .'<li>Lokasi: Kantor Kheedma Indonesia, Pasar Kliwon, Surakarta, atau via Zoom/Google Meet</li></ul>'
                    .'<p><strong>Silabus program:</strong></p>'
                    .'<ol><li>Fondasi Dasar dan Teknis Awal Affiliate TikTok</li>'
                    .'<li>Akselerasi Penjualan dan Strategi Scale Up</li>'
                    .'<li>Optimalisasi Konten dan Iklan TikTok Affiliate</li>'
                    .'<li>Membangun Personal Branding Digital</li></ol>',
            ],
            [
                'heading' => 'Komitmen dan etika belajar.',
                'body' => '<p>Kami mencari rekan yang siap berkomitmen untuk:</p>'
                    .'<ol><li>Alokasi waktu minimal 1 jam per hari untuk mempraktikkan materi dan menyelesaikan task.</li>'
                    .'<li>Menjaga vibrasi positif, saling support antar anggota, dan membangun circle belajar yang sehat.</li>'
                    .'<li>Saling menghargai dan menjaga etika, kepada sesama rekan belajar maupun mentor.</li></ol>'
                    .'<p>Kami tidak menjanjikan keberhasilan instan atau target angka tertentu. Fokus utama komunitas ini adalah membentuk mindset, kebiasaan produktif, dan framework strategi agar kamu dapat mengelola profesi affiliator secara efektif dan berjangka panjang.</p>',
            ],
        ];

        foreach ($sections as $order => $section) {
            ContentSection::create([
                'page' => 'community',
                'heading' => $section['heading'],
                'body' => $section['body'],
                'sort_order' => $order,
            ]);
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/ContentSectionSeederTest.php`
Expected: PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: seeder moving hard-coded community intro into content sections"
```

---

### Task 8: Public rendering + `.kh-prose` stylesheet

**Files:**
- Create: `resources/views/funnel/partials/content-sections.blade.php`
- Modify: `resources/views/funnel/community.blade.php`, `resources/views/funnel/program.blade.php`, `app/Http/Controllers/CommunityController.php`, `app/Http/Controllers/ProgramPageController.php`, `resources/css/app.css`
- Test: `tests/Feature/ContentSectionPublicTest.php`

**Interfaces:**
- Consumes: `ContentSection::forCommunity()`, `Program::sections()` (Task 2), `ContentSectionSeeder` content shape (Task 7).
- Produces: partial expecting `$sections` (Collection of ContentSection); `.kh-prose` CSS class used later by the admin editor (Task 9).

**REQUIRED before coding the CSS:** invoke `frontend-design:frontend-design`; `.kh-prose` must visually match the current card body style (`text-sm leading-relaxed text-teal-800/80` family) using the brand tokens already defined in `app.css`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Models\ContentSection;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentSectionPublicTest extends TestCase
{
    use RefreshDatabase;

    public function test_community_page_renders_sections_in_order(): void
    {
        ContentSection::factory()->create(['heading' => 'Kedua', 'sort_order' => 1]);
        ContentSection::factory()->create([
            'heading' => 'Pertama',
            'body' => '<p>Isi <strong>penting</strong></p>',
            'sort_order' => 0,
        ]);

        $this->get('/komunitas')
            ->assertOk()
            ->assertSeeInOrder(['Pertama', 'Kedua'])
            ->assertSee('<strong>penting</strong>', false);
    }

    public function test_community_page_still_works_with_zero_sections(): void
    {
        $this->get('/komunitas')->assertOk()->assertSee('Kheedma Affiliate Community.');
    }

    public function test_program_page_renders_sections_when_present(): void
    {
        $program = Program::factory()->create(['status' => 'active', 'description' => 'Deskripsi lama']);
        ContentSection::factory()->forProgram($program)->create([
            'heading' => 'Apa yang kamu pelajari',
            'body' => '<p>Materi lengkap</p>',
        ]);

        $this->get("/program/{$program->slug}")
            ->assertOk()
            ->assertSee('Apa yang kamu pelajari')
            ->assertSee('Materi lengkap')
            ->assertDontSee('Deskripsi lama');
    }

    public function test_program_page_falls_back_to_description(): void
    {
        $program = Program::factory()->create(['status' => 'active', 'description' => 'Deskripsi lama']);

        $this->get("/program/{$program->slug}")->assertOk()->assertSee('Deskripsi lama');
    }
}
```

Note: check `ProgramFactory` for required states (`status`, `type`) used by `ProgramDetailTest` and mirror its setup if plain `create()` fails eligibility/visibility checks.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/ContentSectionPublicTest.php`
Expected: `test_community_page_still_works_with_zero_sections` and `test_program_page_falls_back_to_description` may already PASS; the other two FAIL (sections not rendered).

- [ ] **Step 3: Implement partial, controller wiring, blade edits, CSS**

`resources/views/funnel/partials/content-sections.blade.php`:

```blade
@foreach ($sections as $section)
    <div class="rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur sm:p-8">
        @if ($section->heading)
            <h2 class="text-lg font-bold text-teal-900">{{ $section->heading }}</h2>
        @endif
        {{-- Body is sanitized on write (SectionBodySanitizer); raw output is safe. --}}
        <div @class(['kh-prose', 'mt-2' => $section->heading])>{!! $section->body !!}</div>
    </div>
@endforeach
```

`app/Http/Controllers/CommunityController.php` — in `show()`, add before the `return`:

```php
$sections = $focusedEdit ? collect() : ContentSection::forCommunity()->get();
```

and include `'sections'` in the `compact(...)` call. Import `App\Models\ContentSection`.

`resources/views/funnel/community.blade.php` — replace the three hard-coded cards (the whole `<div class="mt-10 space-y-4">…</div>` block, lines ~25–81, inside `@unless ($focusedEdit)`) with:

```blade
<div class="mt-10 space-y-4">
    @include('funnel.partials.content-sections', ['sections' => $sections])
</div>
```

(The closing paragraph "Isi formulir di bawah…" and everything after stays untouched.)

`app/Http/Controllers/ProgramPageController.php` — in `show()`, add to the view data array:

```php
'sections' => $program->sections,
```

`resources/views/funnel/program.blade.php` — replace the `@if ($program->description)` block with:

```blade
@if ($sections->isNotEmpty())
    <div class="mt-10 space-y-4">
        @include('funnel.partials.content-sections', ['sections' => $sections])
    </div>
@elseif ($program->description)
    <div class="mt-10 rounded-3xl border border-teal-900/10 bg-white/70 p-6 text-sm leading-relaxed text-teal-800/90 shadow-sm backdrop-blur sm:p-8">
        {!! nl2br(e($program->description)) !!}
    </div>
@endif
```

`resources/css/app.css` — append after the shadcn token blocks:

```css
/*
 | Managed rich-text content (content sections). One source of truth for the
 | admin Tiptap editor canvas AND the public section cards — this is what
 | keeps the editor preview identical to the live page.
*/
.kh-prose {
    font-size: 0.875rem;
    line-height: 1.625;
    color: color-mix(in srgb, var(--color-teal-800) 85%, transparent);
}
.kh-prose > * + * {
    margin-top: 0.625rem;
}
.kh-prose ul,
.kh-prose ol {
    padding-left: 1.25rem;
}
.kh-prose ul {
    list-style: disc;
}
.kh-prose ol {
    list-style: decimal;
}
.kh-prose li + li {
    margin-top: 0.375rem;
}
.kh-prose strong {
    color: var(--color-teal-900);
    font-weight: 600;
}
.kh-prose a {
    color: var(--color-teal-700);
    font-weight: 600;
    text-decoration: underline;
    text-underline-offset: 4px;
}
.kh-prose a:hover {
    color: var(--color-orange-600);
}
.kh-prose img {
    max-width: 100%;
    height: auto;
    border-radius: 1rem;
}
```

- [ ] **Step 4: Run the new tests + touched-page suites**

Run: `php artisan test --compact tests/Feature/ContentSectionPublicTest.php tests/Feature/CommunityJoinTest.php tests/Feature/ProgramDetailTest.php tests/Feature/PublicNavTest.php`
Expected: PASS. If a pre-existing test asserted the hard-coded card copy on `/komunitas`, seed `ContentSectionSeeder` in that test's setup (content moved, not deleted) — do not delete assertions.

- [ ] **Step 5: Build assets**

Run: `npm run build`
Expected: success (CSS compiles).

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: public pages render managed content sections with kh-prose styling"
```

---

### Task 9: Admin API client + rich text editor + media picker (Vue)

**REQUIRED before coding:** invoke `vue-best-practices` (read its core references) and `frontend-design:frontend-design`. Project convention: plain JS `<script setup>` (NOT TypeScript), flat component directory, shadcn-vue ui kit, lucide icons.

**Files:**
- Modify: `package.json` (npm install), `resources/js/admin/api.js`
- Create: `resources/js/admin/components/RichTextEditor.vue`, `resources/js/admin/components/MediaGrid.vue`, `resources/js/admin/components/MediaPickerDialog.vue`

**Interfaces:**
- Consumes: API endpoints from Tasks 5–6; `apiUpload()` (exists in `api.js`); `.kh-prose` (Task 8).
- Produces:
  - `api.js` exports `contentSections` (`list(params)`, `create(payload)`, `update(id, payload)`, `remove(id)`, `reorder(payload)`) and `media` (`list(params)`, `upload(file)`, `update(id, payload)`, `remove(id)`) — align method names with the existing `users`/`programs` exports if they differ.
  - `RichTextEditor.vue` — `v-model` (HTML string), emits nothing else; renders toolbar + white `.kh-prose` canvas; image button opens `MediaPickerDialog`.
  - `MediaGrid.vue` — props `{ picker: Boolean }`; emits `select(item)` in picker mode; full manager (copy link, edit alt, delete) otherwise. Item shape = media payload from Task 6.
  - `MediaPickerDialog.vue` — `v-model:open`, emits `picked(item)`.

- [ ] **Step 1: Install editor dependencies**

```bash
npm install @tiptap/vue-3 @tiptap/starter-kit @tiptap/extension-link @tiptap/extension-image
```

Note the installed major version (`npm ls @tiptap/vue-3`). The code below targets the v2 API; on Tiptap v3 two calls differ — `setContent(value, { emitUpdate: false })` (options object instead of boolean) and StarterKit option names may vary. Check the installed version's docs before Step 5 and adjust only those call sites.

- [ ] **Step 2: Add API client modules**

In `resources/js/admin/api.js`, after the existing resource exports (match their naming style exactly — read the `users` and `programs` exports first):

```js
export const contentSections = {
    list(params) {
        return api(`/admin/content-sections?${new URLSearchParams(params)}`);
    },
    create(payload) {
        return api('/admin/content-sections', { method: 'POST', body: payload });
    },
    update(id, payload) {
        return api(`/admin/content-sections/${id}`, { method: 'PATCH', body: payload });
    },
    remove(id) {
        return api(`/admin/content-sections/${id}`, { method: 'DELETE' });
    },
    reorder(payload) {
        return api('/admin/content-sections-order', { method: 'PATCH', body: payload });
    },
};

export const media = {
    list(params = {}) {
        return api(`/admin/media?${new URLSearchParams(params)}`);
    },
    upload(file) {
        const formData = new FormData();
        formData.append('file', file);
        return apiUpload('/admin/media', formData);
    },
    update(id, payload) {
        return api(`/admin/media/${id}`, { method: 'PATCH', body: payload });
    },
    remove(id) {
        return api(`/admin/media/${id}`, { method: 'DELETE' });
    },
};
```

- [ ] **Step 3: Create `MediaGrid.vue`**

Responsibility: fetch + render the media grid; upload; per-item actions. Used by the Media view (manager mode) and the picker dialog (picker mode).

```vue
<script setup>
import { ref, watch, onMounted } from 'vue';
import { UploadCloud, Copy, Check, Trash2, FileText, Pencil } from 'lucide-vue-next';
import { media as mediaApi } from '@/api';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

const props = defineProps({
    /** Picker mode: images only, click = select, no destructive actions. */
    picker: { type: Boolean, default: false },
});

const emit = defineEmits(['select']);

const items = ref([]);
const meta = ref(null);
const search = ref('');
const loading = ref(false);
const uploading = ref(false);
const error = ref('');
const copiedId = ref(null);

async function load(page = 1) {
    loading.value = true;
    error.value = '';
    try {
        const params = { page };
        if (props.picker) params.type = 'image';
        if (search.value) params.search = search.value;
        const response = await mediaApi.list(params);
        items.value = page === 1 ? response.data : [...items.value, ...response.data];
        meta.value = { current: response.current_page, last: response.last_page };
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

let searchTimer = null;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => load(1), 300);
});

onMounted(() => load(1));

async function onFilesChosen(fileList) {
    error.value = '';
    uploading.value = true;
    try {
        for (const file of fileList) {
            await mediaApi.upload(file);
        }
        await load(1);
    } catch (e) {
        error.value = e.errors?.file?.[0] ?? e.message;
    } finally {
        uploading.value = false;
    }
}

function onDrop(event) {
    if (event.dataTransfer?.files?.length) onFilesChosen(event.dataTransfer.files);
}

async function copyLink(item) {
    await navigator.clipboard.writeText(window.location.origin + item.url);
    copiedId.value = item.id;
    setTimeout(() => (copiedId.value = null), 1500);
}

async function editAlt(item) {
    const alt = window.prompt('Teks alternatif gambar (untuk aksesibilitas):', item.alt_text ?? '');
    if (alt === null) return;
    const { media: updated } = await mediaApi.update(item.id, { alt_text: alt });
    Object.assign(item, updated);
}

const deletingItem = ref(null);
async function confirmDelete(item) {
    error.value = '';
    try {
        await mediaApi.remove(item.id);
        items.value = items.value.filter((m) => m.id !== item.id);
    } catch (e) {
        error.value = e.message;
    } finally {
        deletingItem.value = null;
    }
}

function formatSize(bytes) {
    return bytes >= 1_048_576 ? `${(bytes / 1_048_576).toFixed(1)} MB` : `${Math.round(bytes / 1024)} KB`;
}
</script>

<template>
    <div @dragover.prevent @drop.prevent="onDrop">
        <div class="flex flex-wrap items-center gap-3">
            <Input v-model="search" placeholder="Cari nama file…" class="max-w-xs" />
            <label class="inline-flex cursor-pointer items-center gap-2 rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground hover:opacity-90">
                <UploadCloud class="h-4 w-4" />
                {{ uploading ? 'Mengunggah…' : 'Unggah File' }}
                <input
                    type="file"
                    class="sr-only"
                    multiple
                    :accept="picker ? 'image/*' : 'image/*,application/pdf'"
                    :disabled="uploading"
                    @change="onFilesChosen($event.target.files); $event.target.value = ''"
                >
            </label>
            <p class="text-xs text-muted-foreground">Bisa juga seret dan lepas file ke sini. Maks 5 MB per file.</p>
        </div>

        <p v-if="error" class="mt-3 rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
            {{ error }}
        </p>

        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            <div
                v-for="item in items"
                :key="item.id"
                class="group overflow-hidden rounded-lg border bg-card"
                :class="picker && 'cursor-pointer transition hover:ring-2 hover:ring-ring'"
                @click="picker && emit('select', item)"
            >
                <img v-if="item.is_image" :src="item.url" :alt="item.alt_text ?? item.original_name" class="aspect-square w-full object-cover">
                <div v-else class="flex aspect-square w-full items-center justify-center bg-secondary">
                    <FileText class="h-10 w-10 text-muted-foreground" />
                </div>
                <div class="p-2">
                    <p class="truncate text-xs font-medium" :title="item.original_name">{{ item.original_name }}</p>
                    <p class="text-xs text-muted-foreground">{{ formatSize(item.size) }}</p>
                    <div v-if="!picker" class="mt-1.5 flex gap-1">
                        <Button variant="ghost" size="icon" class="h-7 w-7" :title="copiedId === item.id ? 'Tersalin!' : 'Salin link'" @click="copyLink(item)">
                            <Check v-if="copiedId === item.id" class="h-3.5 w-3.5 text-teal-600" />
                            <Copy v-else class="h-3.5 w-3.5" />
                        </Button>
                        <Button v-if="item.is_image" variant="ghost" size="icon" class="h-7 w-7" title="Ubah teks alternatif" @click="editAlt(item)">
                            <Pencil class="h-3.5 w-3.5" />
                        </Button>
                        <Button
                            variant="ghost" size="icon" class="h-7 w-7 text-destructive"
                            :title="deletingItem === item.id ? 'Klik lagi untuk hapus' : 'Hapus'"
                            @click="deletingItem === item.id ? confirmDelete(item) : (deletingItem = item.id)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <p v-if="!loading && items.length === 0" class="mt-8 text-center text-sm text-muted-foreground">
            Belum ada file. Unggah file pertamamu di atas.
        </p>

        <div v-if="meta && meta.current < meta.last" class="mt-4 text-center">
            <Button variant="outline" :disabled="loading" @click="load(meta.current + 1)">Muat lebih banyak</Button>
        </div>
    </div>
</template>
```

Before writing, check `@/components/ui/button` and `@/components/ui/dialog` export names in an existing consumer (e.g. `ProgramFormDialog.vue`) and match import style. The two-click delete (`deletingItem`) avoids a nested confirm dialog inside picker contexts; if the codebase already has a confirm-dialog component, use it instead.

- [ ] **Step 4: Create `MediaPickerDialog.vue`**

```vue
<script setup>
import { Dialog } from '@/components/ui/dialog';
import MediaGrid from '@/components/MediaGrid.vue';

const open = defineModel('open', { type: Boolean, default: false });

const emit = defineEmits(['picked']);

function onSelect(item) {
    emit('picked', item);
    open.value = false;
}
</script>

<template>
    <Dialog v-model:open="open" title="Pilih Gambar" description="Klik gambar untuk menyisipkannya ke konten.">
        <MediaGrid picker @select="onSelect" />
    </Dialog>
</template>
```

(Match the actual `Dialog` API used by `CohortFormDialog.vue` — if it uses `DialogContent`/`DialogHeader` subcomponents, mirror that structure.)

- [ ] **Step 5: Create `RichTextEditor.vue`**

```vue
<script setup>
import { ref, watch, onBeforeUnmount } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import { Bold, Italic, List, ListOrdered, Link2, ImagePlus } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import MediaPickerDialog from '@/components/MediaPickerDialog.vue';

/**
 * Schema-constrained rich text editor. Only the nodes/marks the public
 * .kh-prose renderer supports exist in the schema, so pasted content is
 * normalized and the editor canvas IS the public preview.
 */
const model = defineModel({ type: String, default: '' });

const pickerOpen = ref(false);
const linkFormOpen = ref(false);
const linkUrl = ref('');

const editor = useEditor({
    content: model.value,
    extensions: [
        StarterKit.configure({
            heading: false,
            blockquote: false,
            code: false,
            codeBlock: false,
            horizontalRule: false,
            strike: false,
        }),
        Link.configure({ openOnClick: false }),
        Image,
    ],
    editorProps: {
        attributes: { class: 'kh-prose min-h-40 focus:outline-none' },
    },
    onUpdate: ({ editor: instance }) => {
        model.value = instance.getHTML();
    },
});

// External model swaps (dialog re-seeded for another section) reset the doc.
watch(model, (value) => {
    if (editor.value && editor.value.getHTML() !== value) {
        editor.value.commands.setContent(value || '', false);
    }
});

onBeforeUnmount(() => editor.value?.destroy());

function toggleLinkForm() {
    if (editor.value.isActive('link')) {
        editor.value.chain().focus().unsetLink().run();
        return;
    }
    linkUrl.value = '';
    linkFormOpen.value = !linkFormOpen.value;
}

function applyLink() {
    if (linkUrl.value) {
        const chain = editor.value.chain().focus().extendMarkRange('link');
        if (editor.value.state.selection.empty) {
            // No selection: insert the URL as its own link text (covers
            // pasting a copied media-file link, e.g. a PDF from Media).
            chain.insertContent(`<a href="${linkUrl.value}">${linkUrl.value}</a>`).run();
        } else {
            chain.setLink({ href: linkUrl.value }).run();
        }
    }
    linkFormOpen.value = false;
}

function insertImage(item) {
    editor.value.chain().focus().setImage({ src: item.url, alt: item.alt_text ?? '' }).run();
}

const buttons = [
    { icon: Bold, title: 'Tebal', isActive: () => editor.value?.isActive('bold'), run: () => editor.value.chain().focus().toggleBold().run() },
    { icon: Italic, title: 'Miring', isActive: () => editor.value?.isActive('italic'), run: () => editor.value.chain().focus().toggleItalic().run() },
    { icon: List, title: 'Daftar', isActive: () => editor.value?.isActive('bulletList'), run: () => editor.value.chain().focus().toggleBulletList().run() },
    { icon: ListOrdered, title: 'Daftar bernomor', isActive: () => editor.value?.isActive('orderedList'), run: () => editor.value.chain().focus().toggleOrderedList().run() },
];
</script>

<template>
    <div class="rounded-lg border border-input bg-white">
        <div class="flex flex-wrap items-center gap-1 border-b border-input p-1.5">
            <Button
                v-for="button in buttons"
                :key="button.title"
                type="button"
                variant="ghost"
                size="icon"
                class="h-8 w-8"
                :class="button.isActive() && 'bg-accent text-accent-foreground'"
                :title="button.title"
                @click="button.run"
            >
                <component :is="button.icon" class="h-4 w-4" />
            </Button>
            <Button
                type="button" variant="ghost" size="icon" class="h-8 w-8"
                :class="editor?.isActive('link') && 'bg-accent text-accent-foreground'"
                title="Tautan" @click="toggleLinkForm"
            >
                <Link2 class="h-4 w-4" />
            </Button>
            <Button type="button" variant="ghost" size="icon" class="h-8 w-8" title="Sisipkan gambar" @click="pickerOpen = true">
                <ImagePlus class="h-4 w-4" />
            </Button>
        </div>

        <div v-if="linkFormOpen" class="flex items-center gap-2 border-b border-input p-2">
            <Input v-model="linkUrl" placeholder="https://…" class="h-8 text-sm" @keydown.enter.prevent="applyLink" />
            <Button type="button" size="sm" @click="applyLink">Pasang</Button>
        </div>

        {{-- White canvas: same .kh-prose as the public page = true WYSIWYG. --}}
        <div class="rounded-b-lg bg-white px-4 py-3">
            <EditorContent :editor="editor" />
        </div>

        <MediaPickerDialog v-model:open="pickerOpen" @picked="insertImage" />
    </div>
</template>
```

(Remove the Blade-style comment above — Vue uses `<!-- -->`; shown here for emphasis only. In the real file write `<!-- White canvas: same .kh-prose as the public page = true WYSIWYG. -->`.)

- [ ] **Step 6: Build to verify**

Run: `npm run build`
Expected: success, no unresolved imports.

- [ ] **Step 7: Commit**

```bash
git add -A && git commit -m "feat: schema-constrained tiptap editor + media grid/picker components"
```

---

### Task 10: Media manager view + routing + sidebar

**REQUIRED before coding:** `vue-best-practices` + `frontend-design:frontend-design` (if not already loaded this session).

**Files:**
- Create: `resources/js/admin/views/Media.vue`
- Modify: `resources/js/admin/router.js`, `resources/js/admin/components/AppShell.vue`

**Interfaces:**
- Consumes: `MediaGrid.vue` (Task 9), `auth.can('content.manage')` (existing store), permission from Task 4.
- Produces: route `media` at path `/media`.

- [ ] **Step 1: Create the view**

Route views stay thin (composition surface). Mirror the page-header markup of an existing simple view (open `views/Users.vue` or `views/Community.vue` and copy its header/container structure exactly):

```vue
<script setup>
import MediaGrid from '@/components/MediaGrid.vue';
</script>

<template>
    <div>
        <div class="mb-6">
            <h1 class="text-2xl font-bold tracking-tight">Media</h1>
            <p class="text-sm text-muted-foreground">
                Kelola foto dan file untuk konten halaman. Salin link untuk dipakai di tempat lain.
            </p>
        </div>
        <MediaGrid />
    </div>
</template>
```

- [ ] **Step 2: Register the route**

In `resources/js/admin/router.js`, add to the AppShell children (after `users`):

```js
{
    path: 'media',
    name: 'media',
    component: () => import('./views/Media.vue'),
    meta: { permission: 'content.manage' },
},
```

- [ ] **Step 3: Add the sidebar entry**

In `resources/js/admin/components/AppShell.vue`, import `Images` from `lucide-vue-next` and add to the `nav` computed (after the `users` entry):

```js
{ to: { name: 'media' }, match: '/media', label: 'Media', short: 'Media', icon: Images, mobilePrimary: false, show: auth.can('content.manage') },
```

- [ ] **Step 4: Build to verify**

Run: `npm run build`
Expected: success.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: media manager page in admin"
```

---

### Task 11: Content sections editor view + routing + sidebar

**REQUIRED before coding:** `vue-best-practices` + `frontend-design:frontend-design` (if not already loaded this session).

**Files:**
- Create: `resources/js/admin/views/Content.vue`, `resources/js/admin/components/SectionFormDialog.vue`
- Modify: `resources/js/admin/router.js`, `resources/js/admin/components/AppShell.vue`

**Interfaces:**
- Consumes: `contentSections` + `programs` api modules, `RichTextEditor.vue` (Task 9), section payload shape (Task 5).
- Produces: route `content` at path `/content`.

- [ ] **Step 1: Create `SectionFormDialog.vue`**

Follows the `ProgramFormDialog.vue` conventions (`defineModel('open')`, re-seed form on open, `formErrors` from `err.errors`):

```vue
<script setup>
import { ref, computed, watch } from 'vue';
import { contentSections as sectionsApi } from '@/api';
import { Dialog } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import RichTextEditor from '@/components/RichTextEditor.vue';

const props = defineProps({
    /** Section (API shape) to edit; null = create mode. */
    section: { type: Object, default: null },
    /** Owner page for create mode: 'community' or 'program'. */
    page: { type: String, required: true },
    /** Program id, required when page === 'program'. */
    programId: { type: Number, default: null },
});

const open = defineModel('open', { type: Boolean, default: false });

const emit = defineEmits(['saved']);

const isEditing = computed(() => props.section !== null);

const form = ref({ heading: '', body: '' });
const formErrors = ref({});
const saving = ref(false);

watch(open, (isOpen) => {
    if (!isOpen) return;
    form.value = {
        heading: props.section?.heading ?? '',
        body: props.section?.body ?? '',
    };
    formErrors.value = {};
});

async function save() {
    saving.value = true;
    formErrors.value = {};
    try {
        if (isEditing.value) {
            await sectionsApi.update(props.section.id, form.value);
        } else {
            await sectionsApi.create({
                page: props.page,
                ...(props.page === 'program' ? { program_id: props.programId } : {}),
                ...form.value,
            });
        }
        emit('saved');
        open.value = false;
    } catch (e) {
        formErrors.value = e.errors ?? { body: [e.message] };
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open" :title="isEditing ? 'Ubah Section' : 'Tambah Section'">
        <form class="space-y-4" @submit.prevent="save">
            <div>
                <label class="mb-1.5 block text-sm font-medium">Judul <span class="text-muted-foreground">(opsional)</span></label>
                <Input v-model="form.heading" placeholder="Judul kartu, mis. Jadwal belajar" />
                <p v-if="formErrors.heading" class="mt-1 text-xs text-destructive">{{ formErrors.heading[0] }}</p>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium">Isi</label>
                <RichTextEditor v-model="form.body" />
                <p v-if="formErrors.body" class="mt-1 text-xs text-destructive">{{ formErrors.body[0] }}</p>
            </div>
            <div class="flex justify-end gap-2">
                <Button type="button" variant="outline" @click="open = false">Batal</Button>
                <Button type="submit" :disabled="saving">{{ saving ? 'Menyimpan…' : 'Simpan' }}</Button>
            </div>
        </form>
    </Dialog>
</template>
```

(As in Task 9: adapt the `Dialog` usage to the exact API `CohortFormDialog.vue` uses.)

- [ ] **Step 2: Create `Content.vue`**

```vue
<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { ArrowUp, ArrowDown, Pencil, Trash2, Plus } from 'lucide-vue-next';
import { contentSections as sectionsApi, programs as programsApi } from '@/api';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import SectionFormDialog from '@/components/SectionFormDialog.vue';

const programs = ref([]);
const selected = ref('community'); // 'community' | program id as string
const sections = ref([]);
const loading = ref(false);
const error = ref('');

const dialogOpen = ref(false);
const editingSection = ref(null);
const deletingId = ref(null);

const page = computed(() => (selected.value === 'community' ? 'community' : 'program'));
const programId = computed(() => (page.value === 'program' ? Number(selected.value) : null));

const listParams = computed(() => ({
    page: page.value,
    ...(programId.value ? { program_id: programId.value } : {}),
}));

async function loadPrograms() {
    const response = await programsApi.list();
    programs.value = response.programs ?? response.data ?? response;
}

async function loadSections() {
    loading.value = true;
    error.value = '';
    try {
        const response = await sectionsApi.list(listParams.value);
        sections.value = response.sections;
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    loadPrograms();
    loadSections();
});
watch(selected, loadSections);

function openCreate() {
    editingSection.value = null;
    dialogOpen.value = true;
}

function openEdit(section) {
    editingSection.value = section;
    dialogOpen.value = true;
}

async function removeSection(section) {
    error.value = '';
    try {
        await sectionsApi.remove(section.id);
        await loadSections();
    } catch (e) {
        error.value = e.message;
    } finally {
        deletingId.value = null;
    }
}

async function move(index, delta) {
    const ids = sections.value.map((s) => s.id);
    const [id] = ids.splice(index, 1);
    ids.splice(index + delta, 0, id);
    try {
        await sectionsApi.reorder({ ...listParams.value, ids });
        await loadSections();
    } catch (e) {
        error.value = e.message;
    }
}
</script>

<template>
    <div>
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Konten Halaman</h1>
                <p class="text-sm text-muted-foreground">
                    Susun kartu konten yang tampil di halaman publik. Urutan di sini = urutan tampil.
                </p>
            </div>
            <Button @click="openCreate"><Plus class="mr-1.5 h-4 w-4" /> Tambah Section</Button>
        </div>

        <div class="mb-4 max-w-xs">
            <label class="mb-1.5 block text-sm font-medium">Halaman</label>
            <NativeSelect v-model="selected">
                <option value="community">Komunitas (/komunitas)</option>
                <option v-for="program in programs" :key="program.id" :value="String(program.id)">
                    Program: {{ program.name }}
                </option>
            </NativeSelect>
        </div>

        <p v-if="error" class="mb-4 rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
            {{ error }}
        </p>

        <div class="space-y-3">
            <div
                v-for="(section, index) in sections"
                :key="section.id"
                class="rounded-lg border bg-card p-4"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 v-if="section.heading" class="font-semibold">{{ section.heading }}</h2>
                        <p v-else class="text-sm italic text-muted-foreground">(tanpa judul)</p>
                        <div class="kh-prose mt-2 max-h-32 overflow-hidden rounded bg-white p-3" v-html="section.body" />
                    </div>
                    <div class="flex shrink-0 gap-1">
                        <Button variant="ghost" size="icon" class="h-8 w-8" title="Naikkan" :disabled="index === 0" @click="move(index, -1)">
                            <ArrowUp class="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="icon" class="h-8 w-8" title="Turunkan" :disabled="index === sections.length - 1" @click="move(index, 1)">
                            <ArrowDown class="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="icon" class="h-8 w-8" title="Ubah" @click="openEdit(section)">
                            <Pencil class="h-4 w-4" />
                        </Button>
                        <Button
                            variant="ghost" size="icon" class="h-8 w-8 text-destructive"
                            :title="deletingId === section.id ? 'Klik lagi untuk hapus' : 'Hapus'"
                            @click="deletingId === section.id ? removeSection(section) : (deletingId = section.id)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <p v-if="!loading && sections.length === 0" class="mt-8 text-center text-sm text-muted-foreground">
            Belum ada konten di halaman ini. Klik "Tambah Section" untuk memulai.
        </p>

        <SectionFormDialog
            v-model:open="dialogOpen"
            :section="editingSection"
            :page="page"
            :program-id="programId"
            @saved="loadSections"
        />
    </div>
</template>
```

Notes: `v-html` here renders admin-authored, server-sanitized HTML — same trust level as the public page. Check the actual response shape of `programsApi.list()` (open `views/Programs.vue`) and the `NativeSelect` API before wiring.

- [ ] **Step 3: Register route + sidebar entry**

`router.js` (after `programs/:id`):

```js
{
    path: 'content',
    name: 'content',
    component: () => import('./views/Content.vue'),
    meta: { permission: 'content.manage' },
},
```

`AppShell.vue` nav (before the `media` entry; import `LayoutTemplate` from lucide):

```js
{ to: { name: 'content' }, match: '/content', label: 'Konten Halaman', short: 'Konten', icon: LayoutTemplate, mobilePrimary: false, show: auth.can('content.manage') },
```

- [ ] **Step 4: Build to verify**

Run: `npm run build`
Expected: success.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: content sections editor page in admin"
```

---

### Task 12: Full verification

- [ ] **Step 1: Full test suite**

Run: `php artisan test --compact`
Expected: PASS, zero failures. Fix regressions before proceeding.

- [ ] **Step 2: Pint over the branch**

Run: `vendor/bin/pint --dirty --format agent`
Expected: clean (commit any fixes).

- [ ] **Step 3: Fresh build**

Run: `npm run build`
Expected: success.

- [ ] **Step 4: End-to-end manual verification**

Use the project `verify` skill (boot recipe + Playwright drive). Walk through:

1. Run `php artisan db:seed --class=ContentSectionSeeder` (local), open `/komunitas` — the three intro cards render from DB, visually unchanged vs. before.
2. Log in to `/admin` as admin → sidebar shows "Konten Halaman" and "Media".
3. Media: upload a JPG, copy its link, set alt text.
4. Konten Halaman: pick a program page, add a section, bold text, bullet list, insert the uploaded image via the toolbar picker.
5. Open the public `/program/{slug}` — the section renders; typography and image match what the editor showed (WYSIWYG check).
6. Reorder sections with the arrows; public order follows.
7. Try deleting the used image in Media — rejected with the section named; delete an unused file — succeeds.
8. Log in as a mentor account — no Konten/Media sidebar entries; direct `/media` navigation is blocked by the router guard.

- [ ] **Step 5: Commit any fixes, then hand off**

Use `superpowers:finishing-a-development-branch` (merge to `main` per project habit of merge commits).

**Deploy notes (for the release checklist):** run `php artisan migrate` AND `php artisan db:seed --class=ContentSectionSeeder --force`; `composer install` picks up symfony/html-sanitizer; `npm run build` required; `php artisan permission:cache-reset` (or the deploy's config/cache reset) so `content.manage` is seen — re-run `php artisan db:seed --class=PermissionSeeder --force` to create it in production.
