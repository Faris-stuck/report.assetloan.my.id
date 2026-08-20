# Week 2-3 Mobile UI/UX Optimization Spec

## Quick Overview

This spec completes comprehensive mobile-first responsive design optimization for the LAPORIN app across 12+ pages. Building on Week 1 critical fixes, this phase focuses on:

- **Form Grid Optimization**: Mobile-first `col-12 → col-md-6` responsive pattern
- **Accessibility**: ARIA labels, focus indicators, keyboard navigation
- **File Upload**: Validation, preview, error feedback
- **Touch Targets**: 44x44px minimum with adequate spacing
- **Navigation**: Accessible dropdowns, role-based menus
- **Testing**: iPhone 12 (390px), Samsung Galaxy S21 (360px), landscape orientation
- **Verification**: Lighthouse scores ≥85 Performance, ≥95 Accessibility

## Files in This Spec

1. **requirements.md** - 12 feature areas with testable requirements
2. **design.md** - Design decisions, patterns, implementation guidelines
3. **tasks.md** - 28 executable tasks organized in 7 waves (~37.5 hours total)
4. **.config** - Spec metadata and acceptance criteria
5. **README.md** - This file

## Spec Scope

**Pages Covered**: 20+
- 4 public pages (home, guide, FAQ, tracking)
- 2 auth pages (login, register)
- 3 dashboard pages (main, profile, settings)
- 9 admin pages (users, QR codes, audit log, 6 master data)
- 2 role-specific pages (Kesiswaan, Sarpras)
- 1 report detail page

**Feature Areas**: 12
1. Form grid optimization
2. ARIA labels & button accessibility
3. Focus indicators & keyboard navigation
4. File upload validation & preview
5. Helper text standardization
6. Navigation & menu accessibility
7. Touch target optimization
8. Guest layout & public pages
9. Typography & readability
10. Mobile device testing
11. Lighthouse score optimization
12. Regression testing & coverage

## Key Requirements

### Accessibility
- ✅ WCAG AAA compliance (exceeds AA minimum)
- ✅ Touch targets: 44x44px minimum with 8px spacing
- ✅ Focus indicators: visible `:focus-visible` CSS
- ✅ Keyboard navigation: Tab, Shift+Tab, Escape, Enter
- ✅ ARIA labels: descriptive labels on all action buttons
- ✅ Form labels: all inputs have associated `<label>` elements

### Responsive Design
- ✅ Mobile: 375px-414px (iPhone 12, Samsung Galaxy S21)
- ✅ Tablet: 768px+ (col-md-6 breakpoint)
- ✅ Desktop: 1024px+ (col-lg-4 breakpoint)
- ✅ Landscape: 834px (iPhone 12), 720px (Samsung Galaxy S21)
- ✅ No horizontal scrolling on any breakpoint

### Lighthouse Targets
- ✅ Performance: ≥85
- ✅ Accessibility: ≥95
- ✅ Best Practices: ≥90
- ✅ SEO: ≥90 (public pages)

### Form Grid Pattern
All forms use responsive column pattern:
```html
<div class="row g-3">
  <div class="col-12 col-md-6">
    <!-- Form field -->
  </div>
</div>
```

### File Upload Pattern
- Type validation: JPG, PNG only
- Size validation: max 5MB
- Preview: thumbnail image + file name + clear button
- Server-side re-validation on submit

### Helper Text Pattern
```html
<small class="text-muted">Helper text here</small>
```

### ARIA Labels Pattern
```html
<button aria-label="Edit pengguna {{ $user->name }}">
  <i class="bi bi-pencil" aria-hidden="true"></i>
</button>
```

## Task Waves

**Wave 1** (4.5h) - Foundation & Global Patterns
- Focus indicator CSS
- Helper text standardization
- ARIA label convention
- Guest navbar accessibility

**Wave 2** (6.5h) - Form Grid Optimization
- Public form pages (create report, tracking)
- Admin search/filter forms
- Modal forms
- Report detail page

**Wave 3** (6h) - File Upload & Accessibility
- File upload validation & preview
- ARIA labels on admin buttons
- ARIA labels on dropdown navigation
- Touch target audit

**Wave 4** (5h) - Navigation & Accessibility
- Guest navbar mobile optimization
- Admin dropdown mobile optimization
- Modal focus trap
- Keyboard navigation testing

**Wave 5** (3.5h) - Responsive Layout & Guest Pages
- Guide page optimization
- FAQ page optimization
- Tracking form page optimization

**Wave 6** (2.5h) - Dashboard & Profile Pages
- Main dashboard optimization
- Profile page optimization

**Wave 7** (10h) - Lighthouse & Final Testing
- Lighthouse audit & fixes
- iPhone 12 testing
- Samsung Galaxy S21 testing
- Landscape orientation testing
- Regression testing
- Accessibility compliance verification
- Documentation & final verification

## Timeline

- **Total Hours**: ~37.5 hours
- **Full-Time**: 5-6 days
- **Part-Time**: 2-3 weeks

## Success Criteria

Phase 2-3 complete when ALL of the following are met:

✅ All 28 tasks completed
✅ Lighthouse scores: Performance ≥85, Accessibility ≥95, Best Practices ≥90, SEO ≥90
✅ All 20+ pages tested on iPhone 12 (390px) & Samsung Galaxy S21 (360px)
✅ No horizontal scrolling on mobile
✅ All touch targets ≥44x44px with 8px+ spacing
✅ All keyboard navigation works (Tab, Escape, Enter)
✅ All focus indicators visible and properly contrasted
✅ All form inputs have labels & helper text
✅ All action buttons have aria-label
✅ WCAG AAA compliance (or documented exceptions)
✅ No console errors (JavaScript)
✅ No broken links (all 200 status)
✅ No regressions from Week 1 fixes
✅ Real device testing completed

## Next Steps

1. **Start Wave 1** immediately (no dependencies)
2. **Execute tasks sequentially** following wave dependencies
3. **Test incrementally** after each task completes
4. **Document changes** as you go
5. **Final verification** before marking spec complete

## Notes

- This spec **builds on Week 1** critical mobile fixes (form heights, step dots, checkboxes, admin cards, accordions)
- All changes are **optimization only** (no new features, no breaking changes)
- **Mobile-first approach**: design for smallest screens first, enhance for larger screens
- **Real device testing** mandatory (not just responsive design mode)
- **Indonesian UI copy** preferred (user-friendly, concise, actionable)
- **No dependencies** on other active specs

## Questions?

Refer to:
- **requirements.md** for feature details and acceptance criteria
- **design.md** for implementation patterns and guidelines
- **tasks.md** for specific task descriptions and dependencies

---

**Spec Status**: Ready for execution
**Last Updated**: 2025-08-07
**Phase**: Week 2-3 Mobile UI/UX Optimization
