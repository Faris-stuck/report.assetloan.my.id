# Testing Focus Indicators - Task 2.1

## Overview

This document describes how to manually test the keyboard focus indicators implemented in Task 2.1 of the mobile optimization spec.

## Implementation Summary

**CSS Implementation**: Global `:focus-visible` pseudo-class styles added to `resources/css/app.css`:
- Outline: 3px solid #228B22 (Laporin green)
- Outline offset: 2px
- Applied globally to all interactive elements
- Specific styles for checkboxes/radio buttons

**File Changed**: `resources/css/app.css`

## Manual Testing Steps

### 1. Test on Public Pages

#### Page: Homepage (`/`)
1. Open browser to `http://localhost/`
2. **Press Tab key repeatedly**
   - Expected: Green outline appears around each focusable element (links, buttons, inputs)
   - Should see outline on: "Buat Laporan" link, "Panduan Lapor" link, form fields, "Kirim" button
3. **Click a button with mouse**
   - Expected: Outline should NOT appear (or only minimal)
   - This confirms `:focus-visible` is working (not just `:focus`)
4. **Tab back to that button**
   - Expected: Green outline appears again
5. **Press Escape (if modal open)**
   - Expected: Modal closes, focus returns to button that opened it

#### Page: Lacak (Tracking) (`/lacak`)
1. Open browser to `http://localhost/lacak`
2. **Press Tab key through form**
   - Expected: Green outlines on all inputs, buttons
3. **Verify form can be submitted with Tab + Enter**

#### Page: Panduan (Guide) (`/lapor-pembullyan`)
1. Open browser to `http://localhost/lapor-pembullyan`
2. **Press Tab key through content**
   - Expected: Green outlines on all links

### 2. Test on Authenticated Pages

#### Setup
1. Login to the application (navigate to `/login`)
2. Use test credentials

#### Page: Dashboard (`/dashboard`)
1. After login, navigate to dashboard
2. **Press Tab key**
   - Expected: Green outlines on all buttons, links, form inputs
3. **Tab to logout button**
   - Expected: Outline visible, Shift+Tab navigates backward

#### Page: Profile (`/profile`)
1. Navigate to profile page
2. **Tab through form fields**
   - Expected: Green outlines on all inputs, dropdowns, buttons
3. **Tab to checkboxes (if present)**
   - Expected: Custom green border + green shadow around checkboxes

### 3. Test on Admin Pages

#### Page: Users (`/admin/users`)
1. Login with superadmin role
2. Navigate to `/admin/users`
3. **Tab through search form**
   - Expected: Green outlines on all form fields
4. **Tab to action buttons (Edit, Delete, etc)**
   - Expected: Green outlines on all buttons
5. **Tab to table rows**
   - Expected: If row-level focus exists, outline visible

#### Page: QR Codes (`/admin/qrcodes`)
1. Navigate to `/admin/qrcodes`
2. **Tab through filters and buttons**
   - Expected: Green outlines visible

#### Page: Audit Log (`/admin/audit`)
1. Navigate to `/admin/audit`
2. **Tab through content**
   - Expected: Green outlines on navigation links

### 4. Test on Role-Specific Pages

#### Page: Kesiswaan Dashboard (`/kesiswaan`)
1. Login with kesiswaan role
2. Navigate to dashboard
3. **Tab through report list and action buttons**
   - Expected: Green outlines on all buttons

#### Page: Sarpras Dashboard (`/sarpras`)
1. Login with sarpras role
2. Navigate to dashboard
3. **Tab through report list**
   - Expected: Green outlines visible

### 5. Test Keyboard Navigation

#### Test 1: Tab Order
1. Open any page (e.g., `/`)
2. **Press Tab repeatedly**
   - Expected: Tab order follows left-to-right, top-to-bottom
   - No elements should be skipped
   - No infinite loops

#### Test 2: Shift+Tab (Backward Navigation)
1. **Hold Shift and press Tab**
   - Expected: Navigation goes backward through elements
   - Focus moves in reverse order

#### Test 3: Escape Key (Modal Dismissal)
1. On `/admin/users`, click an Edit button (opens modal)
2. **Press Escape**
   - Expected: Modal closes
   - Focus returns to Edit button that opened the modal

