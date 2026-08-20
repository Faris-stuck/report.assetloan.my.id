---
domain: documentation-restructuring
purpose: technical-design
version: 1.0
updated: 2024-01-15
owner: platform-team
related: [architecture, development]
---

# Technical Design: Documentation Restructuring (Modular Structure C)

## Overview

This technical design provides a complete implementation blueprint for restructuring 40+ documentation files from a flat, category-based structure into a modular, domain-based architecture. The design establishes a scalable, maintainable system with clear ownership, machine-readable metadata, and automated quality validation. The solution enables rapid navigation, AI-tool accessibility, and seamless cross-domain referencing.

---

## Section 1: Modular Folder Architecture Blueprint

### 1.1 Complete Folder Structure

```
docs/
├── README.md                          # Root navigation & overview
├── api/                               # API specifications & endpoints
│   ├── README.md
│   ├── endpoints.md
│   ├── authentication-requirements.md
│   ├── rate-limiting.md
│   └── error-codes.md
├── ui/                                # UI components & design system
│   ├── README.md
│   ├── design-system.md
│   ├── design-tokens.md
│   ├── accessibility-standards.md
│   ├── accessibility-report.md
│   ├── aria-conventions.md
│   └── ui-ux-standards.md
├── ux/                                # UX patterns & workflows
│   ├── README.md
│   ├── implementation-guide.md
│   ├── user-workflows.md
│   └── quick-reference.md
├── auth/                              # Authentication & authorization
│   ├── README.md
│   ├── authentication.md
│   ├── authorization-rbac.md
│   ├── security-practices.md
│   └── enterprise-security.md
├── database/                          # Database schema & queries
│   ├── README.md
│   ├── schema-overview.md
│   ├── migration-procedures.md
│   ├── query-optimization.md
│   └── backup-recovery.md
├── testing/                           # Testing guidelines & procedures
│   ├── README.md
│   ├── testing-framework.md
│   ├── accessibility-testing.md
│   ├── implementation-test-checklist.md
│   ├── mobile-device-tests.md
│   ├── accessibility-test-results.md
│   └── consistency-checklist.md
├── deployment/                        # CI/CD & infrastructure
│   ├── README.md
│   ├── deployment-pipeline.md
│   ├── environment-configuration.md
│   ├── monitoring-alerting.md
│   └── rollback-procedures.md
├── business/                          # Business logic & product specs
│   ├── README.md
│   ├── product-specifications.md
│   ├── business-rules.md
│   ├── role-workflow-specification.md
│   └── compliance-requirements.md
├── development/                       # Setup, standards, conventions
│   ├── README.md
│   ├── local-development-setup.md
│   ├── coding-standards.md
│   ├── git-workflow.md
│   ├── build-test-commands.md
│   └── future-features-guide.md
├── decisions/                         # Architecture Decision Records
│   ├── README.md
│   ├── 0-architecture-overview.md
│   ├── 1-authentication-strategy.md
│   ├── 2-database-structure.md
│   └── [ADRs continue...]
├── performance/                       # Performance & optimization
│   ├── README.md
│   ├── performance-targets.md
│   ├── profiling-procedures.md
│   ├── caching-strategies.md
│   ├── lighthouse-audit-report.md
│   └── performance-metrics.md
└── archive/                           # Historical & outdated docs
    ├── README.md
    ├── implementation-phase-summary.md
    ├── mobile-optimization-summary.md
    ├── wave-1-completion.md
    ├── wave-2-completion.md
    ├── wave-3-completion.md
    ├── wave-4-7-implementation.md
    ├── tasks-completion-summary.md
    ├── verification-phase-complete.md
    ├── implementation-changes-wave-1.md
    ├── CHANGELOG-legacy.md
    └── index.md
```

### 1.2 Folder Navigation Relationships

```
Core Domain Layers:
├─ Foundation Layer (api, database, auth)
│  └─ Enables: ui, ux, business, deployment
├─ Interface Layer (ui, ux)
│  ├─ Depends on: api, auth, business
│  └─ References: accessibility standards
├─ Execution Layer (testing, deployment, development)
│  └─ Validates: all other domains
├─ Strategy Layer (business, decisions, performance)
│  └─ Guides: all other domains
└─ History Layer (archive)
   └─ Reference only: for historical context
```

### 1.3 Sub-folder Patterns

**Pattern 1: When to Create Sub-folders**
- Domain exceeds 15 direct files → Create logical sub-folders
- Domain has 3+ distinct categories → Group related files
- Sub-domain has independent ownership → Separate folder

**Pattern 2: Sub-folder Naming Convention**
- Lowercase, hyphenated names matching parent domain
- Example: `ui/components/`, `ui/tokens/`, `database/migrations/`
- Consistent with parent folder naming style

**Pattern 3: Sub-folder README Requirements**
- Must exist in each sub-folder (if folder exists, it has README)
- Explains relationship to parent domain
- Lists files specific to sub-domain
- Contains domain-specific organization rules

---

## Section 2: File Migration & Consolidation Strategy


### 2.1 Comprehensive File Migration Matrix

