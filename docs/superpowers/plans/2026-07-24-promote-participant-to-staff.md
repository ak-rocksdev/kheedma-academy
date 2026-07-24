# Promote Participant to Staff — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an admin promote a participant account to mentor/admin from the Tim page, via a search dialog, as a pure role switch — introducing reusable admin `ConfirmDialog` and `Toast` components along the way.

**Architecture:** Two new endpoints on the existing `UserController` behind `permission:users.manage` (`GET /admin/users/promotable`, `POST /admin/users/{user}/promote`). Frontend: a `PromoteParticipantDialog` on `Users.vue`, confirmed through a new reusable `ConfirmDialog`, success signalled through a new reusable `Toast` system whose CSS (`.kh-toast*` in `resources/css/app.css`) already exists and is shared with the admin bundle.

**Tech Stack:** Laravel 13, Spatie laravel-permission v8, PHPUnit 12, Vue 3 `<script setup>` (admin SPA), Tailwind v4.

**Spec:** `docs/superpowers/specs/2026-07-23-promote-participant-to-staff-design.md`

## Global Constraints

- Code 100% English (identifiers, routes, comments); UI copy 100% Indonesian, warm register ("kamu"), no em-dashes.
- Promote is a PURE role switch: never touch password, `is_active`, `Person` link, or enrollments.
- One role per user (`syncRoles`); no demote, no multi-role.
- Existing views are NOT refactored onto the new components in this feature.
- `vendor/bin/pint --dirty --format agent` after PHP changes; `npm run build` after frontend changes.
- Tests: PHPUnit classes (no Pest), run with `php artisan test --compact --filter=...`.

---

### Task 1: Backend — promotable search + promote endpoints (TDD)

**Files:**
- Modify: `database/factories/UserFactory.php` (add `participant()` state after `mentor()`, ~line 58)
- Modify: `routes/api.php` (inside the `permission:users.manage` group, line ~40)
- Modify: `app/Http/Controllers/Api/Admin/UserController.php` (two methods before `guardSelfAndLastAdmin`)
- Test: `tests/Feature/UserManagementTest.php` (append tests)

**Interfaces:**
- Produces: `GET /api/admin/users/promotable?q=` → `{data: [{id, name, email, phone}]}` (max 20, ordered by name); `POST /api/admin/users/{user}/promote` body `{role: 'admin'|'mentor'}` → `{user: row()}`; 422 when target is not a participant.

- [ ] **Step 1: Add the `participant()` factory state**

```php
    /** Assign the participant role after creation (roles must be seeded first). */
    public function participant(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole('participant'));
    }
```

- [ ] **Step 2: Write the failing tests** (append to `UserManagementTest.php`)

```php
    public function test_promotable_lists_only_participant_accounts(): void
    {
        $participant = User::factory()->participant()->create(['name' => 'Hafiidh']);
        User::factory()->mentor()->create(['name' => 'Mentor Budi']);

        $this->actingAs($this->admin())
            ->getJson('/api/admin/users/promotable')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $participant->id)
            ->assertJsonPath('data.0.email', $participant->email);
    }

    public function test_promotable_filters_by_query(): void
    {
        User::factory()->participant()->create(['name' => 'Hafiidh Ar Rasyiid']);
        User::factory()->participant()->create(['name' => 'Siti Aminah']);

        $this->actingAs($this->admin())
            ->getJson('/api/admin/users/promotable?q=hafi')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Hafiidh Ar Rasyiid');
    }

    public function test_promotable_requires_users_manage_permission(): void
    {
        User::factory()->participant()->create();

        $this->actingAs(User::factory()->mentor()->create())
            ->getJson('/api/admin/users/promotable')
            ->assertForbidden();
    }

    public function test_admin_can_promote_a_participant_to_mentor(): void
    {
        $participant = User::factory()->participant()->create();
        $originalPasswordHash = $participant->password;

        $this->actingAs($this->admin())
            ->postJson("/api/admin/users/{$participant->id}/promote", ['role' => 'mentor'])
            ->assertOk()
            ->assertJsonPath('user.role', 'mentor')
            ->assertJsonPath('user.id', $participant->id);

        $fresh = $participant->fresh();
        $this->assertSame(['mentor'], $fresh->getRoleNames()->all());
        $this->assertSame($originalPasswordHash, $fresh->password);
    }

    public function test_admin_can_promote_a_participant_to_admin(): void
    {
        $participant = User::factory()->participant()->create();

        $this->actingAs($this->admin())
            ->postJson("/api/admin/users/{$participant->id}/promote", ['role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('user.role', 'admin');

        $this->assertSame(['admin'], $participant->fresh()->getRoleNames()->all());
    }

    public function test_cannot_promote_an_account_that_is_already_staff(): void
    {
        $mentor = User::factory()->mentor()->create();

        $this->actingAs($this->admin())
            ->postJson("/api/admin/users/{$mentor->id}/promote", ['role' => 'admin'])
            ->assertStatus(422);

        $this->assertSame(['mentor'], $mentor->fresh()->getRoleNames()->all());
    }

    public function test_promote_rejects_an_invalid_role(): void
    {
        $participant = User::factory()->participant()->create();

        $this->actingAs($this->admin())
            ->postJson("/api/admin/users/{$participant->id}/promote", ['role' => 'participant'])
            ->assertStatus(422);
    }

    public function test_promote_requires_users_manage_permission(): void
    {
        $participant = User::factory()->participant()->create();

        $this->actingAs(User::factory()->mentor()->create())
            ->postJson("/api/admin/users/{$participant->id}/promote", ['role' => 'mentor'])
            ->assertForbidden();
    }
```

