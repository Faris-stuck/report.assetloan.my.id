# Business Documentation

## Purpose

Domain ini mendokumentasikan product specifications, business rules, domain logic, user roles and responsibilities, compliance requirements, dan product roadmap. Dokumentasi business memandu product dan business teams tentang requirements dan strategy.

## Quick Navigation

| Dokumen | Purpose | Untuk Siapa | Status |
|---------|---------|-----------|--------|
| [product-specifications.md](./product-specifications.md) | Complete product specifications dan requirements | Product team, developers | Stable |
| [business-rules.md](./business-rules.md) | Business rules dan domain logic | Business analysts, developers | Stable |
| [role-workflow-specification.md](./role-workflow-specification.md) | User roles, responsibilities, workflows | Business team, QA | Stable |
| [compliance-requirements.md](./compliance-requirements.md) | Compliance, regulatory, legal requirements | Compliance team, DevOps | Stable |

## Folder Contents

### Primary Documents
- **product-specifications.md** - Complete product specifications, features, requirements
- **business-rules.md** - Business rules, domain logic, calculations, validations
- **role-workflow-specification.md** - User roles, permissions, workflows per role
- **compliance-requirements.md** - Compliance requirements, regulatory guidelines, audit trails

### Reference & Quick Lookups
- Semua READMEs dalam folder ini adalah navigation files

## Folder Organization Rules

### When to Add New Files

Tambahkan file baru ketika:
- Business aspect baru memerlukan dokumentasi independen (e.g., `regulatory-guidelines.md`, `sla-requirements.md`)
- Expected size melebihi 300 lines di existing document
- Topic adalah distinct dan independent dari existing files

Extend existing file ketika:
- Content berhubungan dengan existing business aspect
- Section bisa logically ditambahkan ke existing document
- Updates hanya ke existing document yang dibutuhkan

### File Naming Convention

**Pattern**: `PURPOSE[-VARIANT].md`

**Rules**:
- Lowercase letters only
- Gunakan hyphens untuk separate words

**Examples**:
- `product-specifications.md`
- `business-rules.md`
- `role-workflow-specification.md`
- `compliance-requirements.md`
- `sla-requirements.md`
- `regulatory-guidelines.md`

### Folder Growth

**Direct file limit**: 6 files maximum

**Sub-folder naming pattern**: `business-[category]/`

## Related Domains

- **[api/](../api/)** - API specifications reference business requirements
- **[auth/](../auth/)** - Authorization rules based on business roles
- **[ui/](../ui/)** - UI/UX design reflects business requirements
- **[database/](../database/)** - Database schema implements business domain logic
- **[deployment/](../deployment/)** - Compliance requirements inform deployment setup

## Getting Started

### Untuk Product Team

Mulai dengan:
1. Baca [product-specifications.md](./product-specifications.md) untuk feature overview
2. Tinjau [business-rules.md](./business-rules.md) untuk domain logic
3. Review [role-workflow-specification.md](./role-workflow-specification.md) untuk user roles
4. Ikuti [compliance-requirements.md](./compliance-requirements.md) untuk regulatory requirements

### Untuk Business Analysts

Mulai dengan:
1. Baca [business-rules.md](./business-rules.md) untuk domain logic
2. Tinjau [product-specifications.md](./product-specifications.md) untuk feature definitions
3. Review [role-workflow-specification.md](./role-workflow-specification.md) untuk workflows
4. Konsultasi [compliance-requirements.md](./compliance-requirements.md) untuk compliance rules

### Untuk Developers

Mulai dengan:
1. Baca [product-specifications.md](./product-specifications.md) untuk feature requirements
2. Tinjau [business-rules.md](./business-rules.md) untuk business logic
3. Review [role-workflow-specification.md](./role-workflow-specification.md) untuk user roles
4. Ikuti [compliance-requirements.md](./compliance-requirements.md) saat implement features

## Search Tips

| Pertanyaan | Jawaban |
|-----------|---------|
| Apa product specification? | [product-specifications.md](./product-specifications.md) |
| Apa business rule? | [business-rules.md](./business-rules.md) |
| Apa user role dan responsibility? | [role-workflow-specification.md](./role-workflow-specification.md) |
| Apa workflow user? | [role-workflow-specification.md](./role-workflow-specification.md) |
| Apa compliance requirement? | [compliance-requirements.md](./compliance-requirements.md) |
| Apa regulatory guideline? | [compliance-requirements.md](./compliance-requirements.md) |

## See Also

- [Root Documentation Hub](../README.md) - Overview semua domains
- [../api/endpoints.md](../api/endpoints.md) - API specifications dari business requirements
- [../auth/authorization-rbac.md](../auth/authorization-rbac.md) - RBAC dari business roles
- [../ui/design-system.md](../ui/design-system.md) - UI design reflects business requirements

---

*Business domain dipertahankan per AGENTS.md conventions. Lihat juga: design.md, requirements.md*

