# CBT-HERO — AUTH, SECURITY & SESSION FINAL

**Versi:** 1.0  
**Tanggal:** 25 September 2026  
**Status:** **FINAL BASELINE / DOKUMEN TURUNAN NORMATIF**  
**Induk:** `CBT-HERO_DOKUMEN_ACUAN_UTAMA.md`  
**Database:** `CBT-HERO_DATABASE_ERD_FINAL.md`

---

## 0. TUJUAN DAN BATAS

Dokumen ini menerjemahkan keputusan FIX CBT-HERO menjadi aturan implementasi untuk:

- authentication Admin/Operator dan Peserta;
- session;
- credential peserta;
- authorization/ownership;
- one active attempt/client;
- CSRF;
- SQL Injection/XSS/file-upload protection;
- security event dan audit;
- Reset Akses;
- integritas request Attempt/Answer;
- batas security browser biasa dan Exam Browser.

Dokumen ini **tidak membuat requirement bisnis baru**. Jika terjadi perbedaan, `CBT-HERO_DOKUMEN_ACUAN_UTAMA.md` tetap menjadi sumber kebenaran tertinggi.

Prinsip utama:

> **Security aplikasi harus kuat, tetapi CBT-HERO tidak mengorbankan kemudahan, performa, dan kestabilan untuk security theater pada browser biasa.**

---

# 1. SECURITY POSTURE CBT-HERO

Prioritas security CBT-HERO:

```text
1. Jawaban tidak hilang / tidak tertukar
2. Account dan data tidak mudah ditembus
3. Peserta tidak dapat mengakses Attempt milik peserta lain
4. Attempt tidak dapat aktif pada banyak client secara liar
5. Input/upload/rich content tidak menjadi jalur injection
6. Audit tindakan penting tersedia
7. Anti-cheat browser biasa secukupnya
8. Device lockdown ketat → Exam Browser Phase 2
```

CBT-HERO **tidak** mengejar kompleksitas security enterprise yang tidak sebanding dengan risiko sistem ujian sekolah.

---

# 2. DUA AUTH REALM

CBT-HERO mempunyai dua auth realm yang berbeda secara fungsi:

```text
PARTICIPANT REALM
Root: /
Actor: Peserta

MANAGER REALM
Root: /manager
Actor: Admin / Operator
```

Kedua realm boleh menggunakan storage `ci_sessions` yang sama, tetapi state authentication **harus dipisahkan pada application layer**.

Contoh namespace session:

```text
participant_auth.*
manager_auth.*
```

Tidak boleh menggunakan satu flag generik seperti:

```text
is_login = true
```

untuk kedua realm.

---

# 3. ACCOUNT MODEL

## 3.1 Manager

Sumber account:

```text
manager_users
```

Field inti:

```text
id
username
nama
password_hash
role
status
failed_login_count
locked_until
last_login_at
```

Role hanya:

```text
ADMIN
OPERATOR
```

Tidak ada Guru/Pengawas/Wali/BK pada manager auth CBT-HERO.

## 3.2 Peserta

Sumber account:

```text
peserta
```

Identitas dipisahkan menjadi:

```text
peserta.id
→ authority database/FK

peserta.username
→ identitas login

peserta_kegiatan.nomor_peserta
→ identitas administrasi/display per Kegiatan
```

`Nomor Peserta` **bukan** username dan **bukan** FK transaksi.

---

# 4. CREDENTIAL PESERTA

## 4.1 Username

Aturan:

- globally unique;
- disimpan/display uppercase;
- input login dinormalisasi uppercase;
- boleh manual atau generated;
- generator menggunakan karakter yang mudah dibaca;
- generator menghindari karakter ambigu `O I L 0 1`;
- perubahan massal memakai dua warning;
- tidak boleh diubah ketika peserta terikat Kegiatan yang sudah BERJALAN.

## 4.2 Password Peserta

Peserta mempunyai dua representasi:

```text
password_hash
→ authentication

password_encrypted
→ cetak ulang credential
```

