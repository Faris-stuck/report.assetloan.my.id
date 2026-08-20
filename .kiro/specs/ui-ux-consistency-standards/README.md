# UI/UX Consistency Standards - Specification Summary

## Overview

Spec ini mendokumentasikan standardisasi pola UI/UX untuk dua komponen kritis dalam aplikasi LAPORIN:

1. **Modal Workflow** - Pola konsisten untuk semua aksi edit/action
2. **Search/Filter** - Pola konsisten untuk tabel/card dengan banyak items

**Tujuan**: Konsistensi UX, kemudahan maintenance, accessible interface.

---

## Current State vs Target State

### Current Situation ❌

| Aspek | Saat Ini |
|-------|----------|
| Edit Pages | Beberapa inline (master data), beberapa modal (users) - TIDAK KONSISTEN |
| Search/Filter | Semua pages TIDAK ADA search/filter |
| Accessibility | Tidak terdokumentasi, tidak konsisten |
| Developer Guide | Tidak ada standard untuk diikuti |

### Target State ✅

| Aspek | Target |
|-------|--------|
| Edit Pages | SEMUA pages menggunakan modal - KONSISTEN |
| Search/Filter | SEMUA pages dengan 20+ items punya search/filter - KONSISTEN |
| Accessibility | Terdokumentasi, focus trap, keyboard navigation bekerja |
| Developer Guide | Clear templates dan examples untuk diikuti |

---

## Documentation Structure

### Phase 1: Understanding (Read These First)

