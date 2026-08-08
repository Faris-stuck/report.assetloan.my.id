# Bugfix Requirements: Comprehensive Security & Configuration Hardening

## Introduction

This document addresses 17 interconnected security, configuration, and performance hardening issues across the LAPORIN application. These issues span CSP policies, file handling, PII protection, database configuration, deployment hardening, and audit logging. The fixes ensure production readiness while maintaining functional integrity.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN Alpine.js or other scripts require dynamic evaluation THEN the system allows execution with `unsafe-eval` in CSP, increasing XSS risk surface

1.2 WHEN file attachments are uploaded THEN the system stores files without validating magic bytes, allowing executable or malicious file types to bypass extension checks

1.3 WHEN public reports are submitted THEN the system does not audit log public report creation, leaving gaps in security tracking for unauthenticated submissions

1.4 WHEN database connections are established THEN the system does not configure connection timeout, risking hung connections and resource exhaustion

1.5 WHEN PII data (email, phone) are stored in reports THEN the system stores them unencrypted in plain text, exposing sensitive data in database backups

1.6 WHEN user emails are created or retrieved THEN the system accepts mixed-case emails without normalization, potentially creating duplicate accounts or authentication issues

1.7 WHEN session cookies are configured in production THEN the system does not enforce HTTPS-only transmission, risking session hijacking over insecure connections

1.8 WHEN Docker image is built THEN the image includes Node.js runtime, adding unnecessary attack surface and bloat to production container

1.9 WHEN user registration validates email THEN the system performs validation in multiple layers (Form Request + Model) creating redundant logic

1.10 WHEN composer dependencies are installed THEN the system includes dompdf library which is not actively used, increasing maintenance burden

1.11 WHEN application starts in Docker THEN the system lacks environment variables documented in `.env.example`, causing deployment failures or silent misconfigurations

1.12 WHEN Docker container runs THEN the system lacks HEALTHCHECK configuration, making orchestration unable to detect unhealthy instances

1.13 WHEN application initializes THEN storage directories are created with default permissions, potentially allowing unintended access

1.14 WHEN database queries execute THEN the system does not configure statement timeouts, risking slow queries or database locks consuming resources indefinitely

1.15 WHEN CSRF middleware exemptions are applied THEN the system does not document which routes bypass protection or why, complicating security audits

1.16 WHEN public report endpoint receives traffic THEN the system lacks rate limiting, risking abuse, spam reports, or DoS attacks

1.17 WHEN database queries reference the reports table THEN the system lacks foreign key indexes, causing full-table scans on join queries

### Expected Behavior (Correct)

2.1 WHEN Alpine.js or other scripts require dynamic evaluation THEN the system provides safe alternatives without `unsafe-eval` in CSP, using hashes or nonces instead

2.2 WHEN file attachments are uploaded THEN the system validates magic bytes to confirm file type matches extension, rejecting mismatches

2.3 WHEN public reports are submitted THEN the system creates audit log entries for public report creation with hashed IP and user agent

2.4 WHEN database connections are established THEN the system configures connection timeout (e.g., 10-30 seconds) to prevent resource starvation

2.5 WHEN PII data (email, phone) are stored in reports THEN the system encrypts these fields using Laravel's encryption with a rotatable key

2.6 WHEN user emails are created or retrieved THEN the system normalizes them to lowercase during validation and storage, preventing duplicates

2.7 WHEN session cookies are configured in production THEN the system enforces HTTPS-only transmission for session cookies when APP_URL uses HTTPS

2.8 WHEN Docker image is built THEN the image uses PHP-only base image without Node.js, reducing container size and attack surface

2.9 WHEN user registration validates email THEN the system validates email in a single layer (Form Request only), removing redundant Model validation

2.10 WHEN composer dependencies are installed THEN the system removes unused dompdf library from composer.json, reducing dependencies

2.11 WHEN application starts in Docker THEN the system documents all required environment variables in `.env.example` with explanatory comments

2.12 WHEN Docker container runs THEN the system includes HEALTHCHECK instruction to verify Apache/PHP responsiveness

2.13 WHEN application initializes THEN storage directories are created with restricted permissions (700 for directories, 600 for logs)

2.14 WHEN database queries execute THEN the system configures statement timeout (e.g., max_statement_time for MariaDB) to abort runaway queries

2.15 WHEN CSRF middleware exemptions are applied THEN the system documents exempted routes and security rationale in code comments

2.16 WHEN public report endpoint receives traffic THEN the system implements rate limiting (e.g., 5 requests per minute per IP) on public report submission

2.17 WHEN database queries reference the reports table THEN the system creates indexes on foreign key columns to accelerate joins and lookups

### Unchanged Behavior (Regression Prevention)

3.1 WHEN authenticated users submit reports THEN the system SHALL CONTINUE TO process submissions with current validation logic and workflow

3.2 WHEN file attachments are downloaded THEN the system SHALL CONTINUE TO serve files with correct MIME types and original names

3.3 WHEN audit logs are viewed by administrators THEN the system SHALL CONTINUE TO display actor, action, model type, and change details without regression

3.4 WHEN reports are queried by filters or search THEN the system SHALL CONTINUE TO execute with current performance characteristics after index additions

3.5 WHEN production database connection is active THEN the system SHALL CONTINUE TO use MariaDB connection parameters for port, charset, and collation

3.6 WHEN user login occurs THEN the system SHALL CONTINUE TO authenticate using hashed passwords and session management

3.7 WHEN public reports are tracked by access code THEN the system SHALL CONTINUE TO allow users to retrieve reports without authentication

3.8 WHEN Docker containers are orchestrated THEN the system SHALL CONTINUE TO start with migrations applied and caches pre-built

3.9 WHEN role-based access control is enforced THEN the system SHALL CONTINUE TO apply allow-list rules for superadmin, kesiswaan, sarpras, wali_kelas

3.10 WHEN email notifications are sent THEN the system SHALL CONTINUE TO use Gmail SMTP with TLS encryption as configured

3.11 WHEN security headers are applied to responses THEN the system SHALL CONTINUE TO set X-Frame-Options, X-Content-Type-Options, and other protection headers

3.12 WHEN Alpine.js interacts with the DOM THEN the system SHALL CONTINUE TO function without `unsafe-eval` using inline scripts with hashes