Authentication wajib memakai:

```php
password_hash()
password_verify()
```

Generated password:

- uppercase + digit;
- menghindari `O I L 0 1`;
- sederhana untuk diketik peserta;
- reset massal memakai dua warning.

## 4.3 Encryption Key

Credential Encryption Key disimpan pada:

```text
sys_settings
setting_key = credential_encryption_key
value_type  = SECRET
is_secret   = 1
```

Konsekuensi:

- ikut Backup Database;
- kembali otomatis saat Restore Database;
- tidak ditampilkan pada halaman Setting biasa;
- tidak masuk response API/log/debug;
- tidak perlu backup `.env` khusus demi credential key.

Implementasi enkripsi harus memakai **authenticated encryption** melalui service khusus, bukan cipher buatan sendiri atau ECB.

Service minimum:

```text
CredentialCryptoService
├── encryptPrintablePassword()
└── decryptPrintablePassword()
```

Key dibuat sekali pada instalasi/bootstrap pertama jika belum ada dan tidak diregenerate saat deploy/restart.

---

# 5. LOGIN PARTICIPANT

Flow:

```text
POST LOGIN
↓
normalisasi username
↓
validasi format + panjang
↓
cek account peserta
↓
cek status account
↓
cek temporary lock
↓
password_verify()
↓
regenerate session ID
↓
set participant_auth
↓
update last_login_at
↓
Daftar Ujian
```

Session participant minimum:

```text
participant_auth.logged_in
participant_auth.peserta_id
participant_auth.username
participant_auth.login_at
```

Jangan menyimpan password, encrypted password, key, seluruh row Peserta, atau daftar permission besar di session.

Pesan gagal login pada UI cukup generik:

```text
Username atau password tidak sesuai.
```

Detail alasan tetap boleh dicatat internal pada `auth_login_attempts`.

---

# 6. LOGIN MANAGER

Flow:

```text
/manager
↓
username + password
↓
cek manager_users
↓
ACTIVE?
↓
lock/throttle?
↓
password_verify()
↓
regenerate session ID
↓
set manager_auth
↓
Dashboard
```

Session minimum:

```text
manager_auth.logged_in
manager_auth.user_id
manager_auth.username
manager_auth.nama
manager_auth.role
manager_auth.login_at
```

Role di session boleh digunakan untuk rendering UI, tetapi **server tetap memverifikasi authority pada endpoint**.

---

# 7. LOGIN THROTTLING

Tujuan throttling adalah menghentikan brute-force sederhana tanpa membuat pelaksanaan ujian menjadi sulit.

Baseline:

```text
PARTICIPANT
10 kegagalan berturut-turut
→ lock 5 menit

MANAGER
5 kegagalan berturut-turut
→ lock 10 menit
```

Nilai ini boleh menjadi System Setting sehingga tuning tidak memerlukan perubahan schema/source.

Gunakan kombinasi:

```text
account-based counter
+
auth_login_attempts
+
soft IP rate monitoring
```

Tidak ada hard IP binding sebagai default.

Login sukses mereset consecutive failure counter.

---

# 8. SESSION

## 8.1 Storage

Gunakan CI4 Database Session Handler:

```text
ci_sessions
```

Tujuan:

- shared-hosting friendly;
- session tidak bergantung file lokal PHP;
- lebih konsisten jika deployment berkembang menjadi multi-instance.

## 8.2 Cookie

Baseline:

```text
HttpOnly = true
SameSite = Lax
Secure   = true ketika HTTPS
```

Production CBT-HERO harus menggunakan HTTPS jika deployment memungkinkan.

Session cookie tidak boleh dibaca JavaScript.

## 8.3 Regeneration

Session ID diregenerate pada:

- login berhasil;
- perubahan privilege manager yang relevan;
- flow sensitif bila diperlukan framework.

Logout menghancurkan auth state realm terkait.

## 8.4 IP/User-Agent

IP dan User-Agent boleh dicatat untuk:

- audit;
- troubleshooting;
- indikasi perubahan client.

Tetapi:

```text
IP berubah ≠ auto logout
User-Agent berubah ≠ auto cheating verdict
```

---

# 9. FILTER DAN AUTHORIZATION BOUNDARY

Minimum filter/service boundary:

```text
ParticipantAuthFilter
ManagerAuthFilter
ManagerRoleFilter
CsrfFilter
Maintenance/Availability Filter bila digunakan
```

Security tidak berhenti di menu/UI.

Aturan:

> **Controller/Service tetap authoritative.**

Contoh: meskipun tombol Restore Database tidak tampil untuk Operator, endpoint Restore tetap harus menolak Operator secara server-side.

---

# 10. MANAGER PERMISSION MATRIX

## Admin

Boleh seluruh fungsi CBT-HERO, termasuk:

- User Manager;
- Pengaturan Sistem;
- Backup Database/Media;
- Restore Database/Media;
- Pengosongan Data;
- Log/retention;
- seluruh Master Data/Ujian/Pelaksanaan/Hasil.

## Operator

Boleh workflow operasional:

- Master Data;
- Kegiatan/Peserta Ujian/Ruang;
- Bank Soal/Instrumen;
- Jadwal/Susulan;
- Token;
- Monitoring;
- Reset Akses;
- Tambah Waktu;
- Paksa Selesai;
- scoring/koreksi;
- laporan;
- Live Scoring;
- Backup Database;
- Backup Media;
- melihat log operasional yang diizinkan.

Operator tidak boleh:

```text
Restore Database
Restore Media
Pengosongan Data
kelola akun Admin
mengubah secret sistem
```

Detail route permission akan ditetapkan pada dokumen Routes/API.

---

# 11. CSRF

Semua mutation berbasis cookie/session harus dilindungi CSRF, termasuk manager dan participant.

Contoh:

```text
POST
PUT
PATCH
DELETE
```

Client Fetch mengirim token melalui header.

Untuk Exam runtime yang mempunyai sync paralel/frekuensi lebih tinggi:

- gunakan token session yang stabil;
- jangan meregenerate CSRF token pada setiap answer sync jika menyebabkan race antar request;
- tidak membuat endpoint answer sync menjadi exempt hanya demi kemudahan.

GET tidak boleh digunakan untuk mutation.

---

# 12. SQL INJECTION — ATURAN KERAS

CBT-HERO tidak boleh membuat SQL dari concatenation input user.

Dilarang:

```php
$sql = 'SELECT ... WHERE username="'.$username.'"';
```

Gunakan:

- Query Builder CI4;
- named/positional binding;
- parameterized query.

Nama kolom/order/filter dinamis harus berasal dari whitelist aplikasi, bukan diteruskan mentah dari request.

Aturan ini berlaku untuk:

- login;
- DataTables search/order;
- filter laporan;
- import;
- monitoring;
- live score;
- seluruh endpoint API.

---

# 13. XSS / HTML INJECTION

## Data biasa

Semua data biasa di-render escaped:

```text
Nama
Rombel
Username
Keterangan
Nama Kegiatan
Nama Mapel
Nomor Peserta
etc.
```

## Rich question content

Rich content berbeda karena memang membutuhkan HTML.

Flow wajib:

```text
Word/Editor/Import
↓
Parser
↓
Sanitizer whitelist
↓
Normalized content
↓
Database
↓
DOMPurify client defense-in-depth
↓
Render
```

Tidak ada raw user HTML langsung ke output.

---

# 14. FILE UPLOAD / MEDIA

Upload yang diperbolehkan ditentukan eksplisit per fitur.

Aturan minimum:

- extension whitelist;
- MIME/content validation jika relevan;
- size limit;
- internal/random file name;
- macro tidak dieksekusi;
- archive/Word ZIP extraction harus aman dari path traversal;
- executable/script upload ditolak;
- source code aplikasi bukan target upload;
- directory upload/media tidak boleh digunakan untuk mengeksekusi PHP/script.

