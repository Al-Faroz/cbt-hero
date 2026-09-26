# CBT-HERO — IMPLEMENTATION SPEC & ROADMAP FINAL

**Versi:** 1.0  
**Tanggal:** 25 September 2026  
**Status:** **FINAL BASELINE / DOKUMEN TURUNAN NORMATIF**  
**Induk:** `CBT-HERO_DOKUMEN_ACUAN_UTAMA.md`  
**UI/UX:** `CBT-HERO_UIUX_ACUAN.md`  
**Database:** `CBT-HERO_DATABASE_ERD_FINAL.md` + `CBT-HERO_SCHEMA_v1.0.sql`  
**Auth/Security:** `CBT-HERO_AUTH_SECURITY_SESSION_FINAL.md`  
**Routes/API:** `CBT-HERO_ROUTES_API_FINAL.md`

---

# 0. TUJUAN DAN ATURAN PENGERJAAN

Dokumen ini menerjemahkan requirement CBT-HERO yang sudah FIX menjadi **urutan implementasi nyata**. Tujuannya adalah mencegah desain ulang requirement saat coding, mengurangi revisi lintas modul, dan memastikan setiap fase mempunyai dependency, output, serta checkpoint PASS yang jelas.

Dokumen ini **tidak membuat requirement bisnis baru**. Jika terjadi perbedaan, urutan otoritas tetap:

```text
CBT-HERO_DOKUMEN_ACUAN_UTAMA.md
        ↓
Dokumen turunan normatif sesuai domain
        ↓
CBT-HERO_IMPLEMENTATION_SPEC_ROADMAP_FINAL.md
        ↓
Mockup / source referensi
        ↓
Source code
```

Prinsip pengerjaan:

1. **Jangan mendesain requirement saat coding.**
2. Selesaikan dependency sebelum modul yang bergantung padanya.
3. Setiap fase harus mempunyai **browser test + integration checkpoint**.
4. Tidak boleh melanjutkan fase berikut jika ada blocker data-loss, auth, lifecycle, atau transaction pada fase aktif.
5. Controller tipis, Service authoritative, Model fokus query/persistence.
6. UI harus mengikuti `CBT-HERO_UIUX_ACUAN.md`, bukan improvisasi per halaman.
7. Runtime ujian tidak boleh menjadi chatty.
8. Performa, durable answer, dan konsistensi state lebih penting daripada dekorasi.
9. Source ZYA/Garuda hanya referensi perilaku/fitur; jangan copy struktur tanpa alasan teknis.
10. Perubahan requirement hanya boleh melalui revisi eksplisit dokumen acuan.

---

# 1. TARGET STRUKTUR APLIKASI

Struktur direkomendasikan tetap dekat dengan pola standar CodeIgniter 4 agar mudah dirawat.

```text
app/
├── Config/
├── Controllers/
│   ├── Participant/
│   ├── Manager/
│   ├── Api/
│   │   ├── Participant/
│   │   ├── Manager/
│   │   └── Public/
│   └── Media.php
├── Filters/
├── Models/
├── Services/
│   ├── Auth/
│   ├── Master/
│   ├── Exam/
│   ├── Runtime/
│   ├── Scoring/
│   ├── Import/
│   ├── Report/
│   └── System/
├── Libraries/
├── Validation/
├── Views/
│   ├── participant/
│   ├── manager/
│   └── public/
└── Helpers/

public/
├── assets/
│   ├── manager/
│   ├── exam/
│   ├── public/
│   ├── vendor/
│   └── fonts/
└── media/   (jika media publik memang disajikan langsung)

writable/
├── cache/
├── logs/
├── session/
├── uploads/
├── staging/
└── backup/
```

Catatan:

- file upload/runtime tidak boleh bercampur dengan source aplikasi;
- asset vendor production disimpan lokal, tidak bergantung CDN;
- bundle Manager, Exam, dan Public dipisah;
- `JetBrains Mono` digunakan khusus credential print sesuai UI/UX acuan;
- tidak memakai jQuery/React/Vue/Angular/Axios.

---

# 2. BATAS TANGGUNG JAWAB LAYER

## 2.1 Controller

Controller bertanggung jawab untuk:

- menerima request;
- memanggil validation/filter;
- mengambil actor/session context;
- memanggil Service;
- membentuk HTTP response;
- tidak memuat business rule kompleks.

Controller **tidak boleh** menjadi tempat randomisasi soal, scoring, lifecycle Attempt, transaction multi-table, atau authorization bisnis.

## 2.2 Service

Service adalah authority untuk:

- lifecycle Kegiatan/Jadwal/Attempt;
- ownership;
- preparation;
- sync answer;
- scoring;
- finalisasi;
- import commit;
- backup/restore command;
- audit event penting.

Semua aturan FIX yang memengaruhi data harus berada pada Service, bukan hanya JavaScript/UI.

## 2.3 Model

Model bertanggung jawab pada:

- query;
- persistence;
- pagination/filtering;
- atomic update yang sederhana;
- locking/query concurrency bila diperlukan.

Model tidak menentukan kebijakan role/lifecycle.

## 2.4 JavaScript

JavaScript peserta bertanggung jawab pada:

- IndexedDB;
- navigation lokal;
- rendering question workspace;
- local-first answer;
- sync queue;
- retry/backoff;
- timer display;
- local tab lock;
- media cache/prefetch;
- status UI.

JavaScript bukan authority final untuk authorization, time, scoring, atau Attempt state.

---

# 3. DAFTAR FASE IMPLEMENTASI