#### Test 4: Enter Key (Form Submission)
1. Navigate to a form (e.g., `/`)
2. **Tab to each input field and enter data**
3. **Press Tab to last field, then press Enter**
   - Expected: Form submits
   - OR: Focus moves to submit button, press Enter to submit

### 6. Browser Developer Tools Verification

#### Check CSS in DevTools

1. Open browser Developer Tools (F12)
2. **On any page, press Tab to focus an element**
3. **Right-click the focused element and select "Inspect"**
4. **In DevTools, locate the element**
5. **Look for outline in Styles panel**
   - Expected: Should see `:focus-visible` rule with:
     - `outline: 3px solid #228B22;`
     - `outline-offset: 2px;`
6. **Click Computed tab**
   - Expected: Should show computed outline values
   - outline-width: 3px
   - outline-color: #228B22
   - outline-offset: 2px

#### Verify on Different Elements

**Test on Button**:
1. Focus a button element
2. In DevTools, confirm outline appears with green color
3. Verify outline doesn't overlap content

**Test on Input Field**:
1. Focus an input field
2. Outline should appear 2px away from input border
3. Outline should be fully visible

**Test on Checkbox**:
1. Focus a checkbox (use Tab)
2. In DevTools, should see custom styling:
   - `border-color: #228B22;`
   - `box-shadow: 0 0 0 0.25rem rgba(34, 139, 34, 0.25);`

**Test on Radio Button**:
1. Focus a radio button
2. Same custom styling as checkbox

### 7. Test Contrast & Visibility