#### Header Columns Explained
- **Source**: Existing file path (docs/*.md or docs/DECISIONS/*.md)
- **Target Domain**: New domain folder destination
- **Target File**: New file name with domain
- **Strategy**: move, consolidate, archive, split
- **Consolidation Mapping**: If consolidate, which files merge and section mapping

#### Complete Migration Matrix (40+ Files)

| Source | Target Domain | Target File | Strategy | Consolidation Mapping / Notes |
|--------|---------------|------------|----------|------|
| docs/API.md | api/ | endpoints.md | Move | Complete API endpoint specifications remain intact |
| docs/AUTH.md | auth/ | authentication.md | Move | Session/JWT/OAuth mechanisms documented |
| docs/ARCHITECTURE.md | decisions/ | 0-architecture-overview.md | Move | High-level system architecture as ADR |
| docs/BUSINESS_RULES.md | business/ | business-rules.md | Move | Domain logic and business workflows |
| docs/CODING_STANDARDS.md | development/ | coding-standards.md | Move | Code style guides and conventions |
| docs/DATABASE.md | database/ | schema-overview.md | Move | Database structure and entity relationships |
| docs/DESIGN.md | ui/ | design-system.md | Move | Design tokens and design principles |
| docs/DEPLOYMENT.md | deployment/ | deployment-pipeline.md | Move | CI/CD workflow and deployment procedures |
| docs/SECURITY.md | auth/ | security-practices.md | Move | Security guidelines and best practices |
| docs/SECURITY_ENTERPRISE.md | auth/ | enterprise-security.md | Move | Enterprise-specific security requirements |
| docs/TESTING.md | testing/ | testing-framework.md | Move | Test setup, framework configuration |
| docs/PRODUCT.md | business/ | product-specifications.md | Move | Product features and roadmap documentation |
| docs/ACCESSIBILITY_COMPLIANCE_REPORT.md | ui/ | accessibility-report.md | Move | WCAG compliance audit results |
| docs/ARIA_LABEL_CONVENTION.md | ui/ | aria-conventions.md | Move | ARIA label naming standards |
| docs/CHANGELOG.md | archive/ | CHANGELOG-legacy.md | Archive | Historical release notes (archived: true, archived_date, reason: "Historical release tracking") |
| docs/CONSISTENCY_CHECKLIST.md | testing/ | consistency-checklist.md | Move | Validation procedures for documentation consistency |
| docs/CONSISTENCY_VERIFICATION_COMPLETE.md | archive/ | verification-phase-complete.md | Archive | Phase completion report (archived: true, archived_date, reason: "Historical phase documentation") |
| docs/FUTURE_PAGES_IMPLEMENTATION_GUIDE.md | development/ | future-features-guide.md | Move | Planned feature implementation procedures |
| docs/IMPLEMENTATION_CHANGES_DETAILED.md | archive/ | implementation-changes-wave-detailed.md | Archive | Implementation tracking by wave (archived: true, split by wave if multiple versions) |
| docs/IMPLEMENTATION_PHASE_1_2_SUMMARY.md | archive/ | implementation-phase-summary.md | Archive | Historical phase documentation (archived: true, reason: "Historical phase documentation") |
| docs/IMPLEMENTATION_TEST_CHECKLIST.md | testing/ | implementation-test-checklist.md | Move | Testing procedures for implementations |
| docs/KEYBOARD_NAVIGATION_TEST_RESULTS.md | testing/ | accessibility-test-results.md | Consolidate | Merge with mobile testing and other test results in unified accessibility-test-results.md |
| docs/LIGHTHOUSE_AUDIT_RESULTS.md | performance/ | lighthouse-audit-report.md | Move | Performance audit results and benchmarks |
| docs/MOBILE_DEVICE_TESTING_RESULTS.md | testing/ | mobile-device-test-results.md | Move | Mobile device testing procedures and results |
| docs/MOBILE_OPTIMIZATION_COMPLETION_SUMMARY.md | archive/ | mobile-optimization-summary.md | Archive | Phase completion (archived: true, reason: "Historical mobile optimization phase") |
| docs/TASKS_17_23_COMPLETION_SUMMARY.md | archive/ | tasks-completion-summary.md | Archive | Task tracking (archived: true, reason: "Historical task completion tracking") |
| docs/TESTING_FOCUS_INDICATORS.md | testing/ | accessibility-focus-indicators.md | Consolidate | Merge with accessibility-test-results.md under accessibility testing section |
| docs/UI_UX_IMPLEMENTATION_GUIDE.md | ux/ | implementation-guide.md | Move | UX implementation procedures and workflows |
| docs/UI_UX_QUICK_REFERENCE.md | ux/ | quick-reference.md | Move | Quick UI/UX reference guide for rapid lookup |
| docs/UI_UX_STANDARDS.md | ui/ | ui-ux-standards.md | Move | Complete UI/UX design standards and guidelines |
| docs/UI_UX_STANDARDS_INDEX.md | ui/ | standards-index.md | Consolidate | Merge index content into ui-ux-standards.md as navigation section |
| docs/WAVE1_COMPLETION_CHECKLIST.md | archive/ | wave-1-completion.md | Archive | Historical wave completion (archived: true, wave: 1) |
| docs/WAVE2_COMPLETION_CHECKLIST.md | archive/ | wave-2-completion.md | Archive | Historical wave completion (archived: true, wave: 2) |
| docs/WAVE3_COMPLETION_CHECKLIST.md | archive/ | wave-3-completion.md | Archive | Historical wave completion (archived: true, wave: 3) |
| docs/WAVE4-7_IMPLEMENTATION_CHECKLIST.md | archive/ | wave-4-7-implementation.md | Archive | Historical wave implementation (archived: true, waves: [4,5,6,7]) |
| docs/spec-4-role-workflow.md | business/ | role-workflow-specification.md | Move | Role-based workflow documentation and specifications |
| docs/DECISIONS/decision-1.md | decisions/ | 1-[title].md | Keep | Preserve ADR structure, add metadata, renumber if needed |
| docs/DECISIONS/decision-*.md (all ADRs) | decisions/ | [preserve as-is] | Keep | Keep existing ADR naming, add/update YAML frontmatter |

### 2.2 Consolidation Logic

**Consolidation Rule 1: Testing Results**
- Source: KEYBOARD_NAVIGATION_TEST_RESULTS.md, TESTING_FOCUS_INDICATORS.md, MOBILE_DEVICE_TESTING_RESULTS.md
- Target: testing/accessibility-test-results.md
- Structure:
  ```
  # Accessibility Test Results
  
  ## Keyboard Navigation Tests
  [Merged from KEYBOARD_NAVIGATION_TEST_RESULTS.md]
  
  ## Focus Indicator Tests
  [Merged from TESTING_FOCUS_INDICATORS.md]
  
  ## Mobile Device Testing
  [Merged from MOBILE_DEVICE_TESTING_RESULTS.md]
  
  ## Test Summary Matrix
  [Consolidated metrics]
  ```

**Consolidation Rule 2: UI/UX Standards**
- Source: UI_UX_STANDARDS.md, UI_UX_STANDARDS_INDEX.md
- Target: ui/ui-ux-standards.md
- Structure:
  ```
  # UI/UX Standards
  
  ## Quick Navigation
  [Merged from UI_UX_STANDARDS_INDEX.md]
  
  ## Full Standards
  [Complete standards from UI_UX_STANDARDS.md]
  ```

**Consolidation Rule 3: Implementation Changes**
- Source: IMPLEMENTATION_CHANGES_DETAILED.md (if multiple waves exist)
- Target: archive/implementation-changes-wave-*.md (split by wave)
- Strategy: Create separate archive file for each wave if original file contains multiple distinct implementations

### 2.3 Archival Strategy

**Archive Metadata Template**
```yaml
---
domain: archive
purpose: historical-documentation
archived: true
archived_date: 2024-01-15
archive_reason: [reason category]
archive_category: [phase/wave/legacy/completed]
original_location: docs/[original-filename].md
related_active_docs: [links to active versions if applicable]
---
```

**Archive Reason Categories**
1. Historical phase documentation
2. Legacy implementation tracking
3. Historical release notes
4. Historical wave completion
5. Historical task tracking
6. Superseded by current documentation
7. Experimental/proof-of-concept

**Archive Retrieval Guidelines**
- Never link from active documentation to archive (except in ADRs for historical context)
- Archive files marked with `archived: true` metadata
- Archive folder has INDEX.md listing all archived files with retrieval guidance
- Archive README.md explains how to access and context for archived content

### 2.4 File Naming Conventions

**Convention by Domain**

| Domain | Pattern | Examples |
|--------|---------|----------|
| api/ | PURPOSE-VARIANT.md | endpoints.md, authentication-requirements.md, rate-limiting.md |
| ui/ | PURPOSE.md or COMPONENT-TYPE.md | design-system.md, accessibility-standards.md, aria-conventions.md |
| ux/ | PURPOSE.md | implementation-guide.md, user-workflows.md, quick-reference.md |
| auth/ | PURPOSE.md | authentication.md, authorization-rbac.md, security-practices.md |
| database/ | PURPOSE.md | schema-overview.md, migration-procedures.md, query-optimization.md |
| testing/ | TEST-TYPE-SCOPE.md | testing-framework.md, accessibility-testing.md, implementation-test-checklist.md |
| deployment/ | PURPOSE.md | deployment-pipeline.md, environment-configuration.md, monitoring-alerting.md |
| business/ | PURPOSE.md | product-specifications.md, business-rules.md, role-workflow-specification.md |
| development/ | PURPOSE.md | local-development-setup.md, coding-standards.md, git-workflow.md |
| decisions/ | SEQUENCE-TITLE.md | 0-architecture-overview.md, 1-authentication-strategy.md (ADR format) |
| performance/ | PURPOSE.md | performance-targets.md, profiling-procedures.md, caching-strategies.md |
| archive/ | PREFIX-DESCRIPTION.md | CHANGELOG-legacy.md, wave-1-completion.md, implementation-phase-summary.md |

---

## Section 3: Metadata Template & Format

### 3.1 YAML Frontmatter Structure (Complete)

```yaml
---
# Required Fields (ALL files except READMEs and archives)
domain: [api|ui|ux|auth|database|testing|deployment|business|development|decisions|performance|archive]
purpose: [endpoint-specs|design-system|quick-reference|authentication|schema|testing-framework|deployment-pipeline|product-spec|setup-guide|adr|performance-targets|historical]
version: 1.0                           # Document version (semver)
updated: 2024-01-15                    # ISO 8601 date

# Team & Ownership
owner: [team-name]                     # Responsible team
maintainer: [name or role]             # Primary point of contact (optional)

# Relations & Context
related: [domain1, domain2, ...]       # Related domains
dependencies: [domain1, domain2, ...]  # Domains this depends on (optional)
supersedes: [document-name]            # Document this replaces (optional)
deprecated: false                       # Is this document deprecated? (optional)

# Archive-Specific Fields (ONLY for archived files)
archived: true                          # Mark as archived
archived_date: 2024-01-15              # Date archived
archive_reason: [reason from categories]  # Why archived
archive_category: [phase|wave|legacy|completed]
original_location: docs/[original].md  # Where it was
related_active_docs: [link1, link2]    # Current docs that replace this

# Metadata Tags (optional)
tags: [tag1, tag2, ...]                # Search tags
quick_reference: true                  # Is this a quick ref? (flags for indexing)
status: [stable|frequently-updated|experimental]
audience: [all-developers|architects|frontend|backend]
confidentiality: [public|internal|confidential]

# Validation (optional, for QA tools)
validation_status: [valid|needs-review|outdated]
last_verified: 2024-01-15              # Last manual verification
---
```

### 3.2 Domain-Specific Metadata Requirements

| Domain | Additional Required Fields | Additional Optional Fields |
|--------|---|---|
| api/ | `endpoint_count`, `api_version` | `authentication_required`, `rate_limit` |
| ui/ | `component_count`, `design_system_version` | `wcag_level`, `browser_support` |
| auth/ | `authentication_types` | `mfa_supported`, `sso_support` |
| database/ | `schema_version`, `database_engine` | `migration_status`, `backup_frequency` |
| testing/ | `test_framework`, `coverage_target` | `automation_level` |
| deployment/ | `target_environments` | `deployment_frequency`, `rollback_procedure` |
| decisions/ | `decision_date`, `status` (proposed/accepted/deprecated) | `impact_level` |

### 3.3 Quick Reference Metadata Patterns

**Pattern 1: Quick Reference Document**
```yaml
quick_reference: true
audience: [all-developers]
purpose: quick-reference
status: stable
tags: [cheat-sheet, rapid-lookup]
```

**Pattern 2: Frequently Updated Document**
```yaml
status: frequently-updated
last_verified: [recent date]
validation_status: valid
update_frequency: weekly
```

**Pattern 3: Experimental Document**
```yaml
status: experimental
validation_status: needs-review
audience: [architects]
confidentiality: internal
```

**Pattern 4: Cross-Domain Reference Document**
```yaml
related: [domain1, domain2, domain3]
dependencies: [domain1, domain2]
purpose: integration-guide
audience: [all-developers]
```

### 3.4 Metadata Extraction Examples (for AI tools)

```python
# Python: Extract metadata for indexing
import yaml

def extract_metadata(file_path):
    with open(file_path, 'r') as f:
        content = f.read()
    
    # Parse frontmatter
    if content.startswith('---'):
        _, fm, _ = content.split('---', 2)
        metadata = yaml.safe_load(fm)
        return metadata
    return None

# Usage: Extract all docs by domain
metadata_index = {}
for domain in ['api', 'ui', 'auth', 'database', ...]:
    metadata_index[domain] = []
    for file in glob(f"docs/{domain}/*.md"):
        meta = extract_metadata(file)
        if meta:
            metadata_index[domain].append(meta)
```

---

## Section 4: Folder-Level README Templates

### 4.1 Generic README Template (All Folders)

```markdown
---
domain: [domain-name]
purpose: folder-navigation
version: 1.0
updated: 2024-01-15
---

# [Domain Name] Documentation

## Purpose

[2-3 sentences explaining why this folder exists, what it documents, and its role in the system]

## Quick Navigation

| Document | Purpose | Audience |
|----------|---------|----------|
| [file1.md](#file1) | [Brief description] | [Who should read] |
| [file2.md](#file2) | [Brief description] | [Who should read] |

## Folder Contents

### Reference Documents
- [document1.md](./document1.md) - [Description]
- [document2.md](./document2.md) - [Description]

### Quick References
- [quick-reference.md](./quick-reference.md) - Fast lookup guide

### Procedural Guides
- [procedure1.md](./procedure1.md) - Step-by-step instructions

## Folder Organization Rules

### When to Add New Files
- Add new file if: [criteria]
- Extend existing file if: [criteria]

### File Naming Convention
- Pattern: [PATTERN]
- Examples: [file1.md, file2.md]
- Max direct files in folder: [number] (create sub-folder if exceeded)

### Sub-Folder Patterns
- [Sub-folder name]: [Purpose and rules]

## Related Domains

- [Domain 1](../domain1/) - [Relationship]
- [Domain 2](../domain2/) - [Relationship]

## Getting Started

### For [Audience Type 1]
Start with: [recommended-file.md](./recommended-file.md)

### For [Audience Type 2]
Start with: [recommended-file.md](./recommended-file.md)

## Search Tips

**Looking for...?** → Check...
- [Use case 1] → [File/Section]
- [Use case 2] → [File/Section]
```

### 4.2 Domain-Specific README Customizations


#### API Domain README

```markdown
# API Documentation

## Purpose

This folder documents all API specifications, endpoints, authentication requirements, rate limiting policies, and error handling standards. API documentation serves as the contract between frontend/external consumers and backend services.

## Quick Navigation

| Document | Purpose |
|----------|---------|
| endpoints.md | Complete endpoint specifications with request/response schemas |
| authentication-requirements.md | Auth mechanisms per endpoint (session, JWT, API key, OAuth) |
| rate-limiting.md | Rate limits, quotas, and throttling policies |
| error-codes.md | Complete error code reference and handling |

## Folder Organization Rules

- **Add new file** if: A new endpoint category needs independent documentation (e.g., `webhooks.md`)
- **Extend existing** if: New endpoint belongs to existing category in `endpoints.md`
- **Max direct files**: 8 (create sub-folders if exceeded: `endpoints/`, `security/`, `webhooks/`)

## Related Domains

- **auth/** - Authentication mechanisms
- **database/** - Data models referenced in endpoints
- **testing/** - API testing procedures
- **deployment/** - API versioning and deprecation

## Search Tips

- **How do I call endpoint X?** → endpoints.md
- **What auth does this endpoint need?** → authentication-requirements.md
- **Why am I getting error 429?** → error-codes.md, rate-limiting.md
```

#### UI Domain README

```markdown
# UI Documentation

## Purpose

This folder documents all UI components, design system, design tokens, accessibility standards, and UI/UX guidelines. UI documentation guides designers and frontend developers on component usage, design consistency, and accessibility compliance.

## Quick Navigation

| Document | Purpose |
|----------|---------|
| design-system.md | Component library overview and usage |
| design-tokens.md | Colors, spacing, typography, shadows |
| accessibility-standards.md | WCAG compliance, ARIA conventions, keyboard navigation |
| aria-conventions.md | ARIA labeling standards and patterns |
| ui-ux-standards.md | Complete design standards and guidelines |
| accessibility-report.md | WCAG compliance audit results |

## Folder Organization Rules

- **Add new file** if: New component category or design pattern emerges
- **Extend existing** if: Variant or related pattern belongs in existing file
- **Max direct files**: 10 (create `components/` sub-folder if exceeded)

## Related Domains

- **ux/** - User workflows and interaction patterns
- **testing/** - Accessibility testing procedures
- **development/** - Frontend coding standards

## Search Tips

- **How do I use component X?** → design-system.md
- **What color should I use?** → design-tokens.md
- **How do I make form accessible?** → accessibility-standards.md
- **What's the WCAG compliance status?** → accessibility-report.md
```

#### Auth Domain README

```markdown
# Authentication & Authorization Documentation

## Purpose

This folder documents all authentication mechanisms (session, JWT, OAuth), authorization rules (RBAC), enterprise security requirements, and security best practices. Auth documentation guides developers on implementing secure authentication and role-based access control.

## Quick Navigation

| Document | Purpose |
|----------|---------|
| authentication.md | Session, JWT, OAuth implementation details |
| authorization-rbac.md | Role-based access control rules and enforcement |
| security-practices.md | Security guidelines, vulnerability handling, password policies |
| enterprise-security.md | Enterprise-specific security requirements and compliance |

## Folder Organization Rules

- **Add new file** if: New authentication mechanism or security framework introduced
- **Extend existing** if: New security practice or role definition
- **Max direct files**: 6 (keep focused and consolidated)

## Related Domains

- **api/** - Authentication requirements per endpoint
- **database/** - User credential storage
- **deployment/** - Security in production
- **business/** - Role definitions and responsibilities

## Search Tips

- **How do I implement login?** → authentication.md
- **What roles exist and permissions?** → authorization-rbac.md
- **How do I handle security incidents?** → security-practices.md
- **Enterprise SSO/compliance?** → enterprise-security.md
```

#### Database Domain README

```markdown
# Database Documentation

## Purpose

This folder documents database schema, entity relationships, migration procedures, query optimization strategies, and backup/disaster recovery procedures. Database documentation guides developers on database design, migrations, and operational concerns.

## Quick Navigation

| Document | Purpose |
|----------|---------|
| schema-overview.md | Complete database schema with ER diagrams |
| migration-procedures.md | How to create, test, deploy migrations |
| query-optimization.md | Indexing strategy, query patterns, performance |
| backup-recovery.md | Backup procedures, recovery steps, RTO/RPO |

## Folder Organization Rules

- **Add new file** if: New database system or major operational procedure
- **Extend existing** if: New table, migration, or optimization technique
- **Max direct files**: 6 (stay focused on core database concerns)

## Related Domains

- **deployment/** - Database in production environment
- **testing/** - Database seeding, fixtures, test procedures
- **performance/** - Query performance, benchmarking

## Search Tips

- **How is User table structured?** → schema-overview.md
- **How do I create a migration?** → migration-procedures.md
- **Query running slow?** → query-optimization.md
- **Disaster recovery procedure?** → backup-recovery.md
```

### 4.3 Complete README Example (Full Template)

See Section 4.4 below for a production-ready example.

---

## Section 5: Cross-Domain Reference Strategy

### 5.1 Link Format & Resolution Algorithm

**Relative Link Format**
```markdown
# Standard Cross-Domain Link
[Link Text](../other-domain/filename.md)

# Linking to specific section
[Link Text](../other-domain/filename.md#section-anchor)

# Linking within same domain
[Link Text](./filename.md)
[Link Text](./filename.md#section-anchor)
```

**Resolution Algorithm**
```
IF link is relative path:
  IF path contains "../":
    Navigate to parent directory
    Navigate to target domain
    Find target file
  ELSE IF path is "./":
    Resolve within current domain folder
  ENDIF
ELSE IF link is absolute URL:
  (Typically external, validate is not internal path)
ENDIF

IF file not found:
  WARN: Broken link during validation
ENDIF
```

### 5.2 Dependency Mapping Structure

**Complete Domain Dependency Graph**
```
┌─────────────────────────────────────────┐
│         Foundation Domains               │
├─────────────────────────────────────────┤
│ api, database, auth (no dependencies)   │
└─────────────────────────────────────────┘
          ↓ (can be referenced by)
┌─────────────────────────────────────────┐
│        Interface Domains                 │
├─────────────────────────────────────────┤
│ ui (depends on: auth, business)         │
│ ux (depends on: business)               │
└─────────────────────────────────────────┘
          ↓ (can be referenced by)
┌─────────────────────────────────────────┐
│       Execution Domains                  │
├─────────────────────────────────────────┤
│ testing (depends on: all domains)       │
│ deployment (depends on: all domains)    │
│ development (depends on: coding stand.) │
└─────────────────────────────────────────┘
          ↓ (can be referenced by)
┌─────────────────────────────────────────┐
│       Strategy Domains                   │
├─────────────────────────────────────────┤
│ business (independent)                   │
│ decisions (independent)                  │
│ performance (depends on: all domains)   │
└─────────────────────────────────────────┘
          ↓ (archived, reference only)
┌─────────────────────────────────────────┐
│        Archive Domain                    │
├─────────────────────────────────────────┤
│ archive (one-way references only)       │
└─────────────────────────────────────────┘
```

**Dependency Matrix**
```yaml
dependencies:
  api: []  # Foundation
  database: []  # Foundation
  auth: []  # Foundation
  
  ui:
    - auth
    - business
    - api  # Knows schema
  ux:
    - business
    - ui  # References UI patterns
  
  testing:
    - api
    - ui
    - ux
    - auth
    - database
    - deployment
    - business
  deployment:
    - all_domains  # Everything gets deployed
  development:
    - coding standards implicit
  
  business:
    - []  # Pure business logic, independent
  decisions:
    - []  # Strategic, references all but not required
  performance:
    - api
    - database
    - deployment
    - ui
  
  archive:
    - none  # One-way reference only
```

### 5.3 Circular Dependency Detection Pattern

**Detection Rules**
```
FOR each domain:
  references = extract_all_links(domain/)
  
  FOR each reference in references:
    referenced_domain = extract_domain(reference)
    
    IF referenced_domain references back to current_domain:
      IF path forms cycle (A→B→A):
        WARN: Circular dependency detected
        SUGGEST: Convert to "See Also" section
      ENDIF
    ENDIF
  ENDFOR
ENDFOR
```

**Prevention Strategy**
1. One-way reference rule: A references B, B should NOT reference A
2. If bidirectional reference needed: Use "See Also" section instead of inline link
3. Use metadata `related:` field instead of hard links for bidirectional relationships

**"See Also" vs Embedded Content Rules**

| Scenario | Approach | Example |
|----------|----------|---------|
| A needs to know about B but B doesn't need A | Link | api/ → auth/ (one-way) |
| A and B need to know about each other | See Also | testing/ ↔ all domains |
| Reference is supplementary, not core | See Also | decisions/ → historical context |
| Reference is required for understanding | Link | ui/ → design-tokens/ |
| Large content that could stand alone | Link to section | testing/ → test-procedures/ |
| Small reference for completeness | See Also list | api/ lists related business rules |

**"See Also" Section Format**
```markdown
## See Also

- **[Domain Name](../domain/)**  - Why this is related
- **[Related Topic](../other-domain/file.md#section)** - Context for relationship
```

### 5.4 Cross-Domain Reference Integrity Check

```yaml
# Validation Rules

validation:
  - rule: no_broken_links
    check: "All markdown links resolve to existing files/sections"
    
  - rule: no_orphaned_domains
    check: "Every domain is referenced by at least one other domain (except archive)"
    
  - rule: no_circular_hard_links
    check: "Hard links never form cycles; use See Also instead"
    
  - rule: metadata_consistency
    check: "If domain A lists B in 'related', B should acknowledge A in metadata"
    
  - rule: archive_one_way_only
    check: "Archive domain receives links only, never links out (except metadata)"
```

---

## Section 6: Quality Validation Framework

### 6.1 10+ Automated Quality Gates

**Gate 1: Folder Structure Integrity**
```
✓ Folder: docs/ exists
✓ Sub-folder: docs/[api|ui|ux|auth|database|testing|deployment|business|development|decisions|performance|archive] exists
✓ All sub-folders are in expected list (no extra folders)
✓ Each folder contains README.md
✓ No direct markdown files in docs/ root except README.md
```

**Gate 2: README Completeness**
```
✓ README.md exists in every folder
✓ README frontmatter contains: domain, purpose, version, updated
✓ README body contains: Purpose, Quick Navigation, Folder Contents, Organization Rules
✓ README contains Related Domains section
✓ README contains Getting Started or Search Tips
```

**Gate 3: Metadata Completeness**
```
✓ All non-README files have YAML frontmatter
✓ Frontmatter contains: domain, purpose, version, updated, owner
✓ domain field matches file's parent folder name
✓ purpose field is from approved list (endpoint-specs, design-system, etc)
✓ version follows semver format (X.Y.Z)
✓ updated date is ISO 8601 format (YYYY-MM-DD)
✓ archived files contain: archived=true, archived_date, archive_reason, archive_category
```

**Gate 4: Link Validity**
```
✓ All markdown links to .md files resolve to existing files
✓ All internal links use relative paths (../domain/file.md format)
✓ No broken anchors (#section references)
✓ Links use forward slashes on all platforms (Windows, Mac, Linux compatibility)
✓ No external URLs except whitelisted domains (if applicable)
```

**Gate 5: No Circular Dependencies**
```
✓ No hard links form A→B→A cycles
✓ "See Also" sections exist instead of circular hard links
✓ Archive domain receives links only (no outgoing links)
✓ Foundation domains (api, database, auth) not linked by themselves
```

**Gate 6: File Organization Rules**
```
✓ No domain folder exceeds 15 direct files (excludes sub-folders and README)
✓ Files use domain-appropriate naming convention (lowercase, hyphens)
✓ File naming follows PURPOSE[-VARIANT].md pattern
✓ Decision folder files use NUMBER-TITLE.md format (ADR convention)
✓ Archive files prefixed or named clearly as historical
```

**Gate 7: Naming Consistency**
```
✓ All folder names: lowercase, hyphenated (if multi-word)
✓ All file names: lowercase, hyphenated, .md extension
✓ No spaces in file/folder names
✓ Decision folder files numbered sequentially (0-*, 1-*, etc)
```

**Gate 8: Metadata Consistency**
```
✓ If domain A has "related: [B]", B should have A in "related" field (bidirectional)
✓ If domain A has "dependencies: [B]", B's metadata should note A depends on it
✓ Status field (if present) is one of: [stable|frequently-updated|experimental]
✓ Archived documents marked consistently (archived: true in all archive files)
✓ Owner field matches known team list (or populated from standard list)
```

**Gate 9: Content Consistency & Terminology**
```
✓ Terminology is consistent across all domains (no API spelled multiple ways)
✓ Abbreviations defined on first use in each document
✓ Cross-domain terminology uses consistent definitions
✓ Archive files don't contradict current documentation
✓ UI copy matches AGENTS.md guidelines (Indonesian, short, actionable)
```

**Gate 10: Archive Integrity**
```
✓ Archived files in docs/archive/ only
✓ All archived files have frontmatter: archived=true, archived_date, archive_reason
✓ Archive index (archive/README.md) lists all archived files
✓ No active (non-archived) documentation links to archive (except ADRs)
✓ Archive folder has retrieval guidance in README
```

**Gate 11: Domain Coverage**
```
✓ Root docs/README.md links to all 12 domain folders
✓ Every domain has at least 1 primary document (beyond README)
✓ No domain is completely empty
✓ Domain READMEs reference all content within domain
```

**Gate 12: Root Navigation Completeness**
```
✓ docs/README.md contains high-level overview (2-3 sentences per domain)
✓ Folder tree or ASCII diagram included
✓ Quick navigation links to each domain's README
✓ Search tips matrix (use case → recommended folder)
✓ Update frequency noted for each domain
```

### 6.2 Validation Execution

**Manual Validation Checklist**
```markdown
- [ ] Gate 1: Folder structure (12 domains, no extras)
- [ ] Gate 2: README completeness (all folders have proper README)
- [ ] Gate 3: Metadata completeness (all files have frontmatter)
- [ ] Gate 4: Link validity (no broken links)
- [ ] Gate 5: No circular dependencies
- [ ] Gate 6: File organization (max 15 files per folder)
- [ ] Gate 7: Naming consistency
- [ ] Gate 8: Metadata consistency (related fields bidirectional)
- [ ] Gate 9: Terminology consistency
- [ ] Gate 10: Archive integrity
- [ ] Gate 11: Domain coverage
- [ ] Gate 12: Root navigation complete
```

**Automated Validation Script (Pseudocode)**
```bash
#!/bin/bash
# validation.sh

validate_folders() {
  required_folders=("api" "ui" "ux" "auth" "database" "testing" "deployment" "business" "development" "decisions" "performance" "archive")
  
  for folder in "${required_folders[@]}"; do
    [[ -d "docs/$folder" ]] && echo "✓ docs/$folder exists" || echo "✗ MISSING docs/$folder"
    [[ -f "docs/$folder/README.md" ]] && echo "✓ docs/$folder/README.md exists" || echo "✗ MISSING docs/$folder/README.md"
  done
}

validate_metadata() {
  for file in $(find docs -name "*.md" ! -path "*/README.md"); do
    if grep -q "^---$" "$file"; then
      echo "✓ $file has frontmatter"
    else
      echo "✗ $file missing frontmatter"
    fi
  done
}