```text
PHASE 0   Fondasi Project CI4
PHASE 1   Auth + Session + Shell
PHASE 2   Master Data
PHASE 3   Kegiatan + Peserta Ujian + Ruang + Kartu
PHASE 4   Bank Soal + Template + Import + Renderer
PHASE 5   Jadwal + Susulan + Preparation
PHASE 6   Attempt Engine + IndexedDB + Sync + Timer
PHASE 7   Pelaksanaan + Token + Monitoring + Kontrol
PHASE 8   Scoring Akademik + Live Edit + Finalisasi
PHASE 9   Hasil + Laporan + Analisis + Public Live Scoring
PHASE 10  Psikologis
PHASE 11  Settings lanjutan + Backup/Restore + Log + Pengosongan Data
PHASE 12  Performance Hardening + Regression + Final Polish
```

Urutan di atas adalah dependency order. Pekerjaan kecil yang benar-benar independen boleh paralel, tetapi **state engine tidak boleh didahului UI dekoratif**.

---

# 4. PHASE 0 — FONDASI PROJECT CI4

## 4.1 Tujuan

Membuat baseline aplikasi yang siap menerima semua modul tanpa perlu restrukturisasi besar di tengah pengerjaan.

## 4.2 Dependency

Tidak ada.

## 4.3 Output Utama

- project CI4 standar;
- `.env` deployment baseline;
- database connection;
- `CBT-HERO_SCHEMA_v1.0.sql` berhasil diterapkan;
- session database aktif;
- timezone konsisten;
- helper response JSON;
- global exception/error policy production;
- asset pipeline lokal;
- layout Manager, Participant, Public dasar;
- base filters skeleton;
- logging/audit skeleton;
- health/readiness internal sederhana.

## 4.4 Komponen

### Config

```text
App.php
Database.php
Routes.php
Filters.php
Security.php
Session.php
ContentSecurityPolicy.php (jika dipakai)
```

### Shared Services

```text
ApiResponseService
RequestContextService
AuditService
SettingsService (read-only awal)
```

### Views

```text
manager/_layout.php
manager/_sidebar.php
manager/_navbar.php
participant/_layout.php
public/_layout.php
```

### Assets

```text
public/assets/manager/
public/assets/exam/
public/assets/public/
public/assets/vendor/
public/assets/fonts/
```

## 4.5 Database yang Dipakai

```text
sys_settings
ci_sessions
audit_logs
```

## 4.6 UI/UX Checkpoint

- design token dasar konsisten;
- manager shell desktop-first;
- offcanvas/sidebar mobile manager;
- participant shell mobile-first;
- loading/empty/error component dasar tersedia;
- tidak ada CDN wajib.

## 4.7 PASS

Phase 0 PASS jika:

- aplikasi boot tanpa error;
- koneksi database stabil;
- session database terbentuk;
- error production tidak membocorkan SQL/path/secret;
- asset Manager/Exam/Public terpisah;
- struktur public/writable benar;
- source tidak perlu writable untuk workflow normal.

---

# 5. PHASE 1 — AUTH + SESSION + SHELL

## 5.1 Tujuan

Menyelesaikan dua auth realm dan security foundation sebelum modul bisnis.

## 5.2 Dependency

Phase 0.

## 5.3 Komponen Manager

### Controller

```text
Manager/AuthController
Manager/DashboardController
Api/Manager/AuthController
Api/Manager/DashboardController
```

### Service

```text
ManagerAuthService
ManagerSessionService
PermissionService
LoginThrottleService
```

### Model

```text
ManagerUserModel
LoginAttemptModel
AuditLogModel
SessionModel (bila perlu wrapper)
```

### View/JS

```text
manager/auth/login.php
manager/dashboard/index.php
assets/manager/js/auth.js
```

## 5.4 Komponen Participant

### Controller

```text
Participant/AuthController
Participant/ExamListController (shell awal)
Api/Participant/AuthController
```

### Service

```text
ParticipantAuthService
ParticipantSessionService
```

### Model

```text
PesertaModel
```

### View/JS

```text
participant/login.php
participant/exam-list.php (placeholder fungsional)
assets/exam/js/auth.js
```

## 5.5 Filter

Minimal:

```text
ManagerAuthFilter
ParticipantAuthFilter
ManagerRoleFilter
Csrf/CI4 Security
MaintenanceFilter (jika dipakai)
JsonRequestFilter (opsional)
```

## 5.6 Security Rules

- Manager dan Participant realm terpisah pada application session namespace;
- `password_hash()` / `password_verify()`;
- participant printable password tetap mempunyai reversible encrypted copy;
- encryption key berasal dari `sys_settings`;
- SQL memakai Query Builder/binding;
- output escaped by default;
- login throttle;
- session regeneration setelah login;
- manager role hanya ADMIN/OPERATOR;
- status user/peserta diperiksa server-side.

## 5.7 Tables

```text
manager_users
peserta
auth_login_attempts
ci_sessions
sys_settings
audit_logs
```

## 5.8 PASS

- login/logout Manager;
- login/logout Peserta;
- realm tidak saling menganggap sudah login;
- session expired recovery benar;
- invalid password tidak membuat SQL/error leak;
- brute-force throttle bekerja;
- CSRF mutation Manager bekerja;
- participant login case normalization username sesuai acuan;
- account nonaktif ditolak;
- audit login/logout penting tercatat.

---

# 6. PHASE 2 — MASTER DATA

## 6.1 Scope

Default Tahun Pelajaran/Semester berada di Settings, sedangkan Kegiatan menyimpan nilai historisnya. Tidak ada modul Master Periode.

```text
Rombel
Peserta
Account Login Peserta
Mata Pelajaran
Import Peserta
```

