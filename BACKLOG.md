# Backlog — Iniciativa App Móvil (iOS/Android)

Última actualización: 2026-07-30. Este documento existe para no perder de vista nada entre sesiones/agentes mientras se prepara el backend y arranca el frontend Flutter.

---

## 1. Backend — Hecho

- [ ] ~~**Auth para mobile/web**: access token corto (2h) + refresh token (30 días) con rotación, `logout-all`, reset de password vía código, rate limiting general (`throttle:api`, 60/min) en `/v1`. Verificado: 95/95 tests.~~
  **CORREGIDO 2026-07-30**: esta entrada era falsa. Los controladores (`RefreshTokenController`, `LogoutAllController`, `ForgotPasswordController`, `ResetPasswordController`) y el modelo `RefreshToken` existen pero **no están wireados** — cero rutas en `routes/api.php`, `LoginController.php:40-45` nunca actualizado para emitir el par access+refresh. Los 5 tests nuevos (`RefreshTokenTest`, `LogoutAllTest`, `PasswordResetTest`) fallarían hoy si corrieran. No hay ningún `throttle:api` genérico de 60/min en `/v1`. Auditado con evidencia file:line en `openspec/changes/laravel-security-hardening/explore.md`. **En reparación vía SDD `laravel-security-hardening`** (13 PRs encadenados, `feature-branch-chain` → mergea a `Antigravity`). No marcar como hecho hasta que ese PR1 mergee y el test suite corra verde de verdad.

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
