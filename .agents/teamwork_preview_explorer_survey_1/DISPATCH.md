## 2026-08-13T08:57:37Z
Investigate the codebase to map all N+1 Query issues in list queries and model relationships across LAPORIN.
Focus areas:
- Inspect Controllers (e.g. Kesiswaan, Sarpras, Admin, Dashboard, Report lists) in app/Http/Controllers and models in app/Models.
- Inspect Blade views (resources/views) to see which relations are accessed inside `@foreach` loops without eager loading.
- Identify missing `with(...)` calls on Eloquent queries.
