# Bugfix Requirements Document

## Introduction

A ParseError occurs in the admin users index view when rendering the edit user modal. The error is caused by incorrect Blade syntax where blade echo tags `{{ }}` are nested inside a dynamic component attribute binding `:show`. This results in a syntax error with an unexpected `<` token at line 114.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN the `admin.users.index` view is rendered THEN the system throws a ParseError with message "syntax error, unexpected token '<'" at line 114

1.2 WHEN the modal component attribute `:show` is parsed with nested blade echo tags `{{ old('edit_user_id') ? 'true' : 'false' }}` THEN the system fails to compile the blade template due to invalid syntax

### Expected Behavior (Correct)

2.1 WHEN the `admin.users.index` view is rendered THEN the system SHALL compile and display the page without ParseError

2.2 WHEN the modal component attribute `:show` receives a boolean expression THEN the system SHALL pass the PHP expression directly without blade echo tags, using `:show="old('edit_user_id') ? true : false"`

### Unchanged Behavior (Regression Prevention)

3.1 WHEN `old('edit_user_id')` has a value (validation error during edit) THEN the system SHALL CONTINUE TO show the edit modal automatically on page load

3.2 WHEN `old('edit_user_id')` is null or empty THEN the system SHALL CONTINUE TO keep the edit modal hidden on page load

3.3 WHEN the edit modal is opened via the Edit button THEN the system SHALL CONTINUE TO populate the form fields with the selected user's data using Alpine.js