- [ ] **Step 3: Run to verify they fail**

Run: `php artisan test --compact tests/Feature/UserManagementTest.php`
Expected: the 8 new tests FAIL with 404 (routes missing); existing ones still pass.

- [ ] **Step 4: Add the routes** (`routes/api.php`, inside the `users.manage` group; `promotable` before any future `{user}` GET)

```php
        Route::middleware('permission:users.manage')->group(function () {
            Route::get('/users', [UserController::class, 'index']);
            Route::get('/users/promotable', [UserController::class, 'promotable']);
            Route::post('/users', [UserController::class, 'store']);
            Route::post('/users/{user}/promote', [UserController::class, 'promote']);
            Route::patch('/users/{user}', [UserController::class, 'update']);
            Route::delete('/users/{user}', [UserController::class, 'destroy']);
        });
```

- [ ] **Step 5: Implement the controller methods** (in `UserController`, after `destroy`)

```php
    /** Participant accounts eligible for promotion, searchable by name/email/phone. */
    public function promotable(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $users = User::query()
            ->role('participant')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term));
            })
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
            ]);

        return response()->json(['data' => $users]);
    }

    /** Promote a participant account to staff: a pure role switch, nothing else changes. */
    public function promote(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'role' => ['required', 'in:admin,mentor'],
        ]);

        if (! $user->hasRole('participant')) {
            throw ValidationException::withMessages(['user' => 'Akun ini sudah staf.']);
        }

        $user->syncRoles([$data['role']]);

        return response()->json(['user' => $this->row($user->fresh())]);
    }
```

- [ ] **Step 6: Run the file's tests — all green**

Run: `php artisan test --compact tests/Feature/UserManagementTest.php`
Expected: PASS (all, old and new).

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: promotable search + promote endpoints for participant accounts"
```

---

### Task 2: Reusable `ConfirmDialog` ui component

**Files:**
- Create: `resources/js/admin/components/ui/confirm-dialog/ConfirmDialog.vue`
- Create: `resources/js/admin/components/ui/confirm-dialog/index.js`

**Interfaces:**
- Produces: `<ConfirmDialog v-model:open :title :confirm-label :cancel-label :variant :busy @confirm>` with the message as default slot. `variant` forwards to `Button` (`'default' | 'destructive'`).

- [ ] **Step 1: Create the component**

```vue
<script setup>
// House-style confirmation: one question, explicit action buttons. Built on
// Dialog so overlay, Escape, and the close button behave like every dialog.
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';

const open = defineModel('open', { type: Boolean, default: false });

defineProps({
    title: { type: String, required: true },
    confirmLabel: { type: String, default: 'Ya, lanjutkan' },
    cancelLabel: { type: String, default: 'Batal' },
    /** Visual weight of the confirm button: 'default' | 'destructive'. */
    variant: { type: String, default: 'default' },
    /** Disables both buttons while the confirmed action runs. */
    busy: { type: Boolean, default: false },
});

const emit = defineEmits(['confirm']);
</script>

<template>
    <Dialog v-model:open="open" :title="title">
        <div class="space-y-5">
            <div class="text-sm text-muted-foreground"><slot /></div>
            <div class="flex justify-end gap-2">
                <Button variant="outline" :disabled="busy" @click="open = false">{{ cancelLabel }}</Button>
                <Button :variant="variant" :disabled="busy" @click="emit('confirm')">{{ confirmLabel }}</Button>
            </div>
        </div>
    </Dialog>
