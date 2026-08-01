# Backlog — Iniciativa App Móvil (iOS/Android)

Última actualización: 2026-07-30. Este documento existe para no perder de vista nada entre sesiones/agentes mientras se prepara el backend y arranca el frontend Flutter.

---

## 1. Backend — Hecho

- [x] **Auth para mobile/web**: access token corto (2h) + refresh token (30 días) con rotación, `logout-all`, reset de password vía código. `throttle:password-reset` (3/min) aplicado a `forgot-password`/`reset-password`; `throttle:api-auth` (10/min) en `login`/`refresh`.
  **ACTUALIZADO 2026-07-30 (PR1 de `laravel-security-hardening`, slice 1)**: la entrada anterior ("95/95 tests") era falsa — quedó corregida el mismo día y ahora está resuelta de verdad. `LoginController` fue reescrito para delegar en `App\Domain\Auth\IssueTokenPair` (detrás de `App\Contracts\TokenIssuerInterface`), que también usa `RefreshTokenController` — ambas rutas devuelven el mismo contrato `{access_token, refresh_token, token_type, expires_in, user}`. Las 4 rutas (`/v1/auth/refresh`, `/v1/auth/logout-all`, `/v1/auth/forgot-password`, `/v1/auth/reset-password`) quedaron wireadas en `routes/api.php`. `config/sanctum.php`'s `expiration` es ahora `null` (per-token TTL vía `config('api_auth.access_token_ttl_minutes')`), evitando el bug de `expires_in = 0`. **Conteo real observado**: `php artisan test tests/Feature/Api/Auth --compact` → **29/29 passing, 0 failures** (LoginTest, LogoutTest, LogoutAllTest, PasswordResetTest, RefreshTokenTest). No incluye rate-limiting genérico de 60/min en todo `/v1` (no formaba parte del alcance de esta slice). Slices 2-6 de `laravel-security-hardening` (password policy, PII encryption, autorización, audit logging) siguen pendientes vía PR2-PR13.

## 2. Backend — Pendiente antes de exponer más superficie a mobile

- [ ] **CRÍTICO — Bug de cálculo de mora**: hoy el recálculo de mora solo ocurre cuando un asesor abre la página de Filament `Moras.php` (hace *writes* en un GET, sin lock). Mover a un Job programado (mismo patrón que `ActualizarMetricasDiariasJob`). Bloqueante para exponer `/mora` a mobile — sin esto, la app mostraría datos desactualizados.
- [ ] **Wireado de rutas Mora/Retanqueo**: `MoraResource` y `RetanqueoResource` ya existen pero tienen **cero rutas** en `routes/api.php`. Definir y exponer bajo `/v1` una vez resuelto el punto anterior.
- [ ] **Cobertura de Policies**: corregido a 20/31 modelos sin Policy dedicada (cifra refinada por el design de `laravel-security-hardening`; incluye `CuotaIndividual`, `CuotasGrupales`, `Mora`, `PrestamoIndividual`, `SeparacionCliente`). **En reparación vía SDD `laravel-security-hardening`, slice 5b** (PR6-8 de la cadena) — no crear Policies nuevas fuera de ese cambio para evitar duplicar trabajo. También migra las 126 (no ~45) verificaciones inline `hasRole()` en Filament Resources hacia esas Policies (slices 5c-5g).
- [ ] **Auditoría (bitácora)**: la infraestructura ya existe (`AuditService`/`audit_logs`) pero es de invocación manual. Verificar que las mutaciones críticas de mora/cuota/prestamo_individual/separación efectivamente llamen a `registrar()` — no asumir que están cubiertas. **Login/Failed/Logout/PasswordReset auth events se cubren en `laravel-security-hardening` slice 6** (gap distinto y separado del audit trail de negocio de `sdd/trazabilidad-modelo-datos`).
- [ ] **Deprecar rutas legacy**: el grupo de rutas sin prefijo en `routes/api.php` está marcado "one release cycle only". Definir fecha/condición de remoción real, no dejarlo indefinido.

## 3. Backend — Próxima feature grande: Documentos + PDFs

