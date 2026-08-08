# Task List: Documentation Restructuring (Modular Structure C)

**Project Duration**: 7-9 days total across 5 phases
**Total Tasks**: 58 tasks organized in 5 phases
**Success Metric**: All 40+ files migrated, 12 domain folders created, zero broken links

---

## PHASE 1: PREPARATION (Days 1-3, 10 tasks)

### 1.1 Project Initialization & Git Setup
- **Task**: Create feature branch for documentation restructuring
- **Acceptance**: 
  - ✅ Branch created: `feature/docs-restructuring-modular-12domain`
  - ✅ Branch pushed to remote
  - ✅ No commits on main/master (all work on feature branch)
- **Exact Commands**: 
  ```bash
  git checkout -b feature/docs-restructuring-modular-12domain
  git push -u origin feature/docs-restructuring-modular-12domain
  ```
- **Estimated Time**: 10 minutes
- **Dependencies**: None
- **Rollback**: `git branch -D feature/docs-restructuring-modular-12domain` (if needed before any commits)

### 1.2 Backup Current Documentation Structure
- **Task**: Create backup of current docs/ folder structure before any changes
- **Acceptance**:
  - ✅ Backup created at: `docs.backup-before-restructuring/` (or similar)
  - ✅ All 40+ files copied verbatim to backup
  - ✅ Backup directory NOT committed to git (add to .gitignore if needed)
  - ✅ Backup verified: file count matches original
- **Exact Commands**:
  ```bash
  cp -r docs docs.backup-before-restructuring
  # Verify: ls -la docs.backup-before-restructuring/ | wc -l should match docs/
  ```
- **Estimated Time**: 15 minutes
- **Dependencies**: None (local backup only)
- **Rollback**: `rm -rf docs.backup-before-restructuring/` (remove backup if restore from git history needed)

### 1.3 Current Documentation Inventory & Analysis
- **Task**: Analyze existing 40+ documentation files: list, size, relationships, content summary
- **Acceptance**:
  - ✅ Complete inventory in `.kiro/docs-inventory.json` with: filename, size, line count, last modified, purpose
  - ✅ Relationship map showing which files reference each other
  - ✅ Consolidation candidates identified (UI_UX_* files, test results files)
  - ✅ Archive candidates identified (WAVE*, IMPLEMENTATION_PHASE*, etc.)
- **Exact Commands**:
  ```bash
  # Generate inventory script (Python):
  python3 << 'EOF'
  import os, json
  inventory = []
  for f in os.listdir("docs"):
      if f.endswith(".md"):
          path = os.path.join("docs", f)
          size = os.path.getsize(path)
          with open(path) as file:
              lines = len(file.readlines())
          inventory.append({"file": f, "size": size, "lines": lines})
  with open(".kiro/docs-inventory.json", "w") as out:
      json.dump(sorted(inventory, key=lambda x: x["file"]), out, indent=2)
  print(f"Inventory: {len(inventory)} files")
  EOF
  ```
- **Estimated Time**: 45 minutes
- **Dependencies**: 1.2 (backup complete)
- **Rollback**: `rm .kiro/docs-inventory.json` (inventory only for reference)

### 1.4 File Mapping Validation & Cross-Reference Audit
- **Task**: Validate that file mapping table (40+ files) is complete and accurate; audit cross-references
- **Acceptance**:
  - ✅ All files in inventory matched to mapping table (no files missed)
  - ✅ Target domains verified exist in design spec (12 domains)
  - ✅ No conflicts: file not mapped to multiple targets (1:1 or N:1 for consolidations only)
  - ✅ Consolidation logic reviewed: all files being merged identified
  - ✅ Archive candidates confirmed: 11+ files marked for archival
- **Exact Commands**: Manual review using requirements.md & design.md file mapping tables
- **Estimated Time**: 1 hour
- **Dependencies**: 1.3 (inventory complete)
- **Rollback**: None (validation only)

### 1.5 Team Notification & Documentation Planning
- **Task**: Notify team of restructuring plan, schedule, and communication plan
- **Acceptance**:
  - ✅ Team notification sent (Slack/email) with: timeline, phases, potential doc link breakage
  - ✅ Communicate: phase 1-3 will cause doc URLs to change (provide migration table)
  - ✅ Phase 4-5: validation phase, expect fixes if any links broken
  - ✅ Phase 5: final commit with full migration report
  - ✅ Document when old doc structure will be replaced (phase 5 final commit)
- **Estimated Time**: 30 minutes
- **Dependencies**: 1.4 (mapping validated)
- **Rollback**: None (communication only)

### 1.6 Create File Migration Checklist
- **Task**: Create detailed checklist for each existing file with migration steps
- **Acceptance**:
  - ✅ Checklist file created: `.kiro/migration-checklist.md`
  - ✅ Each of 40+ files has row: source | target | strategy | notes | status checkbox
  - ✅ Sub-tasks for consolidations listed separately
  - ✅ Archive candidates clearly marked
  - ✅ Cross-reference verification section added
- **Estimated Time**: 45 minutes
- **Dependencies**: 1.4 (mapping validated)
- **Rollback**: `rm .kiro/migration-checklist.md`

### 1.7 Verify Build/Test Infrastructure
- **Task**: Ensure markdown linter and link validation tools are available
- **Acceptance**:
  - ✅ `npm run lint` works (or equivalent linter)
  - ✅ Identify link validation tool (e.g., `remark`, `markdownlint`, custom script)
  - ✅ Identify if CI/CD has markdown checks (if applicable)
  - ✅ Documentation: command to run link validation
  - ✅ Test on sample file: verify linter catches broken links
- **Exact Commands**:
  ```bash
  npm run lint  # Test if works
  # OR install markdown link checker if not present
  npm install --save-dev markdown-link-check
  ```
- **Estimated Time**: 30 minutes
- **Dependencies**: None
- **Rollback**: `npm uninstall markdown-link-check` (if installed)

### 1.8 Create Domain Folder Planning Document
- **Task**: Finalize 12 domain folder names, purposes, and ownership (reference design spec)
- **Acceptance**:
  - ✅ File created: `.kiro/domain-planning.md`
  - ✅ All 12 domains listed: name, purpose (2-3 sentences), suggested README outline
  - ✅ Example of expected contents per domain
  - ✅ Subfolder patterns identified (if any domain will have subfolders initially)
  - ✅ Naming convention verified: lowercase, hyphenated (e.g., `api`, `ui`, `auth`)
- **Estimated Time**: 45 minutes
- **Dependencies**: Design spec review (reference)
- **Rollback**: `rm .kiro/domain-planning.md`

### 1.9 Metadata Template Finalization
- **Task**: Create template files for YAML frontmatter and README.md per domain
- **Acceptance**:
  - ✅ Template created: `.kiro/templates/frontmatter-template.md` (YAML structure with comments)
  - ✅ Template created: `.kiro/templates/README-template.md` (generic README structure)
  - ✅ Domain-specific README templates created for: api, ui, auth, database (key domains)
  - ✅ Comments in templates explain required vs. optional fields
  - ✅ Metadata examples provided for different document types (spec, quick-ref, archived)
- **Estimated Time**: 1 hour
- **Dependencies**: Design spec section 3 & 4 (metadata & README templates)
- **Rollback**: `rm -rf .kiro/templates/`

