# ADR-001: Use Laravel Monolith for LAPORIN

## Status

Accepted

## Date

2026-08-02

## Context

LAPORIN needs a server-rendered school reporting workflow, role dashboards, file upload handling, validation, audit trail, and straightforward deployment on an existing VPS.

## Decision

Use Laravel 12 with PHP 8.3 as a monolith. Use Blade, Bootstrap 5, Alpine.js, Vite, and a small custom CSS design layer.

## Alternatives Considered

### Next.js Separate Frontend

- Pros: Strong TypeScript and component ecosystem.
- Cons: Adds API boundary, auth duplication, and more deployment moving parts.
- Rejected because current scope benefits from a simpler monolith.

### Pure Static Frontend with API

- Pros: Fast public pages.
- Cons: File uploads, tracking, auth, and role dashboards still need backend complexity.
- Rejected because the application is mostly workflow and data handling.

## Consequences

- One deploy artifact handles public form, auth, admin, and dashboard.
- Laravel policies and middleware centralize authorization.
- UI remains easy to modify with Blade and CSS.
- TypeScript config exists for stricter frontend tooling, not as a full frontend rewrite.
