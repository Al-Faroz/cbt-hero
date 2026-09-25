# CBT-HERO — DOKUMEN ACUAN UTAMA

**Versi:** 1.4  
**Tanggal baseline:** 25 September 2026  
**Status:** **BASELINE FINAL / SINGLE SOURCE OF TRUTH**  
**Platform:** CodeIgniter 4  
**Ruang lingkup:** CBT akademik + tes psikologis berbasis inventori/psychometric questionnaire  

---

## 0. STATUS DOKUMEN DAN ATURAN PERUBAHAN

Dokumen ini adalah **acuan tunggal yang benar** untuk pembangunan CBT-HERO. Dokumen, source referensi, mockup, catatan percakapan, atau prototipe lain **tidak boleh mengalahkan keputusan di dokumen ini** kecuali dilakukan revisi eksplisit pada dokumen acuan utama.

Urutan otoritas keputusan CBT-HERO:

1. kebutuhan dan pengalaman operasional CBT nyata;
2. keputusan CBT-HERO yang sudah dinyatakan FIX;
3. source ZYA-CBT dan Garuda CBT sebagai referensi teknis/operasional;
4. rekomendasi implementasi teknis.

CBT-HERO **bukan clone** ZYA-CBT maupun Garuda CBT. ZYA-CBT dijadikan referensi untuk kesederhanaan, pola engine ringan, dan pengalaman operator. Garuda CBT dijadikan referensi untuk kelengkapan administrasi, monitoring, serta fitur CBT. Arsitektur runtime, cache, sync, preparation, keamanan, dan model datanya dibangun ulang untuk CBT-HERO.

Setelah implementasi dimulai, perubahan requirement hanya dilakukan jika ditemukan:

- kontradiksi nyata yang menghambat implementasi;
- bug desain yang dapat menyebabkan kehilangan data/jawaban;
- kebutuhan regulasi/operasional baru yang benar-benar tidak tercakup;
- perubahan eksplisit dari pemilik proyek.

Perbedaan preferensi coding, nama variabel, optimasi minor, dan tuning performa **bukan alasan mengubah requirement bisnis**.

### 0.1 Dokumen Turunan Normatif

Untuk menjaga implementasi tetap konsisten tanpa memecah sumber kebenaran, Dokumen Acuan Utama ini memiliki dokumen turunan normatif berikut:

- `CBT-HERO_UIUX_ACUAN.md` — aturan layout, komponen, state, responsive behavior, pola interaksi, dan konsistensi visual;
- `CBT-HERO_UIUX_Mockup.html` — visual companion/mockup untuk implementasi UI;
- `CBT-HERO_DATABASE_ERD_FINAL.md` — schema database, constraint, index, transaction boundary, dan ERD final;
- `CBT-HERO_AUTH_SECURITY_SESSION_FINAL.md` — authentication, session, credential, authorization, CSRF, security boundary, dan Attempt/client protection;
- `CBT-HERO_ROUTES_API_FINAL.md` — route UI/API, method, actor, ownership, request/response, idempotency, transaction boundary, error code, dan acceptance contract;
- `CBT-HERO_IMPLEMENTATION_SPEC_ROADMAP_FINAL.md` — urutan implementasi phase-by-phase, dependency, layer, komponen, checkpoint PASS, critical path, dan Definition of Done;
- `CBT-HERO_SCHEMA_v1.0.sql` — baseline DDL yang harus mengikuti dokumen Database & ERD.

Dokumen turunan **tidak boleh membuat requirement bisnis baru**. Jika ada perbedaan, urutan otoritas adalah:

```text
Dokumen Acuan Utama
        ↓
Dokumen turunan normatif sesuai domain
        ↓
Mockup / contoh visual / source referensi
        ↓
Implementasi source code
```

Perubahan source yang menyimpang dari dokumen acuan harus dianggap bug/technical debt sampai dokumen acuan direvisi secara eksplisit.

---

# 1. IDENTITAS DAN FILOSOFI CBT-HERO

## 1.1 Identitas Produk

> **CBT-HERO adalah sistem CBT baru berbasis CodeIgniter 4 yang kebutuhan dan prioritasnya terutama berasal dari pengalaman operasional CBT nyata, dengan ZYA-CBT sebagai referensi kesederhanaan/engine ringan dan Garuda CBT sebagai referensi kelengkapan administrasi/fungsionalitas CBT.**

Ringkasnya:

> **Kemudahan ZYA, kelengkapan CBT Garuda, tanpa E-Learning, dengan arsitektur baru CBT-HERO.**

## 1.2 Sasaran Utama

- sekitar 1.500 peserta saat ini;
- desain untuk **>2.000 peserta**;
- desain untuk **>60 rombel**;
- mayoritas peserta memakai Android;
- tetap realistis untuk server kelas menengah/shared-hosting/VPS kecil sekitar 2 core / 4 GB selama konfigurasi dan beban infrastrukturnya wajar;
- ujian harus tetap dapat berjalan meskipun monitoring, live scoring, atau fitur non-kritis sedang dikurangi frekuensinya.

## 1.3 Prinsip Arsitektur

1. **CBT Only** — tidak ada E-Learning.
2. **Operator First** — workflow operator harus singkat, jelas, dan dapat dipakai saat tekanan pelaksanaan tinggi.
3. **Mobile First untuk Peserta** — Android adalah perangkat dominan.
4. **Desktop First untuk Admin/Operator** — tetap responsif di mobile.
5. **Performance Before Decoration**.
6. **Heavy at Preparation, Light at Execution**.
7. **Durable Answers First** — kehilangan jawaban lebih buruk daripada kehilangan dekorasi/telemetri.
8. **Server Authority, Client Protects Experience**.
9. **Graceful Degradation Under Load**.
10. **Simple Operation, Strong Internal Design**.
11. **Practical Security** — keamanan aplikasi kuat, tetapi browser biasa tidak dipaksa menjadi Exam Browser palsu dengan JavaScript berat.
12. **Tidak ada anti-cheat absolut pada browser biasa**; kontrol perangkat ketat adalah ranah Exam Browser.

## 1.4 Target Pola Beban

Pola beban yang diinginkan:

```text
PREPARATION
    ↓
MASS LOGIN / START
    ↓   (spike wajar)
WORKSPACE UJIAN
    ↓   (load turun signifikan)
ANSWER SYNC ASYNC
    ↓
SUBMIT / FINISH
    ↓   (kenaikan moderat)
```

CBT-HERO tidak mengikuti pola runtime chatty yang terus menerus menyentuh server untuk hal-hal yang sebenarnya dapat dikerjakan di client.

---

## 1.5 Batas Penggunaan ZYA-CBT dan Garuda CBT sebagai Acuan

Dari ZYA-CBT, CBT-HERO mengambil referensi **kesederhanaan operasional dan kecenderungan runtime yang lebih ringan**. Dari Garuda CBT, CBT-HERO mengambil referensi **kelengkapan administrasi, monitoring, reset/izin, dan kontrol pelaksanaan**.

CBT-HERO sengaja **tidak** meniru mentah-mentah pola runtime Garuda yang lebih chatty atau proteksi browser berbasis banyak event JavaScript, karena target utama CBT-HERO adalah menurunkan beban setelah START. CBT-HERO juga tidak menyalin struktur source/database ZYA. Kedua aplikasi adalah pembanding kebutuhan dan perilaku, bukan blueprint kode.

---

# 2. RUANG LINGKUP DAN ROLE

## 2.1 Role Sistem

Hanya ada tiga role:

- **Admin**
- **Operator**
- **Siswa/Peserta**

Tidak ada role Guru, Pengawas, Wali Kelas, BK, atau role E-Learning.

## 2.2 Admin

Admin mempunyai full access:

- konfigurasi sistem;
- account manager;
- master data;
- kegiatan/jadwal/bank soal;
- monitoring dan kontrol ujian;
- backup/restore;
- pengosongan data;
- audit/log;
- scoring/finalisasi/laporan;
- seluruh setting tingkat sistem.

## 2.3 Operator

Operator adalah pelaksana harian CBT:

- mengelola peserta, bank soal, jadwal, ruang, kegiatan;
- import/template soal;
- monitoring;
- reset akses/tambah waktu/paksa selesai;
- grading/koreksi;
- hasil/laporan;
- backup database/media.

Operator **tidak** melakukan restore dan pengosongan data sistem.

## 2.4 Peserta

Peserta hanya mendapat akses ke:

- login;
- daftar ujian yang menjadi haknya;
- konfirmasi ujian;
- START/RESUME;
- workspace ujian;
- submit/selesai;
- halaman selesai.

