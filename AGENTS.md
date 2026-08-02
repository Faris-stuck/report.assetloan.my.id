# Agent Instructions

## Scope

- This repository powers `report.assetloan.my.id`, the LAPORIN reporting app for SMK Taruna Bangsa Bekasi.
- Keep credentials out of git. Use `.env.example` with `[REDACTED]` placeholders only.
- Prefer Indonesian UI copy. Keep wording short, calm, and actionable.

## Stack

| Layer    | Choice                                                 |
| -------- | ------------------------------------------------------ |
| Backend  | Laravel 12 on PHP 8.3                                  |
| Frontend | Blade, Bootstrap 5, Alpine.js, Vite, Tailwind tokens   |
| Database | MariaDB/MySQL in production, SQLite in automated tests |
| Runtime  | Docker image `laporin-app:*` on network `cf-network`   |

## Commands

| Task                | Command                       |
| ------------------- | ----------------------------- |
| PHP tests           | `php artisan test`            |
| Docker-backed tests | `npm run test:docker`         |
| Frontend build      | `npm run build`               |
| Lint JS/TS configs  | `npm run lint`                |
| Format check        | `npm run format:check`        |
| Run migrations      | `php artisan migrate --force` |

## References

| Need          | File                   |
| ------------- | ---------------------- |
| Product scope | `docs/PRODUCT.md`      |
| UI tokens     | `docs/DESIGN.md`       |
| Architecture  | `docs/ARCHITECTURE.md` |
| Database      | `docs/DATABASE.md`     |
| Auth roles    | `docs/AUTH.md`         |
| Deploy        | `docs/DEPLOYMENT.md`   |
| Decisions     | `docs/DECISIONS/`      |

## Conventions

- Do not commit `.env`, `vendor/`, `node_modules/`, `public/build/`, or storage uploads.
- Run tests in Docker if host PHP lacks `pdo_sqlite`.
- Public report forms must stay accessible: labels above inputs, helper text below, server errors rendered inline.
- Role access is allow-list based: `superadmin`, `kesiswaan`, `sarpras`, `wali_kelas`.
- Migrations must be idempotent and safe for production data.

## Commit Attribution

AI commits must include:
`Co-Authored-By: Hermes Agent <noreply@nousresearch.com>`
