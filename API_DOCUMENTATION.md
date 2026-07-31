# Especificación Técnica de API v1 — Financiera / Emprende Conmigo

Esta documentación define las rutas, formatos de petición y esquemas de respuesta de la API v1 para integraciones frontend (React, Next.js, Vue, Mobile, etc.).

---

## 1. Configuración General

- **Base URL:** `https://<tu-dominio-railway>.up.railway.app/api/v1`
- **Headers Obligatorios:**
  ```http
  Accept: application/json
  Content-Type: application/json
  Authorization: Bearer <TOKEN_SANCTUM>  (para rutas protegidas)
  ```

---

## 2. Formato de Respuesta Estándar (`ApiResponse`)

Todas las respuestas de la API siguen un envoltorio JSON estandarizado:

### Éxito (Status 200 / 201)
```json
{
  "success": true,
  "data": { ... },
  "message": "Operación realizada con éxito.",
  "errors": null
}
```

### Respuesta Paginada (Status 200)
```json
{
  "success": true,
  "data": [ ... ],
  "message": null,
  "errors": null,
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 75
  }
}
```

### Error de Validación (Status 422) o Autenticación (Status 401 / 403)
```json
{
  "success": false,
  "data": null,
  "message": "Validation failed.",
  "errors": {
    "email": ["El campo email es obligatorio."]
  }
}
```

---

## 3. Endpoints de Autenticación

### `POST /auth/login` (Público)
Inicia sesión y genera un Bearer Token.

- **Body (JSON):**
  ```json
  {
    "email": "asesor@financiera.com",
    "password": "password123",
    "device_name": "Web Client / Chrome"
  }
  ```
- **Respuesta Éxito (200):**
  ```json
  {
    "success": true,
    "data": {
      "token": "1|abc123xyz...",
      "user": {
        "id": 5,
        "name": "Juan Pérez",
        "email": "asesor@financiera.com",
        "roles": ["Asesor"]
      }
    },
    "message": null,
    "errors": null
  }
  ```

### `POST /auth/logout` (Protegido)
Revoca el token actual del usuario.

- **Headers:** `Authorization: Bearer <TOKEN>`
- **Respuesta Éxito (200):**
  ```json
  {
    "success": true,
    "data": null,
    "message": "Token revocado correctamente.",
    "errors": null
  }
  ```

---

## 4. Endpoints de Recursos (Protegidos)

### 📊 Dashboard
- **`GET /dashboard`**
  - **Headers:** `Authorization: Bearer <TOKEN>`
  - Retorna métricas generales y KPIs según el rol del usuario autenticado (`rol_activo`).

---

### 👥 Grupos
- **`GET /grupos`**
  - Lista paginada de grupos visibles según el rol del usuario.
- **`GET /grupos/{id}`**
  - Detalle del grupo e integrantes, incluyendo `mora_monto` y `cuotas_mora_count`.

---

### 👤 Clientes
- **`GET /clientes`**
  - Lista paginada de clientes. (Asesores ven solo su cartera; coordinadores/administradores ven todos).
- **`GET /clientes/{id}`**
  - Detalle del cliente, scoring vigente, préstamos recientes y grupos.

---

### 💰 Préstamos

- **`GET /prestamos`**
  - Lista paginada de préstamos del usuario.
- **`GET /prestamos/{id}`**
  - Detalle completo del préstamo con cuotas grupales y montos individuales.
- **`POST /prestamos`**
  - Crea una solicitud de préstamo grupal.
