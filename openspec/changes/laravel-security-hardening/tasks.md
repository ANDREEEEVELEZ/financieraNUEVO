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

- [x] 5a.1 RED: test — `Gate::getPolicyFor($model)` resolves for each of the 11 existing models, including `Role` → `RolePolicy` (Scenario 4.2.a).
- [x] 5a.2 GREEN: create `app/Providers/AuthServiceProvider.php` with the explicit map; register in `bootstrap/providers.php`.
- [x] 5a.3 RUN: confirm zero behavior change — existing Filament policy-based checks still pass.

**Completed (PR4)**: branch `laravel-security-hardening/04-gate-policy-map`. Diff: `app/Providers/AuthServiceProvider.php` (created), `bootstrap/providers.php` (+1 line), `tests/Unit/Providers/AuthServiceProviderPolicyRegistrationTest.php` (created) — 2 files changed in `git diff --stat` (new files untracked at diff time) + 1 new test file, well under the ~50-line estimate and the 400-line budget.

**RED/GREEN evidence**: the naive version of 5a.1 (`Gate::getPolicyFor($model)->toBeInstanceOf(...)` for each of the 11 models) turned out to already PASS pre-implementation for all 11 — not just the 10 `App\Models\*` cases (expected, Laravel's naming-convention auto-discovery already covers those), but also `Role` → `RolePolicy`, because `bezhansalleh/filament-shield`'s own service provider independently calls `Gate::policy(Utils::getRoleModel(), 'App\Policies\RolePolicy')` when `filament-shield.register_role_policy.enabled` is true (verified in `vendor/bezhansalleh/filament-shield/src/FilamentShieldServiceProvider.php:49-50`) — a fact neither spec nor design anticipated. A resolution-only assertion would not have been genuine RED, so the test was rewritten to additionally assert against `Gate::policies()` (the explicitly-registered map) for all 11 model=>policy pairs — this DOES fail pre-implementation (`Failed asserting that an array has the key 'App\Models\Asesor'`) and pass post-implementation. Both assertion styles are kept in the final test file (`tests/Unit/Providers/AuthServiceProviderPolicyRegistrationTest.php`) since Scenario 4.2.a's wording covers both "resolves correctly" and "via the explicit registration".

**Scope discipline confirmed**: `git status --short -- app/Filament database/seeders app/Policies` returns empty — zero touches to Filament Resources, `PermissionSeeder`, or existing Policy classes. No new Policy classes created. Zero behavior change: existing implicit/Shield-driven resolution for all 11 models was already correct; this PR only makes it explicit and reviewable in one canonical, project-owned location, independent of Shield's opt-in config flag.

**Test evidence**: `php artisan test tests/Unit/Providers/AuthServiceProviderPolicyRegistrationTest.php --compact` — 12 passed, 33 assertions. `php artisan test tests/Unit/Architecture tests/Unit/Providers --compact` — 14 passed, 54 assertions (broader DB-free regression check). `php -l` clean on all 3 changed/new PHP files.

**Known environment constraint (persists from PR2/PR3)**: the remote test DB (`metro.proxy.rlwy.net:13114`) was unreachable again this session — `php artisan db:show` hung with no output/timeout. Any test extending `Tests\TestCase` (which seeds roles via a DB query in `setUp()`) or using `RefreshDatabase` cannot run in this sandbox; `php artisan test tests/Unit --compact` (full Unit suite) was attempted and hung indefinitely for the same reason. This is why the new test uses the base `Illuminate\Foundation\Testing\TestCase` (boots the real app via `bootstrap/app.php`, no DB touch) instead of `Tests\TestCase` — Gate policy registration/resolution never needs the database. "5a.3 RUN: confirm zero behavior change" is therefore evidenced by (a) the DB-free test suite above, (b) the scope-discipline grep confirming no Filament/seeder/Policy files were touched, and (c) the mechanism itself being purely additive (explicit registration of already-correct resolutions) — NOT by a full Feature-level Filament regression run, which remains blocked by the same DB-unreachability constraint flagged in PR2 and PR3. Full-suite `php artisan test --compact` pass count could not be captured this session for the same reason.

## Slice 5b — ~20 new Policy classes + `PermissionSeeder` (purely additive, split 3 ways)

- [ ] 5b.1 Enumerate the 20 missing-Policy models beyond the 5 named (`Mora`, `CuotaIndividual`, `CuotasGrupales`, `PrestamoIndividual`, `SeparacionCliente`); record the full 31-model list with exclusion justification for any non-authorizable model (Scenario 4.3.b). NOT done in PR5 (financial-group-only scope) — spans all three 5b PRs; complete once PR5-PR7 all land.
- [x] 5b.2 RED (financial group): parity unit tests for `Mora`, `CuotaIndividual`, `CuotasGrupales`, `AplicacionPago`, `AjusteDeuda` — allowed stays allowed, denied stays denied (Scenario 4.3.a). PR5, branch `laravel-security-hardening/05-policies-financial`. Genuine RED confirmed: `UnknownClassOrInterfaceException` for all 5 missing Policy classes before implementation (`tests/Unit/Policies/FinancialModelsPolicyTest.php`, `tests/Unit/Providers/AuthServiceProviderFinancialPolicyRegistrationTest.php`).
- [x] 5b.3 GREEN (financial group): create the 5 Policy classes (`viewAny/view/create/update/delete` minimum, copy Bucket-A closures where they exist, else mirror `PrestamoPolicy`'s Shield shape) + `PermissionSeeder` rows + role assignments in the SAME commit (D7 gotcha — no rows = blank screen for everyone). PR5. **Rule-parity finding**: grep across `app/Filament` and `app/Domain/Pagos` confirmed all 5 financial-group models (`Mora`, `CuotaIndividual`, `CuotasGrupales`, `AplicacionPago`, `AjusteDeuda`) have ZERO existing Bucket-A closures — no `hasRole`/`hasAnyRole`/`visible`/`hidden`/`authorize`/`canX` gate references any of them directly (they're only read via relationships from `Pago`-gated pages, or written by ungated domain services). Per D7's explicit fallback ("mirror PrestamoPolicy's Shield shape" for no-inline-check models) + Requirement 4.3's no-silent-tightening rule: implemented Shield-style permission checks (`$user->can('view_any_mora')` etc.), with `PermissionSeeder` granting every new permission to ALL FOUR existing roles (super_admin, Jefe de creditos, Jefe de operaciones, Asesor) — functionally equivalent to "open to any authenticated user" (the true current behavior) while staying idiomatically consistent with the other 11 Policies. `AuthServiceProvider`'s `$policies` map extended with the 5 new entries (PR4's 11 preserved, alphabetized together). None of these Policies are referenced by any Filament call site yet (5c-5g, later PRs) — additive-only, confirmed via `git status` scope check (zero touches to `app/Filament`).
- [x] 5b.4 RED then GREEN (lifecycle group): repeat 5b.2/5b.3 for `PrestamoIndividual`, `SeparacionCliente`, `Reagrupacion`, `RetanqueoIndividual`. PR6, branch `laravel-security-hardening/06-policies-lifecycle`. Genuine RED confirmed: `UnknownClassOrInterfaceException` for all 4 missing Policy classes before implementation (`tests/Unit/Policies/LifecycleModelsPolicyTest.php`, `tests/Unit/Providers/AuthServiceProviderLifecyclePolicyRegistrationTest.php` — 17 failing tests). **Rule-parity finding**: grep across `app/Filament`, `app/Domain/Prestamos`, and `app/Domain/Grupos` confirmed all 4 lifecycle-group models have ZERO existing Bucket-A authorization gate. `PrestamoIndividual` and `RetanqueoIndividual` ARE referenced in Filament (`PrestamoResource`'s `CreatePrestamo.php`/`EditPrestamo.php`, `PagoResource.php`/`GrupoDetallePagos.php`, `RetanqueoResource\Pages\EditRetanqueo.php`), but only as plain data reads/writes (`::where(...)->get()`, `::create([...])`, `::find(...)`) — never behind a `hasRole`/`hasAnyRole`/`visible`/`hidden`/`authorize`/`canX` gate (not Bucket A per D6). `SeparacionCliente` and `Reagrupacion` have zero references anywhere under `app/Filament` — no Filament Resource of their own; written only by ungated domain services (`MorosoSeparationService`, `RetanqueoWorkflowService`/`RetanqueoEjecucionService`). Per D7's fallback + Requirement 4.3's no-silent-tightening rule: implemented Shield-style permission checks (`$user->can('view_any_prestamo_individual')` etc.), with `PermissionSeeder` granting every new permission to ALL FOUR existing roles — functionally equivalent to "open to any authenticated user" (the true current behavior). `AuthServiceProvider`'s `$policies` map extended with the 4 new entries (PR4's 11 + PR5's 5 preserved, alphabetized together). None of these Policies are referenced by any Filament call site yet (5c-5g, later PRs) — additive-only, confirmed via `git status` scope check (zero touches to `app/Filament`/`app/Models`).
- [ ] 5b.5 RED then GREEN (catalog group): repeat 5b.2/5b.3 for the remaining ~11 models. PR7, not started.
- [x] 5b.6 RUN (financial group only): `php artisan test --compact tests/Unit/Policies tests/Unit/Providers tests/Unit/Architecture` — 35 passed, 129 assertions. Filament panel smoke-test NOT performed — no call site references these Policies yet in this PR, so there is no blank-screen risk to smoke-test against (that risk surfaces starting at Slice 5c-5g, once call sites are wired). Full-suite/DB-backed regression NOT run — remote test DB (`metro.proxy.rlwy.net:13114`) unreachable in this sandbox (4th-consecutive-PR constraint, see PR2-PR4 apply-progress).
- [x] 5b.6b RUN (lifecycle group, PR6): `php artisan test --compact tests/Unit/Policies tests/Unit/Providers tests/Unit/Architecture` — 52 passed, 189 assertions (includes PR4's and PR5's own tests unchanged — no regression). Filament panel smoke-test NOT performed — same rationale as PR5 (no call site references these Policies yet). Full-suite/DB-backed regression NOT run — remote test DB (`metro.proxy.rlwy.net:13114`) unreachable in this sandbox (`php artisan db:show` timed out after 15s, `timeout` exit code 124 — 6th-consecutive-PR constraint, see PR2-PR5 apply-progress). `PermissionSeeder`'s real-DB-seeded behavior remains unverified end-to-end for the same reason.

**Delivery decisions resolved (orchestrator, ask-on-risk gate) — PR5 SHIPPED, PR #18 open**:
- **`size:exception` granted**: this PR's diff (5 tightly-coupled financial-domain Policies, already trimmed to spec's minimum `viewAny/view/create/update/delete` shape) is 506 lines / 10 files, ~25% over the 400-line soft budget despite Slice 5b already being split 3 ways. User confirmed proceeding as-is rather than splitting the 5-model group further (no further split maps to a real reviewable boundary — the 5 Policies are structurally identical).
- **Chain base corrected, PR opened**: PR5 has a genuine code dependency on PR4's `AuthServiceProvider` (needed PR4's commit onto this branch to extend it), so PR5's PR (**#18**, https://github.com/ANDREEEEVELEZ/financieraNUEVO/pull/18) targets PR4's branch `laravel-security-hardening/04-gate-policy-map` directly (feature-branch-chain stacking), NOT the tracker `feature/laravel-security-hardening`. Once PR4 merges into the tracker, PR5's base should be retargeted to the tracker.
- **Cherry-pick → rebase correction (important for PR6/PR7)**: initially cherry-picked PR4's commit onto this branch, which created a NEW commit SHA (`88b8e92`) distinct from PR4 branch's actual tip (`3de8ccf`). This meant `git merge-base(04-gate-policy-map, 05-policies-financial)` resolved to the tracker commit BEFORE PR4 (not to PR4's tip), so a three-dot diff — which is what GitHub's "Files changed" tab uses — still showed the polluted ~682-line diff (PR4's 180 lines double-counted) EVEN with the correct base branch set. Fix: `git rebase --onto origin/laravel-security-hardening/04-gate-policy-map 88b8e92 HEAD` (replays only the PR5-specific commits onto PR4 branch's real tip, dropping the redundant cherry-pick commit) — this made `merge-base` resolve correctly to `3de8ccf`, giving a genuinely clean 506-line diff, confirmed both locally (`git diff --stat`) and via `gh pr view --json additions,deletions,changedFiles` (506/10/10) after opening the PR. **Lesson for PR6/PR7**: when a later slice needs to extend a file from an unmerged prior PR, do NOT cherry-pick that PR's commit as a same-branch addition and then point the PR base at that PR's branch — cherry-picking changes the commit SHA and breaks merge-base resolution. Either (a) branch PR6/PR7 directly off the prior PR's actual branch tip (`git checkout -b 06-... origin/laravel-security-hardening/05-policies-financial`) so the commit is a real ancestor, or (b) cherry-pick as done here but then rebase `--onto` the real branch tip before opening the PR, as done in this fix.

**Delivery decisions resolved (orchestrator, ask-on-risk gate) — PR6 SHIPPED, PR #19 open**:
- **`size:exception` granted**: PR6's diff is 449 changed lines (441 insertions / 8 deletions, 9 files, verified via `gh pr view 19 --json additions,deletions,changedFiles` after opening), ~12% over the 400-line soft budget. Same reasoning as PR5 (506 lines / 25% over): the 4 Policies are structurally identical (~56-58 lines each, same Shield-CRUD shape), `AuthServiceProvider`/`PermissionSeeder` changes are shared infrastructure that must ship together per D7's gotcha, and no further per-model split creates a meaningfully separate review boundary.
- **Standing exception for the rest of Slice 5b (PR7/catalog group)**: the orchestrator/user pre-approved `size:exception` as a standing rule for this exact pattern — identical small Shield-CRUD Policy classes + shared `AuthServiceProvider`/`PermissionSeeder` infra — for the remainder of Slice 5b, so PR7 does not need to re-litigate this specific decision. This does NOT cover a genuinely different structure (e.g. a model needing domain-verb methods beyond CRUD, a materially different Policy shape, or a diff exceeding budget for a different reason) — those cases must still stop and report.
- **Clean stacking confirmed from the start**: PR6 branched directly off PR5's real branch tip (`git fetch` + `git checkout -b ... FETCH_HEAD`, not cherry-picked) — avoided PR5's merge-base pitfall entirely. `git merge-base(05-policies-financial, 06-policies-lifecycle)` resolves correctly to `db7fa68` (PR5 branch's actual tip) on the first attempt; PR #19 (https://github.com/ANDREEEEVELEZ/financieraNUEVO/pull/19) targets `laravel-security-hardening/05-policies-financial` directly (feature-branch-chain stacking), and `gh pr view` confirms a clean diff containing only PR6's 2 commits.

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
