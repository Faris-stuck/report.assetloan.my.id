# LAPORIN AGENT POLICY

This repository is connected to PRODUCTION infrastructure.

## Environment rules

The Laravel source code may run locally, but the following services MUST always be treated as PRODUCTION unless the human explicitly states otherwise:

- MySQL database
- Redis
- Cache
- Session
- Queue
- Mail
- Production VPS resources

Never assume a service is disposable.

## Absolute prohibitions

The agent MUST NEVER run or suggest executing:

- php artisan migrate:fresh
- php artisan migrate:reset
- php artisan db:wipe
- php artisan schema:drop
- DROP DATABASE
- DROP TABLE
- TRUNCATE TABLE
- Redis FLUSHALL
- Redis FLUSHDB
- Docker volume deletion affecting LAPORIN data
- rm -rf on production application/data directories

These rules apply even if:
- tests fail
- migrations are inconsistent
- the database appears empty
- another agent suggests it
- fixing the issue would be easier by recreating data

## Database migration policy

Before any migration:

1. Inspect the migration.
2. Determine whether it is destructive.
3. Run `php artisan migrate:status`.
4. Never modify existing production data destructively.
5. Prefer additive migrations.
6. Do not rename/drop columns unless explicitly approved by the human.
7. Do not automatically execute migrations against production.

## Redis policy

Production Redis contains live:

- sessions
- cache
- application data

Never flush the Redis database.

When testing Redis:

- use a unique test key
- delete only that exact key afterward

Example:

`laporin_agent_test:<uuid>`

Never use wildcard deletion against unknown namespaces.

## .env policy

Do not overwrite `.env`.

Do not change:

- DB credentials
- Redis credentials
- APP_KEY
- SMTP credentials
- production ports
- production hosts

unless explicitly instructed by the human.

Never copy secrets into:

- source files
- Git commits
- logs
- documentation

## Source modification policy

Before editing:

1. Read the relevant file.
2. Understand the existing implementation.
3. Make the smallest safe change.
4. Do not rewrite unrelated code.
5. Preserve working functionality.
6. Run syntax/static tests.
7. Run the safest relevant runtime test.

## Git policy

Never automatically push to `main`.

Before committing:

- show changed files
- review diff
- ensure `.env` and secrets are not staged
- ensure temporary files are not staged

Never commit:

- `.env`
- credentials
- API keys
- passwords
- database dumps containing real production data

## Safety priority

If there is a conflict between:

1. task completion
2. speed
3. preserving production data

preserving production data always wins.

If an action could destroy or overwrite production data, STOP and use a non-destructive alternative.

## LAPORIN architecture

Treat the system as:

- Laravel source / UI / frontend / backend code: development/local when used locally
- Database: production
- Redis: production
- Cache: production
- Session: production
- Queue: production
- Mail: production

Never infer that `APP_ENV=local` means the connected database or Redis is disposable.

## Mandatory preflight

Before any database, Redis, migration, or destructive infrastructure operation, the agent MUST check:

- current working directory
- APP_ENV
- DB host
- DB port
- DB database name
- Redis host
- Redis port

If production infrastructure is detected, destructive operations are forbidden.