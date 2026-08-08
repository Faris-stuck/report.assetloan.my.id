# 📚 LAPORIN Dokumentasi - Hub Navigasi Pusat

Selamat datang di pusat dokumentasi LAPORIN. Direktori ini mengorganisir semua dokumentasi sistem pelaporan untuk SMK Taruna Bangsa Bekasi ke dalam 12 domain terdokumentasi dengan baik.

---

## 🗂️ Struktur 12 Domain

Dokumentasi kami diorganisir ke dalam domain terspesialisasi untuk memudahkan penemuan informasi:

| Domain | Deskripsi | Untuk Siapa |
|--------|-----------|-----------|
| **[API](./api/)** | Spesifikasi endpoint, authentication, rate limiting, error codes | Backend/Frontend developers |
| **[UI](./ui/)** | Design system, design tokens, accessibility standards, conventions | Designers, Frontend developers |
| **[UX](./ux/)** | User workflows, interaction patterns, form design standards | Product team, Designers, Frontend |
| **[Auth](./auth/)** | Authentication, RBAC, security practices, enterprise security | Security team, Backend developers |
| **[Database](./database/)** | Schema design, migrations, query optimization, backup procedures | Backend developers, DBAs |
| **[Testing](./testing/)** | Testing framework, procedures, accessibility testing, checklists | QA, All developers |
| **[Deployment](./deployment/)** | CI/CD pipeline, environment setup, monitoring, rollback procedures | DevOps, Release managers |
| **[Business](./business/)** | Product specs, business rules, roles, compliance requirements | Product team, Business analysts |
| **[Development](./development/)** | Local setup, coding standards, git workflow, build commands | All developers |
| **[Decisions](./decisions/)** | Architecture Decision Records (ADRs), strategic choices | Architects, Lead developers |
| **[Performance](./performance/)** | Performance targets, profiling, benchmarking, optimization | Performance engineers |
| **[Archive](./archive/)** | Historical/outdated documentation, audit trail | Archivists, Historical reference |

---

## 🎯 Mulai Cepat

### Saya ingin...

#### 📱 Membangun fitur UI
1. Baca [UI Quick Reference](./ui/README.md)
2. Lihat [Design System](./ui/design-system.md)
3. Ikuti [Accessibility Standards](./ui/accessibility-standards.md)
4. Selesai dengan testing di [Testing](./testing/README.md)

#### 🔌 Membuat API endpoint
1. Baca [API Endpoints Overview](./api/endpoints.md)
2. Ikuti [Authentication Requirements](./api/authentication-requirements.md)
3. Ikuti [Error Code Standards](./api/error-codes.md)
4. Test per [Testing Procedures](./testing/testing-framework.md)

#### 👤 Menambah fitur login
1. Baca [Authentication](./auth/authentication.md)
2. Ikuti [RBAC Authorization](./auth/authorization-rbac.md)
3. Baca [Security Practices](./auth/security-practices.md)
4. Deploy per [Deployment Pipeline](./deployment/deployment-pipeline.md)

#### 📊 Mengoptimalkan performa
1. Baca [Performance Targets](./performance/performance-targets.md)
2. Jalankan profiling per [Profiling Procedures](./performance/profiling-procedures.md)
3. Implementasikan [Caching Strategies](./performance/caching-strategies.md)
4. Monitor per [Monitoring Setup](./deployment/monitoring-alerting.md)

#### 💾 Membuat database migration
1. Baca [Schema Overview](./database/schema-overview.md)
2. Ikuti [Migration Procedures](./database/migration-procedures.md)
3. Optimize query per [Query Optimization](./database/query-optimization.md)
4. Backup per [Backup Recovery](./database/backup-recovery.md)

#### 🚀 Mendeploy ke produksi
1. Baca [Deployment Pipeline](./deployment/deployment-pipeline.md)
2. Setup environment per [Environment Configuration](./deployment/environment-configuration.md)
3. Monitor per [Monitoring & Alerting](./deployment/monitoring-alerting.md)
4. Prepare rollback per [Rollback Procedures](./deployment/rollback-procedures.md)

---

## 📋 Matriks Referensi Cepat

### Berdasarkan Peran

| Peran | Domain Utama | Domain Sekunder |
|------|------|------|
| **Frontend Developer** | UI, UX, Testing | API, Development |
| **Backend Developer** | API, Database, Auth | Development, Testing |
| **DevOps/SysAdmin** | Deployment, Performance | Database, Testing |
| **QA/Tester** | Testing | API, UI, Database |
| **Security Engineer** | Auth, Deployment | API, Database |
| **Product Manager** | Business, Decisions | UX, API |
| **Data Analyst** | Database, Performance | Business |

### Berdasarkan Tugas

| Tugas | Domain Utama | Konsultasikan |
|------|------|------|
| **Buat fitur baru** | Business, UX, UI, API | Testing, Decisions |
| **Perbaiki bug** | Development, Testing | Terkait domain |
| **Optimize database** | Database, Performance | Testing, Deployment |
| **Secure aplikasi** | Auth, Deployment | API, Database |
| **Deploy update** | Deployment, Testing | Semua domain |
| **Dokumentasikan decision** | Decisions, Business | Terkait domain |
| **Setup development** | Development | UI, API, Database |