## 6.2 Dependency

Phase 1.

## 6.3 Controller

```text
Manager/Master/RombelController
Manager/Master/PesertaController
Manager/Master/MataPelajaranController
Api/Manager/Master/RombelController
Api/Manager/Master/PesertaController
Api/Manager/Master/MataPelajaranController
Api/Manager/ImportController
```

## 6.4 Service

```text
RombelService
PesertaService
ParticipantCredentialService
MataPelajaranService
ImportService
ImportValidationService
```

## 6.5 Model

```text
RombelModel
PesertaModel
MataPelajaranModel
ImportJobModel
ImportStagingItemModel
```

## 6.6 UI

Mengikuti pola Manager:

```text
Page Header
Summary (jika relevan)
Filter
Table server-side
Bulk Action
Form modal/drawer/page
Import Staging page
```

Peserta harus mempunyai tab/area **Account Login** untuk:

- generate username yang belum ada;
- regenerate username massal (2× warning);
- generate/reset password (2× warning untuk bulk);
- printable credential preview;
- uppercase normalization;
- generated charset menghindari `O I L 0 1`.

## 6.7 Import Peserta

Template minimal:

```text
WAJIB:
NISN
Nama
Jenis Kelamin
Rombel

OPSIONAL:
Keterangan
Username
Password
```

Flow wajib:

```text
UPLOAD
→ PARSE
→ STAGING
→ VALIDATION
→ PREVIEW
→ FIX / EXCLUDE
→ COMMIT
```

## 6.8 Tables

```text
rombel
peserta
mata_pelajaran
import_jobs
import_staging_items
audit_logs
```

## 6.9 PASS

- CRUD aman;
- unique constraint benar;
- pagination/filter server-side;
- import duplicate/invalid tertahan di staging;
- commit atomic;
- credential generated sesuai aturan;
- credential bulk tidak mengubah data peserta yang sedang terkunci oleh ujian berjalan;
- XSS dari nama/keterangan tidak dirender mentah;
- responsive manager PASS.

---

# 7. PHASE 3 — KEGIATAN + PESERTA UJIAN + RUANG + KARTU

## 7.1 Scope

```text
Kegiatan Ujian
Peserta Ujian
Ruang
Nomor Peserta
Kartu Ujian
Preflight administratif
```

## 7.2 Dependency

Phase 2 sudah lulus uji integrasi Master Data. Sebelum membuat Kegiatan,
implementasikan Settings dasar untuk default Tahun Pelajaran/Semester. Sebelum
mencetak Kartu, implementasikan Settings identitas instansi/logo/alamat/URL CBT
yang digunakan kartu. Pengaturan lain yang tidak diperlukan modul awal tetap
berada pada Phase 11.

## 7.3 Controller

```text
Manager/Exam/KegiatanController
Manager/Exam/PesertaUjianController
Manager/Exam/RuangController
Manager/Exam/KartuUjianController
Api/Manager/Exam/KegiatanController
Api/Manager/Exam/PesertaUjianController
Api/Manager/Exam/RuangController
```

## 7.4 Service

```text
KegiatanService
PesertaKegiatanService
RuangService
NomorPesertaService
KartuUjianService
KegiatanPreflightService
ParticipantDataLockService
```

## 7.5 Model

```text
KegiatanModel
RuangModel
PesertaKegiatanModel
```

## 7.6 Lifecycle

```text
DRAFT
→ BERJALAN
→ SELESAI
```

Rules:

- DRAFT: struktur peserta/ruang/nomor masih dapat diubah;
- BERJALAN: **tidak ada perubahan data peserta terkait pelaksanaan**;
- SELESAI mengikuti aturan lifecycle di Acuan Utama;
- saat action SELESAI, server tidak boleh menerima state kontradiktif seperti Attempt ACTIVE.

## 7.7 Nomor Peserta

- per Kegiatan;
- prefix Operator + sequence sistem;
- dapat regenerate sebelum lock;
- bukan login identity/FK transaksi;
- snapshot disimpan pada Attempt saat START.

## 7.8 Kartu Ujian

A4, 10 kartu/lembar, 2×5.

Credential print:

```text
NOMOR PESERTA
USERNAME
PASSWORD
→ JetBrains Mono SemiBold/Bold
```

Data lain menggunakan font standar CBT-HERO.

## 7.9 Tables

```text
kegiatan
ruang
peserta_kegiatan
peserta
rombel
```

## 7.10 PASS

- assign peserta all/tingkat/rombel/individual;
- assign ruang bulk;
- generate/regenerate Nomor Peserta;
- duplicate membership ditolak;
- data lock saat BERJALAN server-side;
- kartu print konsisten dan credential mudah dibedakan;
- preflight menampilkan credential/ruang/membership yang belum lengkap;
- tidak ada perubahan peserta saat Kegiatan BERJALAN.

---

# 8. PHASE 4 — BANK SOAL + TEMPLATE + IMPORT + RENDERER

## 8.1 Scope Akademik

Enam tipe teknis:

```text
PG
PG Kompleks
Matching
Isian Singkat
Uraian
PG Bertingkat
```

Dua kelompok UI/scoring peserta:

```text
PILIHAN GANDA / KLIK
→ PG + PG Kompleks + Matching + PG Bertingkat

ISIAN & URAIAN / KETIK
→ Isian Singkat + Uraian
```

## 8.2 Dependency

Phase 3.

## 8.3 Controller

```text
Manager/Bank/BankSoalController
Manager/Bank/SoalController
Manager/Bank/ImportSoalController
Manager/Bank/PreviewController
Manager/Bank/PrintController
Manager/MediaController
Api/Manager/Bank/*
```