### 1.10 Preparation Phase Sign-Off
- **Task**: Review all preparation artifacts and confirm ready for Phase 2
- **Acceptance**:
  - ✅ Backup verified (docs.backup-before-restructuring/ exists with all files)
  - ✅ Inventory complete (.kiro/docs-inventory.json)
  - ✅ File mapping validated (all 40+ files mapped)
  - ✅ Team notified
  - ✅ Checklist created (.kiro/migration-checklist.md)
  - ✅ Tools verified (linter works)
  - ✅ Domain planning finalized (.kiro/domain-planning.md)
  - ✅ Templates ready (.kiro/templates/)
  - ✅ Green light: proceed to Phase 2
- **Estimated Time**: 20 minutes
- **Dependencies**: 1.1 through 1.9 complete
- **Rollback**: None (phase sign-off only)

**Phase 1 Status**: ✅ COMPLETE (Days 1-3)

---

## PHASE 2: FOLDER CREATION & SETUP (Day 4, 13 tasks)

### 2.1 Create 12 Domain Folders
- **Task**: Create all 12 domain folders with correct naming convention
- **Acceptance**:
  - ✅ All 12 folders created in `docs/`: api/, ui/, ux/, auth/, database/, testing/, deployment/, business/, development/, decisions/, performance/, archive/
  - ✅ Folder names: lowercase, hyphenated, matching design spec
  - ✅ Permissions: world-readable (755)
  - ✅ Each folder contains ONLY .gitkeep (empty marker) for now
- **Exact Commands**:
  ```bash
  mkdir -p docs/{api,ui,ux,auth,database,testing,deployment,business,development,decisions,performance,archive}
  touch docs/{api,ui,ux,auth,database,testing,deployment,business,development,decisions,performance,archive}/.gitkeep
  ```
- **Estimated Time**: 5 minutes
- **Dependencies**: None
- **Rollback**: `rm -rf docs/{api,ui,ux,auth,database,testing,deployment,business,development,decisions,performance,archive}`

### 2.2 Create Root docs/README.md (Navigation Hub)
- **Task**: Create main `docs/README.md` with overview, navigation tree, and domain quick links
- **Acceptance**:
  - ✅ File location: `docs/README.md`
  - ✅ Includes: high-level overview of all 12 domains (1-2 sentence each)
  - ✅ Visual ASCII tree showing folder structure
  - ✅ Quick links to each domain README
  - ✅ "How do I find..." section mapping common use cases to domains
  - ✅ Navigation matrix table: domain | purpose | key files | owner | status
  - ✅ Cross-domain dependency overview (reference from design spec section 5)
- **Template Reference**: Design spec section 4.1 & 9
- **Estimated Time**: 1.5 hours
- **Dependencies**: 2.1 (folders created)
- **Rollback**: `rm docs/README.md`

### 2.3 Create api/ Domain README
- **Task**: Create `docs/api/README.md` following template for API domain
- **Acceptance**:
  - ✅ File: `docs/api/README.md` with all required sections
  - ✅ Purpose: explains API documentation scope and role
  - ✅ Quick Navigation table: links to endpoints.md, auth requirements, rate limiting, error codes
  - ✅ Folder Organization Rules: when to add files, naming pattern, max files
  - ✅ Related Domains: links to auth/, database/, testing/
  - ✅ Search Tips: common API questions mapped to files
- **Template Reference**: Design spec section 4.2 (API README)
- **Estimated Time**: 45 minutes
- **Dependencies**: 2.1 (api/ folder created)
- **Rollback**: `rm docs/api/README.md`

### 2.4 Create ui/ Domain README
- **Task**: Create `docs/ui/README.md` for UI domain
- **Acceptance**:
  - ✅ File: `docs/ui/README.md` with required sections
  - ✅ Quick Navigation: design-system, design-tokens, accessibility standards, aria conventions, ui-ux-standards
  - ✅ Folder Organization Rules specific to UI
  - ✅ Related Domains: ux/, testing/, development/
  - ✅ Search Tips for UI questions
- **Template Reference**: Design spec section 4.2 (UI README)
- **Estimated Time**: 45 minutes
- **Dependencies**: 2.1 (ui/ folder created)
- **Rollback**: `rm docs/ui/README.md`

### 2.5 Create auth/ Domain README
- **Task**: Create `docs/auth/README.md` for authentication & authorization domain
- **Acceptance**:
  - ✅ File: `docs/auth/README.md`
  - ✅ Quick Navigation: authentication, authorization-rbac, security practices, enterprise security
  - ✅ Folder Organization Rules for auth
  - ✅ Related Domains: api/, database/, deployment/, business/
- **Template Reference**: Design spec section 4.2 (Auth README)
- **Estimated Time**: 45 minutes
- **Dependencies**: 2.1 (auth/ folder created)
- **Rollback**: `rm docs/auth/README.md`

### 2.6 Create database/ Domain README
- **Task**: Create `docs/database/README.md` for database domain
- **Acceptance**:
  - ✅ File: `docs/database/README.md`
  - ✅ Quick Navigation: schema overview, migration procedures, query optimization, backup recovery
  - ✅ Folder Organization Rules for database
  - ✅ Related Domains: deployment/, testing/, performance/
- **Template Reference**: Design spec section 4.2 (Database README)
- **Estimated Time**: 45 minutes
- **Dependencies**: 2.1 (database/ folder created)
- **Rollback**: `rm docs/database/README.md`

### 2.7 Create Remaining Domain READMEs (Generic Template)
- **Task**: Create READMEs for remaining 7 domains: ux/, testing/, deployment/, business/, development/, decisions/, performance/
- **Acceptance**:
  - ✅ All 7 READMEs created with consistent structure
  - ✅ Each follows generic template (section 4.1): Purpose, Quick Navigation, Folder Org Rules, Related Domains, Getting Started, Search Tips
  - ✅ Content tailored to domain (content from design spec section 4)
  - ✅ Cross-links verified (domains reference each other correctly)
  - ✅ All READMEs include NO YAML frontmatter (README.md are navigation files, not content)
- **Domain-specific Content**: Design spec section 1 (12 domains) and section 4 (README templates)
- **Estimated Time**: 3.5 hours (30 min per domain average)
- **Dependencies**: 2.1 (all folders created), 2.2-2.6 (pattern established)
- **Rollback**: `rm docs/{ux,testing,deployment,business,development,decisions,performance}/README.md`

### 2.8 Create archive/ Domain README & Index
- **Task**: Create `docs/archive/README.md` with archival policy and index of all archived files
- **Acceptance**:
  - ✅ File: `docs/archive/README.md` explaining archive purpose and retrieval
  - ✅ Archive policy section: why files are archived, retention period, how to access
  - ✅ Archive Index: table of all files to be archived with reason and date
  - ✅ Guidance on archival metadata (archived: true, archived_date, archive_reason fields)
  - ✅ Note: "Archive files NOT linked from active docs (except in ADRs for historical context)"
- **Estimated Time**: 45 minutes
- **Dependencies**: 2.1 (archive/ folder created)
- **Rollback**: `rm docs/archive/README.md`

