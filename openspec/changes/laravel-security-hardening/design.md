# Design: Laravel Security Hardening

## Technical Approach

Five slices, each config- or provider-level where possible so it is revertible without touching call sites. New behaviour lands in `app/Domain/*` services/actions bound through `app/Contracts/` interfaces in a provider — the same shape as `PagoService`, `AuditService`, `AsesorScopeGuard`. Controllers and Filament Resources stay thin.

Two findings from reading the code change the proposal's assumptions and are carried as blocking decisions below: **D1** (Sanctum's global `expiration` overrides per-token expiry) and **D5** (`Persona.DNI` is not encryptable with a plain `encrypted` cast).

---

## Architecture Decisions

### D1 — Per-token access TTL

`config/sanctum.php:14-16` documents that the global `expiration` **overrides** any per-token `expires_at`. Setting it to `120` would work but permanently forecloses differently-scoped tokens.

**Choice**: `'expiration' => env('SANCTUM_EXPIRATION')` → `null`; pass expiry per token via Sanctum 4's `createToken(string $name, array $abilities, ?DateTimeInterface $expiresAt)`. TTLs live in `config/api_auth.php`: `access_token_ttl_minutes` (120) + existing `refresh_token_ttl_days` (30).
**Rejected**: global `expiration => 120` (blocks per-token control); custom expiry middleware (reimplements Sanctum).
**Bug this exposes**: `RefreshTokenController.php:55` computes `expires_in` from `config('sanctum.expiration') * 60` → `0` once expiration is null. Must read `api_auth.access_token_ttl_minutes`.

### D2 — Where pair issuance lives

`LoginController` and `RefreshTokenController` need byte-identical issuance.

**Choice**: `App\Domain\Auth\IssueTokenPair` (invokable action) behind `App\Contracts\TokenIssuerInterface`, bound in `AppServiceProvider::register()`. It calls `$user->createToken(...)` with the explicit expiry, then reuses `RefreshToken::issueFor()` **unchanged** (it is already correct: SHA-256 at rest, plaintext returned once, TTL from config), and returns `['access_token','refresh_token','token_type','expires_in']`.
**Rejected**: duplicating the block in both controllers (two places to drift); a static helper on `RefreshToken` (untestable, no DI).

### D3 — Route wiring

**Choice**: inline additions to `routes/api.php` — `refresh`, `forgot-password`, `reset-password` in the existing public `v1` group; `logout-all` in the protected `v1` group. **No legacy unprefixed aliases** for new routes (legacy deprecation is out of scope; do not grow the surface). Throttles: `throttle:api-auth` on refresh, the already-defined-but-unused `throttle:password-reset` on forgot/reset.
**Rejected**: a separate `routes/api/auth.php` — `bootstrap/app.php:17` `withRouting(api: ...)` takes one file; a second needs a `then:` closure. Not worth it for 4 routes.

### D4 — Password policy registration

**Choice**: `Password::defaults()` in `AppServiceProvider::boot()` (this project has no `AuthServiceProvider` and centralises boot wiring there). Chain: `Password::min(8)->mixedCase()->numbers()->symbols()`, plus `->uncompromised()` **only outside `local`/`testing`** — same guard style as the existing `URL::forceScheme` block at `AppServiceProvider.php:83`. Keeps the suite offline and deterministic.
**Rejected**: `uncompromised()` everywhere (network call in every test).

### D5 — PII encryption: split the slice

| Column | Consumers found | Verdict |
|---|---|---|
| `Prestamo.numero_cuenta_desembolso` | attribute read/write only (`Api/PrestamoResource.php:36`, `PrestamoController.php:229`, `RetanqueoEjecucionService`, `RetanqueoWorkflowService`) — no `where`, no search, no sort | **Green**: plain `'encrypted'` cast |
| `Persona.DNI` | `UniqueDNI.php:38` `Persona::where('DNI', $v)`; `ClienteResource.php:282` `->searchable()->sortable()`; column-restricted eager loads (`GrupoResource.php:478`) | **Blocked**: Laravel's `encrypted` cast uses a random IV → ciphertext is non-deterministic. Equality, `LIKE`, and `ORDER BY` all silently break. `UniqueDNI` would stop detecting duplicates — a data-integrity regression worse than the plaintext exposure it fixes. |

