---
domain: decisions
purpose: adr
version: 1.0
updated: 2024-01-15
owner: ux-team
decision_date: 2025
status: approved
related:
  - ../ui/ui-ux-standards.md
  - ../ux/implementation-guide.md
  - ../development/coding-standards.md
tags:
  - design
  - ux
  - consistency
  - modal
  - search-filter
  - accessibility
---

# DECISION: UI/UX Consistency Standards for Modal Workflow & Search/Filter

**Date**: 2025  
**Status**: Approved  
**Scope**: All admin pages and role-based list pages

---

## Problem Statement

The application has inconsistent UI/UX patterns that confuse users and complicate maintenance:

1. **Inconsistent Edit/Action Modal Workflow**
   - Some pages use inline editing (master data) without modal
   - Others use modal with Alpine.js (users admin)
   - No documented standard for all pages to follow

2. **Missing Search/Filter Capability**
   - Pages with 20+ records have no search or filter
   - Users must scroll/paginate to find specific records
   - Performance degradation as data volume grows

3. **No Accessibility Documentation**
   - Modal focus management not documented
   - ARIA labels inconsistent
   - Keyboard navigation unclear

---

## Decision

### Modal Workflow: All edit/action operations MUST use modal with Alpine.js state

**Rationale:**
- Consistent UX across all pages
- Better mobile experience (modal vs inline edit on small screens)
- Easier to manage form validation and error display
- Alpine.js is already in use, proven pattern in `/admin/users`

**Implementation:**
- Use existing `x-modal` Blade component (resources/views/components/modal.blade.php)
- Alpine.js manages form state with `x-data` object
- Modal structure: header, body with form, footer with action buttons
- Focus management: auto-focus first input, Tab cycles within modal, Escape closes

### Search/Filter: All pages with 20+ records MUST have search/filter control

**Rationale:**
- Improves UX for data discovery
- Server-side filtering for scalability
- Standard pattern from `/admin/users` can be replicated
- Minimal performance impact if indexed properly

**Implementation:**
- Search box (text input, searches across key fields)
- Optional filter dropdowns (status, role, date range based on page)
- Server-side filtering via query parameters
- Pagination continues to work with filters applied
- Reset button to clear all filters

---

## Affected Pages

### Priority 1: Core Admin (must standardize in Phase 1)
1. `/admin/master` → Convert inline to modal + add search/filter
2. `/admin/users` → Keep modal, add search/filter
3. `/admin/qrcodes` → Add modal for edit + search/filter
4. `/admin/audit` → Add search/filter

### Priority 2: Role Pages (Phase 2)
1. `/kesiswaan/reports` → Add search/filter
2. `/sarpras/reports` → Add search/filter

### Priority 3: New Pages (going forward)
- All new list/management pages MUST follow this standard

---

## Implementation Details

### Modal Pattern

**Minimum Required:**
- Blade component: `<x-modal name="edit-{resource}" focusable>`
- Alpine.js data for form fields
- Form method POST/PUT with @csrf
- Modal header with title and helper text
- Modal footer with Batal/Simpan buttons
- Error display inline with `is-invalid` class

**Example in:** `/admin/users/index.blade.php` (reference)

### Search/Filter Pattern

**Minimum Required:**
- Form with GET method (preserves URL for bookmarking)
- Search input: name="search"
- Filter selects: name="status", name="role", etc
- Submit button and Reset link
- Server handles: `request('search')`, `request('status')`, etc
- Results display total count
- Pagination with appends(request()->query())

**Example to create:** `/admin/master/index.blade.php` (convert inline to modal + add search)

### Server-side Implementation

**Controller Query Building:**
```php
$query = Resource::query();

if (request('search')) {
    $query->where('name', 'like', "%{request('search')}%");
}

if (request('status') && in_array(request('status'), ['active', 'inactive'])) {
    $query->where('is_active', request('status') === 'active');
}

$resources = $query->paginate(15);
```

---

## Accessibility Requirements

### Modal
- Focus trap: Tab/Shift+Tab cycle only within modal
- Focus auto-move to first input on open
- Escape key closes modal
- All inputs have associated `<label>` with `for` attribute
- Error messages in `invalid-feedback` div

### Search/Filter
- All inputs/selects have associated labels
- Helper text explains search scope
- Filter controls clearly labeled
- Results count displayed

---

## Migration Path

### Phase 1: Documentation & Core Pages (Week 1-2)
- ✅ Publish UI/UX_STANDARDS.md (this week)
- Convert `/admin/master` to modal + search/filter
- Add search/filter to `/admin/users`
- Apply to `/admin/qrcodes` and `/admin/audit`

### Phase 2: Role Pages (Week 3)
- Add search/filter to `/kesiswaan/reports`
- Add search/filter to `/sarpras/reports`

### Phase 3: Maintenance & New Features (Ongoing)
- All new pages auto-follow standard
- Review existing pages during refactoring

---

## Testing Strategy

### Functional Testing
- [ ] Modal opens/closes properly
- [ ] Form validation works inline
- [ ] Submit updates database correctly
- [ ] Search filters results (server-side)
- [ ] Multiple filters work together
- [ ] Reset button clears all filters
- [ ] Pagination works with filters applied

### Accessibility Testing
- [ ] Keyboard navigation: Tab, Shift+Tab, Escape
- [ ] Focus management: auto-focus input on modal open
- [ ] Screen reader: labels, error messages announced
- [ ] Color contrast: meets WCAG AA

### Performance Testing
- [ ] Search with 1000+ records completes < 200ms
- [ ] Pagination with filters doesn't exceed 100 items per page
- [ ] Database queries use indexed columns

---

## Rollback Plan

If significant issues found during implementation:
1. Halt migration at current phase
2. Keep existing inline editing as fallback
3. Review with team before continuing
4. Update documentation with learnings

---

## References

- **UI_UX_STANDARDS.md**: Complete implementation guide
- **DESIGN.md**: Design tokens and principles
- **CODING_STANDARDS.md**: Code guidelines
- **views/admin/users/index.blade.php**: Reference implementation
- **components/modal.blade.php**: Modal component source

---

## Approval

- Product: Approved ✅
- Development: Ready to implement ✅
- QA: Testing plan reviewed ✅

**Next Step**: Implement Phase 1 starting with `/admin/master` conversion