## 8.4 Service

```text
BankSoalService
QuestionService
QuestionRevisionService
QuestionValidationService
TemplateBuilderService
WordImportService
ExcelImportService
ImportStagingService
QuestionRendererService
PdfQuestionService
MediaAssetService
RichContentSanitizerService
```

## 8.5 Model

```text
BankSoalModel
BankTypeConfigModel
SoalModel
SoalRevisionModel
SoalOpsiModel
SoalMatchingPairModel
SoalShortAnswerTextModel
SoalRevisionMediaModel
MediaAssetModel
ImportJobModel
ImportStagingItemModel
```

## 8.6 Shared Renderer

Satu normalized renderer harus dipakai oleh:

```text
Preview Manager
Bank detail
Exam client
PDF print
```

Tujuannya mencegah soal terlihat berbeda antara preview dan peserta.

## 8.7 Rich Content

- HTML hasil import disanitize;
- gambar dinormalisasi;
- formula KaTeX-compatible;
- Arabic/RTL didukung;
- table responsive;
- audio lokal;
- video external/reference;
- macro dokumen tidak dieksekusi.

## 8.8 Isian Singkat

TEXT:

```text
multiple accepted answers
trim
case normalization
whitespace normalization
```

NUMERIC:

```text
expected numeric
tolerance optional
comma decimal normalization
```

## 8.9 State

```text
DRAFT
READY
```

READY hanya setelah validation/preflight bank PASS.

## 8.10 PASS

- manual editor seluruh 6 tipe;
- Word/Excel import staging;
- preview sama dengan renderer exam;
- PDF bank dapat dicetak;
- rich content aman;
- media reference valid;
- shuffle metadata tersedia sesuai tipe;
- bank READY mempunyai struktur lengkap;
- tidak ada key/rubric bocor ke participant API.

---

# 9. PHASE 5 — JADWAL + SUSULAN + PREPARATION

## 9.1 Scope

```text
Jadwal utama
BUKA / TAHAN
Mulai
Batas Mulai
Durasi
Tampilkan Nilai Saat Selesai
Susulan N kali
Preparation / Prepared Assignment
```

## 9.2 Dependency

Phase 4.

## 9.3 Controller

```text
Manager/Schedule/JadwalController
Manager/Schedule/SusulanController
Manager/PreparationController
Api/Manager/Schedule/*
Api/Manager/PreparationController
```

## 9.4 Service

```text
JadwalService
SusulanService
ScheduleEligibilityService
PreparationService
AssignmentBuilderService
AssignmentFingerprintService
RandomizationService
PreflightService
```

## 9.5 Model

```text
JadwalModel
JadwalPesertaTargetModel
PreparedAssignmentModel
PreparedAssignmentItemModel
PreparedAssignmentMediaModel
```

## 9.6 Jadwal Rule

- `Mulai` = earliest allowed START;
- `Batas Mulai` = latest new START;
- participant yang sudah START tetap dapat RESUME setelah Batas Mulai;
- timer individual dimulai dari START aktual;
- saat BERJALAN hanya perubahan operasional yang sudah diizinkan, misalnya Extend Batas Mulai dan Tambah Waktu;
- `Tampilkan Nilai Saat Selesai` terkunci setelah Attempt pertama START.

## 9.7 Susulan

- child dari Jadwal utama;
- tidak membuat duplicate bank;
- tidak dibatasi artificial 1×;
- boleh Susulan #1 ... #N;
- target peserta spesifik;
- belum pernah START → Attempt pertama;
- replacement → Attempt lama SUPERSEDED dan assignment baru sesuai rule.

## 9.8 Preparation

Preparation harus menyelesaikan pekerjaan berat sebelum peak:

```text
resolve target
validate bank
select item
shuffle
stable option order
resolve revision
media manifest
prepared assignment
fingerprint
```

**START tidak melakukan randomisasi/generasi berat sebagai fallback.**

Jika assignment belum siap:

```text
START → reject NOT_PREPARED
```

## 9.9 PASS

- jadwal valid;
- BUKA/TAHAN hanya gate;
- TAHAN tidak menghentikan participant yang sudah berada di workspace;
- Susulan berulang berhasil;
- Preparation resumable/chunked;
- selective rebuild bekerja;
- fingerprint/revision invalidation benar;
- no `ORDER BY RAND()` mass start;
- START preflight dapat mengetahui assignment siap/tidak siap.

---

# 10. PHASE 6 — ATTEMPT ENGINE + INDEXEDDB + SYNC + TIMER

## 10.1 Tujuan

Ini adalah **core engine CBT-HERO**. Phase ini harus ditangani sebagai prioritas tertinggi kualitas dan testing.

## 10.2 Dependency

Phase 5.

## 10.3 Participant Controller/API

```text
Participant/ExamController
Participant/WorkspaceController
Participant/FinishController
Api/Participant/ExamDiscoveryController
Api/Participant/AttemptController
Api/Participant/BootstrapController
Api/Participant/AnswerSyncController
Api/Participant/ClientEventController
```

## 10.4 Service

```text
ExamDiscoveryService
EligibilityService
AttemptStartService
AttemptResumeService
AttemptBootstrapService
AttemptOwnershipService
ActiveAttemptLockService
ActiveClientService
AnswerSyncService
AttemptTimerService
FinalizeService
ClientEventService
ResultVisibilityService
```

## 10.5 Model

```text
AttemptModel
AttemptActiveLockModel
AttemptPauseEventModel
AttemptTimeAdjustmentModel
AttemptClientEventModel
AttemptResponseModel
PreparedAssignmentModel
PreparedAssignmentItemModel
```

