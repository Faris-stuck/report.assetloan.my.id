# Metadata & Frontmatter Reference Guide

## Overview

Dokumentasi content (bukan README.md) menggunakan YAML frontmatter untuk metadata. Guide ini menjelaskan frontmatter structure, domain-specific requirements, dan best practices.

---

## Complete YAML Frontmatter Example

```yaml
---
# Core Metadata
domain: api              # Mandatory: domain folder (api, ui, auth, etc.)
purpose: endpoint-reference  # Mandatory: what this doc does
version: 1.0             # Recommended: semantic version

# Management
updated: 2024-01-15      # Mandatory: last update date (YYYY-MM-DD)
owner: platform-team     # Recommended: owner or team
status: stable           # Recommended: stable|experimental|deprecated|draft

# Organization
related: [auth, database, testing]  # Optional: related domains
keywords: [api, endpoints, rest]    # Optional: search keywords
audience: [developers, qa]          # Optional: intended audience

# Archive (if applicable)
archived: false          # Optional: true if document is archived
archive_reason: null     # Optional: outdated|superseded|experimental|historical
archive_date: null       # Optional: when archived (YYYY-MM-DD)
superseded_by: null      # Optional: link to replacement doc
---
```

---

## Domain-Specific Metadata Requirements

### API Domain

**Mandatory fields**:
```yaml
domain: api
purpose: [endpoint-specs|authentication|rate-limiting|error-handling]
updated: [YYYY-MM-DD]
owner: platform-team
status: [stable|experimental|deprecated]
```

**Examples**:
```yaml
---
domain: api
purpose: endpoint-specs
version: 2.0
updated: 2024-01-15
owner: platform-team
status: stable
related: [auth, database, testing]
---
```

### UI Domain

**Mandatory fields**:
```yaml
domain: ui
purpose: [design-system|design-tokens|accessibility|aria-conventions|standards]
updated: [YYYY-MM-DD]
owner: design-team
status: [stable|experimental|deprecated]
```

**Examples**:
```yaml
---
domain: ui
purpose: design-system
version: 1.5
updated: 2024-01-15
owner: design-team
status: stable
audience: [designers, frontend-developers]
---
```

### UX Domain

**Mandatory fields**:
```yaml
domain: ux
purpose: [workflows|patterns|forms|implementation-guide]
updated: [YYYY-MM-DD]
owner: product-team
status: [stable|experimental|deprecated]
```

### Auth Domain

**Mandatory fields**:
```yaml
domain: auth
purpose: [authentication|authorization|security|enterprise-security]
updated: [YYYY-MM-DD]
owner: security-team
status: [stable|experimental|deprecated]
```

**Examples**:
```yaml
---
domain: auth
purpose: authentication
version: 1.0
updated: 2024-01-15
owner: security-team
status: stable
related: [api, deployment, business]
---
```

### Database Domain

**Mandatory fields**:
```yaml
domain: database
purpose: [schema|migrations|query-optimization|backup-recovery]
updated: [YYYY-MM-DD]
owner: database-team
status: [stable|experimental|deprecated]
```

### Testing Domain

**Mandatory fields**:
```yaml
domain: testing
purpose: [framework|accessibility|procedures|checklists]
updated: [YYYY-MM-DD]
owner: qa-team
status: [stable|experimental|deprecated]
```

### Deployment Domain

**Mandatory fields**:
```yaml
domain: deployment
purpose: [pipeline|environment-config|monitoring|rollback|infrastructure]
updated: [YYYY-MM-DD]
owner: devops-team
status: [stable|experimental|deprecated]
```

### Business Domain

**Mandatory fields**:
```yaml
domain: business
purpose: [product-specs|business-rules|roles|compliance]
updated: [YYYY-MM-DD]
owner: product-team
status: [stable|experimental|deprecated]
```

### Development Domain

**Mandatory fields**:
```yaml
domain: development
purpose: [setup|coding-standards|git-workflow|build-commands]
updated: [YYYY-MM-DD]
owner: platform-team
status: [stable|experimental|deprecated]
```

### Decisions Domain (ADRs)

