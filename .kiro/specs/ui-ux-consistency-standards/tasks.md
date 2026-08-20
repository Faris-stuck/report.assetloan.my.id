# UI/UX Consistency Standards - Implementation Tasks

## Phase 1: Documentation & Core Pages Setup

### 1.1 Create Reusable Modal Components
- [ ] Review existing `component/modal.blade.php`
- [ ] Document modal.blade.php in UI_UX_STANDARDS.md with component API
- [ ] Create example modal snippets for common patterns
- [ ] Verify focus trap and accessibility features work correctly

### 1.2 Create Reusable Search/Filter Form Component
- [ ] Design search/filter form pattern component
- [ ] Create Blade partial or component for standard search box
- [ ] Create Blade partial for standard filter select
- [ ] Document in UI_UX_STANDARDS.md with copy/paste examples

### 1.3 Document Controller Search/Filter Logic
- [ ] Create helper controller method for common filter patterns
- [ ] Document query building for search and filters
- [ ] Add code comments showing where indexed columns are needed
- [ ] Document pagination with filters (appends query strings)

---

## Phase 2: Core Admin Pages Conversion

### 2.1 Convert `/admin/master` (High Priority)

**Current State**: Inline editing in table, no search/filter  
**Target State**: Modal editing + search/filter

#### 2.1.1 Frontend Changes
- [ ] Add search/filter form above table (similar to `/admin/users` pattern)
  - [ ] Search input: searches across name fields
  - [ ] Status filter: active/inactive
  - [ ] Category filter: for resource type if needed
  - [ ] Submit and Reset buttons
  
- [ ] Convert inline edit to modal
  - [ ] Create `edit-master-{resource}` modal using x-modal component
  - [ ] Move form from table row into modal header/body/footer
  - [ ] Add Alpine.js state management (openEdit method)
  - [ ] Update Edit buttons to trigger modal
  
- [ ] Add filter/search display
  - [ ] Show active filters in sidebar or near table
  - [ ] Display result count: "Showing X of Y results"
  - [ ] Ensure pagination works with filters

#### 2.1.2 Backend Changes
- [ ] Update MasterDataController::index()
  - [ ] Add query parameter handling: `request('search')`, `request('status')`
  - [ ] Build dynamic query with filters
  - [ ] Return filtered paginated results
  
- [ ] Database indexing (check if needed)
  - [ ] Verify `school_classes.class_name` is indexed
  - [ ] Verify `subjects.subject_name` is indexed
  - [ ] Add indexes if missing

#### 2.1.3 Testing
- [ ] Test search filters data correctly
- [ ] Test status filter shows active/inactive only
- [ ] Test modal opens with selected data
- [ ] Test form submit updates database
- [ ] Test modal close without save doesn't update
- [ ] Test pagination works with filters applied
- [ ] Test Reset button clears all filters
- [ ] Test mobile responsiveness

### 2.2 Enhance `/admin/users` with Search/Filter

**Current State**: Modal editing ✅, but no search/filter  
**Target State**: Keep modal, add search/filter

#### 2.2.1 Frontend Changes
- [ ] Add search/filter form above users table
  - [ ] Search input: searches email, name
  - [ ] Status filter: active/inactive
  - [ ] Role filter: dropdown of available roles
  - [ ] Submit and Reset buttons
  
- [ ] Add result display
  - [ ] Show filter status: "Showing X of Y users"
  - [ ] Display applied filters (optional: filter tags to clear)

#### 2.2.2 Backend Changes
- [ ] Update AdminController::index()
  - [ ] Add query parameter handling
  - [ ] Build search/filter query
  - [ ] Return filtered paginated users
  
- [ ] Database indexing
  - [ ] Verify `users.email` is indexed (usually is)
  - [ ] Verify `users.is_active` query efficiency

#### 2.2.3 Testing
- [ ] Test search by name works
- [ ] Test search by email works
- [ ] Test role filter works
- [ ] Test status filter works
- [ ] Test combined filters work
- [ ] Test pagination with filters
- [ ] Test Reset button

### 2.3 Apply Same Pattern to `/admin/qrcodes`

**Current State**: ? (need to check)  
**Target State**: Modal editing + search/filter

#### 2.3.1 Discovery
- [ ] Review current `/admin/qrcodes/index.blade.php`
- [ ] Identify current edit/action pattern
- [ ] Identify searchable fields

#### 2.3.2 Implementation (same as 2.1)
- [ ] Convert to modal editing if not already
- [ ] Add search/filter

### 2.4 Apply Same Pattern to `/admin/audit`

**Current State**: Audit log view, no editing, no search/filter  
**Target State**: Add search/filter for review

