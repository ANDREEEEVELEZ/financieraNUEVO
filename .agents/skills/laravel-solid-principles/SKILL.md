---
name: laravel-solid-principles
description: >
  Core SOLID principles tailored for Laravel.
  Trigger: When user asks to apply SOLID, refactor code, or design interfaces/services.
license: Apache-2.0
metadata:
  author: gentleman-programming
  version: "1.0"
---

# SOLID Principles in Laravel

These are the five mid-level building blocks that make scalable architecture possible. They prevent rigidity, fragility, and immobility.

## 1. Single Responsibility Principle (SRP)
**Concept**: A module should have one, and only one, reason to change — it serves one actor (not "does one thing").
**In Laravel**:
- A Controller only serves the "HTTP Actor" (receives request, returns response).
- If a `PrestamoService` calculates interest, generates PDFs, and sends emails, it's violating SRP because it serves Finance, Operations, and Communications.
- **Solution**: Extract logic to `CalculadoraInteresService`, `NotificadorService`, etc.

## 2. Open-Closed Principle (OCP)
**Concept**: Extend behavior by adding new code, not by modifying existing code; strategy and plugin patterns are the mechanism.
**In Laravel**:
- Avoid massive `switch($tipoDePago)` statements inside your services.
- **Solution**: Use Polymorphism or the Strategy pattern. Inject an interface (`CalculadoraMoraInterface`) into your service, and bind the specific implementation (`MoraFija`, `MoraPorcentual`) via the Laravel Service Container. If a new type of penalty is added, you just create a new class, you don't modify the existing service.

## 3. Liskov Substitution Principle (LSP)
**Concept**: Subtypes must be usable through the base type interface without the client knowing the difference.
**In Laravel**:
- If you have an abstract `ReporteBase` class with a method `exportar()`, TODAS las clases hijas (`ReporteFinanciero`, `ReporteMora`) deben retornar el mismo tipo de dato esperado (ej. un `StreamedResponse`).
- **Violation**: Si `ReporteMora` lanza una excepción `MetodoNoImplementadoException` cuando llamas a `exportar()`, rompiste LSP y la app va a crashear en producción.

## 4. Interface Segregation Principle (ISP)
**Concept**: Clients should not be forced to depend on methods they do not use; fat interfaces create unnecessary coupling.
**In Laravel**:
- Do not create a massive `TransaccionesFinancierasInterface` that forces every class to implement `pagar()`, `revertir()`, `condonar()`, y `retanquear()`.
- **Solution**: Split them. `CanBePaidInterface`, `CanBeRefundedInterface`. A simple adjustement service should only depend on what it uses.

## 5. Dependency Inversion Principle (DIP)
**Concept**: High-level modules should not depend on low-level modules; both should depend on abstractions.
**In Laravel**:
- A Controller (High-level) should never do `$mail = new SmtpMailer()`.
- **Solution**: Inject abstractions. `public function __construct(MailerInterface $mailer)`. Then, in a Service Provider, tell Laravel: "Whenever someone asks for `MailerInterface`, give them `SmtpMailer`." This makes the code instantly mockable and testable.

## Checklist for AI
- [ ] Is the Controller doing more than just routing data? (SRP Violation)
- [ ] Are there multiple `if/else` checks for types that should be polymorphic? (OCP Violation)
- [ ] Are dependencies injected via constructor rather than instantiated with `new`? (DIP Violation)
