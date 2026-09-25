# CBT-HERO — UI/UX ACUAN IMPLEMENTASI

**Versi:** 1.0  
**Tanggal:** 25 September 2026  
**Status:** FINAL BASELINE / DOKUMEN TURUNAN NORMATIF  
**Induk:** `CBT-HERO_DOKUMEN_ACUAN_UTAMA.md`

---

## 0. POSISI DOKUMEN

Dokumen ini mengatur **konsistensi tampilan dan perilaku antarmuka**, bukan membuat requirement bisnis baru. Bila terdapat konflik, Dokumen Acuan Utama menang.

Visual companion:

- `CBT-HERO_UIUX_Mockup.html`

Prioritas implementasi:

```text
Business Rule / Flow
        ↓
UI/UX Acuan
        ↓
Visual Mockup
        ↓
Kode halaman
```

---

# 1. DESIGN PRINCIPLES

1. **Manager Desktop First, Responsive Mobile.**
2. **Participant Mobile First, Desktop Adaptation.**
3. **Performance Before Decoration.**
4. **Data Dense untuk Manager, Focused Workspace untuk Peserta.**
5. **Satu pola untuk satu masalah.** Jangan membuat tabel, modal, toolbar, badge, atau drawer dengan gaya berbeda per modul.
6. **Operational clarity.** Status dan aksi kritis harus terbaca cepat saat ujian berlangsung.
7. **No security theater UI.** Jangan memenuhi workspace peserta dengan indikator anti-cheat yang tidak memberi manfaat operasional.
8. **No forced zoom lock.** Browser/mobile zoom tidak dikunci.

---

# 2. DESIGN SYSTEM DASAR

Foundation:

- Bootstrap 5.x;
- Bootstrap Icons;
- Vanilla JS;
- custom CSS variables CBT-HERO;
- asset lokal pada production.

Typography utama memakai system-font stack yang cepat dan stabil. Credential print mempunyai pengecualian khusus pada bagian Kartu Peserta.

Komponen dasar yang harus reusable:

- Button;
- Input/Select/Textarea;
- Badge/Status;
- Card;
- Metric Card;
- Toolbar;
- Filter Panel;
- Data Table;
- Empty State;
- Alert;
- Modal;
- Offcanvas;
- Right Drawer;
- Confirmation Dialog;
- Loading Skeleton/Spinner;
- Toast/Inline Feedback.

Tidak membuat komponen baru hanya karena modul baru.

---

# 3. MANAGER SHELL — ADMIN / OPERATOR

## 3.1 Desktop

Struktur baku:

```text
┌──────────────────────────────────────────────────────────────┐
│ Topbar / Activity Context / Account                          │
├──────────────┬───────────────────────────────────────────────┤
│ Sidebar      │ Page Header                                   │
│              │ Summary / Metrics (jika berguna)              │
│              │ Filter / Toolbar                              │
│              │ Main Content / Data Table                     │
│              │                                               │
└──────────────┴───────────────────────────────────────────────┘
```

Sidebar desktop tetap. Menu mengikuti kelompok:

- Dashboard;
- Master Data;
- Master Ujian;
- Pelaksanaan;
- Hasil & Laporan;
- Sistem.

Kegiatan aktif/terpilih ditampilkan sebagai **context bar**, bukan ditanam ulang pada setiap form.

## 3.2 Mobile Manager

- sidebar menjadi offcanvas;
- filter kompleks boleh menjadi offcanvas/bottom sheet;
- tabel boleh horizontal-scroll atau berubah menjadi compact record layout bila benar-benar lebih mudah;
- action utama tetap terlihat;
- tidak memaksakan semua kolom desktop masuk layar kecil.

---

# 4. POLA HALAMAN MANAGER

Urutan default:

```text
Page Header
→ Primary Action
→ Summary opsional
→ Search / Filter / Bulk Action
→ Table / Content
→ Pagination
```

Gunakan **table** untuk data operasional yang perlu dibandingkan antarbanyak baris. Jangan mengganti dataset besar menjadi ratusan card.

Right Drawer cocok untuk:

- detail peserta;
- detail attempt;
- quick preview;
- metadata tanpa meninggalkan halaman monitoring.

Modal cocok untuk:

- form singkat;
- konfirmasi;
- aksi kecil yang tidak membutuhkan halaman penuh.

Halaman penuh cocok untuk:

- Bank Soal editor;
- Template/Import staging;
- Instrumen Psikologis;
- halaman detail yang mempunyai banyak subsection.

---

# 5. STATUS UI

Status harus mempunyai label teks, tidak bergantung warna saja.

Contoh status operasional:

```text
DRAFT
BERJALAN
SELESAI
READY
STALE
BUKA
TAHAN
BELUM
ACTIVE / SEDANG UJIAN
FINISHED / SELESAI
SUPERSEDED
TIDAK TERDETEKSI
DALAM PROSES
FINAL
```

Warna semantik konsisten:

- success = kondisi sehat/selesai;
- warning = perlu perhatian;
- danger = destructive/blocking/error;
- info/primary = aksi atau state aktif;
- neutral = metadata/noncritical.

---

# 6. FORM & VALIDATION

- label selalu terlihat;
- placeholder bukan pengganti label;
- error ditampilkan dekat field;
- error server tidak hanya ditampilkan di console;
- Save/Submit mempunyai disabled/loading state;
- double submit dicegah;
- form panjang dikelompokkan menjadi section logis;
- setting yang sudah locked ditampilkan readonly/disabled dengan alasan singkat.

Contoh:

```text
Tampilkan Nilai Saat Selesai   [ ON ]
Terkunci karena peserta pertama sudah START.
```

---

# 7. DESTRUCTIVE ACTION

Konfirmasi harus proporsional.

Normal destructive action:

```text
1× confirmation
```

Aksi yang sudah FIX memakai 2× warning:

- regenerate Username massal;
- reset Password massal;
- aksi lain yang secara eksplisit ditetapkan 2× confirmation.

Restore/Pengosongan Data mengikuti requirement Sistem dan hanya untuk Admin.

---

# 8. PARTICIPANT FLOW

Flow baku:

```text
LOGIN
→ DAFTAR UJIAN
→ PILIH UJIAN
→ KONFIRMASI UJIAN
→ START / RESUME
→ WORKSPACE
→ SELESAI
→ KONFIRMASI #1
→ KONFIRMASI #2
→ SYNC / FINALIZE
→ HALAMAN SELESAI
```

Tidak membuat jalur alternatif tanpa requirement.

---

# 9. LOGIN PESERTA

Login hanya meminta:

```text
USERNAME
PASSWORD
```

Username input dinormalisasi uppercase. Form harus nyaman digunakan di Android, input besar, dan tombol login mudah disentuh.

Error login singkat dan operasional. Jangan membocorkan detail sistem/database.

---

# 10. DAFTAR UJIAN PESERTA

Setiap card/list item menampilkan minimum:

- nama ujian/mapel;
- kegiatan;
- status akses;
- waktu bila relevan;
- primary action.

State utama:

```text
BELUM DIBUKA
BISA DIMULAI
LANJUTKAN
SELESAI
```

Jika ada satu Attempt ACTIVE, ujian lain tidak dapat START sampai Attempt tersebut selesai.

---

# 11. KONFIRMASI UJIAN

Menampilkan:

- identitas peserta;
- nama ujian;
- informasi jadwal/durasi;
- instruksi default dari Settings;
- checkbox persetujuan wajib;
- input Token hanya jika global Token = ON.

Halaman ini **tidak membuat Attempt dan tidak memulai timer**.

---

# 12. EXAM WORKSPACE — MOBILE

Prioritas layar:

```text
Soal
→ Jawaban
→ Status Sync
→ Timer
→ Navigasi
```

Header tetap ringkas:

- judul/mapel;
- status sync;
- timer.

Bottom navigation sticky:

```text
Sebelumnya | Ragu | Daftar Soal | Berikutnya
```

Touch target minimal sekitar 44–48 px.

Seluruh area opsi pilihan menjadi target sentuh, bukan hanya radio/checkbox kecil.

---

# 13. EXAM WORKSPACE — DESKTOP

Flow sama dengan mobile. Ruang tambahan digunakan untuk palette soal permanen di kanan.

Tidak membuat feature desktop yang menyebabkan perilaku jawaban berbeda dari mobile.

---

# 14. QUESTION RENDERING

Renderer yang sama secara konseptual dipakai untuk preview, bank, client, dan print.

UI wajib mendukung:

- rich text;
- Arabic RTL;
- formula KaTeX;
- responsive image;
- image zoom/Panzoom;
- table horizontal-scroll;
- audio controls;
- video/reference online;
- stimulus panjang.

Jenis akademik tetap enam secara engine, tetapi peserta melihat pengalaman sesuai interaksi soal, bukan istilah teknis database.

Ragu-ragu tersedia untuk seluruh tipe akademik.

---

# 15. QUESTION PALETTE

State minimal nomor soal:

- current;
- answered;
- unanswered;
- flagged/ragu.

Desktop = panel permanen.  
Mobile = bottom sheet.

Jangan membuat palette memicu request server per klik; navigasi lokal.

---

# 16. STATUS SYNC

Peserta harus selalu mendapat feedback sederhana:

```text
✓ Tersimpan
↻ Menyimpan
⚠ Belum tersinkron
```

Status ini menunjukkan durability/sync state, bukan status anti-cheat.

