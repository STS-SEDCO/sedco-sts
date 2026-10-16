# SEDCO Smart Training System — Supabase Migration Plan

Status: **planning only**. This branch must not be deployed to users as-is.

## Current verified delivery
- `*.html` on GitHub Pages is a browser-only preview, currently backed by `localStorage`.
- `training-calendar.js` reads `sedcoApplications` and `sedcoPreviewUser` from each browser.
- `index.html` explicitly describes preview mode and does not verify passwords.
- `*.php` is a separate implementation backed by MySQL/MariaDB; GitHub Pages cannot execute PHP.
- Existing P0 workflow updates are contained in the parent development branch, not production.

## Target architecture
- **Frontend:** Existing SaaS-style visual design preserved, static HTML/CSS/JavaScript hosted on GitHub Pages initially.
- **Database:** A new dedicated Supabase PostgreSQL project for SEDCO, **never the Tumbooh database**.
- **Auth:** Supabase Auth identities; never trust a browser-supplied role, department, reviewer ID, or email.
- **Access:** PostgreSQL RLS and privileged server-side transactional approval functions. Staff may only see their own applications; HOD assigned/department only; GM and Pengerusi only their permitted review scopes; both Training officers have oversight and may not approve their own applications.
- **Storage:** Private Supabase Storage buckets for attachments and signatures, accessed only under equivalent authorization.
- **Backend:** Replace PHP endpoints with Supabase PostgreSQL functions/Edge Functions as needed; direct MySQL code is not executable in Supabase.
- **Workflow:** Applicant -> Training Initial -> HOD -> GM -> Pengerusi -> Training Final -> Completed, respecting confirmed legacy role skip rules. Finance payment/registration information preserved for print/history but **not** an approval stage.
- **Notifications:** In-app events with authorized email delivery through secure server-side functions (no email provider secrets in browser code).
- **Versioning:** Preserve correction history, approval audit trails and existing record references.

## Ordered phases
1. Confirm **SEDCO-owned** Supabase project and billing organization. Check project cost first; do not deploy to Tumbooh.
2. Inventory all live data sources: each browser's localStorage, and any existing PHP/MySQL host. **Do not clear browser data or delete localStorage** until exported/verified. Browser data does not automatically sync across devices.
3. Take encrypted backups; obtain authorization before handling identifiable staff/training data; map source IDs to UUID identities without exposing credentials.
4. Create additive PostgreSQL schema, indexes, enums/check constraints, RLS, trigger safeguards, audit, and storage policies. Independently test cross-role privilege boundaries.
5. Migrate login/password process securely. Do **not** copy or treat preview passwords as valid credentials; Supabase Auth invitations/password resets may be needed.
6. Rewrite all client data access for BPL, PKK, TEA, workflow, calendar, submissions, training oversight, reports and notifications. Do not wire real users to a half-migrated application.
7. Import/validate records, comments, approvals, attachments and submitted signatures using count/hash sampling. No missing historical rows; keep mapping and logs.
8. Validate staging with test accounts: staff, two Training officers, Admin, HOD, GM, Pengerusi; test anti-self-approval, corrections, PDF exports, concurrency and RLS violations.
9. Only after stakeholder signoff: cut over real GitHub Pages frontend to Supabase. Retain rollback procedure, backups, and an agreed read-only window.

## Safety gates
- Never put service-role/secret API keys in GitHub Pages, JS bundle, GitHub commits or chat.
- Supabase publishable key in frontend is valid **only with correctly enforced RLS**.
- Migration scripts and Edge Functions are not yet written or deployed.
- Do not merge this branch or overwrite GitHub Pages production before successful staging validation.
