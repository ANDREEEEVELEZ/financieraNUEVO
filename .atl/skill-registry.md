# Skill Registry

**Delegator use only.** Any agent that launches sub-agents reads this registry to resolve compact rules, then injects them directly into sub-agent prompts. Sub-agents do NOT read this registry or individual SKILL.md files.

See `_shared/skill-resolver.md` for the full resolution protocol.

## User Skills

| Trigger | Skill | Path |
|---------|-------|------|
| Laravel architecture, Eloquent, database, API development, PHP patterns | laravel-best-practices | `.agents/skills/laravel-best-practices/SKILL.md` |
| Testing, Pest, TDD, assertions, coverage, spec, debugging | pest-testing | `.agents/skills/pest-testing/SKILL.md` |
| Authentication, authorization, validation, CSRF, mass assignment, file uploads, rate limiting, secrets, deployment | laravel-security | `.agents/skills/laravel-security/SKILL.md` |

## Compact Rules

Pre-digested rules per skill. Delegators copy matching blocks into sub-agent prompts as `## Project Standards (auto-resolved)`.

### laravel-best-practices
- Service classes extract business logic from controllers and models
- Action classes for single-purpose domain operations
- Eager-load relationships to prevent N+1 queries (use `->with()` at query site)
- Use Form Requests for validation and authorization
- API Resources transform model data for responses
- Query scopes encapsulate reusable query logic in models
- Soft deletes for safe record recovery; use `->onlyTrashed()` to query deleted records
- Chunking processes large datasets efficiently without memory issues
- Repository pattern for complex query logic; DTOs for data transfer
- Event-driven patterns decouple subsystems with events and listeners

### pest-testing
- All tests use Pest framework — never raw PHPUnit
- Test files in `tests/Feature/` and `tests/Unit/`
- Use specific assertions (`assertSuccessful()`) not generic status codes (`assertStatus(200)`)
- Mock external services; use `Queue::fake()` for jobs, `Notification::fake()` for notifications
- Use datasets for repetitive test parameters (validation rules, email formats, etc.)
- Browser tests in `tests/Browser/` use `visit()`, `click()`, `fill()`, `assertSee()`
- Run with `php artisan test --compact` and validate >85% coverage before committing
- Never delete tests without explicit approval — they are application code

### laravel-security
- Always use Laravel Sanctum/Passport for API auth; never roll your own
- Sessions: set `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=strict`, regenerate on login
- Form Requests validate AND authorize; use `$this->authorize()` to check permissions
- Mass assignment: use `$fillable` or `$guarded` on models; never `Model::unguard()`
- Passwords: always `Hash::make()`, never plaintext; use Laravel's password broker for reset flows
- File uploads: validate MIME type, size, extension; store outside public path when possible
- Rate limiting: apply `throttle` middleware on login, password reset, OTP endpoints
- Signed URLs for temporary tamper-proof links (`URL::temporarySignedRoute()`)
- Log redaction: never log passwords, tokens, card data — use `[REDACTED]` for sensitive fields
- Environment variables for secrets; rotate keys immediately on compromise

## Project Conventions

No project-level convention files found. Refer to user global conventions at `~/.claude/CLAUDE.md`.

---

**Registry Generated**: 2026-05-04
**Project**: EC-Antigravity
**Skills Found**: 3 (laravel-best-practices, pest-testing, laravel-security)