**Mandatory fields**:
```yaml
domain: decisions
purpose: adr  # Always "adr" for Architecture Decision Records
decision_id: 1  # ADR number
decision_status: [accepted|proposed|deprecated|superseded]
decision_date: [YYYY-MM-DD]
updated: [YYYY-MM-DD]
owner: architecture-team
---
```

**Example**:
```yaml
---
domain: decisions
purpose: adr
decision_id: 1
decision_status: accepted
decision_date: 2024-01-10
updated: 2024-01-15
owner: architecture-team
related: [api, auth, database]
---
```

### Performance Domain

**Mandatory fields**:
```yaml
domain: performance
purpose: [targets|profiling|caching|metrics]
updated: [YYYY-MM-DD]
owner: performance-team
status: [stable|experimental|deprecated]
```

### Archive Domain

**Mandatory fields**:
```yaml
domain: [original-domain]  # Original domain sebelum archiving
purpose: [original-purpose]
archived: true
archive_reason: [outdated|superseded|experimental|historical]
archive_date: [YYYY-MM-DD]
updated: [YYYY-MM-DD]
superseded_by: [link-if-replacement]
---
```

**Example**:
```yaml
---
domain: api
purpose: old-endpoint-specs
archived: true
archive_reason: superseded
archive_date: 2024-01-15
updated: 2024-01-15
superseded_by: ../api/endpoints.md
---
```

---

## Field Reference

### Mandatory Fields

| Field | Values | Purpose |
|-------|--------|---------|
| `domain` | api, ui, ux, auth, database, testing, deployment, business, development, decisions, performance, archive | Identifies domain folder |
| `purpose` | Domain-specific | What document does (endpoint-specs, authentication, schema, etc.) |
| `updated` | YYYY-MM-DD | Last update date for versioning |

### Recommended Fields

| Field | Values | Purpose |
|-------|--------|---------|
| `version` | X.Y.Z (semantic) | Document version for tracking changes |
| `owner` | team-name | Owner/maintainer team |
| `status` | stable, experimental, deprecated, draft | Document status |

### Optional Fields

| Field | Values | Purpose |
|-------|--------|---------|
| `related` | [domain1, domain2] | Related domains for cross-linking |
| `keywords` | [keyword1, keyword2] | Search keywords |
| `audience` | [audience1, audience2] | Intended audience |
| `archived` | true/false | Whether document is archived |
| `archive_reason` | outdated, superseded, experimental, historical | Why archived |
| `archive_date` | YYYY-MM-DD | When archived |
| `superseded_by` | relative-path | Link to replacement |

---

## Common Metadata Patterns

### Quick-Reference Document

```yaml
---
domain: api
purpose: quick-reference
version: 1.0
updated: 2024-01-15
owner: platform-team
status: stable
keywords: [quick, reference, endpoints]
audience: [frontend-developers, mobile-developers]
---
```

### Frequently-Updated Document

```yaml
---
domain: testing
purpose: test-results
version: 1.5
updated: 2024-01-15
owner: qa-team
status: stable
keywords: [testing, results, coverage]
---
```

### Experimental Document

```yaml
---
domain: api
purpose: experimental-batch-api
version: 0.1
updated: 2024-01-15
owner: platform-team
status: experimental
keywords: [batch, experimental, api]
related: [testing, deployment]
---
```

### Cross-Domain Document

```yaml
---
domain: testing
purpose: accessibility-testing
version: 1.0
updated: 2024-01-15
owner: qa-team
status: stable
related: [ui, development, deployment]
audience: [developers, qa, designers]
---
```

### Archived Document

```yaml
---
domain: api
purpose: deprecated-endpoints
archived: true
archive_reason: superseded
archive_date: 2024-01-15
updated: 2024-01-15
superseded_by: ../api/endpoints.md
---
```

---

## Metadata Extraction for AI Tools

Script untuk extract metadata dari documentation:

```python
import re
import yaml

def extract_metadata(file_path):
    """Extract YAML frontmatter from markdown file"""
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # Match frontmatter pattern
    match = re.match(r'^---\n(.*?)\n---\n', content, re.DOTALL)
    if not match:
        return None
    
    frontmatter_text = match.group(1)
    metadata = yaml.safe_load(frontmatter_text)
    return metadata

def get_docs_by_domain(docs_path, domain):
    """Get all documents in a domain"""
    import os
    domain_path = os.path.join(docs_path, domain)
    docs = []
    
    for file in os.listdir(domain_path):
        if file.endswith('.md') and file != 'README.md':
            metadata = extract_metadata(os.path.join(domain_path, file))
            if metadata:
                docs.append({
                    'file': file,
                    'metadata': metadata
                })
    return docs

def get_docs_by_status(docs_path, status):
    """Get all documents dengan status tertentu"""
    import os
    results = []
    
    for domain in os.listdir(docs_path):
        if os.path.isdir(os.path.join(docs_path, domain)):
            for file in os.listdir(os.path.join(docs_path, domain)):
                if file.endswith('.md') and file != 'README.md':
                    metadata = extract_metadata(os.path.join(docs_path, domain, file))
                    if metadata and metadata.get('status') == status:
                        results.append({
                            'domain': domain,
                            'file': file,
                            'metadata': metadata
                        })
    return results
```

---

## Best Practices

### Metadata Consistency

1. **Always include mandatory fields** - domain, purpose, updated
2. **Use consistent date format** - YYYY-MM-DD always
3. **Use lowercase domain names** - api, not API
4. **Use hyphenated purpose values** - endpoint-specs, not endpoint_specs

### Metadata Maintenance

1. **Update `updated` field** whenever document changes
2. **Increment `version`** for significant changes
3. **Change `status` field** if document becomes deprecated
4. **Add `related` domains** untuk cross-linking
5. **Document archive** dengan proper `archive_reason` dan date

### Metadata Accuracy

1. **Accurate `domain`** - harus match folder structure
2. **Descriptive `purpose`** - jelas apa dokumen lakukan
3. **Correct `owner`** - harus update jika ownership changes
4. **Relevant `keywords`** - untuk search dan discovery
5. **Proper `status`** - should reflect actual document state

---

## Troubleshooting

### YAML Parse Errors

**Problem**: Frontmatter tidak parse correctly

**Solution**:
- Verify `---` delimiters di start dan end
- Check indentation (YAML requires consistent spaces)
- Ensure strings with special chars are quoted
- Use YAML validator untuk test frontmatter

### Missing Required Fields

**Problem**: Document missing mandatory fields

**Solution**:
- Add `domain` matching folder structure
- Add `purpose` describing what document does
- Add `updated` dengan current date (YYYY-MM-DD)

### Stale Metadata

**Problem**: Metadata outdated vs actual content

**Solution**:
- Update `updated` field ketika edit document
- Review `status` field - apakah accurate?
- Verify `owner` field - siapa maintain document?
- Update `related` jika relationships changed

---

## Examples by Domain

### Example: API Document

File: `docs/api/endpoints.md`

```yaml
---
domain: api
purpose: endpoint-specs
version: 2.1
updated: 2024-01-15
owner: platform-team
status: stable
related: [auth, database, testing]
keywords: [endpoints, rest, api, requests, responses]
audience: [frontend-developers, mobile-developers, backend-developers]
---

# API Endpoints Reference

[Content...]
```

### Example: UI Document

File: `docs/ui/design-system.md`

```yaml
---
domain: ui
purpose: design-system
version: 1.5
updated: 2024-01-15
owner: design-team
status: stable
related: [ux, testing, development]
keywords: [components, design-system, patterns, ui]
audience: [designers, frontend-developers]
---

# Design System

[Content...]
```

### Example: ADR

File: `docs/decisions/1-authentication-strategy.md`

```yaml
---
domain: decisions
purpose: adr
decision_id: 1
decision_status: accepted
decision_date: 2024-01-10
updated: 2024-01-15
owner: architecture-team
related: [auth, api, database]
---

# 1. Authentication Strategy

[Content...]
```

---

## See Also

- [Root Documentation Hub](./README.md) - Overview semua domains
- [../AGENTS.md](../AGENTS.md) - Workspace conventions
- Domain READMEs - Domain-specific documentation guidelines

---

**Last Updated**: 2024-01-15  
*Metadata guide dipertahankan per AGENTS.md conventions.*

