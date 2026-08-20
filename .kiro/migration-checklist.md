# File Migration Checklist: 39 Documentation Files

**Total Files**: 39  
**Move Operations**: 25  
**Consolidations**: 3 (2+2+varies)  
**Archive Operations**: 11  

---

## API Domain (4 files)

- [ ] API.md → api/endpoints.md (MOVE) 
- [ ] (via audit) API endpoints documented in new location
- [ ] (via audit) Relative links updated from API domain

---

## AUTH Domain (3 files)

- [ ] AUTH.md → auth/authentication.md (MOVE)
- [ ] SECURITY.md → auth/security-practices.md (MOVE)
- [ ] SECURITY_ENTERPRISE.md → auth/enterprise-security.md (MOVE)
- [ ] Verify RBAC documentation in place
- [ ] Cross-links to api/, database/ updated

---

## UI Domain (6 files)

- [ ] DESIGN.md → ui/design-system.md (MOVE)
- [ ] ACCESSIBILITY_COMPLIANCE_REPORT.md → ui/accessibility-report.md (MOVE)
- [ ] ARIA_LABEL_CONVENTION.md → ui/aria-conventions.md (MOVE)
- [ ] UI_UX_STANDARDS.md → ui/ui-ux-standards.md (MOVE)
- [ ] UI_UX_STANDARDS_INDEX.md → Merge into ui-ux-standards.md (CONSOLIDATE)
- [ ] Verify consolidated file has Quick Navigation section
- [ ] Cross-links to testing/, ux/ updated

---

## UX Domain (3 files)

- [ ] UI_UX_IMPLEMENTATION_GUIDE.md → ux/implementation-guide.md (MOVE)
- [ ] UI_UX_QUICK_REFERENCE.md → ux/quick-reference.md (MOVE)
- [ ] Create placeholder docs/ux/user-workflows.md (if needed) or link existing
- [ ] Cross-links to ui/, business/ updated

---

## DATABASE Domain (1 file)

- [ ] DATABASE.md → database/schema-overview.md (MOVE)
- [ ] Verify cross-links to auth/, deployment/, testing/

---

## TESTING Domain (6 files)

- [ ] TESTING.md → testing/testing-framework.md (MOVE)
- [ ] CONSISTENCY_CHECKLIST.md → testing/consistency-checklist.md (MOVE)
- [ ] IMPLEMENTATION_TEST_CHECKLIST.md → testing/implementation-test-checklist.md (MOVE)
- [ ] KEYBOARD_NAVIGATION_TEST_RESULTS.md + TESTING_FOCUS_INDICATORS.md → testing/accessibility-test-results.md (CONSOLIDATE)
- [ ] Verify consolidated file has separate sections: Keyboard Navigation | Focus Indicators | Mobile Device Testing
- [ ] MOBILE_DEVICE_TESTING_RESULTS.md → testing/mobile-device-test-results.md (MOVE)
- [ ] Cross-links to all domains verified

---

## DEPLOYMENT Domain (1 file)

- [ ] DEPLOYMENT.md → deployment/deployment-pipeline.md (MOVE)
- [ ] Cross-links to all domains updated

---

## BUSINESS Domain (4 files)

- [ ] BUSINESS_RULES.md → business/business-rules.md (MOVE)
- [ ] PRODUCT.md → business/product-specifications.md (MOVE)
- [ ] spec-4-role-workflow.md → business/role-workflow-specification.md (MOVE)
- [ ] Create placeholder docs/business/compliance-requirements.md or link existing
- [ ] Cross-links to auth/, api/ updated

---

## DEVELOPMENT Domain (2 files)

- [ ] CODING_STANDARDS.md → development/coding-standards.md (MOVE)
- [ ] FUTURE_PAGES_IMPLEMENTATION_GUIDE.md → development/future-features-guide.md (MOVE)
- [ ] Create placeholder docs/development/local-development-setup.md (or link)
- [ ] Cross-links to testing/ updated

---

## DECISIONS Domain (3 ADR files)

- [ ] ARCHITECTURE.md → decisions/0-architecture-overview.md (MOVE as ADR)
- [ ] DECISIONS/ADR-001-tech-stack.md → decisions/1-tech-stack.md (RENUMBER if needed)
- [ ] DECISIONS/ADR-002-authentication.md → decisions/2-authentication.md (RENUMBER if needed)
- [ ] DECISIONS/UI_UX_CONSISTENCY_DECISION.md → decisions/3-ui-ux-consistency.md (RENUMBER if needed)
- [ ] Verify ADRs use sequential numbering (0-*, 1-*, 2-*, etc)
- [ ] Add YAML frontmatter: domain: decisions, purpose: adr, decision_date, status

---

## PERFORMANCE Domain (1 file)

- [ ] LIGHTHOUSE_AUDIT_RESULTS.md → performance/lighthouse-audit-report.md (MOVE)
- [ ] Create placeholders or links: performance-targets.md, profiling-procedures.md, caching-strategies.md
- [ ] Verify not marked as archived (archived: false or absent)