**Choice**: ship `numero_cuenta_desembolso` alone (slice 3a). DNI (slice 3b) requires a **blind index**: add `Persona.DNI_hash` (`HMAC-SHA256(DNI, APP_KEY)`), repoint `UniqueDNI` and exact search at `DNI_hash`, drop `->sortable()` and swap `->searchable()` for exact-match on the hash. Column-restricted eager loads are unaffected.
**Rejected**: deterministic/fixed-IV cast (weaker crypto, still no `LIKE`/sort); descoping DNI entirely (leaves the stated gap open).

**Audit gate** (mandatory, blocking, before either migration) — a documented manual step, not runtime: `rg -n "numero_cuenta_desembolso|DNI" app/ database/ resources/ routes/`, classify every hit as attribute-access / `where`-equality / `LIKE` / `ORDER BY` / raw SQL / column-restricted select, and record the table in the slice PR description. The gate passes only if every hit is attribute-access or already routed through the blind index.

**Rollback**: each backfill migration's `down()` reverses in place — read via the cast, write via `DB::table()->update()` with the plaintext (and drop `DNI_hash`). Chunk with `Model::chunkById(500)`; wrap each chunk in a transaction. Verified DB backup required before the production run.

### D6 — Triage the Filament call sites before migrating

`rg` finds **126** `hasRole|hasAnyRole` occurrences across 29 Filament files, not ~45. Most are not authorization.

| Bucket | Signature | Action |
|---|---|---|
| **A — authorization** | `->visible()`, `->hidden()`, `->authorize*()`, static `canEdit/canView/canCreate/canDelete/canDeleteAny` (e.g. `PrestamoResource.php:815,1099,1229,1248,1263,1275`; `GrupoDetallePagos.php:240,266,891,918,946`) | Migrate to Policy |
| **B — data scoping** | `getEloquentQuery()` / `$query->where('asesor_id', ...)` (`PrestamoResource.php:159-166,1197-1204`) | **Out of scope.** Row scoping, not a Policy; `AsesorScopeGuard` is the existing home. Leave untouched. |
| **C — UI cosmetics** | icon, colour, label, `->disabled()` (`GrupoDetallePagos.php:220,227,373,428,507`) | **Leave untouched.** Not a security control; touching them inflates the diff and the regression surface. |

**Rationale**: bucket A is the actual attack surface. This turns "126 unbounded occurrences" into a bounded, mechanical migration and is what makes slice 5 reviewable at all.

**Migration form** — mechanical, one line per site, no restructuring:

```php
// before
->visible(fn ($record) => auth()->user()?->hasAnyRole(['Asesor', 'super_admin']))
// after
->visible(fn ($record) => auth()->user()?->can('reformular', $record))
```

The Policy method body is a **verbatim copy** of the closure it replaces, so behaviour parity is by construction and each hunk is diffable against its own removal.

### D7 — Policy registration and the missing 20 models

31 models, 11 Policies. Existing Policies are Filament Shield-style (`$user->can('view_any_prestamo')`) with role gates for domain verbs (`PrestamoPolicy::aprobar`).

**Choice**: new `App\Providers\AuthServiceProvider` registered in `bootstrap/providers.php`, with an explicit `Gate::policy()` map in `boot()`.
**Rejected**: appending to `AppServiceProvider::boot()` — it already carries 11 observer registrations plus rate limiters; a 31-entry map there is unreviewable. Auto-discovery alone stays implicit and silently no-ops on a typo'd class name.

**Rule derivation**: copy from bucket-A closures where one exists. Where a model has **no** inline check today (`Mora`, `CuotaIndividual`, `CuotasGrupales`, `PrestamoIndividual`, `SeparacionCliente`, …), mirror `PrestamoPolicy`'s Shield shape and add the matching permission rows to `database/seeders/PermissionSeeder.php`.

**Gotcha**: a Shield-style Policy whose permission rows do not exist denies everyone and blanks the screen. Every new Policy PR must ship its `PermissionSeeder` entries and the role assignments in the same commit.

### D8 — Auth event logging

`AuditServiceInterface::registrar()` requires a non-null `Model`, and `audit_logs.auditable_type`/`auditable_id` are `NOT NULL`. A failed login for an unknown email has no model — `AuditService` cannot express it.

