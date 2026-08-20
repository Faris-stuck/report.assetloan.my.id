# Bugfix Requirements: Master Classes Arrow Icon Sizing

## Introduction

Arrow icons (`>` and `<`) displayed on the master classes page (`/admin/master/classes`) are oversized. These icons should be scaled to a proportionate size that matches the standard icon sizing used throughout the application. This sizing issue affects the visual appearance and user experience of the master data management interface.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN the master classes page is loaded THEN the arrow icons (`>` and `<`) are displayed at an oversized scale that disrupts the visual layout

1.2 WHEN users view the master classes list THEN the oversized icons take up excessive space and appear disproportionate to other UI elements on the page

### Expected Behavior (Correct)

2.1 WHEN the master classes page is loaded THEN the arrow icons (`>` and `<`) SHALL be displayed at a proportionate size that matches the standard icon sizing convention used elsewhere in the application

2.2 WHEN users view the master classes list THEN the icons SHALL maintain appropriate proportions relative to surrounding text and UI elements without excessive spacing

### Unchanged Behavior (Regression Prevention)

3.1 WHEN other elements on the master classes page are rendered (class names, descriptions, action buttons) THEN the system SHALL CONTINUE TO display them at their current correct sizes without modification

3.2 WHEN navigation to other master data pages occurs (e.g., subjects, locations) THEN the system SHALL CONTINUE TO display their icons at properly sized dimensions as they currently do

3.3 WHEN the master classes page is printed or exported THEN the icon sizing SHALL CONTINUE TO maintain appropriate proportions in the output format
