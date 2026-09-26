# CBT-HERO — ROUTES & API FINAL

**Versi:** 1.0  
**Tanggal:** 25 September 2026  
**Status:** **FINAL BASELINE / DOKUMEN TURUNAN NORMATIF**  
**Induk:** `CBT-HERO_DOKUMEN_ACUAN_UTAMA.md`  
**Database:** `CBT-HERO_DATABASE_ERD_FINAL.md`  
**Auth/Security:** `CBT-HERO_AUTH_SECURITY_SESSION_FINAL.md`  
**UI/UX:** `CBT-HERO_UIUX_ACUAN.md`

---

# 0. TUJUAN, OTORITAS, DAN BATAS

Dokumen ini menetapkan kontrak route dan API final CBT-HERO agar implementasi Controller, Service, JavaScript, Attempt Engine, Monitoring, Scoring, Import, Backup/Restore, dan UI tidak membuat pola endpoint sendiri-sendiri.

Dokumen ini **tidak membuat requirement bisnis baru**. Jika terjadi perbedaan, urutan otoritas tetap:

```text
CBT-HERO_DOKUMEN_ACUAN_UTAMA.md
        ↓
Dokumen turunan normatif sesuai domain
        ↓
Mockup / source referensi
        ↓
Source code
```

Prinsip:

> **Route harus sederhana dibaca manusia, API harus stabil, Service tetap authoritative, dan Exam runtime tidak boleh menjadi chatty.**

Nama method Controller/Service boleh dipoles saat coding, tetapi path, ownership, state transition, idempotency, dan transaction boundary pada dokumen ini tidak boleh berubah tanpa revisi eksplisit.

---

# 1. ARSITEKTUR ROUTE

CBT-HERO mempunyai empat kelompok route.

```text
PARTICIPANT UI
/...

PARTICIPANT API
/api/...

MANAGER UI + MANAGER API
/manager/...
/manager/api/...

PUBLIC DISPLAY
/live/{publicToken}
/api/public/live/{publicToken}/snapshot
```

## 1.1 Participant Realm

```text
/                  → landing/login peserta
/ujian             → daftar ujian peserta
/ujian/...         → flow peserta
/api/...            → participant API
```

## 1.2 Manager Realm

```text
/manager                  → login atau redirect dashboard
/manager/dashboard
/manager/master-data/...
/manager/master-ujian/...
/manager/pelaksanaan/...
/manager/hasil/...
/manager/system/...
/manager/api/...
```

## 1.3 Public Realm

Hanya untuk Live Scoring fullscreen dengan bearer-like random URL.

Tidak memakai manager session dan tidak menampilkan data selain field yang memang ditetapkan untuk Live Scoring.

---

# 2. KONVENSI HTTP

## 2.1 Method

```text
GET     → baca data / render page
POST    → create / command / state transition
PUT     → replace/update object secara penuh
PATCH   → update sebagian field/state
DELETE  → delete yang memang diizinkan lifecycle
```

State transition penting seperti START, FINALIZE, Reset Akses, Finalisasi Hasil, Preparation, dan Kegiatan SELESAI menggunakan **POST command endpoint**, bukan dipaksakan menjadi CRUD generik.

## 2.2 Content Type

API normal:

```text
Content-Type: application/json
Accept: application/json
```

Upload:

```text
multipart/form-data
```

Download PDF/Excel/backup/media mengikuti MIME file aktual.

## 2.3 API Response Envelope

Success:

```json
{
  "ok": true,
  "data": {},
  "meta": {
    "request_id": "...",
    "server_time": "2026-09-25T14:30:00+07:00"
  }
}
```

List/pagination:

```json
{
  "ok": true,
  "data": [],
  "meta": {
    "request_id": "...",
    "server_time": "...",
    "page": 1,
    "per_page": 50,
    "total": 1500,
    "filtered": 327
  }
}
```

Error:

```json
{
  "ok": false,
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "Data belum valid.",
    "fields": {
      "nama": "Nama wajib diisi."
    }
  },
  "meta": {
    "request_id": "...",
    "server_time": "..."
  }
}
```

API production tidak mengembalikan stack trace, SQL, path server, secret, encryption key, password hash, atau credential internal.

---

# 3. HTTP STATUS DAN ERROR CODE

## 3.1 HTTP Status Baseline

| HTTP | Penggunaan |
|---|---|
| 200 | sukses umum / idempotent repeat |
| 201 | resource baru dibuat |
| 204 | sukses tanpa body |
| 400 | request malformed |
| 401 | belum login / session tidak valid |
| 403 | actor/ownership tidak berhak |
| 404 | resource tidak ada/tidak terlihat actor |
| 409 | state conflict / duplicate / lifecycle conflict |
| 422 | validasi field/business input gagal |
| 423 | resource dikunci oleh state ujian/finalisasi |
| 429 | rate limit/throttle |
| 500 | unexpected server error |
| 503 | service sementara tidak siap / load protection |

## 3.2 Stable Error Code Minimum

```text
AUTH_REQUIRED
AUTH_INVALID
ACCOUNT_INACTIVE
ACCOUNT_LOCKED
FORBIDDEN
CSRF_INVALID
RATE_LIMITED
NOT_FOUND
VALIDATION_FAILED
STATE_CONFLICT
DATA_LOCKED
DEPENDENCY_EXISTS

EXAM_NOT_OPEN
EXAM_HELD
START_TOO_EARLY
START_WINDOW_CLOSED
TOKEN_REQUIRED
TOKEN_INVALID
EXAM_BROWSER_REQUIRED
PREPARATION_NOT_READY
ACTIVE_ATTEMPT_EXISTS
ATTEMPT_NOT_ACTIVE
ATTEMPT_ALREADY_FINISHED
ATTEMPT_CLIENT_MISMATCH
ATTEMPT_PAUSED
RESULT_VISIBILITY_LOCKED
RESULT_ALREADY_FINAL

ANSWER_ITEM_NOT_ASSIGNED
ANSWER_REVISION_STALE
ANSWER_MUTATION_DUPLICATE

IMPORT_NOT_READY
IMPORT_HAS_ERRORS
BACKUP_FAILED
RESTORE_FAILED
```

UI boleh menerjemahkan code ke pesan yang ramah. Logic frontend tidak boleh bergantung pada parsing teks error.

---

# 4. AUTH, FILTER, CSRF, OWNERSHIP

## 4.1 Filter Minimum

```text
ParticipantAuthFilter
ManagerAuthFilter
ManagerRoleFilter
CsrfFilter
```

Tambahan filter/per-service guard boleh digunakan untuk maintenance atau load protection.

## 4.2 CSRF

Semua mutation berbasis session/cookie wajib membawa CSRF, termasuk participant answer sync.

Untuk Exam runtime, token CSRF **stabil selama session yang relevan** dan tidak diregenerate pada setiap sync agar tidak menimbulkan race antar-request.

Header baseline:

```text
X-CSRF-TOKEN: <token>
```

## 4.3 Participant Ownership

Participant API **tidak menerima `peserta_id` sebagai authority**.

Authority peserta selalu berasal dari session server.

Setiap Attempt endpoint wajib memeriksa:

```text
Attempt milik peserta session?
Attempt state valid?
client_uuid valid?
client_generation valid?
assigned item valid?
```

## 4.4 Manager Permission

```text
ADMIN
→ seluruh manager route

OPERATOR
→ seluruh operasional
→ TIDAK boleh Restore DB/Media
→ TIDAK boleh Pengosongan Data
→ TIDAK boleh kelola Admin/secret sistem
```

---

# 5. IDEMPOTENCY CONTRACT

Critical mutation yang berpotensi double-click/retry memakai:

```text
Idempotency-Key: <UUID/random key>
```