validate_links() {
  for file in $(find docs -name "*.md"); do
    grep -o '\[.*\](.*\.md)' "$file" | while read -r link; do
      target=$(echo "$link" | sed 's/.*(\(.*\.md\).*/\1/')
      # Resolve relative path and check if file exists
      [[ -f "$target" ]] && echo "✓ Link valid" || echo "✗ BROKEN: $target"
    done
  done
}

validate_folders
validate_metadata
validate_links
```

---

## Section 7: Migration Execution Pipeline

### 7.1 Phase-by-Phase Migration Sequence (5 Phases)

**Phase 1: Preparation & Validation (Duration: 2-3 days)**
```
Step 1.1: Audit existing 40+ documentation files
  - List all files in docs/ and docs/DECISIONS/
  - Categorize by size, complexity, relationships
  - Identify consolidation opportunities
  - Flag outdated/archived content

Step 1.2: Finalize folder structure
  - Create folder naming and organization blueprint
  - Define metadata template (YAML frontmatter)
  - Create README templates per domain

Step 1.3: Create git branch for migration
  - Branch: docs/restructure-modular-v1
  - Preserve complete history
  - Prepare for easy rollback

Step 1.4: Checkpoint Gate 1
  ✓ All existing files audited and categorized
  ✓ Folder structure approved
  ✓ Metadata template finalized
  ✓ README templates created