1. **bugfix.md** (This spec's requirements)
   - Bug analysis dan current/expected behavior
   - Acceptance criteria dalam format WHEN/THEN

2. **../../docs/UI_UX_STANDARDS.md** (Complete Design Guide)
   - Modal Workflow standards dengan struktur, accessibility, animations
   - Search/Filter Pattern standards dengan server-side logic
   - Pages to standardize dengan priority
   - Implementation checklist
   - Complete code examples

3. **../../docs/DECISIONS/UI_UX_CONSISTENCY_DECISION.md** (Decision Log)
   - Mengapa keputusan ini dibuat
   - Affected pages dan priority
   - Implementation details
   - Testing strategy
   - Rollback plan

### Phase 2: Implementation (Developer Reference)

4. **tasks.md** (What to Build)
   - Phase-by-phase breakdown
   - Task list untuk setiap page
   - Developer assignments
   - Timeline estimation
   - Success criteria

5. **../../docs/UI_UX_IMPLEMENTATION_GUIDE.md** (How to Build)
   - Copy-paste templates untuk modal pattern
   - Copy-paste templates untuk search/filter
   - Common patterns (single filter, multiple filters, date range, advanced)
   - Troubleshooting guide
   - Routes reference

---

## Key Documents

| Document | Purpose | Audience |
|----------|---------|----------|
| `bugfix.md` | This spec's requirements | Team leads, architects |
| `UI_UX_STANDARDS.md` | Complete design guide | Designers, developers |
| `UI_UX_CONSISTENCY_DECISION.md` | Decision rationale | Decision makers, reviewers |
| `UI_UX_IMPLEMENTATION_GUIDE.md` | Developer quick start | Developers building features |
| `tasks.md` | Implementation roadmap | Project managers, developers |

---

## Implementation Roadmap

### Phase 1: Documentation & Preparation (WEEK 1)
- ✅ Publish UI_UX_STANDARDS.md
- ✅ Publish UI_UX_IMPLEMENTATION_GUIDE.md
- ✅ Publish decision log
- ✅ Create task breakdown
- Developer review & Q&A

### Phase 2: Core Admin Pages (WEEK 2)
- Convert `/admin/master` from inline to modal + add search/filter
- Add search/filter to `/admin/users` (keep modal as-is)
- Apply same pattern to `/admin/qrcodes` and `/admin/audit`
- Testing & QA

### Phase 3: Role-Based Pages (WEEK 3)
- Add search/filter to `/kesiswaan/reports`
- Add search/filter to `/sarpras/reports`
- Testing & QA

### Phase 4: Quality & Training (WEEK 4)
- Accessibility audit
- Performance testing
- Regression testing
- Cross-browser testing
- Developer training & documentation update

---

## Success Criteria

### Functionality
- ✅ All edit/action operations use modal (not inline)
- ✅ All pages with 20+ items have search/filter
- ✅ Search/filter server-side, not client-side
- ✅ Pagination works with filters applied
- ✅ Data persistence correct

### Accessibility
- ✅ Modal focus trap works (Tab/Shift+Tab within modal only)
- ✅ Escape key closes modal
- ✅ All inputs have associated labels
- ✅ Error messages inline and accessible
- ✅ Color contrast meets WCAG AA

### Performance
- ✅ Search on 1000+ records: < 200ms response
- ✅ Database queries indexed for search/filter fields
- ✅ No N+1 queries
- ✅ Pagination max 100 items per page

### Developer Experience
- ✅ Clear templates for new pages to follow
- ✅ Copy-paste examples available
- ✅ No ambiguity in implementation
- ✅ All developers can follow pattern

---

## Pages Affected

### Priority 1 (MUST DO)
- `/admin/users` - Add search/filter (keep modal)
- `/admin/master` - Convert inline → modal + add search/filter
- `/admin/qrcodes` - Convert to modal + add search/filter
- `/admin/audit` - Add search/filter

### Priority 2 (SHOULD DO)
- `/kesiswaan/reports` - Add search/filter
- `/sarpras/reports` - Add search/filter

### Priority 3 (ONGOING)
- All new list/management pages going forward

---

## Key Components

### Frontend Components

1. **x-modal Blade Component** (Already exists)
   - Located: `resources/views/components/modal.blade.php`
   - Features: Focus trap, keyboard nav, transitions
   - Usage: `<x-modal name="edit-resource" focusable>`

2. **Alpine.js State Management**
   - Manages form fields (name, email, status, etc)
   - Methods: openEdit(), resetForm()
   - Triggers modal via `$dispatch('open-modal', 'resource-name')`

3. **Search/Filter Form**
   - GET method (preserves URL for bookmarking)
   - Search input + optional filter dropdowns
   - Submit button + Reset link

### Backend Components

1. **Controller Query Building**
   - Dynamic query construction based on request parameters
   - Server-side filtering (safe, scalable)
   - Paginated results with filter query params preserved

2. **Database Indexing**
   - Search fields must be indexed
   - Example: `school_classes.class_name`, `users.email`
   - Improves search performance on large datasets

---

## Copy-Paste Templates

### Modal Pattern
Full templates in `UI_UX_IMPLEMENTATION_GUIDE.md`:
- Blade view with search/filter + table + modal
- Alpine.js data structure
- Controller logic with query building
- Ready to customize for your page

### Search/Filter Only
For existing modal pages, just add search/filter form above table.

---

## Common Mistakes to Avoid

❌ **DON'T**: Use inline editing in table rows  
✅ **DO**: Use modal for all edit/action operations

❌ **DON'T**: Client-side filtering with JavaScript  
✅ **DO**: Server-side filtering with query parameters

❌ **DON'T**: Lose pagination when filters applied  
✅ **DO**: Use `appends(request()->query())` in pagination links

❌ **DON'T**: Forget accessibility (focus trap, labels)  
✅ **DO**: Test keyboard navigation, use focusable attribute

❌ **DON'T**: Skip database indexing for search fields  
✅ **DO**: Add indexes to frequently searched columns

---

## Testing Checklist

### Each Page Conversion Should Include:

- [ ] Search filters data correctly
- [ ] Filters work individually and combined
- [ ] Modal opens with proper focus
- [ ] Tab/Shift+Tab cycles within modal
- [ ] Escape key closes modal without saving
- [ ] Form validation works (client & server)
- [ ] Submit saves data correctly
- [ ] Pagination preserved with filters
- [ ] Mobile layout responsive
- [ ] Performance acceptable (< 200ms search)

---

## References

### Design System
- `docs/DESIGN.md` - Color tokens, spacing, components
- `docs/CODING_STANDARDS.md` - PHP, Blade, JavaScript standards

### Examples in Codebase
- `resources/views/admin/users/index.blade.php` - Reference: Modal + search pattern
- `resources/views/components/modal.blade.php` - Modal component source

### Related Docs
- `docs/ARCHITECTURE.md` - Overall system design
- `docs/TESTING.md` - Testing practices

---

## Questions & Support

### For Design Questions
See `docs/UI_UX_STANDARDS.md` Part 1-2

### For Implementation Questions
See `docs/UI_UX_IMPLEMENTATION_GUIDE.md`

### For Decision Rationale
See `docs/DECISIONS/UI_UX_CONSISTENCY_DECISION.md`

### For Task Breakdown
See `tasks.md`

---

## Related Decision Logs

This spec builds on several decisions:

1. **Technology Stack** - Laravel 12, Blade, Bootstrap 5, Alpine.js (from `AGENTS.md`)
2. **Design Tokens** - Green color scheme, motion intensity low (from `docs/DESIGN.md`)
3. **Accessibility** - WCAG AA compliance, keyboard navigation required (from `docs/DESIGN.md`)

---

## Version History

| Version | Date | Change |
|---------|------|--------|
| 1.0 | 2025 | Initial spec: modal + search/filter standards |

---

## Spec Status

- ✅ Requirements defined (bugfix.md)
- ✅ Standards documented (UI_UX_STANDARDS.md)
- ✅ Decision approved (UI_UX_CONSISTENCY_DECISION.md)
- ✅ Implementation guide created (UI_UX_IMPLEMENTATION_GUIDE.md)
- ✅ Tasks defined (tasks.md)
- ⏳ Implementation starting (Phase 1)

---

**Next Steps**: 
1. Developer review of standards & templates
2. Start Phase 1 conversion of core admin pages
3. QA testing & accessibility audit
4. Publish developer training & update docs
