# ADR-002: Four Internal Roles with Public Tracking Codes

## Status

Accepted

## Date

2026-08-02

## Context

The app has two identity modes: public reporters without login, and internal school users with role-based access. Legacy role names existed but should not retain active access.

## Decision

Use four internal roles: `superadmin`, `kesiswaan`, `sarpras`, and `wali_kelas`. Public reporters use a report number and access code for tracking instead of accounts.

## Alternatives Considered

### Public Reporter Accounts

- Pros: Repeat reporters can see history.
- Cons: Higher friction, more privacy obligations, and account recovery complexity.
- Rejected because reports should be easy to submit quickly.

### Keep Legacy `guru` and `siswa` Roles Active

- Pros: Less migration work.
- Cons: Broader attack surface and unclear permissions.
- Rejected because access needs to be explicit and minimal.

## Consequences

- `wali_kelas` is read-only and scoped to homeroom classes.
- `kesiswaan` and `sarpras` operate only on their report domains.
- Legacy `guru` and `siswa` accounts are archived without deleting historical data.
- Public tracking depends on protecting the 6 digit access code.
