---
name: laravel-clean-architecture
description: >
  Pragmatic Clean Architecture and SOLID principles tailored for Laravel.
  Trigger: When user asks about clean architecture, structuring business logic, creating services, actions, or decoupling controllers.
license: Apache-2.0
metadata:
  author: gentleman-programming
  version: "1.0"
---

# Laravel Clean Architecture (Pragmatic Approach)

Applying pure "Clean Architecture" (like Robert C. Martin's) to Laravel often leads to over-engineering and fights against the framework. This skill defines the **Pragmatic Laravel Clean Architecture**, blending SOLID principles with Laravel's strengths (Eloquent, FormRequests).

## When to Use

- Structuring new complex features
- Refactoring fat controllers or fat models
- Separating business logic from the UI (Filament/HTTP)
- Designing services and actions

## Critical Patterns (The "Laravel Way")

### 1. The Controller Boundary (No Business Logic)
Controllers (and Filament Pages/Actions) must ONLY handle HTTP/UI concerns.
- They receive the Request (validated via FormRequest).
- They pass the validated data (preferably as a DTO) to an Action or Service.
- They return the Response.
- **NEVER** write `if/else` business rules or complex database queries inside a Controller.

### 2. Actions (Single Responsibility Principle)
For specific, discrete use cases, use **Actions** (Invokable classes).
- Name them like `AprobarPrestamoAction`, `DesembolsarPrestamoAction`.
- They should have exactly ONE public method: `execute()` or `handle()`.
- They are easy to test in isolation.

### 3. Services (Orchestration)
For grouping related domain operations that share dependencies, use **Services**.
- Example: `PagoService` which handles `registrarPago()`, `revertirPago()`, etc.
- Services should not depend on `Illuminate\Http\Request`. They should receive scalar types, arrays, or DTOs.

### 4. Eloquent Models as Rich Domain Entities
Do not create separate "Domain Entity" classes. Use Eloquent Models, but keep them clean:
- **YES:** Relationships, Mutators/Accessors, Query Scopes, state-check methods (`puedeSerAprobado()`).
- **NO:** Sending emails, calling external APIs, executing complex multi-model transactions.

### 5. Repositories are an Anti-Pattern
In 95% of Laravel apps, the Repository Pattern is redundant because Eloquent *is* an implementation of the Active Record / Repository pattern.
- Instead of `UserRepository->getActive()`, use Query Scopes: `User::active()->get()`.
- If a query is massively complex, extract it to a custom Query Builder class, not a generic Repository interface.

## Code Examples

### ❌ Bad: Fat Controller (Coupled)
```php
public function store(Request $request) {
    $request->validate([...]);
    $prestamo = new Prestamo();
    $prestamo->monto = $request->monto;
    // Business logic inside controller!
    if ($prestamo->monto > 1000) {
        $prestamo->estado = 'requiere_firma';
    }
    $prestamo->save();
    Mail::to($prestamo->cliente)->send(new PrestamoCreado());
    return redirect()->back();
}
```

### ✅ Good: Clean Controller + Action
```php
// Controller
public function store(StorePrestamoRequest $request, CrearPrestamoAction $action) {
    $prestamo = $action->execute($request->validated(), $request->user());
    return redirect()->route('prestamos.show', $prestamo);
}

// Action
class CrearPrestamoAction {
    public function execute(array $data, User $asesor): Prestamo {
        return DB::transaction(function() use ($data, $asesor) {
            $prestamo = Prestamo::create([...$data, 'asesor_id' => $asesor->id]);
            
            if ($prestamo->monto > 1000) {
                $prestamo->transicionarA('requiere_firma'); // Logic in Model
            }
            
            // Side effects should ideally go to Events, but Action is acceptable
            PrestamoCreadoEvent::dispatch($prestamo);
            
            return $prestamo;
        });
    }
}
```

## Commands

To generate an action (if using custom stubs) or simply a plain class:
```bash
php artisan make:class Actions/CrearPrestamoAction
```
