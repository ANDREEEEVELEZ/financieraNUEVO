# Tasks: Laravel Security Hardening

Reads: `spec.md` (reconciled, RFC-2119), `design.md` (D1-D8), `proposal.md`. Strict TDD is active for this project (Pest 3.8, `php artisan test`) — every behavioral task below follows RED (failing test first) → GREEN (implement) → RUN (full-suite regression check).

**Numbering note**: spec's own slice numbers (Slice 4 = authorization, Slice 5 = auth-event logging) collide with design's Sequencing table (slice 4 = auth-event logging, slice 5 = authorization, 5a-5g). This document follows **design's structural seam** for authorization (5a-5g, the chaining unit) and labels auth-event logging **Slice 6** to avoid colliding with either prior scheme. Slice 4 has no standalone task set — see note below.

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~2650-2950 total (see per-slice table below) |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | 13 work units, PR1 → PR13 (see table) |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: High

### Per-Slice Line Estimate

| Slice | Est. lines | >400 alone? |
|---|---|---|
| 1 — Token wiring | ~250 | No |
| 2 — Password policy | ~60 | No |
| 3 — PII encryption (numero_cuenta_desembolso) | ~150 | No |
| 5a — AuthServiceProvider + 11 existing Policies | ~50 | No |
| 5b — ~20 new Policies + PermissionSeeder (financial/lifecycle/catalog) | ~800-900 | **Yes — must split 3 ways** |
| 5c — PagoResource + Pages (~60 hits, GrupoDetallePagos alone = 18) | ~300-450 | **Borderline/likely** |
| 5d — PrestamoResource + Pages (~17 hits) | ~160-200 | No |
| 5e — RetanqueoResource + Pages (~17 hits) | ~160-200 | No |
| 5f — GrupoResource + Pages (~15 hits) | ~140-180 | No |
| 5g — Remaining Resources/Widgets/Pages (~17 hits) | ~160-200 | No |
| 6 — Auth-event logging | ~250 | No |

**5b** exceeds 400 alone even though it is "purely additive" per D7 — 20 Policy classes plus unit tests plus `PermissionSeeder` rows is real weight. It is already split into financial/lifecycle/catalog groups below; treat each group as its own PR (5 work units in the table are already this split).