## 10.6 Exam Client JavaScript

Direkomendasikan dipisah modul:

```text
exam-db.js
exam-bootstrap.js
exam-renderer.js
exam-navigation.js
exam-answer-store.js
exam-sync.js
exam-timer.js
exam-media.js
exam-tab-lock.js
exam-submit.js
exam-runtime-state.js
```

Dexie.js menjadi wrapper IndexedDB.

Object store minimal:

```text
attempt_meta
question_cache
answer_store
sync_queue
media_manifest
runtime_state
```

## 10.7 START

START harus ringan:

```text
validate auth
validate membership
validate schedule/token/exambrowser rule
validate no other ACTIVE Attempt
validate prepared assignment
create Attempt
create active lock/client generation
snapshot identity
return bootstrap metadata
```

## 10.8 Answer Flow

```text
USER ANSWER
→ IndexedDB transaction
→ UI update
→ sync_queue
→ async sync
→ server ACK
```

Server menerima hanya mutation yang:

- ownership benar;
- Attempt ACTIVE;
- generation/client valid;
- revision lebih baru/valid;
- item milik assignment;
- answer valid untuk tipe soal;
- tidak melewati authority time.

## 10.9 Timer — FIX

```text
ATTEMPT ACTIVE
→ waktu terus berjalan
```

Termasuk:

```text
network putus
browser ditutup
browser crash
HP mati
background
```

Pause hanya melalui **Reset Akses** yang authoritative di server.

## 10.10 Offline Timeout

Saat client mencapai 0:

```text
input lock
pending answer yang valid tetap tersimpan lokal
jika online → sync → finalize
jika offline → TIMEOUT_PENDING lokal
network kembali → sync valid pending → server finalize
```

Server authority menentukan acceptance berdasarkan waktu/attempt rules yang sudah FIX.

## 10.11 Duplicate Tab

- local tab lock;
- tab kedua attempt yang sama diblok secara lokal;
- tidak memakai polling berat;
- tidak auto-logout hanya karena visibility/focus.

## 10.12 Media Prefetch

```text
question package
→ critical image prefetch
→ audio prefetch bila reasonable
→ READY
```

Video tetap online/reference streaming.

## 10.13 PASS

Wajib lulus:

- START hanya sekali/idempotent;
- one participant = one active Attempt;
- one Attempt = one active client;
- refresh same device recover;
- offline answer tidak hilang;
- sync duplicate/old revision aman;
- reconnect tidak menciptakan retry storm;
- browser close timer tetap jalan;
- timeout lock;
- submit drain + finalize;
- package tidak membawa answer key;
- 2-tab guard;
- new device butuh lifecycle Reset Akses bila lease masih aktif.

Phase 6 **tidak boleh dinyatakan selesai hanya karena UI bisa mengerjakan soal**. Durable answer dan recovery harus PASS.

---

# 11. PHASE 7 — PELAKSANAAN + TOKEN + MONITORING + KONTROL

## 11.1 Scope

```text
Token global
Monitoring Ujian
Reset Akses
Tambah Waktu
Paksa Selesai
Attempt Detail
Adaptive monitoring refresh
```

## 11.2 Dependency

Phase 6.

## 11.3 Controller

```text
Manager/Execution/TokenController
Manager/Execution/MonitoringController
Api/Manager/Execution/TokenController
Api/Manager/Execution/MonitoringController
Api/Manager/Execution/AttemptControlController
```

## 11.4 Service

```text
TokenService
MonitoringService
ResetAccessService
TimeAdjustmentService
ForceFinishService
ExecutionAuditService
AdaptiveLoadService
```

## 11.5 Tables

```text
token_control
attempt
attempt_active_lock
attempt_pause_event
attempt_time_adjustment
attempt_client_event
attempt_response
audit_logs
```

## 11.6 Monitoring UI

Summary:

```text
Total
Belum
Sedang
Selesai
Tidak Terdeteksi
```

Table:

```text
No Peserta
Nama
Rombel
Ruang
Ujian
Status
Used
Remaining
Last Sync
Aksi
```

Aksi individual/bulk:

```text
Reset Akses
Tambah Waktu
Paksa Selesai
Detail
```

## 11.7 Token

Global:

```text
ON/OFF
current token
next rotate
manual generate
auto rotate interval
```

Rotation tidak mengganggu participant yang sudah di workspace.

## 11.8 PASS

- monitoring pagination/delta ringan;
- no N+1 berat;
- Reset Akses preserve jawaban dan pause waktu sesuai rule;
- resume setelah Reset Akses benar;
- Tambah Waktu audited;
- Paksa Selesai authoritative;
- bulk action idempotent;
- Token ON/OFF bekerja pada START/RE-ENTRY;
- monitoring dapat diperlambat Adaptive Load Protection tanpa mengganggu Answer Sync.

---

# 12. PHASE 8 — SCORING AKADEMIK + LIVE EDIT + FINALISASI

## 12.1 Dependency

Phase 7.

## 12.2 Controller

```text
Manager/Scoring/ScoringController
Manager/Scoring/CorrectionController
Manager/Scoring/LiveEditController
Manager/Scoring/FinalizationController
Api/Manager/Scoring/*
```

## 12.3 Service

```text
AcademicScoringService
ObjectiveScoringService
ShortAnswerScoringService
EssayScoringService
ManualOverrideService
VoidService
RescoreService
QuestionLiveEditService
ResultSnapshotService
ResultFinalizationService
```

## 12.4 Model

```text
ScoreAdjustmentLogModel
ResultSnapshotModel
ResultItemSnapshotModel
OfficialResultPointerModel
SoalRevisionModel
AttemptResponseModel
```

