# Testing Documentation

## Purpose

Domain ini mendokumentasikan testing frameworks, guidelines, procedures, test coverage targets, accessibility testing, dan quality assurance checkpoints. Dokumentasi testing menyediakan comprehensive guidance tentang unit, integration, e2e testing, dan validation procedures.

## Quick Navigation

| Dokumen | Purpose | Untuk Siapa | Status |
|---------|---------|-----------|--------|
| [testing-framework.md](./testing-framework.md) | Testing framework setup dan procedures | All developers, QA | Stable |
| [accessibility-testing.md](./accessibility-testing.md) | Accessibility testing guidelines dan WCAG validation | QA testers, developers | Stable |
| [implementation-test-checklist.md](./implementation-test-checklist.md) | Comprehensive test checklist untuk feature implementation | QA, developers | Stable |
| [consistency-checklist.md](./consistency-checklist.md) | Consistency validation checklist across features | QA, developers | Stable |

## Folder Contents

### Primary Documents
- **testing-framework.md** - Testing framework setup, unit tests, integration tests, e2e tests
- **accessibility-testing.md** - Accessibility testing procedures, WCAG compliance validation
- **implementation-test-checklist.md** - Comprehensive checklist untuk feature testing
- **consistency-checklist.md** - Consistency checklist untuk UI/UX/API/Database

### Reference & Quick Lookups
- Semua READMEs dalam folder ini adalah navigation files

## Folder Organization Rules

### When to Add New Files

Tambahkan file baru ketika:
- Testing type baru memerlukan dokumentasi independen (e.g., `performance-testing.md`, `security-testing.md`)
- Expected size melebihi 300 lines di existing document
- Topic adalah distinct dan independent dari existing files

Extend existing file ketika:
- Content berhubungan dengan existing testing type
- Section bisa logically ditambahkan ke existing document
- Updates hanya ke existing document yang dibutuhkan

### File Naming Convention

**Pattern**: `PURPOSE[-VARIANT].md` atau `TEST-TYPE-SCOPE.md`

**Rules**:
- Lowercase letters only
- Gunakan hyphens untuk separate words

**Examples**:
- `testing-framework.md`
- `accessibility-testing.md`
- `performance-testing.md`
- `security-testing.md`
- `mobile-device-testing.md`

### Folder Growth

**Direct file limit**: 10 files maximum (testing adalah cross-domain)

**Sub-folder naming pattern**: `testing-[category]/`

## Related Domains

- **[api/](../api/)** - API testing procedures dan test cases
- **[ui/](../ui/)** - UI/Component testing procedures
- **[database/](../database/)** - Database testing dan migration validation
- **[deployment/](../deployment/)** - Deployment testing dan smoke tests
- **[development/](../development/)** - Development testing setup

## Getting Started

### Untuk Developers

Mulai dengan:
1. Baca [testing-framework.md](./testing-framework.md) untuk setup dan procedures
2. Tinjau domain-specific testing (API, UI, Database, etc.)
3. Gunakan [implementation-test-checklist.md](./implementation-test-checklist.md) saat develop feature
4. Ikuti [accessibility-testing.md](./accessibility-testing.md) untuk a11y validation

### Untuk QA/Testers

Mulai dengan:
1. Baca [testing-framework.md](./testing-framework.md) untuk framework overview
2. Gunakan [implementation-test-checklist.md](./implementation-test-checklist.md) untuk test planning
3. Ikuti [accessibility-testing.md](./accessibility-testing.md) untuk a11y testing
4. Tinjau [consistency-checklist.md](./consistency-checklist.md) untuk consistency validation

### Untuk QA Lead

Mulai dengan:
1. Tinjau [testing-framework.md](./testing-framework.md) untuk coverage targets
2. Baca [implementation-test-checklist.md](./implementation-test-checklist.md) untuk test strategy
3. Implementasikan [accessibility-testing.md](./accessibility-testing.md) guidelines
4. Monitor consistency per [consistency-checklist.md](./consistency-checklist.md)

## Search Tips

| Pertanyaan | Jawaban |
|-----------|---------|
| Bagaimana setup test framework? | [testing-framework.md](./testing-framework.md) |
| Apa test coverage target? | [testing-framework.md](./testing-framework.md) |
| Bagaimana test accessibility? | [accessibility-testing.md](./accessibility-testing.md) |
| Apa WCAG requirements? | [accessibility-testing.md](./accessibility-testing.md) |
| Apa test checklist? | [implementation-test-checklist.md](./implementation-test-checklist.md) |
| Bagaimana validate consistency? | [consistency-checklist.md](./consistency-checklist.md) |

## See Also

- [Root Documentation Hub](../README.md) - Overview semua domains
- [../api/endpoints.md](../api/endpoints.md) - API testing context
- [../ui/accessibility-standards.md](../ui/accessibility-standards.md) - Accessibility standards
- [../development/coding-standards.md](../development/coding-standards.md) - Development standards

---

*Testing domain dipertahankan per AGENTS.md conventions. Lihat juga: design.md, requirements.md*