### 2.9 Create Metadata & Frontmatter Reference Guide
- **Task**: Create documentation guide for YAML frontmatter and metadata best practices
- **Acceptance**:
  - ✅ File: `docs/METADATA_GUIDE.md` explaining frontmatter structure
  - ✅ Complete YAML frontmatter example (section 3.1 from design)
  - ✅ Domain-specific metadata requirements table
  - ✅ Examples for: quick-reference, frequently-updated, experimental, cross-domain documents
  - ✅ Metadata extraction examples (Python script for AI tools)
- **Template Reference**: Design spec section 3 (Metadata)
- **Estimated Time**: 1 hour
- **Dependencies**: None
- **Rollback**: `rm docs/METADATA_GUIDE.md`

### 2.10 Verify Folder Structure Completeness
- **Task**: Verify all 12 folders and 13 READMEs created correctly
- **Acceptance**:
  - ✅ Run: `find docs -type d | wc -l` → Should be 13 (docs + 12 domains)
  - ✅ Run: `find docs -name README.md | wc -l` → Should be 13 (root + 12 domains)
  - ✅ Each README checked for: Purpose section, Quick Navigation, Related Domains, Folder Org Rules
  - ✅ Root docs/README.md verified has navigation links to all 12 domains
  - ✅ All links in READMEs use relative paths: `../other-domain/`
  - ✅ No broken links (test with markdown link checker if available)
- **Estimated Time**: 30 minutes
- **Dependencies**: 2.2-2.9 (all READMEs created)
- **Rollback**: None (verification only)

### 2.11 Verify Naming Conventions & Organization Rules
- **Task**: Ensure folder naming, file naming, and organization rules are consistent
- **Acceptance**:
  - ✅ All folder names: lowercase, hyphenated (api, ui, auth, database, etc.)
  - ✅ All README.md files follow consistent structure pattern
  - ✅ Folder Org Rules section in each README is clear and actionable
  - ✅ Max file limits documented (typically 10-15 direct files before sub-folders)
  - ✅ File naming patterns documented per domain (PURPOSE.md, PURPOSE-VARIANT.md, etc.)
- **Estimated Time**: 45 minutes
- **Dependencies**: 2.2-2.9 (all READMEs created)
- **Rollback**: None (verification only)

### 2.12 Create Phase 2 Checklist: Folder Setup
- **Task**: Create verification checklist for all folder setup tasks
- **Acceptance**:
  - ✅ Checklist file: `.kiro/phase-2-folder-setup-verification.md`
  - ✅ Checklist items: 12 folders created | 13 READMEs exist | links valid | naming consistent | org rules clear
  - ✅ Sign-off: all items checked ✅
- **Estimated Time**: 15 minutes
- **Dependencies**: 2.1-2.11 (setup complete)
- **Rollback**: `rm .kiro/phase-2-folder-setup-verification.md`

### 2.13 Phase 2 Sign-Off
- **Task**: Final verification that all folder creation and setup is complete before Phase 3
- **Acceptance**:
  - ✅ 12 domain folders exist
  - ✅ 13 README.md files created (root + 12 domains)
  - ✅ All READMEs follow template structure
  - ✅ Cross-links verified and working
  - ✅ Naming conventions consistent
  - ✅ Archive policy documented
  - ✅ Metadata guide created
  - ✅ Green light: proceed to Phase 3 (file migration)
- **Estimated Time**: 20 minutes
- **Dependencies**: 2.1-2.12 (all Phase 2 tasks)
- **Rollback**: None (sign-off only)

**Phase 2 Status**: ✅ COMPLETE (Day 4)

---

## PHASE 3: FILE MIGRATION (Days 5-7, 26 tasks)

### 3.1 Migrate API.md → docs/api/endpoints.md
- **Task**: Move API.md to new location and add YAML frontmatter
- **Acceptance**:
  - ✅ File moved: `docs/API.md` → `docs/api/endpoints.md` (using `git mv` to preserve history)
  - ✅ YAML frontmatter added with: domain: api, purpose: endpoint-specs, version: 1.0, updated: [date], owner: platform-team
  - ✅ No content changes to file body
  - ✅ Old path does not exist
  - ✅ Git history preserved in `git log docs/api/endpoints.md`
- **Exact Commands**:
  ```bash
  git mv docs/API.md docs/api/endpoints.md
  # Then prepend frontmatter using script or editor
  ```
- **Estimated Time**: 15 minutes
- **Dependencies**: Phase 2 complete (folders exist)
- **Rollback**: `git checkout -- docs/api/endpoints.md && git reset --hard`

### 3.2 Migrate AUTH.md → docs/auth/authentication.md
- **Task**: Move AUTH.md to auth domain and add frontmatter
- **Acceptance**:
  - ✅ `git mv docs/AUTH.md docs/auth/authentication.md`
  - ✅ Frontmatter added: domain: auth, purpose: authentication, ...
  - ✅ Content preserved, git history intact
- **Estimated Time**: 15 minutes
- **Dependencies**: Phase 2 complete
- **Rollback**: Git reset

### 3.3 Migrate ARCHITECTURE.md → docs/decisions/0-architecture-overview.md
- **Task**: Move ARCHITECTURE.md to decisions (ADR format) with naming prefix
- **Acceptance**:
  - ✅ `git mv docs/ARCHITECTURE.md docs/decisions/0-architecture-overview.md`
  - ✅ Frontmatter with: domain: decisions, purpose: adr (architecture decision record), decision_date: [date], status: accepted
  - ✅ Content becomes ADR format if not already
- **Estimated Time**: 15 minutes
- **Dependencies**: Phase 2 complete
- **Rollback**: Git reset

### 3.4 Migrate BUSINESS_RULES.md → docs/business/business-rules.md
- **Task**: Move to business domain
- **Acceptance**:
  - ✅ `git mv docs/BUSINESS_RULES.md docs/business/business-rules.md`
  - ✅ Frontmatter: domain: business, purpose: business-rules, ...
- **Estimated Time**: 15 minutes
- **Dependencies**: Phase 2 complete
- **Rollback**: Git reset

### 3.5 Migrate CODING_STANDARDS.md → docs/development/coding-standards.md
- **Task**: Move to development domain
- **Acceptance**:
  - ✅ `git mv docs/CODING_STANDARDS.md docs/development/coding-standards.md`
  - ✅ Frontmatter: domain: development, purpose: coding-standards, ...
- **Estimated Time**: 15 minutes
- **Dependencies**: Phase 2 complete
- **Rollback**: Git reset

### 3.6 Migrate DATABASE.md → docs/database/schema-overview.md
- **Task**: Move to database domain
- **Acceptance**:
  - ✅ `git mv docs/DATABASE.md docs/database/schema-overview.md`
  - ✅ Frontmatter: domain: database, purpose: schema, ...
- **Estimated Time**: 15 minutes
- **Dependencies**: Phase 2 complete
- **Rollback**: Git reset

### 3.7 Migrate DESIGN.md → docs/ui/design-system.md
- **Task**: Move to UI domain
- **Acceptance**:
  - ✅ `git mv docs/DESIGN.md docs/ui/design-system.md`
  - ✅ Frontmatter: domain: ui, purpose: design-system, ...
- **Estimated Time**: 15 minutes
- **Dependencies**: Phase 2 complete
- **Rollback**: Git reset

### 3.8 Migrate DEPLOYMENT.md → docs/deployment/deployment-pipeline.md
- **Task**: Move to deployment domain
- **Acceptance**:
  - ✅ `git mv docs/DEPLOYMENT.md docs/deployment/deployment-pipeline.md`
  - ✅ Frontmatter: domain: deployment, purpose: deployment-pipeline, ...