Peserta tidak mempunyai halaman hasil historis, edit data, atau akses administratif.

## 2.5 Pengawas

Pengawas adalah fungsi operasional manusia, bukan role/account CBT-HERO.

## 2.6 Struktur Menu Manager

Baseline menu manager:

```text
DASHBOARD

MASTER DATA
├── Periode
├── Rombel
├── Peserta
└── Mata Pelajaran

MASTER UJIAN
├── Kegiatan Ujian
├── Peserta Ujian
├── Ruang
├── Bank Soal
├── Instrumen Psikologis
└── Jadwal Ujian

PELAKSANAAN
├── Token
├── Monitoring Ujian
└── Live Scoring

HASIL & LAPORAN
├── Hasil Ujian
├── Rekap Nilai
├── Analisis Soal
└── Hasil Psikologis

SYSTEM
├── Backup & Restore
├── Pengosongan Data
├── Pengaturan
├── User Manager
└── Log
```

Dashboard bersifat operasional: ringkasan Kegiatan/Jadwal berjalan, kesiapan/preflight, peserta aktif, masalah sync, dan shortcut tindakan penting. Chart tidak boleh mendominasi dashboard.

---

# 3. DUA REALM AUTENTIKASI

CBT-HERO memakai dua realm terpisah:

```text
/          → Peserta
/manager   → Admin / Operator
```

Realm manager dan peserta tidak berbagi konteks otorisasi.

---

# 4. IDENTITAS PESERTA, USERNAME, PASSWORD, NOMOR PESERTA

## 4.1 Tiga Identitas yang Berbeda

### A. `peserta_id`

- primary key internal;
- tidak dipakai sebagai login;
- tidak perlu ditampilkan ke pengguna;
- menjadi FK utama transaksi.

### B. Username

- identitas login peserta;
- **global unique**;
- disimpan/display **UPPERCASE**;
- input login dinormalisasi uppercase;
- dapat dibuat manual atau generated;
- tidak boleh digunakan ulang otomatis.

### C. Nomor Peserta

- identitas administratif/display;
- spesifik per Kegiatan;
- bukan FK transaksi;
- format: prefix Operator + sequence sistem;
- dapat digenerate ulang sesuai kebutuhan operator;
- snapshot disimpan pada Attempt untuk histori.

## 4.2 Password

Untuk password generated:

- huruf kapital + angka;
- pendek dan mudah diketik;
- hindari karakter ambigu.

Password untuk autentikasi menggunakan:

- `password_hash()`;
- `password_verify()`.

Untuk kebutuhan cetak ulang kartu, CBT-HERO juga dapat menyimpan credential reversible dalam bentuk terenkripsi.

## 4.3 Karakter Generated yang Dihindari

Untuk Username/Password generated, hindari:

```text
O I L 0 1
```

Charset yang disarankan:

```text
ABCDEFGHJKMNPQRSTUVWXYZ
2346789
```

## 4.4 Bulk Credential

- generate username yang belum ada;
- regenerate username massal;
- generate/reset password massal;
- regenerate/reset massal memakai **2× warning** sebelum eksekusi;
- bulk credential tidak boleh dijalankan untuk peserta yang sedang mempunyai Attempt ACTIVE.

## 4.5 Lock Data Peserta Saat Ujian Berjalan

Prinsip sederhana:

> **Tidak ada perubahan data peserta jika ujian sudah berjalan.**

Pada konteks Kegiatan/Jadwal yang sedang berjalan, perubahan yang memengaruhi identitas dan pelaksanaan dikunci, termasuk:

- nama/identitas utama;
- username;
- password;
- nomor peserta;
- rombel;
- ruang;
- membership peserta Kegiatan;
- assignment/prepared assignment peserta.

Live Edit Soal tetap menjadi jalur khusus dan tidak termasuk perubahan data peserta.

---

# 5. ENCRYPTION KEY CREDENTIAL

CBT-HERO memilih kesederhanaan operasional.

- encryption key credential disimpan di database;
- dapat berada di `sys_settings`/record secret;
- tidak ditampilkan di UI biasa;
- tidak ditulis ke log;
- otomatis ikut **Backup Database**;
- otomatis kembali saat **Restore Database**;
- key dibuat sekali pada instalasi awal;
- rotasi key bukan kebutuhan V1.

Tujuan enkripsi reversible ini adalah **operasional cetak ulang password**, bukan perlindungan setara sistem perbankan terhadap pihak yang sudah menguasai seluruh database.

---

# 6. FONT KARTU PESERTA

Untuk nilai credential berikut:

- NOMOR PESERTA
- USERNAME
- PASSWORD

font resmi CBT-HERO adalah:

> **JetBrains Mono SemiBold/Bold**

Alasan: monospaced dan lebih mudah membedakan karakter seperti `0/O`, `1/I/L`.

Aturan:

- credential dicetak lebih besar daripada data biasa;
- data lain memakai font standar CBT-HERO;
- JetBrains Mono dibundel lokal dalam asset produksi;
- Username dan Password generated menggunakan uppercase.

---

# 7. MASTER DATA

```text
MASTER DATA
├── Periode
├── Rombel
├── Peserta
│   ├── Data Peserta
│   ├── Tambah/Edit
│   ├── Import Excel
│   └── Account Login
└── Mata Pelajaran
```

## 7.1 Periode

Periode hanya memuat:

- Tahun Pelajaran;
- Semester Ganjil/Genap.

Tidak ada status aktif tunggal.

## 7.2 Rombel

Pisahkan:

- tingkat;
- kode rombel.

Display contoh:

```text
7-A
8-R
9-P
```

## 7.3 Peserta

Data minimum:

- id internal;
- NISN;
- nama;
- jenis kelamin;
- rombel;
- status;
- keterangan.

`keterangan` adalah free text untuk kebutuhan filter/sort.

## 7.4 Import Peserta

Kolom wajib:

- NISN;
- Nama;
- Jenis Kelamin;
- Rombel.

Kolom opsional:

- Keterangan;
- Username;
- Password.

Jika Username/Password kosong, peserta tetap boleh diimport dan credential dapat digenerate melalui Account Login.

Template Excel ideal:

- Sheet 1: data import;
- Sheet 2: petunjuk, rombel yang tersedia, contoh, dan aturan validasi.

## 7.5 Mata Pelajaran

Field utama:

- kode mapel;
- nama mapel;
- singkatan;
- status;
- urutan.

Tidak ada Guru, KKM, atau jadwal mengajar guru.

---

# 8. MASTER UJIAN

```text
MASTER UJIAN
├── Kegiatan Ujian
├── Peserta Ujian
├── Ruang
├── Bank Soal
├── Instrumen Psikologis
└── Jadwal Ujian
```

Tidak ada menu Jenis Ujian, Sesi, Gelombang, Paket, Blueprint, Clone, atau Copy.

Jenis Ujian cukup menjadi atribut Kegiatan.

---

# 9. KEGIATAN UJIAN

## 9.1 Field Minimal

- Nama Kegiatan;
- Jenis;
- Periode;
- Keterangan;
- Status.

Kegiatan **tidak** menyimpan durasi, token, ruang, tanggal ujian, atau mapel secara langsung.

## 9.2 Lifecycle

```text
DRAFT → BERJALAN → SELESAI
```

### DRAFT

- persiapan bebas;
- peserta/bank/jadwal masih dapat disusun;
- preparation dapat dibangun.

### BERJALAN

- struktur inti dikunci;
- data peserta terkait tidak boleh diubah;
- live edit soal tetap dapat dilakukan dengan mekanisme khusus;
- tindakan operasional tetap tersedia.

### SELESAI

Tombol SELESAI dapat digunakan ketika Jadwal/Susulan yang sudah dibuat telah melewati waktu pelaksanaannya dan tidak ada Attempt ACTIVE.

Status SELESAI tidak menghapus data dan tidak memblokir kebutuhan historis/laporan.

## 9.3 Susulan sebagai Pengecualian Operasional

Susulan boleh dibuat berkali-kali sesuai kebutuhan Operator. Sistem **tidak memberikan artificial limit satu kali**.

Susulan adalah **pengecualian operasional resmi** terhadap lock struktur Kegiatan. Jika Kegiatan sudah berstatus `SELESAI`, Operator tetap boleh membuat Susulan baru tanpa harus melakukan workflow “buka kembali Kegiatan”. Status Kegiatan tidak perlu diputar ulang ke BERJALAN hanya untuk menjalankan Susulan.