Jenis seperti berikut tidak diterima sebagai media biasa:

```text
.php
.phtml
.phar
.cgi
.pl
.sh
.htaccess
```

---

# 15. PARTICIPANT OWNERSHIP / IDOR

Setiap participant API harus menentukan Peserta dari session server, **bukan dari `peserta_id` yang dikirim client**.

Untuk request Attempt, server wajib mengecek:

```text
Attempt ada?
↓
Attempt milik participant_auth.peserta_id?
↓
Kegiatan/Jadwal valid?
↓
client generation valid?
↓
state mengizinkan operasi?
```

Client tidak dapat mengakses Attempt lain hanya dengan mengganti:

```text
attempt_id
prepared_assignment_id
response_id
peserta_id
```

IDOR failure menghasilkan 403/404 sesuai kontrak API, tanpa membocorkan data peserta lain.

---

# 16. ONE PARTICIPANT = ONE ACTIVE ATTEMPT

Rule final:

```text
SATU PESERTA
→ maksimum SATU Attempt ACTIVE
```

Database boundary:

```text
attempt_active_lock
```

START wajib memperoleh lock tersebut dalam transaksi.

Jika peserta sedang ACTIVE pada Ujian A:

```text
Ujian A → LANJUTKAN
Ujian B → tidak boleh START
Ujian C → tidak boleh START
```

FINISH/SUPERSEDE melepaskan lock sesuai lifecycle Attempt.

---

# 17. ONE ATTEMPT = ONE ACTIVE CLIENT

Attempt menyimpan:

```text
client_uuid
client_generation
```

Client identity lokal disimpan di IndexedDB.

Request exam runtime membawa identitas/generation yang sesuai contract API.

Client kedua tidak boleh mengambil alih Attempt aktif hanya dengan login account yang sama.

Jika client lama hilang/rusak:

```text
Operator → Reset Akses
```

Reset Akses:

- invalidasi generation lama;
- pause timer effective pada server;
- jawaban tidak dihapus;
- Attempt tidak diganti;
- Prepared Assignment tidak diganti;
- peserta login/re-entry;
- Token jika ON;
- RESUME dengan generation baru.

---

# 18. DUPLICATE TAB

Attempt yang sama tidak boleh aktif di dua tab pada browser/client yang sama.

Gunakan local guard ringan:

```text
Web Locks
atau BroadcastChannel
+ fallback coordination
```

Tab kedua:

```text
UJIAN SUDAH TERBUKA DI TAB LAIN
```

Tidak perlu heartbeat server tambahan hanya untuk mendeteksi duplicate tab.

Duplicate-tab guard adalah perlindungan race-condition UX, bukan klaim anti-cheat mutlak.

---

# 19. TOKEN SECURITY

Token adalah global operational switch.

```text
ON  → diperlukan START / RE-ENTRY
OFF → tidak diperlukan
```

Token bukan session credential dan bukan pengganti username/password.

Rotasi token:

- tidak mengeluarkan peserta yang sudah berada di workspace;
- token baru dipakai START/RE-ENTRY berikutnya;
- token tidak disimpan permanen di client lebih lama dari kebutuhan flow.

---

# 20. DATA PESERTA LOCK

Rule final yang harus diterapkan sederhana:

> **TIDAK ADA PERUBAHAN DATA PESERTA JIKA UJIAN SUDAH BERJALAN.**

Untuk peserta yang terikat Kegiatan BERJALAN, blok perubahan yang mempengaruhi pelaksanaan, termasuk:

- identitas peserta;
- NISN;
- rombel;
- username/password;
- membership Kegiatan;
- Nomor Peserta;
- Ruang;
- assignment peserta.

Live Edit Soal adalah workflow khusus dan **bukan pengecualian perubahan data peserta**.

---

# 21. START AUTHORIZATION CONTRACT

START hanya boleh sukses jika seluruh kondisi berikut lulus:

