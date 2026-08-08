# Requirements: Documentation Restructuring (Modular Structure C)

## Introduction

Repository ini memiliki 40+ dokumentasi yang tersebar tanpa struktur terorganisir. Dokumentasi perlu direstruktur dari kategori-based (ARCHITECTURE, API, AUTH, dll) menjadi **modular/domain-based structure** dimana setiap folder merepresentasikan domain atau module spesifik dengan ownership yang jelas.

Tujuan: Membuat dokumentasi yang scalable, maintainable, mudah dinavigasi, dan accessible untuk AI/tools via metadata terstruktur.

## Glossary

- **Domain/Module**: Area fungsional tertentu (api, ui, auth, database, dll)
- **Modular Structure C**: Domain-based organization dimana setiap folder independently mendokumentasikan satu aspek system
- **README.md (Folder-level)**: File di setiap folder yang menjelaskan purpose, contents, dan quick navigation
- **Cross-Domain Reference**: Link/referensi antar folder untuk menghindari duplikasi
- **Metadata**: Structured information di header dokumen untuk machine readability (frontmatter YAML)
- **Quick Reference**: Short-form documentation untuk akses cepat developer
- **Archive**: Historical atau outdated documentation disimpan untuk audit trail

## Modular Folder Structure

### Folder Blueprint (12-15 Domains)

```
docs/
├── api/                  # API Specification & Endpoints
├── ui/                   # UI Components & Design Standards
├── ux/                   # UX Patterns, Workflows, User Journeys
├── auth/                 # Authentication, Authorization, Security
├── database/             # Database Schema, Migrations, Queries
├── testing/              # Testing Guidelines, Procedures, Checklists
├── deployment/           # Deployment, DevOps, Infrastructure
├── business/             # Product Rules, Business Logic, Workflows
├── development/          # Setup, Standards, Tools, Conventions
├── decisions/            # Architecture Decision Records (ADRs)
├── performance/          # Performance Optimization, Benchmarks
├── archive/              # Historical, Outdated, Phase Documentation
└── README.md             # Root navigation & overview
```

## Requirements

### Requirement 1: Modular Folder Structure Implementation

**User Story:** As a developer, I want documentation organized by domain/module, so that I can find relevant information quickly and maintain ownership per area.

#### Acceptance Criteria

1. WHEN navigating to `docs/` directory, THE repository SHALL contain 12+ modular folders, each representing one primary domain
2. WHERE each folder is a domain (api, ui, auth, database, etc), THE folder SHALL contain:
   - README.md with purpose, contents list, and quick navigation
   - 2-5 primary documentation files specific to that domain
   - Sub-folders if domain complexity requires (e.g., `ui/components/`, `ui/tokens/`)
3. WHILE organizing domains, THE folder names SHALL use lowercase, hyphenated naming convention (e.g., `ui-components/`, `database-migrations/`)
4. THE repository root `docs/README.md` SHALL provide overview of all 12+ domains with quick links and navigation tree

### Requirement 2: File Mapping from Existing to Modular Structure

**User Story:** As a project maintainer, I want a clear mapping of existing 40+ documents to new modular structure, so that no documentation is lost and migration is traceable.

#### Acceptance Criteria