Implementasi harus memungkinkan satu Jadwal utama mempunyai N Susulan dan monitoring tetap dapat menjalankan Jadwal Susulan tersebut meskipun Kegiatan induk sudah berstatus SELESAI.

---

# 10. PESERTA UJIAN DAN RUANG

## 10.1 Peserta Ujian

Peserta dipilih melalui:

- semua peserta;
- tingkat;
- rombel;
- individual.

Hasil seleksi menjadi membership eksplisit Kegiatan.

Unique minimum:

```text
(kegiatan_id, peserta_id)
```

Snapshot membership minimal:

- NISN;
- nama;
- jenis kelamin;
- rombel.

## 10.2 Ruang

Ruang berdiri sendiri dan reusable.

Field minimal:

- kode;
- nama;
- status.

Tidak perlu:

- kapasitas;
- device;
- slot;
- server;
- sesi.

Mapping ruang berada pada peserta Kegiatan dan dapat dilakukan massal melalui filter.

---

# 11. KARTU PESERTA

Default cetak:

- A4;
- 10 kartu/halaman;
- pola 2 × 5.

Kartu menampilkan:

- identitas madrasah;
- nama peserta;
- rombel;
- ruang;
- Nomor Peserta;
- Username;
- Password;
- URL CBT;
- QR URL jika diperlukan.

QR tidak menyimpan credential login.

Credential menggunakan JetBrains Mono SemiBold/Bold.

---

# 12. BANK SOAL DAN INSTRUMEN

## 12.1 Bank Soal Akademik

Bank dimiliki oleh Kegiatan.

Pola umum:

```text
Kegiatan + Mapel + Tingkat
```

Status:

```text
DRAFT / READY
```

Tidak ada reuse/clone/copy sebagai workflow utama. Membuat Bank baru dibuat murah melalui template/import.

Komposisi, randomisasi, scoring, dan bobot berada dalam Bank.

## 12.2 Instrumen Psikologis

Instrumen psikologis terpisah dari Bank Akademik pada sisi engine/scoring, tetapi memakai core pelaksanaan CBT yang sama.

Status:

```text
DRAFT / READY
```

---

# 13. TIPE SOAL AKADEMIK DAN DUA KELOMPOK UI

## 13.1 Enam Tipe Teknis Akademik

1. PG
2. PG Kompleks
3. Matching / Menjodohkan
4. Isian Singkat
5. Uraian
6. PG Bertingkat (numeric point per opsi)

Psikologis **bukan tipe akademik ke-7**. Stimulus, gambar, audio, video, formula, tabel, dan rich content adalah atribut/konten soal, bukan tipe soal baru. Engine akademik dan engine psikologis tidak dicampur dalam satu Bank/Jadwal yang sama.

## 13.2 Dua Kelompok Besar untuk UI/Result

### PILIHAN GANDA / KLIK

- PG
- PG Kompleks
- Matching
- PG Bertingkat

### ISIAN & URAIAN / KETIK

- Isian Singkat
- Uraian

Engine tetap memahami 6 tipe teknis, sedangkan UI peserta dan ringkasan nilai memakai 2 kelompok besar agar sederhana.

## 13.3 Ragu-ragu

Flag/Ragu-ragu tersedia untuk **semua tipe akademik**.

---

# 14. RANDOMISASI

Objektif/klik:

- PG: shuffle soal/opsi jika diizinkan;
- PG Kompleks: shuffle soal/opsi jika diizinkan;
- PG Bertingkat: shuffle soal/opsi jika diizinkan;
- Matching: shuffle soal/pair/right side dengan stable mapping.

Isian Singkat dan Uraian tidak memakai randomisasi objektif seperti PG.

Urutan umum:

```text
kelompok objektif/klik
→ Isian
→ Uraian
```

Psikologis preserve order secara default kecuali instrumen secara eksplisit mengizinkan randomisasi.

---

# 15. TEMPLATE, IMPORT, STAGING, RENDERER

Prinsip:

> Guru/pembuat soal tidak dipaksa memahami struktur teknis CBT.

Flow resmi:

```text
Kesepakatan Bentuk Ujian
→ Operator buat template
→ Guru isi
→ Operator pilih/buat Bank
→ Upload
→ Parser
→ Import Staging
→ Validation
→ Preview
→ Fix / Exclude
→ Commit
→ Bank
→ PDF
→ Koreksi Guru
→ Edit / Reprint / Recheck
→ READY
```

Staging **wajib**. Tidak ada upload langsung ke Bank tanpa validation/preview.

## 15.1 Word

Mendukung:

- rich text;
- gambar;
- Arab/RTL;
- formula;
- tabel;
- media reference yang diizinkan.

## 15.2 Excel

Cocok untuk:

- structured bulk;
- PG Bertingkat;
- psikologis;
- data dengan scoring matrix.

## 15.3 Manual Editor dan Psikologis

Bank Akademik dan Instrumen Psikologis sama-sama mempunyai **manual editor** untuk membuat/memperbaiki item tanpa import ulang.

Instrumen psikologis mendukung:

- Excel import;
- Word import;
- manual editor.

Alasannya: item psikologis juga dapat membutuhkan gambar/rich content.

## 15.4 Shared Renderer

Preview import, Bank, Exam Client, dan PDF menggunakan renderer normalized-data yang konsisten agar tampilan tidak berbeda-beda antar modul.

---

# 16. MEDIA

Prinsip media:

- gambar: local/static setelah normalisasi;
- audio: local jika ukuran wajar;
- video: reference/streaming, misalnya Drive/provider lain;
- text/options: wajib tersedia lokal di Exam Client.

Sebelum workspace READY:

```text
START
→ package
→ prefetch critical media
→ READY
→ pengerjaan
```

Prioritas:

- teks/opsi: mandatory;
- gambar small/medium: prefetch;
- audio: prefetch jika reasonable;
- video besar: online/reference.

Threshold ukuran ditentukan saat tuning performa.

---

# 17. PREPARATION / PREPARED ASSIGNMENT

CBT-HERO memakai prinsip **precompute sebelum peak**.

Preparation menyelesaikan pekerjaan berat sebelum peserta START:

- resolve target peserta;
- resolve Bank/revision;
- randomisasi;
- order soal;
- order opsi;
- stable mapping matching;
- media manifest;
- fingerprint/revision;
- prepared assignment per peserta.

Prepared Assignment menyimpan ID/order/revision/mapping, **bukan full HTML/media**.

## 17.1 Aturan START

START tidak boleh melakukan randomisasi/generasi berat sebagai fallback.

Jika prepared assignment belum siap:

```text
START DITOLAK
→ status: BELUM SIAP
→ Operator mendapat indikator preflight
```

Tidak ada perubahan assignment peserta setelah ujian berjalan.

## 17.2 Preflight Sebelum Pelaksanaan

Preflight harus dapat menunjukkan kesiapan secara actionable, minimal:

- peserta mempunyai account login yang valid;
- peserta mempunyai Nomor Peserta;
- mapping Ruang lengkap bila diwajibkan;
- Bank/Instrumen READY;
- Jadwal valid;
- Prepared Assignment READY;
- media kritis/manifest valid;
- tidak ada konfigurasi yang membuat START mustahil.

Tombol/aksi membuat Kegiatan BERJALAN **tidak menjalankan pekerjaan berat**. Pekerjaan berat harus sudah selesai pada Preparation.

## 17.3 Incremental Preparation

Mendukung:

- peserta baru sebelum Kegiatan berjalan;
- invalidasi selektif;
- rebuild hanya target terdampak;
- resumable/chunked processing.

Tidak membutuhkan Redis/queue sebagai dependency wajib.

---

# 18. JADWAL UJIAN

Field utama Jadwal:

- Mulai;
- Batas Mulai;
- Durasi;
- status akses `BUKA / TAHAN`;
- `Tampilkan Nilai Saat Selesai` ON/OFF.

## 18.1 Batas Mulai

`Batas Mulai` adalah batas peserta melakukan **START baru**, bukan waktu selesai.

Peserta yang sudah mempunyai Attempt dapat tetap RESUME sesuai aturan waktu Attempt.

## 18.2 BUKA / TAHAN

- BUKA: START/RESUME diizinkan jika syarat lain terpenuhi;
- TAHAN: START/RESUME ditahan;
- peserta yang sudah berada di dalam workspace tetap berjalan;
- timer tidak pause karena TAHAN.

## 18.3 Perubahan Jadwal saat BERJALAN

Perubahan struktural dilarang.

Yang diperbolehkan sebagai tindakan operasional terkontrol:

- perpanjang Batas Mulai;
- Tambah Waktu.