```text
participant authenticated
peserta ACTIVE
membership Kegiatan valid
Kegiatan/Jadwal eligible
Batas Mulai belum terlewati
Jadwal BUKA
Token valid jika ON
Exam Browser valid jika required
Prepared Assignment READY
peserta tidak mempunyai Attempt ACTIVE lain
client identity valid
```

START tidak boleh melakukan fallback preparation berat.

Jika Prepared Assignment belum READY:

```text
START DITOLAK
→ status kesiapan dikembalikan
→ Operator memperbaiki Preparation
```

---

# 22. ANSWER SYNC SECURITY & INTEGRITY

Answer flow:

```text
Peserta memilih/mengetik jawaban
↓
IndexedDB commit
↓
UI update
↓
sync_queue
↓
async request
↓
server ownership/state validation
↓
revision check
↓
DB commit
↓
ACK
```

Server tidak menerima score/key/rubric dari client.

Server hanya menerima answer payload yang diizinkan untuk assigned item tersebut.

Aturan mutation:

- `mutation_id`/revision idempotent;
- old/duplicate revision di-ignore + ACK;
- revision lebih baru diterima jika state masih mengizinkan;
- participant tidak dapat mengganti `prepared_assignment_item_id` ke item yang bukan milik Attempt;
- scoring dihitung server-side.

Client timestamps/elapsed adalah telemetry/integrity aid, bukan authority tunggal.

---

# 23. TIMER / OFFLINE SECURITY BOUNDARY

Timer authority tetap server.

```text
Attempt ACTIVE
→ waktu terus berjalan

Browser ditutup
HP mati
Network putus
App background
→ TIDAK pause
```

Pause hanya melalui Reset Akses Operator.

Saat timer client mencapai 0:

- input dikunci;
- mutation baru tidak dibuat;
- queued answer yang sudah tersimpan tetap dipertahankan;
- ketika koneksi kembali, queue disinkronkan sesuai timeout/finalize contract;
- server menyelesaikan Attempt dengan reason `TIMEOUT`.

Browser biasa tidak dianggap trusted device; karena itu security server tetap berfokus pada ownership, revision, assignment, state, dan deadline authority.

---

# 24. EXAM BROWSER BOUNDARY

Phase 1:

```text
Exam Browser = OFF
browser biasa
```

Tidak ada pseudo-Exam Browser menggunakan JavaScript berat.

Tidak ada:

- auto logout hanya karena blur;
- auto finish karena visibilitychange;
- polling anti-cheat berat;
- klaim browser biasa adalah locked environment.

Phase 2:

```text
Android Exam Browser
```

Jika Kegiatan mengaktifkan Exam Browser, START/RESUME harus memverifikasi client Exam Browser.

Kamera/proctoring bukan core Phase 1.

---

# 25. AUDIT LOG

Audit wajib untuk tindakan yang mengubah state penting, misalnya:

```text
manager login/logout penting
credential bulk generation/reset
Kegiatan status change
Jadwal change saat diizinkan
Token ON/OFF/rotate
Reset Akses
Tambah Waktu
Paksa Selesai
Live Edit Soal
VOID
manual score override
Finalisasi hasil
Backup/Restore
Pengosongan Data
pengaturan security
```

Audit tidak dipakai untuk merekam event client setiap detik.

Log retention mengikuti setting sistem dan auto purge.

---

# 26. ERROR HANDLING

Production:

- stack trace tidak ditampilkan ke peserta/operator;
- SQL error detail tidak dikirim ke browser;
- secret tidak masuk error response;
- error memiliki correlation/request ID bila implementasi memerlukan troubleshooting;
- detail teknis masuk server log sesuai environment.

API memakai format error konsisten yang akan ditentukan pada dokumen Routes/API.

---

# 27. SECURITY HEADER BASELINE

Baseline production yang disarankan:

```text
X-Content-Type-Options: nosniff
Referrer-Policy: same-origin
frame-ancestors / X-Frame-Options sesuai kebutuhan
Content-Security-Policy disusun kompatibel dengan renderer soal/media
```