Minimum endpoint yang wajib idempotent:

- Participant START;
- Participant FINALIZE;
- Manager Reset Akses;
- Manager Tambah Waktu;
- Manager Paksa Selesai;
- Kegiatan transition;
- Jadwal/Susulan creation command;
- Preparation command;
- Import COMMIT;
- Result Finalization;
- Backup creation;
- Restore execution;
- Pengosongan Data.

Answer Sync mempunyai mekanisme idempotency sendiri melalui:

```text
mutation_id
client_revision
server_revision
```

Retry request yang sama tidak boleh menggandakan jawaban, menambah waktu dua kali, membuat Attempt ganda, atau menjalankan restore dua kali.

---

# 6. PAGINATION, FILTER, SORT

Dataset besar selalu server-side.

Query baseline:

```text
page
per_page
q
sort
order=asc|desc
status
```

`per_page` default 50 untuk manager table besar dan dapat dibatasi server.

Karena Manager memakai DataTables 2, adapter frontend boleh mengubah parameter DataTables (`draw`, `start`, `length`, `search`, `order`) menjadi format API di atas. API bisnis **tidak wajib dikunci ke format DataTables**.

Monitoring boleh memakai cursor/delta tambahan jika implementasi terbukti lebih ringan.

---

# 7. PARTICIPANT UI ROUTES

| Method | Route | Auth | Fungsi |
|---|---|---|---|
| GET | `/` | Optional | Jika belum login tampil Login; jika login redirect `/ujian` |
| GET | `/ujian` | Participant | Daftar seluruh ujian yang menjadi hak peserta |
| GET | `/ujian/{jadwalId}/konfirmasi` | Participant | Konfirmasi ujian + token conditional |
| GET | `/attempt/{attemptId}` | Participant | Exam workspace shell |
| GET | `/attempt/{attemptId}/selesai` | Participant | Halaman Selesai; bukan portal hasil historis |

Aturan UI:

- membuka Konfirmasi tidak membuat Attempt;
- workspace hanya boleh dibuka untuk Attempt milik session;
- halaman selesai tidak menyediakan menu untuk membuka ulang hasil/jawaban/kunci/pembahasan setelah kembali ke Daftar Ujian.

---

# 8. PARTICIPANT AUTH API

## 8.1 Login

```text
POST /api/auth/login
```

Request:

```json
{
  "username": "AB72K",
  "password": "M8P4R7"
}
```

Behavior:

- normalisasi Username uppercase;
- cek status account/throttle;
- `password_verify()`;
- regenerate session ID;
- set `participant_auth`;
- generic error untuk credential salah.

Success:

```json
{
  "ok": true,
  "data": {
    "redirect": "/ujian"
  }
}
```

## 8.2 Logout

```text
POST /api/auth/logout
```

Auth: Participant  
CSRF: wajib

Tidak mengubah Attempt ACTIVE. Logout **bukan pause** dan timer tetap berjalan.

## 8.3 Session Status

```text
GET /api/auth/session
```

Dipakai untuk recovery UI, bukan polling berkala.

Response minimum:

```text
logged_in
username
has_active_attempt
active_attempt_id nullable
```

---

# 9. EXAM DISCOVERY API

## 9.1 Daftar Ujian

```text
GET /api/ujian
```

Mengembalikan ujian yang menjadi hak peserta dengan state UI:

```text
BELUM_DIBUKA
BISA_DIMULAI
LANJUTKAN
SELESAI
```

Per item minimum:

```text
jadwal_id
kegiatan_id
nama_kegiatan
nama_mapel/instrumen
tipe AKADEMIK/PSIKOLOGIS
mulai_at
batas_mulai_at
durasi_seconds
access_state
ui_state
attempt_id nullable
```

Jika peserta mempunyai Attempt ACTIVE pada ujian A, ujian lain tidak boleh START.

## 9.2 Confirmation Data

```text
GET /api/ujian/{jadwalId}/konfirmasi
```

Mengembalikan:

- identitas peserta;
- kegiatan/mapel/instrumen;
- ruang/no peserta;
- waktu/durasi;
- instruction default;
- Token ON/OFF;
- Exam Browser requirement;
- apakah START atau RESUME;
- reason jika belum eligible.

Tidak membuat Attempt.

## 9.3 Eligibility Check

Eligibility pada dasarnya dihitung saat confirmation/START. Endpoint eksplisit berikut boleh digunakan UI untuk recheck tepat sebelum submit START:

```text
GET /api/ujian/{jadwalId}/eligibility
```

Tidak boleh dipolling terus-menerus.

---

# 10. START API — CRITICAL

```text
POST /api/ujian/{jadwalId}/start
```

Auth: Participant  
CSRF: wajib  
Idempotency-Key: wajib

Request:

```json
{
  "agreement": true,
  "token": "ABCD12",
  "client_uuid": "uuid-client",
  "exam_browser_proof": null
}
```

`token` hanya wajib saat Token global ON. `exam_browser_proof` hanya dipakai ketika Kegiatan mewajibkan Exam Browser.

Validasi transaction order:

```text
1. participant session
2. membership/target
3. Jadwal BUKA/TAHAN
4. Mulai/Batas Mulai
5. Token jika ON
6. Exam Browser jika required
7. Prepared Assignment READY
8. tidak ada Attempt ACTIVE lain
9. acquire attempt_active_lock
10. create Attempt ACTIVE
11. bind client UUID/generation
12. mark Prepared Assignment USED
13. lock result visibility jika START pertama Jadwal
14. COMMIT
```

START **tidak boleh** melakukan randomisasi/materialisasi berat.

Success:

```json
{
  "ok": true,
  "data": {
    "attempt_id": 9912,
    "client_generation": 1,
    "start_at": "...",
    "deadline_at": "...",
    "bootstrap_url": "/api/attempt/9912/bootstrap",
    "redirect": "/attempt/9912"
  }
}
```

Jika request dengan `Idempotency-Key` yang sama terulang, server mengembalikan Attempt yang sama.

Jika Attempt ACTIVE lain milik peserta ada:

```text
409 ACTIVE_ATTEMPT_EXISTS
```

---

# 11. RESUME / RE-ENTRY API — CRITICAL

```text
POST /api/attempt/{attemptId}/resume
```

Auth: Participant  
CSRF: wajib  
Idempotency-Key: wajib

Digunakan untuk:

- re-entry normal;
- session browser hilang tetapi client identity masih tersedia;
- resume setelah Reset Akses.

Request baseline:

```json
{
  "token": "ABCD12",
  "client_uuid": "uuid-client",
  "client_generation": 3,
  "exam_browser_proof": null
}
```

Aturan:

- Token diminta jika Token global ON;
- Jadwal `TAHAN` menahan RESUME;
- Attempt harus ACTIVE;
- same client re-entry harus cocok client identity/generation;
- setelah Reset Akses, server menerima binding client baru pada generation yang sudah diinvalidate/ditingkatkan oleh Reset Akses;
- resume setelah pause menutup `attempt_pause_event` dan menggeser deadline sebesar pause;
- network putus/browser close tanpa Reset Akses tidak membuat pause.

Success mengembalikan:

```text
attempt_id
client_generation
deadline_at
server_sync_revision
bootstrap_url
redirect
```

---

# 12. ATTEMPT STATUS API

```text
GET /api/attempt/{attemptId}/status
```

Auth + ownership + client validation.

Digunakan saat recovery/reload, bukan polling cepat.

Response minimum:

```text
status
start_at
deadline_at
paused
client_generation
server_sync_revision
last_sync_at
scoring_status
```

---

# 13. BOOTSTRAP / PACKAGE API — CRITICAL

## 13.1 Bootstrap Header

```text
GET /api/attempt/{attemptId}/bootstrap
```