Semua diaudit.

---

# 19. DURASI DAN TIMER

## 19.1 Authority

Timer authority berada pada server melalui data Attempt.

Client menampilkan countdown lokal agar tidak perlu polling berat.

## 19.2 Model Timer — FIX

Selama Attempt ACTIVE:

- network putus → waktu tetap berjalan;
- browser ditutup → waktu tetap berjalan;
- browser crash → waktu tetap berjalan;
- HP mati → waktu tetap berjalan;
- background → waktu tetap berjalan.

Pause hanya karena **Reset Akses oleh Operator**.

## 19.3 Timeout Client

Saat timer lokal mencapai 0:

- input dikunci;
- jawaban yang sudah tersimpan lokal sebelum batas waktu tetap dipertahankan;
- jika online: drain sync → FINALIZE;
- jika offline: state lokal `TIMEOUT_PENDING`, lalu sync ketika jaringan kembali dan server FINALIZE;
- jawaban setelah batas waktu tidak boleh dibuat oleh UI.

---

# 20. SUSULAN

Susulan adalah child dari Jadwal utama.

Tidak menduplikasi:

- Kegiatan;
- Bank;
- rule scoring;
- core preparation rules.

Operator menentukan:

- peserta target;
- tanggal;
- mulai;
- batas mulai;
- durasi.

## 20.1 Frekuensi

Satu Jadwal utama boleh mempunyai:

```text
Susulan #1
Susulan #2
...
Susulan #N
```

Tidak dibatasi satu kali.

## 20.2 Belum Pernah START

- menggunakan prepared assignment yang relevan;
- menjadi Attempt pertama peserta.

## 20.3 Replacement Attempt

Jika Attempt lama harus diganti:

- Attempt lama tetap menjadi histori;
- Attempt lama diberi status `SUPERSEDED` pada layer pelaksanaan/official selection;
- dibuat Attempt baru;
- dibuat prepared assignment baru dari Bank/rule yang sama;
- randomisasi dapat berbeda;
- tidak menggunakan Reset Attempt untuk menghapus histori lama.

Finalized snapshot lama tidak diedit; official result dapat menunjuk hasil replacement terbaru tanpa mengubah isi snapshot lama.

---

# 21. TOKEN

Token adalah **global operational switch**.

```text
TOKEN ON
→ wajib pada START / RE-ENTRY

TOKEN OFF
→ tidak diminta
```

Token **bukan** atribut Kegiatan, Jadwal, Mapel, atau Ruang.

Menu:

```text
PELAKSANAAN
└── Token
```

Fitur halaman Token:

- ON/OFF;
- token sekarang;
- token berikutnya;
- rotate manual;
- generate manual;
- optional auto-rotate interval.

Rotasi token tidak memengaruhi peserta yang sudah berada di dalam workspace.

---

# 22. EXAM BROWSER

Setting `Exam Browser ON/OFF` berada pada **Kegiatan**.

Phase 1:

- Exam Browser = OFF;
- browser biasa dipakai;
- tidak ada klaim anti-cheat absolut.

Phase 2:

- Android Exam Browser;
- jika Kegiatan `Exam Browser = ON`, START/RESUME hanya dari client Exam Browser tervalidasi.

CBT-HERO **tidak** meniru anti-cheat agresif browser biasa ala aplikasi yang bergantung pada banyak event/polling JS karena kompromi performa dan tetap mudah dilewati oleh kemampuan OS Android modern.

Exam Browser/kiosk client adalah jalur kontrol perangkat yang lebih ketat.

Kamera/proctoring bukan core V1 dan bukan kewajiban Phase 2 awal.

---

# 23. FLOW PESERTA

```text
LOGIN
→ DAFTAR UJIAN
→ PILIH UJIAN
→ KONFIRMASI UJIAN
→ TOKEN (jika ON)
→ START / RESUME
→ PACKAGE + PREFETCH
→ WORKSPACE
→ SELESAI
→ KONFIRMASI #1
→ KONFIRMASI #2
→ SYNC + FINALIZE
→ HALAMAN SELESAI
```

Konfirmasi Ujian menampilkan:

- identitas;
- informasi ujian;
- pesan/instruksi default sistem;
- checkbox persetujuan;
- token input jika Token ON.

Membuka halaman Konfirmasi tidak membuat Attempt dan tidak memulai timer.

---

# 24. DAFTAR UJIAN PESERTA

Peserta melihat seluruh ujian yang menjadi haknya dengan status seperti:

- Belum Dibuka;
- Bisa Dimulai;
- Lanjutkan;
- Selesai.

Prinsip:

> **Satu peserta maksimum satu Attempt ACTIVE pada satu waktu.**

Jika masih mempunyai Attempt ACTIVE, ujian lain tidak boleh START sampai Attempt tersebut selesai.

---

# 25. ATTEMPT

Attempt dibuat hanya saat peserta benar-benar menekan MULAI dan seluruh validasi lulus.

Status inti:

- `ACTIVE`
- `FINISHED`
- `SUPERSEDED`

Attempt menyimpan snapshot pelaksanaan penting, termasuk:

- peserta_id;
- kegiatan/jadwal;
- nomor peserta snapshot;
- nama snapshot;
- rombel snapshot;
- ruang snapshot;
- prepared assignment/revision;
- start time;
- duration authority;
- accumulated pause/reset information;
- finish time/status.

---

# 26. ONE ACTIVE CLIENT DAN DUPLICATE TAB

## 26.1 One Attempt = One Active Client

Attempt mempunyai client UUID/generation.

Perangkat/client kedua ditolak sampai Operator melakukan Reset Akses.

Jika cookie/session hilang tetapi IndexedDB client lama masih tersedia, aplikasi boleh membangun kembali session terhadap Attempt/client generation yang sama setelah validasi server. Jika IndexedDB/client identity juga hilang, perangkat dianggap client baru dan membutuhkan Reset Akses apabila lease/generation lama masih aktif.

## 26.2 Duplicate Tab

Pada browser/client yang sama, Attempt yang sama tidak boleh dibuka aktif di dua tab.

Gunakan tab-lock lokal ringan, misalnya:

- BroadcastChannel;
- Web Locks;
- fallback local coordination.

Tab kedua menampilkan pesan bahwa ujian sudah terbuka di tab lain.

Tidak perlu request server terus-menerus hanya untuk tab-lock.

---

# 27. RESET AKSES

Reset Akses:

- invalidasi client generation aktif;
- menghentikan penggunaan client lama;
- **pause waktu efektif pada server** pada waktu reset;
- jawaban yang sudah tersimpan tidak dihapus;
- prepared assignment tidak dihapus;
- Attempt tetap sama;
- peserta login kembali;
- pilih ujian;
- token jika ON;
- resume;
- timer melanjutkan sisa waktu.

Reset Akses bukan Reset Attempt.

---

# 28. EXAM CLIENT CACHE

Exam Client memakai IndexedDB melalui **Dexie.js**.

Store minimum:

```text
attempt_meta
question_cache
answer_store
sync_queue
media_manifest
runtime_state
```

Bootstrap/package dapat dikompresi dan dipecah menjadi chunk jika payload besar.

Bootstrap membawa:

- metadata Attempt;
- peserta;
- jadwal;
- duration authority;
- used/remaining;
- question manifest;
- assigned question content;
- answer snapshot;
- media manifest;
- revision;
- sync cursor.

Navigation dan display soal berlangsung lokal setelah package tersedia.

---

# 29. ANSWER FLOW DAN SYNC

Flow jawaban:

```text
USER ANSWER
→ IndexedDB transaction
→ UI update
→ sync_queue
→ async sync
→ server ACK
```

Prinsip:

- lokal dulu, server kemudian;
- mutation idempotent;
- setiap jawaban mempunyai revision/sequence;
- server menerima revision lebih tinggi;
- duplicate/old mutation di-ignore tetapi ACK aman;
- retry memakai backoff + jitter;
- essay/isian dapat memakai debounce;
- UI status: tersimpan / menyimpan / belum sinkron.

Server tetap authority untuk jawaban resmi yang sudah ACK.

## 29.1 Refresh

Jika page refresh dan IndexedDB masih ada:

- recover dari local cache;
- reconcile dengan server.

Jika pindah device/cache hilang:

- rebuild dari server ACK snapshot;
- jawaban yang belum pernah sync dari device lama tidak dapat dipulihkan jika storage device hilang.

---

# 30. SUBMIT / FINALIZE

Saat peserta menekan Selesai:

1. Konfirmasi #1;
2. Konfirmasi #2;
3. drain pending queue;
4. tunggu ACK sesuai batas wajar;
5. server FINALIZE;
6. Attempt → FINISHED;
7. clear cache exam yang sudah final jika aman.

Saat timeout tidak ada dua konfirmasi; input langsung dikunci dan proses finalisasi berjalan sesuai kondisi jaringan.

---

# 31. PELAKSANAAN DAN MONITORING

```text
PELAKSANAAN
├── Token
├── Monitoring Ujian
└── Live Scoring
```

## 31.1 Monitoring

Satu halaman utama:

- summary;
- filter;
- tabel;
- detail;
- tindakan individual/massal.

Summary:

- total;
- belum;
- sedang;
- selesai;
- tidak terdeteksi.

`Menunggu Token` adalah telemetry/UI state, bukan status Attempt permanen.

Tabel minimal:

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

## 31.2 Aksi Monitoring

Individual:

- Reset Akses;
- Tambah Waktu;
- Paksa Selesai;
- Detail.

Bulk:

- Reset Akses;
- Tambah Waktu;
- Paksa Selesai.

Paksa Selesai harus memberi warning jika masih ada pending local sync yang terdeteksi.

## 31.3 Load Monitoring

- server-side paging;
- default 25–50 row;
- refresh 5–15 detik atau adaptif;
- query ringan/delta bila memungkinkan;
- di bawah high load, frekuensi non-kritis dapat diturunkan.

---

# 32. LIVE EDIT SOAL

Setiap soal memakai:

- stable `question_id`;
- `revision_no`.

Prepared Assignment mereferensikan ID/revision/order/stable option mapping.

Tidak ada push wajib; perubahan dapat diketahui melalui checkpoint/sync response.

Jenis Live Edit:

### A. Content Fix

- typo/teks/gambar;
- jawaban peserta dipertahankan.

### B. Key/Weight Fix

- jawaban dipertahankan;
- scoring direcompute.

### C. Structural Edit

Untuk peserta ACTIVE yang sudah menjawab:

- Operator memilih preserve atau reanswer sesuai jenis perubahan;
- histori jawaban lama dipertahankan.

Peserta FINISHED tidak dibuka ulang; gunakan rescore/VOID sebelum finalisasi hasil.

### D. VOID

- soal dikeluarkan dari scoring;
- histori tetap ada.

Live edit saat BERJALAN wajib:

- warning;
- audit;
- revision trace.

---

# 33. SCORING AKADEMIK

## 33.1 Tiga Layer

```text
Answer
→ Raw Point
→ Type Weight Contribution
→ Final 0–100
```

Setiap soal mempunyai `max_point`.

Bank mempunyai bobot total akademik = 100.

## 33.2 PG

- benar = max point;
- salah = 0.

## 33.3 PG Kompleks

Partial default:

```text
(correct_selected - wrong_selected) / total_correct
```

clamp minimum 0, kemudian × max point.

Dapat menyediakan mode all-or-nothing bila Bank mengaturnya.

## 33.4 Matching

- partial atau all-or-nothing sesuai setting Bank;
- stable mapping wajib.

## 33.5 Isian Singkat

Dua matcher:

### TEXT

- multiple accepted answers;
- trim;
- case normalization/insensitive;
- collapse/whitespace normalization;
- tanpa fuzzy AI.

### NUMERIC

- expected numeric;
- optional absolute tolerance;
- koma Indonesia dapat dinormalisasi sebagai decimal separator;
- tolerance 0 = exact numeric.

Keduanya mendukung manual override.

## 33.6 Uraian

- manual scoring;
- decimal allowed;
- range 0..max;
- rubric;
- audit scorer/time.

## 33.7 PG Bertingkat

Setiap opsi mempunyai numeric point.

## 33.8 Override

`auto_score` dan `manual_score` dipisah.

Effective score memakai manual override jika tersedia.

Semua override diaudit.

## 33.9 VOID

VOID mengeluarkan soal dari denominator.

Jika seluruh soal suatu tipe void, bobot aktif dinormalisasi agar final score tetap valid.

Precision internal cukup tinggi; display default 2 desimal.

---

# 34. DUA KELOMPOK NILAI PESERTA

## 34.1 Nilai Pilihan Ganda

Mencakup:

- PG;
- PG Kompleks;
- Matching;
- PG Bertingkat.

Normalized 0–100:

```text
objective_click_earned_contribution
----------------------------------- × 100
objective_click_max_contribution
```

Soal belum dijawab sementara bernilai 0 untuk Live Scoring.

## 34.2 Nilai Isian & Uraian

Mencakup:

- Isian Singkat;
- Uraian.

Jika seluruh komponen sudah scored:

```text
typed_earned_contribution
------------------------- × 100
typed_max_contribution
```

Jika masih ada Uraian/manual scoring belum selesai:

```text
DALAM PROSES
```

## 34.3 Nilai Akhir

Nilai akhir **bukan rata-rata sederhana** dua nilai kelompok.

Nilai akhir berasal dari weighted contribution semua tipe sesuai bobot Bank.

---

# 35. TAMPILKAN NILAI SAAT SELESAI

Setting dimiliki oleh **Jadwal**:

```text
Tampilkan Nilai Saat Selesai = ON/OFF
```

Setting dikunci setelah Attempt pertama pada Jadwal melakukan START.

### OFF

Halaman selesai hanya menampilkan status selesai.

### ON — Akademik

Halaman selesai dapat menampilkan:

```text
NILAI PILIHAN GANDA
85.00

NILAI ISIAN & URAIAN
DALAM PROSES
```

atau nilai kedua kelompok jika sudah lengkap.

Nilai hanya tersedia pada **Halaman Selesai** sebagai UI akhir pelaksanaan. Tidak ada menu peserta untuk membuka ulang nilai, jawaban, kunci, atau pembahasan setelah kembali ke Daftar Ujian.

### Psikologis

Hasil tidak ditampilkan ke peserta.

---

# 36. FINALISASI HASIL

Scored tidak sama dengan Final.

Finalisasi berlaku pada unit hasil Jadwal/Susulan yang relevan.

Sebelum FINAL:

- koreksi;
- rescore;
- manual override;
- VOID;
- perbaikan key/weight;
- scoring Uraian.

Setelah FINAL:

- tidak ada edit normal;
- tidak ada reopen hasil;
- tidak ada normal rescore;
- snapshot hasil immutable.

Jika kemudian ada replacement melalui Susulan, hasil lama tetap immutable sebagai histori; official-result selection dapat menunjuk replacement terbaru tanpa mengubah snapshot lama.

---

# 37. HASIL & LAPORAN

```text
HASIL & LAPORAN
├── Hasil Ujian
├── Rekap Nilai
├── Analisis Soal
└── Hasil Psikologis
```

## 37.1 Hasil Ujian

Per peserta:

- identitas;
- attempt;
- status scoring;
- status final;
- breakdown tipe/kelompok;
- lazy item detail.

## 37.2 Rekap Nilai

Matrix:

```text
Peserta × Mapel
```

Gunakan official result saja.

Export utama:

- Excel;
- PDF bila diperlukan.

## 37.3 Analisis Soal

Type-aware.

Aggregate dapat:

- ditandai stale;
- rebuild setelah perubahan scoring/live edit.

## 37.4 Participant Result Portal

Tidak ada.

---

# 38. PUBLIC LIVE SCORING

Live Scoring adalah layar publik/fullscreen terpisah dari hasil resmi.

Satu display Live Scoring menampilkan **satu Jadwal pada satu waktu**.

Operator:

- pilih Jadwal;
- START/STOP public display;
- generate/regenerate public URL random bearer-like;
- copy URL.

Tidak membutuhkan YouTube API; dapat ditangkap OBS/browser capture.

## 38.1 Kolom Wajib

Tepat 5 kolom:

```text
No | No Peserta | Nama | Skor | Status
```

Status:

- MENGERJAKAN;
- SELESAI.

## 38.2 Skor

Skor memakai seluruh kelompok **Pilihan Ganda/Klik**:

- PG;
- PG Kompleks;
- Matching;
- PG Bertingkat.

Rumus sama dengan `Nilai Pilihan Ganda` pada Halaman Selesai.

Soal belum dijawab = 0 sementara.

## 38.3 Ranking

- competition rank;
- score sama → rank sama;
- secondary order stabil berdasarkan No Peserta;
- tidak ada tie-break waktu.

Scroll speed dan timing siklus ditentukan sistem, bukan setting bebas Operator. Public endpoint tidak perlu melakukan traffic tambahan jika layar publik tidak sedang dibuka.

## 38.4 Siklus Display

