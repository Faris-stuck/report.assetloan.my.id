# DATABASE Domain README Template

This is a domain-specific template for `docs/database/README.md`. Copy and customize.

---

# Database Documentation

## Purpose

This folder documents database schema design, entity relationships, migration procedures and versioning, query optimization strategies and indexing, backup and disaster recovery procedures, and data consistency patterns. Database documentation guides developers on schema changes, query performance, operational procedures, and data management best practices.

## Quick Navigation

| Document | Purpose | For Whom |
|----------|---------|----------|
| [schema-overview.md](./schema-overview.md) | Complete database schema with ER diagrams and table definitions | All developers, DBAs |
| [migration-procedures.md](./migration-procedures.md) | How to create, test, deploy database migrations safely | Backend developers, DevOps |
| [query-optimization.md](./query-optimization.md) | Indexing strategy, query patterns, performance tuning | Backend developers, DBAs |
| [backup-recovery.md](./backup-recovery.md) | Backup procedures, recovery steps, RTO/RPO definitions | DevOps, DBAs |
| [data-consistency.md](./data-consistency.md) | Constraints, transactions, referential integrity rules | Backend developers, DBAs |
| [connection-pooling.md](./connection-pooling.md) | Connection pool configuration and timeout settings | Backend developers, DevOps |

## Folder Organization Rules

### When to Add New Files

Add new file when:
- New database system or major migration strategy documented
- Operational procedure needs standalone reference (e.g., `sharding.md`, `replication.md`)
- Performance optimization topic warrants independent documentation

Extend existing file when:
- New table addition to existing schema
- New index or query pattern fits existing optimization doc
- Additional migration example or procedure variant

### File Naming Convention

**Pattern**: `PURPOSE.md`  
**Examples**:
- `schema-overview.md`
- `migration-procedures.md`
- `query-optimization.md`
- `backup-recovery.md`
- `data-consistency.md`
- `connection-pooling.md`
- `sharding-strategy.md` (if applicable)
- `replication-setup.md` (if applicable)

### Folder Growth

**Current files**: [X] direct markdown files  
**Max threshold**: 6 files  
**Action if exceeded**: Create `operations/` or `migrations/` subfolder

**Example future structure** (if 6+ docs):
```
docs/database/
├── README.md
├── schema-overview.md
├── query-optimization.md
├── data-consistency.md
├── migrations/
│   ├── README.md
│   ├── migration-procedures.md
│   ├── version-history.md
│   └── rollback-procedures.md
└── operations/
    ├── README.md
    ├── backup-recovery.md
    ├── connection-pooling.md
    └── monitoring.md
```

## Related Domains

- **[deployment/](../deployment/)** - Database deployment in production environments
- **[testing/](../testing/)** - Database seeding, fixtures, test data management
- **[performance/](../performance/)** - Query performance metrics and benchmarking
- **[api/](../api/)** - API request data models match database schema
- **[auth/](../auth/)** - User credential storage and access control

## Getting Started

### For Backend Developers

1. **Understand schema**: [Review schema-overview.md](./schema-overview.md) for data model
2. **Write migrations**: [Follow migration-procedures.md](./migration-procedures.md)
3. **Optimize queries**: [Check query-optimization.md](./query-optimization.md)
4. **Maintain integrity**: [Follow data-consistency.md](./data-consistency.md)
5. **Handle connections**: [Review connection-pooling.md](./connection-pooling.md)

### For DBAs/DevOps

1. **Understand design**: [Review schema-overview.md](./schema-overview.md)
2. **Manage migrations**: [Follow migration-procedures.md](./migration-procedures.md)
3. **Monitor performance**: [Check query-optimization.md](./query-optimization.md)
4. **Plan recovery**: [Review backup-recovery.md](./backup-recovery.md)
5. **Configure pools**: [Follow connection-pooling.md](./connection-pooling.md)

### For QA/Testing

1. **Know schema**: [Review schema-overview.md](./schema-overview.md)
2. **Seed test data**: [Check migration-procedures.md](./migration-procedures.md#test-data)
3. **Test integrity**: [Follow data-consistency.md](./data-consistency.md#testing)
4. **Verify backups**: [Review backup-recovery.md](./backup-recovery.md#testing)

## Search Tips

| Question | Answer |
|----------|--------|
| What's the [table] structure? | [schema-overview.md](./schema-overview.md) |
| How do I create a migration? | [migration-procedures.md](./migration-procedures.md) |
| Why is my query slow? | [query-optimization.md](./query-optimization.md) |
| What indexes exist? | [query-optimization.md](./query-optimization.md#indexes) or [schema-overview.md](./schema-overview.md) |
| How do I back up data? | [backup-recovery.md](./backup-recovery.md) |
| How do I recover data? | [backup-recovery.md](./backup-recovery.md#recovery) |
| What are referential constraints? | [data-consistency.md](./data-consistency.md) |
| How many connections can I open? | [connection-pooling.md](./connection-pooling.md) |

### Common Scenarios → Documents

- **"I need to add a column"** → [migration-procedures.md](./migration-procedures.md)
- **"My report query is slow"** → [query-optimization.md](./query-optimization.md)
- **"I need test data"** → [schema-overview.md](./schema-overview.md) or [migration-procedures.md](./migration-procedures.md#fixtures)
- **"I lost data accidentally"** → [backup-recovery.md](./backup-recovery.md)
- **"Connection timeouts"** → [connection-pooling.md](./connection-pooling.md)

## See Also

- [Root Documentation Hub](../README.md) - Overview of all domains
- [../deployment/deployment-pipeline.md](../deployment/deployment-pipeline.md) - Database in production deployment
- [../performance/query-analysis.md](../performance/query-analysis.md) - Performance analysis tools
- [../testing/testing-framework.md](../testing/testing-framework.md) - Database testing procedures

---

## Database Summary

**Database System**: [MySQL|MariaDB|PostgreSQL]  
**Version**: [X.X.X]  
**Tables**: [X] documented  
**Indexes**: [X]  
**Migrations**: [X] tracked  
**Backup Frequency**: [daily|hourly]  
**RTO**: [X] minutes  
**RPO**: [X] minutes  

---

*Database documentation is maintained per AGENTS.md. See design.md for domain structure.*