Header client:

```text
X-CBT-Client-Id
X-CBT-Client-Generation
```

Mengembalikan:

```text
Attempt metadata
participant snapshot
Jadwal metadata
start/deadline/remaining authority
question/item manifest
answer ACK snapshot
media manifest
revision map
server_sync_revision
package mode/chunk metadata
```

Tidak mengirim:

```text
answer key
rubric rahasia
correct option flags
psych scoring matrix
norma internal yang tidak perlu client
```

## 13.2 Package Chunk

Untuk Bank besar, bootstrap boleh mengembalikan package terpecah.

```text
GET /api/attempt/{attemptId}/package/{chunkNo}
```

Chunk immutable untuk Attempt/revision yang sama dan boleh diberi cache header yang aman.

## 13.3 Revision Checkpoint

Live Edit tidak membutuhkan push/WebSocket wajib.

Perubahan revision dapat diinformasikan melalui response Sync atau endpoint ringan:

```text
GET /api/attempt/{attemptId}/revisions?since={serverRevision}
```

Dipanggil hanya sesuai checkpoint, bukan polling agresif.

---

# 14. ANSWER SYNC API — PALING KRITIS

```text
POST /api/attempt/{attemptId}/sync
```

Auth: Participant  
CSRF: wajib  
Client headers: wajib

Request:

```json
{
  "base_server_revision": 120,
  "mutations": [
    {
      "mutation_id": "uuid-mutation",
      "item_id": 551,
      "client_revision": 4,
      "answer_payload": {},
      "is_flagged": false,
      "answered_at_client": "...",
      "client_elapsed_ms": 1622000
    }
  ]
}
```

Server flow per batch kecil:

```text
1. validate Attempt ownership/state/client generation
2. validate item memang assigned
3. compare client_revision/mutation_id
4. duplicate/older → safe ACK, no duplicate write
5. accepted → insert/update attempt_response
6. increment server_sync_revision
7. update last_sync_at/last_activity_at
8. return ACK + optional revision change signal
```

Response:

```json
{
  "ok": true,
  "data": {
    "attempt_status": "ACTIVE",
    "deadline_at": "...",
    "server_sync_revision": 124,
    "acks": [
      {
        "mutation_id": "uuid-mutation",
        "item_id": 551,
        "client_revision": 4,
        "accepted": true,
        "server_revision": 124
      }
    ],
    "revision_changes": []
  }
}
```

Server tidak menerima score/key dari client.

Answer Sync harus tetap prioritas tinggi saat adaptive load protection aktif.

---

# 15. MEANINGFUL CLIENT EVENT API

CBT-HERO tidak mengirim focus/blur spam.

Untuk event yang memang bermakna:

```text
POST /api/attempt/{attemptId}/event
```

Contoh event yang boleh dicatat:

```text
CACHE_RECOVERED
PACKAGE_READY
SYNC_RECOVERED
CLIENT_WARNING
```

Visibility/focus event hanya dicatat jika benar-benar diperlukan dan harus dibatasi/debounce.

Endpoint ini **non-kritis** dan boleh diturunkan/dinonaktifkan saat load tinggi.

---

# 16. FINALIZE API — CRITICAL

```text
POST /api/attempt/{attemptId}/finalize
```

Auth: Participant  
CSRF: wajib  
Idempotency-Key: wajib

Request:

```json
{
  "finish_reason": "SUBMIT",
  "last_server_revision": 124
}
```

`finish_reason` participant normal:

```text
SUBMIT
TIMEOUT
```

Client flow normal:

```text
Konfirmasi #1
→ Konfirmasi #2
→ drain sync queue
→ ACK
→ FINALIZE
```

Timeout:

```text
timer 0
→ lock input
→ sync pending lama
→ FINALIZE TIMEOUT
```

Server transaction:

```text
1. lock Attempt
2. validate ACTIVE
3. apply valid mutations yang sudah masuk
4. mark FINISHED + reason + finish_at
5. release attempt_active_lock
6. compute/update scoring state
7. create result snapshot version
8. update official result pointer jika berlaku
9. COMMIT
```

Repeated finalize pada Attempt yang sudah FINISHED mengembalikan state FINISHED yang sama, bukan error destructive.

Response minimum:

```text
attempt_status=FINISHED
finish_reason
finish_at
show_result
click_score nullable
typed_score nullable
typed_score_state COMPLETE/IN_PROCESS/NOT_APPLICABLE
redirect=/attempt/{id}/selesai
```

Untuk Psikologis, `show_result=false`.

---

# 17. PUBLIC LIVE SCORING

## 17.1 UI

```text
GET /live/{publicToken}
```

Fullscreen public display tanpa manager chrome.

Invalid/off token → 404/disabled page tanpa data.

## 17.2 Snapshot

```text
GET /api/public/live/{publicToken}/snapshot
```

No session. GET only.

Mengembalikan tepat kebutuhan display:

```text
snapshot_id/generated_at
Jadwal display info
rows:
  rank
  nomor_peserta
  nama
  skor
  status MENGERJAKAN/SELESAI
```

Tidak mengembalikan:

```text
username
password
NISN
jawaban
Attempt detail
IP/User-Agent
kunci
```

Browser public melakukan:

```text
FETCH SNAPSHOT
→ render/rank
→ scroll sampai row terakhir
→ baru FETCH snapshot berikutnya
```

Tidak ada refresh dataset di tengah siklus scroll.

Endpoint boleh memakai cache snapshot server-side ringan.

---

# 18. MANAGER AUTH & DASHBOARD ROUTES

## 18.1 UI

| Method | Route | Actor | Fungsi |
|---|---|---|---|
| GET | `/manager` | Public | Login atau redirect dashboard |
| GET | `/manager/dashboard` | Admin/Operator | Dashboard operasional |

## 18.2 Auth API

```text
POST /manager/api/auth/login
POST /manager/api/auth/logout
GET  /manager/api/auth/session
```

Manager session terpisah dari participant session namespace.

## 18.3 Dashboard API

```text
GET /manager/api/dashboard/summary
GET /manager/api/dashboard/active-schedules
GET /manager/api/dashboard/preflight-warnings
```

Dashboard API non-kritis dan boleh diturunkan refresh-nya di bawah high load.

---

# 19. MANAGER UI ROUTE MAP

```text
/manager/dashboard

/manager/master-data/rombel
/manager/master-data/peserta
/manager/master-data/mapel

/manager/master-ujian/kegiatan
/manager/master-ujian/peserta-ujian
/manager/master-ujian/ruang
/manager/master-ujian/bank-soal
/manager/master-ujian/psikologis
/manager/master-ujian/jadwal

/manager/pelaksanaan/token
/manager/pelaksanaan/monitoring
/manager/pelaksanaan/live-scoring

/manager/hasil/ujian
/manager/hasil/rekap
/manager/hasil/analisis
/manager/hasil/psikologis

/manager/system/backup
/manager/system/pengosongan-data
/manager/system/pengaturan
/manager/system/users
/manager/system/log
```

Detail modal/drawer tidak perlu route page baru bila cukup ditangani API + UI component.

---

# 20. KONTEKS TAHUN PELAJARAN DAN SEMESTER

Default Tahun Pelajaran dan Semester disimpan di Settings sebagai isian awal Kegiatan baru. API Kegiatan menerima `tahun_pelajaran` dan `semester`, lalu menyimpan keduanya pada row Kegiatan. Perubahan Settings tidak mengubah Kegiatan lama. Tidak ada route Master Periode.

---

# 21. MASTER DATA — ROMBEL API

```text
GET    /manager/api/rombel
POST   /manager/api/rombel
GET    /manager/api/rombel/{id}
PUT    /manager/api/rombel/{id}
PATCH  /manager/api/rombel/{id}/status
DELETE /manager/api/rombel/{id}
```