#### 2.4.1 Discovery
- [ ] Review current `/admin/audit/index.blade.php`
- [ ] Identify logged fields to display
- [ ] Identify searchable/filterable fields

#### 2.4.2 Implementation
- [ ] Add search box: searches across user name, action type
- [ ] Add filters: date range, action type, user
- [ ] Display result count
- [ ] Ensure pagination works

---

## Phase 3: Role-Based Pages

### 3.1 Add Search/Filter to `/kesiswaan/reports`

**Current State**: ? (need to check)  
**Target State**: Add search/filter

#### 3.1.1 Discovery
- [ ] Review current reports list structure
- [ ] Identify searchable fields (report number, student name, date, status)
- [ ] Identify applicable filters (status, date range, category)

#### 3.1.2 Implementation
- [ ] Add search box: report number, student name
- [ ] Add filters: status, date range, report category
- [ ] Display filter results and pagination

### 3.2 Add Search/Filter to `/sarpras/reports`

Similar to 3.1

---

## Phase 4: Quality Assurance

### 4.1 Accessibility Audit
- [ ] Modal: focus management works
- [ ] Modal: Tab/Shift+Tab cycles correctly
- [ ] Modal: Escape closes modal
- [ ] All inputs have labels
- [ ] All errors displayed inline and accessible
- [ ] Color contrast meets WCAG AA

### 4.2 Performance Testing
- [ ] Search on 1000+ records: < 200ms response
- [ ] Pagination with filters: no lag
- [ ] Database queries are indexed
- [ ] No N+1 queries

### 4.3 Regression Testing
- [ ] Existing functionality not broken
- [ ] Data persistence correct
- [ ] Error messages display properly
- [ ] Validation (client & server) works

### 4.4 Browser Testing
- [ ] Chrome/Chromium
- [ ] Firefox
- [ ] Safari (if available)
- [ ] Mobile Safari / Chrome on mobile

---

## Phase 5: Documentation & Training

### 5.1 Update DESIGN.md
- [ ] Add modal pattern reference
- [ ] Add search/filter pattern reference

### 5.2 Create Developer Guide
- [ ] Quick start: "How to add search/filter to a new page"
- [ ] Copy-paste template for modal
- [ ] Copy-paste template for search/filter form
- [ ] Common pitfalls and how to avoid

### 5.3 Code Examples
- [ ] Complete before/after example for one page
- [ ] Modal component usage
- [ ] Alpine.js state management
- [ ] Controller filter logic

---

## Task Breakdown by Developer

### Developer A: Frontend (Modal & Search/Filter UI)
- [ ] 1.1: Review modal component and document
- [ ] 1.2: Create search/filter form component
- [ ] 2.1.1: Convert `/admin/master` to modal
- [ ] 2.1.1: Add search/filter form to `/admin/master`
- [ ] 2.2.1: Add search/filter to `/admin/users`
- [ ] 2.3 & 2.4: Apply to qrcodes and audit pages
- [ ] 4.1: Accessibility audit
- [ ] 5.2: Create developer guide with templates

### Developer B: Backend (Query & Controller Logic)
- [ ] 1.3: Create/document filter helper logic
- [ ] 2.1.2: Update MasterDataController for search/filter
- [ ] 2.2.2: Update AdminController for search/filter
- [ ] Database indexing analysis
- [ ] 4.2: Performance testing
- [ ] 5.3: Code examples for backend logic

### Developer C: QA & Documentation
- [ ] 4.3: Regression testing
- [ ] 4.4: Cross-browser testing
- [ ] 5.1: Update DESIGN.md
- [ ] Create test cases for new features

---

## Estimated Timeline

| Phase | Week | Effort | Status |
|-------|------|--------|--------|
| 1. Doc & Setup | Week 1 | 2 days | Ready |
| 2. Core Pages | Week 2 | 5 days | Ready |
| 3. Role Pages | Week 3 | 3 days | Blocked until Phase 2 |
| 4. QA | Week 4 | 2 days | Blocked until Phase 3 |
| 5. Training | Week 4 | 1 day | Blocked until Phase 4 |

**Total**: ~2 weeks for full implementation

---

## Success Criteria

✅ All core admin pages use modal for edit  
✅ All pages with 20+ items have search/filter  
✅ Accessibility: keyboard navigation works  
✅ Performance: search < 200ms on 1000 records  
✅ No regression in existing functionality  
✅ Documentation complete and up-to-date  
✅ All developers can follow pattern for new pages  

---

## Notes

- Consider using migration for database indexing
- Some pages may need custom filters (date range, categories)
- Ensure CSRF tokens in all forms
- Test on SQLite (dev) and MariaDB (prod) if different
- Consider caching for frequently filtered data