</template>
```

`index.js`:

```js
export { default as ConfirmDialog } from './ConfirmDialog.vue';
```

- [ ] **Step 2: Verify the Button variants used exist** (`outline`, `destructive`, `default`) in `resources/js/admin/components/ui/button/` — adjust names only if they differ.

- [ ] **Step 3: Commit**

```bash
git add resources/js/admin/components/ui/confirm-dialog && git commit -m "feat: reusable admin ConfirmDialog component"
```

---

### Task 3: Reusable Toast system + viewport in App.vue

**Files:**
- Create: `resources/js/admin/components/ui/toast/use-toast.js`
- Create: `resources/js/admin/components/ui/toast/ToastCard.vue`
- Create: `resources/js/admin/components/ui/toast/ToastViewport.vue`
- Create: `resources/js/admin/components/ui/toast/index.js`
- Modify: `resources/js/admin/App.vue` (mount `<ToastViewport />`; leave the session-renewed pill alone — different shape, migrating it is not trivial)

The `.kh-toast`, `.kh-toast-timer`, `--kh-toast-duration`, and `data-leaving` CSS already exist in `resources/css/app.css` (shared with the admin bundle) — reuse them, do not duplicate.

**Interfaces:**
- Produces: `useToast()` → `{ toasts, dismiss(id), success(msg, opts?), error(msg, opts?), warning(msg, opts?), info(msg, opts?) }`, `opts = { duration?: ms, default 5000 }`. `<ToastViewport />` renders the queue top-right.

- [ ] **Step 1: `use-toast.js`** — module-level state so every importer shares one queue

```js
import { ref } from 'vue';

const toasts = ref([]);
let nextId = 1;

function push(type, message, { duration = 5000 } = {}) {
    toasts.value.push({ id: nextId++, type, message, duration });
}

/** Shared toast queue; <ToastViewport /> in App.vue renders it. */
export function useToast() {
    return {
        toasts,
        dismiss: (id) => (toasts.value = toasts.value.filter((t) => t.id !== id)),
        success: (message, opts) => push('success', message, opts),
        error: (message, opts) => push('error', message, opts),
        warning: (message, opts) => push('warning', message, opts),
        info: (message, opts) => push('info', message, opts),
    };
}
```

- [ ] **Step 2: `ToastCard.vue`** — one card, house look (port of `resources/views/components/toast.blade.php` with lucide icons); the timer bar's `animationend` starts the exit, the exit animation's end removes it; a timeout fallback covers `prefers-reduced-motion` (where the exit animation never fires)

```vue
<script setup>
import { ref } from 'vue';
import { AlertTriangle, Check, Info, X } from 'lucide-vue-next';

const props = defineProps({
    /** {id, type: 'success'|'error'|'warning'|'info', message, duration} */
    toast: { type: Object, required: true },
});
const emit = defineEmits(['dismiss']);

const leaving = ref(false);
let leaveFallback;

function startLeave() {
    if (leaving.value) return;
    leaving.value = true;
    leaveFallback = setTimeout(() => emit('dismiss'), 600);
}

function onAnimationEnd(e) {
    if (e.animationName === 'kh-toast-timer') startLeave();
    if (e.animationName === 'kh-toast-out') {
        clearTimeout(leaveFallback);
        emit('dismiss');
    }
}

const disc = {
    success: 'bg-teal-100 text-teal-700',
    error: 'bg-red-100 text-red-600',
    warning: 'bg-orange-100 text-orange-600',
    info: 'bg-sand-100 text-teal-700',
}[props.toast.type];

const bar = {
    success: 'bg-teal-600',
    error: 'bg-red-500',
    warning: 'bg-orange-500',
    info: 'bg-teal-600/50',
}[props.toast.type];

const icon = { success: Check, error: X, warning: AlertTriangle, info: Info }[props.toast.type];
</script>