Field inti:

```text
tingkat
kode_rombel
display_name
status
```

Delete hanya untuk row yang aman dari dependency.

---

# 22. MASTER DATA — MATA PELAJARAN API

```text
GET    /manager/api/mapel
POST   /manager/api/mapel
GET    /manager/api/mapel/{id}
PUT    /manager/api/mapel/{id}
PATCH  /manager/api/mapel/{id}/status
DELETE /manager/api/mapel/{id}
```

Field inti:

```text
kode_mapel
nama_mapel
singkatan
status
urutan
```

Tidak ada teacher/KKM/schedule pada Master Mapel.

---

# 23. MASTER DATA — PESERTA API

## 23.1 CRUD

```text
GET   /manager/api/peserta
POST  /manager/api/peserta
GET   /manager/api/peserta/{id}
PUT   /manager/api/peserta/{id}
PATCH /manager/api/peserta/{id}/status
```

Data yang mempengaruhi pelaksanaan **dikunci jika peserta terikat Kegiatan BERJALAN**.

Jika dikunci:

```text
423 DATA_LOCKED
```

## 23.2 Account Login

```text
GET  /manager/api/peserta/{id}/account
POST /manager/api/peserta/{id}/account/generate-username
POST /manager/api/peserta/{id}/account/reset-password
```

Bulk:

```text
POST /manager/api/peserta/accounts/generate-usernames
POST /manager/api/peserta/accounts/reset-passwords
```

Bulk Username/Password wajib dua warning pada UI dan memakai Idempotency-Key.

Tidak boleh dijalankan pada target yang data pelaksanaannya sudah terkunci.

## 23.3 Printable Credential

Decrypt printable password hanya dilakukan di server pada endpoint berizin, misalnya untuk kartu/credential preview. Plaintext password tidak boleh menjadi field list API umum.

---

# 24. IMPORT ENGINE GENERIC

Import peserta, Bank Word/Excel, dan Psikologis Word/Excel memakai pola yang sama.

## 24.1 Upload

```text
POST /manager/api/imports
```

Multipart fields minimum:

```text
import_type
context_type
context_id nullable
file
```

`import_type`:

```text
PESERTA
BANK_WORD
BANK_EXCEL
PSYCH_WORD
PSYCH_EXCEL
```

## 24.2 Status / Staging

```text
GET /manager/api/imports/{jobId}
GET /manager/api/imports/{jobId}/items
```

## 24.3 Parse

```text
POST /manager/api/imports/{jobId}/parse
```

Boleh dijalankan chunk/resumable.

## 24.4 Validate

```text
POST /manager/api/imports/{jobId}/validate
```

## 24.5 Fix / Exclude Staging Item

```text
PATCH /manager/api/imports/{jobId}/items/{itemId}
POST  /manager/api/imports/{jobId}/items/{itemId}/exclude
POST  /manager/api/imports/{jobId}/items/{itemId}/include
```

## 24.6 Commit

```text
POST /manager/api/imports/{jobId}/commit
```

Idempotency-Key: wajib.

Commit hanya jika staging valid sesuai rule context. Job COMMITTED yang dipanggil ulang mengembalikan hasil commit yang sama.

## 24.7 Download Template

```text
GET /manager/import-template/peserta.xlsx
GET /manager/import-template/bank-soal.docx
GET /manager/import-template/bank-soal.xlsx
GET /manager/import-template/psikologis.docx
GET /manager/import-template/psikologis.xlsx
```

Template yang dihasilkan harus mengikuti renderer/schema import CBT-HERO, bukan struktur internal database mentah.

---

# 25. RUANG API

```text
GET    /manager/api/ruang
POST   /manager/api/ruang
GET    /manager/api/ruang/{id}
PUT    /manager/api/ruang/{id}
PATCH  /manager/api/ruang/{id}/status
DELETE /manager/api/ruang/{id}
```

Ruang reusable lintas Kegiatan.

---

# 26. KEGIATAN API

## 26.1 CRUD

```text
GET    /manager/api/kegiatan
POST   /manager/api/kegiatan
GET    /manager/api/kegiatan/{id}
PUT    /manager/api/kegiatan/{id}
DELETE /manager/api/kegiatan/{id}
```

Delete hanya DRAFT yang aman dependency.

Field utama:

```text
nama
jenis AKADEMIK/PSIKOLOGIS
tahun_pelajaran
semester
keterangan
exam_browser_required
status
```

## 26.2 Start Kegiatan

```text
POST /manager/api/kegiatan/{id}/start
```

Transition:

```text
DRAFT → BERJALAN
```

Server melakukan preflight; command tidak melakukan Preparation berat.

Jika belum siap:

```text
409 PREPARATION_NOT_READY
```

## 26.3 Selesaikan Kegiatan

```text
POST /manager/api/kegiatan/{id}/finish
```

Boleh jika:

- Jadwal/Susulan yang sudah dibuat telah melewati waktu pelaksanaan;
- tidak ada Attempt ACTIVE.

Transition:

```text
BERJALAN → SELESAI
```

Kegiatan SELESAI tetap boleh mendapat Susulan baru sebagai operational exception sesuai requirement; tidak ada workflow reopen Kegiatan.

## 26.4 Preflight Aggregate

```text
GET /manager/api/kegiatan/{id}/preflight
```

Mengembalikan checklist actionable:

```text
peserta credential
nomor peserta
ruang
Bank/Instrumen READY
Jadwal valid
Prepared Assignment READY
critical media
blocking issues
warning issues
```

---

# 27. PESERTA UJIAN / MEMBERSHIP API

## 27.1 List

```text
GET /manager/api/kegiatan/{kegiatanId}/peserta
```

## 27.2 Tambah Peserta

```text
POST /manager/api/kegiatan/{kegiatanId}/peserta
```

Request dapat memakai selector:

```text
ALL
TINGKAT
ROMBEL
IDS
```

Server mematerialisasi membership eksplisit `peserta_kegiatan`.

## 27.3 Hapus Membership

```text
DELETE /manager/api/kegiatan/{kegiatanId}/peserta/{pesertaKegiatanId}
```

Hanya ketika lifecycle mengizinkan.

## 27.4 Assign Ruang

Individual:

```text
PATCH /manager/api/kegiatan/{kegiatanId}/peserta/{pesertaKegiatanId}/ruang
```

Bulk:

```text
POST /manager/api/kegiatan/{kegiatanId}/peserta/assign-ruang
```

## 27.5 Generate / Regenerate Nomor Peserta

```text
POST /manager/api/kegiatan/{kegiatanId}/nomor-peserta/generate
```

Request minimum:

```text
prefix
start_sequence
scope/filter
```

Nomor Peserta bukan login identity.

Data peserta/membership/ruang/no peserta tidak boleh berubah setelah Kegiatan BERJALAN, kecuali Susulan operational target yang secara eksplisit diizinkan requirement.

---

# 28. KARTU UJIAN ROUTES

Preview:

```text
GET /manager/master-ujian/kegiatan/{id}/kartu
```

PDF:

```text
GET /manager/master-ujian/kegiatan/{id}/kartu.pdf
```

Filter query boleh mencakup ruang/rombel/peserta.

Credential card menampilkan:

```text
Nomor Peserta
Username
Password
```

Ketiga value wajib memakai **JetBrains Mono SemiBold/Bold** pada renderer kartu.

QR hanya membawa URL CBT, bukan credential plaintext.

---

# 29. BANK SOAL AKADEMIK API

## 29.1 Bank CRUD

```text
GET    /manager/api/bank-soal
POST   /manager/api/bank-soal
GET    /manager/api/bank-soal/{bankId}
PUT    /manager/api/bank-soal/{bankId}
DELETE /manager/api/bank-soal/{bankId}
```