---

# 17. TIMER

Timer selalu terlihat tetapi tidak mendominasi layar.

Saat waktu habis:

- input langsung locked;
- tampil state proses penyelesaian;
- queue lama tetap diproses sesuai engine timeout/offline;
- tidak ada modal kedua yang meminta peserta memperpanjang waktu.

---

# 18. HALAMAN SELESAI

Akademik, jika `Tampilkan Nilai Saat Selesai = ON`:

```text
NILAI PILIHAN GANDA
85.00

NILAI ISIAN & URAIAN
DALAM PROSES
```

Tidak ada portal peserta untuk membuka kembali nilai/jawaban.

Psikologis tidak menampilkan hasil peserta secara default.

---

# 19. MONITORING UJIAN

Manager page memakai pola:

```text
Summary
→ Filter
→ Table
→ Row Action / Bulk Action
```

Kolom minimum:

```text
No Peserta | Nama | Rombel | Ruang | Ujian | Status | Used | Remaining | Last Sync | Aksi
```

Aksi individual/bulk sesuai Dokumen Acuan Utama.

Monitoring harus tetap readable ketika load tinggi; visualisasi non-kritis boleh dikurangi lebih dulu.

---

# 20. PUBLIC LIVE SCORING

Fullscreen tanpa sidebar/topbar manager.

Kolom **tepat**:

```text
No | No Peserta | Nama | Skor | Status
```

Satu snapshot ditampilkan sampai scroll mencapai peserta terakhir. Baru setelah itu snapshot baru diambil, ranking dihitung ulang, dan display kembali ke atas.

---

# 21. KARTU PESERTA

Data biasa memakai font standar CBT-HERO.

Tiga credential wajib memakai:

```text
JetBrains Mono SemiBold/Bold
```

untuk:

- NOMOR PESERTA;
- USERNAME;
- PASSWORD.

Credential dicetak lebih besar daripada metadata lain.

Generated Username/Password:

- uppercase;
- hindari `O I L 0 1`;
- Username input login dinormalisasi uppercase.

Nomor Peserta tetap boleh memakai `0`/`1`; perbedaan karakter diserahkan pada bentuk glyph JetBrains Mono.

Baseline kartu A4:

```text
2 kolom × 5 baris = 10 kartu / lembar
```

---

# 22. RESPONSIVE & ACCESSIBILITY

- viewport tidak mengunci zoom;
- teks ujian tidak terlalu kecil;
- A-/A/A+ tersedia di workspace;
- target sentuh cukup besar;
- warna bukan satu-satunya pembeda state;
- keyboard focus tetap terlihat pada desktop;
- modal/offcanvas tidak membuat focus trap rusak;
- media dan formula tidak menyebabkan overflow halaman.

---

# 23. LOADING / EMPTY / ERROR

Setiap halaman data wajib mempunyai tiga state yang dirancang:

```text
LOADING
EMPTY
ERROR
```

Tidak boleh hanya menghasilkan area kosong.

Error operasional harus menjawab dua hal:

```text
Apa yang gagal?
Apa yang dapat dilakukan Operator/Peserta sekarang?
```

---

# 24. PERFORMANCE UI

- jangan render ribuan row sekaligus di Manager;
- DataTables server-side untuk dataset besar;
- debounce search;
- monitoring polling ringan;
- exam navigation lokal;
- public Live Scoring menggunakan frozen snapshot per cycle;
- animasi/dekorasi tidak boleh mengganggu perangkat Android kelas menengah.

---

# 25. ATURAN IMPLEMENTASI KONSISTENSI

Sebelum membuat halaman baru, developer wajib menentukan halaman tersebut menggunakan pola mana:

```text
LIST/TABLE
DETAIL
FORM
WIZARD/STAGING
MONITORING
EXAM
PUBLIC DISPLAY
PRINT
```

Kemudian gunakan shell, spacing, toolbar, button hierarchy, status badge, dialog, dan feedback yang sudah ada.

**Tidak membuat CSS per halaman untuk menyelesaikan masalah yang seharusnya menjadi reusable component.**

---

# 26. DEFINITION OF UI/UX PASS

Sebuah halaman dianggap PASS bila:

- sesuai flow requirement;
- menggunakan shell/pattern yang benar;
- desktop/mobile sesuai prioritas actor;
- loading/empty/error tersedia;
- action hierarchy jelas;
- destructive confirmation sesuai aturan;
- state locked/disabled dapat dimengerti;
- tidak ada horizontal overflow yang tidak disengaja;
- zoom tidak dikunci;
- tidak ada dependency frontend di luar stack final;
- visual konsisten dengan `CBT-HERO_UIUX_Mockup.html` sepanjang tidak bertentangan dengan requirement terbaru.