**5c is the flagged unknown.** The closure→Policy call-site diff itself is small (~1 line/hit × 60 ≈ 60-100 lines, per D6's "mechanical" migration form), but Scenario 4.1.b requires a role×state parity regression test per Bucket-A hunk, and `GrupoDetallePagos.php` alone carries 18 of the 60 hits plus several Bucket-C touches nearby (do not touch Bucket C — risk of scope creep inflating the diff further). Recommendation: attempt 5c as **one PR** first; if the actual diff during `sdd-apply` exceeds ~400 lines, split into **5c-i** (`PagoResource.php` core + simple Pages) and **5c-ii** (`GrupoDetallePagos.php` only, isolated because of its outsized hit density). Flag this explicitly to the user at apply time if it fires.

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Slice 1 — token wiring | PR1 | Independent; corrects `BACKLOG.md` |
| 2 | Slice 2 — password policy | PR2 | Independent |
| 3 | Slice 3 — PII encryption | PR3 | Independent; audit gate blocks start |
| 4 | Slice 5a — Gate::policy() map, 11 existing | PR4 | No behavior change |
| 5 | Slice 5b-financial (Mora, CuotaIndividual, CuotasGrupales, AplicacionPago, AjusteDeuda) | PR5 | Depends on PR4; additive only |
| 6 | Slice 5b-lifecycle (PrestamoIndividual, SeparacionCliente, Reagrupacion, RetanqueoIndividual) | PR6 | Depends on PR4; additive only |
| 7 | Slice 5b-catalog (remaining ~11 models) | PR7 | Depends on PR4; additive only |
| 8 | Slice 5c — PagoResource + Pages (largest call-site PR; may split further, see above) | PR8 | Depends on PR5-7 (Policies must exist first) |
| 9 | Slice 5d — PrestamoResource + Pages | PR9 | Depends on PR5-7 |
| 10 | Slice 5e — RetanqueoResource + Pages | PR10 | Depends on PR5-7 |
| 11 | Slice 5f — GrupoResource + Pages | PR11 | Depends on PR5-7 |
| 12 | Slice 5g — remaining Resources/Widgets/Pages | PR12 | Depends on PR5-7 |
| 13 | Slice 6 — auth-event logging | PR13 | Independent of the 5a-5g chain |

---

## Slice 1 — Auth token wiring + password-reset throttle

- [ ] 1.1 RED: update `tests/Feature/Api/Auth/LoginTest.php` to assert `data.access_token`/`data.refresh_token`/`token_type`/`expires_in` instead of `data.token` (Scenario 1.1.a); confirm it fails against current `LoginController`.
- [ ] 1.2 RED: add TTL assertions — access token ~7200s, refresh token ~30d (Scenarios 1.1.b, 1.1.c).
- [ ] 1.3 GREEN: create `app/Contracts/TokenIssuerInterface.php` + `app/Domain/Auth/IssueTokenPair.php` (explicit `createToken(...,$expiresAt)`, reuse `RefreshToken::issueFor()` unchanged).
- [ ] 1.4 GREEN: `config/sanctum.php` `expiration` → `env('SANCTUM_EXPIRATION')` (null); add `access_token_ttl_minutes` to `config/api_auth.php`.
- [ ] 1.5 GREEN: bind `TokenIssuerInterface` in `AppServiceProvider::register()`; rewrite `LoginController::__invoke()` to delegate, both `/auth/login` and `/v1/auth/login`.
- [ ] 1.6 RED: guard test — `expires_in` never `0` once `sanctum.expiration` is null (Scenario 1.1.d).
- [ ] 1.7 GREEN: fix `RefreshTokenController.php:55` to read `api_auth.access_token_ttl_minutes` via `IssueTokenPair`, not `config('sanctum.expiration')`.
- [ ] 1.8 GREEN: wire `routes/api.php` — `v1` routes for `refresh` (public), `logout-all` (`auth:sanctum`), `forgot-password`/`reset-password` (public); no legacy unprefixed aliases (D3).
- [ ] 1.9 RED then GREEN: add Scenario 1.3.a test (4th `forgot-password` request/min → 429); apply `throttle:password-reset` middleware.
- [ ] 1.10 RUN: `php artisan test tests/Feature/Api/Auth --compact` — 0 failures across all 5 files (Scenario 1.1.e); record actual pass count.
- [ ] 1.11 Update `BACKLOG.md:9` to the actually-observed count, replacing the stale "95/95" claim.

## Slice 2 — Global password policy

- [ ] 2.1 RED: Pest tests for weak-password rejection (2.1.a), compromised-password rejection (2.1.b), strong-password acceptance (2.1.c) against `ResetPasswordRequest`.
- [ ] 2.2 GREEN: register `Password::defaults()` in `AppServiceProvider::boot()` — `min(8)->mixedCase()->numbers()->symbols()`, `->uncompromised()` guarded off in `local`/`testing` (D4).
- [ ] 2.3 Document Scenario 2.1.d (HIBP fail-open) as an accepted risk in a code comment near the guard — no new logic, existing Laravel behavior.
- [ ] 2.4 RUN: `php artisan test --compact --filter=Password` — green.

## Slice 3 — PII encryption (`numero_cuenta_desembolso` only)

Non-goal reminder: `Persona.DNI` is explicitly descoped (Requirement 3.3) — no task here touches `DNI`, `DNI_hash`, `UniqueDNI`, or `ClienteResource`'s DNI column.

- [ ] 3.1 BLOCKING AUDIT (Requirement 3.1): `rg -n "numero_cuenta_desembolso" app/ database/ resources/ routes/`; classify every hit (attribute-access/`where`/`LIKE`/`ORDER BY`/raw SQL/column-restricted select); re-confirm D5's known sites (`Api/PrestamoResource.php:36`, `PrestamoController.php:229`, `RetanqueoEjecucionService`, `RetanqueoWorkflowService`) are still attribute-access-only; record the table in the PR description.
- [ ] 3.2 GATE: do not start 3.3+ until 3.1's table shows zero `where`/`LIKE`/`ORDER BY`/raw-SQL hits (Scenario 3.1.b).
- [ ] 3.3 RED: test — raw DB column differs from plaintext post-cast; Eloquent read returns plaintext (Scenario 3.2.a).
- [ ] 3.4 GREEN: add `'numero_cuenta_desembolso' => 'encrypted'` to `Prestamo::$casts`.
- [ ] 3.5 RED: backfill test — existing plaintext rows encrypted in place, Eloquent read unchanged (Scenario 3.2.b).
- [ ] 3.6 GREEN: create `database/migrations/*_encrypt_numero_cuenta_desembolso.php`, `Model::chunkById(500)`, each chunk in a transaction.
- [ ] 3.7 RED: `down()` round-trip test — decrypt back to plaintext, no data loss (Scenario 3.2.c).
- [ ] 3.8 GREEN: implement `down()` — read via cast, write plaintext via `DB::table()->update()`.
- [ ] 3.9 OPS checklist (non-code): confirm verified DB backup precedes production execution (Scenario 3.2.d) — record in the runbook, not enforced by app code.

## Slice 4 — N/A (confirmed against design)

No standalone task set. Spec's Requirement 4 (authorization consolidation) is fully covered by Slice 5a (registration) + Slice 5b (new Policies) + Slices 5c-5g (call-site migration) below — design's sequencing table treats this as one chained slice, not four.

## Slice 5a — `AuthServiceProvider` + `Gate::policy()` for the 11 existing Policies

- [ ] 5a.1 RED: test — `Gate::getPolicyFor($model)` resolves for each of the 11 existing models, including `Role` → `RolePolicy` (Scenario 4.2.a).
- [ ] 5a.2 GREEN: create `app/Providers/AuthServiceProvider.php` with the explicit map; register in `bootstrap/providers.php`.
- [ ] 5a.3 RUN: confirm zero behavior change — existing Filament policy-based checks still pass.

## Slice 5b — ~20 new Policy classes + `PermissionSeeder` (purely additive, split 3 ways)

- [ ] 5b.1 Enumerate the 20 missing-Policy models beyond the 5 named (`Mora`, `CuotaIndividual`, `CuotasGrupales`, `PrestamoIndividual`, `SeparacionCliente`); record the full 31-model list with exclusion justification for any non-authorizable model (Scenario 4.3.b).
- [ ] 5b.2 RED (financial group): parity unit tests for `Mora`, `CuotaIndividual`, `CuotasGrupales`, `AplicacionPago`, `AjusteDeuda` — allowed stays allowed, denied stays denied (Scenario 4.3.a).
- [ ] 5b.3 GREEN (financial group): create the 5 Policy classes (`viewAny/view/create/update/delete` minimum, copy Bucket-A closures where they exist, else mirror `PrestamoPolicy`'s Shield shape) + `PermissionSeeder` rows + role assignments in the SAME commit (D7 gotcha — no rows = blank screen for everyone).
- [ ] 5b.4 RED then GREEN (lifecycle group): repeat 5b.2/5b.3 for `PrestamoIndividual`, `SeparacionCliente`, `Reagrupacion`, `RetanqueoIndividual`.
- [ ] 5b.5 RED then GREEN (catalog group): repeat 5b.2/5b.3 for the remaining ~11 models.
- [ ] 5b.6 RUN: `php artisan test --compact --filter=Policy` — all new unit tests pass; smoke-test the Filament panel loads with no blank screens.

## Slice 5c — `PagoResource` + Pages (~60 hits, largest; `GrupoDetallePagos.php` = 18)

- [ ] 5c.1 RED: role×state parity regression test per Bucket-A hunk (Scenario 4.1.b), one Livewire test file per affected Page.
- [ ] 5c.2 GREEN: replace each Bucket-A `hasRole`/`hasAnyRole` closure with `$user->can(...)`/`Gate::allows(...)`, verbatim-copying the closure body into the matching Policy method from 5b.
- [ ] 5c.3 RUN: grep Bucket-A patterns in `PagoResource.php`/Pages — zero remain; confirm Bucket B/C untouched (Scenario 4.1.a). If diff exceeds ~400 lines, split into 5c-i (core + simple Pages) and 5c-ii (`GrupoDetallePagos.php` only) before requesting review.

## Slice 5d — `PrestamoResource` + Pages (~17 hits)

- [ ] 5d.1 RED: parity regression tests per hunk.
- [ ] 5d.2 GREEN: migrate closures to Policy calls; where `PrestamoPolicy::aprobar/firmar/desembolsar` already parallels a closure the Resource never called, treat the existing Policy method as source of truth (Risk #3), not the closure.
- [ ] 5d.3 RUN: grep confirms zero Bucket-A matches remain.

## Slice 5e — `RetanqueoResource` + Pages (~17 hits)

- [ ] 5e.1 RED: parity regression tests per hunk.
- [ ] 5e.2 GREEN: migrate closures to Policy calls.
- [ ] 5e.3 RUN: grep confirms zero.

## Slice 5f — `GrupoResource` + Pages (~15 hits)

- [ ] 5f.1 RED: parity regression tests per hunk.
- [ ] 5f.2 GREEN: migrate closures to Policy calls.
- [ ] 5f.3 RUN: grep confirms zero.

## Slice 5g — Remaining Resources/Widgets/Pages (~17 hits)

- [ ] 5g.1 RED: parity regression tests per hunk.
- [ ] 5g.2 GREEN: migrate closures to Policy calls.
- [ ] 5g.3 RUN: project-wide Bucket-A grep across all Filament files — zero matches; unscoped `hasRole`/`hasAnyRole` grep still shows Bucket B/C matches, which is expected per D6 (Scenario 4.1.a).

## Slice 6 — Auth-event logging (spec Requirement 5 / design D8)

- [ ] 6.1 RED: migration test — `audit_logs.auditable_type`/`auditable_id` accept null.
- [ ] 6.2 GREEN: create `database/migrations/*_make_auditable_nullable_in_audit_logs.php` (precedent: `2026_05_26_172145_make_user_id_nullable_in_audit_logs.php`).
- [ ] 6.3 RED: successful login produces an `audit_logs` row, `accion=auth_login` (Scenario 5.1.a).
- [ ] 6.4 GREEN: create `app/Contracts/AuthEventLoggerInterface.php` + `app/Domain/Auth/AuthEventLogger.php` (writes `AuditLog` directly) + `app/Listeners/Auth/LogSuccessfulLogin.php`; register via `Event::listen()` in `AppServiceProvider::boot()`.
- [ ] 6.5 RED: failed login for a KNOWN user produces a row, no password logged (Scenario 5.1.b).
- [ ] 6.6 GREEN: create `app/Listeners/Auth/LogFailedLogin.php` (known-user branch).
- [ ] 6.7 RED: failed login for an UNKNOWN email still produces a row via the nullable-auditable path (Scenario 5.1.c).
- [ ] 6.8 GREEN: extend `LogFailedLogin`/`AuthEventLogger` for the null-model case.
- [ ] 6.9 RED: logout produces a row (Scenario 5.1.d).
- [ ] 6.10 GREEN: create `app/Listeners/Auth/LogSuccessfulLogout.php`.
- [ ] 6.11 RED: password reset produces EXACTLY ONE row (Scenario 5.1.e).
- [ ] 6.12 GREEN: create `app/Listeners/Auth/LogPasswordReset.php`; REMOVE `ResetPasswordController.php:30`'s inline `registrar('password_reset', ...)` call.
- [ ] 6.13 RUN: assert no email/Slack/webhook side effect fires from any listener (Scenario 5.1.f).
- [ ] 6.14 RUN: `php artisan test --compact` full suite — 0 failures (cross-cutting constraint).