Delete hanya DRAFT yang belum dipakai.

## 29.2 Type Configuration

```text
GET /manager/api/bank-soal/{bankId}/type-config
PUT /manager/api/bank-soal/{bankId}/type-config
```

Validasi READY:

```text
total weight tipe aktif = 100
selection_count valid
shuffle/scoring config valid
```

## 29.3 Manual Question Editor

```text
GET    /manager/api/bank-soal/{bankId}/soal
POST   /manager/api/bank-soal/{bankId}/soal
GET    /manager/api/bank-soal/{bankId}/soal/{soalId}
PUT    /manager/api/bank-soal/{bankId}/soal/{soalId}
DELETE /manager/api/bank-soal/{bankId}/soal/{soalId}
```

PUT/DELETE normal hanya untuk DRAFT sebelum live execution. Setelah digunakan, perubahan memakai revision/live-edit command.

## 29.4 READY / DRAFT

```text
POST /manager/api/bank-soal/{bankId}/ready
POST /manager/api/bank-soal/{bankId}/draft
```

Kembali ke DRAFT hanya jika lifecycle/preparation belum mengunci Bank.

## 29.5 Preview / Print

```text
GET /manager/master-ujian/bank-soal/{bankId}/preview
GET /manager/master-ujian/bank-soal/{bankId}/print.pdf
```

Renderer harus sama semantiknya dengan Exam renderer.

---

# 30. LIVE EDIT SOAL API

## 30.1 Create Revision

```text
POST /manager/api/bank-soal/{bankId}/soal/{soalId}/revision
```

Request minimum:

```text
change_kind CONTENT / KEY_WEIGHT / STRUCTURAL
change_note
revision payload
active_answer_policy nullable PRESERVE / REANSWER
```

Service menentukan apakah `active_answer_policy` diperlukan.

Revision lama immutable.

## 30.2 VOID

```text
POST /manager/api/bank-soal/{bankId}/soal/{soalId}/void
```

Tidak menghapus response historis.

## 30.3 Revision History

```text
GET /manager/api/bank-soal/{bankId}/soal/{soalId}/revisions
```

Live Edit diaudit dan tidak melakukan push wajib ke seluruh client. Client mengetahui perubahan pada sync/checkpoint berikutnya.

---

# 31. INSTRUMEN PSIKOLOGIS API

## 31.1 Instrument CRUD

```text
GET    /manager/api/psych-instruments
POST   /manager/api/psych-instruments
GET    /manager/api/psych-instruments/{id}
PUT    /manager/api/psych-instruments/{id}
DELETE /manager/api/psych-instruments/{id}
```

## 31.2 Dimensions

```text
GET    /manager/api/psych-instruments/{id}/dimensions
POST   /manager/api/psych-instruments/{id}/dimensions
PUT    /manager/api/psych-instruments/{id}/dimensions/{dimensionId}
DELETE /manager/api/psych-instruments/{id}/dimensions/{dimensionId}
```

## 31.3 Items / Manual Editor

```text
GET    /manager/api/psych-instruments/{id}/items
POST   /manager/api/psych-instruments/{id}/items
GET    /manager/api/psych-instruments/{id}/items/{itemId}
PUT    /manager/api/psych-instruments/{id}/items/{itemId}
DELETE /manager/api/psych-instruments/{id}/items/{itemId}
```

## 31.4 Scoring Matrix / Norm

```text
GET /manager/api/psych-instruments/{id}/scoring-matrix
PUT /manager/api/psych-instruments/{id}/scoring-matrix

GET /manager/api/psych-instruments/{id}/norms
PUT /manager/api/psych-instruments/{id}/norms
```

## 31.5 READY

```text
POST /manager/api/psych-instruments/{id}/ready
POST /manager/api/psych-instruments/{id}/draft
```

## 31.6 Revision

Jika koreksi setelah dipakai memang diizinkan engine/lifecycle:

```text
POST /manager/api/psych-instruments/{id}/items/{itemId}/revision
GET  /manager/api/psych-instruments/{id}/items/{itemId}/revisions
```

Psikologis memakai revision model, tetapi perubahan saat pelaksanaan lebih ketat daripada akademik dan harus ditolak jika tidak aman secara scoring/instrument validity.

---

# 32. JADWAL API

## 32.1 MAIN Jadwal

```text
GET    /manager/api/jadwal
POST   /manager/api/jadwal
GET    /manager/api/jadwal/{jadwalId}
PUT    /manager/api/jadwal/{jadwalId}
DELETE /manager/api/jadwal/{jadwalId}
```

MAIN create minimum:

```text
kegiatan_id
bank_soal_id OR psych_instrument_id
mulai_at
batas_mulai_at
durasi_seconds
access_state
tampilkan_nilai_saat_selesai
```

Structural edit hanya selama lifecycle mengizinkan.

## 32.2 BUKA / TAHAN

```text
PATCH /manager/api/jadwal/{jadwalId}/access
```

Request:

```json
{"access_state":"BUKA"}
```

TAHAN:

- menahan START/RESUME;
- tidak menghentikan peserta yang sudah masuk;
- tidak pause timer.

## 32.3 Extend Batas Mulai

```text
POST /manager/api/jadwal/{jadwalId}/extend-start-window
```

Hanya menambah/memperpanjang, tidak memundurkan aturan menjadi lebih sempit ketika sudah berjalan.

## 32.4 Tampilkan Nilai Saat Selesai

```text
PATCH /manager/api/jadwal/{jadwalId}/result-visibility
```

Setelah Attempt pertama START:

```text
423 RESULT_VISIBILITY_LOCKED
```

Untuk Psikologis hasil participant tetap tidak ditampilkan.

---

# 33. SUSULAN API

Susulan adalah child langsung dari MAIN Jadwal dan boleh dibuat N kali.

## 33.1 Create Susulan

```text
POST /manager/api/jadwal/{mainJadwalId}/susulan
```

Idempotency-Key: wajib.

Request minimum:

```text
mulai_at
batas_mulai_at
durasi_seconds
targets[]
```

Per target:

```text
peserta_kegiatan_id
target_mode FIRST_ATTEMPT / REPLACEMENT
supersede_attempt_id nullable
```

Kegiatan induk boleh sudah `SELESAI`; tidak perlu reopen Kegiatan.

Susulan tetap:

```text
parent_jadwal_id = MAIN
```

bukan chain child-of-child.

## 33.2 List Susulan

```text
GET /manager/api/jadwal/{mainJadwalId}/susulan
```

## 33.3 Update Susulan

```text
PUT /manager/api/jadwal/{susulanId}
```

Hanya sebelum lifecycle target mengunci perubahan struktural.

## 33.4 Cancel Target Sebelum START

```text
POST /manager/api/jadwal/{susulanId}/targets/{targetId}/cancel
```

Tidak boleh dipakai untuk menghapus Attempt yang sudah pernah START.

---

# 34. PREPARATION API

Preparation adalah pekerjaan berat sebelum peak.

## 34.1 Prepare Jadwal

```text
POST /manager/api/jadwal/{jadwalId}/prepare
```

Idempotency-Key: wajib.

Request opsional:

```text
scope ALL / SELECTED
peserta_kegiatan_ids[]
force_rebuild=false
```

Implementasi harus chunked/resumable bila target besar.

## 34.2 Status

```text
GET /manager/api/jadwal/{jadwalId}/preparation
```

Response:

```text
total_target
ready
stale
failed
progress
last_error
can_start
```

## 34.3 Selective Rebuild

```text
POST /manager/api/jadwal/{jadwalId}/prepare/rebuild
```

