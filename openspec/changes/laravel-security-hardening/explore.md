# SDD Explore — laravel-security-hardening

Project: financieranuevo (EC-Antigravity, Laravel 12 + Filament 3.3, group/individual microfinance).
Scope trigger: user-supplied 6-area Laravel security checklist (authn/session/tokens, RBAC/ABAC, input validation, CSRF/CORS, secrets/encryption/headers, audit logging).

## Headline finding

The recently merged `fix/sanctum-hardening` branch is **not wired**: the new auth controllers, model, and config exist but are dead code. This directly contradicts `BACKLOG.md:9`, which claims the mobile auth work ("access token corto (2h) + refresh token (30 días) con rotación, logout-all, reset de password, rate limiting general throttle:api 60/min en /v1") is done and verified with 95/95 passing tests, referencing engram `sdd/mobile-auth/apply`. That claim must be re-verified (real test run) before any proposal scopes around it as "already done."

## 1. Authn & session/token management — PARTIAL, currently BROKEN

- Untracked files exist: `RefreshTokenController.php`, `LogoutAllController.php`, `ForgotPasswordController.php`, `ResetPasswordController.php`, `RefreshToken` model, `config/api_auth.php`, a migration, `ApiPasswordResetNotification`.
- **Critical gap**: `routes/api.php` (tracked, unmodified) has zero routes for `/auth/refresh`, `/auth/logout-all`, `/auth/forgot-password`, `/auth/reset-password`. These 4 controllers are orphan code today.
- `LoginController.php:40-45` still returns the OLD `{token, user}` contract — never updated to call `RefreshToken::issueFor()`. Refresh flow cannot bootstrap even if routes existed.
- New tests (`RefreshTokenTest.php`, `LogoutAllTest.php`, `PasswordResetTest.php`) call routes that don't exist and expect response keys `LoginController` never returns — these would fail today.
- `config/sanctum.php:20` `'expiration' => 43200` (30 days) — contradicts BACKLOG's "access token corto (2h)" claim; no per-token `expiresAt` override anywhere.
- Rate limiting (`app/Providers/AppServiceProvider.php:92-105`): `api-auth` (10/min, IP-only, no per-email) wired via `throttle:api-auth` on both login routes (`routes/api.php:18,23`). `password-reset` limiter (3/min, email-or-ip) is defined but **never referenced by any route**. `filament-login` (5/min, email+ip) is wired for the Filament panel. No generic `throttle:api` (60/min) exists on `/v1`, despite BACKLOG's claim.
- `Password::defaults()` used once (`ResetPasswordRequest.php:23`) but never configured in any provider — falls back to bare `min(8)`; no mixedCase/numbers/symbols/uncompromised anywhere.
- No public `/register` endpoint — not a gap (admin-created users only).

## 2. Authorization (RBAC/ABAC) — PARTIAL

- 11 Policies exist in `app/Policies/` (Asesor, Cliente, Egreso, Grupo, Ingreso, ProductoFinanciero, Retanqueo, Role, User, Pago, Prestamo) — relies on Laravel auto-discovery, no explicit `Gate::policy` registration found.
- `AsesorScopeGuard` trait **already exists** (`app/Http/Concerns/AsesorScopeGuard.php`) and is wired into `CuotaController.php` and `PagoPdfController.php` — the IDOR fix from `sdd/core-contable-seguridad/design` (Decision D5) is done, even though that design doc says "next: sdd-tasks". Flag back to that artifact as stale.
- `auditoria/plan-remediacion` task T3.2 (closures → Policies) confirmed still open: `PrestamoResource.php` (34,46,151,159,815,1185-1275), `GrupoResource.php` (37,113,324,363,412,470,485,518-519), `PagoResource.php` (1013,1038,1063 + more) all use inline `request()->user()->hasRole(...)`. ~45 files repo-wide match this pattern.
- Corroborated by `BACKLOG.md:15`: "18/29 modelos sin Policy dedicada", specifically naming `CuotaIndividual`, `CuotasGrupales`, `Mora`, `PrestamoIndividual`, `SeparacionCliente` as unprotected and slated for mobile exposure — real, unaddressed access-control gap.

## 3. Input validation / injection / XSS — IMPLEMENTED, adequate

- No `$request->all()` in `app/`. `{!! !!}` only appears in vendor/framework Blade views.
- All spot-checked `whereRaw`/`selectRaw`/`DB::raw` use parameterized `?` bindings or static aggregates (`app/Rules/UniqueCorreo.php:39`, `AsesorResource.php:168,229,259,307`, `Moras.php:126,145`). No injection risk found.

## 4. CSRF & CORS — CORS partial, CSRF adequate

