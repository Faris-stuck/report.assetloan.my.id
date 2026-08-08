---
domain: documentation-restructuring
purpose: implementation-reference
version: 1.0
updated: 2024-01-15
owner: platform-team
related: [design]
---

# Technical Design Reference: Implementation Examples

## Section 10: Implementation Example - Complete Domain Structure

### 10.1 API Domain - Complete Example

```
docs/api/
├── README.md
├── endpoints.md                         # Complete API specifications
├── authentication-requirements.md       # Auth per endpoint
├── rate-limiting.md                     # Rate limits & quotas
└── error-codes.md                       # Error reference
```

**Sample: api/README.md**
```markdown
---
domain: api
purpose: folder-navigation
version: 1.0
updated: 2024-01-15
owner: platform-team
---

# API Documentation

## Purpose

Complete API specifications, endpoint documentation, authentication requirements, rate limiting policies, and error handling standards. Serves as the contract between frontend/backend and external consumers.

## Quick Navigation

| Document | Purpose | For Whom |
|----------|---------|----------|
| endpoints.md | All endpoint specs | API developers |
| authentication-requirements.md | Auth per endpoint | Security, Frontend |
| rate-limiting.md | Rate limits & quotas | Integration, DevOps |
| error-codes.md | Error reference | All developers |

## Folder Organization Rules

- Add new file: New endpoint category or auth framework
- Extend existing: New endpoint in existing category
- Max direct files: 8 (create sub-folders if exceeded: `endpoints/`, `webhooks/`)

## Related Domains

- **auth/** - Authentication mechanisms (session, JWT, OAuth)
- **database/** - Data schemas referenced in endpoints
- **testing/** - API testing procedures
- **deployment/** - API versioning and deprecation

## Getting Started

**For API Developers**: Start with endpoints.md
**For Frontend Integration**: endpoints.md + authentication-requirements.md
**For Troubleshooting**: error-codes.md + rate-limiting.md
```

**Sample: api/endpoints.md (Header)**
```yaml
---
domain: api
purpose: endpoint-specs
version: 2.1
updated: 2024-01-15
owner: platform-team
related: [auth, database]
endpoint_count: 24
api_version: v2
authentication_required: true
---

# API Endpoints Reference

[Full endpoint specs...]
```

### 10.2 Testing Domain - Complete Example

```
docs/testing/
├── README.md
├── testing-framework.md                 # Framework setup
├── accessibility-testing.md             # Accessibility procedures
├── accessibility-test-results.md        # Consolidated test results
├── implementation-test-checklist.md     # Implementation testing
├── mobile-device-test-results.md        # Mobile testing
└── consistency-checklist.md             # Documentation consistency
```

**Sample: testing/accessibility-test-results.md (Consolidated)**
```yaml
---
domain: testing
purpose: test-results
version: 1.2
updated: 2024-01-15
owner: qa-team
related: [ui, ux]
quick_reference: true
---

# Accessibility Test Results

## Keyboard Navigation Tests

[Merged from KEYBOARD_NAVIGATION_TEST_RESULTS.md]
- Section 1: Test procedures
- Section 2: Results matrix
- Section 3: Pass/fail status

## Focus Indicator Tests

[Merged from TESTING_FOCUS_INDICATORS.md]
- Section 1: Focus indicator specs
- Section 2: Test results
- Section 3: Browser compatibility

## Mobile Device Testing

[Merged from MOBILE_DEVICE_TESTING_RESULTS.md]
- Section 1: Device list
- Section 2: Test procedures
- Section 3: Results by device

## Test Summary

[Consolidated metrics across all tests]
```

### 10.3 Archive Domain - Structure