Hanya target STALE/terdampak; tidak membangun ulang semua jika tidak perlu.

## 34.4 Prepared Assignment Detail

Untuk troubleshooting manager:

```text
GET /manager/api/jadwal/{jadwalId}/prepared-assignments
GET /manager/api/prepared-assignments/{assignmentId}
```

Tidak memperlihatkan answer key kepada participant.

---

# 35. TOKEN API

Token adalah singleton global.

```text
GET   /manager/api/token
PATCH /manager/api/token/state
POST  /manager/api/token/rotate
POST  /manager/api/token/generate
PATCH /manager/api/token/auto-rotate
```

Actor: Admin/Operator.

`state`:

```text
ON / OFF
```

Rotate/generate tidak memengaruhi peserta yang sudah berada di workspace.

Token value tidak pernah dikirim ke public endpoint.

---

# 36. MONITORING API

## 36.1 Summary

```text
GET /manager/api/monitoring/summary
```

Filter minimum:

```text
kegiatan_id
jadwal_id
ruang_id
rombel_id
```

Summary:

```text
total
belum
sedang
selesai
tidak_terdeteksi
```

## 36.2 List

```text
GET /manager/api/monitoring/attempts
```

Server-side paginated.

Row minimum:

```text
nomor_peserta
nama
rombel
ruang
ujian
status
used_seconds
remaining_seconds
last_sync_at
connection_state
attempt_id
```

## 36.3 Detail

```text
GET /manager/api/monitoring/attempts/{attemptId}
```

Detail boleh mencakup response/sync state secukupnya untuk troubleshooting, tetapi tidak perlu memuat seluruh Bank jika tidak diminta.

## 36.4 Delta

Opsional untuk mengurangi load:

```text
GET /manager/api/monitoring/delta?since={cursor}
```

Jika implementasi delta tidak memberi manfaat nyata, server-side paginated refresh biasa tetap sah.

---

# 37. RESET AKSES API — CRITICAL

Individual:

```text
POST /manager/api/monitoring/attempts/{attemptId}/reset-access
```

Bulk:

```text
POST /manager/api/monitoring/reset-access
```

Request bulk:

```text
attempt_ids[]
reason
```

Idempotency-Key: wajib.

Transaction:

```text
lock ACTIVE Attempt
→ create pause event bila belum pause
→ set pause_started_at
→ increment client_generation
→ invalidate client lama
→ audit
```

Tidak menghapus:

```text
Attempt
jawaban
Prepared Assignment
active attempt lock
```

---

# 38. TAMBAH WAKTU API — CRITICAL

Individual:

```text
POST /manager/api/monitoring/attempts/{attemptId}/add-time
```

Bulk:

```text
POST /manager/api/monitoring/add-time
```

Request:

```text
seconds_added > 0
reason
```

Hanya menambah waktu.

Server:

```text
deadline_at += seconds_added
insert attempt_time_adjustment
audit
```

Idempotency-Key wajib agar retry tidak menambah waktu dua kali.

---

# 39. PAKSA SELESAI API — CRITICAL

Individual:

```text
POST /manager/api/monitoring/attempts/{attemptId}/force-finish
```

Bulk:

```text
POST /manager/api/monitoring/force-finish
```

Request:

```text
attempt_ids[]
reason
confirm_pending_sync_risk=true|false
```

Jika server mendeteksi indikasi pending local sync/last sync stale, UI harus memberi warning sebelum command dikirim.

Server authority tetap dapat FINISH Attempt dengan reason:

```text
FORCE_FINISH
```

Command idempotent.

---

# 40. LIVE SCORING MANAGER API

## 40.1 Config

```text
GET /manager/api/live-scoring
PUT /manager/api/live-scoring
```

Config minimum:

```text
jadwal_id
enabled
```

Satu Live Scoring display = satu Jadwal pada satu waktu.

## 40.2 Start / Stop

```text
POST /manager/api/live-scoring/start
POST /manager/api/live-scoring/stop
```

## 40.3 Public URL

```text
POST /manager/api/live-scoring/regenerate-url
GET  /manager/api/live-scoring/public-url
```

Regenerate membuat token random baru dan token lama tidak valid.

## 40.4 Preview Snapshot

```text
GET /manager/api/live-scoring/preview
```

Menggunakan formula kelompok Pilihan Ganda/Klik:

```text
PG + PG Kompleks + Matching + PG Bertingkat
```

---

# 41. SCORING / KOREKSI API

## 41.1 Result Attempt Detail

```text
GET /manager/api/results/attempts/{attemptId}
```

## 41.2 Correction by Question

```text
GET /manager/api/scoring/questions
GET /manager/api/scoring/questions/{questionId}/responses
```

Filter:

```text
jadwal_id
bank_id
question_type
scoring_state
```

## 41.3 Manual Score / Override

```text
POST /manager/api/scoring/responses/{responseId}/manual-score
```

Request:

```text
score
reason
```

Audit ke `score_adjustment_log`.

Ditolak setelah result Jadwal final:

```text
423 RESULT_ALREADY_FINAL
```

## 41.4 Rescore

```text
POST /manager/api/scoring/jadwal/{jadwalId}/rescore
```

Digunakan setelah key/weight/VOID sebelum Finalisasi Hasil.

Idempotency-Key wajib.

## 41.5 VOID Impact / Rebuild

VOID dibuat melalui Live Edit; scoring endpoint melakukan recompute sesuai denominator aktif.

---

# 42. FINALISASI HASIL API

```text
POST /manager/api/results/jadwal/{jadwalId}/finalize
```

Actor: Admin/Operator  
Idempotency-Key: wajib

Finalisasi mengunci hasil unit Jadwal/Susulan tersebut.

Sebelum execute, server validasi scoring state yang diperlukan sesuai jenis ujian.

Repeated request pada Jadwal yang sudah FINAL mengembalikan current final state.

Tidak ada endpoint `reopen`.

Status:

```text
results_finalized_at
results_finalized_by
```

Replacement Susulan di kemudian hari menghasilkan Attempt/result baru; snapshot lama tetap immutable dan official pointer dapat berubah ke replacement terbaru sesuai rule.

---

# 43. HASIL UJIAN API

## 43.1 Hasil Ujian

```text
GET /manager/api/results
GET /manager/api/results/{resultSnapshotId}
```

Filter:

```text
kegiatan
jadwal
mapel
rombel
ruang
status scoring
status final
q
```

## 43.2 Export Hasil

```text
GET /manager/hasil/ujian/export.xlsx
GET /manager/hasil/ujian/export.pdf
```

Export mengikuti filter yang dikirim secara eksplisit/query aman.

---

# 44. REKAP NILAI API

```text
GET /manager/api/rekap-nilai
GET /manager/hasil/rekap/export.xlsx
GET /manager/hasil/rekap/export.pdf
```

Rekap memakai hasil **official** dari `official_result_pointer`, bukan semua Attempt historis.

---

# 45. ANALISIS SOAL API

```text
GET /manager/api/analisis-soal
GET /manager/api/analisis-soal/{questionId}
POST /manager/api/analisis-soal/rebuild
GET /manager/hasil/analisis/export.xlsx
GET /manager/hasil/analisis/export.pdf
```

Aggregate analisis boleh cached/stale dan direbuild. Tidak boleh menghambat Attempt Engine.

---

# 46. HASIL PSIKOLOGIS API

```text
GET /manager/api/psych-results
GET /manager/api/psych-results/{resultSnapshotId}
GET /manager/hasil/psikologis/export.xlsx
GET /manager/hasil/psikologis/export.pdf
GET /manager/hasil/psikologis/{resultSnapshotId}.pdf
```

Result dapat memuat dimensi, normalized score, category, interpretation, chart, dan response detail sesuai instrumen.