Aturan wajib:

```text
FETCH SNAPSHOT
→ HITUNG RANKING
→ TAMPIL DARI ATAS
→ AUTO SCROLL SAMPAI DATA TERAKHIR
→ BARU FETCH SNAPSHOT TERBARU
→ RANK ULANG
→ KEMBALI KE ATAS
```

Tidak ada refresh dataset di tengah satu siklus scroll.

Konsekuensi yang memang diinginkan:

- peserta sedikit → siklus pendek → update lebih sering;
- peserta banyak → siklus panjang → update lebih jarang.

Setelah semua peserta selesai, layar tetap looping sampai Operator OFF.

---

# 39. TES PSIKOLOGIS

Tes Psikologis **secara operasional adalah tes CBT yang sama** dengan Tes Akademik.

Core yang dipakai bersama:

- login;
- daftar ujian;
- konfirmasi;
- token;
- peserta;
- ruang;
- jadwal;
- preparation;
- attempt;
- cache;
- sync;
- monitoring;
- reset akses;
- duration;
- susulan;
- finish flow;
- security.

Yang berbeda:

- item engine;
- scoring engine;
- setting instrumen;
- result/report.

## 39.1 Scope V1 Psikologis

V1 fokus pada inventory/psychometric questionnaire seperti:

- personality/self-report;
- minat/RIASEC-like;
- attitude;
- motivation;
- workstyle.

Bukan engine khusus untuk:

- Kraepelin/Pauli;
- reaction time;
- drawing/projective;
- special visual tests.

Tes kemampuan kognitif benar/salah dapat menggunakan engine akademik.

## 39.2 Tipe Item

- Likert;
- Forced Choice;
- SELECT_ONE;
- MOST_LEAST.

## 39.3 Model Data Psikologis

Mendukung:

- dimensi;
- normal/reverse scoring;
- option → dimension scoring matrix;
- norms;
- category;
- interpretation;
- profile chart.

Tidak ada konsep benar/salah akademik sebagai default.

## 39.4 Result Peserta

Tidak ditampilkan ke peserta.

Tidak masuk Public Live Scoring.

## 39.5 Laporan Psikologis

Sesuai instrumen dapat memuat:

- identitas;
- instrument version;
- dimension scores;
- normalized score;
- category;
- interpretation;
- profile chart;
- response detail bila sesuai;
- Excel;
- PDF;
- individual PDF.

Sistem tidak mengarang diagnosis atau norma yang tidak diberikan oleh instrumen.

---

# 40. UI/UX

## 40.1 Dua Shell

### Manager Shell

Admin/Operator:

- desktop priority;
- responsive mobile;
- fixed sidebar desktop;
- offcanvas mobile;
- activity context bar;
- page header;
- summary/filter/table;
- right drawer untuk detail cepat;
- DataTables/server-side untuk dataset besar.

### Exam Shell

Peserta:

- mobile priority;
- desktop tetap baik;
- question workspace sederhana;
- sticky navigation;
- A-/A/A+;
- image zoom;
- formula responsive;
- Arabic RTL;
- table horizontal scroll bila perlu;
- native audio/video controls sesuai requirement.

## 40.2 Palette Soal

Desktop:

- palette kanan permanen.

Mobile:

- bottom sheet.

## 40.3 Tema

Tema CBT-HERO custom.

Tidak menggunakan heavy premade admin template sebagai fondasi utama.


## 40.4 Acuan Konsistensi UI/UX

Implementasi UI/UX wajib mengikuti `CBT-HERO_UIUX_ACUAN.md` dan visual companion `CBT-HERO_UIUX_Mockup.html`. Keduanya berfungsi menjaga halaman yang dikerjakan pada fase berbeda tetap terlihat sebagai satu aplikasi.

Aturan utamanya:

- pola halaman yang sama harus memakai komponen yang sama, bukan membuat variasi baru tanpa kebutuhan nyata;
- Manager memakai pola `Page Header → Summary opsional → Filter/Toolbar → Table/Content → Drawer/Modal`;
- Exam memakai pola `Header Ujian → Area Soal → Status Sync/Timer → Navigasi`;
- Public Live Scoring tidak memakai manager chrome;
- state `loading`, `empty`, `error`, `disabled`, `locked`, `success`, dan `warning` harus konsisten;
- destructive action mengikuti tingkat konfirmasi yang sudah ditetapkan requirement;
- credential kartu peserta (`Nomor Peserta`, `Username`, `Password`) memakai **JetBrains Mono SemiBold/Bold** dan tidak boleh diganti font proporsional;
- komponen mobile peserta mengutamakan touch target dan keterbacaan, bukan kepadatan informasi;
- tidak boleh mengunci browser zoom;
- CSS/JS baru harus memperluas design system CBT-HERO, bukan membuat mini-design-system per modul.

---

# 41. FRONTEND STACK

Foundation:

- HTML5;
- CSS3;
- Bootstrap 5.x;
- Bootstrap Icons;
- Vanilla JS ES6+;
- Fetch API;
- ES modules.

Manager:

- DataTables 2 vanilla/server-side;
- SweetAlert2;
- Chart.js;
- SortableJS.

Exam:

- Dexie.js;
- DOMPurify;
- KaTeX;
- Panzoom.

Public Live Scoring:

- custom HTML/CSS/Vanilla JS.

Larangan:

- jQuery;
- React;
- Vue;
- Angular;
- Axios sebagai dependency wajib.

Asset core disimpan lokal, tidak bergantung CDN produksi.

---

# 42. SECURITY BASELINE

CBT-HERO tidak mengejar kompleksitas security enterprise yang tidak sebanding dengan risiko, tetapi celah aplikasi umum harus ditutup dengan disiplin.

## 42.1 SQL Injection

- Query Builder;
- prepared/parameter binding;
- tidak ada concatenation raw SQL dengan input peserta/operator.

## 42.2 XSS / HTML Injection

Data biasa:

- escaped output.

Rich question content:

- sanitization whitelist;
- DOMPurify pada sisi client sebagai defense-in-depth.

## 42.3 CSRF

Aktif pada mutation manager/web yang relevan.

## 42.4 Authorization / IDOR

Setiap endpoint server mengecek ownership dan permission.

Peserta tidak boleh mengakses Attempt/jawaban peserta lain dengan mengganti ID URL/request.

## 42.5 Session

- HttpOnly;
- SameSite;
- Secure jika HTTPS;
- session regeneration;
- realm peserta dan manager terpisah;
- IP dan User-Agent boleh dicatat untuk audit, tetapi **tidak ada hard IP binding** sebagai default.

## 42.6 File Upload

- whitelist jenis file;
- validasi extension + MIME/content bila relevan;
- rename internal;
- script/executable dilarang;
- parser tidak mengeksekusi macro;
- protect ZIP/path traversal;
- source code tidak boleh menjadi target upload.

## 42.7 Security vs Anti-Cheat

Browser biasa:

- keamanan aplikasi tetap wajib;
- tidak diklaim sebagai lingkungan ujian terkunci;
- visibility/focus hanya signal ringan bila dipakai;
- tidak otomatis logout/pause/finish;
- tidak ada polling anti-cheat berat.

Exam Browser adalah mekanisme untuk kontrol device yang lebih ketat.

---

# 43. BACKUP, RESTORE, PENGOSONGAN DATA

## 43.1 Dua Backup Terpisah

```text
Backup Database
Backup Media
```

Tidak digabung menjadi satu file wajib dan **tidak ada mekanisme pairing backup yang rumit**. Admin/Operator bertanggung jawab memahami file yang sedang digunakan. Sistem cukup menampilkan metadata dasar seperti nama, tanggal, ukuran, dan versi/schema bila relevan.

## 43.2 Permission

### Admin

- Backup Database;
- Restore Database;
- Backup Media;
- Restore Media;
- Pengosongan Data.

### Operator

- Backup Database;
- Backup Media.

## 43.3 Encryption Key

Encryption key credential berada di database sehingga otomatis ikut Backup/Restore Database.

Tidak perlu backup `.env` khusus demi encryption key.

## 43.4 Safety Backup Restore

Sebelum Restore Database, sistem boleh membuat safety backup DB aktif jika tidak berat/sulit.

Tidak perlu memaksa safety copy media.

## 43.5 Pengosongan Data

Kelompok data yang dapat dikosongkan secara terkontrol:

- execution/answers;
- result/scoring;
- schedule/activity;
- bank/instrument;
- participant membership/nomor peserta;
- master peserta;
- rombel;
- mapel.

Yang dipertahankan:

- manager users;
- core config;
- encryption key;
- sistem penting yang diperlukan untuk aplikasi tetap hidup.

Pengosongan wajib:

- dependency check;
- warning;
- audit;
- FK-safe.

---

# 44. SETTINGS DAN LOG

Settings minimal:

- nama instansi;
- logo;
- alamat;
- URL CBT;
- tahun/default display;
- timezone;
- identitas kartu;
- footer/copyright;
- pesan/instruksi default peserta sebelum ujian;
- retensi log;
- credential encryption key secret record.

Log:

- dapat dilihat Admin/Operator;
- auto purge berdasarkan konfigurasi;
- default implementasi dapat menggunakan 180 hari dan dapat diubah.

---

# 45. LOAD PROTECTION DAN PERFORMANCE

## 45.1 Acceptance Target

Wajib diuji:

- 500 concurrent;
- 1.000 concurrent;
- 1.500 concurrent;
- 2.000 concurrent.

Stress:

- 2.500 concurrent.

Stretch:

- 3.000 jika environment memungkinkan;
- bukan janji bahwa semua hosting 2C/4GB mampu 3.000.

## 45.2 Acceptance Behavior

- mass login/start boleh spike;
- setelah masuk workspace, server load harus turun signifikan;
- answer sync stabil;
- monitoring tidak mengganggu engine;
- Live Scoring tidak mengganggu engine;
- tidak ada retry storm;
- tidak ada jawaban hilang karena desain.

## 45.3 Adaptive Load Protection

Jika server load tinggi:

Prioritas tetap:

1. Login;
2. START/RESUME;
3. Answer Sync;
4. Timer authority;
5. Submit/Finalize;
6. Reset Akses.

Yang dapat diturunkan frekuensinya:

- chart;
- monitoring refresh;
- aggregate non-kritis;
- telemetry;
- public display refresh.

Tidak ada mandatory Redis/WebSocket.

---

# 46. DATABASE — MODEL LOGIS ACUAN

Nama tabel dapat dipoles saat implementasi, tetapi entitas dan constraint bisnis di bagian ini tidak boleh hilang.

## 46.1 Core / System

### `sys_settings`

Menyimpan:

- key;
- value;
- type;
- is_secret;
- updated_at.

Termasuk credential encryption key.

### `sys_logs`

- actor;
- action;
- module;
- target;
- metadata;
- timestamp.

### `manager_users`

- username;
- password_hash;
- role ADMIN/OPERATOR;
- status;
- login protection metadata.

### `ci_sessions`

Session CI4/database driver jika dipilih.

## 46.2 Master

### `periode`

- tahun_pelajaran;
- semester.

### `rombel`

- tingkat;
- kode_rombel;
- display_name;
- status.

### `mata_pelajaran`

- kode;
- nama;
- singkatan;
- status;
- urutan.

### `peserta`

- nisn UNIQUE;
- nama;
- jenis_kelamin;
- rombel_id;
- status;
- keterangan;
- username UNIQUE;
- password_hash;
- password_encrypted;
- credential timestamps/status.

## 46.3 Kegiatan

### `kegiatan`

- nama;
- jenis;
- periode_id;
- keterangan;
- status DRAFT/BERJALAN/SELESAI;
- exam_browser_required;
- timestamps.

### `ruang`

- kode UNIQUE;
- nama;
- status.

### `peserta_kegiatan`

- kegiatan_id;
- peserta_id;
- ruang_id;
- nomor_peserta;
- snapshot identity;
- UNIQUE(kegiatan_id,peserta_id);
- UNIQUE(kegiatan_id,nomor_peserta).

## 46.4 Bank Akademik

### `bank_soal`

- kegiatan_id;
- mapel_id;
- tingkat/context;
- status DRAFT/READY;
- scoring config;
- randomization config;
- version/fingerprint.

### `soal`

- bank_id;
- stable_question_id;
- tipe;
- revision_no;
- content normalized;
- max_point;
- status/void;
- ordering metadata.

### `soal_opsi`

- stable_option_id;
- question_id;
- content;
- key/point metadata;
- order metadata.

### `soal_revision`

Histori live edit/revision.

## 46.5 Psikologis

### `instrumen_psikologis`

- kegiatan_id;
- nama/version;
- status;
- scoring model config.

### `psych_dimension`

Dimensi instrumen.

### `psych_item`

Item dan revision.

### `psych_option`

Opsi jawaban.

### `psych_scoring_matrix`

Option → dimension points.

### `psych_norm`

Norm/category/interpretation data yang diberikan instrumen.

## 46.6 Jadwal

### `jadwal`

- kegiatan_id;
- bank/instrument target;
- mulai;
- batas_mulai;
- durasi;
- access_state BUKA/TAHAN;
- tampilkan_nilai_saat_selesai;
- visibility setting lock after first start;
- parent_jadwal_id nullable untuk Susulan;
- type MAIN/SUSULAN;
- timestamps.

Susulan menggunakan tabel yang sama dengan `parent_jadwal_id` agar jumlah Susulan tidak dibatasi.

### `jadwal_peserta_target`

Digunakan terutama untuk target Susulan/individual schedule bila diperlukan.

## 46.7 Preparation

### `prepared_assignment`

- peserta_kegiatan_id;
- jadwal/bank context;
- bank_revision/fingerprint;
- status READY/STALE;
- generated_at.

### `prepared_assignment_item`

- assignment_id;
- question_id;
- question_revision;
- question_order;
- option mapping/order;
- matching mapping;
- media manifest ref.

## 46.8 Attempt

### `attempt`

- peserta_id;
- peserta_kegiatan_id;
- jadwal_id;
- prepared_assignment_id;
- status ACTIVE/FINISHED/SUPERSEDED;
- client_uuid;
- client_generation;
- start_at;
- finish_at;
- duration_seconds;
- added_seconds;
- paused_seconds;
- reset/pause state;
- snapshot no peserta/nama/rombel/ruang;
- finalization metadata.

Constraint bisnis:

- satu peserta maksimum satu Attempt ACTIVE secara global;
- satu Attempt maksimum satu active client generation.

### `attempt_pause_event`

Audit Reset Akses/pause/resume.

### `attempt_client_event`

Hanya event yang bermakna; jangan dijadikan telemetry berlebihan.

## 46.9 Answer

### `attempt_answer`

- attempt_id;
- question/item_id;
- answer normalized;
- answer_revision;
- auto_score;
- manual_score;
- effective_score;
- scoring state;
- last_sync_at.

Unique:

```text
(attempt_id, question_id)
```

### `answer_mutation_log` (opsional/terbatas)

Hanya jika diperlukan untuk idempotency/audit teknis; jangan dibiarkan membengkak tanpa retensi.

## 46.10 Result / Final Snapshot

### `result_snapshot`

- attempt_id;
- schedule context;
- objective_click_score;
- typed_score;
- final_score;
- scoring status;
- finalized flag/time;
- immutable payload/version.

### `official_result_pointer`

Menunjuk hasil resmi terbaru per peserta/context tanpa mengubah snapshot lama, berguna untuk replacement Susulan.

## 46.11 Public Live Scoring

Tidak wajib tabel khusus jika aggregate dapat dihitung ringan/cached.

Boleh memiliki cache/aggregate server-side yang dapat rebuild.

## 46.12 Index Minimum Kritis

Index harus tersedia untuk:

- peserta username;
- kegiatan/status;
- peserta_kegiatan kegiatan+peserta;
- jadwal kegiatan+waktu/status;
- prepared assignment peserta/jadwal/status;
- attempt peserta/status;
- attempt jadwal/status;
- answer attempt+question;
- last_sync/monitoring;
- result official lookup.

Hindari query `ORDER BY RAND()` pada mass start.

---

# 47. ROUTES / API — KONTRAK LOGIS

Kontrak route/API final ditetapkan pada `CBT-HERO_ROUTES_API_FINAL.md`. Bagian ini tetap menjadi ringkasan requirement; implementasi method/path, actor, idempotency, transaction boundary, request/response, dan error code wajib mengikuti dokumen Routes/API Final tersebut.

Kelompok endpoint berikut harus ada.

## 47.1 Participant Auth

- login;
- logout;
- session status.

## 47.2 Exam Discovery

- daftar ujian;
- detail/confirmation data;
- eligibility check.

## 47.3 START / RESUME

START melakukan validasi:

- account;
- membership;
- data lock;
- schedule;
- BUKA/TAHAN;
- Batas Mulai;
- Token jika ON;
- Exam Browser jika required;
- prepared assignment READY;
- one active attempt;
- one active client.