## 12.5 Scoring

Tiga layer:

```text
answer
→ raw point
→ type contribution/weight
→ final 0–100
```

Rules per tipe mengikuti Acuan Utama.

Kelompok Klik:

```text
PG
PG Kompleks
Matching
PG Bertingkat
```

Kelompok Ketik:

```text
Isian Singkat
Uraian
```

## 12.6 Live Edit

Stable `question_id` + `revision_no`.

Kategori perubahan:

```text
A content fix
B key/weight
C structural
VOID
```

Finished Attempt tidak dibuka kembali hanya karena Live Edit.

## 12.7 Finalisasi

- semua koreksi/rescore/VOID/manual override dilakukan sebelum FINAL;
- setelah FINAL tidak ada normal workflow untuk reopen;
- Official Result menunjuk snapshot final yang immutable.

## 12.8 PASS

- semua tipe scoring akurat;
- partial PG Kompleks/Matching sesuai rule;
- Isian TEXT/NUMERIC sesuai normalization;
- Uraian manual decimal/rubric;
- override audited;
- VOID denominator benar;
- rescore deterministic;
- Live Edit tidak merusak Attempt aktif;
- final result immutable;
- result snapshot repeatable.

---

# 13. PHASE 9 — HASIL + LAPORAN + ANALISIS + PUBLIC LIVE SCORING

## 13.1 Dependency

Phase 8.

## 13.2 Scope

```text
Hasil Ujian
Rekap Nilai
Analisis Soal
Export Excel/PDF
Halaman Selesai participant
Public Live Scoring
```

## 13.3 Controller

```text
Manager/Report/HasilUjianController
Manager/Report/RekapNilaiController
Manager/Report/AnalisisSoalController
Manager/Execution/LiveScoringController
Public/LiveScoringController
Api/Manager/Report/*
Api/Manager/Execution/LiveScoringController
Api/Public/LiveScoringController
```

## 13.4 Service

```text
HasilUjianService
RekapNilaiService
AnalisisSoalService
ReportExportService
ParticipantFinishViewService
LiveScoringService
LiveScoringSnapshotService
```

## 13.5 Halaman Selesai

Jika `Tampilkan Nilai Saat Selesai = ON`:

```text
NILAI PILIHAN GANDA
xx.xx

NILAI ISIAN & URAIAN
xx.xx / DALAM PROSES
```

Kelompok Pilihan Ganda/Klik mencakup PG, PG Kompleks, Matching, PG Bertingkat.

Nilai hanya bagian dari **UI Halaman Selesai**, tidak ada portal histori hasil peserta.

## 13.6 Public Live Scoring

Satu display memilih satu Jadwal.

Table EXACT:

```text
No | No Peserta | Nama | Skor | Status
```

Cycle:

```text
fetch snapshot
→ ranking
→ display top
→ auto-scroll sampai baris terakhir
→ BARU fetch snapshot berikutnya
→ rerank
→ ulang dari atas
```

Tidak ada refresh dataset di tengah scroll.

Score memakai seluruh kelompok Klik dengan denominator maksimum kelompok tersebut; belum dijawab = 0 sementara.

## 13.7 PASS

- official-only report benar;
- superseded Attempt tidak masuk hasil resmi;
- export konsisten;
- analysis item-aware;
- Halaman Selesai sesuai setting;
- Live Score tidak memberi traffic kontinu per row;
- URL public random/regenerate/off;
- public hanya memuat field yang diizinkan;
- 2.000 peserta tidak membuat polling Live Scoring agresif.

---

# 14. PHASE 10 — PSIKOLOGIS

## 14.1 Prinsip

Tes Psikologis **menggunakan Exam Core yang sama**. Yang berbeda adalah item engine, scoring engine, dan beberapa setting.

Tidak membuat duplicate auth/runtime/monitoring.

## 14.2 Dependency

Phase 6 wajib; Phase 8/9 untuk reporting lengkap.

## 14.3 Controller

```text
Manager/Psych/InstrumentController
Manager/Psych/DimensionController
Manager/Psych/ItemController
Manager/Psych/ScoringController
Manager/Psych/ResultController
Api/Manager/Psych/*
```

## 14.4 Service

```text
PsychInstrumentService
PsychItemService
PsychImportService
PsychScoringService
PsychNormService
PsychResultService
PsychReportService
```

## 14.5 Model

```text
PsychInstrumentModel
PsychDimensionModel
PsychItemModel
PsychItemRevisionModel
PsychOptionModel
PsychScoringMatrixModel
PsychNormModel
PsychItemRevisionMediaModel
PsychDimensionResultModel
```

## 14.6 V1 Scope

```text
Likert
Forced Choice SELECT_ONE / MOST_LEAST
Dimension
Normal/Reverse
Scoring matrix
Norm optional
Interpretation optional
Rich content / image
Word + Excel import
Manual editor
```

Tidak mencakup special psych engine seperti Kraepelin/Pauli/reaction/drawing/projective.

## 14.7 Participant Flow

Tetap:

```text
Login
→ Daftar Ujian
→ Konfirmasi
→ Token jika ON
→ START/RESUME
→ Workspace
→ Submit
→ Selesai
```

Tidak ada public Live Scoring untuk psych.

## 14.8 PASS

- instrument DRAFT/READY;
- dimensions/matrix/norm valid;
- Word/Excel staging;
- manual editor;
- runtime menggunakan Attempt Engine yang sama;
- scoring dimension reproducible;
- reverse scoring benar;
- hasil participant tidak membocorkan interpretasi yang tidak diperuntukkan;
- Excel/PDF report bekerja.