Tidak ada participant result endpoint untuk psikologis.

---

# 47. SETTINGS API

## 47.1 Read

```text
GET /manager/api/settings
```

Admin/Operator boleh membaca setting non-secret yang memang diperlukan UI.

## 47.2 Update Settings

```text
PUT /manager/api/settings
```

Actor: **Admin only**.

Operator menggunakan modul operasional khusus seperti Token/Live Scoring/Jadwal dan tidak mengubah konfigurasi sistem umum melalui endpoint ini.

Secret seperti `credential_encryption_key` tidak pernah dikirim sebagai plaintext ke browser dan tidak diedit melalui form setting biasa.

## 47.3 Branding Upload

```text
POST /manager/api/settings/logo
```

Actor: **Admin only**.

File validation mengikuti Media/File Upload security.

---

# 48. USER MANAGER API

Admin only.

```text
GET    /manager/api/users
POST   /manager/api/users
GET    /manager/api/users/{id}
PUT    /manager/api/users/{id}
PATCH  /manager/api/users/{id}/status
POST   /manager/api/users/{id}/reset-password
```

Operator tidak dapat membuat/mengubah Admin.

Tidak ada route untuk mengembalikan password manager plaintext.

---

# 49. LOG API

```text
GET /manager/api/logs
GET /manager/api/logs/{id}
```

Admin/Operator sesuai scope log yang diizinkan.

Admin:

```text
PATCH /manager/api/logs/retention
POST  /manager/api/logs/purge
```

Auto purge mengikuti `log_retention_days`.

Purge diaudit.

---

# 50. BACKUP DATABASE API

## 50.1 Create

```text
POST /manager/api/backup/database
```

Actor: Admin/Operator  
Idempotency-Key: wajib

Response:

```text
backup_id/file_token
filename
created_at
size_bytes
schema_version
```

Encryption Key ikut karena berada di database.

## 50.2 Download

```text
GET /manager/api/backup/database/{backupId}/download
```

File dikirim melalui endpoint authorization dan tidak dibiarkan sebagai public file permanen.

---

# 51. RESTORE DATABASE API

Admin only.

Upload/prepare:

```text
POST /manager/api/restore/database/prepare
```

Execute:

```text
POST /manager/api/restore/database/{restoreJobId}/execute
```

Idempotency-Key: wajib.

Flow:

```text
upload
→ validate format/schema dasar
→ preview metadata/warning
→ optional safety DB backup jika feasible
→ explicit confirmation UI
→ execute restore
→ audit
```

Restore tidak memakai public upload path.

---

# 52. BACKUP / RESTORE MEDIA API

Backup:

```text
POST /manager/api/backup/media
GET  /manager/api/backup/media/{backupId}/download
```

Actor Backup: Admin/Operator.

Restore:

```text
POST /manager/api/restore/media/prepare
POST /manager/api/restore/media/{restoreJobId}/execute
```

Actor Restore: Admin only.

Database dan Media tetap dua backup independen; tidak ada pairing workflow wajib.

---

# 53. PENGOSONGAN DATA API

Admin only.

## 53.1 Preview Dependency

```text
POST /manager/api/system/clear-data/preview
```

Request memilih kelompok:

```text
execution_answers
results_scoring
schedules_activities
banks_instruments
participant_membership
master_peserta
rombel
mapel
```

Response menampilkan count/dependency impact.

## 53.2 Execute

```text
POST /manager/api/system/clear-data/execute
```

Idempotency-Key: wajib.

Wajib explicit destructive confirmation dari UI, FK-safe, transaction/chunk strategy sesuai volume, dan audit.

Yang dipertahankan:

```text
manager users
core config
credential encryption key
system minimum yang diperlukan aplikasi tetap hidup
```

---

# 54. MEDIA ROUTES

## 54.1 Local Media

Media lokal sebisa mungkin disajikan sebagai static file dari directory non-executable dengan opaque/internal filename.

Contoh URL manifest:

```text
/media/{opaquePath}
```

Tidak perlu melewati CI4 untuk setiap image/audio jika server static delivery aman dan jauh lebih ringan.

## 54.2 External Media

Video/asset external menggunakan provider metadata dari `media_assets`.

Client hanya menerima URL/embed data yang memang dibutuhkan renderer.

## 54.3 Manager Upload

Upload media soal/instrumen dilakukan melalui import/editor endpoint, bukan public media route.

---

# 55. DATA LOCK DAN LIFECYCLE ERROR

Aturan sederhana yang harus diterapkan konsisten:

> **Tidak ada perubahan data peserta yang mempengaruhi pelaksanaan jika Kegiatan sudah BERJALAN.**

Termasuk:

```text
identitas peserta
rombel
username/password
membership
ruang
nomor peserta
```

API mengembalikan:

```text
423 DATA_LOCKED
```

Prepared Assignment yang sudah dipakai tidak diedit in-place. Soal berubah melalui revision model.

Final result yang sudah FINAL tidak diedit/reopen.

---

# 56. TRANSACTION BOUNDARY WAJIB

## 56.1 Atomic Short Transaction

Wajib pendek dan atomic:

```text
START
RESUME after Reset Akses
Answer Sync batch kecil
Reset Akses
Tambah Waktu
Paksa Selesai
FINALIZE Attempt
Result Finalization metadata
Credential bulk state write per chunk
```

## 56.2 Heavy Work di Luar Peak Transaction

```text
Import parse
Preparation
PDF generation
Excel export
Analysis rebuild
Media processing
Backup
```

Heavy work harus chunk/resumable bila volume besar dan tidak memegang DB transaction panjang.

---

# 57. RATE LIMIT & THROTTLE

## 57.1 Auth

Login participant/manager memakai throttle username + IP secara wajar tanpa hard IP binding.

## 57.2 Exam Runtime

Jangan menerapkan rate limit yang menghambat Answer Sync normal.

Proteksi runtime berfokus pada:

```text
ownership
client generation
mutation id
revision
batch size
request size
```

## 57.3 Public Live Scoring

Public snapshot boleh diberi minimum refresh guard/cache agar tidak dapat dipukul request sangat cepat, tetapi tetap mengikuti full-cycle client behavior.

---

# 58. ADAPTIVE LOAD PROTECTION PADA ROUTE

Priority route yang **tidak boleh dikorbankan**:

```text
POST /api/auth/login
POST /api/ujian/{jadwalId}/start
POST /api/attempt/{attemptId}/resume
GET  /api/attempt/{attemptId}/bootstrap
POST /api/attempt/{attemptId}/sync
POST /api/attempt/{attemptId}/finalize
POST /manager/api/monitoring/.../reset-access
```

Non-kritis yang boleh diperlambat/cache/diturunkan:

```text
manager dashboard charts
monitoring refresh frequency
analysis rebuild/display
client telemetry
public live snapshot regeneration
non-critical aggregates
```

HTTP `503` dengan code stabil boleh digunakan bila non-kritis sengaja didegradasi sementara.

---

# 59. SUGGESTED CONTROLLER / SERVICE BOUNDARY

Nama boleh dipoles, tetapi pemisahan tanggung jawab disarankan seperti berikut.

## Participant

```text
Controllers:
ParticipantAuthController
ParticipantExamController
AttemptController
PublicLiveScoringController

Services:
ParticipantAuthService
ExamDiscoveryService
AttemptStartService
AttemptResumeService
AttemptBootstrapService
AnswerSyncService
AttemptFinalizeService
```

## Manager

