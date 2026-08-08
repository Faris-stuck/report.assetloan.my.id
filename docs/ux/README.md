# UX Documentation

## Purpose

Domain ini mendokumentasikan user experience patterns, workflows, interaction guidelines, user journeys, dan form design standards. Dokumentasi UX menyediakan higher-level interaction guidance beyond UI components, memandu user experience design dan implementation.

## Quick Navigation

| Dokumen | Purpose | Untuk Siapa | Status |
|---------|---------|-----------|--------|
| [implementation-guide.md](./implementation-guide.md) | Comprehensive implementation guide untuk UX patterns | Frontend developers, designers | Stable |
| [quick-reference.md](./quick-reference.md) | Quick reference untuk common UX patterns | Frontend developers, designers | Stable |
| [user-workflows.md](./user-workflows.md) | User workflows dan interaction flows | Product team, designers | Stable |
| [form-design-patterns.md](./form-design-patterns.md) | Form design standards dan patterns | Frontend developers, designers | Stable |

## Folder Contents

### Primary Documents
- **implementation-guide.md** - Complete implementation guide untuk UX patterns dan workflows
- **quick-reference.md** - Quick reference untuk common UX patterns dan solutions
- **user-workflows.md** - User workflows, user journeys, interaction flows
- **form-design-patterns.md** - Form design patterns, validation, error handling

### Reference & Quick Lookups
- Semua READMEs dalam folder ini adalah navigation files

## Folder Organization Rules

### When to Add New Files

Tambahkan file baru ketika:
- Workflow category baru memerlukan dokumentasi independen (e.g., `checkout-flow.md`)
- Expected size melebihi 300 lines di existing document
- Topic adalah distinct dan independent dari existing files

Extend existing file ketika:
- Content berhubungan dengan existing pattern
- Section bisa logically ditambahkan ke existing document
- Updates hanya ke existing document yang dibutuhkan

### File Naming Convention

**Pattern**: `PURPOSE[-VARIANT].md`

**Rules**:
- Lowercase letters only
- Gunakan hyphens untuk separate words

**Examples**:
- `implementation-guide.md`
- `quick-reference.md`
- `user-workflows.md`
- `form-design-patterns.md`
- `checkout-flow.md`
- `modal-patterns.md`

### Folder Growth

**Direct file limit**: 6 files maximum

**Sub-folder naming pattern**: `ux-[category]/`

## Related Domains

- **[ui/](../ui/)** - UI components dan design system yang implement UX patterns
- **[business/](../business/)** - Product specifications yang inform user flows
- **[testing/](../testing/)** - Usability testing procedures
- **[development/](../development/)** - Development setup untuk UX implementation

## Getting Started

### Untuk Frontend Developers

Mulai dengan:
1. Baca [quick-reference.md](./quick-reference.md) untuk common patterns
2. Tinjau [implementation-guide.md](./implementation-guide.md) untuk implementation details
3. Referensi [user-workflows.md](./user-workflows.md) untuk user flow context
4. Ikuti [form-design-patterns.md](./form-design-patterns.md) untuk form implementation

### Untuk Designers

Mulai dengan:
1. Baca [user-workflows.md](./user-workflows.md) untuk user flows
2. Tinjau [form-design-patterns.md](./form-design-patterns.md) untuk form design
3. Referensi [quick-reference.md](./quick-reference.md) untuk patterns
4. Implementasikan per [ui/design-system.md](../ui/design-system.md)

### Untuk Product/UX Team

Mulai dengan:
1. Baca [user-workflows.md](./user-workflows.md) untuk user flows
2. Tinjau [implementation-guide.md](./implementation-guide.md) untuk feasibility
3. Referensi [form-design-patterns.md](./form-design-patterns.md) untuk forms
4. Konsultasi [business/product-specifications.md](../business/product-specifications.md)

## Search Tips

| Pertanyaan | Jawaban |
|-----------|---------|
| Bagaimana flow user? | [user-workflows.md](./user-workflows.md) |
| Apa interaction pattern? | [quick-reference.md](./quick-reference.md) |
| Bagaimana design form? | [form-design-patterns.md](./form-design-patterns.md) |
| Bagaimana implement pattern ini? | [implementation-guide.md](./implementation-guide.md) |
| Apa user journey? | [user-workflows.md](./user-workflows.md) |

## See Also

- [Root Documentation Hub](../README.md) - Overview semua domains
- [../ui/design-system.md](../ui/design-system.md) - Design system untuk implement patterns
- [../business/product-specifications.md](../business/product-specifications.md) - Product requirements
- [../testing/accessibility-testing.md](../testing/accessibility-testing.md) - UX testing procedures

---

*UX domain dipertahankan per AGENTS.md conventions. Lihat juga: design.md, requirements.md*