- `config/cors.php`: `allowed_origins` is env-driven (`FRONTEND_URLS`), not wildcard; `supports_credentials => false` (correct for bearer-token API). Actual `.env`/`.env.example` value for `FRONTEND_URLS` could not be verified from this session (env files blocked from Read/Grep) — needs manual confirmation it's non-empty in production.
- CSRF: default Laravel 12 `ValidateCsrfToken` active for web routes, no override in `bootstrap/app.php`. API is stateless bearer-token — CSRF N/A there by design, not a gap.

## 5. Secrets, encryption, HTTP security headers — PARTIAL

- No hardcoded credentials found in `app/`.
- `SecurityHeadersMiddleware` is implemented and registered (`bootstrap/app.php:37`, prepended globally): CSP, HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy — done, adequate.
- Only `User.password => 'hashed'` (`User.php:62`) is protected. Genuine gap: `Prestamo.numero_cuenta_desembolso` (bank account, `Prestamo.php:107`, no cast) and `Persona.DNI` (`Persona.php:15`, no cast) store financial/PII in plaintext.

## 6. Security audit logging — MISSING (auth events), inconsistent (business events)

- No listener for `Login`/`Failed`/`Logout`/`PasswordReset` anywhere. `app/Listeners/` only has `InvalidateSeparacionCache.php` (unrelated). No `EventServiceProvider`; `AppServiceProvider::boot()` registers only model Observers.
- Distinct from `sdd/trazabilidad-modelo-datos` (business-entity `audit_logs` hash-chain design) — that covers Prestamo/Grupo mutation history, not auth events.
- Refined by `BACKLOG.md:16`: `AuditService`/`audit_logs` infrastructure already exists but is invoked manually and inconsistently — critical mutations on mora/cuota/prestamo_individual/separación are NOT confirmed to call `registrar()`. So business audit logging isn't purely "planned" (trazabilidad-modelo-datos), it's "exists but leaky" — a second, narrower gap alongside the fully-absent auth-event logging.

## Already covered elsewhere — do not duplicate

- `sdd/core-contable-seguridad/design` D5 — `AsesorScopeGuard` IDOR fix — **done**, verified in code.
- `auditoria/plan-remediacion` T3.2 — Filament closures → Policies migration — confirmed still open, large scope; leave to that change (this change should not re-scope it, only reference it).
- `sdd/trazabilidad-modelo-datos` — business-entity audit trail (hash-chain, non-repudiation) — different concern from auth-event logging.

## Genuine new scope for `laravel-security-hardening`

1. Wire the orphaned auth-hardening routes (`/v1/auth/refresh`, `/logout-all`, `/forgot-password`, `/reset-password`); update `LoginController` to issue an access+refresh pair; set an explicit short access-token TTL; re-run the 5 new `Api/Auth` test files to get ground truth before trusting `BACKLOG.md:9`.
2. Apply `throttle:password-reset` once those routes exist; consider IP-based limiting alongside email-based on all auth endpoints.
3. Register project-wide `Password::defaults()` (mixedCase, numbers, symbols, uncompromised).
4. Add `'encrypted'` casts to `Prestamo.numero_cuenta_desembolso` and `Persona.DNI`, with a backfill migration and an audit of any raw-SQL consumers of those columns first.
5. Add auth-event listeners (Login/Failed/Logout/PasswordReset) via a lightweight listener reusing the existing `AuditService`/`audit_logs` infrastructure.
6. Audit whether critical business mutations (mora/cuota/prestamo_individual/separación) actually call `AuditService::registrar()` — closing the "manual, unconfirmed" gap BACKLOG.md already flagged, scoped narrowly (verification + gap-filling, not a redesign — that's `trazabilidad-modelo-datos`'s job).
7. Confirm production `FRONTEND_URLS` is actually set (non-empty) — manual/ops confirmation, not code.

Explicitly OUT of scope (belongs to other in-flight changes): Filament Policies migration (plan-remediacion T3.2), business-entity hash-chain audit trail (trazabilidad-modelo-datos), accounting/retanqueo ledger work (core-contable-seguridad).

## Risks

- Trusting `BACKLOG.md`/`sdd/mobile-auth/apply`'s "done, 95/95 passing" claim without re-running tests would scope the proposal on a false premise.
- Encrypting `numero_cuenta_desembolso`/DNI requires a backfill + audit of raw-SQL consumers first.
- `.env`/`.env.example` values blocked from this session's tools — `FRONTEND_URLS` needs manual/human confirmation.

## Ready for Proposal

Yes. Recommend `sdd-propose` start by re-running the full test suite to get ground truth on the 5 new `Api/Auth` test files before committing to scope size.

---
Session: sdd-explore delegation (agent ae26bf0954dfc964e)
Project: financieranuevo
Artifact store: openspec
Topic mirror (engram): sdd/laravel-security-hardening/explore (id 283)