```
docs/archive/
├── README.md                            # Archive index & retrieval guide
├── index.md                             # Machine-readable archive index
├── CHANGELOG-legacy.md                  # Historical release notes
├── implementation-phase-summary.md      # Historical phase docs
├── mobile-optimization-summary.md       # Historical phase docs
├── wave-1-completion.md                 # Wave 1 completion
├── wave-2-completion.md                 # Wave 2 completion
├── wave-3-completion.md                 # Wave 3 completion
├── wave-4-7-implementation.md           # Waves 4-7 implementation
├── tasks-completion-summary.md          # Historical task tracking
├── verification-phase-complete.md       # Historical verification
└── implementation-changes-wave-detailed.md
```

**Sample: archive/README.md**
```yaml
---
domain: archive
purpose: folder-navigation
archived: false
version: 1.0
updated: 2024-01-15
---

# Documentation Archive

## Purpose

This folder contains historical, outdated, or superseded documentation. Archive files are preserved for audit trails and historical reference but are NOT actively linked from current documentation (except in ADRs for historical context).

## Archived Files Index

| File | Archive Date | Category | Reason |
|------|--------------|----------|--------|
| CHANGELOG-legacy.md | 2024-01-15 | Release Notes | Legacy release tracking |
| wave-1-completion.md | 2024-01-15 | Phase | Historical wave completion |
| wave-2-completion.md | 2024-01-15 | Phase | Historical wave completion |
| [more files...] | ... | ... | ... |

## Retrieval Guidelines

Archive files are preserved for:
- Historical audit trail
- Context for decision records (see decisions/ folder)
- Reference when understanding legacy decisions

Archive files are NOT recommended for current use because:
- Information may be outdated
- Procedures may have changed
- Technology references may be superseded

## How to Use Archive

### Finding historical context
1. Locate related decision record in decisions/ folder
2. Check "See Also" section for archive references
3. Review archive file for historical context only

### Restoring archived information
If you need information from archived file:
1. Check if current documentation covers this (likely in active domain folder)
2. If not found in active docs, review archive file
3. Verify information currency before using in decisions

### Adding files to archive
When archiving a document:
1. Add `archived: true` and `archived_date: YYYY-MM-DD` to frontmatter
2. Add `archive_reason: [reason from categories]`
3. Move to docs/archive/ folder
4. Update this README index
```

**Sample: archive/index.md (Machine-Readable)**
```yaml
---
domain: archive
purpose: machine-readable-index
version: 1.0
---

archived_documents:
  - file: CHANGELOG-legacy.md
    archived_date: 2024-01-15
    category: release-notes
    reason: historical-release-tracking
    original_location: docs/CHANGELOG.md
    
  - file: wave-1-completion.md
    archived_date: 2024-01-15
    category: phase
    reason: historical-wave-completion
    wave: 1
    
  - file: wave-2-completion.md
    archived_date: 2024-01-15
    category: phase
    reason: historical-wave-completion
    wave: 2

  # [more archived documents...]

archive_statistics:
  total_files: 11
  earliest_archive: 2024-01-15
  categories:
    phase: 5
    release-notes: 1
    implementation: 2
    task-tracking: 1
    verification: 1
```

---

## Section 11: Quick Reference Matrix (Developer Lookup Table)

### 11.1 "What Am I Doing?" → "Where Do I Look?" Matrix

| Developer Task | Primary Folder | Quick Reference | Related Folders |
|---|---|---|---|
| Add API endpoint | api/ | endpoints.md | auth/, testing/ |
| Create UI component | ui/ | design-system.md | ux/, testing/ |
| Design UX flow | ux/ | user-workflows.md | ui/, business/ |
| Implement authentication | auth/ | authentication.md | api/, deployment/ |
| Create database migration | database/ | migration-procedures.md | deployment/, testing/ |
| Write unit tests | testing/ | testing-framework.md | all domains |
| Deploy to production | deployment/ | deployment-pipeline.md | all domains |
| Define business rule | business/ | business-rules.md | auth/, ux/ |
| Set up dev environment | development/ | local-development-setup.md | coding-standards.md |
| Make architecture decision | decisions/ | README.md (ADR list) | all domains |
| Optimize performance | performance/ | performance-targets.md | database/, api/ |

