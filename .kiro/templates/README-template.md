---
# Generic README template for all domain folders
# Copy and modify this template for each domain's README.md
# NOTE: README.md files do NOT include YAML frontmatter (they are navigation files)

---

# [Domain Name] Documentation

## Purpose

[2-3 sentences explaining why this folder exists, what content it contains, and its role in the system.]

**Example for API domain:**
"This folder documents all API specifications, endpoint definitions, authentication requirements, rate limiting policies, and error handling standards. API documentation serves as the technical contract between frontend/mobile clients and backend services, defining how systems communicate."

## Quick Navigation

| Document | Purpose | Audience | Status |
|----------|---------|----------|--------|
| [file1.md](./file1.md) | [Brief one-line description] | [who should read] | [stable/experimental] |
| [file2.md](./file2.md) | [Brief one-line description] | [who should read] | [stable/experimental] |
| [file3.md](./file3.md) | [Brief one-line description] | [who should read] | [stable/experimental] |

## Folder Contents

### Primary Documents
- **[filename].md** - [Description: what this document covers and why it matters]
- **[filename].md** - [Description]

### Reference & Quick Lookups
- **[quick-reference.md](./quick-reference.md)** - Fast lookup guide for common tasks
- **[cheat-sheet.md](./cheat-sheet.md)** - Condensed reference (if exists)

### Procedural Guides
- **[how-to-guide.md](./how-to-guide.md)** - Step-by-step instructions for [specific task]
- **[setup-procedures.md](./setup-procedures.md)** - Setup and configuration steps

## Folder Organization Rules

### When to Add New Files

Add a new file if:
- Content is large enough (300+ lines) to warrant its own document
- Topic is distinct and independent from existing files
- Multiple developers will reference this content frequently

Extend existing file if:
- Content is related to existing topic (< 200 lines)
- Section can be added to existing document logically
- Updates to existing document only needed

### File Naming Convention

**Pattern**: `PURPOSE[-VARIANT].md`  
**Rules**:
- Lowercase letters only
- Use hyphens to separate words (not underscores or spaces)
- Be descriptive: `endpoints.md`, `error-codes.md`, NOT `api1.md` or `API_SPEC.md`
- No file extensions except .md

**Examples**:
- `endpoints.md` (main endpoint docs)
- `endpoints-pagination.md` (variant with specific focus)
- `authentication-requirements.md`
- `rate-limiting.md`

### Folder Growth & Sub-folders

**Direct file limit**: [X] files maximum  
**Action if exceeded**: Create sub-folder with descriptive name and its own README.md

**Sub-folder naming pattern**: `[domain]-[category]/`  
**Examples**:
- `api/` contains `endpoints/` subfolder if 8+ endpoint docs
- `ui/` contains `components/` subfolder if 10+ component docs

**Sub-folder README requirements**:
- Each sub-folder must have README.md
- Sub-folder README explains relationship to parent domain
- Sub-folder README documents sub-folder organization rules

## Related Domains

Cross-domain relationships (links to other documentation domains):

- **[Domain Name](../other-domain/)** - Brief explanation of relationship (e.g., "provides authentication context for API requests")
- **[Domain Name](../other-domain/)** - Brief explanation of relationship
- **[Domain Name](../other-domain/)** - Brief explanation of relationship

**Note**: Use relative paths (`../domain-name/`) for all cross-domain links.

## Getting Started

### For [Audience Type 1] (e.g., Frontend Developers)

Start with:
1. [Read quick-reference.md](./quick-reference.md) for fast overview
2. [Review endpoints.md](./endpoints.md) for available endpoints
3. [Check authentication-requirements.md](./authentication-requirements.md) for auth setup
4. Refer to [error-codes.md](./error-codes.md) when debugging issues

### For [Audience Type 2] (e.g., API Implementers)

Start with:
1. [Review endpoints.md](./endpoints.md) for endpoint patterns
2. [Check API conventions](./conventions.md) for consistency
3. [Follow authentication-requirements.md](./authentication-requirements.md) for security
4. Use [error-codes.md](./error-codes.md) for standardized error responses

### For [Audience Type 3] (e.g., QA/Testers)

Start with:
1. [Review testing procedures](./testing-procedures.md)
2. [Check error scenarios](./error-codes.md#common-errors)
3. [Follow authentication test cases](./authentication-requirements.md#test-cases)

## Search Tips

**Looking for...?** → **Check...**

| Use Case | Recommended Document | Backup References |
|----------|----------------------|-------------------|
| How do I [specific task]? | [specific-file.md] | [related-doc.md], [../related-domain/file.md] |
| What is [concept]? | [reference-file.md] | [../related-domain/file.md] |
| How do I debug [issue]? | [troubleshooting.md](./troubleshooting.md) | [error-codes.md](./error-codes.md) |
| What are the [standards/rules]? | [standards.md](./standards.md) | [conventions.md](./conventions.md) |
| Where is [item] documented? | See Quick Navigation table above | [../related-domain/README.md] |

### Common Questions Answered

- **"How do I [common task]?"** → [specific-file.md](./specific-file.md#section)
- **"What's the difference between [option A] and [option B]?"** → [comparison-file.md](./comparison-file.md)
- **"Which [concept] should I use?"** → [decision-guide.md](./decision-guide.md)
- **"Where do I [procedure]?"** → [how-to-guide.md](./how-to-guide.md)

## See Also

- [../other-domain/](../other-domain/) - Related domain with complementary information
- [../other-domain/specific-file.md](../other-domain/specific-file.md) - Linked document in another domain
- [Root Documentation Hub](../README.md) - Return to main documentation overview

---

## Document Updates

| Date | Change | Author |
|------|--------|--------|
| 2024-01-15 | Initial structure | [name] |
| [future date] | [update description] | [name] |

---

*This folder structure is maintained per AGENTS.md domain folder conventions. See also: design.md, requirements.md*