<template>
    <div
        role="status"
        class="kh-toast pointer-events-auto w-full max-w-sm overflow-hidden rounded-2xl bg-white shadow-lg ring-1 ring-teal-900/10"
        :data-leaving="leaving || undefined"
        :style="{ '--kh-toast-duration': `${toast.duration}ms` }"
        @animationend="onAnimationEnd"
    >
        <div class="flex items-center gap-3 px-4 py-3">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full" :class="disc">
                <component :is="icon" class="size-5" />
            </span>
            <p class="min-w-0 flex-1 text-sm font-medium leading-snug text-teal-900">{{ toast.message }}</p>
            <button
                type="button"
                aria-label="Tutup"
                class="flex size-7 shrink-0 items-center justify-center rounded-full text-teal-800/40 transition hover:bg-sand-100 hover:text-teal-900"
                @click="startLeave"
            >
                <X class="size-3.5" />
            </button>
        </div>
        <div class="h-1 w-full bg-teal-900/5">
            <div class="kh-toast-timer h-full" :class="bar"></div>
        </div>
    </div>
</template>
```

- [ ] **Step 3: `ToastViewport.vue`**

```vue
<script setup>
import ToastCard from './ToastCard.vue';
import { useToast } from './use-toast';

const { toasts, dismiss } = useToast();
</script>

<template>
    <Teleport to="body">
        <div class="pointer-events-none fixed right-4 top-4 z-[70] flex w-[calc(100vw-2rem)] max-w-sm flex-col items-end gap-2">
            <ToastCard v-for="t in toasts" :key="t.id" :toast="t" @dismiss="dismiss(t.id)" />
        </div>
    </Teleport>
</template>
```

`index.js`:

```js
export { default as ToastViewport } from './ToastViewport.vue';
export { useToast } from './use-toast';
```

- [ ] **Step 4: Mount in `App.vue`** — add `import { ToastViewport } from '@/components/ui/toast';` and `<ToastViewport />` right after `<SessionExpiredDialog ... />`.

- [ ] **Step 5: Commit**

```bash
git add resources/js/admin/components/ui/toast resources/js/admin/App.vue
git commit -m "feat: reusable admin toast system (house style, shared kh-toast CSS)"
```

---

### Task 4: Promote dialog + Users.vue wiring + api.js

**Files:**
- Modify: `resources/js/admin/api.js` (two methods in the `users` block, line ~140)
- Create: `resources/js/admin/components/PromoteParticipantDialog.vue`
- Modify: `resources/js/admin/views/Users.vue` (button + dialog + toast on success)

**Interfaces:**
- Consumes: `usersApi.promotable(q)`, `usersApi.promote(id, {role})` (Task 1 endpoints); `ConfirmDialog` (Task 2); `useToast` (Task 3).
- Produces: `<PromoteParticipantDialog v-model:open @promoted="({user, role}) => ..." />`.

- [ ] **Step 1: api.js methods** (inside `export const users = {...}`)

```js
    promotable(q = '') {
        return api(`/admin/users/promotable${q ? `?q=${encodeURIComponent(q)}` : ''}`);
    },
    promote(id, payload) {
        return api(`/admin/users/${id}/promote`, { method: 'POST', body: payload });
    },
```

- [ ] **Step 2: `PromoteParticipantDialog.vue`** (structural precedent: `EnrollPersonDialog.vue`)

```vue
<script setup>
// Promote a participant login to staff (mentor/admin): search, pick, confirm.
// A pure role switch — the PIN, Person link, and enrollments stay untouched.
import { ref, watch } from 'vue';
import { users as usersApi } from '@/api';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Dialog } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';

const open = defineModel('open', { type: Boolean, default: false });
const emit = defineEmits(['promoted']);

const query = ref('');
const results = ref([]);
const selected = ref(null);
const role = ref('mentor');
const error = ref('');
const confirmOpen = ref(false);
const busy = ref(false);

const roleLabel = { mentor: 'Mentor', admin: 'Admin' };

let debounce;
watch(query, () => {
    clearTimeout(debounce);
    debounce = setTimeout(search, 300);
});

watch(open, (isOpen) => {
    if (!isOpen) return;
    query.value = '';
    results.value = [];
    selected.value = null;
    role.value = 'mentor';
    error.value = '';
    search();
});

async function search() {
    try {
        const res = await usersApi.promotable(query.value);
        results.value = res.data;
    } catch (e) {
        if (!e.sessionExpired) error.value = e.message ?? 'Gagal memuat data.';
    }
}