```

**Phase 2: Folder Creation & Template Setup (Duration: 1 day)**
```
Step 2.1: Create all 12 domain folders
  - mkdir docs/api
  - mkdir docs/ui
  - [... create all 12 folders]

Step 2.2: Create domain-specific README.md files
  - Copy generic template and customize for each domain
  - Add Quick Navigation tables
  - Add domain-specific folder rules

Step 2.3: Create root docs/README.md
  - Add high-level overview (2-3 sentences per domain)
  - Add ASCII folder tree
  - Add Quick Navigation links
  - Add Search Tips matrix

Step 2.4: Checkpoint Gate 2
  ✓ All 12 folders exist
  ✓ All README.md files created with proper structure
  ✓ Root README.md complete with navigation
```

**Phase 3: File Migration (Duration: 3-5 days)**
```
Step 3.1: Migrate foundation domain files (api, database, auth)
  - Move API.md → docs/api/endpoints.md
  - Move DATABASE.md → docs/database/schema-overview.md
  - Move AUTH.md → docs/auth/authentication.md
  - Add YAML frontmatter to all

Step 3.2: Consolidate related files
  - Consolidate accessibility testing files → docs/testing/accessibility-test-results.md
  - Consolidate UI/UX standards → docs/ui/ui-ux-standards.md
  - Archive Wave 1-3 completion checklists → docs/archive/wave-*-completion.md

