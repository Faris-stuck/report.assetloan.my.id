# Performance Documentation

## Purpose

Domain ini mendokumentasikan performance targets dan SLAs, profiling procedures, benchmarking results, caching strategies, optimization techniques, dan performance metrics. Dokumentasi performance memandu teams tentang performance requirements dan optimization strategies.

## Quick Navigation

| Dokumen | Purpose | Untuk Siapa | Status |
|---------|---------|-----------|--------|
| [performance-targets.md](./performance-targets.md) | Performance targets dan SLAs | Performance engineers, developers | Stable |
| [profiling-procedures.md](./profiling-procedures.md) | Profiling procedures dan tools | Performance engineers, developers | Stable |
| [caching-strategies.md](./caching-strategies.md) | Caching strategies dan implementation | Backend developers, DevOps | Stable |
| [performance-metrics.md](./performance-metrics.md) | Performance metrics dan monitoring | Performance engineers, DevOps | Stable |

## Folder Contents

### Primary Documents
- **performance-targets.md** - Performance targets, SLAs, latency budgets per feature
- **profiling-procedures.md** - Profiling procedures, tools, analysis methods
- **caching-strategies.md** - Caching strategies, implementation, cache invalidation
- **performance-metrics.md** - Performance metrics, monitoring setup, dashboards

### Reference & Quick Lookups
- Semua READMEs dalam folder ini adalah navigation files

## Folder Organization Rules

### When to Add New Files

Tambahkan file baru ketika:
- Performance aspect baru memerlukan dokumentasi independen (e.g., `database-optimization.md`, `frontend-optimization.md`)
- Expected size melebihi 300 lines di existing document
- Topic adalah distinct dan independent dari existing files

Extend existing file ketika:
- Content berhubungan dengan existing performance aspect
- Section bisa logically ditambahkan ke existing document
- Updates hanya ke existing document yang dibutuhkan

### File Naming Convention

**Pattern**: `PURPOSE[-VARIANT].md`

**Rules**:
- Lowercase letters only
- Gunakan hyphens untuk separate words

**Examples**:
- `performance-targets.md`
- `profiling-procedures.md`
- `caching-strategies.md`
- `performance-metrics.md`
- `database-optimization.md`
- `frontend-optimization.md`

### Folder Growth

**Direct file limit**: 6 files maximum

**Sub-folder naming pattern**: `performance-[category]/`

## Related Domains

- **[api/](../api/)** - API performance targets dan optimization
- **[database/](../database/)** - Database query optimization
- **[deployment/](../deployment/)** - Performance monitoring dalam production
- **[ui/](../ui/)** - Frontend performance optimization
- **[development/](../development/)** - Profiling tools setup

## Getting Started

### Untuk Performance Engineers

Mulai dengan:
1. Baca [performance-targets.md](./performance-targets.md) untuk targets dan SLAs
2. Ikuti [profiling-procedures.md](./profiling-procedures.md) untuk profiling
3. Implementasikan [caching-strategies.md](./caching-strategies.md) untuk optimization
4. Monitor per [performance-metrics.md](./performance-metrics.md)

### Untuk Backend Developers

Mulai dengan:
1. Baca [performance-targets.md](./performance-targets.md) untuk requirements
2. Gunakan [caching-strategies.md](./caching-strategies.md) untuk implementation
3. Tinjau [profiling-procedures.md](./profiling-procedures.md) untuk optimization
4. Referensi [performance-metrics.md](./performance-metrics.md) untuk monitoring

### Untuk DevOps/SRE

Mulai dengan:
1. Baca [performance-targets.md](./performance-targets.md) untuk SLAs
2. Setup monitoring per [performance-metrics.md](./performance-metrics.md)
3. Alert per SLAs di [deployment/monitoring-alerting.md](../deployment/monitoring-alerting.md)
4. Investigate issues per [profiling-procedures.md](./profiling-procedures.md)

## Search Tips

| Pertanyaan | Jawaban |
|-----------|---------|
| Apa performance target? | [performance-targets.md](./performance-targets.md) |
| Apa SLA? | [performance-targets.md](./performance-targets.md) |
| Bagaimana profile aplikasi? | [profiling-procedures.md](./profiling-procedures.md) |
| Apa caching strategy? | [caching-strategies.md](./caching-strategies.md) |
| Bagaimana implement cache? | [caching-strategies.md](./caching-strategies.md) |
| Apa performance metrics? | [performance-metrics.md](./performance-metrics.md) |
| Bagaimana optimize database? | [../database/query-optimization.md](../database/query-optimization.md) |

## See Also

- [Root Documentation Hub](../README.md) - Overview semua domains
- [../database/query-optimization.md](../database/query-optimization.md) - Database query optimization
- [../api/rate-limiting.md](../api/rate-limiting.md) - API rate limiting untuk performance protection
- [../deployment/monitoring-alerting.md](../deployment/monitoring-alerting.md) - Production monitoring

---

*Performance domain dipertahankan per AGENTS.md conventions. Lihat juga: design.md, requirements.md*