1. WHEN reviewing file mapping, THE documentation SHALL include a comprehensive table mapping 40+ existing files to their new location:
   - Source file path (docs/*.md, docs/DECISIONS/*)
   - Target folder & file name
   - Migration strategy (move, consolidate, archive, split)
   - Owner/domain responsible
2. WHERE multiple related files can be consolidated, THE migration strategy SHALL indicate consolidation with clear section mapping
3. WHERE outdated/historical files exist, THE strategy SHALL indicate archiving to `docs/archive/` with date and reason
4. WHERE files need splitting (e.g., large API.md), THE strategy SHALL indicate which sections go to which folders

### Requirement 3: Folder-Level Organization Rules

**User Story:** As a documentation contributor, I want clear rules for how each folder is organized internally, so that I maintain consistency across all domains.

#### Acceptance Criteria

1. EVERY folder in `docs/` SHALL contain a README.md following this structure:
   - **Purpose**: 2-3 sentences explaining folder purpose
   - **Contents**: Bulleted list of all files/sections in folder
   - **Quick Navigation**: Links to key documents
   - **Folder Rules**: Organization rules specific to this domain (if applicable)
2. WHERE a folder contains many files (5+), THE README.md SHALL include a table of contents with sections
3. WHERE sub-folders exist within a domain, THE parent README.md SHALL document sub-folder structure and purpose
4. THE naming convention for files within a folder SHALL be: `PURPOSE-VARIANT.md` or `PURPOSE.md` (e.g., `api-endpoints.md`, `api-authentication.md`)
5. WHILE maintaining file organization, THE folder SHALL not exceed 15 direct files (consolidate or create sub-folders if needed)

### Requirement 4: Domain-Specific Documentation Content

**User Story:** As a developer reading domain documentation, I want comprehensive guidance specific to each domain, so that I understand both "what" and "how" for that area.

#### Acceptance Criteria

1. THE `api/` folder SHALL document:
   - API endpoint specifications with request/response schemas
   - Authentication requirements per endpoint
   - Rate limiting, versioning, deprecation policy
   - Error handling standards and codes
2. THE `ui/` folder SHALL document:
   - Component library or UI patterns used
   - Design tokens (colors, spacing, typography)
   - Accessibility compliance (WCAG guidelines, ARIA conventions)
   - Responsive design breakpoints and mobile optimization
3. THE `ux/` folder SHALL document:
   - User workflows and journey maps
   - Interaction patterns and conventions
   - Form design standards and validation patterns
   - Navigation structure and information architecture
4. THE `auth/` folder SHALL document:
   - Authentication mechanisms (session, JWT, OAuth, etc)
   - Authorization rules and role-based access control (RBAC)
   - Security best practices and vulnerability handling
   - Password policy, MFA, and credential management
5. THE `database/` folder SHALL document:
   - Database schema with entity relationships
   - Migration procedures and versioning
   - Indexing strategy and query optimization
   - Backup and disaster recovery procedures
6. THE `testing/` folder SHALL document:
   - Testing framework setup and configuration
   - Unit, integration, e2e testing guidelines
   - Test coverage targets and quality gates
   - Test data management and fixtures
7. THE `deployment/` folder SHALL document:
   - Deployment pipeline and CI/CD workflow
   - Environment configuration (dev, staging, production)
   - Monitoring, logging, and alerting setup
   - Rollback procedures and incident response
8. THE `business/` folder SHALL document:
   - Product specifications and feature definitions
   - Business rules and domain logic
   - User roles and their responsibilities
   - Compliance requirements and audit trails
9. THE `development/` folder SHALL document:
   - Local development environment setup
   - Build and test commands
   - Code style guides and linting rules
   - Git workflow and commit conventions
10. THE `decisions/` folder SHALL contain Architecture Decision Records (ADRs) documenting:
    - Decision title and date
    - Context and problem statement
    - Options considered and trade-offs
    - Decision and consequences
11. THE `performance/` folder SHALL document:
    - Performance targets and SLAs
    - Profiling and benchmarking procedures
    - Caching strategies and optimization techniques
    - Monitoring and performance metrics
12. THE `archive/` folder SHALL contain outdated or historical documentation:
    - Labeled with archive date and deprecation reason
    - Organized by phase or project milestone
    - Not linked from current documentation (except in ADRs for historical context)

### Requirement 5: Cross-Domain Referencing Strategy

**User Story:** As a documentation author, I want clear rules for linking across domains, so that documentation stays DRY and circular dependencies are prevented.

#### Acceptance Criteria

1. WHEN a document needs to reference content from another domain, THE document SHALL use relative markdown links: `[Link Text](../other-domain/file.md)`
2. WHERE reference is one-way (A references B but B doesn't reference A), THE reference SHALL be a standard link in the text
3. WHERE reference is bidirectional or creates circular risk, THE documents SHALL instead cross-reference via a "See Also" section that lists related documents
4. THE repository SHALL maintain a cross-domain dependency map (in `docs/README.md` or `docs/ARCHITECTURE.md`) showing which domains depend on others
5. WHILE linking across domains, documents SHALL avoid embedding full content from other domains; instead link with brief context

### Requirement 6: Metadata and Machine-Readable Format

**User Story:** As an AI tool or script, I want structured metadata in documentation, so that I can parse, index, and link documents programmatically.

#### Acceptance Criteria

1. EVERY documentation file (except READMEs and archives) SHALL include a YAML frontmatter header with metadata:
   ```yaml
   ---
   domain: api              # Which domain folder
   purpose: endpoint-specs  # Document purpose/type
   version: 1.0            # Documentation version
   updated: YYYY-MM-DD     # Last update date
   owner: platform-team    # Team responsible
   related: [auth, database]  # Related domains
   ---
   ```
2. THE frontmatter SHALL be machine-parseable (valid YAML)
3. WHEN a document is archived, THE frontmatter SHALL include: `archived: true`, `archived_date: YYYY-MM-DD`, `archive_reason: [brief reason]`
4. WHERE a document is a quick reference, THE frontmatter SHALL include: `quick_reference: true` to flag for indexing

### Requirement 7: File Mapping Table (40+ Existing Files)

**User Story:** As a project lead, I want a complete, detailed mapping of existing documentation to new structure, so that migration can be executed without loss of information.

#### Acceptance Criteria

1. THE requirements document SHALL include a comprehensive table with columns:
   - Existing File (source in docs/)
   - Target Folder (new domain)
   - Target File (new filename)
   - Migration Strategy (move/consolidate/archive/split)
   - Notes (e.g., which sections, merge details)
2. THE table SHALL cover ALL 40+ existing documentation files found in `docs/` root and `docs/DECISIONS/`
3. WHERE files are consolidated (merged), THE notes SHALL specify:
   - Which files are merged
   - Which sections become separate files if needed
   - Consolidation rationale
4. WHERE files are split (e.g., large API.md), THE notes SHALL indicate:
   - Which sections become separate files
   - New folder structure
   - Splitting rationale
5. THE table SHALL indicate which files should be moved to archive/ with deprecation reason

### Requirement 8: Folder Organization Principles

**User Story:** As a documentation maintainer, I want documented principles for how each folder stays organized over time, so that structure doesn't degrade as new docs are added.

#### Acceptance Criteria

1. EACH folder README.md SHALL include "Folder Rules" section documenting:
   - When to add new files vs. sections in existing files
   - File naming conventions specific to that domain
   - Maximum number of direct files (before creating sub-folders)
   - When to create sub-folders and what pattern to use
2. WHERE a domain grows large (10+ files), THE folder SHALL have sub-folders with their own READMEs
3. THE repository root `docs/README.md` SHALL document global principles:
   - Consistency checks across folders
   - When to create vs. update files
   - Cross-domain linking principles
   - Archival policy and timeline
4. WHEN adding new documentation, contributors SHALL check domain README first for folder-specific rules

### Requirement 9: Root Navigation and Discovery

**User Story:** As a new developer, I want to find documentation quickly from a single entry point, so that onboarding is smooth and I understand the full documentation structure.

#### Acceptance Criteria

1. THE `docs/README.md` SHALL provide:
   - High-level overview of all 12+ domains (1-2 sentence description each)
   - Visual folder tree or ASCII diagram of structure
   - Quick navigation links to each domain's README
   - Search tips for common use cases (e.g., "How do I add an API endpoint?" → `api/` folder)
2. WHEN clicking on a domain link, THE developer SHALL reach that domain's README.md with clear next steps
3. THE root README SHALL include a matrix table showing:
   - Domain name and purpose
   - Key files in domain
   - Owner/team responsible
   - Update frequency (stable, frequently updated, experimental)
4. WHERE documentation types vary (conceptual, procedural, reference), THE root README SHALL note which folders contain which types

### Requirement 10: Existing Document Analysis & Migration Strategy

**User Story:** As a migration executor, I want clear analysis of what existing files contain and how they fit into new structure, so that consolidation/splitting decisions are informed and validated.

#### Acceptance Criteria

1. WHEN reviewing existing 40+ files, THE requirements SHALL include brief analysis of each file:
   - Current location and filename
   - Content summary (1-2 sentences)
   - Size/line count
   - Relationship to other files
   - Recommended target domain
2. WHERE files can be merged (e.g., multiple UI_UX_* files), THE analysis SHALL indicate:
   - Files to consolidate
   - How sections will be organized in target
   - Rationale for consolidation
3. WHERE files are purely historical/outdated, THE analysis SHALL recommend archiving with reason
4. WHERE large files should be split, THE analysis SHALL suggest split boundaries and new file names

### Requirement 11: Quality Gates and Validation Rules

**User Story:** As a quality lead, I want validation rules for the modular structure, so that documentation stays consistent and organized over time.

#### Acceptance Criteria

1. THE requirements SHALL document validation rules that can be checked automatically or manually:
   - Every folder SHALL have a README.md with required sections (Purpose, Contents, Quick Navigation)
   - Every non-README file SHALL have YAML frontmatter with required fields (domain, purpose, updated)
   - No broken internal links across folders
   - Circular dependencies SHALL be flagged (A→B→A patterns)
   - Archive files SHALL not be linked from current documentation (except in ADR context)
2. WHERE documentation files are added/updated, THE validation rules SHALL be checked before accepting the change
3. WHEN cross-domain references exist, THE system SHALL verify that both domains are documented (avoid orphaned references)
4. THE `docs/` folder SHALL not exceed [TBD - e.g., 100 direct files] without sub-folder restructuring

### Requirement 12: Documentation Discovery and Indexing

**User Story:** As a developer searching documentation, I want to find relevant docs quickly via metadata, so that I don't need to manually browse folders.

#### Acceptance Criteria

1. WHEN metadata is complete (frontmatter + README structure), THE documentation SHALL be machine-indexable by:
   - Domain (api, ui, auth, etc)
   - Purpose (specifications, tutorials, reference, quick-start)
   - Related domains
   - Update status (stable, frequently updated, archived)
2. THE root README.md SHALL include a searchable list or quick-reference matrix for common developer tasks:
   - "How do I..." → Recommended folder(s)
   - API documentation → `api/` folder
   - UI components → `ui/` folder
   - Deployment → `deployment/` folder
   - etc.

## File Mapping Table: 40+ Existing Files

| Existing File | Target Folder | Target File | Strategy | Notes |
|---|---|---|---|---|
| API.md | api/ | endpoints.md | Move | Complete API endpoint specs |
| AUTH.md | auth/ | authentication.md | Move | Session/JWT/OAuth mechanisms |
| ARCHITECTURE.md | decisions/ | 0-architecture-overview.md | Move | High-level system architecture (ADR) |
| BUSINESS_RULES.md | business/ | business-rules.md | Move | Domain logic and business workflows |
| CODING_STANDARDS.md | development/ | coding-standards.md | Move | Code style and conventions |
| DATABASE.md | database/ | schema-overview.md | Move | Database structure and design |
| DESIGN.md | ui/ | design-system.md | Move | Design tokens and principles |
| DEPLOYMENT.md | deployment/ | deployment-pipeline.md | Move | CI/CD and deployment workflow |
| SECURITY.md | auth/ | security-practices.md | Move | Security guidelines and best practices |
| SECURITY_ENTERPRISE.md | auth/ | enterprise-security.md | Move | Enterprise security requirements |
| TESTING.md | testing/ | testing-framework.md | Move | Test setup and guidelines |
| PRODUCT.md | business/ | product-specifications.md | Move | Product features and roadmap |
| ACCESSIBILITY_COMPLIANCE_REPORT.md | ui/ | accessibility-report.md | Move | WCAG compliance audit results |
| ARIA_LABEL_CONVENTION.md | ui/ | aria-conventions.md | Move | ARIA label naming standards |
| CHANGELOG.md | archive/ | CHANGELOG-legacy.md | Archive | Historical release notes (if outdated) |
| CONSISTENCY_CHECKLIST.md | testing/ | consistency-checklist.md | Move | Validation procedures |
| CONSISTENCY_VERIFICATION_COMPLETE.md | archive/ | verification-phase-complete.md | Archive | Phase completion report (historical) |
| FUTURE_PAGES_IMPLEMENTATION_GUIDE.md | development/ | future-features-guide.md | Move | Planned feature implementation |
| IMPLEMENTATION_CHANGES_DETAILED.md | archive/ | implementation-changes-wave-*.md | Archive/Split | Implementation tracking (store by wave if multiple) |
| IMPLEMENTATION_PHASE_1_2_SUMMARY.md | archive/ | implementation-phase-summary.md | Archive | Historical phase documentation |
| IMPLEMENTATION_TEST_CHECKLIST.md | testing/ | implementation-test-checklist.md | Move | Testing procedures for implementations |
| KEYBOARD_NAVIGATION_TEST_RESULTS.md | testing/ | accessibility-test-results.md | Consolidate | Merge with other test results |
| LIGHTHOUSE_AUDIT_RESULTS.md | performance/ | lighthouse-audit-report.md | Move | Performance audit results |
| MOBILE_DEVICE_TESTING_RESULTS.md | testing/ | mobile-device-test-results.md | Move | Mobile testing procedures/results |
| MOBILE_OPTIMIZATION_COMPLETION_SUMMARY.md | archive/ | mobile-optimization-summary.md | Archive | Phase completion (historical) |
| TASKS_17_23_COMPLETION_SUMMARY.md | archive/ | tasks-completion-summary.md | Archive | Task tracking (historical) |
| TESTING_FOCUS_INDICATORS.md | testing/ | accessibility-focus-indicators.md | Consolidate | Merge with accessibility testing |
| UI_UX_IMPLEMENTATION_GUIDE.md | ux/ | implementation-guide.md | Move | UX implementation procedures |
| UI_UX_QUICK_REFERENCE.md | ux/ | quick-reference.md | Move | Quick UI/UX reference guide |
| UI_UX_STANDARDS.md | ui/ | ui-ux-standards.md | Move | UI/UX design standards and guidelines |
| UI_UX_STANDARDS_INDEX.md | ui/ | standards-index.md | Consolidate | Merge with UI_UX_STANDARDS.md |
| WAVE1_COMPLETION_CHECKLIST.md | archive/ | wave-1-completion.md | Archive | Historical wave completion |
| WAVE2_COMPLETION_CHECKLIST.md | archive/ | wave-2-completion.md | Archive | Historical wave completion |
| WAVE3_COMPLETION_CHECKLIST.md | archive/ | wave-3-completion.md | Archive | Historical wave completion |
| WAVE4-7_IMPLEMENTATION_CHECKLIST.md | archive/ | wave-4-7-implementation.md | Archive | Historical wave implementation |
| spec-4-role-workflow.md | business/ | role-workflow-specification.md | Move | Role-based workflow documentation |
| DECISIONS/decision-1.md | decisions/ | decision-*.md | Keep | ADRs organized with prefix numbers |
| DECISIONS/decision-2.md | decisions/ | decision-*.md | Keep | ADRs organized with prefix numbers |
| (DECISIONS/ all ADRs) | decisions/ | [preserve structure] | Keep | Keep existing ADR structure, add metadata |

## Implementation Guidance

### Phase 1: Preparation
- Review all 40+ existing files to understand content and relationships
- Finalize folder structure and naming conventions
- Create migration checklist

### Phase 2: Folder Creation & README Setup
- Create 12 modular folders with descriptive README.md for each
- Set up folder-level organization rules in READMEs
- Create root `docs/README.md` with navigation

### Phase 3: File Migration
- Move/consolidate/archive files per mapping table
- Add YAML frontmatter to all files
- Verify cross-domain links are correct

### Phase 4: Validation
- Verify no broken links
- Check metadata completeness
- Validate folder organization against rules

### Phase 5: Documentation
- Document migration results
- Create search/discovery guide
- Update any code references to doc paths

