# Development Documentation

## Purpose

Domain ini mendokumentasikan local development environment setup, build/test commands, code style guides, linting rules, git workflow, commit conventions, dan development tools/plugins. Dokumentasi development memandu developers tentang tools, setup, dan coding practices.

## Quick Navigation

| Dokumen | Purpose | Untuk Siapa | Status |
|---------|---------|-----------|--------|
| [local-development-setup.md](./local-development-setup.md) | Local environment setup dan prerequisites | All developers | Stable |
| [coding-standards.md](./coding-standards.md) | Code style, linting rules, best practices | All developers | Stable |
| [git-workflow.md](./git-workflow.md) | Git workflow, branching, commit conventions | All developers | Stable |
| [build-test-commands.md](./build-test-commands.md) | Build, test, lint commands dan procedures | All developers | Stable |

## Folder Contents

### Primary Documents
- **local-development-setup.md** - Local setup dengan prerequisites, tools, environment setup
- **coding-standards.md** - Code style guides, linting rules, best practices per language
- **git-workflow.md** - Git workflow, branching strategy, commit conventions, PR process
- **build-test-commands.md** - Build commands, test commands, lint commands, CI procedures

### Reference & Quick Lookups
- Semua READMEs dalam folder ini adalah navigation files

## Folder Organization Rules

### When to Add New Files

Tambahkan file baru ketika:
- Development aspect baru memerlukan dokumentasi independen (e.g., `development-tools.md`, `docker-setup.md`)
- Expected size melebihi 300 lines di existing document
- Topic adalah distinct dan independent dari existing files

Extend existing file ketika:
- Content berhubungan dengan existing development aspect
- Section bisa logically ditambahkan ke existing document
- Updates hanya ke existing document yang dibutuhkan

### File Naming Convention

**Pattern**: `PURPOSE[-VARIANT].md`

**Rules**:
- Lowercase letters only
- Gunakan hyphens untuk separate words

**Examples**:
- `local-development-setup.md`
- `coding-standards.md`
- `git-workflow.md`
- `build-test-commands.md`
- `development-tools.md`
- `docker-setup.md`

### Folder Growth

**Direct file limit**: 8 files maximum

**Sub-folder naming pattern**: `development-[category]/` atau `setup/`

**Examples if subfolder needed**:
```
docs/development/
├── README.md
├── local-development-setup.md
├── coding-standards.md
├── git-workflow.md
├── setup/
│   ├── README.md
│   ├── php-setup.md
│   ├── node-setup.md
│   └── docker-setup.md
└── build-test-commands.md
```

## Related Domains

- **[testing/](../testing/)** - Testing procedures menggunakan commands di development domain
- **[deployment/](../deployment/)** - Deployment setup berdasarkan development setup
- **[ui/](../ui/)** - UI development standards dan tools
- **[api/](../api/)** - API development procedures
- **[database/](../database/)** - Database setup untuk local development

## Getting Started

### Untuk New Developers

Mulai dengan:
1. Baca [local-development-setup.md](./local-development-setup.md) untuk environment setup
2. Ikuti [build-test-commands.md](./build-test-commands.md) untuk first build
3. Tinjau [git-workflow.md](./git-workflow.md) untuk commit procedures
4. Ikuti [coding-standards.md](./coding-standards.md) untuk code style

### Untuk All Developers

Mulai dengan:
1. Referensi [build-test-commands.md](./build-test-commands.md) untuk common commands
2. Ikuti [coding-standards.md](./coding-standards.md) ketika code
3. Tinjau [git-workflow.md](./git-workflow.md) untuk commit procedures
4. Konsultasi [local-development-setup.md](./local-development-setup.md) untuk troubleshooting

### Untuk Dev Lead

Mulai dengan:
1. Tinjau [local-development-setup.md](./local-development-setup.md) untuk onboarding
2. Baca [coding-standards.md](./coding-standards.md) untuk code review
3. Monitor [git-workflow.md](./git-workflow.md) adherence
4. Update [build-test-commands.md](./build-test-commands.md) saat changes

## Search Tips

| Pertanyaan | Jawaban |
|-----------|---------|
| Bagaimana setup development environment? | [local-development-setup.md](./local-development-setup.md) |
| Apa code style? | [coding-standards.md](./coding-standards.md) |
| Bagaimana commit? | [git-workflow.md](./git-workflow.md) |
| Apa build command? | [build-test-commands.md](./build-test-commands.md) |
| Apa test command? | [build-test-commands.md](./build-test-commands.md) |
| Bagaimana lint code? | [coding-standards.md](./coding-standards.md) |
| Apa git workflow? | [git-workflow.md](./git-workflow.md) |

## See Also

- [Root Documentation Hub](../README.md) - Overview semua domains
- [../testing/testing-framework.md](../testing/testing-framework.md) - Testing procedures
- [../deployment/deployment-pipeline.md](../deployment/deployment-pipeline.md) - CI/CD pipeline
- [../database/schema-overview.md](../database/schema-overview.md) - Database schema untuk local setup

---

*Development domain dipertahankan per AGENTS.md conventions. Lihat juga: design.md, requirements.md*