### 11.2 Troubleshooting Guide → Documentation Map

| Problem/Question | Best Starting Point | Follow-Up Resources |
|---|---|---|
| "Why is my API call failing?" | api/error-codes.md | api/endpoints.md, api/rate-limiting.md |
| "Is this component accessible?" | ui/accessibility-standards.md | testing/accessibility-testing.md |
| "How do I set up my environment?" | development/local-development-setup.md | development/coding-standards.md |
| "What's the authentication flow?" | auth/authentication.md | api/authentication-requirements.md |
| "How do I deploy this?" | deployment/deployment-pipeline.md | deployment/rollback-procedures.md |
| "What role can do what?" | business/role-workflow-specification.md | auth/authorization-rbac.md |
| "Why is my query slow?" | database/query-optimization.md | performance/performance-targets.md |
| "What tests do I need to write?" | testing/testing-framework.md | testing/implementation-test-checklist.md |

### 11.3 Audience Quick Start Paths

#### Frontend Developer Path
1. Start: development/local-development-setup.md
2. Then: ui/design-system.md + ux/quick-reference.md
3. API usage: api/endpoints.md + api/authentication-requirements.md
4. Testing: testing/testing-framework.md
5. Quick refs: ux/quick-reference.md (bookmarked)

#### Backend Developer Path
1. Start: development/local-development-setup.md
2. Then: api/endpoints.md + database/schema-overview.md
3. Authentication: auth/authentication.md + auth/authorization-rbac.md
4. Migrations: database/migration-procedures.md
5. Testing: testing/testing-framework.md
6. Quick refs: api/error-codes.md (bookmarked)

#### Designer Path
1. Start: ui/design-system.md
2. Then: ui/design-tokens.md
3. Workflows: ux/implementation-guide.md + ux/user-workflows.md
4. Accessibility: ui/accessibility-standards.md
5. References: ui/design-tokens.md (bookmarked)

#### DevOps Engineer Path
1. Start: deployment/deployment-pipeline.md
2. Then: deployment/environment-configuration.md
3. Monitoring: deployment/monitoring-alerting.md
4. Recovery: deployment/rollback-procedures.md
5. Database: database/backup-recovery.md
6. Quick refs: deployment/deployment-pipeline.md (bookmarked)

---

## Section 12: Migration Execution Checklist

### 12.1 Pre-Migration Tasks

```markdown
- [ ] Review and approve folder structure
- [ ] Finalize metadata template (YAML frontmatter)
- [ ] Create README templates for all 12 domains
- [ ] Identify consolidation candidates (40+ files reviewed)
- [ ] Flag files for archival with reasons
- [ ] Create git branch: docs/restructure-modular-v1
- [ ] Notify team of migration timeline
- [ ] Back up current docs/ folder
```

### 12.2 Phase 1: Preparation

```markdown
- [ ] Audit all 40+ existing files
- [ ] Create categorization spreadsheet
- [ ] Identify consolidation opportunities (e.g., testing files)
- [ ] Approve folder structure diagram
- [ ] Finalize metadata template
- [ ] Create domain-specific README templates
- [ ] Checkpoint Gate 1: All tasks complete
```

### 12.3 Phase 2: Folder Creation

```markdown
- [ ] Create all 12 domain folders (api/, ui/, ux/, auth/, database/, testing/, deployment/, business/, development/, decisions/, performance/, archive/)
- [ ] Create README.md in each folder using domain-specific template
- [ ] Create root docs/README.md with high-level overview
- [ ] Add Quick Navigation links in root README
- [ ] Add ASCII folder tree diagram to root README
- [ ] Create search tips matrix in root README
- [ ] Commit: "docs: create 12-domain modular structure"
- [ ] Checkpoint Gate 2: All README files in place
```

### 12.4 Phase 3: File Migration

