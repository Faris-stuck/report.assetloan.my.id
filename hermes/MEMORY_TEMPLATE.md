# MEMORY.md Template

**Lokasi:** `hermes/data/memory/profiles/{role}-{userId}-MEMORY.md`

## Apa itu MEMORY.md?

`MEMORY.md` adalah file kurator yang disimpan otomatis oleh Hermes setelah setiap percakapan. File ini menyimpan profil terstruktur tentang user agar Hermes "mengenal" user lebih dalam. Ini adalah metode curation yang jauh lebih baik daripada dump transcript mentah.

---

## Struktur MEMORY.md

```markdown
# Memory for {user-role}-{user-id}

**Last updated:** 2026-04-08 14:35:42

## Profile
- Nama: Dika
- Role: admin (akses penuh)
- Keahlian: PHP, MySQL, web development
- Preferensi: Suka dokumentasi terstruktur, tidak suka ambiguitas

## Preferences
- Bahasa: Indonesia (dengan istilah teknis English)
- Format: Bullet points, code examples, table jika kompleks
- Pace: Cepat, langsung ke point
- Detail level: Teknis (tapi bukan low-level)

## Goals & Objectives
- Mengimplementasikan Hermes Agent style architecture di PHP
- Membangun semantic memory system untuk AI agent
- Integrasi dengan external providers (optional)
- Monitoring & improvement pipeline

## Recent Conversation Insights

- User menanyakan: Apakah Hermes saya sudah sesuai dengan NousResearch/hermes-agent?
- User menginginkan: Context summarization + MEMORY.md curation
- User fokus pada: Gap fungsional, bukan fine-tuning

## Lessons Learned

- Context compression adalah gap terbesar, bukan retrieval
- Kurasi manual memory lebih penting daripada full transcript storage
- Skill synthesis pipeline sudah cukup solid
- User mengerti arsitektur agent, tidak perlu over-explain

## Next Steps

- Monitor MEMORY.md berkembang seiring percakapan
- Kumpulkan feedback tentang kualitas memory recall
- Pertimbangkan SQLite FTS5 jika TF-scoring tidak cukup baik
```

---

## Bagaimana MEMORY.md Di-Update?

1. **After Every Chat Turn** (di `chat.php`):
   - Percakapan terbaru dianalyze
   - Section Profile, Preferences, Goals diupdate jika ada perubahan
   - Recent Conversation Insights di-refine
   - MEMORY.md di-flush ke disk

2. **Structure Preservation**:
   - User bisa edit MEMORY.md manual jika perlu koreksi
   - Next chat turn akan preserve existing sections
   - Hanya Recent Insights + metadata yang di-update otomatis

3. **Pada Prompt Berikutnya** (di `memory-helper.php`):
   - Hermes membaca MEMORY.md (prioritas tinggi)
   - Content diinjeksi ke system prompt
   - Agent "tahu" user ini siapa dan apa yang diinginkan

---

## Contoh File Actual

**Path:** `e:\xampp\htdocs\PROJECT\hermes\data\memory\profiles\admin-1-MEMORY.md`

```markdown
# Memory for admin-1

**Last updated:** 2026-04-08 15:42:18

## Profile
- Nama: Administrator PROJECT
- Role: admin
- Level pengalaman: Expert PHP/MySQL/Web
- Interest: AI agent architecture, semantic systems, automation

## Preferences
- Bahasa: ID + EN technical terms
- Format: Code-first, then explanation
- Tier: High-level architecture + implementation details
- Engagement: Direct, no fluff

## Goals & Objectives
- Align Hermes PHP with official Hermes Agent (NousResearch)
- Full memory + retrieval + summarization
- Optional: external provider integration (Mem0, Honcho)

## Recent Conversation Insights

- Asked about 10-method Hermes Agent blueprint
- Interested in context summarization specifically
- Prefers prioritization by impact/effort ratio
- Chose Option A: summarization + MEMORY.md curation

## Lessons Learned

- Gap #4 (context summarization) adalah blocker for long chats
- MEMORY.md curation beats raw transcript storage
- User understands architecture, keep explanations concise
- Focus on implementation gaps, not theoretical perfection

## Next Steps

- Deploy summarization + MEMORY.md flush
- Validate memory recall improves over time
- Collect user feedback on memory quality
- Consider SQLite FTS5 if simple TF-scoring underperforms
```

---

## Konfigurasi di `config/ai_agent.php`

```php
'summarization_enabled' => true,
'summarization_threshold_messages' => 20,  // Mulai summarize di 20+ messages
'summarization_preserve_recent' => 5,      // Keep last 5 messages intact
'summarization_min_lines' => 3,            // Min 3 lines per summary
'summarization_max_lines' => 15,           // Max 15 lines per summary
'summarization_target_tokens' => 2000,     // Target token after compression
```

---

## Flow Diagram

```
┌─────────────────────────┐
│  User sends message     │
└────────────┬────────────┘
             │
             ▼
┌─────────────────────────┐
│  Load MEMORY.md         │
│  (for system prompt)    │
└────────────┬────────────┘
             │
             ▼
┌─────────────────────────┐
│  AI processes request   │
└────────────┬────────────┘
             │
             ▼
┌─────────────────────────┐
│  Build conversation     │
│  (history + reply)      │
└────────────┬────────────┘
             │
             ▼
┌─────────────────────────┐
│  Should Summarize?      │
│  (> 20 messages?)       │
└────────────┬────────────┘
         Yes │ No
             │  └─────┐
             │        │
             ▼        ▼
        ┌──────────────────────┐
        │ Summarize & Compress │ (Keep recent intact)
        └─────────┬────────────┘
                  │
                  ▼
        ┌──────────────────────┐
        │ Build MEMORY.md      │
        │ (extract insights)   │
        └─────────┬────────────┘
                  │
                  ▼
        ┌──────────────────────┐
        │ Flush to disk        │
        │ (hermes/data/memory) │
        └──────────────────────┘
```

---

## Checklist untuk Verify

- [x] `summarization-helper.php` created with 10 functions
- [x] `aiAgentFlu shMemoryMarkdown()` integrated into `chat.php`
- [x] `aiAgentShouldSummarizeConversation()` checks threshold
- [x] MEMORY.md includes Profile, Preferences, Goals, Insights, Lessons
- [x] `config/ai_agent.php` has summarization keys
- [x] `memory-helper.php` includes MEMORY.md in prompt (priority high)
- [x] All PHP files pass syntax check
