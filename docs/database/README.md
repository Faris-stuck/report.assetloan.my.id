# Database Documentation

## Purpose

Domain ini mendokumentasikan database schema design, entity relationships, migration procedures, query optimization strategies, backup/disaster recovery, dan data consistency patterns. Dokumentasi database memandu developers tentang schema changes, query performance, dan operational procedures.

## Quick Navigation

| Dokumen | Purpose | Untuk Siapa | Status |
|---------|---------|-----------|--------|
| [schema-overview.md](./schema-overview.md) | Database schema design dengan entity relationships | Backend developers, DBAs | Stable |
| [migration-procedures.md](./migration-procedures.md) | Safe migration procedures dan best practices | Backend developers, DBAs | Stable |
| [query-optimization.md](./query-optimization.md) | Query optimization strategies dan indexing | Backend developers, performance engineers | Stable |
| [backup-recovery.md](./backup-recovery.md) | Backup strategies dan disaster recovery procedures | DBAs, DevOps | Stable |

## Folder Contents

### Primary Documents
- **schema-overview.md** - Database schema design dengan ERD dan entity relationships
- **migration-procedures.md** - Safe migration procedures, idempotency, testing
- **query-optimization.md** - Query optimization, indexing strategies, explain plans
- **backup-recovery.md** - Backup strategies, retention policies, recovery procedures

### Reference & Quick Lookups
- Semua READMEs dalam folder ini adalah navigation files

## Folder Organization Rules

### When to Add New Files

Tambahkan file baru ketika:
- Aspect database baru memerlukan dokumentasi independen (e.g., `data-validation.md`, `transaction-patterns.md`)
- Expected size melebihi 300 lines di existing document
- Topic adalah distinct dan independent dari existing files

Extend existing file ketika:
- Content berhubungan dengan existing topic
- Section bisa logically ditambahkan ke existing document
- Updates hanya ke existing document yang dibutuhkan

### File Naming Convention

**Pattern**: `PURPOSE[-VARIANT].md`

**Rules**:
- Lowercase letters only
- Gunakan hyphens untuk separate words
- Deskriptif dan jelas

**Examples**:
- `schema-overview.md`
- `migration-procedures.md`
- `query-optimization.md`
- `backup-recovery.md`
- `transaction-patterns.md`
- `data-validation.md`

### Folder Growth

**Direct file limit**: 6 files maximum

**Sub-folder naming pattern**: `database-[category]/`

**Examples if subfolder needed**:
```
docs/database/
├── README.md
├── schema-overview.md
├── migration-procedures.md
├── query-optimization.md
├── migrations/
│   ├── README.md
│   ├── migration-001.md
│   ├── migration-002.md
│   └── [more migration docs...]
└── backup-recovery.md
```

## Related Domains

- **[api/](../api/)** - API request/response schemas relasi dengan database schema
- **[deployment/](../deployment/)** - Production database setup dan monitoring
- **[testing/](../testing/)** - Database testing procedures
- **[performance/](../performance/)** - Database performance monitoring dan optimization
- **[business/](../business/)** - Business rules yang inform data structure

## Getting Started

### Untuk Backend Developers

Mulai dengan:
1. Baca [schema-overview.md](./schema-overview.md) untuk entity relationships
2. Ikuti [migration-procedures.md](./migration-procedures.md) untuk schema changes
3. Tinjau [query-optimization.md](./query-optimization.md) untuk efficient queries
4. Referensi [backup-recovery.md](./backup-recovery.md) untuk data safety

### Untuk DBAs

Mulai dengan:
1. Tinjau [schema-overview.md](./schema-overview.md) untuk full schema overview
2. Baca [migration-procedures.md](./migration-procedures.md) untuk change management
3. Implementasikan [backup-recovery.md](./backup-recovery.md) untuk disaster recovery
4. Monitor per [query-optimization.md](./query-optimization.md) guidelines

### Untuk Performance Engineers

Mulai dengan:
1. Baca [query-optimization.md](./query-optimization.md) untuk optimization strategies
2. Gunakan [schema-overview.md](./schema-overview.md) untuk schema context
3. Implementasikan per [backup-recovery.md](./backup-recovery.md) backup strategies
4. Referensi [performance/performance-targets.md](../performance/performance-targets.md) untuk targets

## Search Tips

| Pertanyaan | Jawaban |
|-----------|---------|
| Apa struktur database? | [schema-overview.md](./schema-overview.md) |
| Bagaimana entity relate? | [schema-overview.md](./schema-overview.md) |
| Bagaimana migration aman? | [migration-procedures.md](./migration-procedures.md) |
| Bagaimana optimize query? | [query-optimization.md](./query-optimization.md) |
| Bagaimana setup backup? | [backup-recovery.md](./backup-recovery.md) |
| Apa indexing strategy? | [query-optimization.md](./query-optimization.md) |
| Bagaimana disaster recovery? | [backup-recovery.md](./backup-recovery.md) |

## See Also

- [Root Documentation Hub](../README.md) - Overview semua domains
- [../api/endpoints.md](../api/endpoints.md) - API schema context untuk endpoints
- [../deployment/deployment-pipeline.md](../deployment/deployment-pipeline.md) - Production database setup
- [../performance/performance-targets.md](../performance/performance-targets.md) - Database performance targets

---

*Database domain dipertahankan per AGENTS.md conventions. Lihat juga: design.md, requirements.md*