---

# 15. PHASE 11 — SETTINGS + BACKUP/RESTORE + LOG + PENGOSONGAN DATA

## 15.1 Dependency

Seluruh core schema stabil.

## 15.2 Controller

```text
Manager/System/SettingsController
Manager/System/BackupController
Manager/System/RestoreController
Manager/System/LogController
Manager/System/ClearDataController
Api/Manager/System/*
```

## 15.3 Service

```text
SettingsService
BrandingService
DatabaseBackupService
DatabaseRestoreService
MediaBackupService
MediaRestoreService
LogService
LogRetentionService
ClearDataService
DependencyPreviewService
```

## 15.4 Settings

Minimal:

```text
institution name
logo
address
CBT URL
academic display/year
timezone
card identity
footer/copyright
default pre-exam message
encryption key secret record
log retention
```

Secret setting tidak ditampilkan ke UI normal/log/API.

## 15.5 Backup/Restore Permission

```text
ADMIN
- Backup Database
- Restore Database
- Backup Media
- Restore Media
- Pengosongan Data

OPERATOR
- Backup Database
- Backup Media
```

Encryption Key ikut Backup Database karena berada pada database.

## 15.6 Pengosongan Data

Dependency-aware dan tidak menghapus:

```text
manager users
critical system configuration
encryption key
```

Kelompok clear mengikuti Acuan Utama.

## 15.7 PASS

- DB backup dapat direstore pada test environment;
- encryption key tetap cocok sesudah restore DB;
- media backup/restore terpisah;
- safety backup DB sebelum restore bila implementasinya ringan;
- restore Admin-only;
- clear dependency preview benar;
- tidak bisa menghapus admin terakhir;
- log retention auto purge;
- audit restore/clear tercatat.

---

# 16. PHASE 12 — PERFORMANCE HARDENING + REGRESSION + FINAL POLISH

## 16.1 Tujuan

Membuktikan bahwa CBT-HERO memenuhi target operasional nyata, bukan hanya lulus functional test.

## 16.2 Load Scenario

Wajib:

```text
500 peserta
1000 peserta
1500 peserta
2000 peserta
```

Stress:

```text
2500 peserta
```

Stretch bila environment memungkinkan:

```text
3000 peserta
```

## 16.3 Scenario Test

Minimal:

```text
mass login
mass START
question bootstrap
steady answer sync
network loss/reconnect
refresh/recovery
submit wave
timeout wave
monitoring open
live scoring open
reset access burst
add time burst
susulan
live edit/rescore
report generation
backup outside peak
```

## 16.4 Metrics

```text
CPU
RAM
DB connections
query rate
slow query
HTTP latency p50/p95/p99
error rate
network throughput
sync queue delay
answer ACK latency
START latency
FINALIZE latency
```

## 16.5 Adaptive Load Protection

Yang boleh diperlambat/dikurangi:

```text
monitoring refresh
charts
noncritical summary
analysis rebuild
public snapshot cadence (naturally cycle-bound)
```

Yang **tidak boleh dikorbankan**:

```text
login
START
RESUME
answer sync
timer authority
submit/finalize
Reset Akses
```

## 16.6 Database Hardening

- EXPLAIN query kritis;
- index final berdasarkan traffic nyata;
- hindari full scan pada monitoring/sync;
- pagination server-side;
- no per-participant N+1;
- transaction singkat;
- preparation chunked;
- archive/purge event/log sesuai retention.

## 16.7 UI Polish

- cross viewport participant;
- image zoom;
- formula responsive;
- Arabic/RTL;
- table horizontal scroll;
- font credential print;
- accessibility keyboard/focus wajar;
- no zoom lock mobile;
- loading/empty/error consistent.

## 16.8 PASS

Phase 12 PASS jika acceptance target pada dokumen testing terpenuhi dan tidak ada blocker severity tinggi.

---

# 17. DEPENDENCY MAP RINGKAS

```text
PHASE 0 FOUNDATION
   ↓
PHASE 1 AUTH/SESSION
   ↓
PHASE 2 MASTER DATA
   ↓
PHASE 3 KEGIATAN/PESERTA/RUANG
   ↓
PHASE 4 BANK/IMPORT/RENDERER
   ↓
PHASE 5 JADWAL/PREPARATION/SUSULAN
   ↓
PHASE 6 ATTEMPT/CACHE/SYNC/TIMER
   ↓
PHASE 7 MONITORING/CONTROL
   ↓
PHASE 8 SCORING/LIVE EDIT/FINAL
   ↓
PHASE 9 RESULT/REPORT/LIVE SCORE
   ↓
PHASE 10 PSYCH
   ↓
PHASE 11 SYSTEM/BACKUP
   ↓
PHASE 12 PERFORMANCE/FINAL POLISH
```

Psych editor/instrument master dapat mulai lebih awal secara paralel setelah Phase 4 renderer/import stabil, tetapi **psych runtime tetap tidak boleh membuat engine Attempt kedua**.

---

# 18. CRITICAL PATH — JANGAN DITUNDA

Komponen yang harus diprioritaskan karena risiko desain/data-loss tinggi:

```text
Auth realm separation
Participant ownership
Prepared Assignment
Attempt Start
Active Attempt Lock
Active Client Generation
IndexedDB schema
Answer local-first
Sync idempotency/revision
Timer authority
Offline recovery
Finalize
Reset Akses
Scoring snapshot
Official Result Pointer
```

UI dekorasi, chart tambahan, dan polish tidak boleh menghambat critical path.

---

# 19. DEFINITION OF DONE PER MODUL