**Choice**: `App\Domain\Auth\AuthEventLogger` behind `App\Contracts\AuthEventLoggerInterface`, writing `AuditLog` directly, plus a small migration making `auditable_type`/`auditable_id` nullable. Precedent: `2026_05_26_172145_make_user_id_nullable_in_audit_logs.php` did exactly this for `user_id`.
**Rejected**: widening `AuditServiceInterface` to accept `?Model` — it is injected across many domain callers; a signature change there is a cross-cutting break for one narrow use case. Also rejected: a sentinel `auditable_id = 0` (dangling morph, poisons `scopeDelModelo`).

Listeners in `app/Listeners/Auth/`, registered explicitly via `Event::listen()` in `AppServiceProvider::boot()` (matches the "observers are explicit here" convention; do not rely on auto-discovery).

| Event | Listener | `accion` | auditable | Payload (`datos_nuevos`) |
|---|---|---|---|---|
| `Illuminate\Auth\Events\Login` | `LogSuccessfulLogin` | `auth_login` | the `User` | `guard`, `ip`, `user_agent`, `remember` |
| `Failed` | `LogFailedLogin` | `auth_login_failed` | `User` if resolved, else `null` | `guard`, `email_attempted`, `ip`, `user_agent` |
| `Logout` | `LogSuccessfulLogout` | `auth_logout` | the `User` | `guard`, `ip`, `user_agent` |
| `PasswordReset` | `LogPasswordReset` | `auth_password_reset` | the `User` | `ip`, `user_agent` |

Never logged: password, token, refresh token. `app/Logging/SensitiveKeyRedactor.php` already exists and must cover `email_attempted` handling. `created_at` is the timestamp; `ip` uses the existing column.

**Dedup**: `ResetPasswordController.php:30` already calls `registrar('password_reset', ...)`. Remove that inline call — the listener becomes the single source, otherwise API resets double-log.

---

## Data Flow — login / refresh

```
POST /v1/auth/login ──> LoginController ──> IssueTokenPair
                                              │
                            createToken(name, ['*'], now()+2h)
                                              │
                            RefreshToken::issueFor(user, device, ptId)  [sha256 at rest]
                                              │
                            {access_token, refresh_token, expires_in}
                                              │
   Login event ──> LogSuccessfulLogin ──> AuthEventLogger ──> audit_logs

POST /v1/auth/refresh ──> RefreshTokenController
        lookup by sha256 ──> revoke old ──> delete linked PAT ──> IssueTokenPair
```

Rotation-on-use is already implemented correctly in the orphan `RefreshTokenController` — replay of a stolen refresh token fails on the legitimate device's next call, which is the detection signal.

---

## File Changes

| File | Action | Description |
|---|---|---|
| `config/sanctum.php` | Modify | `expiration` → `env('SANCTUM_EXPIRATION')` (null) |
| `config/api_auth.php` | Modify | add `access_token_ttl_minutes` |
| `app/Contracts/TokenIssuerInterface.php` | Create | pair-issuance contract |
| `app/Domain/Auth/IssueTokenPair.php` | Create | access+refresh issuance |
| `app/Http/Controllers/Api/Auth/LoginController.php` | Modify | delegate to `IssueTokenPair`; new response contract |
| `app/Http/Controllers/Api/Auth/RefreshTokenController.php` | Modify | delegate to `IssueTokenPair`; fix `expires_in` |
| `routes/api.php` | Modify | wire 4 routes + throttles |
| `app/Providers/AppServiceProvider.php` | Modify | `Password::defaults()`, bindings, `Event::listen` |
| `app/Providers/AuthServiceProvider.php` | Create | explicit `Gate::policy()` map |
| `bootstrap/providers.php` | Modify | register `AuthServiceProvider` |
| `app/Models/Prestamo.php` | Modify | `'numero_cuenta_desembolso' => 'encrypted'` |
| `app/Models/Persona.php` | Modify | `'DNI' => 'encrypted'` + `DNI_hash` (slice 3b) |
| `app/Rules/UniqueDNI.php` | Modify | query `DNI_hash` (slice 3b) |
| `database/migrations/*_encrypt_numero_cuenta_desembolso.php` | Create | backfill + reversible `down()` |
| `database/migrations/*_add_dni_hash_and_encrypt_dni.php` | Create | blind index + backfill (slice 3b) |
| `database/migrations/*_make_auditable_nullable_in_audit_logs.php` | Create | allow model-less auth events |
| `app/Contracts/AuthEventLoggerInterface.php` + `app/Domain/Auth/AuthEventLogger.php` | Create | auth-event writer |
| `app/Listeners/Auth/*.php` | Create | 4 listeners |
| `app/Http/Controllers/Api/Auth/ResetPasswordController.php` | Modify | drop inline `registrar()` |
| `app/Policies/*.php` | Create | ~20 new Policies |
| `database/seeders/PermissionSeeder.php` | Modify | permission rows for new Policies |
| `app/Filament/**` | Modify | bucket-A sites only |

