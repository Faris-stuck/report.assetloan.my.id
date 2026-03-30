# PROJECT - Windows/Linux Workflow

This repository is developed on Windows and deployed on Linux.
Use Git as the single source of truth to keep both environments in sync.

## Standard Workflow

1. Develop on Windows.
2. Commit and push changes to remote.
3. Deploy on Linux with fast-forward pull only.
4. If a Linux hotfix is made, commit + push from Linux, then pull on Windows.

## Rules

- Do not use manual folder copy between OS.
- Keep line ending policy from `.gitattributes` (LF for source files).
- Keep runtime artifacts out of Git (`assets/uploads`, logs, temp files).
- Keep file permissions normalized in Git (non-executable for regular web/source files).

## Suggested Local Git Settings

Run once per clone:

```bash
git config core.autocrlf false
git config core.filemode false
```

## Presentation Material

Presentation-ready project notes and slide content are available in:

- `PRESENTASI_PROJECT_PEMINJAMAN.md`
- `STRUKTUR_PROJECT_DAN_DATABASE.md`
