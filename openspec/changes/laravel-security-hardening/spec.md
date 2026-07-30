# Spec: laravel-security-hardening

**Status:** Draft | **Date:** 2026-07-30 | **RFC:** 2119

Change slug: `laravel-security-hardening`. Reads proposal (`openspec/changes/laravel-security-hardening/proposal.md`, mirrored in engram at `sdd/laravel-security-hardening/proposal`, obs #284), exploration (`sdd/laravel-security-hardening/explore`, obs #283), and design (`openspec/changes/laravel-security-hardening/design.md`, mirrored in engram at `sdd/laravel-security-hardening/design`, obs #286). This revision reconciles the spec against the completed design — see the "Reconciliation Log" at the bottom for exactly what changed and why.

Scope: WHAT must be true after the change is applied. Implementation mechanism (exact classes, exact route middleware order, exact migration SQL, exact response DTO shape) is the design phase's job. Scenarios whose acceptance depends on a design choice not yet made are tagged `DEPENDS-ON-DESIGN`; they still describe an observable outcome, but the precise mechanism is deferred and must be reconciled against the Design artifact before `sdd-tasks`.

Domain terms (retanqueo, cuota, mora, asesor, cobranza, cuota grupal, cuota individual) kept in Spanish per language contract.

Grounding note (verified against current code during spec-writing, updated after design):
- `routes/api.php` has zero routes for `/auth/refresh`, `/auth/logout-all`, `/auth/forgot-password`, `/auth/reset-password`. The four controllers (`RefreshTokenController`, `LogoutAllController`, `ForgotPasswordController`, `ResetPasswordController`), the `RefreshToken` model, and `config/api_auth.php` exist as orphan code.
- `LoginController::__invoke()` (app/Http/Controllers/Api/Auth/LoginController.php:40-45) returns `{token, user}` and never calls `RefreshToken::issueFor()`.
- **RESOLVED by design (D2, D3)**: the login response contract conflict this spec originally flagged as `DEPENDS-ON-DESIGN` (`LoginTest.php` asserting `data.token` vs. the three new Api/Auth test files asserting `data.access_token`/`data.refresh_token`) is settled — design's `App\Domain\Auth\IssueTokenPair` action becomes the single issuance path for both `/api/auth/login` and `/api/v1/auth/login`, returning `{access_token, refresh_token, token_type, expires_in}` (plus `user`, unchanged). This means `LoginTest.php`'s `data.token` assertion is superseded and MUST be updated to the new contract as part of this slice — see Requirement 1.1.
- `config/sanctum.php:20` currently sets a flat `'expiration' => 43200` (30 days) with no per-route override. Design (D1) found this is a bug-in-waiting: Sanctum's global `expiration` overrides any per-token `expires_at`, so `RefreshTokenController.php:55`'s `expires_in` computation (`config('sanctum.expiration') * 60`) would silently become `0` once `expiration` is nulled to allow per-token TTLs. Requirement 1.1 accounts for this explicitly.
- `RateLimiter::for('password-reset', ...)` is defined in `AppServiceProvider::boot()` (lines 103-105) but referenced by zero routes.
- No `Password::defaults()` registration exists anywhere in the codebase (grepped).
- `Prestamo::$casts` (app/Models/Prestamo.php:111-121) has no entry for `numero_cuenta_desembolso` — design (D5) confirms all its consumers are attribute read/write only (no `where`, search, or sort), so a plain `'encrypted'` cast is safe. **`Persona::$casts` (app/Models/Persona.php:27-29) has no entry for `DNI`, and design (D5) found a plain `'encrypted'` cast is UNSAFE for this column**: `UniqueDNI.php:38` does `Persona::where('DNI', $v)` and `ClienteResource.php:282` uses `->searchable()->sortable()` on it — Laravel's `encrypted` cast uses a random IV per write, so equality/`LIKE`/`ORDER BY` all silently break, and `UniqueDNI` would stop detecting duplicates (a data-integrity regression worse than the plaintext exposure being fixed). Design proposed a blind-index (`DNI_hash` HMAC-SHA256) alternative that preserves duplicate detection but loses partial search/sort. **The user was asked directly and chose to remove DNI encryption from this change entirely** — DNI stays plaintext, deferred to a separate future change; only `numero_cuenta_desembolso` is encrypted in this change. See Requirement 3.3 (explicit non-goal).
- 11 Policies exist in `app/Policies/`; none are registered via explicit `Gate::policy()` (relies on Laravel's model-name auto-discovery, unverified for non-`App\Models\*` targets such as Spatie's `Role`). Design (D7) confirms 31 total models vs. 11 Policies (20 missing, not the proposal's earlier "18/29" estimate) and chooses a new `App\Providers\AuthServiceProvider` with an explicit `Gate::policy()` map (rejecting appending a 31-entry map to the already-crowded `AppServiceProvider::boot()`).
- `PrestamoResource.php`, `GrupoResource.php`, `PagoResource.php` contain inline `request()->user()->hasRole(...)`/`hasAnyRole(...)` checks duplicating logic that in some cases (e.g. `PrestamoPolicy::aprobar()`) already exists as an equivalent Policy method the Resource does not call — confirming this is duplicated/drifted authorization logic, not merely missing authorization. **Design's actual grep (D6) found 126 `hasRole`/`hasAnyRole` occurrences across 29 Filament files — not the proposal's ~45 estimate — triaged into three buckets: Bucket A (authorization: `->visible()`, `->hidden()`, `->authorize*()`, static `canEdit`/`canView`/`canCreate`/`canDelete`/`canDeleteAny`) migrates to Policies; Bucket B (data scoping: `getEloquentQuery()`/`$query->where('asesor_id', ...)`) is explicitly out of scope — it belongs to the existing `AsesorScopeGuard` territory, not a Policy concern; Bucket C (UI cosmetics: icon/colour/label/`->disabled()`) is explicitly out of scope — not a security control.** This triage (D6) is authoritative for `sdd-tasks`; Requirement 4.1 below is scoped to Bucket A only.
- No listener exists for `Illuminate\Auth\Events\{Login,Failed,Logout,PasswordReset}`; `AppServiceProvider::boot()` registers only model Observers and rate limiters. Design (D8) confirms `AuditServiceInterface::registrar()` requires a non-null `Model`, and `audit_logs.auditable_type`/`auditable_id` are currently `NOT NULL` — a failed login for an unknown email has no resolvable model, so a dedicated `App\Domain\Auth\AuthEventLogger` (writing `AuditLog` directly) plus a nullable-auditable migration is required, not a reuse of `AuditServiceInterface` as-is. Design also flags that `ResetPasswordController.php:30`'s existing direct `registrar('password_reset', ...)` call must be REMOVED once the `PasswordReset` listener lands, to avoid double-logging.

---

## Slice 1 — Auth token wiring (access/refresh) + password-reset throttle

### Requirement 1.1 — Login MUST issue an access token and a refresh token via a single unified contract (RESOLVED by design D2/D3)

`LoginController` MUST delegate to `App\Domain\Auth\IssueTokenPair` (behind `App\Contracts\TokenIssuerInterface`) to issue a Sanctum access token AND a `RefreshToken` (via `RefreshToken::issueFor()`, unchanged) on every successful login, at both the unprefixed `/auth/login` and prefixed `/v1/auth/login` routes — both MUST return the identical response shape. The response MUST contain `access_token`, `refresh_token`, `token_type`, `expires_in`, and `user` (the existing `UserResource`). The access token MUST have an effective TTL of approximately 2 hours (`config('api_auth.access_token_ttl_minutes')`); the refresh token MUST have an effective TTL of approximately 30 days (`config('api_auth.refresh_token_ttl_days')`).

The login-contract conflict this spec originally flagged as `DEPENDS-ON-DESIGN` is now resolved: design settled on this single `access_token`/`refresh_token` contract for both routes. `tests/Feature/Api/Auth/LoginTest.php`'s current assertions of `data.token` and `data.user.roles` are superseded by this contract and MUST be updated, as in-scope work of this slice, to assert `data.access_token`/`data.refresh_token` instead of `data.token` (the `data.user.roles` assertion is unaffected and stays).

#### Scenario 1.1.a — Successful login returns a usable access token and refresh token
- GIVEN a user with `active = true` and valid credentials
- WHEN `POST /api/v1/auth/login` (or `POST /api/auth/login`) is called with `email`, `password`, `device_name`
- THEN the response MUST be HTTP 200 with `success: true`
- AND the response `data` MUST contain `access_token`, `refresh_token`, `token_type` (`"Bearer"`), `expires_in`, and `user`
- AND the access token MUST authenticate a subsequent `auth:sanctum` request (e.g. `GET /api/v1/dashboard` returns 200)

#### Scenario 1.1.b — Access token TTL is approximately 2 hours
- GIVEN a token is issued at login
- WHEN the token's expiry is inspected (directly on the `personal_access_tokens` row, or via the response's `expires_in` value)
- THEN the expiry MUST resolve to approximately 2 hours (7200 seconds) from issuance, NOT the prior unscoped global 30-day (`43200`-minute) `config/sanctum.php` default

#### Scenario 1.1.c — Refresh token TTL is approximately 30 days
- GIVEN a refresh token is issued at login
- WHEN `RefreshToken::expires_at` is inspected
- THEN it MUST be approximately 30 days from issuance (consistent with `config('api_auth.refresh_token_ttl_days')`)

#### Scenario 1.1.d — `expires_in` is never zero (guards the D1 config-nulling bug)
- GIVEN `config/sanctum.php`'s global `expiration` is set to `null` (env-driven, to allow per-token expiry per D1) so that per-token TTLs can be honored
- WHEN the login or refresh response's `expires_in` value is computed
- THEN it MUST reflect the actual configured access-token TTL in seconds (e.g. `7200` for a 2h TTL), and MUST NOT be `0` or otherwise derived from `config('sanctum.expiration') * 60` once that config value is null

#### Scenario 1.1.e — All 5 Api/Auth test files pass, ground truth re-verified
- GIVEN the full `tests/Feature/Api/Auth/*` suite (`LoginTest`, `LogoutTest`, `RefreshTokenTest`, `LogoutAllTest`, `PasswordResetTest`)
- WHEN `php artisan test tests/Feature/Api/Auth` is run after this slice is implemented
- THEN all tests in all 5 files MUST pass (0 failures) — updating `LoginTest.php` to the new contract (per this Requirement's resolution) is in-scope work for this slice, not a follow-up
- AND `BACKLOG.md:9`'s "95/95 tests, sin regresiones" claim MUST be corrected to reflect the actually-observed pass count for this slice

### Requirement 1.2 — `/v1/auth/refresh`, `/v1/auth/logout-all`, `/v1/auth/forgot-password`, `/v1/auth/reset-password` MUST be routed

`routes/api.php` MUST register all four routes under the `v1` prefix, wired to the existing controllers (`RefreshTokenController`, `LogoutAllController`, `ForgotPasswordController`, `ResetPasswordController`). `refresh` and `forgot-password`/`reset-password` MUST be reachable without a prior `auth:sanctum` token (a user without a valid access token must still be able to refresh using only a valid refresh token, and must be able to request/perform a password reset while logged out); `logout-all` MUST require `auth:sanctum`. Per design (D3), these 4 new routes MUST NOT gain legacy unprefixed aliases (deprecating the legacy alias surface is explicitly out of scope for this change — do not grow it further).

#### Scenario 1.2.a — Refresh route rotates the token pair
- GIVEN a user holds a valid, non-expired, non-revoked refresh token
- WHEN `POST /api/v1/auth/refresh` is called with that refresh token and no `Authorization` header
- THEN the response MUST be HTTP 200 with a new access token and a new refresh token, both different from the ones submitted
- AND the old access token MUST no longer authenticate any `auth:sanctum` route
- AND resubmitting the old (now-rotated) refresh token MUST return HTTP 401

#### Scenario 1.2.b — Logout-all requires authentication and revokes every session
- GIVEN a user is authenticated via a valid access token and has multiple active device sessions (multiple Sanctum tokens + multiple active `RefreshToken` rows)
- WHEN `POST /api/v1/auth/logout-all` is called with that access token
- THEN the response MUST be HTTP 200
- AND every Sanctum token belonging to that user MUST be deleted
- AND every `RefreshToken` belonging to that user MUST be revoked (`revoked_at` set)
- AND other users' sessions MUST remain untouched
- AND calling `logout-all` with no `Authorization` header MUST return HTTP 401

#### Scenario 1.2.c — Forgot-password does not leak account existence
- GIVEN an email that does and one that does not correspond to a user
- WHEN `POST /api/v1/auth/forgot-password` is called with each
- THEN both responses MUST be HTTP 200 with an identical generic success message (no signal distinguishing existing vs. non-existing accounts)

#### Scenario 1.2.d — Reset-password invalidates all prior sessions
- GIVEN a user has an active access token and a valid password-reset token
- WHEN `POST /api/v1/auth/reset-password` is called with a new password and the valid reset token
- THEN the response MUST be HTTP 200, the user's password MUST be updated (verifiable via `Hash::check`)
- AND the previously-active access token MUST no longer authenticate any `auth:sanctum` route
- AND an invalid/garbage or expired reset token MUST return HTTP 422 with `success: false` and MUST NOT change the password

### Requirement 1.3 — `password-reset` rate limiter MUST actually be applied

The already-defined `password-reset` RateLimiter (3/min, keyed by email-or-IP) MUST be attached to the `forgot-password` route (and MAY also apply to `reset-password`) via `throttle:password-reset` middleware.

#### Scenario 1.3.a — Exceeding 3 requests/min throttles forgot-password
- GIVEN no prior requests against the `password-reset` limiter bucket
- WHEN `POST /api/v1/auth/forgot-password` is called 4 times in rapid succession with the same email
- THEN the 4th response MUST be HTTP 429

---

## Slice 2 — Global password policy

### Requirement 2.1 — `Password::defaults()` MUST enforce a strong, breach-checked policy globally

A `Password::defaults()` closure MUST be registered (e.g. in `AppServiceProvider::boot()`) requiring, at minimum: `min(8)`, mixed case, numbers, symbols, and `uncompromised()` (HaveIBeenPwned k-anonymity check). This MUST apply to every password-entry flow validated via Laravel's `Password` rule (currently: `ResetPasswordRequest`; any future registration/password-change flow inherits it automatically by using `Password::defaults()` instead of a hardcoded rule list).

#### Scenario 2.1.a — Weak password is rejected
- GIVEN a password-reset request with `password = 'password'` (fails mixed case/numbers/symbols)
- WHEN the request is validated against `Password::defaults()`
- THEN validation MUST fail with a 422 and an error referencing the password field

#### Scenario 2.1.b — Compromised password is rejected
- GIVEN a password known to appear in HaveIBeenPwned's breach corpus (e.g. `'Password123!'` or another well-known breached value), otherwise satisfying length/mixedCase/numbers/symbols
- WHEN the request is validated against `Password::defaults()`
- THEN validation MUST fail (uncompromised check triggers)

#### Scenario 2.1.c — Strong, non-compromised password is accepted
- GIVEN a password satisfying min length, mixed case, numbers, symbols, and not present in the breach corpus
- WHEN the request is validated against `Password::defaults()`
- THEN validation MUST pass

#### Scenario 2.1.d — HaveIBeenPwned outage does not lock out password changes (documented behavior)
- GIVEN the outbound network call to HaveIBeenPwned fails or times out
- WHEN `uncompromised()` is evaluated
- THEN Laravel's fail-open default behavior MUST be preserved (an otherwise-valid password is NOT rejected solely due to the HIBP check being unreachable) — this is existing framework behavior, not new code, and MUST be explicitly documented as an accepted risk rather than silently relied upon

---

## Slice 3 — PII encryption (`numero_cuenta_desembolso` only — `DNI` descoped, see Requirement 3.3) — audit-gated

**Reconciliation note**: design (D5) found that a plain `'encrypted'` cast is safe for `numero_cuenta_desembolso` (attribute-access-only consumers) but unsafe for `DNI` (breaks `UniqueDNI` duplicate detection and `ClienteResource`'s searchable/sortable column, because Sanctum's `encrypted` cast uses a random IV making ciphertext non-deterministic). Design proposed a blind-index (`DNI_hash` HMAC-SHA256) fallback for DNI as an optional slice 3b. **The user was asked directly and chose to remove DNI encryption from this change entirely** (Requirement 3.3) rather than build the blind index now. This slice therefore only encrypts `numero_cuenta_desembolso`.

### Requirement 3.1 — BLOCKING GATE: raw-SQL / non-Eloquent consumer audit MUST complete and be confirmed before any encrypted cast or migration ships for `numero_cuenta_desembolso`

This is a precondition, not a normal feature scenario. No code in this slice that adds an `'encrypted'` cast to `numero_cuenta_desembolso`, nor any backfill migration touching that column, MUST be merged until the audit below is performed and its findings are resolved (either "no non-Eloquent consumers found" or "found consumers X, Y updated to go through Eloquent / explicitly excluded with justification").

#### Scenario 3.1.a — Audit is performed and recorded before cast work starts
- GIVEN this slice begins
- WHEN raw SQL (`whereRaw`, `selectRaw`, `DB::raw`, `DB::select`, `DB::statement`), report generators (e.g. `ReporteProfesionalService`, `PagoExportNuevoController`, `PagoPdfController`-style exports), and any other non-Eloquent code path touching `prestamos.numero_cuenta_desembolso` are enumerated (design's D5 methodology: `rg -n "numero_cuenta_desembolso" app/ database/ resources/ routes/`, classifying every hit as attribute-access / `where`-equality / `LIKE` / `ORDER BY` / raw SQL / column-restricted select)
- THEN the enumeration MUST be recorded (in the slice PR description and/or the apply-progress artifact) BEFORE any cast/migration for this column is written; design's own D5 research already found the known call sites (`Api/PrestamoResource.php:36`, `PrestamoController.php:229`, `RetanqueoEjecucionService`, `RetanqueoWorkflowService`) to be attribute-access only, but this MUST be re-confirmed at apply time, not assumed from the design doc alone
- AND every found consumer MUST either be updated to read through Eloquent (so the cast transparently decrypts) or be explicitly justified as safe to leave as-is with a written rationale
- AND the gate passes only if every hit is attribute-access (no `where`-equality, `LIKE`, `ORDER BY`, or raw SQL touching the column)

#### Scenario 3.1.b — Cast/migration work is blocked until the gate is cleared
- GIVEN the audit in 3.1.a has not yet been recorded as complete
- WHEN a task-tracking check is performed (tasks/apply phase)
- THEN no `'encrypted'` cast addition or backfill migration for `numero_cuenta_desembolso` MUST be marked as started, let alone merged

### Requirement 3.2 — Once cleared, `numero_cuenta_desembolso` MUST be encrypted at rest and decrypt transparently through Eloquent

After Requirement 3.1 is satisfied, `Prestamo::$casts['numero_cuenta_desembolso']` MUST be set to `'encrypted'`. All existing plaintext rows MUST be backfilled (encrypted in place) by a migration.

#### Scenario 3.2.a — New records are stored encrypted
- GIVEN Requirement 3.1's gate is cleared
- WHEN a new `Prestamo` is created with a `numero_cuenta_desembolso` value
- THEN the raw database column value MUST NOT equal the plaintext input (verifiable via a raw `DB::table(...)->find()` read bypassing the model cast)
- AND `Prestamo::find($id)->numero_cuenta_desembolso` via Eloquent MUST return the original plaintext value transparently

#### Scenario 3.2.b — Existing plaintext rows are backfilled
- GIVEN pre-existing rows with a plaintext `numero_cuenta_desembolso` value
- WHEN the backfill migration runs (chunked, e.g. `Model::chunkById(500)`, each chunk wrapped in a transaction, per design)
- THEN every existing row's column value MUST be encrypted in place
- AND reading each row via Eloquent after the migration MUST return the same plaintext value it held before migration (no data loss/corruption)

#### Scenario 3.2.c — Backfill migration is reversible
- GIVEN the backfill migration has run
- WHEN `down()` is invoked
- THEN the column values MUST be decrypted back to plaintext (read via the cast, written back via `DB::table()->update()`) without data loss (per the proposal's Rollback Plan, this `down()` MUST actually work, not be a no-op)

#### Scenario 3.2.d — A verified DB backup precedes production execution (operational precondition)
- GIVEN the backfill migration is about to run in production
- WHEN the operational runbook is followed
- THEN a verified database backup MUST exist before the migration executes (procedural requirement, not enforced by application code)

### Requirement 3.3 — `DNI` encryption is explicitly OUT OF SCOPE for this change (descoped by explicit user decision)

`Persona.DNI` MUST remain stored as plaintext after this change. No `'encrypted'` cast, no `DNI_hash` blind-index column, and no changes to `UniqueDNI` or `ClienteResource`'s searchable/sortable DNI column MUST ship as part of `laravel-security-hardening`. DNI encryption (via the blind-index approach design outlined) is tracked as a separate future change, not a task within this one.

#### Scenario 3.3.a — Persona.DNI is unchanged by this change
- GIVEN the change is complete
- WHEN `Persona::getCasts()` is inspected
- THEN it MUST NOT contain an `'encrypted'` (or equivalent) cast for `DNI`
- AND no `DNI_hash` column MUST exist on the `personas` table
- AND `UniqueDNI` and `ClienteResource`'s DNI column (searchable/sortable behavior) MUST be unchanged from current behavior

#### Scenario 3.3.b — Rationale is discoverable, not silently dropped
- GIVEN a future engineer or auditor reviews this change
- WHEN they look for why `DNI` encryption was scoped out
- THEN the rationale (plain `encrypted` cast breaks duplicate detection and partial search/sort due to non-deterministic IV; blind-index alternative exists but was explicitly deferred by user decision) MUST be discoverable via this spec and/or `design.md`'s D5 analysis and Open Questions — it MUST NOT read as an oversight or a silently-abandoned requirement

---

## Slice 4 — Authorization consolidation (Filament Resources → Policies)

### Requirement 4.1 — Bucket-A (authorization) inline `hasRole()`/`hasAnyRole()` closures in Filament Resources MUST route through Policies; Bucket B and Bucket C are explicitly OUT OF SCOPE (RESOLVED/bounded by design D6)

**Reconciliation note**: this spec originally cited the proposal's ~45-occurrence estimate. Design's actual grep (D6) found **126** `hasRole`/`hasAnyRole` occurrences across **29** Filament files, and triaged them into three buckets. Only Bucket A migrates in this change:

| Bucket | What it is | Disposition |
|---|---|---|
| **A — authorization** | `->visible()`, `->hidden()`, `->authorize*()`, static `canEdit`/`canView`/`canCreate`/`canDelete`/`canDeleteAny` (e.g. `PrestamoResource.php:815,1099,1229,1248,1263,1275`; `GrupoDetallePagos.php:240,266,891,918,946`) | MUST migrate to Policy-routed calls |
| **B — data scoping** | `getEloquentQuery()` / `$query->where('asesor_id', ...)` (e.g. `PrestamoResource.php:159-166,1197-1204`) | OUT OF SCOPE — this is row-level scoping, not a Policy concern; `AsesorScopeGuard` is its existing home. MUST be left untouched by this change. |
| **C — UI cosmetics** | icon, colour, label, `->disabled()` (e.g. `GrupoDetallePagos.php:220,227,373,428,507`) | OUT OF SCOPE — not a security control; touching these only inflates the diff/regression surface. MUST be left untouched by this change. |

This bucket triage (D6) is authoritative for `sdd-tasks` — tasks MUST NOT re-litigate which occurrences belong to which bucket without new evidence of a miscategorization.

`PrestamoResource.php`, `GrupoResource.php`, `PagoResource.php`, and any other Filament Resource with Bucket-A occurrences MUST have every such inline `request()->user()->hasRole(...)`/`hasAnyRole(...)` authorization decision replaced with a call that resolves through the model's registered Policy (e.g. `$user->can(...)`, `Gate::allows(...)`, or Filament's native policy-based `canX()` hooks — per design, the Policy method body MUST be a verbatim copy of the closure it replaces). Each migrated check MUST preserve the exact same boolean outcome as the closure it replaces (behavior parity — this is a refactor, not a re-scoping of who can do what, unless the proposal explicitly says otherwise).

#### Scenario 4.1.a — Zero Bucket-A inline hasRole/hasAnyRole checks remain; Bucket B and C are untouched
- GIVEN the change is complete
- WHEN a project-wide grep for `hasRole(`/`hasAnyRole(` is run across `app/Filament/**/Resources/*.php`, scoped to Bucket-A call-site patterns (`->visible(`, `->hidden(`, `->authorize`, and the static `canEdit`/`canView`/`canCreate`/`canDelete`/`canDeleteAny` methods)
- THEN zero matches MUST be found within those specific Bucket-A patterns (all such logic now lives in Policy classes, not Resources)
- AND occurrences remaining in Bucket B (data-scoping queries) and Bucket C (UI cosmetics) are EXPECTED and MUST NOT be flagged as incomplete migration — a full unscoped grep for `hasRole(`/`hasAnyRole(` across the same files is expected to still return matches after this change, and that is correct per D6's triage

#### Scenario 4.1.b — Behavior parity per migrated Bucket-A check
- GIVEN a specific Bucket-A inline closure existed before migration (e.g. `PrestamoResource.php:69` — `$user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']) && !$prestamoNoPendiente && !$esRetanqueo`)
- WHEN the equivalent Policy-routed check is exercised with the same user/role/state combination after migration
- THEN the resulting boolean (visible/editable/actionable or not) MUST be identical to the pre-migration closure's result for every role × state combination exercised by existing tests or an equivalent new test added for this migration

### Requirement 4.2 — Explicit `Gate::policy()` registration MUST exist for every model with a Policy

Every model with a corresponding Policy class (existing 11 plus the newly-created ones from Requirement 4.3 — 31 total per design's D7 recount) MUST be explicitly bound via `Gate::policy(Model::class, Policy::class)`, rather than relying solely on Laravel's implicit naming-convention auto-discovery. Design (D7) chose a dedicated registration point for this map — a new `App\Providers\AuthServiceProvider` registered in `bootstrap/providers.php` — rather than appending a 31-entry map to the already-crowded `AppServiceProvider::boot()` (11 observer registrations plus rate limiters already live there).

#### Scenario 4.2.a — Explicit registration exists and resolves correctly
- GIVEN the full set of models with Policies (existing + new)
- WHEN `Gate::getPolicyFor($model)` is inspected for each
- THEN each MUST resolve to its intended Policy class via the explicit registration, including `Role` (Spatie) → `RolePolicy`, whose implicit-convention resolution was previously unverified

### Requirement 4.3 — Missing Policies MUST be created for currently-unprotected models

Policies MUST be created for at least `CuotaIndividual`, `CuotasGrupales`, `Mora`, `PrestamoIndividual`, `SeparacionCliente`, and any additional models discovered during design/apply to lack a Policy. Design (D7) recounts the actual total: **31 models, 11 existing Policies, 20 missing** (superseding the proposal's earlier "18/29" estimate). Each new Policy MUST implement least-privilege rules matching the CURRENT de-facto access behavior for that model (derived from wherever its authorization logic lives today — inline Bucket-A checks, implicit role gates, or absence of any check; design's D7 rule is: copy from the Bucket-A closure where one exists, otherwise mirror `PrestamoPolicy`'s Shield-style shape) — this change MUST NOT silently expand or restrict who can do what beyond what the proposal explicitly calls for. Per design's gotcha (D7): every new Policy PR MUST ship its corresponding `PermissionSeeder` entries and role assignments in the same commit — a Shield-style Policy whose permission rows don't exist denies everyone and blanks the screen.

**DEPENDS-ON-DESIGN**: the full enumeration of the 20 missing models (design names `Mora`, `CuotaIndividual`, `CuotasGrupales`, `PrestamoIndividual`, `SeparacionCliente`, "…") and the exact permission matrix per new Policy (which roles get which CRUD/transition actions) still requires a design/apply-time audit of each remaining model's current de-facto access rules; this spec fixes the outcome (least-privilege, parity with current behavior, no silent change, Shield-shape + PermissionSeeder parity) but not the complete enumerated list or matrix.

#### Scenario 4.3.a — Named models have a Policy with parity to current behavior
- GIVEN `CuotaIndividual`, `CuotasGrupales`, `Mora`, `PrestamoIndividual`, `SeparacionCliente` currently have no Policy class
- WHEN this change is complete
- THEN each MUST have a Policy class implementing at minimum `viewAny`, `view`, `create`, `update`, `delete`
- AND for any role/action combination currently allowed (however that allowance is currently implemented, including "allowed by default because no check exists"), the new Policy MUST NOT deny it unless the proposal explicitly directs a behavior change
- AND for any role/action combination currently denied, the new Policy MUST NOT newly allow it unless the proposal explicitly directs a behavior change

#### Scenario 4.3.b — All 20/31 models are enumerated and covered before slice close
- GIVEN design's D7 recount of 31 total models / 11 existing Policies / 20 missing
- WHEN the design/apply phase completes its audit
- THEN every model lacking a Policy MUST either receive one, or be explicitly listed with a written justification for exclusion (e.g. a value object, a pivot-only model with no direct authorization surface) — no silent gaps

---

## Slice 5 — Auth-event logging

### Requirement 5.1 — `Login`, `Failed`, `Logout`, `PasswordReset` events MUST be logged via the existing `AuditService`/`audit_logs` infrastructure, log-only

Listeners MUST be registered for Laravel's `Illuminate\Auth\Events\Login`, `Failed`, `Logout`, and `PasswordReset` events, in `app/Listeners/Auth/*`, registered explicitly via `Event::listen()` in `AppServiceProvider::boot()` (matching this project's existing "observers are explicit here" convention — no auto-discovery). This change MUST NOT add email/Slack/webhook alerting — that is explicitly out of scope (a documented follow-up).

**Reconciliation note (design D8)**: `AuditServiceInterface::registrar()` requires a non-null `Model`, and `audit_logs.auditable_type`/`auditable_id` are currently `NOT NULL` — a failed login for an unknown email has no resolvable model, so `AuditServiceInterface` as-is cannot express it. Design's resolution: a dedicated `App\Domain\Auth\AuthEventLogger` (behind `App\Contracts\AuthEventLoggerInterface`) writes `AuditLog` rows directly, plus a migration making `auditable_type`/`auditable_id` nullable (precedent: `2026_05_26_172145_make_user_id_nullable_in_audit_logs.php` did the same for `user_id`). Requirements below are updated to require this nullable-auditable path for the unknown-email case, and each listener's `accion`/payload follows design's table: `auth_login` / `auth_login_failed` / `auth_logout` / `auth_password_reset`, carrying `guard`, `ip`, `user_agent`, and (for `Failed`) `email_attempted` — but never password, token, or refresh token.

#### Scenario 5.1.a — Successful login produces an audit_logs row
- GIVEN a user authenticates successfully (any guard/flow that fires `Illuminate\Auth\Events\Login`)
- WHEN the login completes
- THEN an `audit_logs` row MUST exist with `auditable_type`/`auditable_id` referencing that `User`, and an `accion` identifier indicating a successful login (`auth_login`)

#### Scenario 5.1.b — Failed login for a known user produces an audit_logs row
- GIVEN invalid credentials are submitted for an email that DOES correspond to an existing user (fires `Illuminate\Auth\Events\Failed` with a resolvable model)
- WHEN the failed attempt is processed
- THEN an `audit_logs` row MUST exist with `auditable_type`/`auditable_id` referencing that `User` and an `accion` identifier indicating a failed login attempt (`auth_login_failed`)
- AND the row MUST NOT contain the submitted password

#### Scenario 5.1.c — Failed login for an unknown email still produces an audit_logs row (nullable-auditable path)
- GIVEN invalid credentials are submitted for an email that does NOT correspond to any existing user (fires `Failed` with no resolvable model)
- WHEN the failed attempt is processed
- THEN an `audit_logs` row MUST still be created (logging MUST NOT silently no-op just because no `User` could be resolved) — this requires `audit_logs.auditable_type`/`auditable_id` to be nullable, since `AuditServiceInterface::registrar()`'s current non-null-`Model` contract cannot express a model-less event
- AND the row MUST record the attempted email (`email_attempted`) and non-sensitive request context (IP, user agent) without exposing the submitted password

#### Scenario 5.1.d — Logout produces an audit_logs row
- GIVEN an authenticated user logs out (fires `Illuminate\Auth\Events\Logout`)
- WHEN the logout completes
- THEN an `audit_logs` row MUST exist with `auditable_type`/`auditable_id` referencing that user and an `accion` identifier indicating logout (`auth_logout`)

#### Scenario 5.1.e — Password reset produces exactly one audit_logs row (RESOLVED — single-logging, no duplication)
- GIVEN a user completes a password reset (fires `Illuminate\Auth\Events\PasswordReset`)
- WHEN the reset completes
- THEN exactly ONE `audit_logs` row MUST exist for that reset action (not zero, not duplicated), with `auditable_type`/`auditable_id` referencing that user and an `accion` identifier indicating password reset (`auth_password_reset`)
- AND `ResetPasswordController.php:30`'s existing direct `AuditServiceInterface::registrar('password_reset', ...)` call MUST be REMOVED as part of this slice, so the `PasswordReset` event listener becomes the single source of truth for this log entry — this is no longer an open risk; design (D8) confirms the inline call must go

#### Scenario 5.1.f — No alerting side effect is introduced
- GIVEN any of the four listeners fires
- WHEN its execution is inspected
- THEN it MUST NOT send an email, Slack message, or any other outbound notification — only the `audit_logs` write occurs

---

## Out of Scope / Non-Goals (explicit)

### Requirement N.1 — This change MUST NOT touch `sdd/core-contable-seguridad`'s ledger/retanqueo logic

The retanqueo ledger-writing, saldo-service consolidation, and the already-implemented `AsesorScopeGuard` IDOR fix delivered under `sdd/core-contable-seguridad` MUST NOT be modified, re-implemented, or duplicated by this change.

#### Scenario N.1.a — No overlapping files touched for ledger concerns
- GIVEN this change's diff
- WHEN reviewed against `sdd/core-contable-seguridad`'s scope (retanqueo ledger writes, saldo service, `AsesorScopeGuard`)
- THEN no file MUST be modified for a ledger/retanqueo/saldo-service reason; any incidental touch (e.g. adding a Policy to a model that also happens to be retanqueo-adjacent) MUST be strictly for the authorization/audit-logging concern of this change, not a ledger behavior change

### Requirement N.2 — This change MUST NOT touch `sdd/trazabilidad-modelo-datos`'s business-entity hash-chain audit trail

The non-repudiation, hash-chained `audit_logs` design for Prestamo/Grupo business-entity mutation history (a distinct concern from Slice 5's authentication-event logging) MUST NOT be implemented, redesigned, or duplicated here.

#### Scenario N.2.a — Auth-event logging does not become a hash-chain redesign
- GIVEN Slice 5's listeners write plain `audit_logs` rows via `AuthEventLogger` (or, where a resolvable model exists, `AuditServiceInterface::registrar()`)
- WHEN the implementation is reviewed
- THEN no hash-chaining, cryptographic linking, or non-repudiation mechanism MUST be introduced as part of this change — that remains exclusively `sdd/trazabilidad-modelo-datos`'s concern

---

## Cross-Cutting Constraints

- Every requirement in Slices 1, 2, 4, 5 MUST be revertible by reverting its slice's PR (additive/behavioral only, no destructive schema change) — per the proposal's Rollback Plan.
- Slice 3 (PII encryption) is the only slice with an irreversible-ish operational step; Requirement 3.2's `down()` and Requirement 3.2.d's backup precondition are mandatory, not optional.
- `Password::defaults()` and both TTL config values MUST be config/provider-level so they can be reverted independently of any other slice's code.
- No behavior-parity requirement in Slice 4 permits a silent authorization change; any INTENTIONAL behavior change (if design finds current de-facto access is itself a bug) MUST be called out explicitly in the design artifact and confirmed, not silently shipped as a "fix" bundled into a refactor.
- `php artisan test` MUST pass with 0 failures after each slice is applied independently (slices are independently shippable per the proposal's Approach).
- Human/ops confirmation that production `FRONTEND_URLS` (`config/cors.php`) is non-empty is a dependency of this change but is NOT a code requirement — it is a manual checklist item (proposal Dependencies).

---

## Open Items Deferred to Design Phase (explicitly NOT specced here — HOW, not WHAT)

Design (`design.md`) is now complete and has resolved most items originally deferred here. Remaining open items (still HOW-level, or explicitly flagged by design itself as unresolved):

- Full enumeration of the 20 missing-Policy models beyond the 5 design names explicitly (`Mora`, `CuotaIndividual`, `CuotasGrupales`, `PrestamoIndividual`, `SeparacionCliente`) and the precise permission matrix (which roles get which actions) for each — Requirement 4.3. Design's own model-group split (financial / lifecycle / catalog, for chaining purposes) is a task-sequencing hint, not the enumeration itself.
- Design's own unresolved Open Questions (carried forward, not spec-level but relevant to sequencing): whether any report/export/BI process outside this repo reads `personas.DNI` or `prestamos.numero_cuenta_desembolso` (part of the Requirement 3.1 audit gate, needs a human answer); confirming production `FRONTEND_URLS` is non-empty (ops, not code); whether `Failed`-login `audit_logs` rows should be retained indefinitely or pruned (capacity concern, not required to resolve before this change ships, but flagged for follow-up).

Resolved by design, removed from this list: exact login response contract mechanism (Requirement 1.1 — `IssueTokenPair`, D2/D3); exact TTL-enforcement mechanism (Requirement 1.1.d — per-token `expiresAt` + `config/api_auth.php`, D1); exact mechanism for Filament closure migration (Requirement 4.1 — verbatim-copy Policy methods per Bucket-A site, D6); whether the `PasswordReset` listener replaces the existing direct `registrar()` call (Requirement 5.1.e — it does; the inline call is removed, D8); raw-SQL/report consumer audit methodology for the encryption gate (Requirement 3.1.a — `rg` command + classification table, D5); DNI encryption viability (Requirement 3.3 — descoped entirely by explicit user decision after D5's finding).

## Risks / Assumptions Forced at Spec Level

1. ~~Login response contract conflict~~ — **RESOLVED by design (D2/D3)**: single `access_token`/`refresh_token` contract for both login routes; `LoginTest.php` is updated as in-scope work (Requirement 1.1).
2. ~~`ResetPasswordController` double-logging risk~~ — **RESOLVED by design (D8)**: the inline `registrar('password_reset', ...)` call is removed; the `PasswordReset` listener is the single source (Requirement 5.1.e).
3. **Existing `PrestamoPolicy` methods already parallel some inline `PrestamoResource` closures** (e.g. `aprobar`, `firmar`, `desembolsar`) but the Resource does not call them — confirming Slice 4 is fixing genuine logic duplication/drift, not just adding authorization where none existed. Design's Bucket-A triage and "verbatim copy" convention (D6) directly addresses this: treat the existing Policy methods as the source of truth for parity checks where they already exist, rather than re-deriving intended behavior from the Resource closures alone (in case the two have already silently diverged).
4. **HaveIBeenPwned check is applied only outside `local`/`testing` environments** (design D4, refining this spec's original framing of "fail-open on network outage"): the `uncompromised()` rule is deliberately excluded in `local`/`testing` to keep the suite offline and deterministic; Scenario 2.1.d's fail-open framing still applies to staging/production where the check IS active and HIBP is unreachable.
5. **Slice 3's audit gate now has a concrete methodology** (design D5: `rg -n "numero_cuenta_desembolso" app/ database/ resources/ routes/`, classify every hit, record the table in the slice PR description) — this substantially de-risks what was previously an open-ended "recorded and resolved" criterion (Requirement 3.1), and it is now scoped to `numero_cuenta_desembolso` only since DNI encryption is descoped (Requirement 3.3).
6. **DNI encryption is deferred, not abandoned** — the plaintext-DNI state this change leaves behind is an explicit, user-confirmed trade-off (data-integrity/searchability parity now, vs. encryption-at-rest for DNI later via the blind-index approach design outlined). This MUST NOT be re-read as "the team forgot about DNI" during a future security review — Requirement 3.3.b requires the rationale to be discoverable.
7. **Slice 5 (authorization) is now confirmed the dominant scope driver at a larger real size than estimated** — design counts ~1500+ lines and recommends a 5a→5g chain (`AuthServiceProvider` + 11 existing Policies → ~20 new Policies + `PermissionSeeder` → one Filament Resource family per PR, largest first). This is a `sdd-tasks`/delivery-strategy concern, not a spec-level requirement change, but is noted here so tasks doesn't need to rediscover it.

## Skills Applied
- laravel-security: token TTL/refresh conventions (Slice 1), `Password::defaults()` pattern with `uncompromised()` (Slice 2), encrypted casts (Slice 3), Policy/Gate authorization pattern (Slice 4), rate limiting (`throttle:password-reset`, Slice 1.3).
- laravel-best-practices: service/provider-level registration conventions for config-level changes (Slices 1-2), migration reversibility expectations (Slice 3).

---

## Reconciliation Log (spec updated against completed design.md)

This spec was revised after `sdd-design` completed (`design.md`, engram `sdd/laravel-security-hardening/design` obs #286). Changes made in this revision, for `sdd-tasks`' benefit:

1. **DNI encryption descoped entirely** (explicit user decision, after design D5 found a plain `encrypted` cast breaks `UniqueDNI` duplicate detection and `ClienteResource` searchable/sortable behavior due to non-deterministic IV). Requirement 3.2's DNI-specific scenarios were removed; Slice 3 now covers `numero_cuenta_desembolso` only. Added Requirement 3.3 as an explicit non-goal asserting `Persona.DNI` stays plaintext, with a discoverability scenario (3.3.b) so this isn't mistaken for an oversight later.
2. **Authorization scope corrected from ~45 to 126 occurrences / 29 files, triaged into 3 buckets per design D6** (Bucket A — authorization, migrates; Bucket B — data scoping via `AsesorScopeGuard`, out of scope; Bucket C — UI cosmetics, out of scope). Requirement 4.1 and Scenario 4.1.a rewritten to scope the "zero inline checks" assertion to Bucket-A patterns only, so a full unscoped grep correctly still shows Bucket B/C matches after the change. Requirement 4.3's "18/29" figure corrected to design's actual "31 models, 11 existing Policies, 20 missing" (D7).
3. **Login response contract conflict resolved, `DEPENDS-ON-DESIGN` tag removed**: Requirement 1.1 now specifies the single `IssueTokenPair`-based `access_token`/`refresh_token`/`token_type`/`expires_in`/`user` contract for both login routes (D2/D3), and explicitly requires `LoginTest.php` to be updated to this contract as in-scope work. Added Scenario 1.1.d guarding against the `expires_in` becoming `0` once `config/sanctum.php`'s global `expiration` is nulled (D1's bug finding).
4. **Password-reset double-logging risk resolved**: Scenario 5.1.e (renumbered from 5.1.d) now asserts exactly one `audit_logs` row per reset and requires `ResetPasswordController`'s existing inline `registrar()` call to be removed (D8), rather than leaving the reconciliation mechanism open.
5. **Requirement 5 (auth-event logging) split to account for the nullable-auditable path** (D8): added Scenario 5.1.c for a failed login against an unknown email, requiring `audit_logs.auditable_type`/`auditable_id` to be nullable and a dedicated `AuthEventLogger` (since `AuditServiceInterface::registrar()` cannot express a model-less event). Scenario 5.1.b narrowed to the known-user failed-login case for symmetry.
6. Folded design's explicit `Gate::policy()` registration mechanism (new `AuthServiceProvider`, D7) into the existing Requirement 4.2 rather than duplicating it.
7. Updated the top grounding note, Open Items list, and Risks list throughout to mark resolved items as resolved (with design's decision ID referenced) rather than leaving stale `DEPENDS-ON-DESIGN` tags or open risks that design has since closed.
8. Everything not called out above (Requirements 1.2, 1.3, 2.1, 3.1 audit-gate structure, 4.1.b parity scenario, N.1/N.2 non-goals, Cross-Cutting Constraints) is preserved from the original spec — design did not invalidate it.