---

## Testing Strategy

| Layer | What | How |
|---|---|---|
| Unit | `IssueTokenPair` TTLs; each Policy method; `AuthEventLogger` payload shape | Pest unit, no HTTP |
| Feature | 5 existing `Api/Auth` test files (ground truth first — the `BACKLOG.md:9` "95/95" claim is unverified); throttle 429s; `Password::defaults()` rejection cases | `RefreshDatabase` |
| Feature | Auth events → `audit_logs` rows, including failed login with unknown email | `Event::fake()` off; assert DB |
| Migration | Encrypt backfill `up()` then `down()` round-trip returns original plaintext | Dedicated migration test |
| Regression | Per bucket-A hunk: authorized role sees the action, unauthorized does not | Filament Livewire tests |

---

## Sequencing and Review Workload

| Slice | Scope | Est. lines | Depends on |
|---|---|---|---|
| 1 | Token wiring, TTLs, throttles, `BACKLOG.md:9` correction | ~250 | — |
| 2 | `Password::defaults()` | ~60 | — |
| 3a | `numero_cuenta_desembolso` encryption | ~150 | audit gate |
| 3b | DNI blind index + encryption | ~250 | audit gate, 3a |
| 4 | Auth-event logging (+ nullable-auditable migration) | ~250 | — |
| 5 | Authorization consolidation | **~1500+** | — |

**Slice 5 must be chained.** Structural seams, in order — each is independently shippable and rollback-safe:

- **5a** — `AuthServiceProvider` + `Gate::policy()` map for the **11 existing** Policies. No behaviour change. ~50 lines.
- **5b** — the ~20 new Policy classes + `PermissionSeeder` rows + Policy unit tests, **not yet referenced by any call site**. Purely additive, zero regression risk, easy to review in isolation. Splits further by model group if needed (financial: `Mora`/`CuotaIndividual`/`CuotasGrupales`/`AplicacionPago`/`AjusteDeuda`; lifecycle: `PrestamoIndividual`/`SeparacionCliente`/`Reagrupacion`/`RetanqueoIndividual`; catalog: the rest).
- **5c…5g** — bucket-A call-site migration, **one Resource family per PR**, largest first: `PagoResource` + its Pages (~60 hits, `GrupoDetallePagos.php` alone has 18) → `PrestamoResource` + Pages (~17) → `RetanqueoResource` + Pages (~17) → `GrupoResource` + Pages (~15) → remaining Resources/Widgets/Pages (~17).

Ordering 5a → 5b → 5c… means every call-site PR only ever deletes a closure and calls an already-tested, already-registered Policy. `delivery_strategy` is `ask-on-risk`; expect the Review Workload Guard to fire on slice 5 and recommend this chain.

---

## Open Questions

- [ ] **Blocking for 3b**: is losing partial (`LIKE`) search and sort on DNI in the Filament Cliente table acceptable to the business? If not, DNI encryption is not viable and slice 3b must be descoped.
- [ ] Does any report, export, or BI/external process read `personas.DNI` or `prestamos.numero_cuenta_desembolso` outside this repo? Repo-local grep cannot answer this — it is part of the audit gate and needs a human answer.
- [ ] Confirm production `FRONTEND_URLS` is non-empty (`config/cors.php`) — ops, not code.
- [ ] Should `Failed` login events be retained indefinitely in `audit_logs`, or pruned? Unbounded rows from a credential-stuffing burst are a capacity concern.