```text
Controllers:
ManagerAuthController
DashboardController
RombelController
PesertaController
MapelController
KegiatanController
RuangController
BankSoalController
PsychInstrumentController
JadwalController
PreparationController
TokenController
MonitoringController
LiveScoringController
ScoringController
ResultController
BackupController
SettingsController
ManagerUserController
AuditLogController

Services:
CredentialService
CredentialCryptoService
ImportService
KegiatanService
ParticipantMembershipService
BankSoalService
PsychInstrumentService
PreparationService
JadwalService
MonitoringService
ScoringService
ResultService
BackupService
RestoreService
ClearDataService
```

Controller tidak boleh menjadi tempat query/business rule utama.

---

# 60. ROUTE GROUPING CI4 — REKOMENDASI

Bentuk konseptual `Routes.php`:

```php
$routes->get('/', 'Participant\\Auth::index');

$routes->group('api', ['filter' => 'participant-api'], static function ($routes) {
    // auth, ujian, attempt
});

$routes->group('manager', static function ($routes) {
    // public manager login

    $routes->group('', ['filter' => 'manager-auth'], static function ($routes) {
        // manager UI
    });

    $routes->group('api', ['filter' => 'manager-api'], static function ($routes) {
        // manager API
    });
});

$routes->get('live/(:segment)', 'PublicLiveScoring::index/$1');
$routes->get('api/public/live/(:segment)/snapshot', 'PublicLiveScoring::snapshot/$1');
```

Actual filter composition harus tetap memisahkan:

```text
Auth
Role
CSRF
Ownership/business validation
```

Jangan membuat satu filter raksasa yang mencampur semua business logic.

---

# 61. ACCEPTANCE TEST ROUTES/API

Sebelum Route/API dianggap FINAL PASS, minimal seluruh skenario berikut harus lolos.

## 61.1 Participant Auth

- login valid;
- username case normalization;
- password salah;
- account inactive;
- throttle;
- logout tidak pause Attempt;
- session participant tidak memberi akses manager.

## 61.2 START

- sebelum Mulai ditolak;
- lewat Batas Mulai untuk START baru ditolak;
- TAHAN ditolak;
- Token ON salah ditolak;
- Token OFF tidak diminta;
- Prepared Assignment belum READY ditolak;
- active Attempt lain ditolak;
- double click START menghasilkan satu Attempt;
- START tidak melakukan heavy preparation.

## 61.3 RESUME

- normal same-client re-entry;
- second client ditolak;
- Reset Akses lalu client baru berhasil;
- client lama setelah reset ditolak;
- Token ON wajib saat re-entry;
- TAHAN menahan resume;
- pause hanya berasal dari Reset Akses.

## 61.4 Sync

- revision baru diterima;
- duplicate mutation safe ACK;
- old revision tidak overwrite;
- wrong item/Attempt ditolak;
- client generation lama ditolak;
- batch retry tidak menggandakan response;
- server tidak percaya score dari client.

## 61.5 Offline / Timeout

- offline tidak pause timer;
- client timer 0 mengunci input;
- queue lama tetap dapat dikirim setelah network kembali;
- finalize TIMEOUT idempotent;
- jawaban baru setelah UI timeout tidak dibuat.

## 61.6 Monitoring

- paging/filter;
- Reset Akses individual/bulk;
- Add Time individual/bulk;
- Force Finish individual/bulk;
- idempotent retry;
- monitoring refresh tidak mengganggu answer sync.

## 61.7 Susulan

- MAIN dapat punya banyak Susulan;
- Susulan setelah Kegiatan SELESAI dapat dibuat;
- never-start menjadi first Attempt;
- replacement membuat assignment + Attempt baru;
- Attempt lama tetap histori/SUPERSEDED;
- official result pointer dapat berpindah tanpa overwrite snapshot lama.

## 61.8 Live Edit

- content revision;
- key/weight revision;
- structural preserve/reanswer sesuai rule;
- VOID;
- active client menerima revision change lewat sync/checkpoint;
- tidak ada push storm.

## 61.9 Result

- manual score sebelum FINAL;
- rescore sebelum FINAL;
- Finalisasi idempotent;
- setelah FINAL perubahan normal ditolak;
- tidak ada reopen endpoint;
- participant hanya melihat result pada Halaman Selesai jika setting ON;
- psikologis tidak menampilkan result participant.

## 61.10 Live Scoring

- token random URL;
- tepat lima kolom;
- formula Klik benar;
- competition rank;
- snapshot tidak refresh di tengah scroll;
- regenerate URL mematikan token lama;
- STOP menghilangkan public data.

## 61.11 Import

- upload valid/invalid;
- parse;
- staging;
- validation;
- fix/exclude/include;
- commit idempotent;
- macro/executable tidak dijalankan;
- path traversal ditolak.

## 61.12 Backup/Restore

- Operator dapat Backup DB/Media;
- Operator ditolak Restore;
- Admin dapat Restore;
- DB backup membawa encryption key;
- backup download butuh authorization;
- clear data Admin-only;
- dependency preview benar.

## 61.13 Security

- CSRF missing ditolak;
- SQL injection payload tidak mengubah query behavior;
- XSS ordinary field escaped;
- rich content sanitized;
- IDOR participant ditolak;
- secret tidak muncul di API/log.

---

# 62. CONSOLIDATED CRITICAL RUNTIME MAP

```text
LOGIN
POST /api/auth/login
        ↓
GET /api/ujian
        ↓
GET /api/ujian/{jadwalId}/konfirmasi
        ↓
POST /api/ujian/{jadwalId}/start
        ↓
GET /api/attempt/{attemptId}/bootstrap
        ↓
[LOCAL EXAM WORKSPACE]
        ↓
POST /api/attempt/{attemptId}/sync   ← async, batch kecil
        ↓
POST /api/attempt/{attemptId}/finalize
        ↓
GET /attempt/{attemptId}/selesai
```

Re-entry:

```text
LOGIN / SESSION RECOVERY
        ↓
POST /api/attempt/{attemptId}/resume
        ↓
GET /api/attempt/{attemptId}/bootstrap
        ↓
WORKSPACE
```

Operator emergency:

```text
MONITORING
├── POST reset-access
├── POST add-time
└── POST force-finish
```

Ini adalah jalur yang harus mendapat prioritas performa tertinggi.

---

# 63. KEPUTUSAN FINAL ROUTES/API

Baseline ini mengunci:

```text
✓ Participant UI di root realm
✓ Manager UI di /manager
✓ Participant API di /api
✓ Manager API di /manager/api
✓ Public Live Scoring terpisah
✓ JSON response envelope stabil
✓ stable application error code
✓ session + CSRF mutation
✓ server-side ownership
✓ START atomic + idempotent
✓ RESUME / Reset Akses contract
✓ Bootstrap/package ringan setelah preparation
✓ local-first answer + idempotent Sync
✓ FINALIZE idempotent
✓ satu peserta maksimum satu ACTIVE Attempt
✓ satu Attempt satu active client
✓ duplicate-tab ditangani lokal
✓ Jadwal BUKA/TAHAN
✓ Susulan N kali, child MAIN
✓ Susulan tetap dapat dibuat setelah Kegiatan SELESAI
✓ Preparation explicit sebelum peak
✓ Monitoring individual/bulk control
✓ Live Scoring full-cycle snapshot
✓ Live Edit via immutable revision
✓ Result final tidak mempunyai reopen endpoint
✓ generic import staging contract
✓ Backup DB/Media terpisah
✓ Restore/Clear Data Admin-only
✓ static local media sebisa mungkin tidak melalui CI4
✓ adaptive load mengutamakan Exam runtime
```

Setelah dokumen ini FINAL, route baru tidak boleh ditambahkan hanya karena kenyamanan coding. Route baru harus salah satu dari:

1. implementasi langsung requirement yang sudah ada;
2. kebutuhan teknis internal yang tidak mengubah business contract;
3. revisi eksplisit Dokumen Acuan bila benar-benar ada requirement baru.