Sebuah modul **belum selesai** hanya karena CRUD/UI-nya tampil.

Definition of Done minimum:

```text
[ ] route final sesuai Routes/API
[ ] permission/ownership server-side
[ ] validation
[ ] Service rule
[ ] DB constraint/index relevan
[ ] transaction boundary
[ ] audit bila destructive/critical
[ ] UI sesuai UI/UX acuan
[ ] responsive test
[ ] empty/loading/error state
[ ] browser functional test
[ ] negative test
[ ] integration test dependency
[ ] no new PHP/JS console/runtime error
[ ] docs tidak menyimpang
```

Untuk runtime exam tambahkan:

```text
[ ] idempotency
[ ] retry test
[ ] offline/reconnect
[ ] duplicate request
[ ] stale revision
[ ] ownership tamper
[ ] timeout
[ ] refresh/recovery
```

---

# 20. GIT / DELIVERY CHECKPOINT

Setiap submodul dikerjakan dan diuji secara mandiri: contoh Rombel → uji
tampilan/fungsi Rombel → perbaikan → PASS → push → lanjut Peserta. Jangan
mensyaratkan data/modul yang belum dibuat pada acceptance submodul. Setelah
seluruh submodul dalam satu kelompok selesai, lakukan uji integrasi antarmodul
dan perbaiki sebelum kelompok berikutnya.

Setiap submodul mempunyai commit checkpoint; akhir Phase dapat diberi tag.
Settings dasar boleh dikerjakan lebih awal sebagai dependency nyata Kegiatan
dan Kartu. Perubahan pada Settings dasar diuji kembali ketika modul pemakainya
dibuat.

Contoh:

```text
phase-00-foundation
phase-01-auth-session
phase-02-master-data
...
phase-12-release-candidate
```

Jangan menggabungkan banyak Phase besar dalam satu commit raksasa.

Sebelum checkpoint:

```text
PHP lint
JS syntax check
DB verify
browser smoke test
route list review
security negative test
```

Tidak push source yang menyertakan:

```text
.env production
backup database real
secret
runtime upload peserta
writable/log production
session data
```

---

# 21. DOKUMEN YANG HARUS DIPAKAI SAAT CODING

Developer/AI yang mengerjakan CBT-HERO harus menggunakan set berikut sebagai satu paket:

```text
1. CBT-HERO_DOKUMEN_ACUAN_UTAMA.md
2. CBT-HERO_UIUX_ACUAN.md
3. CBT-HERO_DATABASE_ERD_FINAL.md
4. CBT-HERO_SCHEMA_v1.0.sql
5. CBT-HERO_AUTH_SECURITY_SESSION_FINAL.md
6. CBT-HERO_ROUTES_API_FINAL.md
7. CBT-HERO_IMPLEMENTATION_SPEC_ROADMAP_FINAL.md
8. CBT-HERO_TEST_ACCEPTANCE_FINAL.md (setelah dibuat)
```

`CBT-HERO_UIUX_Mockup.html` / visual HTML hanya companion visual, bukan sumber requirement yang mengalahkan Markdown normatif.

---

# 22. HAL YANG TIDAK BOLEH BERUBAH SAAT IMPLEMENTASI TANPA REVISI DOKUMEN

```text
Role = Admin / Operator / Peserta
Dua auth realm
Participant login = Username + Password
Nomor Peserta per Kegiatan
Data peserta lock saat ujian BERJALAN
Prepared Assignment sebelum START
One Participant = max one ACTIVE Attempt
One Attempt = one active client
Timer Model A
Reset Akses sebagai pause authoritative
Local-first answer + IndexedDB
Async idempotent sync
6 tipe akademik teknis
2 kelompok UI/scoring besar
Psych memakai core Attempt yang sama
Token global
Exam Browser Phase 2
Susulan child Jadwal dan dapat N kali
Live Score full-cycle snapshot
Final Result immutable
Encryption Key di database
Backup Database dan Media terpisah
JetBrains Mono untuk credential print
No jQuery / no SPA framework berat
```

---

# 23. HAL YANG BOLEH DITUNING TANPA MENGUBAH REQUIREMENT

Parameter berikut boleh ditentukan saat benchmark/implementasi:

```text
monitoring refresh interval
sync debounce
retry backoff
package chunk size
media prefetch threshold
pagination default
adaptive load thresholds
connection pool/server config
index tambahan berdasarkan EXPLAIN
cache TTL
log retention default
username/password exact generated length
```

Tuning tidak boleh mengubah lifecycle atau business rule.

---

# 24. URUTAN KERJA SETELAH DOKUMEN INI

Setelah dokumen ini:

```text
1. Susun CBT-HERO_TEST_ACCEPTANCE_FINAL.md
2. Review konsistensi seluruh dokumen normatif
3. Freeze baseline v1.x
4. Mulai PHASE 0
5. Kerjakan phase-by-phase dengan checkpoint PASS
```

Tidak perlu membuat dokumen konsep baru sebelum coding kecuali ditemukan kontradiksi nyata.

---

# 25. STATUS FINAL

Roadmap ini menetapkan urutan implementasi final CBT-HERO:

> **Fondasi → Auth → Master → Kegiatan → Bank → Jadwal/Preparation → Attempt Engine → Pelaksanaan → Scoring → Hasil → Psikologis → System → Performance.**

Target utamanya bukan sekadar menyelesaikan fitur, melainkan menjaga tiga sifat CBT-HERO sejak awal sampai release:

> **MUDAH — RINGAN — POWERFUL**

serta memastikan keputusan arsitektur yang sudah FIX tidak kembali didesain ulang di tengah proses pengerjaan.
