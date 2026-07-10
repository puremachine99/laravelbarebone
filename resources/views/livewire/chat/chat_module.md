# Nakama Chat — Implementation Plan

## Context

`/chat` (`ChatIndex` Livewire component + `chat-index.blade.php`) is currently a placeholder "coming soon" page describing four planned features in Indonesian marketing copy: real-time messaging via Laravel Reverb, auto-classified S3 file storage (work docs private / casual images public), a "Quick OKR Sync" drawer, and a premium 2-column layout. None of this is built — the route (`can:manage-chat` gate, `routes/web.php:71`) exists, the component is empty, and there's no message/conversation schema at all. This plan turns that PRD copy into a real, incrementally-shippable feature.

**Codebase facts that shape the plan:**
- Laravel 13 / PHP 8.3, Livewire ^4.3 (classic class-based components — no Volt usage in this area despite the package being installed)
- Flux UI Pro vendored locally at `package/flux-pro/src` (namespace `FluxPro\`) + daisyui 5 + Tailwind v4 (CSS-first, no `tailwind.config.js`) — **confirmed no drawer/slide-over component exists in Flux Pro** (grepped, zero matches), so the OKR drawer needs a plain Alpine panel, not a vendored primitive.
- **Laravel Reverb is not installed at all** — no `config/reverb.php`, no `REVERB_*` env vars, no `routes/channels.php`, `BROADCAST_CONNECTION=log`. Fully greenfield.
- No OKR model exists — only `employees.target_okr` (text) and `kpi_achieved_m1`/`m2` (decimal) columns (`database/migrations/2026_06_03_093653_create_employees_table.php`). The OKR drawer must read these, not a new entity.
- `config/filesystems.php` has one `s3` disk only — no public/private split yet.
- `User.php` uses spatie `HasRoles`, Fortify traits, PHP 8 attribute-based `#[Fillable]`/`#[Hidden]`, and has `employee()` hasOne.
- Testing: Pest (`tests/Pest.php` present).

## Guiding decisions

- **Schema supports 1:1 DMs now, group-ready later**: `conversations` + `conversation_participants` pivot (not `sender_id`/`recipient_id` on messages). Group chat later just means >2 participant rows — no rewrite.
- **Reuse `manage-chat` gate for module access; add `ConversationPolicy` for per-thread access** (must be a participant to view/post).
- **Presence channel for online status, private channel per conversation for message delivery** — presence channels give the joined-members list for free; private channels are simpler/cheaper for point-to-point delivery.
- **Polling (`wire:poll`) is a Phase 1 stand-in only**, removed once Reverb lands in Phase 2 — not kept as a permanent fallback (PRD doesn't require degraded-network support; maintaining both paths is pure overhead).
- **File classification logic lives in `app/Services/Chat/FileClassifier.php`**, not inline in the upload handler — keeps it unit-testable and gives one place to extend rules.
- **No new OKR model** — drawer reads `employees.target_okr`/`kpi_achieved_*` via the existing `Employee` relation.
- Build MVP messaging fully before touching Reverb — isolates all websocket/infra risk into one later phase instead of debugging UI and broadcasting simultaneously.

## Phase 0 — Schema & domain foundation

Migrations (follow existing convention: `foreignId()->constrained()->cascadeOnDelete()`, `softDeletes()`, snake_case, timestamped filenames):
- `conversations`: `id`, `type` (string, default `direct`), `last_message_at` (nullable timestamp), timestamps, softDeletes
- `conversation_participants`: `id`, `conversation_id` FK, `user_id` FK→users, `last_read_at` (nullable), timestamps, unique(`conversation_id`,`user_id`)
- `messages`: `id`, `conversation_id` FK, `user_id` FK (sender), `body` (text, nullable), `type` (string: text|image|file, default text), timestamps, softDeletes

Models (`app/Models/`): `Conversation` (`participants()` belongsToMany User with pivot `last_read_at`, `messages()` hasMany, `otherParticipant(User $me)` helper), `Message` (`belongsTo Conversation`, `belongsTo User as sender()`). Extend `User` with `conversations()` belongsToMany.

Policy: `app/Policies/ConversationPolicy.php` — `view`/`update` check the user is a participant.

Test: `tests/Feature/Chat/ConversationModelTest.php` — create conversation, attach participants, post message, policy denies a third user.

## Phase 1 — MVP UI (persisted messaging, no websockets)

Livewire components under `app/Livewire/Chat/`:
- `ChatIndex.php` — becomes the 2-column shell; replace placeholder `render()`; holds active conversation state.
- `ConversationList.php` — left column: search box (`wire:model.live.debounce`) over `User`, list of conversations ordered by `last_message_at desc` with unread indicator (compare `last_read_at` vs latest message), find-or-create a `direct` conversation on selecting a new contact.
- `ChatCanvas.php` — right column: message list + composer. Use a Livewire `Form` object (`app/Livewire/Forms/Chat/MessageForm.php`) for the composer, not a FormRequest. `sendMessage()` creates `Message`, bumps `last_message_at`, updates sender's `last_read_at`. Temporary `wire:poll.3s` while a conversation is open (removed in Phase 2).

Blades under `resources/views/livewire/chat/`: replace `chat-index.blade.php` with a real flex 2-column layout (`w-80 shrink-0` left / `flex-1 flex flex-col` right) using `<flux:card>`/`<flux:separator>`/`<flux:input>`/`<flux:textarea>`/`<flux:button>`. Extract `partials/message-bubble.blade.php` for own-vs-other styling (named PRD requirement).

Test: `tests/Feature/Chat/ChatFlowTest.php` via `Livewire::test(ChatIndex::class)` — start conversation, send message, non-participant gets denied.

## Phase 2 — Reverb realtime layer

- `composer require laravel/reverb` → `php artisan reverb:install` (scaffolds `config/reverb.php`, `.env` `REVERB_*` + `BROADCAST_CONNECTION=reverb`, Echo/Vite JS wiring).
- `npm install --save-dev laravel-echo pusher-js` (Reverb speaks the Pusher protocol).
- Create `routes/channels.php`:
  - `Broadcast::channel('conversation.{id}', ...)` — **private**, checks participant membership, used for message delivery.
  - `Broadcast::channel('online', ...)` — **presence**, single global channel for online/offline dots on contact rows.
- `app/Events/Chat/MessageSent.php` implements `ShouldBroadcast`, broadcasts on `PrivateChannel("conversation.{id}")`, fired from `ChatCanvas::sendMessage()`.
- Livewire 4 has no native Echo-listener attribute — wire manually via `@script` in `chat-canvas.blade.php`: `Echo.private(...).listen('MessageSent', e => $wire.dispatchSelf(...))`, component method appends to local message list. Presence subscription for online dots lives at the `ChatIndex` root, toggling state client-side (avoid a Livewire round-trip per presence event).
- Remove `wire:poll` once this works.
- Test: `Event::fake([MessageSent::class])` asserting correct channel/payload; manual two-browser-session check for actual delivery (websocket delivery isn't practically assertable in Pest).

## Phase 3 — File uploads with S3 bucket classification

- `config/filesystems.php`: add `s3_private` and `s3_public` disks (start with prefix-based separation on the existing bucket via `AWS_BUCKET` + a path prefix, unless a second physical bucket is already provisioned — cheaper to stand up, upgrade to real dual-bucket only if ACL requirements demand it).
- New table `chat_attachments`: `id`, `message_id` FK, `disk` (string), `path`, `original_name`, `mime_type`, `size`, timestamps.
- `app/Services/Chat/FileClassifier.php` — `classify(UploadedFile $file): string`, rule table (mime `image/*` → `s3_public`, else → `s3_private`), unit-testable without touching S3 (`Storage::fake()`).
- `ChatCanvas` gets `WithFileUploads`; `MessageForm` validates size/mime; `sendMessage()` classifies, stores, creates `Message` + `ChatAttachment`, broadcasts as before.
- Private files served via a gated route/controller action doing `Storage::disk('s3_private')->temporaryUrl(...)` — never link the private bucket directly.
- Test: table-driven `FileClassifierTest`, feature test for correct disk routing + 403 for non-participants on the attachment route.

## Phase 4 — Quick OKR Sync Sidebar

- No new tables. `ChatCanvas` gets `$showOkrDrawer` + `toggleOkrDrawer()`, and a `#[Computed]` `partnerEmployee` resolving `$conversation->otherParticipant($me)->employee` (handle null gracefully).
- Drawer UI: since Flux Pro has no drawer/slide-over primitive, build a plain Alpine `x-show` + `translate-x` panel styled to match existing Flux card chrome. Shows `target_okr` text + `kpi_achieved_m1`/`m2` as stat tiles.
- Header button: `<flux:button icon="flag" wire:click="toggleOkrDrawer">` in `chat-canvas.blade.php`.
- Test: `Livewire::test` asserts drawer toggles and partner's `target_okr` renders.

## Phase 5 — Polish

Unread badges (`last_read_at` vs latest message, mark-as-read on `mount()`), typing indicators (ephemeral, Echo `whisper()`, no DB), day separators/empty states/skeletons matching existing daisyui/Flux tokens, rate-limit `sendMessage`, soft-delete message UI (column already present from Phase 0).

## Verification

Each phase ships independently testable: `php artisan test --filter=Chat` after each phase's Pest tests land. Phase 1 and later should also get a manual click-through at `/chat` (start conversation, send message, refresh). Phase 2 needs a manual two-session check (two logged-in browsers) since websocket delivery isn't reliably assertable in automated tests. Phase 3 needs a manual upload check against real/staging S3 credentials to confirm bucket routing and that private URLs are actually inaccessible unsigned.

## Critical files

- `app/Livewire/Chat/ChatIndex.php`, `resources/views/livewire/chat/chat-index.blade.php` — convert from placeholder to real shell
- `routes/web.php:70-72` — existing gate/route, extend with `routes/channels.php` in Phase 2
- `database/migrations/2026_06_03_093653_create_employees_table.php` — OKR columns to reuse in Phase 4
- `config/filesystems.php` — base for Phase 3 disks
- `app/Models/User.php`, `app/Models/Employee.php` — relationships everything hangs off