---

## 📖 Navigasi Domain Lengkap

### Layer Fondasi (Independent)
Kategori ini mandiri dan tidak bergantung pada domain lain:
- **[API Domain](./api/)** - Spesifikasi API dan endpoint
- **[Database Domain](./database/)** - Desain dan operasi database
- **[Auth Domain](./auth/)** - Authentication dan authorization

### Layer Interface (Depends on Foundation)
Kategori ini bergantung pada layer fondasi:
- **[UI Domain](./ui/)** - Komponen UI dan design system
- **[UX Domain](./ux/)** - User experience dan workflows

### Layer Eksekusi (Enables Deployment)
Kategori ini mengaktifkan deployment:
- **[Testing Domain](./testing/)** - Framework dan procedures
- **[Deployment Domain](./deployment/)** - Pipeline dan operations
- **[Development Domain](./development/)** - Setup dan tools

### Layer Strategi (Guides Everything)
Kategori ini memandu semua kategori:
- **[Business Domain](./business/)** - Requirements dan specs
- **[Decisions Domain](./decisions/)** - ADRs dan strategic choices
- **[Performance Domain](./performance/)** - Targets dan metrics

### Layer Arsip (Reference Only)
Kategori ini untuk referensi historis:
- **[Archive Domain](./archive/)** - Dokumentasi usang

---

## 🔗 Konvensi Cross-Domain

### Linking

Semua link cross-domain menggunakan **relative paths**:
```markdown
✅ Benar:   [Other Domain](../other-domain/)
❌ Salah:   [Other Domain](other-domain/)
```

### Organizing Files

Setiap domain README.md mendokumentasikan:
- **Purpose** - Mengapa domain ini ada
- **Quick Navigation** - Tabel file dan link
- **Folder Organization Rules** - Kapan menambah file baru
- **Related Domains** - Cross-domain links
- **Getting Started** - Entry points untuk audience berbeda
- **Search Tips** - Common questions → files

---

## 📝 Metadata & Frontmatter

Dokumentasi content (bukan README.md) menggunakan YAML frontmatter:

```yaml
---
domain: api
purpose: endpoint-reference
version: 1.0
updated: 2024-01-15
owner: platform-team
status: stable
related: [auth, database, testing]
---
```

Lihat [Metadata Guide](./METADATA_GUIDE.md) untuk details lengkap.

---

## 📚 Dokumentasi Khusus

### Panduan Lengkap

- **[METADATA_GUIDE.md](./METADATA_GUIDE.md)** - Frontmatter structure dan metadata requirements
- **[AGENTS.md](../AGENTS.md)** - Instruksi agen dan workspace conventions

### Daftar File Arsip

Dokumentasi usang disimpan di [Archive Domain](./archive/README.md) untuk referensi historis.

---

## ✅ Daftar Periksa Navigasi

Gunakan checklist ini untuk memastikan dokumentasi tetap terorganisir:

- [ ] Semua 12 domain folder ada dengan README.md
- [ ] Setiap README mengikuti template struktur
- [ ] Cross-domain links menggunakan relative paths
- [ ] Tidak ada broken links dalam navigation
- [ ] File naming konsisten (lowercase hyphenated)
- [ ] Metadata guide tersedia dan up-to-date
- [ ] Archive domain terdokumentasi dengan policy

---

## 🔄 Maintenance

### Menambah dokumentasi baru

1. Tentukan domain mana yang sesuai
2. Baca domain README.md untuk naming conventions
3. Ikuti Folder Organization Rules
4. Tambahkan YAML frontmatter (jika content, bukan README)
5. Link di domain README.md Quick Navigation

### Mengarsipkan dokumentasi

1. Baca [Archive Domain Policy](./archive/README.md)
2. Pindahkan file ke archive/ dengan metadata `archived: true`
3. Hapus link dari dokumentasi aktif
4. Dokumentasikan di Archive Index

### Updating documentation

1. Edit file di lokasi yang tepat
2. Update `updated:` field di frontmatter
3. Komit dengan pesan yang jelas

---

## 🆘 Mendapatkan Bantuan

- **Tidak tahu mulai dari mana?** → Lihat section "Mulai Cepat" di atas
- **Mencari topik spesifik?** → Gunakan [Matriks Referensi Cepat](#matriks-referensi-cepat)
- **Mencari domain yang tepat?** → Lihat [Navigasi Domain Lengkap](#navigasi-domain-lengkap)
- **Pertanyaan tentang file spesifik?** → Baca domain README.md atau [METADATA_GUIDE.md](./METADATA_GUIDE.md)

---

## 📊 Statistik Dokumentasi

| Metrik | Nilai |
|--------|-------|
| Total Domain | 12 |
| Total README.md | 13 (root + 12 domain) |
| Organization | Lowercase hyphenated folders |
| File Naming | PURPOSE[-VARIANT].md pattern |
| Frontmatter | YAML with domain/purpose/version/updated/owner/status |
| Link Style | Relative paths (../domain/) |

---

**Terakhir diperbarui**: 2024-01-15  
*Hub navigasi dokumentasi LAPORIN dipertahankan per AGENTS.md conventions.*