---

## ARCHIVE Domain (11 files) ⚠️ HISTORICAL DOCS

- [ ] CHANGELOG.md → archive/CHANGELOG-legacy.md (ARCHIVE: historical release notes)
- [ ] CONSISTENCY_VERIFICATION_COMPLETE.md → archive/verification-phase-complete.md (ARCHIVE: phase completion)
- [ ] IMPLEMENTATION_CHANGES_DETAILED.md → archive/implementation-changes-wave-detailed.md (ARCHIVE: implementation tracking)
- [ ] IMPLEMENTATION_PHASE_1_2_SUMMARY.md → archive/implementation-phase-summary.md (ARCHIVE: phase documentation)
- [ ] MOBILE_OPTIMIZATION_COMPLETION_SUMMARY.md → archive/mobile-optimization-summary.md (ARCHIVE: phase completion)
- [ ] TASKS_17_23_COMPLETION_SUMMARY.md → archive/tasks-completion-summary.md (ARCHIVE: task tracking)
- [ ] WAVE1_COMPLETION_CHECKLIST.md → archive/wave-1-completion.md (ARCHIVE: wave 1)
- [ ] WAVE2_COMPLETION_CHECKLIST.md → archive/wave-2-completion.md (ARCHIVE: wave 2)
- [ ] WAVE3_COMPLETION_CHECKLIST.md → archive/wave-3-completion.md (ARCHIVE: wave 3)
- [ ] WAVE4-7_IMPLEMENTATION_CHECKLIST.md → archive/wave-4-7-implementation.md (ARCHIVE: waves 4-7)
- [ ] All archived files have YAML frontmatter: archived: true, archived_date, archive_reason, archive_category

---

## ✅ Phase 3 Completion Checklist

### File Movement Verification
- [ ] All 25 move operations completed (git mv used)
- [ ] All 3 consolidation groups completed
- [ ] All 11 archive files moved with metadata

### Metadata Verification
- [ ] 100% of non-README files have YAML frontmatter
- [ ] All frontmatter contains: domain, purpose, version, updated, owner
- [ ] All archive files have: archived, archived_date, archive_reason, archive_category
- [ ] No files missing frontmatter

### Link Verification
- [ ] All internal links use relative paths (../domain/file.md format)
- [ ] No broken anchor links (#section-header)
- [ ] Cross-domain links tested and working

### Organization Verification
- [ ] No domain exceeds 15 direct files (excluding sub-folders)
- [ ] README.md exists in all 12 domain folders
- [ ] Folder naming convention: lowercase, hyphenated (api, ui, ux, auth, database, etc)
- [ ] File naming convention: PURPOSE[-VARIANT].md (e.g., endpoints.md, security-practices.md)
- [ ] Decisions folder: files numbered 0-*, 1-*, 2-*, etc.

### Cross-Reference Verification
- [ ] All domain README.md files list related domains
- [ ] "See Also" sections used instead of circular hard links
- [ ] No broken cross-domain references
- [ ] Archive files not linked from active docs (except in ADRs)

---

## Notes & Sub-Tasks

### Consolidation Sub-Tasks

**Sub-Task 1: Accessibility Testing Consolidation**
- [ ] Extract sections from KEYBOARD_NAVIGATION_TEST_RESULTS.md
- [ ] Extract sections from TESTING_FOCUS_INDICATORS.md
- [ ] Create unified structure in accessibility-test-results.md
- [ ] Verify all content preserved
- [ ] Add consolidation note in metadata

**Sub-Task 2: UI/UX Standards Consolidation**
- [ ] Extract index content from UI_UX_STANDARDS_INDEX.md
- [ ] Move to "Quick Navigation" section in ui-ux-standards.md
- [ ] Verify cross-links preserved
- [ ] Add consolidation note in metadata

**Sub-Task 3: Decision Records Renumbering (if needed)**
- [ ] Check existing ADR numbering
- [ ] Renumber sequentially if gaps exist
- [ ] Update links/metadata for any renumbered files

### Validation Sub-Tasks

- [ ] Run markdown link checker: `npm run markdown:links`
- [ ] Verify relative path format in all links
- [ ] Spot-check 15+ files for metadata completeness
- [ ] Test navigation from root README.md
- [ ] Verify domain README.md files have required sections

---

## Status Legend

| Symbol | Meaning |
|--------|---------|
| ✅ | Completed successfully |
| ⏳ | In progress |
| ❌ | Blocked or failed |
| ⚠️ | Warning or special note |
| [ ] | Not started (checkbox) |

---

## Final Phase 3 Sign-Off

**All file migrations complete?** [ ] YES  
**All metadata added?** [ ] YES  
**All links verified?** [ ] YES  
**No broken cross-references?** [ ] YES  
**Organization rules followed?** [ ] YES  

**Ready for Phase 4 Validation:** [ ] YES → Proceed to link validation & quality gates

---

*This checklist tracks 39 files across 5 migration strategies (move/consolidate/archive/renumber). Update items as migration progresses through Phase 3.*