CSP tidak boleh diterapkan secara asal hingga mematahkan KaTeX, media, rich question, atau sumber video yang memang diizinkan.

---

# 28. BACKUP / RESTORE SECURITY

Permission final:

```text
ADMIN
✓ Backup Database
✓ Restore Database
✓ Backup Media
✓ Restore Media
✓ Pengosongan Data

OPERATOR
✓ Backup Database
✓ Backup Media
✗ Restore
✗ Pengosongan Data
```

Encryption Key ikut Database Backup.

Backup hasil download harus dikirim melalui endpoint authorization, bukan diletakkan sebagai file publik permanen.

Safety backup database aktif sebelum Restore Database bila feasible dan ringan.

---

# 29. SECURITY ACCEPTANCE CHECK

Sebelum release, minimal PASS:

### Authentication

- participant login valid;
- participant credential salah;
- manager Admin/Operator login valid;
- account inactive ditolak;
- lock/throttle bekerja;
- session ID berubah setelah login;
- logout memutus auth state.

### Authorization

- Participant tidak dapat membuka Attempt peserta lain;
- Operator tidak dapat Restore/Pengosongan Data;
- endpoint Admin tetap menolak Operator walau URL dipanggil manual;
- mutation tanpa auth ditolak.

### SQL/XSS

- login payload khusus tidak mengubah query behavior;
- DataTables/search tidak raw SQL concatenate;
- plain output escaped;
- rich HTML sanitizer menolak script/event handler berbahaya.

### CSRF

- manager mutation tanpa token ditolak;
- participant answer mutation tanpa token ditolak;
- valid Fetch dengan token tetap lancar di load test.

### Attempt/Client

- satu peserta tidak dapat membuat dua Attempt ACTIVE;
- second device/client ditolak;
- Reset Akses mengizinkan client baru dan invalidasi client lama;
- duplicate tab diblok lokal;
- client lama tidak dapat sync setelah generation invalid.

### Timer/Offline

- network putus tidak pause;
- browser close tidak pause;
- Reset Akses pause;
- timeout lock input;
- queued answer tidak hilang;
- delayed sync tidak menyebabkan jawaban peserta lain tertukar.

### File/Import

- executable upload ditolak;
- MIME/extension invalid ditolak;
- ZIP/path traversal tidak menulis di luar workspace import;
- macro tidak dieksekusi.

### Secret

- credential encryption key tidak tampil UI/log/API;
- Backup Database membawa key;
- Restore Database mengembalikan kemampuan cetak ulang password encrypted.

---

# 30. KEPUTUSAN FINAL AUTH/SECURITY/SESSION

Baseline final:

```text
✓ Dua auth realm: Participant dan Manager
✓ Role manager hanya Admin/Operator
✓ Participant login memakai Username + Password
✓ Username uppercase/global unique
✓ Password auth = password_hash/password_verify
✓ Printable password = authenticated encryption
✓ Encryption Key disimpan DB sebagai secret setting
✓ Database session handler
✓ Session cookie HttpOnly/SameSite/Secure HTTPS
✓ CSRF untuk cookie-authenticated mutation
✓ Query Builder/binding; no raw input SQL concat
✓ Plain output escaped; rich HTML sanitized
✓ Ownership/IDOR check server-side
✓ One Participant = One Active Attempt
✓ One Attempt = One Active Client
✓ Duplicate-tab = local lock ringan
✓ Reset Akses = invalidate client + pause timer, bukan reset Attempt
✓ Tidak ada perubahan data peserta saat Kegiatan BERJALAN
✓ Browser biasa tidak dibuat pseudo-Exam Browser
✓ Exam Browser = Phase 2
✓ Audit hanya untuk event penting
✓ Security harus tetap ringan dan operasional
```

Dokumen berikutnya yang langsung memakai baseline ini adalah:

```text
CBT-HERO_ROUTES_API_FINAL.md
```
