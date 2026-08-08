---
# YAML Frontmatter Template for Documentation Files
# Copy and modify this template at the top of every documentation file in docs/

# ============================================================================
# REQUIRED FIELDS (all documentation files must include these)
# ============================================================================

domain: [api|ui|ux|auth|database|testing|deployment|business|development|decisions|performance|archive]
# Which domain folder this file belongs to. Must match parent folder name.
# Examples: domain: api, domain: auth, domain: database

purpose: [brief-purpose-code]
# What is this document's main purpose/type? Choose from:
# For api/: endpoint-specs, authentication-requirements, rate-limiting, error-codes
# For ui/: design-system, design-tokens, accessibility-standards, aria-conventions
# For ux/: implementation-guide, user-workflows, form-patterns, quick-reference
# For auth/: authentication, authorization-rbac, security-practices, enterprise-security
# For database/: schema-overview, migration-procedures, query-optimization, backup-recovery
# For testing/: testing-framework, accessibility-testing, test-checklist, test-results
# For deployment/: deployment-pipeline, environment-config, monitoring-alerting, rollback
# For business/: product-specifications, business-rules, role-definitions, compliance
# For development/: setup-guide, coding-standards, git-workflow, build-commands
# For decisions/: adr (architecture decision record)
# For performance/: performance-targets, profiling, caching-strategies, benchmarks
# For archive/: historical, deprecated, legacy

version: 1.0.0
# Documentation version following semver: MAJOR.MINOR.PATCH
# Increment MAJOR when structure/content fundamentally changes
# Increment MINOR when significant new sections added
# Increment PATCH when typos/minor updates made

updated: YYYY-MM-DD
# ISO 8601 date format. Update every time file is modified.
# Examples: updated: 2024-01-15, updated: 2024-12-31

owner: [team-or-role]
# Team or role responsible for maintaining this document
# Examples: owner: platform-team, owner: frontend-team, owner: devops-team

# ============================================================================
# OPTIONAL FIELDS (recommended for most documents)
# ============================================================================

maintainer: [name-or-role]
# Primary point of contact for questions about this document
# Examples: maintainer: "John Doe", maintainer: "api-lead@company.com"

related: [domain1, domain2, ...]
# List other domains this document frequently references or links to
# Examples: related: [auth, database], related: [api, testing]

dependencies: [domain1, domain2, ...]
# Domains this file depends on for context or prerequisites
# Examples: dependencies: [auth], dependencies: [api, database]

tags: [tag1, tag2, tag3]
# Search tags for indexing and discovery
# Examples: tags: [rest-api, endpoints, schema], tags: [accessibility, wcag, aria]

# ============================================================================
# STATUS & QUALITY FIELDS (optional, for tracking)
# ============================================================================

status: [stable|frequently-updated|experimental]
# Stability status:
# - stable: Complete and not frequently changing
# - frequently-updated: Active development or regular updates
# - experimental: Draft or proof-of-concept

quick_reference: [true|false]
# Set to true if this document is a quick reference/cheat sheet
# Flags document for indexing as rapid lookup material
# Examples: quick_reference: true (for quick-reference.md, cheat sheets)

deprecated: [true|false]
# Set to true if document is deprecated but not yet archived
# Mark deprecated documents for future archival
# Examples: deprecated: false (default), deprecated: true

last_verified: YYYY-MM-DD
# Last date this document was manually reviewed for accuracy
# Examples: last_verified: 2024-01-15

validation_status: [valid|needs-review|outdated]
# Current validation/review status
# - valid: Recently reviewed and confirmed accurate
# - needs-review: Flagged for review but not yet addressed
# - outdated: Known to contain outdated information

# ============================================================================
# AUDIENCE & SCOPE FIELDS (optional, for discoverability)
# ============================================================================

audience: [all-developers|frontend|backend|devops|designers|architects|product]
# Intended audience for this document
# Can list multiple: audience: [frontend, backend]

confidentiality: [public|internal|confidential]
# Document visibility level
# - public: Can be shared externally
# - internal: For team members only
# - confidential: Restricted access

# ============================================================================
# ARCHIVE-SPECIFIC FIELDS (REQUIRED for docs/archive/ files only)
# ============================================================================

archived: [true|false]
# Set to true ONLY for files in docs/archive/ domain
# Examples: archived: false (default), archived: true (for archived docs)

archived_date: YYYY-MM-DD
# Date when document was archived (if archived: true)
# Examples: archived_date: 2024-01-15

archive_reason: [brief-reason-code]
# Why this document was archived? Choose from:
# - historical-phase-documentation (phase completion reports)
# - historical-wave-completion (wave/sprint completion checklists)
# - legacy-release-notes (old changelogs)
# - historical-task-tracking (old task/bug tracking)
# - superseded-by-current-documentation (newer version exists)
# - experimental-proof-of-concept (POC no longer needed)
# Examples: archive_reason: "historical-wave-completion"

archive_category: [wave|phase|legacy|completed|other]
# Category of archived content for organization
# Examples: archive_category: wave, archive_category: phase

original_location: docs/[filename].md
# Where this file was originally located before archiving
# Helps trace file history and retrieval
# Examples: original_location: "docs/WAVE1_COMPLETION_CHECKLIST.md"

related_active_docs: [../domain/file.md, ../domain/file2.md]
# Links to current/active documents that replaced or superseded this archive
# Helps readers find replacement documentation
# Examples: related_active_docs: ["../testing/testing-framework.md"]

# ============================================================================
# Example Complete Frontmatter (Copy and Modify)
# ============================================================================

# For a typical API endpoint documentation file:
# ---
# domain: api
# purpose: endpoint-specs
# version: 1.2.3
# updated: 2024-01-15
# owner: platform-team
# maintainer: "api-lead@company.com"
# related: [auth, database]
# tags: [rest, endpoints, schema]
# status: stable
# audience: [frontend, backend]
# ---

# For a design system document:
# ---
# domain: ui
# purpose: design-system
# version: 2.0.0
# updated: 2024-01-15
# owner: design-team
# related: [ux, testing]
# tags: [design-tokens, components]
# status: frequently-updated
# quick_reference: false
# audience: [frontend, designers]
# last_verified: 2024-01-10
# validation_status: valid
# ---

# For an archived wave completion checklist:
# ---
# domain: archive
# purpose: historical
# version: 1.0.0
# updated: 2024-01-15
# owner: project-manager
# archived: true
# archived_date: 2024-01-15
# archive_reason: historical-wave-completion
# archive_category: wave
# original_location: "docs/WAVE1_COMPLETION_CHECKLIST.md"
# related_active_docs: ["../testing/testing-framework.md"]
# ---

# ============================================================================
# Notes on Usage
# ============================================================================

# 1. Copy this entire block to top of new documentation file
# 2. Replace bracketed values [like-this] with actual values
# 3. Remove optional fields that don't apply to your document
# 4. For multiple values, use YAML list format: [item1, item2, item3]
# 5. Dates must be YYYY-MM-DD format
# 6. Do NOT include comments in final file (remove # lines)
# 7. Keep YAML structure valid (proper indentation, colons, quotes)

---

# ============================================================================
# Document Content Starts Below
# ============================================================================

# Document title and content follows the frontmatter block above

# # Document Title

# [Your documentation content here]
# Start with a top-level heading (# Document Title)
# Organize with subheadings (## Section, ### Subsection)