async function promote() {
    busy.value = true;
    error.value = '';
    try {
        const res = await usersApi.promote(selected.value.id, { role: role.value });
        confirmOpen.value = false;
        open.value = false;
        emit('promoted', { user: res.user, role: role.value });
    } catch (e) {
        if (e.sessionExpired) return;
        confirmOpen.value = false;
        error.value = e.message ?? 'Gagal mengangkat peserta.';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open" title="Angkat dari Peserta">
        <div class="space-y-4">
            <Alert v-if="error" variant="destructive">{{ error }}</Alert>

            <div>
                <label class="mb-1 block text-sm font-medium text-foreground" for="promote-search">Cari peserta</label>
                <Input id="promote-search" v-model="query" placeholder="Nama, email, atau nomor HP" autocomplete="off" />
            </div>

            <ul v-if="results.length" class="max-h-56 divide-y divide-border overflow-y-auto rounded-lg border border-border">
                <li v-for="user in results" :key="user.id">
                    <button
                        type="button"
                        class="flex w-full flex-col items-start gap-0.5 px-3 py-2 text-left transition hover:bg-accent"
                        :class="selected?.id === user.id ? 'bg-accent' : ''"
                        @click="selected = user"
                    >
                        <span class="text-sm font-medium text-foreground">{{ user.name }}</span>
                        <span class="text-xs text-muted-foreground">{{ user.email }}</span>
                    </button>
                </li>
            </ul>
            <p v-else class="rounded-lg border border-dashed border-border px-3 py-4 text-center text-sm text-muted-foreground">
                Tidak ada akun peserta yang cocok.
            </p>

            <div v-if="selected" class="space-y-3 rounded-lg bg-accent/50 p-3">
                <p class="text-sm text-foreground">
                    Angkat <strong>{{ selected.name }}</strong> menjadi:
                </p>
                <NativeSelect v-model="role">
                    <option value="mentor">Mentor</option>
                    <option value="admin">Admin</option>
                </NativeSelect>
                <Button class="w-full" @click="confirmOpen = true">Lanjutkan</Button>
            </div>
        </div>
    </Dialog>

    <ConfirmDialog
        v-model:open="confirmOpen"
        title="Angkat jadi staf?"
        :confirm-label="`Ya, angkat jadi ${roleLabel[role]}`"
        :busy="busy"
        @confirm="promote"
    >
        <template v-if="selected">
            {{ selected.name }} akan menjadi {{ roleLabel[role] }} dan tidak lagi masuk sebagai peserta.
            PIN login tidak berubah.
        </template>
    </ConfirmDialog>
</template>
```

- [ ] **Step 3: Wire into `Users.vue`** — script: import `PromoteParticipantDialog`, `useToast`; add `const promoteOpen = ref(false);` and `const toast = useToast();` and:

```js
function onPromoted({ user, role }) {
    toast.success(`${user.name} sekarang jadi ${role === 'admin' ? 'Admin' : 'Mentor'}.`);
    load();
}
```

Template: next to the existing add-staff button add
`<Button variant="outline" @click="promoteOpen = true">Angkat dari Peserta</Button>`,
and at the bottom `<PromoteParticipantDialog v-model:open="promoteOpen" @promoted="onPromoted" />`.

- [ ] **Step 4: Build + verify in the running app** (Herd: http://kheedma-academy.test — never `php artisan serve`)

```bash
npm run build
```

Then drive with Playwright (per the `verify` skill recipe): log in to `/admin`, open Tim, click "Angkat dari Peserta", search a seeded participant, promote to Mentor, confirm: ConfirmDialog copy correct, toast appears with timer bar, user appears in the staff list. Screenshot for the user.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: promote-participant dialog on the Tim page"
```

---

### Task 5: Quality gates + deploy

- [ ] **Step 1: Full suite** — `php artisan test --compact` → all green (382+ tests).
- [ ] **Step 2: `/simplify` pass over the whole diff** (`git diff origin/main...HEAD`) — apply what it finds.
- [ ] **Step 3: Pint** — `vendor/bin/pint --dirty --format agent`; rerun affected tests if it changed files; commit any fixes.
- [ ] **Step 4: Push** — `git push origin main`.
- [ ] **Step 5: Deploy** (no migrations, no new permissions — `users.manage` already exists):

```bash
ssh ak_rocks@103.157.97.233 'bash /srv/www/kheedma-academy/deploy/deploy.sh main'
```

- [ ] **Step 6: Smoke from the VPS** — curl `/`, `/daftar`, `/admin/login` expect 200; `tail` laravel.log clean; verify the new route exists: `cd current && php8.4 artisan route:list --path=users`.
- [ ] **Step 7: Report** — release id + SHA to the user. Do NOT promote Hafiidh's account in production; that is the PO's call to make through the new UI.
