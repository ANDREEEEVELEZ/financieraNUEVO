# Proposal: Laravel Security Hardening

## Intent

`BACKLOG.md:9` claims mobile auth hardening (2h access token + 30d refresh with rotation, `logout-all`, password reset, `throttle:api` 60/min on `/v1`) is **done, 95/95 tests passing**. Exploration proved otherwise: the controllers, `RefreshToken` model and `config/api_auth.php` exist but have **zero routes** in `routes/api.php`, and `LoginController.php:40-45` still returns the old `{token, user}` contract. The feature is orphan code. Planning the Flutter app on top of a false "done" is the immediate risk.

Alongside that, four verified gaps remain before more surface is exposed to mobile: no configured `Password::defaults()`, plaintext PII (`Prestamo.numero_cuenta_desembolso`, `Persona.DNI`), ~45 inline `request()->user()->hasRole(...)` authorization checks in Filament Resources with 18/29 models lacking a Policy, and no auth-event logging at all.

## Scope

### In Scope

1. **Auth token wiring** — wire `/v1/auth/{refresh,logout-all,forgot-password,reset-password}`; update `LoginController` to issue an access+refresh pair; **access token TTL 2 hours, refresh token TTL 30 days** (today `config/sanctum.php:20` is a flat 30d with no override); apply the already-defined-but-unused `throttle:password-reset` limiter; re-run the 5 `Api/Auth` test files for ground truth.
2. **Password policy** — register `Password::defaults()` as `min(8)->mixedCase()->numbers()->symbols()->uncompromised()` globally. Applied even though users are admin-created staff with no public `/register`, because they handle sensitive financial data.
3. **PII encryption** — add `'encrypted'` casts + backfill migration for `Prestamo.numero_cuenta_desembolso` and `Persona.DNI`. **Gated pre-check (mandatory, blocking)**: audit raw-SQL / non-Eloquent consumers of these columns (grep raw SQL, review report and export code paths) BEFORE any cast or migration. Eloquent-only access must not be assumed.
4. **Authorization consolidation** — absorbs `auditoria/plan-remediacion` T3.2 in full: migrate ALL inline `hasRole(...)` / closure checks in `PrestamoResource.php`, `GrupoResource.php`, `PagoResource.php` and any other Filament Resource with the same pattern into the 11 existing Policies with explicit `Gate::policy()` bindings, PLUS create the missing Policies for the 18/29 unprotected models (`CuotaIndividual`, `CuotasGrupales`, `Mora`, `PrestamoIndividual`, `SeparacionCliente`, and others as discovered).
5. **Auth-event logging** — `Login` / `Failed` / `Logout` / `PasswordReset` listeners that **only log**, reusing the existing `AuditService` / `audit_logs` infrastructure.
6. **Business-audit gap check (narrow)** — verify critical mora/cuota/prestamo_individual/separación mutations actually call `AuditService::registrar()` and fill missing calls. Verification + gap-filling only.
7. **Ops confirmation** — verify production `FRONTEND_URLS` is non-empty (`config/cors.php`); manual, not code.

### Out of Scope

- **Real-time alerting** (email/Slack) on auth events — explicit follow-up, not this phase.
- **`sdd/core-contable-seguridad`** — accounting/retanqueo ledger rework, incl. the already-done `AsesorScopeGuard` IDOR fix. Referenced, not redone.
- **`sdd/trazabilidad-modelo-datos`** — business-entity hash-chain audit trail / non-repudiation for Prestamo/Grupo history. Item 5 is a narrower, different concern; item 6 is gap-filling, **not** an audit-trail redesign.
- Deprecating legacy unprefixed routes (`BACKLOG.md:17`), Mora/Retanqueo route wiring (`BACKLOG.md:14`).

## Capabilities

### New Capabilities

- `api-auth-tokens`: access/refresh token lifecycle, TTLs, rotation, logout-all, password reset, auth rate limiting.
- `password-policy`: global password strength rules incl. breach check.
- `pii-encryption`: encryption at rest for bank account and DNI, with consumer audit and backfill.
- `model-authorization`: Policy coverage for all models + Filament Resources authorizing via Policies, not inline role checks.
- `auth-event-logging`: authentication events recorded through `AuditService`.
- `audit-coverage`: critical business mutations verified to call `AuditService::registrar()`.

### Modified Capabilities

None (no `openspec/specs/` exist yet).

## Approach