- [ ] Tabla `documentos` genérica (polimórfica: Prestamo/Cliente/Grupo), con `tipo_documento` (enum real, no string mágico), versionado (nunca sobreescribir, nueva fila por versión), `Storage::disk` configurable (local ahora, S3-ready).
- [ ] Plantillas Blade en `resources/views/pdf/templates/{tipo}.blade.php` con layout compartido de membrete/pie de página de la empresa. Reusar `dompdf` (ya instalado, patrón probado en `PagoPdfController`).
- [ ] Generación síncrona para documento individual; solo pasar a cola si después se agrega generación masiva.
- [ ] Cada alta/baja de documento debe pasar por `AuditServiceInterface::registrar()`.
- [ ] Endpoints `/v1/documentos` (CRUD + descarga) una vez el modelo esté listo — mobile los va a necesitar para mostrar/descargar pagarés y contratos.

## 4. Mobile — Flutter (nueva app)

- [ ] Scaffold del proyecto Flutter (iOS + Android).
- [ ] Capa de red: cliente HTTP + interceptor que maneja el refresh automático de token (detectar 401 por token expirado → llamar `/v1/auth/refresh` → reintentar request original). Este es el punto de integración más delicado del nuevo contrato de auth.
- [ ] Pantallas de auth: login, "olvidé mi password" (pide código por email), ingresar código + nueva password, logout, "cerrar todas las sesiones".
- [ ] Navegación/shell por rol: Asesor / Jefe de Operaciones / Jefe de Créditos / Super Admin (los 4 roles ya existen en `filament-shield`/Spatie Permission).
- [ ] Pantallas para entidades YA disponibles en `/v1`: Grupos, Clientes, Prestamos (listar/ver/crear/aprobar/rechazar/firmar/desembolsar), Cuotas, Pagos (listar/crear/aprobar/revertir).
- [ ] **Esperar backend** antes de construir: pantallas de Mora, Retanqueo y Documentos — el contrato de API para esas tres todavía no existe o tiene el bug crítico pendiente (sección 2). Construir contra un contrato que no existe implica rehacer pantallas después.
- [ ] Definir identidad visual mobile: reusar tokens de marca de `claude_design_instructions.md` (paleta borgoña, tipografía Poppins) pero diseñar patrones de UI **nativos de mobile** — ese documento describe layout de Filament (grids, slide-overs, breadcrumbs), que no aplica a una app de teléfono. Falta un diseño de pantallas mobile propiamente dicho (navegación, jerarquía táctil, listas de cartera en pantalla chica).

## 5. Decisiones ya tomadas (no reabrir sin motivo)

- Un solo backend/BD para web + mobile — la app móvil consume la misma API Laravel, nunca se duplica el backend.
- Stack mobile: **Flutter** (no Kotlin Multiplatform ni nativo separado) — un solo codebase para iOS/Android.
- Auth de la API: **solo Sanctum** (bearer tokens). Descartado JWT — no hay un segundo frontend (SPA con cookies) que lo justifique; Sanctum con access+refresh ya cubre lo que JWT resolvería.
- Estrategia de sesión: **access + refresh token pair**, no sliding expiration ni token fijo de 30 días.
- Mobile consume **solo `/v1`**, nunca las rutas legacy.
- Storage de tokens en el device: **`flutter_secure_storage`** (Keychain/Keystore), nunca `shared_preferences`.
- State management: **Riverpod** (no Bloc ni Provider).
- Offline: **solo modo consulta** (caché de última respuesta exitosa, sin escrituras offline ni cola de sincronización). Toda pantalla que sirva datos cacheados debe indicar explícitamente que no son datos en vivo — ninguna acción de escritura (pagos, aprobaciones) se habilita sin conexión.

## 6. Mobile — Pendiente de implementación (detalle técnico)

- [ ] Interceptor HTTP de refresh automático (401 por token expirado → `/v1/auth/refresh` → reintento transparente).
- [ ] Capa de caché offline (Hive) en `data/repositories`, con timestamp de "última sincronización" visible en UI.
- [ ] Definir umbral de staleness para datos de mora específicamente (más corto que el resto, por el bug de recálculo irregular en backend — ver sección 2).