- **Estimated Time**: 15 minutes
- **Dependencies**: Phase 2 complete
- **Rollback**: Git reset

### 3.9 Migrate SECURITY.md & SECURITY_ENTERPRISE.md → docs/auth/
- **Task**: Move both security files to auth domain as separate files
- **Acceptance**:
  - ✅ `git mv docs/SECURITY.md docs/auth/security-practices.md`
  - ✅ `git mv docs/SECURITY_ENTERPRISE.md docs/auth/enterprise-security.md`
  - ✅ Both with appropriate frontmatter: domain: auth, purpose: security-practices or enterprise-security
- **Estimated Time**: 20 minutes
- **Dependencies**: Phase 2 complete
- **Rollback**: Git reset

### 3.10 Migrate TESTING.md → docs/testing/testing-framework.md
- **Task**: Move to testing domain
- **Acceptance**:
  - ✅ `git mv docs/TESTING.md docs/testing/testing-framework.md`
  - ✅ Frontmatter: domain: testing, purpose: testing-framework, ...
- **Estimated Time**: 15 minutes
- **Dependencies**: Phase 2 complete
- **Rollback**: Git reset

### 3.11 Migrate PRODUCT.md → docs/business/product-specifications.md
- **Task**: Move to business domain
- **Acceptance**:
  - ✅ `git mv docs/PRODUCT.md docs/business/product-specifications.md`
  - ✅ Frontmatter: domain: business, purpose: product-spec, ...
- **Estimated Time**: 15 minutes
- **Dependencies**: Phase 2 complete
- **Rollback**: Git reset

### 3.12 Migrate Accessibility Files → docs/ui/
- **Task**: Move accessibility files to UI domain
- **Acceptance**:
  - ✅ `git mv docs/ACCESSIBILITY_COMPLIANCE_REPORT.md docs/ui/accessibility-report.md`
  - ✅ `git mv docs/ARIA_LABEL_CONVENTION.md docs/ui/aria-conventions.md`
  - ✅ Both with frontmatter: domain: ui, purpose: accessibility-* or aria-conventions
- **Estimated Time**: 20 minutes
- **Dependencies**: Phase 2 complete
- **Rollback**: Git reset

### 3.13 Migrate CONSISTENCY & TESTING Files → docs/testing/
- **Task**: Move consistency and testing-related files
- **Acceptance**:
  - ✅ `git mv docs/CONSISTENCY_CHECKLIST.md docs/testing/consistency-checklist.md`
  - ✅ `git mv docs/IMPLEMENTATION_TEST_CHECKLIST.md docs/testing/implementation-test-checklist.md`
  - ✅ `git mv docs/MOBILE_DEVICE_TESTING_RESULTS.md docs/testing/mobile-device-test-results.md`
  - ✅ All with frontmatter: domain: testing, purpose: testing-* or consistency-*
- **Estimated Time**: 30 minutes
- **Dependencies**: Phase 2 complete
- **Rollback**: Git reset

### 3.14 Consolidate Test Results Files → docs/testing/accessibility-test-results.md
- **Task**: Merge KEYBOARD_NAVIGATION_TEST_RESULTS.md and TESTING_FOCUS_INDICATORS.md
- **Acceptance**:
  - ✅ Create `docs/testing/accessibility-test-results.md` with merged content
  - ✅ Structure: ## Keyboard Navigation Tests | ## Focus Indicator Tests | ## Mobile Device Testing
  - ✅ Add YAML frontmatter: domain: testing, purpose: accessibility-testing, ...
  - ✅ Delete old files: `git rm docs/KEYBOARD_NAVIGATION_TEST_RESULTS.md docs/TESTING_FOCUS_INDICATORS.md`
  - ✅ Add notes in new file: "Consolidated from KEYBOARD_NAVIGATION_TEST_RESULTS.md and TESTING_FOCUS_INDICATORS.md"
- **Estimated Time**: 45 minutes
- **Dependencies**: 3.13 (other testing files moved)
- **Rollback**: Restore from backup if consolidation has issues; can recreate from git history

### 3.15 Consolidate UI/UX Standards Files → docs/ui/ui-ux-standards.md
- **Task**: Merge UI_UX_STANDARDS.md and UI_UX_STANDARDS_INDEX.md
- **Acceptance**:
  - ✅ Move UI_UX_STANDARDS.md to `docs/ui/ui-ux-standards.md`
  - ✅ Merge UI_UX_STANDARDS_INDEX.md content as "Quick Navigation" section at top
  - ✅ Structure: ## Quick Navigation (from index) | ## Full Standards (from standards)
  - ✅ Delete old file: `git rm docs/UI_UX_STANDARDS_INDEX.md`
  - ✅ Add YAML frontmatter to consolidated file
  - ✅ Add note: "Consolidated from UI_UX_STANDARDS.md and UI_UX_STANDARDS_INDEX.md"
- **Estimated Time**: 45 minutes
- **Dependencies**: Phase 2 complete (ui/ folder ready)
- **Rollback**: Restore from backup or git history

### 3.16 Migrate UX Files → docs/ux/
- **Task**: Move UX implementation and reference files
- **Acceptance**:
  - ✅ `git mv docs/UI_UX_IMPLEMENTATION_GUIDE.md docs/ux/implementation-guide.md`
  - ✅ `git mv docs/UI_UX_QUICK_REFERENCE.md docs/ux/quick-reference.md`
  - ✅ Create `docs/ux/user-workflows.md` (if content exists in other files, consolidate; otherwise create placeholder with "See Also" to related docs)
  - ✅ All with frontmatter: domain: ux, purpose: implementation-guide or quick-reference, ...
- **Estimated Time**: 30 minutes
- **Dependencies**: Phase 2 complete
- **Rollback**: Git reset

### 3.17 Migrate Business Files → docs/business/
- **Task**: Move role/workflow and business spec files
- **Acceptance**:
  - ✅ `git mv docs/spec-4-role-workflow.md docs/business/role-workflow-specification.md`
  - ✅ Create `docs/business/compliance-requirements.md` (if exists elsewhere, move; otherwise placeholder)
  - ✅ All with frontmatter: domain: business, ...
- **Estimated Time**: 20 minutes
- **Dependencies**: Phase 2 complete
- **Rollback**: Git reset

