# CHANGELOG

## Unreleased

### Added

- Repository documentation set: product, design, architecture, database, API, auth, business rules, coding standards, testing, security, deployment, changelog, and ADRs.
- Agent instructions in `AGENTS.md`.
- Strict TypeScript config, ESLint flat config, Prettier config, and Tailwind token mirror.

### Changed

- Public report form copy and SEO title aligned with perundungan/pembullyan keywords.
- Email is optional for reporters, while phone remains required.
- Docker start script now runs production migrations before Laravel cache warmup.

### Fixed

- Docker-backed test suite now passes with the latest source.
- Public form exposes grouped class selectors consistently for reporter, related class, victim class, and alleged actor class.
