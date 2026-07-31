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

- [x] 5b.1 Enumerate the 20 missing-Policy models beyond the 5 named (`Mora`, `CuotaIndividual`, `CuotasGrupales`, `PrestamoIndividual`, `SeparacionCliente`); record the full 31-model list with exclusion justification for any non-authorizable model (Scenario 4.3.b). Completed in PR7: full enumeration is 11 original + 5 financial (PR5) + 4 lifecycle (PR6) + 10 catalog (PR7) = 30 Policies, plus 1 justified exclusion (`GrupoCliente` — pure join-table model for the `grupo_cliente` pivot, zero direct call sites anywhere in `app/`, never wired via `->using()`, no Filament Resource of its own) = 31 total models, matching design D7's count exactly.
- [x] 5b.2 RED (financial group): parity unit tests for `Mora`, `CuotaIndividual`, `CuotasGrupales`, `AplicacionPago`, `AjusteDeuda` — allowed stays allowed, denied stays denied (Scenario 4.3.a). PR5, branch `laravel-security-hardening/05-policies-financial`. Genuine RED confirmed: `UnknownClassOrInterfaceException` for all 5 missing Policy classes before implementation (`tests/Unit/Policies/FinancialModelsPolicyTest.php`, `tests/Unit/Providers/AuthServiceProviderFinancialPolicyRegistrationTest.php`).
- [x] 5b.3 GREEN (financial group): create the 5 Policy classes (`viewAny/view/create/update/delete` minimum, copy Bucket-A closures where they exist, else mirror `PrestamoPolicy`'s Shield shape) + `PermissionSeeder` rows + role assignments in the SAME commit (D7 gotcha — no rows = blank screen for everyone). PR5. **Rule-parity finding**: grep across `app/Filament` and `app/Domain/Pagos` confirmed all 5 financial-group models (`Mora`, `CuotaIndividual`, `CuotasGrupales`, `AplicacionPago`, `AjusteDeuda`) have ZERO existing Bucket-A closures — no `hasRole`/`hasAnyRole`/`visible`/`hidden`/`authorize`/`canX` gate references any of them directly (they're only read via relationships from `Pago`-gated pages, or written by ungated domain services). Per D7's explicit fallback ("mirror PrestamoPolicy's Shield shape" for no-inline-check models) + Requirement 4.3's no-silent-tightening rule: implemented Shield-style permission checks (`$user->can('view_any_mora')` etc.), with `PermissionSeeder` granting every new permission to ALL FOUR existing roles (super_admin, Jefe de creditos, Jefe de operaciones, Asesor) — functionally equivalent to "open to any authenticated user" (the true current behavior) while staying idiomatically consistent with the other 11 Policies. `AuthServiceProvider`'s `$policies` map extended with the 5 new entries (PR4's 11 preserved, alphabetized together). None of these Policies are referenced by any Filament call site yet (5c-5g, later PRs) — additive-only, confirmed via `git status` scope check (zero touches to `app/Filament`).
- [x] 5b.4 RED then GREEN (lifecycle group): repeat 5b.2/5b.3 for `PrestamoIndividual`, `SeparacionCliente`, `Reagrupacion`, `RetanqueoIndividual`. PR6, branch `laravel-security-hardening/06-policies-lifecycle`. Genuine RED confirmed: `UnknownClassOrInterfaceException` for all 4 missing Policy classes before implementation (`tests/Unit/Policies/LifecycleModelsPolicyTest.php`, `tests/Unit/Providers/AuthServiceProviderLifecyclePolicyRegistrationTest.php` — 17 failing tests). **Rule-parity finding**: grep across `app/Filament`, `app/Domain/Prestamos`, and `app/Domain/Grupos` confirmed all 4 lifecycle-group models have ZERO existing Bucket-A authorization gate. `PrestamoIndividual` and `RetanqueoIndividual` ARE referenced in Filament (`PrestamoResource`'s `CreatePrestamo.php`/`EditPrestamo.php`, `PagoResource.php`/`GrupoDetallePagos.php`, `RetanqueoResource\Pages\EditRetanqueo.php`), but only as plain data reads/writes (`::where(...)->get()`, `::create([...])`, `::find(...)`) — never behind a `hasRole`/`hasAnyRole`/`visible`/`hidden`/`authorize`/`canX` gate (not Bucket A per D6). `SeparacionCliente` and `Reagrupacion` have zero references anywhere under `app/Filament` — no Filament Resource of their own; written only by ungated domain services (`MorosoSeparationService`, `RetanqueoWorkflowService`/`RetanqueoEjecucionService`). Per D7's fallback + Requirement 4.3's no-silent-tightening rule: implemented Shield-style permission checks (`$user->can('view_any_prestamo_individual')` etc.), with `PermissionSeeder` granting every new permission to ALL FOUR existing roles — functionally equivalent to "open to any authenticated user" (the true current behavior). `AuthServiceProvider`'s `$policies` map extended with the 4 new entries (PR4's 11 + PR5's 5 preserved, alphabetized together). None of these Policies are referenced by any Filament call site yet (5c-5g, later PRs) — additive-only, confirmed via `git status` scope check (zero touches to `app/Filament`/`app/Models`).
- [x] 5b.5 RED then GREEN (catalog group): repeat 5b.2/5b.3 for the remaining ~11 models. PR7, branch `laravel-security-hardening/07-policies-catalog`. Genuine RED confirmed: `UnknownClassOrInterfaceException` for all 10 missing Policy classes before implementation (`tests/Unit/Policies/CatalogModelsPolicyTest.php`, `tests/Unit/Providers/AuthServiceProviderCatalogPolicyRegistrationTest.php` — 41 failing tests). **Model-list derivation**: `app/Models/*.php` (29 files) minus the 20 already registered by PR4-PR6 (10 of PR4's 11, since `Role` is Spatie not `app/Models`, plus PR5's 5 + PR6's 4) leaves exactly 11 candidates: `AuditLog`, `BusinessRuleConfig`, `Categoria`, `ClienteScoring`, `ConsultaAsistente`, `GrupoCliente`, `MetaMensual`, `MetricaDiariaAsesor`, `MovimientoFinanciero`, `Persona`, `Subcategoria`. Of these, `GrupoCliente` is EXCLUDED (Scenario 4.3.b) — confirmed via grep, `GrupoCliente::` has zero direct call sites anywhere in `app/` (accessed exclusively via `Grupo`/`Cliente`'s `belongsToMany()` relationships, never queried or mutated directly, never wired as an explicit pivot model via `->using()`, no Filament Resource). The remaining 10 models each received a Policy. **Rule-parity finding**: grep across `app/Filament`, `app/Domain`, `app/Services` confirmed all 10 catalog models have ZERO existing Bucket-A closures — every reference (`AuditLog::delModelo()`, `Persona::where('DNI', ...)`, `Categoria::find()`, `Subcategoria::create()`, `MetricaDiariaAsesor::where('asesor_id', ...)`, `ClienteScoring::whereIn()`, `MetaMensual::where('anio', ...)`, `BusinessRuleConfig::where('clave', ...)`) is a plain data read/write inside Filament form callbacks or a Service, never behind a `hasRole`/`hasAnyRole`/`visible`/`hidden`/`authorize`/`canX` gate; `MovimientoFinanciero` and `ConsultaAsistente` have zero references outside their own model/factory/test files. Per D7's fallback + Requirement 4.3's no-silent-tightening rule: implemented Shield-style permission checks (`$user->can('view_any_audit_log')` etc.), with `PermissionSeeder` granting every new permission to ALL FOUR existing roles. `AuthServiceProvider`'s `$policies` map extended with the 10 new entries (PR4's 11 + PR5's 5 + PR6's 4 preserved, now 30 entries total, alphabetized together). None of these Policies are referenced by any Filament call site yet (5c-5g, later PRs) — additive-only, confirmed via `git status --short -- app/Filament app/Models` (empty).
- [x] 5b.6 RUN (financial group only): `php artisan test --compact tests/Unit/Policies tests/Unit/Providers tests/Unit/Architecture` — 35 passed, 129 assertions. Filament panel smoke-test NOT performed — no call site references these Policies yet in this PR, so there is no blank-screen risk to smoke-test against (that risk surfaces starting at Slice 5c-5g, once call sites are wired). Full-suite/DB-backed regression NOT run — remote test DB (`metro.proxy.rlwy.net:13114`) unreachable in this sandbox (4th-consecutive-PR constraint, see PR2-PR4 apply-progress).
- [x] 5b.6b RUN (lifecycle group, PR6): `php artisan test --compact tests/Unit/Policies tests/Unit/Providers tests/Unit/Architecture` — 52 passed, 189 assertions (includes PR4's and PR5's own tests unchanged — no regression). Filament panel smoke-test NOT performed — same rationale as PR5 (no call site references these Policies yet). Full-suite/DB-backed regression NOT run — remote test DB (`metro.proxy.rlwy.net:13114`) unreachable in this sandbox (`php artisan db:show` timed out after 15s, `timeout` exit code 124 — 6th-consecutive-PR constraint, see PR2-PR5 apply-progress). `PermissionSeeder`'s real-DB-seeded behavior remains unverified end-to-end for the same reason.
- [x] 5b.6c RUN (catalog group, PR7 — completes Slice 5b): `php artisan test --compact tests/Unit/Policies tests/Unit/Providers tests/Unit/Architecture` — 93 passed, 339 assertions (includes PR4's/PR5's/PR6's own tests unchanged — no regression). Filament panel smoke-test NOT performed — same rationale as PR5/PR6 (no call site references these Policies yet). Full-suite/DB-backed regression NOT run — remote test DB (`metro.proxy.rlwy.net:13114`) unreachable in this sandbox (`timeout 15 php artisan db:show` → exit code 124 — 7th-consecutive-PR constraint, see PR2-PR6 apply-progress). `PermissionSeeder`'s real-DB-seeded behavior remains unverified end-to-end for the same reason.

**Delivery decisions resolved (orchestrator, ask-on-risk gate) — PR5 SHIPPED, PR #18 open**:
- **`size:exception` granted**: this PR's diff (5 tightly-coupled financial-domain Policies, already trimmed to spec's minimum `viewAny/view/create/update/delete` shape) is 506 lines / 10 files, ~25% over the 400-line soft budget despite Slice 5b already being split 3 ways. User confirmed proceeding as-is rather than splitting the 5-model group further (no further split maps to a real reviewable boundary — the 5 Policies are structurally identical).
- **Chain base corrected, PR opened**: PR5 has a genuine code dependency on PR4's `AuthServiceProvider` (needed PR4's commit onto this branch to extend it), so PR5's PR (**#18**, https://github.com/ANDREEEEVELEZ/financieraNUEVO/pull/18) targets PR4's branch `laravel-security-hardening/04-gate-policy-map` directly (feature-branch-chain stacking), NOT the tracker `feature/laravel-security-hardening`. Once PR4 merges into the tracker, PR5's base should be retargeted to the tracker.
- **Cherry-pick → rebase correction (important for PR6/PR7)**: initially cherry-picked PR4's commit onto this branch, which created a NEW commit SHA (`88b8e92`) distinct from PR4 branch's actual tip (`3de8ccf`). This meant `git merge-base(04-gate-policy-map, 05-policies-financial)` resolved to the tracker commit BEFORE PR4 (not to PR4's tip), so a three-dot diff — which is what GitHub's "Files changed" tab uses — still showed the polluted ~682-line diff (PR4's 180 lines double-counted) EVEN with the correct base branch set. Fix: `git rebase --onto origin/laravel-security-hardening/04-gate-policy-map 88b8e92 HEAD` (replays only the PR5-specific commits onto PR4 branch's real tip, dropping the redundant cherry-pick commit) — this made `merge-base` resolve correctly to `3de8ccf`, giving a genuinely clean 506-line diff, confirmed both locally (`git diff --stat`) and via `gh pr view --json additions,deletions,changedFiles` (506/10/10) after opening the PR. **Lesson for PR6/PR7**: when a later slice needs to extend a file from an unmerged prior PR, do NOT cherry-pick that PR's commit as a same-branch addition and then point the PR base at that PR's branch — cherry-picking changes the commit SHA and breaks merge-base resolution. Either (a) branch PR6/PR7 directly off the prior PR's actual branch tip (`git checkout -b 06-... origin/laravel-security-hardening/05-policies-financial`) so the commit is a real ancestor, or (b) cherry-pick as done here but then rebase `--onto` the real branch tip before opening the PR, as done in this fix.

**Delivery decisions resolved (orchestrator, ask-on-risk gate) — PR6 SHIPPED, PR #19 open**:
- **`size:exception` granted**: PR6's diff is 449 changed lines (441 insertions / 8 deletions, 9 files, verified via `gh pr view 19 --json additions,deletions,changedFiles` after opening), ~12% over the 400-line soft budget. Same reasoning as PR5 (506 lines / 25% over): the 4 Policies are structurally identical (~56-58 lines each, same Shield-CRUD shape), `AuthServiceProvider`/`PermissionSeeder` changes are shared infrastructure that must ship together per D7's gotcha, and no further per-model split creates a meaningfully separate review boundary.
- **Standing exception for the rest of Slice 5b (PR7/catalog group)**: the orchestrator/user pre-approved `size:exception` as a standing rule for this exact pattern — identical small Shield-CRUD Policy classes + shared `AuthServiceProvider`/`PermissionSeeder` infra — for the remainder of Slice 5b, so PR7 does not need to re-litigate this specific decision. This does NOT cover a genuinely different structure (e.g. a model needing domain-verb methods beyond CRUD, a materially different Policy shape, or a diff exceeding budget for a different reason) — those cases must still stop and report.
- **Clean stacking confirmed from the start**: PR6 branched directly off PR5's real branch tip (`git fetch` + `git checkout -b ... FETCH_HEAD`, not cherry-picked) — avoided PR5's merge-base pitfall entirely. `git merge-base(05-policies-financial, 06-policies-lifecycle)` resolves correctly to `db7fa68` (PR5 branch's actual tip) on the first attempt; PR #19 (https://github.com/ANDREEEEVELEZ/financieraNUEVO/pull/19) targets `laravel-security-hardening/05-policies-financial` directly (feature-branch-chain stacking), and `gh pr view` confirms a clean diff containing only PR6's 2 commits.

**Delivery decisions resolved (orchestrator, ask-on-risk gate) — PR7 SHIPPED, PR #20 open — Slice 5b COMPLETE**:
- **`size:exception` applied per the PR6 standing rule**: PR7's diff is 855 changed lines (855 insertions / 7 deletions, 14 files, verified via `gh pr view 20 --json additions,deletions,changedFiles`), more than double the 400-line soft budget in absolute terms, but per-model average is 85.5 lines/model (855÷10) — LOWER than PR5's 101.2 lines/model and PR6's 112.25 lines/model. The larger absolute total is proportional to covering 10 models (vs 4-5 in PR5/PR6), not a structural deviation; the shape (identical Shield-CRUD Policies + shared `AuthServiceProvider`/`PermissionSeeder` infra) is unchanged from PR5/PR6, so the standing exception applies without re-litigating.
- **Clean stacking confirmed again**: PR7 branched directly off PR6's real branch tip (`git fetch` + `git checkout -b ... FETCH_HEAD`, not cherry-picked). `git log --oneline -6` on the new branch confirmed PR4/PR5/PR6's real commits (`166cbcd`, `e411d18`, `56b357d`, etc.) already in ancestry. PR #20 (https://github.com/ANDREEEEVELEZ/financieraNUEVO/pull/20) targets `laravel-security-hardening/06-policies-lifecycle` directly (feature-branch-chain stacking), and `gh pr view 20` confirms a clean diff (855/7/14) containing only PR7's own 1 commit.
- **Slice 5b is now fully complete**: 11 original (PR4) + 5 financial (PR5) + 4 lifecycle (PR6) + 10 catalog (PR7) = 30 registered Policies, covering 30 of design D7's 31 counted models. The 31st, `GrupoCliente`, is a justified exclusion (Scenario 4.3.b) — documented in `AuthServiceProvider`'s docblock and `CatalogModelsPolicyTest.php`'s docblock. Task 5b.1 (full enumeration + exclusion justification) is now fully satisfied across the three PRs.

## Slice 5c — `PagoResource` + Pages (~60 hits, largest; `GrupoDetallePagos.php` = 18)

- [x] 5c.1 RED: role×state parity regression test per Bucket-A hunk (Scenario 4.1.b). Actual hit count (re-grepped at apply time, not design's ~60 estimate): 49 raw `hasRole`/`hasAnyRole` occurrences across `PagoResource.php` (14), `GrupoDetallePagos.php` (18), `EditPago.php` (9), `ListPagos.php` (4), `PagosStatsWidget.php` (4); `CreatePago.php` has zero. DB-dependent Livewire/Feature tests are blocked by the standing environment constraint (remote test DB unreachable, 8th consecutive PR — `timeout 15 php artisan db:show` → exit 124), so per the same DB-free precedent PR4-PR7 established, RED/GREEN evidence lives in `tests/Unit/Policies/PagoPolicyMigrationParityTest.php` (Mockery-stubbed `User::hasRole`/`hasAnyRole`, no DB) instead of one Livewire test file per Page. Genuine RED confirmed: 13 of 21 assertions failed with `Call to undefined method App\Policies\PagoPolicy::{rechazar,aprobarMasivo,crearParaGrupo,verEnGrupoDetalle}()` before implementation (the other 8 — `aprobar`/`revertir`, which already existed from PR4 — passed pre-migration, since only the ROLE-check bodies of those methods are exercised and they were already correct).
- [x] 5c.2 GREEN: replaced each Bucket-A `hasRole`/`hasAnyRole` closure with `$user->can(...)`, verbatim-copying the ROLE-check portion of each closure into a `PagoPolicy` method (state checks, which differ per call site — 'pendiente' for aprobar/rechazar, 'aprobado' for revertir — stay inline at each call site, same convention as `PrestamoPolicy::aprobar/firmar/desembolsar`'s "state validity checked separately by the caller"). 12 Bucket-A `->visible()` sites migrated across 3 files; `PagoPolicy` gained 4 new methods (`rechazar`, `aprobarMasivo`, `crearParaGrupo`, `verEnGrupoDetalle`) and reused 2 existing ones (`aprobar`, `revertir`, both already correct from PR4 — same role set, matching Risk #3's "treat the existing Policy method as source of truth" guidance).
- [x] 5c.3 RUN: re-grepped `hasRole`/`hasAnyRole` in all 5 files post-migration — zero remain inside any `->visible(`/`->hidden(`/`->authorize`/static-canX Bucket-A pattern (all 12 sites confirmed migrated); 36 raw hits remain, all Bucket B (data-scoping: `options()`/`getEloquentQuery()`/`getFilteredQuery()` filtering by `asesor_id`) or Bucket C (UI cosmetics: `->icon()`/`->color()`) or explicitly out-of-scope per the operational Scenario-4.1.a grep-pattern rule (`isFormDisabled()`/`shouldDisableForm()`, `mutateFormDataBeforeSave()`, `getFormActions()`, `mount()`'s `abort(403)`, and the `->action()` closure's inline re-check — none of these are literally `->visible()`/`->hidden()`/`->authorize()`/static canX, so per the mechanical grep-pattern definition established for this migration they are not Bucket A; flagged in apply-progress as borderline cases for a future PR/discussion, not silently dropped). `git status --short -- app/Filament` confirms only the 3 intended files touched (`CreatePago.php`, `ListPagos.php`, `PagosStatsWidget.php` untouched). Diff: 4 files changed, 87 insertions / 37 deletions in `app/` (124 lines) + 1 new test file (150 lines) = ~274 total changed lines, well under the 400-line budget — no split needed.

## Slice 5d — `PrestamoResource` + Pages (~17 hits)

- [x] 5d.1 RED: parity regression tests per hunk. `tests/Unit/Policies/PrestamoPolicyMigrationParityTest.php` (new, DB-free per standing 9th-consecutive-PR environment constraint). Genuine RED confirmed: 8 failing assertions with `Call to undefined method App\Policies\PrestamoPolicy::{reenviar,verFiltroAsesor}()` before implementation (re-verified by temporarily `git stash`-ing the Policy change and re-running the filtered subset — restored immediately after, no work lost).
- [x] 5d.2 GREEN: migrated 6 Bucket-A call sites (8 raw `hasRole`/`hasAnyRole` hits): the Asesor-filter `->visible()` (new `PrestamoPolicy::verFiltroAsesor`), the `reenviar` action `->visible()` (new `PrestamoPolicy::reenviar`, state check folded in per this Policy's own `reformular`/`condonarMora` convention), and the static `canEdit`/`canView`/`canCreate`/`canDelete` overrides (deleted from the Resource; their bodies moved verbatim into `PrestamoPolicy::update/view/create/delete`, letting Filament's default `Resource::canEdit/canView/canCreate/canDelete` delegate through Gate). **Drift verified before reuse (Risk #3)**: `aprobar`/`firmar`/`desembolsar`/`rechazar` were found ALREADY migrated to `auth()->user()?->can(...)` calls in a prior maintenance pass — not touched. `view`/`create`/`update`/`delete`'s PRE-EXISTING Spatie-permission bodies (`$user->can('update_prestamo')` etc.) DID drift from the Resource's richer canEdit/canView/canCreate/canDelete logic (asesor-ownership + estado + retanqueo checks) — confirmed via grep that zero callers anywhere in `app/` ever invoke `$user->can('update_prestamo'|'view_prestamo'|'create_prestamo'|'delete_prestamo')` directly, so the Spatie-permission bodies were provably unreachable dead code (the Resource's own overrides always short-circuited first). This is a verified drift RESOLUTION (moving the one actually-governing implementation into the Policy, replacing dead code), not a silent pick between two live authorization paths — see apply-progress for the full verification trail.
- [x] 5d.3 RUN: grep confirms zero Bucket-A matches remain — re-grepped `hasRole`/`hasAnyRole` post-migration: 8 raw hits remain across `PrestamoResource.php`/`ListPrestamo.php`/`ViewPrestamo.php`, all Bucket B (data-scoping: `options()`/`getEloquentQuery()`/bulk-export query filtering by asesor), Bucket C (feeds `->disabled()` only), or dead code (`$puedeEditarEstado` computed but never referenced anywhere in the file). `EditPrestamo.php`'s 4 `roles->pluck('name')->contains/intersect()` sites and `ViewPrestamo.php`'s `mount()` page-gate and `PrestamoResource.php`'s own dead (never-called) `mutateFormDataBeforeSave()` method are flagged as non-matching-syntax (same category as PR8's flagged `mount()`/`shouldDisableForm()`/`getFormActions()` findings) — not migrated, not silently dropped.

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