Five independent, individually shippable slices ordered by risk-to-value. Each slice is self-contained: it can land, be verified, and be rolled back without the others. Config-level changes (`Password::defaults()`, token TTLs) go through providers/config so they are revertible without touching call sites. PII encryption is strictly two-phase: audit gate first, cast + backfill second. Authorization consolidation is behavior-preserving by construction — each migrated closure maps 1:1 to a Policy method returning the same boolean.

## First-Pass Slice / Ordering Hint

> Refined properly at `sdd-tasks`. Flagged now because `delivery_strategy` is `ask-on-risk`.

| # | Slice | Est. footprint | Notes |
|---|-------|----------------|-------|
| 1 | Auth token wiring + TTLs + throttle | Small/Med | Unblocks Flutter; corrects the false BACKLOG claim first |
| 2 | Password policy | Small | Single provider registration + tests |
| 3 | PII encryption | Small/Med | **Blocked on the raw-SQL consumer audit gate** |
| 4 | Auth-event logging | Small | Listeners + `AuditService` reuse |
| 5 | **Authorization consolidation** | **Large** | ~45 files + ~18 new Policy classes |

**Slice 5 is the dominant scope and risk driver.** It very likely exceeds the 400-line review budget on its own and should get its own chained PR (or sub-chain), kept separate from slices 1–4. Expect the Review Workload Guard to fire at `sdd-tasks` with a chained-PR recommendation.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `routes/api.php` | Modified | Wire 4 orphan auth routes under `/v1`, apply throttles |
| `app/Http/Controllers/Api/Auth/*` | Modified | `LoginController` issues access+refresh pair |
| `config/sanctum.php`, `config/api_auth.php` | Modified | 2h access TTL, 30d refresh TTL |
| `app/Providers/AppServiceProvider.php` | Modified | `Password::defaults()`, `Gate::policy()` bindings, listener registration |
| `app/Models/Prestamo.php`, `app/Models/Persona.php` | Modified | `'encrypted'` casts (post-audit) |
| `database/migrations/` | New | Backfill migration for encrypted columns |
| `app/Policies/` | New/Modified | ~18 new Policies; 11 existing extended |
| `app/Filament/**/Resources/*` | Modified | Inline `hasRole` → Policy calls |
| `app/Listeners/` | New | Auth event listeners |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Non-Eloquent readers of `numero_cuenta_desembolso` / DNI break on encryption | Med | Blocking audit gate before any cast; slice cannot start until it passes |
| Slice 5 regresses Filament permissions across many screens | Med | 1:1 closure→Policy mapping, Policy unit tests per model, chained PR with focused diffs |
| `BACKLOG.md:9` "95/95 passing" is trusted again downstream | High | Re-run `Api/Auth` tests first and correct `BACKLOG.md` in slice 1 |
| Shorter 2h access token breaks existing clients | Low | No production mobile client yet; refresh flow ships in the same slice |
| `uncompromised()` blocks admin user creation offline | Low | HIBP failures fail-open by default in Laravel; document behavior |

## Rollback Plan

- Slices 1, 2, 4, 5: revert the slice PR — all are additive/behavioral, no destructive schema change.
- Slice 3: encryption is the only irreversible-ish step. The backfill migration MUST ship with a working `down()` that decrypts in place, and a verified DB backup must be taken before running it in production.
- Token TTLs and `Password::defaults()` are config/provider-level and revertible independently of code.

## Dependencies

- Human/ops confirmation that production `FRONTEND_URLS` is non-empty.
- DB backup before the slice 3 backfill.
- Outbound network access to HaveIBeenPwned for `uncompromised()`.

## Success Criteria

- [ ] `/v1/auth/refresh`, `/logout-all`, `/forgot-password`, `/reset-password` are routed, throttled, and green in the `Api/Auth` test suite.
- [ ] Login returns an access token expiring in 2h and a refresh token valid 30d.
- [ ] `Password::defaults()` enforces mixedCase + numbers + symbols + uncompromised, covered by tests.
- [ ] Raw-SQL consumer audit documented; `numero_cuenta_desembolso` and `DNI` encrypted at rest with backfill verified.
- [ ] Zero inline `request()->user()->hasRole(...)` authorization checks remain in Filament Resources; every model has a Policy.
- [ ] Login / Failed / Logout / PasswordReset produce `audit_logs` rows.
- [ ] `BACKLOG.md:9` corrected to reflect actual state.
