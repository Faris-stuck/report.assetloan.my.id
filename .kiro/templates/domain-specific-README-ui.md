# UI Domain README Template

This is a domain-specific template for `docs/ui/README.md`. Copy and customize for the UI domain.

---

# UI Documentation

## Purpose

This folder documents all UI components, design system, design tokens (colors, spacing, typography, shadows), accessibility standards (WCAG compliance, ARIA conventions), responsive design breakpoints, and UI consistency guidelines. UI documentation guides designers and frontend developers on component usage, design consistency, and accessibility compliance.

## Quick Navigation

| Document | Purpose | For Whom |
|----------|---------|----------|
| [design-system.md](./design-system.md) | Component library overview with usage examples | Frontend developers, designers |
| [design-tokens.md](./design-tokens.md) | Color palette, spacing scale, typography, shadows | Designers, frontend developers |
| [accessibility-standards.md](./accessibility-standards.md) | WCAG guidelines, accessible component patterns | All developers, designers |
| [aria-conventions.md](./aria-conventions.md) | ARIA labeling standards and naming patterns | Frontend developers, QA |
| [ui-ux-standards.md](./ui-ux-standards.md) | Comprehensive UI/UX design guidelines | Designers, frontend developers |
| [accessibility-report.md](./accessibility-report.md) | WCAG compliance audit results and findings | QA, accessibility team |

## Folder Organization Rules

### When to Add New Files

Add new file when:
- New component category needs documentation (e.g., `modals.md`, `navigation.md`)
- Design system feature requires standalone reference (e.g., `responsive-breakpoints.md`)
- Accessibility topic warrants independent documentation

Extend existing file when:
- Variant of existing component
- Additional example of existing pattern
- New guidance related to existing topic

### File Naming Convention

**Pattern**: `COMPONENT-TYPE.md` or `PURPOSE.md`  
**Examples**:
- `design-system.md` (main component library)
- `design-tokens.md` (colors, spacing, typography)
- `accessibility-standards.md`
- `aria-conventions.md`
- `buttons.md` (if components/ subfolder created)
- `forms.md` (if components/ subfolder created)
- `responsive-breakpoints.md`
- `dark-mode.md` (if theme variant docs needed)

### Folder Growth

**Current files**: [X] direct markdown files  
**Max threshold**: 10 files  
**Action if exceeded**: Create `components/` or `tokens/` subfolder

**Example future structure** (if 10+ docs):
```
docs/ui/
├── README.md
├── design-system.md
├── design-tokens.md
├── accessibility-standards.md
├── aria-conventions.md
├── ui-ux-standards.md
├── accessibility-report.md
├── components/
│   ├── README.md
│   ├── buttons.md
│   ├── forms.md
│   ├── modals.md
│   ├── navigation.md
│   └── [more component specs...]
└── tokens/
    ├── README.md
    ├── colors.md
    ├── spacing.md
    ├── typography.md
    └── shadows.md
```

## Related Domains

- **[ux/](../ux/)** - Defines user interaction patterns and workflows that UX implements
- **[testing/](../testing/)** - Includes accessibility testing procedures and compliance validation
- **[development/](../development/)** - Coding standards for frontend implementation
- **[business/](../business/)** - Brand and product guidelines
- **[auth/](../auth/)** - Authentication UI patterns for secure login flows

## Getting Started

### For Frontend Developers

1. **Learn system**: [Review design-system.md](./design-system.md) for available components
2. **Get tokens**: [Check design-tokens.md](./design-tokens.md) for colors, spacing, typography
3. **Build accessible**: [Read accessibility-standards.md](./accessibility-standards.md)
4. **Label correctly**: [Follow aria-conventions.md](./aria-conventions.md)
5. **Reference examples**: [Check ui-ux-standards.md](./ui-ux-standards.md) for patterns

### For Designers

1. **Understand system**: [Review design-system.md](./design-system.md)
2. **Use tokens**: [Follow design-tokens.md](./design-tokens.md) for consistency
3. **Design accessible**: [Read accessibility-standards.md](./accessibility-standards.md)
4. **Check guidelines**: [Review ui-ux-standards.md](./ui-ux-standards.md)
5. **Verify compliance**: [Check accessibility-report.md](./accessibility-report.md)

### For QA/Accessibility Testing

1. **Understand standards**: [Review accessibility-standards.md](./accessibility-standards.md)
2. **Test aria**: [Follow aria-conventions.md](./aria-conventions.md)
3. **Reference audit**: [Review accessibility-report.md](./accessibility-report.md)
4. **Check components**: [Use design-system.md](./design-system.md#testing)
5. **Verify compliance**: Cross-check against WCAG guidelines

## Search Tips

| Question | Answer |
|----------|--------|
| How do I use [component]? | [design-system.md](./design-system.md) |
| What color should I use? | [design-tokens.md](./design-tokens.md#colors) |
| What's the spacing scale? | [design-tokens.md](./design-tokens.md#spacing) |
| What font should I use? | [design-tokens.md](./design-tokens.md#typography) |
| How do I make [component] accessible? | [accessibility-standards.md](./accessibility-standards.md) |
| What ARIA label should I use? | [aria-conventions.md](./aria-conventions.md) |
| Is this design WCAG compliant? | [accessibility-report.md](./accessibility-report.md) |
| What responsive breakpoints exist? | [design-tokens.md](./design-tokens.md#responsive) or [accessibility-report.md](./accessibility-report.md) |

### Common Scenarios → Documents

- **"I'm building a new feature UI"** → Start with [design-system.md](./design-system.md), use [design-tokens.md](./design-tokens.md)
- **"My form isn't accessible"** → Check [accessibility-standards.md](./accessibility-standards.md), then [aria-conventions.md](./aria-conventions.md)
- **"What ARIA should I use?"** → [aria-conventions.md](./aria-conventions.md)
- **"Is my design system compliant?"** → [accessibility-report.md](./accessibility-report.md)
- **"How do I implement [pattern]?"** → [ui-ux-standards.md](./ui-ux-standards.md)

## See Also

- [Root Documentation Hub](../README.md) - Overview of all domains
- [../ux/implementation-guide.md](../ux/implementation-guide.md) - UX implementation patterns
- [../testing/accessibility-testing.md](../testing/accessibility-testing.md) - Testing procedures
- [../development/coding-standards.md](../development/coding-standards.md) - Frontend coding standards

---

## Design System Summary

**Components**: [X] documented  
**Color Palette**: [X] colors  
**Spacing Scale**: [X] units  
**Typography**: [X] font families  
**WCAG Level**: [AA|AAA]  
**Browser Support**: [list browsers]  

---

*UI documentation is maintained per AGENTS.md. See design.md for domain structure.*
