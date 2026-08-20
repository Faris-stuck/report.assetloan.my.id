# Archive Documentation

## Purpose

Domain ini menyimpan historical, outdated, atau deprecated documentation untuk audit trail dan historical context. Archive documentation **tidak pernah** di-link dari dokumentasi aktif (kecuali dalam ADRs untuk referensi historis), dan ditandai dengan metadata archival.

Archive domain berfungsi sebagai historical record sistem dan keputusan yang dibuat selama development.

## Archive Policy

### Why Files Are Archived

Files diarchivkan karena:
- **Outdated** - Documentation untuk features yang sudah deprecated atau removed
- **Historical** - Documentation dari fase development/waves yang sudah selesai
- **Superseded** - Documentation yang sudah digantikan dengan versi baru
- **Experimental** - Documentation dari experiments atau prototypes yang tidak jadi

### Retention Period

- **Indefinite** - Archive files disimpan selamanya untuk historical context
- **Never Deleted** - Files di archive tidak dihapus kecuali privacy/security concerns
- **Audit Trail** - Archive berfungsi sebagai audit trail untuk decisions dan changes

### How to Access Archives

**Direct Access**: Files di archive/ folder tersedia untuk direct reading jika perlu referensi historis.

**Search**: Gunakan frontmatter `archived: true` dan `archive_reason` untuk finding archived docs.

**Historical Context**: Referensi archived docs dalam ADRs untuk explaining why decisions dibuat.

## Archive Metadata Requirements

Setiap archived file harus include frontmatter:

```yaml
---
domain: [original-domain]
purpose: [original-purpose]
archived: true
archive_reason: [outdated|superseded|experimental|historical]
archive_date: [YYYY-MM-DD]
superseded_by: [link-to-replacement] # if applicable
related_wave: [wave-number] # if applicable
original_location: [original-path]
---
```

## Archive Index

Daftar lengkap semua archived files dengan reason dan date:

### Historical Waves & Implementation

| File | Reason | Archived Date | Related Wave |
|------|--------|---------------|--------------|
| wave-1-completion.md | historical | 2024-01-15 | Wave 1 |
| wave-2-completion.md | historical | 2024-01-15 | Wave 2 |
| wave-3-completion.md | historical | 2024-01-15 | Wave 3 |
| wave-4-7-implementation.md | historical | 2024-01-15 | Waves 4-7 |
| implementation-phase-summary.md | historical | 2024-01-15 | Implementation |

### Testing & Verification

| File | Reason | Archived Date | Type |
|------|--------|---------------|------|
| consistency-verification-complete.md | superseded | 2024-01-15 | Verification |
| mobile-optimization-summary.md | superseded | 2024-01-15 | Optimization |
| accessibility-compliance-report.md | outdated | 2024-01-15 | Report |

### Implementation Details

| File | Reason | Archived Date | Type |
|------|--------|---------------|------|
| implementation-changes-wave-detailed.md | historical | 2024-01-15 | Documentation |
| implementation-test-checklist.md | outdated | 2024-01-15 | Checklist |
| tasks-completion-summary.md | historical | 2024-01-15 | Summary |

### Legacy Documentation

| File | Reason | Archived Date | Original Domain |
|------|--------|---------------|-----------------|
| CHANGELOG-legacy.md | historical | 2024-01-15 | Development |
| [Add archived files...] | [...] | [...] | [...] |

## Folder Organization Rules

### Archiving a Document

Untuk mengarchive dokumen:

1. **Move file** ke archive/ folder
2. **Add frontmatter** dengan `archived: true` dan `archive_reason`
3. **Remove links** dari dokumentasi aktif
4. **Document in ADR** jika decision archival itu signifikan
5. **Update Archive Index** dengan file baru

### File Naming Convention

**Pattern**: `PURPOSE[-VARIANT].md` atau `WAVE-[NUMBER]-DESCRIPTION.md`

**Rules**:
- Preservasi original filename jika memungkinkan
- Add prefix `archived-` jika naming conflict
- Lowercase dengan hyphens

**Examples**:
- `wave-1-completion.md`
- `archived-old-spec.md`
- `implementation-changes-wave-detailed.md`
- `CHANGELOG-legacy.md`

### Folder Growth

**No limit** - Archive dapat grow indefinitely

**Organization**: Minimal subfolder structure, semua di root archive/

## Related Domains

Archive domain **tidak** link ke domains lain secara aktif.

Sebaliknya:
- **ADRs** dapat reference archived docs untuk historical context
- **Design docs** dapat reference archived docs untuk explaining evolution
- **Active domains** NOT link ke archive (except dalam historical context di ADRs)

## Getting Started

### Untuk Historical Reference

Gunakan archive ketika:
1. Perlu understand why decision dibuat
2. Perlu context tentang features yang outdated
3. Perlu audit trail untuk compliance
4. Researching evolution dari system

### Untuk Archiving New Documents

1. Baca "Archiving a Document" section di atas
2. Ikuti metadata requirements dengan frontmatter
3. Update Archive Index dengan entry baru
4. Dokumentasikan di ADR jika signifikan

### Untuk Cleanup

Archive tidak require regular cleanup - preserved selamanya untuk historical context.

## Search Tips

| Pertanyaan | Jawaban |
|-----------|---------|
| Apa yang terjadi di Wave X? | [wave-X-completion.md](./wave-X-completion.md) di archive/ |
| Mengapa feature X dihapus? | Cari di ADRs atau archive frontmatter `superseded_by` |
| Apa old implementation? | Referensi archived implementation docs |
| Bagaimana accessibility testing dilakukan? | Lihat [accessibility-compliance-report.md](./accessibility-compliance-report.md) |

## See Also

- [Root Documentation Hub](../README.md) - Overview semua domains (active)
- [../decisions/](../decisions/) - ADRs yang mereference archived docs
- Active domains - Untuk current documentation

---

## Archive Statistics

| Metrik | Value |
|--------|-------|
| Total Archived Files | [X] |
| Archive Reason: Historical | [X] |
| Archive Reason: Outdated | [X] |
| Archive Reason: Superseded | [X] |
| Archive Reason: Experimental | [X] |
| Oldest Archive Date | [YYYY-MM-DD] |
| Most Recent Archive Date | [YYYY-MM-DD] |

---

**Archive Policy**: Files dalam domain archive dipertahankan selamanya untuk audit trail dan historical context. Lihat juga: design.md, requirements.md, AGENTS.md

