# Auth Documentation

## Purpose

Domain ini mendokumentasikan authentication mechanisms (session, JWT, OAuth), authorization rules (RBAC), enterprise security requirements, dan security best practices. Dokumentasi auth memandu implementation dari secure authentication dan role-based access control.

## Quick Navigation

| Dokumen | Purpose | Untuk Siapa | Status |
|---------|---------|-----------|--------|
| [authentication.md](./authentication.md) | Mekanisme authentication (session/JWT/OAuth) | Backend developers, security reviewers | Stable |
| [authorization-rbac.md](./authorization-rbac.md) | RBAC rules, role definitions, permission matrices | Backend developers, security team | Stable |
| [security-practices.md](./security-practices.md) | Security best practices dan guidelines | All developers, security team | Stable |
| [enterprise-security.md](./enterprise-security.md) | Enterprise security requirements dan compliance | Security team, DevOps | Stable |

## Folder Contents

### Primary Documents
- **authentication.md** - Mekanisme authentication dan implementation procedures
- **authorization-rbac.md** - RBAC rules, role definitions, permission matrix
- **security-practices.md** - Security best practices, password policies, etc.
- **enterprise-security.md** - Enterprise security requirements, compliance, MFA procedures

### Reference & Quick Lookups
- Semua READMEs dalam folder ini adalah navigation files

## Folder Organization Rules

### When to Add New Files

Tambahkan file baru ketika:
- Mechanism auth baru memerlukan dokumentasi independen (e.g., `oauth-setup.md`)
- Expected size melebihi 300 lines di existing document
- Topic adalah distinct dan independent dari existing files

Extend existing file ketika:
- Content berhubungan dengan existing mechanism
- Section bisa logically ditambahkan ke existing document
- Updates hanya ke existing document yang dibutuhkan

### File Naming Convention

**Pattern**: `PURPOSE[-VARIANT].md`

**Rules**:
- Lowercase letters only
- Gunakan hyphens untuk separate words
- Deskriptif dan jelas

**Examples**:
- `authentication.md`
- `authorization-rbac.md`
- `security-practices.md`
- `enterprise-security.md`
- `oauth-setup.md`
- `mfa-procedures.md`

### Folder Growth

**Direct file limit**: 6 files maximum

**Sub-folder naming pattern**: `auth-[category]/`

**Examples if subfolder needed**:
```
docs/auth/
├── README.md
├── authentication.md
├── authorization-rbac.md
├── security-practices.md
├── mechanisms/
│   ├── README.md
│   ├── session-auth.md
│   ├── jwt-auth.md
│   └── oauth-setup.md
└── enterprise-security.md
```

## Related Domains

- **[api/](../api/)** - API authentication requirements reference di auth domain
- **[database/](../database/)** - Database security untuk sensitive data
- **[deployment/](../deployment/)** - Production security setup dan monitoring
- **[business/](../business/)** - Business rules yang inform security policies
- **[testing/](../testing/)** - Security testing procedures

## Getting Started

### Untuk Backend Developers

Mulai dengan:
1. Baca [authentication.md](./authentication.md) untuk auth mechanisms
2. Ikuti [authorization-rbac.md](./authorization-rbac.md) untuk role setup
3. Tinjau [security-practices.md](./security-practices.md) untuk best practices
4. Implementasikan per [API requirements](../api/authentication-requirements.md)

### Untuk Security Team

Mulai dengan:
1. Tinjau [security-practices.md](./security-practices.md) untuk guidelines
2. Baca [enterprise-security.md](./enterprise-security.md) untuk compliance
3. Review [authorization-rbac.md](./authorization-rbac.md) untuk permissions
4. Audit per [authentication.md](./authentication.md) procedures

### Untuk QA/Security Testers

Mulai dengan:
1. Baca [authentication.md](./authentication.md) untuk test scenarios
2. Tinjau [authorization-rbac.md](./authorization-rbac.md) untuk role testing
3. Gunakan [security-practices.md](./security-practices.md) untuk security test cases
4. Referensi [enterprise-security.md](./enterprise-security.md) untuk compliance tests

## Search Tips

| Pertanyaan | Jawaban |
|-----------|---------|
| Bagaimana session authentication bekerja? | [authentication.md](./authentication.md) |
| Apa role dan permission saya? | [authorization-rbac.md](./authorization-rbac.md) |
| Bagaimana setup MFA? | [enterprise-security.md](./enterprise-security.md) |
| Apa password policy? | [security-practices.md](./security-practices.md) |
| Bagaimana implement RBAC? | [authorization-rbac.md](./authorization-rbac.md) |
| Apa security best practices? | [security-practices.md](./security-practices.md) |
| Bagaimana compliance requirements? | [enterprise-security.md](./enterprise-security.md) |

## See Also

- [Root Documentation Hub](../README.md) - Overview semua domains
- [../api/authentication-requirements.md](../api/authentication-requirements.md) - API authentication requirements
- [../database/schema-overview.md](../database/schema-overview.md) - Database security context
- [../deployment/deployment-pipeline.md](../deployment/deployment-pipeline.md) - Production security setup

---

*Auth domain dipertahankan per AGENTS.md conventions. Lihat juga: design.md, requirements.md*

