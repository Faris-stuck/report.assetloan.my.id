# API Documentation

## Purpose

Domain ini mendokumentasikan semua spesifikasi API, definisi endpoint, request/response schemas, requirements authentication per endpoint, rate limiting policies, versioning strategy, dan error handling standards. Dokumentasi API berfungsi sebagai technical contract antara frontend/mobile clients dan backend services, memastikan perilaku API yang konsisten dan predictable.

## Quick Navigation

| Dokumen | Purpose | Untuk Siapa | Status |
|---------|---------|-----------|--------|
| [endpoints.md](./endpoints.md) | Daftar lengkap endpoints dengan request/response schemas | Frontend/Mobile developers, API consumers | Stable |
| [authentication-requirements.md](./authentication-requirements.md) | Mekanisme authentication (session/JWT/OAuth) per endpoint | API implementers, security reviewers | Stable |
| [rate-limiting.md](./rate-limiting.md) | Rate limit policies, quotas, dan throttling behavior | Frontend/Backend developers | Stable |
| [error-codes.md](./error-codes.md) | Standard error codes dan meanings | All developers | Stable |

## Folder Contents

### Primary Documents
- **endpoints.md** - Referensi lengkap endpoint dengan request/response schemas dan test cases
- **authentication-requirements.md** - Mekanisme authentication dan security per endpoint
- **rate-limiting.md** - Policies untuk rate limiting, quotas, dan throttling
- **error-codes.md** - Standard error codes dengan meanings dan handling

### Reference & Quick Lookups
- Semua READMEs dalam folder ini adalah navigation files

## Folder Organization Rules

### When to Add New Files

Tambahkan file baru ketika:
- Kategori endpoint baru memerlukan dokumentasi independen (e.g., `webhooks.md`, `batch-api.md`)
- Fitur API (versioning, caching, rate limiting) perlu standalone document
- Expected size melebihi 300 lines di existing document

Extend existing file ketika:
- Endpoint baru termasuk kategori yang ada (tambahkan ke `endpoints.md`)
- Error type baru berhubungan dengan existing errors
- Auth requirement baru builds pada existing mechanisms

### File Naming Convention

**Pattern**: `PURPOSE[-VARIANT].md`

**Rules**:
- Lowercase letters only
- Gunakan hyphens untuk separate words
- Deskriptif: `endpoints.md`, `error-codes.md`, NOT `api1.md`

**Examples**:
- `endpoints.md` (main endpoint reference)
- `authentication-requirements.md`
- `rate-limiting.md`
- `error-codes.md`

### Folder Growth

**Direct file limit**: 8 files maximum

**Sub-folder naming pattern**: `api-[category]/`

**Examples if subfolder needed**:
```
docs/api/
├── README.md
├── authentication-requirements.md
├── rate-limiting.md
├── error-codes.md
├── endpoints/
│   ├── README.md
│   ├── users-api.md
│   ├── reports-api.md
│   └── [more endpoint categories...]
└── webhooks.md
```

## Related Domains

- **[auth/](../auth/)** - Mendefinisikan mekanisme authentication referenced di API requirements
- **[database/](../database/)** - Menyediakan data schema context untuk request/response structures
- **[testing/](../testing/)** - Termasuk API testing procedures dan test results
- **[deployment/](../deployment/)** - Spesifikasi API deployment dan versioning di production
- **[business/](../business/)** - Mendefinisikan business rules yang constrain API behavior

## Getting Started

### Untuk Frontend/Mobile Developers

Mulai dengan:
1. Baca [endpoints.md](./endpoints.md) untuk available endpoints
2. Ikuti [authentication-requirements.md](./authentication-requirements.md) untuk auth setup
3. Lihat [error-codes.md](./error-codes.md) ketika debug issues
4. Referensi [rate-limiting.md](./rate-limiting.md) untuk quota planning

### Untuk API Implementers

Mulai dengan:
1. Tinjau [endpoints.md](./endpoints.md) untuk endpoint specs
2. Ikuti [authentication-requirements.md](./authentication-requirements.md) untuk security
3. Implementasikan [error-codes.md](./error-codes.md) standards
4. Rencanakan rate limiting per [rate-limiting.md](./rate-limiting.md)

### Untuk QA/Testers

Mulai dengan:
1. Tinjau [endpoints.md](./endpoints.md) untuk test scenarios
2. Gunakan [error-codes.md](./error-codes.md) untuk negative test cases
3. Test rate limiting per [rate-limiting.md](./rate-limiting.md)
4. Test authentication per [authentication-requirements.md](./authentication-requirements.md)

## Search Tips

| Pertanyaan | Jawaban |
|-----------|---------|
| Bagaimana cara memanggil [endpoint name]? | [endpoints.md](./endpoints.md) |
| Apa auth yang dibutuhkan [endpoint]? | [authentication-requirements.md](./authentication-requirements.md) |
| Apa format request? | [endpoints.md](./endpoints.md) - request schema section |
| Apa errors yang bisa terjadi? | [error-codes.md](./error-codes.md) |
| Berapa rate limit saya? | [rate-limiting.md](./rate-limiting.md) |
| Error apa ini? | [error-codes.md](./error-codes.md) |
| Bagaimana batch request? | [endpoints.md](./endpoints.md#batch-endpoints) |

## See Also

- [Root Documentation Hub](../README.md) - Overview semua domains
- [../auth/authentication.md](../auth/authentication.md) - Detailed authentication mechanisms
- [../database/schema-overview.md](../database/schema-overview.md) - Database schema untuk API context
- [../testing/testing-framework.md](../testing/testing-framework.md) - API testing procedures

---

*API domain dipertahankan per AGENTS.md conventions. Lihat juga: design.md, requirements.md*

