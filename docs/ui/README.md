# UI Documentation

## Purpose

Domain ini mendokumentasikan UI components, design system, design tokens, accessibility standards, dan compliance documentation. Dokumentasi UI memandu designers dan frontend developers tentang component usage, consistency, dan accessibility best practices.

## Quick Navigation

| Dokumen | Purpose | Untuk Siapa | Status |
|---------|---------|-----------|--------|
| [design-system.md](./design-system.md) | Complete design system dengan components, patterns, usage | Designers, Frontend developers | Stable |
| [design-tokens.md](./design-tokens.md) | Design tokens (colors, spacing, typography, etc.) | Designers, Frontend developers | Stable |
| [accessibility-standards.md](./accessibility-standards.md) | WCAG compliance, accessibility guidelines | All developers, QA testers | Stable |
| [aria-conventions.md](./aria-conventions.md) | ARIA label conventions dan best practices | Frontend developers | Stable |
| [ui-ux-standards.md](./ui-ux-standards.md) | UI/UX consistency standards dan patterns | Designers, Frontend developers | Stable |

## Folder Contents

### Primary Documents
- **design-system.md** - Complete design system documentation dengan components dan patterns
- **design-tokens.md** - Design tokens reference (colors, spacing, typography, shadows, etc.)
- **accessibility-standards.md** - WCAG compliance requirements dan accessibility guidelines
- **aria-conventions.md** - ARIA label conventions dan semantic HTML best practices
- **ui-ux-standards.md** - UI/UX consistency standards, patterns, dan implementation guidelines

### Reference & Quick Lookups
- Semua READMEs dalam folder ini adalah navigation files

## Folder Organization Rules

### When to Add New Files

Tambahkan file baru ketika:
- Component category baru memerlukan dokumentasi independen (e.g., `forms.md`, `buttons.md`)
- Expected size melebihi 300 lines di existing document
- Topic adalah distinct dan independent dari existing files

Extend existing file ketika:
- Content berhubungan dengan existing topic
- Section bisa logically ditambahkan ke existing document
- Updates hanya ke existing document yang dibutuhkan

### File Naming Convention

**Pattern**: `PURPOSE[-VARIANT].md`

**Rules**:
- Lowercase letters only
- Gunakan hyphens untuk separate words
- Deskriptif dan jelas

**Examples**:
- `design-system.md`
- `accessibility-standards.md`
- `aria-conventions.md`
- `design-tokens.md`
- `forms.md`
- `buttons.md`

### Folder Growth

**Direct file limit**: 10 files maximum

**Sub-folder naming pattern**: `ui-[category]/`

**Examples if subfolder needed**:
```
docs/ui/
├── README.md
├── design-system.md
├── design-tokens.md
├── accessibility-standards.md
├── components/
│   ├── README.md
│   ├── buttons.md
│   ├── forms.md
│   ├── cards.md
│   └── [more components...]
└── aria-conventions.md
```

## Related Domains

- **[ux/](../ux/)** - User experience patterns dan interaction guidelines
- **[testing/](../testing/)** - Termasuk accessibility testing procedures
- **[development/](../development/)** - Setup dan coding standards untuk UI development
- **[business/](../business/)** - Product specifications yang inform UI requirements
- **[performance/](../performance/)** - Performance targets untuk UI rendering

## Getting Started

### Untuk UI/Frontend Developers

Mulai dengan:
1. Baca [design-tokens.md](./design-tokens.md) untuk colors, spacing, typography
2. Tinjau [design-system.md](./design-system.md) untuk available components
3. Ikuti [accessibility-standards.md](./accessibility-standards.md) untuk WCAG compliance
4. Gunakan [aria-conventions.md](./aria-conventions.md) untuk semantic markup
5. Referensi [ui-ux-standards.md](./ui-ux-standards.md) untuk consistency

### Untuk Designers

Mulai dengan:
1. Baca [design-system.md](./design-system.md) untuk component library
2. Tinjau [design-tokens.md](./design-tokens.md) untuk visual standards
3. Ikuti [accessibility-standards.md](./accessibility-standards.md) untuk inclusive design
4. Referensi [ui-ux-standards.md](./ui-ux-standards.md) untuk design patterns

### Untuk QA/Testers

Mulai dengan:
1. Baca [accessibility-standards.md](./accessibility-standards.md) untuk test criteria
2. Gunakan [aria-conventions.md](./aria-conventions.md) untuk semantic HTML validation
3. Test design compliance per [design-system.md](./design-system.md)
4. Referensi [ui-ux-standards.md](./ui-ux-standards.md) untuk consistency checks

## Search Tips

| Pertanyaan | Jawaban |
|-----------|---------|
| Apa colors yang digunakan? | [design-tokens.md](./design-tokens.md) |
| Apa spacing/padding standard? | [design-tokens.md](./design-tokens.md) |
| Bagaimana membuat button? | [design-system.md](./design-system.md) |
| Apa accessibility requirement? | [accessibility-standards.md](./accessibility-standards.md) |
| ARIA label apa yang digunakan? | [aria-conventions.md](./aria-conventions.md) |
| Apa design pattern ini? | [ui-ux-standards.md](./ui-ux-standards.md) |
| Consistency standard apa? | [ui-ux-standards.md](./ui-ux-standards.md) |

## See Also

- [Root Documentation Hub](../README.md) - Overview semua domains
- [../ux/README.md](../ux/README.md) - UX patterns dan interaction guidelines
- [../testing/accessibility-testing.md](../testing/accessibility-testing.md) - Accessibility testing procedures
- [../development/coding-standards.md](../development/coding-standards.md) - Frontend coding standards

---

*UI domain dipertahankan per AGENTS.md conventions. Lihat juga: design.md, requirements.md*

