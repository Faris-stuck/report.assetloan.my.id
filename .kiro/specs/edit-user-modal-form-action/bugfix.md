# Bugfix Requirements: Edit User Modal Form Action

## Introduction

The edit user modal in the admin users page has an incorrect form action URL. The form currently uses a hardcoded URL pattern `/admin/users/` concatenated with the user ID, which results in improperly formed URLs and prevents the edit form from submitting correctly. This should be replaced with the proper Laravel route helper to generate the correct edit route.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN user clicks the edit button in the users list THEN the form action is set to a hardcoded string pattern `/admin/users/` concatenated with the user ID, resulting in URLs like `/admin/users/1`

1.2 WHEN the edit form is submitted THEN the request fails because the URL pattern does not match the actual Laravel route definition for updating users

### Expected Behavior (Correct)

2.1 WHEN user clicks the edit button in the users list THEN the form action SHALL be set to the proper Laravel route using `route('admin.users.update', editingUserId)` 

2.2 WHEN the edit form is submitted THEN the request SHALL be routed correctly to the user update endpoint with the proper HTTP method and parameters

### Unchanged Behavior (Regression Prevention)

3.1 WHEN the user modal is displayed in non-edit mode (creating a new user) THEN the form action SHALL CONTINUE TO remain empty or set to the create route without modification

3.2 WHEN other form fields (name, email, etc.) are populated in the edit modal THEN the system SHALL CONTINUE TO display and validate them correctly regardless of the action URL fix