### 3.18 Migrate Performance Files → docs/performance/
- **Task**: Move performance and benchmark files
- **Acceptance**:
  - ✅ `git mv docs/LIGHTHOUSE_AUDIT_RESULTS.md docs/performance/lighthouse-audit-report.md`
  - ✅ Create placeholder files if needed: performance-targets.md, profiling-procedures.md, caching-strategies.md
  - ✅ All with frontmatter: domain: performance, purpose: performance-*, ...
  - ✅ Lighthouse file has: archived: false (it's active, not historical)
- **Estimated Time**: 30 minutes
- **Dependencies**: Phase 2 complete
- **Rollback**: Git reset

### 3.19 Migrate Development Files → docs/development/
- **Task**: Move development setup and future features guide
- **Acceptance**:
  - ✅ `git mv docs/FUTURE_PAGES_IMPLEMENTATION_GUIDE.md docs/development/future-features-guide.md`
  - ✅ Create placeholder files: local-development-setup.md, git-workflow.md, build-test-commands.md
  - ✅ All with frontmatter: domain: development, ...
- **Estimated Time**: 30 minutes
- **Dependencies**: Phase 2 complete
- **Rollback**: Git reset

### 3.20 Archive Historical Phase Files → docs/archive/
- **Task**: Move all historical wave and phase completion files to archive
- **Acceptance**:
  - ✅ `git mv docs/WAVE1_COMPLETION_CHECKLIST.md docs/archive/wave-1-completion.md`
  - ✅ `git mv docs/WAVE2_COMPLETION_CHECKLIST.md docs/archive/wave-2-completion.md`
  - ✅ `git mv docs/WAVE3_COMPLETION_CHECKLIST.md docs/archive/wave-3-completion.md`
  - ✅ `git mv docs/WAVE4-7_IMPLEMENTATION_CHECKLIST.md docs/archive/wave-4-7-implementation.md`
  - ✅ All with ARCHIVE frontmatter: domain: archive, purpose: historical, archived: true, archived_date: [date], archive_reason: historical-wave-completion, archive_category: wave
- **Estimated Time**: 30 minutes
- **Dependencies**: Phase 2 complete (archive/ folder ready)
- **Rollback**: Git reset

### 3.21 Archive Implementation & Completion Files → docs/archive/
- **Task**: Move implementation tracking and phase summary files
- **Acceptance**:
  - ✅ `git mv docs/IMPLEMENTATION_PHASE_1_2_SUMMARY.md docs/archive/implementation-phase-summary.md`
  - ✅ `git mv docs/IMPLEMENTATION_CHANGES_DETAILED.md docs/archive/implementation-changes-detailed.md`
  - ✅ `git mv docs/MOBILE_OPTIMIZATION_COMPLETION_SUMMARY.md docs/archive/mobile-optimization-summary.md`
  - ✅ `git mv docs/CONSISTENCY_VERIFICATION_COMPLETE.md docs/archive/verification-phase-complete.md`
  - ✅ `git mv docs/TASKS_17_23_COMPLETION_SUMMARY.md docs/archive/tasks-completion-summary.md`
  - ✅ All with ARCHIVE frontmatter: archived: true, archive_reason: historical-phase-documentation
- **Estimated Time**: 30 minutes
- **Dependencies**: Phase 2 complete
- **Rollback**: Git reset

### 3.22 Archive Changelog & Legacy Files → docs/archive/
- **Task**: Move legacy CHANGELOG to archive
- **Acceptance**:
  - ✅ `git mv docs/CHANGELOG.md docs/archive/CHANGELOG-legacy.md`
  - ✅ With ARCHIVE frontmatter: archived: true, archive_reason: historical-release-notes
- **Estimated Time**: 10 minutes
- **Dependencies**: Phase 2 complete
- **Rollback**: Git reset

### 3.23 Move/Preserve DECISIONS Folder Files
- **Task**: Ensure all ADR files from docs/DECISIONS/ have proper frontmatter and organization
- **Acceptance**:
  - ✅ All ADR files already in `docs/decisions/` (or move if located elsewhere)
  - ✅ Each ADR has frontmatter: domain: decisions, purpose: adr, decision_date: [date], status: [proposed|accepted|deprecated]
  - ✅ ADRs follow naming pattern: 0-[title].md, 1-[title].md, etc.
  - ✅ `docs/decisions/README.md` created (if not in Phase 2)
- **Estimated Time**: 45 minutes
- **Dependencies**: Phase 2 complete
- **Rollback**: None (preservation task)

### 3.24 Add YAML Frontmatter to All Moved Files
- **Task**: Verify all 26+ migrated files have proper YAML frontmatter
- **Acceptance**:
  - ✅ Every file in all 12 domains (except READMEs and archive) has frontmatter block
  - ✅ Frontmatter includes: domain, purpose, version, updated, owner, related, status (at minimum)
  - ✅ Archive files also have: archived: true, archived_date, archive_reason
  - ✅ Script to verify: `python3 verify_frontmatter.py` (checks all *.md files for valid YAML front)
  - ✅ No files missing frontmatter
- **Estimated Time**: 1 hour (includes script creation and verification)
- **Dependencies**: 3.1-3.23 (all files moved)
- **Rollback**: Can add frontmatter to files manually if issues

### 3.25 Update Cross-References & Internal Links
- **Task**: Find and update all internal links that reference moved files
- **Acceptance**:
  - ✅ Search all files for references to old paths (e.g., `docs/API.md` → should be `docs/api/endpoints.md`)
  - ✅ Update markdown links in remaining documentation (if any cross-references)
  - ✅ Relative paths verified: `../api/endpoints.md` format
  - ✅ Links tested with markdown link checker if available
  - ✅ No broken internal links remain
- **Exact Commands**:
  ```bash
  # Find old file references (if docs mention old paths)
  grep -r "docs/API.md" docs/
  grep -r "docs/AUTH.md" docs/
  # (continue for all migrated files)
  # Update links manually or with sed if bulk update needed
  ```
- **Estimated Time**: 1.5 hours
- **Dependencies**: 3.1-3.25 (all files moved)
- **Rollback**: Git diff to see what changed; can revert individual files

### 3.26 Create Migration Completion Report
- **Task**: Document all files migrated, consolidations completed, and archive actions taken
- **Acceptance**:
  - ✅ File created: `.kiro/migration-completion-report.md`
  - ✅ Summary: X files moved | Y files consolidated | Z files archived
  - ✅ Detailed checklist: all 40+ files accounted for (moved/consolidated/archived)
  - ✅ Consolidation details: which files merged, how sections organized
  - ✅ Archive details: files archived, reasons, retrieval instructions
  - ✅ Known issues (if any): links to fix in Phase 4
- **Estimated Time**: 45 minutes
- **Dependencies**: 3.1-3.25 (all migration complete)
- **Rollback**: None (report only)

**Phase 3 Status**: ✅ COMPLETE (Days 5-7)

---

## PHASE 4: VALIDATION & QUALITY GATES (Day 8, 7 tasks)

### 4.1 Automated Link Validation
- **Task**: Run automated link checker to identify broken internal links
- **Acceptance**:
  - ✅ Link validation script executed: `npm run markdown:links` or `markdown-link-check docs/**/*.md`
  - ✅ Report generated: `.kiro/link-validation-report.md`
  - ✅ All internal links verified: `../domain/file.md` format working
  - ✅ Broken links identified (if any) with file and line number
  - ✅ Zero critical broken links (all cross-references working)
  - ✅ External links validated (if applicable)
- **Exact Commands**:
  ```bash
  # Option 1: Using markdown-link-check
  find docs -name "*.md" -type f | xargs markdown-link-check > .kiro/link-validation-report.md
  
  # Option 2: Using remark with remark-validate-links
  remark docs --use validate-links > .kiro/link-validation-report.md
  ```
- **Estimated Time**: 1 hour (including script setup if needed)
- **Dependencies**: Phase 3 complete (all files migrated)
- **Rollback**: No rollback (validation only); fix broken links if found

### 4.2 Verify Metadata Completeness
- **Task**: Verify all non-README files have complete and valid YAML frontmatter
- **Acceptance**:
  - ✅ Metadata verification script: `.kiro/verify-metadata.py` checks all files
  - ✅ Report: `.kiro/metadata-verification-report.md`
  - ✅ Required fields present in all files: domain, purpose, version, updated, owner
  - ✅ No malformed YAML (all frontmatter is valid YAML)
  - ✅ All files validated, count = expected (26+ non-README files in docs/)
  - ✅ Archive files have additional fields: archived, archived_date, archive_reason
  - ✅ 100% metadata coverage (no files missing frontmatter)
- **Exact Commands**:
  ```bash
  python3 << 'EOF'
  import os, yaml, re
  
  report = []
  for root, dirs, files in os.walk("docs"):
      for file in files:
          if not file.endswith(".md") or file == "README.md":
              continue
          path = os.path.join(root, file)
          with open(path) as f:
              content = f.read()
          
          if content.startswith("---"):
              try:
                  _, fm, _ = content.split("---", 2)
                  meta = yaml.safe_load(fm)
                  required = ["domain", "purpose", "version", "updated", "owner"]
                  missing = [k for k in required if k not in meta]
                  report.append({"file": path, "status": "valid" if not missing else "missing:" + str(missing), "fields": len(meta)})
              except:
                  report.append({"file": path, "status": "invalid YAML"})
          else:
              report.append({"file": path, "status": "no frontmatter"})
  
  with open(".kiro/metadata-verification-report.md", "w") as out:
      out.write("# Metadata Verification Report\n\n")
      for r in report:
          out.write(f"- {r['file']}: {r['status']} ({r.get('fields', 0)} fields)\n")
  EOF
  ```
- **Estimated Time**: 1 hour
- **Dependencies**: Phase 3 complete (all files migrated with frontmatter)
- **Rollback**: Fix frontmatter in files; no rollback needed

### 4.3 Verify Folder Organization Rules Compliance
- **Task**: Verify each domain folder follows organization rules (max files, sub-folder patterns)
- **Acceptance**:
  - ✅ Folder structure validation: `.kiro/folder-structure-validation.md`
  - ✅ Each domain folder checked: count of direct files vs. max threshold
  - ✅ No domain exceeds 15 direct files (or has documented reason for exceeding)
  - ✅ Sub-folder patterns verified (if any domain has subfolders)
  - ✅ Each README documents Folder Organization Rules correctly
  - ✅ File naming patterns consistent within each domain
  - ✅ No orphaned files (all files in expected domain folders)
- **Exact Commands**:
  ```bash
  for dir in docs/{api,ui,ux,auth,database,testing,deployment,business,development,decisions,performance,archive}; do
    count=$(find $dir -maxdepth 1 -name "*.md" -not -name "README.md" | wc -l)
    echo "$dir: $count files"
  done > .kiro/folder-file-count.txt
  ```
- **Estimated Time**: 1 hour
- **Dependencies**: Phase 3 complete
- **Rollback**: None (validation only); reorganize if needed

### 4.4 Validate Consolidations (Word Count & Content Check)
- **Task**: Verify that consolidated files contain all content from source files
- **Acceptance**:
  - ✅ Consolidation validation: `.kiro/consolidation-validation.md`
  - ✅ 3 consolidations verified:
    1. **ui-ux-standards.md**: Contains full content from UI_UX_STANDARDS.md + UI_UX_STANDARDS_INDEX.md navigation
    2. **accessibility-test-results.md**: Contains sections from KEYBOARD_NAVIGATION_TEST_RESULTS.md + TESTING_FOCUS_INDICATORS.md
    3. (Any others from Phase 3)
  - ✅ Word count check: new consolidated file word count ≥ sum of source files (minus redundancy)
  - ✅ Section headers preserved and organized
  - ✅ No content loss detected
  - ✅ Cross-references from sources updated to new file
- **Exact Commands**:
  ```bash
  # Example: check ui-ux-standards.md contains content from both sources
  wc -w docs/ui/ui-ux-standards.md
  # Should be roughly: standards words + index words - overlap
  ```
- **Estimated Time**: 1.5 hours (manual spot-check of consolidations)
- **Dependencies**: Phase 3 complete (consolidations done)
- **Rollback**: Can restore from backup if consolidation incomplete

### 4.5 Manual Spot-Check (15+ Documents)
- **Task**: Manually review representative files from each domain for quality and organization
- **Acceptance**:
  - ✅ Spot-check completed: 15+ files reviewed from all 12 domains
  - ✅ Files checked (1-2 per domain minimum):
    - api/endpoints.md (API domain)
    - ui/design-system.md (UI domain)
    - ux/implementation-guide.md (UX domain)
    - auth/authentication.md (Auth domain)
    - database/schema-overview.md (Database domain)
    - testing/testing-framework.md (Testing domain)
    - deployment/deployment-pipeline.md (Deployment domain)
    - business/product-specifications.md (Business domain)
    - development/coding-standards.md (Development domain)
    - decisions/0-architecture-overview.md (Decisions domain)
    - performance/lighthouse-audit-report.md (Performance domain)
    - archive/wave-1-completion.md (Archive domain)
    - docs/README.md (Root navigation)
  - ✅ For each file: frontmatter valid, content readable, section structure clear
  - ✅ Cross-domain links spot-checked: working and relative paths correct
  - ✅ Quality issues noted: typos, broken formatting, unclear sections
  - ✅ Spot-check report: `.kiro/spot-check-report.md`
- **Estimated Time**: 2 hours (manual review)
- **Dependencies**: Phase 3 & 4.1-4.4 complete
- **Rollback**: Fix issues found; no rollback needed

### 4.6 Verify Relative Path Convention
- **Task**: Ensure all cross-domain links use correct relative path format
- **Acceptance**:
  - ✅ Path convention verification: `.kiro/relative-path-audit.md`
  - ✅ Sample cross-links verified:
    - `api/` → `auth/` links use: `../auth/file.md`
    - `ui/` → `api/` links use: `../api/file.md`
    - Within same domain links use: `./other-file.md`
  - ✅ No absolute paths found (e.g., `/docs/` paths)
  - ✅ No broken relative paths (all `../` and `./` correct)
  - ✅ Links tested: click or validate in markdown viewer
- **Estimated Time**: 1.5 hours
- **Dependencies**: 4.1 (link validation)
- **Rollback**: Fix path format in files; no rollback needed

### 4.7 Phase 4 Quality Gate Sign-Off
- **Task**: Review all validation reports and approve readiness for Phase 5 (final commit)
- **Acceptance**:
  - ✅ Link validation: zero critical broken links
  - ✅ Metadata: 100% file coverage, all required fields present
  - ✅ Folder organization: all domains compliant with rules
  - ✅ Consolidations: verified complete content
  - ✅ Spot-check: 15+ files reviewed, quality acceptable
  - ✅ Relative paths: all cross-links using correct format
  - ✅ Summary report: `.kiro/phase-4-quality-gate-summary.md`
  - ✅ Green light: proceed to Phase 5 (final commit)
  - ✅ Known issues (if any): tracked for Phase 5 or post-migration
- **Estimated Time**: 30 minutes
- **Dependencies**: 4.1-4.6 (all validation complete)
- **Rollback**: None (sign-off only); fix issues if quality gate fails

**Phase 4 Status**: ✅ COMPLETE (Day 8)

---

## PHASE 5: FINAL COMMIT & DOCUMENTATION (Day 9, 6 tasks)

### 5.1 Stage All Changes for Commit
- **Task**: Stage all documentation restructuring changes for a single comprehensive commit
- **Acceptance**:
  - ✅ All new files staged: `git add docs/` (all domain folders and files)
  - ✅ All moved files tracked: `git status` shows moved files (from `git mv`)
  - ✅ Deleted old files tracked: `git status` shows deletions (archived files)
  - ✅ No untracked files in docs/ (except backup, which is .gitignore'd)
  - ✅ Staging area ready: `git status` shows clear pending commit
  - ✅ Verify: `git diff --cached docs/ | head -50` shows expected changes
- **Exact Commands**:
  ```bash
  git add docs/
  git status
  ```
- **Estimated Time**: 15 minutes
- **Dependencies**: Phase 4 complete (all quality gates passed)
- **Rollback**: `git reset` (unstage changes if needed)

### 5.2 Create Comprehensive Migration Commit Message
- **Task**: Write detailed commit message documenting migration scope and structure
- **Acceptance**:
  - ✅ Commit message follows format:
    ```
    docs: restructure from flat to modular 12-domain architecture
    
    SUMMARY
    -------
    Restructure 40+ documentation files from flat category-based structure to 
    modular, domain-based organization with 12 primary domains:
    api, ui, ux, auth, database, testing, deployment, business, development,
    decisions, performance, archive
    
    CHANGES
    -------
    - Created 12 domain folders with README.md navigation for each
    - Migrated 26+ files using git mv to preserve history
    - Consolidated 2 groups: UI/UX files (merge into ui/ui-ux-standards.md),
      test results (merge into testing/accessibility-test-results.md)
    - Archived 11+ historical files (wave completions, phase summaries, legacy)
    - Added YAML frontmatter to all files for machine-readability
    - Updated internal cross-references to use relative paths
    - Created root docs/README.md with navigation hub and discovery guide
    
    STRUCTURE
    ---------
    docs/
    ├── README.md (navigation hub)
    ├── api/ (API specifications & endpoints)
    ├── ui/ (UI components & design system)
    ├── ux/ (UX patterns & workflows)
    ├── auth/ (authentication & authorization)
    ├── database/ (schema & migrations)
    ├── testing/ (test guidelines & procedures)
    ├── deployment/ (CI/CD & infrastructure)
    ├── business/ (product specs & business logic)
    ├── development/ (setup & standards)
    ├── decisions/ (ADRs & strategic decisions)
    ├── performance/ (optimization & metrics)
    └── archive/ (historical & outdated docs)
    
    MIGRATION DETAILS
    -----------------
    Files Moved: 26+ (using git mv to preserve history)
    Files Consolidated: 2 groups (UI/UX standards, accessibility test results)
    Files Archived: 11+ (wave completions, phase summaries, legacy)
    Metadata Added: YAML frontmatter with domain, purpose, version, owner, related
    Cross-links Updated: Relative paths (../domain/file.md) throughout
    
    BENEFITS
    --------
    - Faster navigation: developers find docs by domain
    - Clearer ownership: each domain has team responsibility
    - Machine-readable: frontmatter enables indexing by tools/AI
    - Maintainable: scalable structure, clear organization rules
    - Traceable: git history preserved for all moved files
    
    VALIDATION
    ----------
    ✅ Link validation: zero broken links
    ✅ Metadata: 100% frontmatter coverage
    ✅ Folder organization: all domains compliant with rules
    ✅ Consolidations: verified complete content
    ✅ Spot-check: 15+ files reviewed
    ✅ Relative paths: all cross-links correct
    
    Related: requirements.md, design.md, AGENTS.md (LAPORIN project conventions)
    ```
  - ✅ Message saved to: `.kiro/commit-message.txt` (for reference)
  - ✅ Message length: clear and comprehensive (provides full context)
- **Estimated Time**: 45 minutes
- **Dependencies**: None (commit message preparation)
- **Rollback**: N/A (message only)

### 5.3 Execute Final Commit
- **Task**: Create the final commit with all migration changes
- **Acceptance**:
  - ✅ Commit created: `git commit -m "[message from 5.2]"`
  - ✅ Commit message from `.kiro/commit-message.txt` or typed directly
  - ✅ Commit includes all staged changes (26+ file moves, 2 consolidations, 11+ archives)
  - ✅ Verify: `git log -1 --oneline` shows migration commit
  - ✅ Verify: `git show --stat` shows file statistics (40+ files affected)
- **Exact Commands**:
  ```bash
  git commit -F .kiro/commit-message.txt
  # OR
  git commit -m "docs: restructure from flat to modular 12-domain architecture

  [Full message from section 5.2]"
  ```
- **Estimated Time**: 10 minutes
- **Dependencies**: 5.1 (all changes staged), 5.2 (commit message ready)
- **Rollback**: `git reset --soft HEAD~1` (undo commit, keep changes staged)

### 5.4 Push Feature Branch to Remote
- **Task**: Push the migration commit to remote repository
- **Acceptance**:
  - ✅ Feature branch pushed: `git push origin feature/docs-restructuring-modular-12domain`
  - ✅ Remote branch now contains all migration commits
  - ✅ Verify: `git log origin/feature/docs-restructuring-modular-12domain` shows commit
  - ✅ Ready for PR/MR review
- **Exact Commands**:
  ```bash
  git push origin feature/docs-restructuring-modular-12domain
  ```
- **Estimated Time**: 5 minutes
- **Dependencies**: 5.3 (commit created)
- **Rollback**: `git push --force-with-lease origin :feature/docs-restructuring-modular-12domain` (delete remote branch if major issues found before PR)

### 5.5 Create Pull Request or Merge Request
- **Task**: Open PR/MR for documentation restructuring on remote repository
- **Acceptance**:
  - ✅ PR/MR opened on: GitHub/GitLab/CodeCommit (as applicable)
  - ✅ PR title: "docs: restructure from flat to modular 12-domain architecture"
  - ✅ PR description includes:
    - Summary of migration
    - Link to migration report
    - List of 12 domains created
    - Consolidations and archives noted
    - Testing completed (link validation, metadata, spot-check)
    - Ready for merge when approved
  - ✅ PR labels: docs, architecture, restructuring
  - ✅ Assigned reviewers (if applicable)
- **Exact Commands**:
  ```bash
  # GitHub CLI example:
  gh pr create --title "docs: restructure from flat to modular 12-domain architecture" \
               --body "See .kiro/migration-completion-report.md and .kiro/phase-4-quality-gate-summary.md for details"
  
  # GitLab CLI example:
  glab mr create --title "docs: restructure from flat to modular 12-domain architecture"
  ```
- **Estimated Time**: 20 minutes
- **Dependencies**: 5.4 (branch pushed)
- **Rollback**: Close PR/MR if major issues; fix issues on branch and force-push

### 5.6 Create Final Migration Summary & Archive
- **Task**: Create comprehensive final summary documenting entire migration process
- **Acceptance**:
  - ✅ Final summary file: `docs/MIGRATION_SUMMARY.md` (or `.kiro/MIGRATION_SUMMARY.md`)
  - ✅ Summary includes:
    - **Migration Overview**: what was done, timeline (5 phases), scope (40+ files)
    - **Structure Changes**: old flat structure → new 12-domain structure (show tree)
    - **File Accounting**: 26 moved | 2 consolidated | 11 archived | 0 lost
    - **Domain Descriptions**: brief description of each 12 domain and key files
    - **Validation Results**: link checks, metadata coverage, spot-checks (with links to detailed reports)
    - **Archive Information**: files archived, reasons, retrieval instructions
    - **How to Navigate**: quick-start guide for finding documentation
    - **Related Documentation**: links to AGENTS.md, design spec, requirements
    - **Timeline**: dates and phase completion
    - **Post-Migration**: any known issues and follow-up tasks
  - ✅ Summary is human-readable and provides complete context
  - ✅ Supporting files organized: `.kiro/phase-*-*.md`, `.kiro/*-report.md`
- **Estimated Time**: 1.5 hours
- **Dependencies**: All phases complete (5.1-5.5)
- **Rollback**: None (summary only)

**Phase 5 Status**: ✅ COMPLETE (Day 9)

---

## ACCEPTANCE CRITERIA: COMPLETE RESTRUCTURING PROJECT

### Project Success Criteria

- ✅ **Folder Structure**: 12 modular domain folders created (api, ui, ux, auth, database, testing, deployment, business, development, decisions, performance, archive)
- ✅ **File Migration**: All 40+ existing files accounted for (26+ moved, 2 consolidated, 11+ archived)
- ✅ **Git History**: All file moves preserved via `git mv` (no lost history)
- ✅ **Cross-References**: All internal links updated to use relative paths; zero broken links
- ✅ **Metadata**: 100% of non-README files have YAML frontmatter with required fields
- ✅ **Organization**: Each domain folder has README.md with clear navigation and organization rules
- ✅ **Navigation**: Root `docs/README.md` provides central hub with discovery guide
- ✅ **Quality**: All validation gates passed (link validation, metadata verification, spot-check)
- ✅ **Documentation**: Migration summary created with complete accounting and post-migration guidance
- ✅ **Ready for Merge**: PR/MR created and passed all quality checks
- ✅ **Zero Data Loss**: All 40+ files present in new structure with content intact

### Post-Migration (After Merge)

1. **Update Any Code References**: Search codebase for hardcoded references to old doc paths (if applicable)
2. **Update External Links**: If docs are linked from external sites, update to new domain structure
3. **Update Team Wiki/Guides**: Update any team documentation that links to `docs/` structure
4. **Monitor & Maintain**: Enforce folder organization rules as new docs are added
5. **Feedback & Iteration**: Collect feedback on new structure; iterate if needed

---

## Summary Statistics

| Phase | Tasks | Duration | Key Deliverables |
|-------|-------|----------|------------------|
| 1: Preparation | 10 | 2-3 days | Backup, inventory, mapping, team notification, templates |
| 2: Folder Setup | 13 | 1 day | 12 folders created, 13 READMEs (root + domains), metadata guide |
| 3: File Migration | 26 | 2-3 days | 26+ files moved, 2 consolidated, 11+ archived, frontmatter added |
| 4: Validation | 7 | 1 day | Link validation, metadata verification, folder compliance, spot-check |
| 5: Final Commit | 6 | 1 day | Comprehensive commit, PR/MR, final summary, migration report |
| **TOTAL** | **58** | **7-9 days** | **All 40+ files restructured, 12 domains operational, zero broken links** |

---

## Dependencies & Sequencing

```
Phase 1 (Prep) → Phase 2 (Folders) → Phase 3 (Migration) → Phase 4 (Validation) → Phase 5 (Commit)
   (linear sequence, each phase depends on previous)
```

- **Phase 1**: No dependencies (can start immediately)
- **Phase 2**: Requires Phase 1 complete (backup, inventory, templates ready)
- **Phase 3**: Requires Phase 2 complete (folders exist before moving files)
- **Phase 4**: Requires Phase 3 complete (all files migrated before validation)
- **Phase 5**: Requires Phase 4 complete (all quality gates passed before final commit)

---

## Rollback Strategy

### Before Final Commit (Phases 1-4)
- Keep backup: `docs.backup-before-restructuring/` (restore with `cp -r docs.backup-before-restructuring/* docs/`)
- Use git: `git status` and `git diff` to see pending changes
- If needed: `git reset --hard` to return to last committed state

### After Final Commit (Phase 5)
- If major issues found before PR merge: `git revert [commit-hash]` to undo entire migration
- If issues found after PR merge: branch `main` and cherry-pick only needed commits
- Backup preserved: `docs.backup-before-restructuring/` can be restored if necessary

---

## Maintenance & Future Updates

### Adding New Documentation
1. Identify appropriate domain folder based on content purpose
2. Check domain README for Folder Organization Rules
3. Create file with frontmatter (follow metadata template)
4. Add to domain's Quick Navigation section in README if major file
5. Update cross-domain links if relevant

### Updating Existing Files
1. Update content as needed
2. Update YAML frontmatter: `updated: [new date]`, `version: [incremented]`
3. Verify internal cross-links still work

### Monitoring & Enforcement
- Run monthly: `npm run markdown:links` to verify no broken links
- Run quarterly: metadata verification script to ensure frontmatter compliance
- Enforce in PR reviews: all new/updated docs must follow domain folder structure and metadata requirements

---

## Files Generated During Process

**Kiro Planning & Verification Files** (.kiro/):
- `.config.kiro` - Spec configuration
- `docs-inventory.json` - File inventory analysis
- `domain-planning.md` - Domain folder planning
- `migration-checklist.md` - Per-file migration tracking
- `templates/` - Frontmatter and README templates
- `link-validation-report.md` - Broken link audit
- `metadata-verification-report.md` - Metadata completeness check
- `folder-structure-validation.md` - Folder organization compliance
- `consolidation-validation.md` - Merged file verification
- `spot-check-report.md` - Manual review of 15+ files
- `relative-path-audit.md` - Cross-domain link verification
- `phase-*-*.md` - Per-phase completion verification
- `migration-completion-report.md` - Full migration accounting
- `phase-4-quality-gate-summary.md` - Final quality gate report
- `commit-message.txt` - Final commit message template

**Documentation Files** (docs/):
- `README.md` - Root navigation hub (updated)
- `12 domain folders/` - New modular structure
- `METADATA_GUIDE.md` - Metadata and frontmatter guide
- `MIGRATION_SUMMARY.md` - Final migration report
- `archive/README.md` - Archive policy and retrieval guide

**Backup** (local, not committed):
- `docs.backup-before-restructuring/` - Original docs structure (safety backup)

---

## Success Metrics & KPIs

| Metric | Target | Pass/Fail |
|--------|--------|-----------|
| Files migrated | 40+ files | ✅ (26 moved, 2 consolidated, 11+ archived = 40+) |
| Domain folders created | 12 | ✅ (api, ui, ux, auth, database, testing, deployment, business, development, decisions, performance, archive) |
| Broken links | 0 | ✅ (link validation passed) |
| Metadata coverage | 100% | ✅ (all non-README files have frontmatter) |
| Git history preserved | 100% | ✅ (used git mv for all moves) |
| Consolidations complete | 2 groups merged | ✅ (UI/UX standards, test results) |
| Navigation clarity | 12 domains clearly documented | ✅ (READMEs + root hub) |
| Quality gates passed | All 7 validation checks | ✅ (link, metadata, folder org, consolidation, spot-check, paths, sign-off) |