```markdown
#### Foundation Domains (Day 1)
- [ ] Move docs/API.md → docs/api/endpoints.md
- [ ] Move docs/AUTH.md → docs/auth/authentication.md
- [ ] Move docs/DATABASE.md → docs/database/schema-overview.md
- [ ] Add YAML frontmatter to all three files
- [ ] Commit: "docs: migrate foundation domain files (api, auth, database)"

#### UI Domain (Day 2)
- [ ] Move docs/DESIGN.md → docs/ui/design-system.md
- [ ] Move docs/ACCESSIBILITY_COMPLIANCE_REPORT.md → docs/ui/accessibility-report.md
- [ ] Move docs/ARIA_LABEL_CONVENTION.md → docs/ui/aria-conventions.md
- [ ] Move docs/UI_UX_STANDARDS.md → docs/ui/ui-ux-standards.md
- [ ] Merge UI_UX_STANDARDS_INDEX.md into ui-ux-standards.md
- [ ] Add YAML frontmatter to all files
- [ ] Commit: "docs: migrate ui domain files"

#### Testing Domain (Day 2)
- [ ] Move docs/TESTING.md → docs/testing/testing-framework.md
- [ ] Create docs/testing/accessibility-test-results.md (consolidated)
- [ ] Merge KEYBOARD_NAVIGATION_TEST_RESULTS.md → accessibility-test-results.md
- [ ] Merge TESTING_FOCUS_INDICATORS.md → accessibility-test-results.md
- [ ] Move docs/MOBILE_DEVICE_TESTING_RESULTS.md → docs/testing/mobile-device-test-results.md
- [ ] Move docs/IMPLEMENTATION_TEST_CHECKLIST.md → docs/testing/implementation-test-checklist.md
- [ ] Move docs/CONSISTENCY_CHECKLIST.md → docs/testing/consistency-checklist.md
- [ ] Add YAML frontmatter to all files
- [ ] Commit: "docs: migrate and consolidate testing domain files"

#### Remaining Domains (Day 3-4)
- [ ] Migrate deployment/ domain files
- [ ] Migrate business/ domain files
- [ ] Migrate development/ domain files
- [ ] Migrate ux/ domain files
- [ ] Migrate auth/ additional files
- [ ] Migrate decisions/ (ADRs with renumbering)
- [ ] Migrate performance/ domain files
- [ ] Add YAML frontmatter to all files
- [ ] Commit per domain: "docs: migrate [domain] files"

#### Archive Domain (Day 5)
- [ ] Move docs/CHANGELOG.md → docs/archive/CHANGELOG-legacy.md
- [ ] Move docs/CONSISTENCY_VERIFICATION_COMPLETE.md → docs/archive/verification-phase-complete.md
- [ ] Move docs/IMPLEMENTATION_PHASE_1_2_SUMMARY.md → docs/archive/implementation-phase-summary.md
- [ ] Move docs/MOBILE_OPTIMIZATION_COMPLETION_SUMMARY.md → docs/archive/mobile-optimization-summary.md
- [ ] Move docs/TASKS_17_23_COMPLETION_SUMMARY.md → docs/archive/tasks-completion-summary.md
- [ ] Move docs/WAVE1_COMPLETION_CHECKLIST.md → docs/archive/wave-1-completion.md
- [ ] Move docs/WAVE2_COMPLETION_CHECKLIST.md → docs/archive/wave-2-completion.md
- [ ] Move docs/WAVE3_COMPLETION_CHECKLIST.md → docs/archive/wave-3-completion.md
- [ ] Move docs/WAVE4-7_IMPLEMENTATION_CHECKLIST.md → docs/archive/wave-4-7-implementation.md
- [ ] Move docs/IMPLEMENTATION_CHANGES_DETAILED.md → docs/archive/implementation-changes-wave-detailed.md
- [ ] Add archive metadata to all files (archived: true, archived_date, archive_reason)
- [ ] Create docs/archive/index.md (machine-readable archive index)
- [ ] Commit: "docs: archive historical documentation"

- [ ] Checkpoint Gate 3: All 40+ files migrated
```