Step 3.3: Archive historical documentation
  - Move implementation phase summaries to docs/archive/
  - Move wave completion checklists to docs/archive/
  - Move task tracking summaries to docs/archive/
  - Add archive metadata to all archived files

Step 3.4: Migrate remaining domain files
  - Move testing documentation → docs/testing/
  - Move deployment documentation → docs/deployment/
  - Move business documentation → docs/business/
  - [... continue for all domains]

Step 3.5: Migrate decision records
  - Keep DECISIONS/* structure
  - Move to docs/decisions/
  - Renumber sequentially (0-architecture, 1-auth, etc)
  - Add/update YAML frontmatter

Step 3.6: Checkpoint Gate 3
  ✓ All 40+ files migrated to correct domains
  ✓ All files have YAML frontmatter
  ✓ Consolidations completed
  ✓ All archived files marked with metadata
```

**Phase 4: Link Correction & Validation (Duration: 2-3 days)**
```
Step 4.1: Update internal link references
  - Search all .md files for broken links
  - Fix cross-domain relative paths (../domain/file.md)
  - Update links in new locations
  - Verify section anchors (#heading) still valid

Step 4.2: Validate all gates
  - Gate 1: Folder structure (PASS if all 12 exist)
  - Gate 2: README completeness (PASS if all folders have proper README)
  - Gate 3: Metadata completeness (PASS if all files have frontmatter)
  - Gate 4: Link validity (PASS if no broken links)
  - Gate 5: No circular dependencies (PASS if no cycles)
  - [... validate all 12 gates]

Step 4.3: Resolve validation failures
  - Fix any broken links
  - Add missing metadata
  - Resolve circular dependencies

Step 4.4: Checkpoint Gate 4
  ✓ All 12 validation gates PASS
  ✓ No broken links
  ✓ No circular dependencies
```

**Phase 5: Documentation & Finalization (Duration: 1-2 days)**
```
Step 5.1: Update code references
  - Search codebase for doc paths
  - Update any hardcoded references to old docs/ structure
  - Update documentation links in code comments

Step 5.2: Create migration summary
  - Document what was moved/consolidated/archived
  - Note any deprecations or breaking changes
  - Provide mapping table for reference

Step 5.3: Update project documentation
  - Update CONTRIBUTING.md if it references docs/
  - Update README.md links to documentation
  - Update any CI/CD scripts that build docs

Step 5.4: Merge migration branch
  - Create PR with all changes
  - Final validation of migration
  - Merge to main branch

Step 5.5: Final Checkpoint Gate 5
  ✓ All code references updated
  ✓ Migration PR merged
  ✓ All validation gates still PASS
  ✓ Repository ready for new structure
```

### 7.2 Checkpoints & Verification Gates

```yaml
# Phase 1 Gate
checkpoint_1_preparation_complete:
  condition: "All existing files audited and folder structure approved"
  verification:
    - audit_list_complete: true
    - folder_structure_approved: true
    - metadata_template_finalized: true
  gate_pass: "Proceed to Phase 2"
  gate_fail: "Review and adjust folder structure, return to Step 1.2"

# Phase 2 Gate
checkpoint_2_structure_created:
  condition: "All 12 folders exist with proper README files"
  verification:
    - folders_exist: 12
    - readme_files_created: 12
    - root_readme_complete: true
  gate_pass: "Proceed to Phase 3"
  gate_fail: "Create missing folders/READMEs before proceeding"

# Phase 3 Gate
checkpoint_3_files_migrated:
  condition: "All 40+ files migrated, consolidated, or archived"
  verification:
    - files_migrated: 40+
    - consolidations_complete: true
    - archive_metadata_complete: true
  gate_pass: "Proceed to Phase 4"
  gate_fail: "Complete remaining migrations before proceeding"

# Phase 4 Gate
checkpoint_4_validation_passed:
  condition: "All 12 validation gates PASS"
  verification:
    - gate_1_folder_structure: PASS
    - gate_2_readme_completeness: PASS
    - gate_3_metadata_completeness: PASS
    - gate_4_link_validity: PASS
    - gate_5_no_circular_deps: PASS
    - gate_6_file_organization: PASS
    - gate_7_naming_consistency: PASS
    - gate_8_metadata_consistency: PASS
    - gate_9_terminology_consistency: PASS
    - gate_10_archive_integrity: PASS
    - gate_11_domain_coverage: PASS
    - gate_12_root_navigation: PASS
  gate_pass: "Proceed to Phase 5"
  gate_fail: "Fix validation failures and re-run gates"

# Phase 5 Gate
checkpoint_5_finalization_complete:
  condition: "Migration complete, merged, all gates still passing"
  verification:
    - code_references_updated: true
    - migration_pr_merged: true
    - validation_gates_final_pass: true
  gate_pass: "Migration SUCCESS - New structure live"
  gate_fail: "Address failures before considering migration complete"
```

### 7.3 Rollback Strategy

**Rollback Plan**
```
IF any checkpoint gate FAILS:
  Step 1: Do NOT merge changes to main branch
  Step 2: Delete the docs/restructure-modular-v1 branch
  Step 3: Keep branch available for review if needed
  Step 4: Git checkout main (original structure restored)
  Step 5: Address root cause
  Step 6: Create new branch and restart migration
ENDIF

Rolling back from merged state:
  git revert [merge-commit] --no-edit
  OR
  git reset --hard [commit-before-merge]
  → Original structure restored
```

### 7.4 Git Preservation Strategy

```bash
# Branch naming convention
migration_branch="docs/restructure-modular-v1"

# Commit strategy
git add docs/[domain]/README.md
git commit -m "docs: create [domain] folder structure"

# Preserve history
git branch -m $migration_branch  # Keep branch after merge
git log --follow docs/[old-path]/file.md  # Track file history
```

---

## Section 8: AI/Tool Accessibility Design

### 8.1 Metadata Extraction Patterns

**Pattern 1: Domain-Based Indexing**
```python
import yaml
import os
from pathlib import Path

def build_domain_index():
    """Build searchable index by domain"""
    index = {}
    
    for domain_folder in Path('docs').glob('*'):
        if domain_folder.is_dir() and domain_folder.name != 'archive':
            index[domain_folder.name] = []
            
            for md_file in domain_folder.glob('*.md'):
                metadata = extract_metadata(md_file)
                index[domain_folder.name].append({
                    'file': md_file.name,
                    'path': str(md_file),
                    'metadata': metadata
                })
    
    return index

# Output: {
#   'api': [
#     {'file': 'endpoints.md', 'path': 'docs/api/endpoints.md', 
#      'metadata': {'domain': 'api', 'purpose': 'endpoint-specs', ...}},
#     {'file': 'authentication-requirements.md', ...}
#   ],
#   'ui': [...],
#   ...
# }
```

**Pattern 2: Cross-Domain Reference Graph**
```python
def build_reference_graph():
    """Build graph of cross-domain references"""
    graph = defaultdict(list)
    
    for md_file in Path('docs').rglob('*.md'):
        if 'archive' in md_file.parts:
            continue
            
        source_domain = md_file.parent.name
        references = extract_cross_domain_links(md_file)
        
        for target_domain in references:
            graph[source_domain].append(target_domain)
    
    return dict(graph)

# Output: {
#   'api': ['auth', 'database'],
#   'ui': ['auth', 'business'],
#   'testing': ['api', 'ui', 'auth', 'database', ...],
#   ...
# }
```

**Pattern 3: Metadata Query for AI Tools**
```python
def query_docs_by_criteria(domain=None, purpose=None, status=None, quick_ref=False):
    """Query documentation by various criteria"""
    results = []
    
    for md_file in Path('docs').rglob('*.md'):
        if 'README' in md_file.name or 'archive' in md_file.parts:
            continue
            
        metadata = extract_metadata(md_file)
        
        # Apply filters
        if domain and metadata.get('domain') != domain:
            continue
        if purpose and metadata.get('purpose') != purpose:
            continue
        if status and metadata.get('status') != status:
            continue
        if quick_ref and not metadata.get('quick_reference', False):
            continue
            
        results.append({'file': md_file, 'metadata': metadata})
    
    return results

# Examples:
# query_docs_by_criteria(domain='api')
# query_docs_by_criteria(quick_ref=True)
# query_docs_by_criteria(status='frequently-updated')
```

### 8.2 Index Generation Structure

**Searchable Index Format**
```json
{
  "version": "1.0",
  "generated_at": "2024-01-15T10:30:00Z",
  "total_documents": 45,
  "domains": {
    "api": {
      "count": 5,
      "documents": [
        {
          "id": "api-endpoints-1",
          "file": "endpoints.md",
          "path": "docs/api/endpoints.md",
          "title": "API Endpoints",
          "purpose": "endpoint-specs",
          "owner": "platform-team",
          "updated": "2024-01-15",
          "status": "stable",
          "tags": ["rest", "endpoints", "schema"]
        }
      ],
      "related_domains": ["auth", "database"]
    },
    "ui": {
      "count": 7,
      "documents": [...]
    }
  },
  "cross_domain_references": {
    "api": ["auth", "database"],
    "ui": ["auth", "business"],
    ...
  },
  "quick_references": [
    {"file": "ux/quick-reference.md", "purpose": "quick-reference"},
    ...
  ]
}
```

### 8.3 Searchability & Discoverability Features

**Feature 1: Full-Text Search Integration**
```yaml
search_fields:
  - frontmatter.domain
  - frontmatter.purpose
  - frontmatter.tags
  - frontmatter.related
  - heading_1  # Document title
  - heading_2  # Section headers
  - body_text  # Paragraph text

search_examples:
  - Query: "API authentication" → Returns api/authentication-requirements.md, auth/authentication.md
  - Query: "accessibility" → Returns ui/accessibility-standards.md, testing/accessibility-testing.md
  - Query: "deployment" → Returns deployment/deployment-pipeline.md
```

**Feature 2: "Getting Started" Guide by Role**
```
Developer Role:
├─ Setup Guide (development/local-development-setup.md)
├─ Coding Standards (development/coding-standards.md)
├─ Quick References (ux/quick-reference.md, api/)
└─ Testing (testing/testing-framework.md)

Designer Role:
├─ Design System (ui/design-system.md)
├─ Design Tokens (ui/design-tokens.md)
├─ UI/UX Standards (ui/ui-ux-standards.md)
└─ Accessibility (ui/accessibility-standards.md)

DevOps Role:
├─ Deployment Pipeline (deployment/deployment-pipeline.md)
├─ Environment Config (deployment/environment-configuration.md)
├─ Monitoring (deployment/monitoring-alerting.md)
└─ Rollback Procedures (deployment/rollback-procedures.md)
```

**Feature 3: "How Do I..." Search Tips Matrix**
```yaml
search_tips:
  - "How do I add an API endpoint?"
    → Start with: api/endpoints.md
    → Related: api/authentication-requirements.md, testing/testing-framework.md
    
  - "How do I make the UI accessible?"
    → Start with: ui/accessibility-standards.md
    → Related: ui/design-system.md, testing/accessibility-testing.md
    
  - "How do I deploy to production?"
    → Start with: deployment/deployment-pipeline.md
    → Related: deployment/environment-configuration.md, deployment/rollback-procedures.md
    
  - "How do I implement a new role?"
    → Start with: business/role-workflow-specification.md
    → Related: auth/authorization-rbac.md, development/future-features-guide.md
```

---

## Section 9: Correctness Properties (15-20 Properties)

### 9.1 Core Properties

**Property 1: Folder Hierarchy Enforced**
```
∀ file ∈ docs/:
  file.parent_folder ∈ {api, ui, ux, auth, database, testing, deployment, business, development, decisions, performance, archive}
  OR file.name == README.md (root only)
```

**Property 2: No Orphaned Files**
```
∀ file ∈ docs/:
  file.path matches pattern docs/[domain]/[filename].md
  AND file.domain_folder is NOT empty
  AND file is referenced OR file.name == README.md (valid exception)
```

**Property 3: Metadata Completeness (Non-Archive)**
```
∀ file ∈ docs/ WHERE file NOT IN archive/ AND file.name != README.md:
  file.frontmatter.domain != null
  AND file.frontmatter.purpose != null
  AND file.frontmatter.version != null
  AND file.frontmatter.updated != null
  AND file.frontmatter.owner != null
```

**Property 4: Archive Marking Consistency**
```
∀ file ∈ docs/archive/:
  file.frontmatter.archived == true
  AND file.frontmatter.archived_date != null
  AND file.frontmatter.archive_reason != null

∀ file NOT ∈ docs/archive/:
  file.frontmatter.archived != true  OR file.frontmatter.archived == false
```

**Property 5: Link Validity**
```
∀ link ∈ all_markdown_links():
  IF link.is_internal_reference():
    target_file = resolve_link(link)
    target_file.exists() == true
    OR link.is_in_archived_context() (allowed only in ADRs)
```

**Property 6: Zero Information Loss**
```
information_in_new_structure >= information_in_old_structure
∧ (consolidations preserve all content in merged files)
∧ (archived files preserve historical content)
```

**Property 7: No Circular Hard Links**
```
∀ pair(domain_a, domain_b):
  IF domain_a → domain_b (link exists):
    domain_b ↛ domain_a (no back link)
    OR they use "See Also" section instead
```

**Property 8: Domain Separation Maintained**
```
∀ domain_X:
  files_in_domain_X.count ≤ 15  (direct files, excludes sub-folders)
  AND (files_in_domain_X.count > 15) ⟹ (sub-folder created with name domain_X-subcategory)
```

**Property 9: Root Navigation Coverage**
```
docs/README.md contains:
  - reference_to_all_12_domains == true
  - quick_navigation_links_complete == true
  - search_tips_matrix_complete == true
  - overview_for_each_domain == true
```

**Property 10: File Naming Convention Compliance**
```
∀ file ∈ docs/:
  IF file.domain == decisions:
    file.name matches ^\d+-.*\.md$ (e.g., 0-architecture.md)
  ELSE:
    file.name matches ^[a-z0-9-]+\.md$ (lowercase, hyphens only)
  END
```

### 9.2 Cross-Domain Properties

**Property 11: Bidirectional Consistency**
```
∀ file ∈ docs/ WHERE file.frontmatter.related != null:
  ∀ related_domain ∈ file.frontmatter.related:
    ∃ file2 ∈ related_domain/:
      file.domain ∈ file2.frontmatter.related
      (metadata relationship is bidirectional)
```

**Property 12: Dependency Tree Valid**
```
∀ domain_A:
  domain_A.dependencies ⊆ valid_domain_list
  AND NOT (∃ path: domain_A → domain_B → ... → domain_A)  # no cycles
```

**Property 13: Archive One-Way Only**
```
∀ file ∈ docs/archive/:
  count(incoming_links_to_archive_file) > 0  (archive file is referenced)
  AND count(outgoing_links_from_archive_file) == 0  (archive links out to nothing)
  EXCEPT: ADRs may reference archive for historical context
```

**Property 14: Metadata Consistency Across Domains**
```
∀ pair(domain_A, domain_B) where domain_A.related includes domain_B:
  IF domain_A.owner != domain_B.owner:
    explicit_note_in_README == true  (clarify ownership boundary)
```

**Property 15: Domain Coverage**
```
all_12_domains_populated == true
AND ∀ domain:
  domain.document_count >= 1  (every domain has at least one doc beyond README)
  AND domain.README.exists() == true
```

### 9.3 Quality & Validation Properties

**Property 16: Validation Gate Completeness**
```
validation_gates_automated >= 10
AND validation_gates_pass == true
AND last_validation_date >= current_date - 1_day  (freshly validated)
```

**Property 17: Terminology Consistency**
```
∀ term ∈ critical_terminology:
  count(definitions_of_term) == 1  (single definition across all docs)
  OR count(definitions_of_term) == domain_count
    (term defined separately per domain is acceptable if intentional)
```

**Property 18: No Duplicate Content**
```
∀ pair(file_A, file_B):
  IF file_A != file_B AND same_domain:
    content_similarity < 80%  (no significant duplication)
  ELSE IF different_domains:
    content_similarity < 30%  (cross-domain duplication rare, allowed with note)
```

**Property 19: Archive Completeness**
```
all_historical_files_preserved == true
AND all_archived_files_have_metadata == true
AND archive_index_updated == true
AND count(active_docs_linking_to_archive) == 0
  (except ADRs for historical context)
```

**Property 20: Git History Preservation**
```
git log --follow:
  ∀ migrated_file:
    file_history_preserved == true  (git can trace file through restructuring)
    AND can_git_blame_original_commits == true
```

---

## Summary & Key Deliverables

This technical design specification provides a **production-ready blueprint** for restructuring documentation with:

### Core Deliverables
1. **12-Domain Modular Architecture** - Clear folder structure with ownership
2. **Complete File Migration Matrix** - 40+ files mapped to new locations with consolidation logic
3. **Machine-Readable Metadata** - YAML frontmatter for AI/tool accessibility
4. **Domain README Templates** - Customizable templates for each domain folder
5. **Cross-Domain Reference Strategy** - Link format, dependency graphs, circular detection
6. **Quality Validation Framework** - 12+ automated gates for consistency
7. **5-Phase Migration Pipeline** - Detailed execution with checkpoints and rollback
8. **AI/Tool Accessibility Design** - Metadata extraction, indexing, searchability
9. **20 Correctness Properties** - Formal specifications for verification
10. **Complete Implementation Checklist** - Ready for immediate execution

### Implementation Timeline
- **Phase 1 (Preparation)**: 2-3 days
- **Phase 2 (Folder Creation)**: 1 day
- **Phase 3 (File Migration)**: 3-5 days
- **Phase 4 (Link & Validation)**: 2-3 days
- **Phase 5 (Finalization)**: 1-2 days
- **Total**: 5-7 working days

### Quality Metrics
- ✅ 12 validation gates (all must pass)
- ✅ Zero information loss guaranteed
- ✅ Git history preserved for all files
- ✅ No broken links or orphaned files
- ✅ Bidirectional metadata consistency
- ✅ Circular dependency elimination
- ✅ Terminology consistency enforced

See **design-reference.md** for implementation examples, quick reference matrices, and detailed execution checklists.