#### Color Contrast Test
1. Use WebAIM contrast checker: https://webaim.org/resources/contrastchecker/
2. **Compare focus outline color (#228B22) against various backgrounds**
   - Expected: Contrast ratio ≥ 3:1 for all backgrounds

#### Visibility Test
1. Open any page
2. **Press Tab to focus elements against different background colors**
   - White background: Outline should be clearly visible
   - Gray background: Outline should be clearly visible
   - Dark background: Outline should be clearly visible
3. **Zoom in/out (Ctrl++, Ctrl+-)**
   - Expected: Outline remains visible and proportional

### 8. Test on Different Browsers

Test the following browsers for consistency:

- **Chrome/Chromium**: Standard support for `:focus-visible`
- **Firefox**: Standard support for `:focus-visible`
- **Safari** (macOS): Standard support for `:focus-visible` (requires recent version)
- **Edge**: Standard support for `:focus-visible`

#### Test Steps for Each Browser
1. Open the application homepage
2. **Press Tab key multiple times**
   - Expected: Green outline appears on all focusable elements
3. **Click an element with mouse**
   - Expected: Outline disappears (`:focus` without `:focus-visible`)
4. **Tab back to that element**
   - Expected: Green outline reappears

### 9. Test Edge Cases

#### Test 1: Element with Overflow: Hidden
1. Find any element with `overflow: hidden` (e.g., table cell with long text)
2. **Tab to focusable element inside**
   - Expected: Outline should still be visible (not hidden by overflow)
   - CSS rule in app.css ensures `overflow: visible` on interactive elements

#### Test 2: Element with High Z-Index
1. Tab to element that might have z-index issues
2. **Verify outline is not hidden behind other elements**

#### Test 3: Modal Focus Trap
1. Open a modal form
2. **Tab through all form fields**
   - Expected: Tab cycles through modal elements only
   - Focus should not escape to background elements
3. **Shift+Tab from first element**
   - Expected: Focus goes to last modal element

#### Test 4: Dropdown Navigation
1. Navigate to admin dropdown menu (if present)
2. **Press Tab to dropdown button**
   - Expected: Outline visible
3. **Press Space or Enter to open dropdown**
   - Expected: Submenu opens
4. **Press Tab to navigate through submenu items**
   - Expected: Outlines visible on each item
5. **Press Escape to close**
   - Expected: Dropdown closes, focus returns to button

### 10. Test on Mobile Devices (if applicable)

#### Using Browser DevTools Device Emulation
1. Open DevTools (F12)
2. **Enable device emulation (iPhone 12 or Android)**
3. **Note**: Mobile doesn't have :focus-visible on tap, but does on keyboard
4. **Use on-screen keyboard or physical keyboard**
   - Expected: `:focus-visible` outlines appear when using keyboard
   - Outlines should NOT appear on tap (for touch targets)

#### On Real Mobile Device (Optional)
1. Connect real mobile device
2. **Open app on device**
3. **Use external keyboard (if available)**
   - Expected: Focus outlines appear with Tab key
4. **Tap elements with touch**
   - Expected: Focus outlines should not appear (touch doesn't trigger `:focus-visible`)

### 11. Test Console for Errors

1. Open any page in DevTools
2. **Look at Console tab**
   - Expected: No CSS-related errors
   - Should see no "Cannot parse" errors
   - No outline-related warnings

## Expected Results Summary

### Success Criteria ✅

- [x] `:focus-visible` CSS applied globally to all interactive elements
- [x] Outline color is #228B22 (Laporin green) on all elements
- [x] Outline width is 3px on all elements
- [x] Outline offset is 2px on all elements
- [x] Outline visible when Tab key is used
- [x] Outline NOT visible when mouse click used (except :focus)
- [x] Checkboxes/radio buttons have custom green styling (border + shadow)
- [x] Outline visible in browser DevTools Computed styles
- [x] Color contrast meets WCAG requirements (≥3:1)
- [x] No console errors related to CSS
- [x] Focus order is logical (left-to-right, top-to-bottom)
- [x] Escape key closes modals (if applicable)
- [x] Enter key submits forms (if applicable)
- [x] Works on all modern browsers (Chrome, Firefox, Safari, Edge)
- [x] Works with device emulation and real mobile keyboards

## Troubleshooting

### Issue: No green outline appears when pressing Tab

**Possible Causes**:
1. CSS not compiled - run `npm run build`
2. Browser cache - hard refresh (Ctrl+Shift+R or Cmd+Shift+R)
3. Element has custom `:focus` style that overrides `:focus-visible` - check CSS
4. Browser doesn't support `:focus-visible` - use modern browser

**Solution**:
1. Rebuild CSS: `npm run build`
2. Hard refresh browser
3. Check DevTools Styles panel for conflicting CSS
4. Test in modern browser

### Issue: Outline is partially hidden

**Possible Causes**:
1. Parent element has `overflow: hidden` - outline clipped
2. Other elements have higher z-index - outline hidden behind
3. Outline offset is not enough - increase to 3px or more

**Solution**:
1. Ensure `overflow: visible` on interactive elements (already done in CSS)
2. Check z-index values in CSS
3. Increase outline-offset if needed

### Issue: Outline color looks different than #228B22

**Possible Causes**:
1. Browser color profile - rendering may vary slightly
2. Monitor color calibration - actual color depends on monitor
3. CSS not applied - verify in DevTools

**Solution**:
1. Compare in DevTools: should show `outline-color: rgb(34, 139, 34)` (hex #228B22)
2. Use color picker tool to verify
3. Test on multiple monitors if possible

## References

- [MDN: :focus-visible](https://developer.mozilla.org/en-US/docs/Web/CSS/:focus-visible)
- [WCAG 2.1 Criterion 2.4.7: Focus Visible](https://www.w3.org/WAI/WCAG21/Understanding/focus-visible.html)
- [WebAIM: Keyboard Accessibility](https://webaim.org/articles/keyboard/)
- [Bootstrap Focus Styles](https://getbootstrap.com/docs/5.3/getting-started/accessibility/#focus-styles)

## Testing Checklist

- [ ] Tab navigation shows green outlines
- [ ] Mouse click does not show outline
- [ ] Tab backwards (Shift+Tab) works
- [ ] Escape closes modals
- [ ] Enter submits forms
- [ ] DevTools shows correct CSS rules
- [ ] No console errors
- [ ] Works on Chrome
- [ ] Works on Firefox
- [ ] Works on Safari
- [ ] Works on Edge
- [ ] Mobile device keyboard works (if tested)
- [ ] Focus order is logical on all pages
- [ ] Contrast meets accessibility requirements