### 12.5 Phase 4: Link Correction & Validation

```markdown
#### Link Fixing (Day 1-2)
- [ ] Search for broken markdown links: `grep -r '\[.*\](.*\.md)' docs/`
- [ ] Fix cross-domain relative paths to use ../domain/file.md format
- [ ] Verify all section anchors (#heading) still valid
- [ ] Test links on Windows, Mac, Linux (forward slashes)
- [ ] Update links in any code files referencing docs/
- [ ] Commit: "docs: fix cross-domain links and references"

#### Validation (Day 2-3)
- [ ] Run Gate 1 validation: Folder structure (12 domains, no extras)
- [ ] Run Gate 2 validation: README completeness (all folders have README)
- [ ] Run Gate 3 validation: Metadata completeness (all files have frontmatter)
- [ ] Run Gate 4 validation: Link validity (no broken links)
- [ ] Run Gate 5 validation: No circular dependencies
- [ ] Run Gate 6 validation: File organization (max 15 files per folder)
- [ ] Run Gate 7 validation: Naming consistency
- [ ] Run Gate 8 validation: Metadata consistency
- [ ] Run Gate 9 validation: Terminology consistency
- [ ] Run Gate 10 validation: Archive integrity
- [ ] Run Gate 11 validation: Domain coverage
- [ ] Run Gate 12 validation: Root navigation complete
- [ ] Resolve any validation failures
- [ ] Commit: "docs: validation gates pass"
- [ ] Checkpoint Gate 4: All 12 validation gates PASS
```

### 12.6 Phase 5: Finalization

```markdown
- [ ] Update any code references to old docs/ paths
- [ ] Update CONTRIBUTING.md if it references docs/
- [ ] Update project README.md with new docs structure
- [ ] Update CI/CD scripts if they build or reference docs/
- [ ] Create migration summary document
- [ ] Create PR for migration branch
- [ ] Final validation of migration (all gates should still PASS)
- [ ] Merge PR to main branch
- [ ] Verify main branch still passes all validation gates
- [ ] Delete migration branch (or archive it)
- [ ] Commit: "docs: merge modular restructuring (12 domains, 40+ files)"
- [ ] Tag release: v1.0-docs-restructured
- [ ] Checkpoint Gate 5: Migration complete and live
```

### 12.7 Post-Migration Maintenance

```markdown
- [ ] Update team documentation (new structure overview)
- [ ] Train team on new folder organization
- [ ] Update issue templates to reference correct doc links
- [ ] Set up automated validation in CI/CD pipeline
- [ ] Schedule monthly validation check
- [ ] Create runbook for adding new documentation to correct domain
- [ ] Monitor for broken links in first week
```

---

## Conclusion & Implementation Ready

This technical design provides a complete, production-ready blueprint for restructuring 40+ documentation files into a scalable, modular architecture. The design covers:

✅ 12-domain folder structure with clear ownership
✅ Complete file migration matrix (40+ files)
✅ Consolidation logic for related documents
✅ Archive strategy with metadata
✅ YAML frontmatter format for machine readability
✅ 5-phase migration pipeline with checkpoints
✅ 12+ automated validation gates
✅ Cross-domain reference strategy
✅ 20 correctness properties
✅ AI/tool accessibility patterns
✅ Complete implementation checklist

**Ready for immediate execution with:**
- Pre-calculated timing (5-7 days total)
- Clear phase gates and rollback strategy
- Detailed checklist for each phase
- Validation criteria for quality assurance
- Git preservation strategy for history
- Team communication plan

The restructured documentation will be:
- **Scalable**: Easy to add new documents or domains
- **Maintainable**: Clear ownership and organization rules
- **Discoverable**: Machine-indexable metadata and cross-domain links
- **Accessible**: Both for humans and AI tools
- **Historical**: Complete archive with reasoning preserved