RESUME memvalidasi Attempt ACTIVE, client generation, token bila ON, dan access state.

## 47.4 Bootstrap

Mengirim package Attempt/question/media manifest/snapshot secukupnya.

## 47.5 Answer Sync

- batch mutation;
- idempotent;
- ACK revision;
- tidak mengirim full page/state setiap jawaban.

## 47.6 Finalize

- validate Attempt;
- accept pending valid mutation;
- finalize;
- return finish payload/one-time result UI data.

## 47.7 Monitoring

- summary;
- paginated list;
- detail;
- reset access;
- add time;
- force finish.

## 47.8 Live Scoring

- manager settings/start/stop;
- public snapshot endpoint;
- public endpoint hanya mengembalikan field yang diperlukan.

## 47.9 Import

- upload;
- parse;
- staging;
- validation;
- preview;
- commit.

## 47.10 Backup/Restore

- create database backup;
- restore database (Admin);
- create media backup;
- restore media (Admin);
- clear data (Admin).

---

# 48. DEPLOYMENT

Rekomendasi deployment CI4:

```text
app/
system/
writable/
public/
```

Web root diarahkan ke `public/` bila hosting memungkinkan.

Writable hanya untuk kebutuhan runtime:

- logs;
- cache;
- session jika file;
- temp import;
- media/upload terkontrol.

Source code tidak boleh menjadi target upload/import.

Media static sebisa mungkin dilayani langsung web server, tidak selalu melalui CI4 controller.

HTTPS direkomendasikan kuat untuk produksi.

---

# 49. ACCEPTANCE TEST WAJIB

Sebelum CBT-HERO dinyatakan siap produksi, minimal harus lulus skenario berikut.

## 49.1 Auth dan Credential

- login peserta benar/salah;
- uppercase normalization username;
- generated credential non-ambigu;
- print kartu JetBrains Mono;
- bulk reset warning;
- credential lock saat ujian berjalan.

## 49.2 Schedule

- sebelum Mulai;
- di antara Mulai dan Batas Mulai;
- setelah Batas Mulai sebelum START;
- resume Attempt setelah Batas Mulai;
- BUKA/TAHAN;
- tambah waktu.

## 49.3 Token

- ON/OFF;
- rotate;
- re-entry;
- token tidak memengaruhi peserta di dalam.

## 49.4 Preparation

- READY;
- STALE;
- missing preparation blocks START;
- incremental rebuild.

## 49.5 Attempt

- one active attempt per participant;
- one active client;
- duplicate tab guard;
- reset access;
- device change after reset.

## 49.6 Offline

- network putus di tengah ujian;
- answer tersimpan lokal;
- recover sync;
- refresh;
- timeout saat offline;
- reconnect after timeout.

## 49.7 Submit

- normal submit;
- pending answer;
- timeout submit;
- force finish.

## 49.8 Susulan

- belum pernah START;
- replacement attempt;
- multiple Susulan;
- official result pointer.

## 49.9 Live Edit

- content;
- key/weight;
- structural;
- VOID;
- active participant;
- finished result sebelum final;
- final result immutable.

## 49.10 Scoring

- semua 6 tipe akademik;
- Isian TEXT;
- Isian NUMERIC tolerance;
- manual override;
- VOID;
- normalized 2 kelompok;
- final weighted score.

## 49.11 Result Visibility

- ON/OFF;
- setting lock after first START;
- one-time finish UI;
- no participant result portal.

## 49.12 Public Live Scoring

- ranking;
- tie;
- full scroll cycle;
- refresh only after end of cycle;
- 50/500/2000 rows;
- stop/start/regenerate public URL.

## 49.13 Psych

- Likert;
- Forced Choice;
- reverse scoring;
- matrix;
- norms;
- report;
- no participant-side result;
- no public ranking.

## 49.14 Security

- SQL injection regression;
- XSS/HTML sanitizer;
- CSRF manager mutation;
- IDOR;
- session fixation/regeneration;
- malicious upload;
- path traversal archive;
- source directory non-writable by upload flow.

## 49.15 Backup/Restore

- DB backup includes encryption key;
- DB restore can decrypt old printable password;
- media backup/restore;
- clear-data dependency safety.

## 49.16 Load

- 500;
- 1000;
- 1500;
- 2000 concurrent;
- stress 2500;
- verify load drops after START peak;
- no answer loss;
- monitoring/live scoring do not starve exam core.

---

# 50. HAL YANG SENGAJA TIDAK ADA

Untuk mencegah scope creep, CBT-HERO V1 **tidak** mempunyai:

- E-Learning;
- Guru/Pengawas role;
- jadwal mengajar guru;
- clone/copy Bank sebagai workflow utama;
- paket ujian manual;
- gelombang/sesi kompleks;
- Blueprint terpisah;
- participant result portal;
- pembahasan/kunci jawaban untuk peserta;
- mandatory Redis;
- mandatory WebSocket;
- mandatory CDN;
- anti-cheat browser agresif ala pseudo-Exam Browser;
- kamera/proctoring V1;
- diagnosis psikologis otomatis yang tidak berasal dari instrumen;
- true/false sebagai tipe akademik standalone.

---

# 51. PEMBAGIAN FASE IMPLEMENTASI

## Phase 1 — CBT Web Core

Mencakup seluruh requirement utama dokumen ini kecuali Exam Browser Android.

Fokus:

- master data;
- auth;
- kegiatan;
- bank/import;
- preparation;
- jadwal/susulan;
- attempt/cache/sync;
- monitoring;
- scoring;
- result/report;
- psych V1;
- live scoring;
- backup/restore;
- performance hardening.

## Phase 2 — Exam Browser Android

- client Android;
- device validation;
- Kegiatan Exam Browser ON;
- stricter device control;
- tetap memakai backend Attempt/Sync CBT-HERO yang sama.

---

# 52. DEFINISI DONE CBT-HERO V1

CBT-HERO V1 dianggap selesai jika:

1. seluruh modul inti pada dokumen ini tersedia;
2. seluruh keputusan FIX diterapkan tanpa kontradiksi;
3. import soal dapat menghasilkan Bank READY dengan staging/validation;
4. preparation dapat menyiapkan peserta sebelum peak;
5. 2.000 peserta dapat diuji sesuai environment target;
6. jawaban tidak hilang pada skenario offline/refresh normal;
7. monitoring dan Live Scoring tidak mengganggu engine ujian;
8. scoring 6 tipe akademik benar;
9. psych V1 berjalan pada core ujian yang sama;
10. final result immutable setelah finalisasi;
11. backup/restore berfungsi;
12. security baseline lulus;
13. UI peserta nyaman di Android;
14. UI Operator tetap operasional saat peak.

---

# 53. RINGKASAN FINAL ARSITEKTUR

```text
                       CBT-HERO
                          │
            ┌─────────────┴─────────────┐
            │                           │
        MANAGER                      PESERTA
     Admin / Operator                Web Client
            │                           │
            │                   Login / Daftar Ujian
            │                           │
       Master Data                 Konfirmasi
       Kegiatan                        │
       Bank/Instrumen             START / RESUME
       Jadwal/Susulan                  │
       Preparation              Prepared Assignment
       Monitoring                       │
       Scoring                  IndexedDB Question Cache
       Reports                  IndexedDB Answer Store
       Backup                           │
            │                     Async Answer Sync
            │                           │
            └──────────── SERVER AUTHORITY ────────────┐
                                                        │
                                                 Result / Final
                                                        │
                                              Report / Live Score
```

CBT-HERO sengaja memindahkan sebanyak mungkin pekerjaan non-authoritative ke client setelah START, tetapi tetap menjaga server sebagai authority untuk eligibility, Attempt, waktu, jawaban ACK, scoring, dan finalisasi.

---

# 54. CHANGE CONTROL

Dokumen ini adalah baseline implementasi.

Setiap revisi setelah v1.0 harus dicatat di bagian ini dengan format:

```text
Tanggal | Versi | Bagian | Perubahan | Alasan | Dampak DB/API/UI
```

Tidak boleh ada perubahan diam-diam pada requirement hanya karena implementasi terasa lebih mudah dengan cara lain.

---

## CHANGE LOG

| Tanggal | Versi | Bagian | Perubahan | Alasan | Dampak |
|---|---|---|---|---|---|
| 2026-09-24 | 1.0 | Seluruh dokumen | Baseline final hasil audit konsep CBT-HERO | Menjadi single source of truth sebelum implementasi | Baseline DB/API/UI |

---

**END OF DOCUMENT — CBT-HERO DOKUMEN ACUAN UTAMA v1.0**
