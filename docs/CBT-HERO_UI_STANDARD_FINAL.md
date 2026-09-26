# CBT-HERO — UI STANDARD FINAL

**Versi:** 1.1  
**Tanggal:** 25 September 2026  
**Status:** FINAL IMPLEMENTATION STANDARD  
**Fokus awal:** Manager / Admin / Operator  
**Induk UI/UX:** `CBT-HERO_UIUX_ACUAN.md`

Dokumen ini menerjemahkan acuan UI/UX CBT-HERO menjadi standar implementasi teknis yang dipakai oleh seluruh halaman. Tujuannya adalah mencegah setiap modul membuat pola tabel, pagination, filter, form, modal, spacing, ukuran huruf, status, dan responsive behavior sendiri-sendiri.

Jika terjadi konflik:

```text
CBT-HERO_DOKUMEN_ACUAN_UTAMA.md
        ↓
CBT-HERO_UIUX_ACUAN.md
        ↓
CBT-HERO_UI_STANDARD_FINAL.md
        ↓
Implementasi CSS / View / JavaScript
```

---

# 1. PRINSIP UMUM

Manager CBT-HERO:

- desktop-first;
- mobile tetap usable;
- data-dense, tetapi tidak sesak;
- table lebih diprioritaskan daripada card untuk dataset administratif;
- satu Manager Shell untuk seluruh modul;
- action hierarchy harus konsisten;
- status tidak bergantung warna saja;
- library hanya dimuat ketika halaman membutuhkannya;
- tidak membuat UI berat hanya untuk dekorasi;
- tidak membuat CSS besar per halaman jika masalahnya seharusnya reusable.

Participant CBT-HERO:

- mobile-first;
- fokus pada alur ujian;
- touch target cukup besar;
- tidak mengunci browser zoom;
- tidak membawa dependency Manager yang tidak diperlukan.

---

# 2. DESIGN TOKENS DASAR

Spacing scale utama:

```text
4px
8px
12px
16px
20px
24px
32px
```

Gunakan scale ini sebelum membuat nilai baru.

Radius baseline:

```text
small control     8–10px
input/button      9–11px
card              13–16px
large panel       18–24px
```

Shadow:

- gunakan ringan;
- tidak setiap elemen membutuhkan shadow;
- table container dan card besar boleh memakai shadow tipis;
- hover shadow tidak boleh menyebabkan layout shift.

---

# 3. BREAKPOINT & RESPONSIVE

Gunakan breakpoint Bootstrap sebagai baseline:

```text
xs   < 576px
sm   ≥ 576px
md   ≥ 768px
lg   ≥ 992px
xl   ≥ 1200px
xxl  ≥ 1400px
```

Minimum viewport test setiap halaman Manager:

```text
Desktop:
1366 × 768
1920 × 1080

Tablet:
768 × 1024

Mobile:
390 × 844
360 × 800
375 × 667
```

Tidak boleh ada horizontal page overflow yang tidak disengaja.

Horizontal scroll **boleh** pada area table/media tertentu.

---

# 4. MANAGER SHELL

## 4.1 Sidebar

Expanded:

```text
260px
```

Minimized:

```text
72px
```

Aturan desktop:

- sidebar fixed;
- tombol minimize/maximize berada di topbar;
- state disimpan di `localStorage`;
- minimized menampilkan icon utama;
- section label/submenu disembunyikan ketika minimized;
- klik kategori ketika minimized membuka sidebar kembali;
- active state tetap terlihat.

Aturan mobile `< 992px`:

- sidebar menjadi offcanvas;
- tidak memakai mode 72px;
- menu ditutup setelah user pindah halaman;
- body tidak boleh ikut horizontal-scroll.

## 4.2 Topbar

Baseline:

```text
height: 64px
```

Berisi:

- sidebar control;
- judul halaman;
- subtitle singkat;
- context Kegiatan bila tersedia;
- account Manager.

Jangan membuat topbar dua baris untuk informasi yang dapat masuk page header.

## 4.3 Content

Desktop:

```text
padding-left/right: 24px
padding-top:        22px
padding-bottom:     38px
```

Mobile:

```text
padding-left/right: 12–14px
padding-top:        14–16px
padding-bottom:     24–28px
```

---

# 5. TYPOGRAPHY MANAGER

| Elemen | Desktop | Mobile |
|---|---:|---:|
| Page title | 25–30px | 23–24px |
| Page description | 13px | 12px |
| Topbar title | 14px | 14px |
| Topbar subtitle | 10px | hidden bila sempit |
| Sidebar menu | 12.5px | 12.5px |
| Sidebar submenu | 11.5px | 11.5px |
| Section/card title | 13–14px | 13–14px |
| Table header | 10px | 10px |
| Table body | 12px | 12px |
| Metric label | 10px | 10px |
| Metric value | 20px | 19px |
| Helper text | 11–12px | 11–12px |

Judul besar hanya dipakai bila hierarchy memang membutuhkannya.

---

# 6. POLA HALAMAN MANAGER

Urutan default:

```text
Page Header
→ Primary Action
→ Summary/Metrics bila berguna
→ Toolbar
→ Search / Filter / Bulk Action
→ Table / Main Content
→ Pagination
```

Pattern halaman harus dipilih sebelum coding:

```text
LIST / TABLE
DETAIL
FORM
WIZARD / STAGING
MONITORING
EDITOR
REPORT
SYSTEM
PUBLIC DISPLAY
PRINT
```

---

# 7. BUTTON HIERARCHY

Gunakan hierarchy konsisten:

```text
Primary
→ aksi utama halaman

Secondary / Outline
→ aksi alternatif

Neutral
→ utility

Danger
→ destructive
```

Aturan:

- maksimal satu primary action dominan pada satu area;
- icon tidak menggantikan text untuk aksi kritis;
- icon-only button wajib `title` / accessible label;
- destructive action tidak memakai warna primary;
- loading state men-disable tombol dan mencegah double submit.

Minimum touch target:

```text
44px
```

untuk Participant dan action mobile penting.

---

# 8. DATA TABLE — STANDAR UMUM

Manager menggunakan:

```text
DataTables 2.x
Vanilla JavaScript
Bootstrap 5 integration
tanpa jQuery
```

Namun DataTables **tidak wajib** untuk setiap tabel.

Gunakan plain Bootstrap table bila:

- dataset kecil dan stabil;
- tidak membutuhkan search/sort/pagination kompleks;
- jumlah row realistis tetap rendah.

Gunakan DataTables 2 bila:

- user perlu search/sort/filter interaktif;
- banyak kolom;
- bulk selection;
- dataset berpotensi besar;
- pagination wajib.

Dataset besar selalu:

```text
server-side
```

Jangan render 1.500–2.000+ row sekaligus ke DOM.

---

# 9. DATA TABLE — SERVER SIDE CONTRACT

API bisnis tetap mengikuti kontrak:

```text
page
per_page
q
sort
order=asc|desc
status
+ filter domain-specific
```

DataTables adapter frontend boleh menerjemahkan:

```text
draw
start
length
search
order
```

ke kontrak API tersebut.

Backend tidak boleh menerima raw nama kolom SQL dari browser.

`sort` harus melalui whitelist server.

Contoh:

```text
nama      → peserta.nama
username  → peserta.username
rombel    → rombel.display_name
```

bukan:

```text
ORDER BY $_GET['sort']
```

---

# 10. PAGINATION

Default Manager dataset besar:

```text
per_page = 50
```

Pilihan page size standar:

```text
25
50
100
```

Tidak menyediakan:

```text
ALL
SHOW ALL
```

untuk dataset besar.

Pagination menampilkan:

```text
Menampilkan 1–50 dari 1.500
```

dan kontrol:

```text
Sebelumnya | 1 | 2 | 3 | … | Berikutnya
```

Aturan:

- posisi pagination di bawah table;
- mobile menggunakan pagination compact;
- perubahan filter kembali ke page 1;
- refresh data mempertahankan page jika page tersebut masih valid;
- export tidak mengekspor hanya page DOM yang sedang tampil.

---

# 11. DEFAULT PAGE SIZE PER AREA

| Area | Default | Catatan |
|---|---:|---|
| Rombel | 25 | plain/DataTables sesuai kebutuhan |
| Mata Pelajaran | 25 | plain/DataTables |
| Peserta | 50 | server-side |
| User Manager | 25 | dataset kecil |
| Kegiatan Ujian | 25 | server-side bila histori banyak |
| Peserta Ujian / Membership | 50 | server-side |
| Ruang | 25 | dataset kecil |
| Bank Soal | 25 | list bank; item soal memakai pola editor |
| Jadwal | 25 | filter Kegiatan/Mapel/Tingkat |
| Import Staging | 50 | server-side |
| Monitoring Ujian | 50 | server-side + refresh/delta |
| Hasil Ujian | 50 | server-side |
| Rekap Nilai | 50 | server-side; matrix bisa horizontal-scroll |
| Analisis Soal | 50 | per item |
| Hasil Psikologis | 50 | server-side |
| Audit / System Log | 50 | server-side, filter waktu |
| Backup History | 25 | bila histori ditampilkan |

Nilai ini default UI. Server tetap membatasi `per_page` maksimum.

---

# 12. SEARCH

Search table default:

```text
debounce: 350ms
```

Aturan:

- jangan request setiap keypress tanpa debounce;
- query kosong tidak dikirim berulang;
- search reset kembali ke page 1;
- tombol clear harus tersedia;
- pencarian exact seperti No Peserta/Username tetap boleh memakai satu search field yang sama jika backend mendukung.

Untuk halaman dengan traffic tinggi seperti Monitoring:

```text
debounce 500ms
```

lebih disarankan.

---

# 13. SORTING

Default:

- hanya kolom yang secara operasional berguna yang sortable;
- kolom checkbox tidak sortable;
- kolom aksi tidak sortable;
- sorting server-side pada dataset besar;
- active sort diberi indikator arah.

Default sort harus eksplisit per modul.

Contoh:

```text
Peserta          → nama ASC / atau username ASC sesuai kebutuhan halaman
Monitoring       → status + no peserta / urutan operasional
Log              → created_at DESC
Hasil            → nomor peserta ASC
Import staging   → item_no ASC
```

---

# 14. FILTER

Filter sederhana:

- select/dropdown;
- search;
- status;
- tingkat;
- rombel;
- mapel;
- Kegiatan;
- ruang;
- tanggal bila relevan.

Desktop:

```text
toolbar horizontal / wrap
```

Mobile:

```text
filter button
→ offcanvas / sheet
```

Aturan:

- tampilkan badge/count bila filter aktif;
- `Reset Filter` selalu tersedia jika filter aktif;
- filter perubahan mengembalikan ke page 1;
- filter tidak boleh memicu full page reload bila halaman sudah memakai AJAX table;
- filter domain-specific tetap divalidasi server.

---

# 15. BULK SELECTION

Pada Master Data Peserta, tombol **Pilih Semua (Halaman Ini)** dan checkbox header
memilih maksimal 100 Peserta pada halaman yang sedang tampil. Pilihan dapat
dipertahankan ketika berpindah halaman, tetapi dihapus saat filter atau jumlah
baris per halaman berubah. Jumlah terpilih terlihat sebelum aksi Username atau
Password, dan aksi massal meminta dua konfirmasi.

Tabel Peserta menampilkan Username dan kolom Password. Isi Password dibuka
per baris melalui tombol **Lihat** pada endpoint credential berizin dan dapat
disembunyikan lagi; plaintext tidak ikut dalam response daftar Peserta.
Form Tambah/Edit Peserta memakai modal singkat, sedangkan import memakai halaman
staging tersendiri.

Header checkbox default:

```text
pilih row pada PAGE SAAT INI
```

Jangan diam-diam memilih seluruh filtered dataset.

Jika fitur membutuhkan seluruh hasil filter:

```text
Pilih 50 pada halaman ini
→ opsi eksplisit:
"Pilih seluruh 327 hasil filter"
```

Destructive bulk action harus menampilkan:

- jumlah record;
- scope selection;
- dampak;
- confirmation sesuai tingkat risiko.

Setelah action:

- clear selection;
- refresh data;
- tampilkan hasil sukses/gagal;
- partial failure harus dijelaskan.

---

# 16. KOLOM TABLE

Urutan umum:

```text
[checkbox bila bulk]
Identitas utama
Metadata pembanding
Status
Updated/Time bila penting
Aksi
```

Aksi selalu di kolom paling kanan.

Untuk table lebar:

- horizontal scroll;
- identitas utama boleh sticky bila benar-benar membantu;
- jangan memaksa font menjadi sangat kecil;
- kolom sekunder boleh disembunyikan pada mobile.

Responsive tidak berarti memaksa semua kolom desktop tampil pada 360px.

---

# 17. ROW ACTION

Aksi yang sering dipakai:

```text
button langsung
```

Aksi sekunder/banyak:

```text
dropdown "Aksi"
```

Untuk Monitoring:

```text
Reset Akses
Tambah Waktu
Paksa Selesai
Detail
```

Tetap mudah dijangkau tanpa submenu bertingkat.

---

# 18. TABLE STATE

Setiap table/list wajib memiliki:

## Loading

- skeleton row atau table loading indicator;
- jangan tampilkan kosong seolah data tidak ada.

## Empty — belum ada data

Contoh:

```text
Belum ada peserta.
[Tambah Peserta] [Import Excel]
```

## Empty — filter tidak menemukan hasil

Contoh:

```text
Tidak ada data yang sesuai filter.
[Reset Filter]
```

## Error

Tampilkan:

```text
Data gagal dimuat.
[Coba Lagi]
```

Bila ada `request_id`, boleh ditampilkan sebagai reference teknis kecil.

---

# 19. DATATABLES STATE

`stateSave` DataTables:

```text
OFF secara default
```

Alasan:

- Kegiatan/context dapat berubah;
- filter lama bisa membingungkan operator;
- schema kolom dapat berubah selama development.

Yang boleh dipersist:

- page size;
- column visibility opsional;
- filter tertentu bila requirement jelas.

Gunakan app-level storage yang terkontrol, bukan menyimpan seluruh DataTables state tanpa aturan.

Perubahan Kegiatan aktif harus membersihkan state/filter yang tidak lagi relevan.

---

# 20. EXPORT TABLE

Export tidak mengambil row dari DOM saat ini.

Gunakan endpoint/report service yang menjalankan filter yang sama.

Contoh:

```text
Filter UI:
Rombel 7-A
Status ACTIVE

Export Excel
→ seluruh hasil filter 7-A + ACTIVE
→ bukan hanya page 1
```

File besar boleh dibangun server-side/chunked sesuai kebutuhan.

Button export ditempatkan di toolbar, bukan di setiap row.

---

# 21. TOOLBAR STANDARD

Desktop:

```text
[Search................] [Filter] [Reset]
                     [Bulk Action] [Export] [Primary Action]
```

Urutan boleh menyesuaikan ruang, tetapi hierarchy tetap sama.

Mobile:

```text
Search
[Filter] [Action]
```

Action sekunder boleh masuk dropdown.

Toolbar tidak boleh mempunyai terlalu banyak button dengan bobot visual sama.

---

# 22. FORM

Label:

```text
12–13px
```

Aturan:

- label selalu terlihat;
- placeholder bukan pengganti label;
- inline validation dekat field;
- field readonly berbeda dari disabled business state;
- locked field menampilkan alasan;
- form panjang dikelompokkan section;
- Save/Submit mencegah double submit;
- server validation authoritative.

Form singkat:

```text
modal / right drawer
```

Form panjang:

```text
full page
```

---

# 23. MODAL

Gunakan modal untuk:

- form pendek;
- confirmation;
- input parameter sederhana;
- quick command.

Jangan gunakan modal untuk:

- editor bank soal panjang;
- import staging;
- detail dengan banyak subsection;
- table besar.

Default size:

```text
sm / default / lg
```

Gunakan fullscreen mobile bila konten memang membutuhkan.

---

# 24. RIGHT DRAWER / OFFCANVAS DETAIL

Cocok untuk:

- Detail Peserta;
- Detail Attempt;
- Quick Preview;
- Detail log;
- metadata tanpa meninggalkan table.

Desktop:

```text
width kira-kira 420–520px
```

Mobile:

```text
fullscreen / hampir penuh
```

Drawer detail tidak boleh menjadi tempat mengedit workflow kompleks.

---

# 25. SWEETALERT2 / CONFIRMATION

SweetAlert2 dipakai untuk confirmation/toast yang memang membutuhkan dialog.

Normal destructive action:

```text
1× confirmation
```

Aksi yang sudah FIX memakai:

```text
2× confirmation
```

minimum:

- regenerate Username massal;
- reset Password massal.

Restore/Pengosongan Data memakai confirmation yang menjelaskan dependency/dampak.

Jangan memakai SweetAlert untuk validasi field biasa.

---

# 26. TOAST & INLINE FEEDBACK

Toast cocok untuk:

- save berhasil;
- aksi ringan berhasil;
- copy berhasil;
- background operation selesai.

Inline feedback cocok untuk:

- validation;
- login error;
- import error;
- warning locked state.

Error kritis tidak boleh hilang hanya sebagai toast 2 detik.

---

# 27. BADGE / STATUS

Status selalu:

```text
warna + text
```

Contoh:

```text
DRAFT
READY
STALE
BERJALAN
SELESAI
BUKA
TAHAN
BELUM
ACTIVE
FINISHED
SUPERSEDED
TIDAK TERDETEKSI
DALAM PROSES
FINAL
```

Warna semantik:

```text
success → sehat / selesai
warning → perhatian
danger  → blocking / destructive / error
primary/info → aktif / operasional
neutral → metadata
```

Nama badge harus konsisten dengan lifecycle backend.

---

# 28. SUMMARY / METRIC CARD

Metric hanya dipakai bila memberi nilai operasional.

Contoh Monitoring:

```text
Total
Belum
Sedang
Selesai
Tidak Terdeteksi
```

Jangan membuat metric:

```text
UI Ready
Session Valid
Auth Aktif
```

pada halaman production final jika itu hanya informasi development.

Metric card maksimum:

```text
4–5 indikator utama
```

di satu baris desktop.

Mobile:

```text
2 kolom bila terbaca
atau 1 kolom jika label panjang
```

---

# 29. CHART.JS

Chart hanya digunakan untuk insight yang lebih mudah dibaca visual daripada table.

Area potensial:

- Dashboard;
- Analisis Soal;
- ringkasan hasil;
- statistik operasional non-kritis.

Aturan:

- Chart.js tidak dimuat global;
- maksimal beberapa chart yang benar-benar berguna per halaman;
- chart bukan sumber data resmi;
- angka penting tetap tersedia dalam text/table;
- Adaptive Load Protection boleh menunda/disable refresh chart lebih dulu;
- jangan polling chart agresif saat ujian berlangsung.

---

# 30. SORTABLEJS

Dipakai untuk reorder yang memang membutuhkan drag-and-drop:

- urutan soal;
- urutan section/template;
- urutan item tertentu.

Aturan:

- tampilkan drag handle;
- tidak seluruh row menjadi drag target;
- simpan order setelah perubahan yang valid;
- optimistic UI boleh, tetapi server tetap authority;
- sediakan fallback/action jika drag sulit di mobile;
- jangan gunakan SortableJS untuk table monitoring/data administratif.

---

# 31. IMPORT / STAGING UI

Flow baku:

```text
UPLOAD
→ PARSER
→ IMPORT STAGING
→ VALIDATION
→ PREVIEW
→ FIX / EXCLUDE
→ COMMIT
```

Layout:

```text
Stepper/Status
Summary counts
Filter error/status
Staging table
Detail/error panel
Primary action
```

Staging table:

```text
per_page 50
server-side untuk file besar
```

Filter minimal:

```text
ALL
VALID
INVALID
EXCLUDED
```

Jangan render seluruh workbook ke DOM.

COMMIT:

- hanya data eligible;
- confirmation;
- progress state;
- idempotent;
- hasil commit menampilkan count sukses/gagal.

---

# 32. QUESTION BANK / EDITOR

Bank list memakai table/list biasa.

Question editor bukan DataTables generik.

Pola:

```text
Question list/navigation
→ selected question editor
→ preview
→ validation
```

Jika reorder diperlukan:

```text
SortableJS
```

Preview memakai renderer yang sama secara konseptual dengan client/print.

Media/formula/table harus diuji pada container responsive.

---

# 33. MONITORING UJIAN

Monitoring bukan halaman DataTables biasa yang reload total.

Pola:

```text
Summary
→ Filter
→ Server-side Table
→ Bulk Action / Row Action
```

Default:

```text
per_page = 50
```

Refresh baseline:

```text
5–15 detik
```

nilai exact boleh dituning benchmark.

Aturan refresh:

- pertahankan page/filter/sort;
- update row yang berubah;
- jangan kembali ke page 1 setiap refresh;
- hindari destroy/re-init DataTables setiap polling;
- boleh memakai custom table adapter/delta daripada DataTables bila lebih ringan;
- tab background boleh memperlambat non-kritis;
- Adaptive Load Protection boleh memperpanjang interval;
- answer sync/login/start/submit tidak boleh dikorbankan untuk Monitoring.

Status koneksi seperti `TIDAK TERDETEKSI` harus terlihat jelas tanpa animation berat.

---

# 34. LIVE SCORING PUBLIC

Tidak memakai DataTables UI Manager.

Fullscreen custom:

```text
No | No Peserta | Nama | Skor | Status
```

Tidak ada pagination control.

Behavior:

```text
fetch snapshot
→ rank
→ tampil dari atas
→ auto-scroll SELURUH row
→ setelah row terakhir
→ fetch snapshot baru
→ rerank
→ kembali atas
```

Dataset tidak direfresh di tengah scroll.

---

# 35. HASIL UJIAN

Hasil Ujian:

```text
server-side table
per_page 50
```

Filter potensial:

- Kegiatan;
- jadwal;
- mapel;
- tingkat;
- rombel;
- ruang;
- scoring status;
- final status;
- participant search.

Detail item scoring:

```text
lazy-load
```

jangan load seluruh answer detail untuk setiap row utama.

---

# 36. REKAP NILAI

Rekap dapat berbentuk matrix:

```text
Peserta × Mata Pelajaran
```

Aturan:

- horizontal scroll diperbolehkan;
- identitas peserta boleh sticky;
- header mapel boleh sticky bila diperlukan;
- pagination participant server-side;
- export Excel adalah output utama untuk matrix besar;
- PDF hanya format yang masih terbaca.

Jangan memperkecil font ekstrem untuk memaksa seluruh matrix masuk layar.

---

# 37. ANALISIS SOAL

List item:

```text
server-side / paginated
```

Chart per item hanya bila membantu.

Jangan membuat puluhan Chart.js instance sekaligus dalam satu page.

Detail distribution dapat dibuka lazy/on-demand.

Jika analysis stale:

```text
badge STALE
+ action Rebuild
```

---

# 38. PSYCHOLOGICAL RESULT / MATRIX

Hasil Psikologis:

- table/list server-side untuk participant;
- dimension detail lazy;
- matrix scoring editor memakai table khusus;
- jangan memaksa DataTables untuk matrix input kompleks;
- interpretasi panjang memakai detail page/drawer.

Tidak ada ranking public.

---

# 39. SYSTEM LOG

Default:

```text
per_page 50
sort created_at DESC
server-side
```

Filter:

```text
date range
actor realm
actor
module
action
entity
```

Detail before/after JSON:

```text
drawer/modal detail
```

jangan tampilkan JSON panjang langsung pada row table.

Secret tidak pernah ditampilkan.

---

# 40. BACKUP / RESTORE

Backup list/history bila ada:

```text
plain table / DataTables kecil
```

Action:

```text
Backup Database
Backup Media
Restore Database
Restore Media
```

Restore Admin-only.

Progress/status harus terlihat.

Download button hanya aktif setelah artifact READY.

Backup Database dan Backup Media tetap dua workflow terpisah.

---

# 41. SETTINGS

Settings tidak memakai DataTables kecuali subbagian yang memang berupa list.

Layout:

```text
sectioned form
```

Secret settings:

- tidak ditampilkan plaintext;
- tidak memakai eye-toggle;
- credential encryption key tidak masuk form normal.

Save per section bila lebih aman daripada satu form raksasa.

---

# 42. DATE / TIME DISPLAY

Timezone aplikasi mengikuti konfigurasi:

```text
Asia/Jakarta
```

Display Manager yang disarankan:

```text
25 Sep 2026 13:45
```

Jika seconds tidak dibutuhkan, jangan tampilkan.

Untuk log/monitoring yang memerlukan presisi:

```text
25 Sep 2026 13:45:22
```

Duration tampil human-readable:

```text
01:28:14
```

bukan detik mentah.

---

# 43. NUMBER FORMAT

Score display:

```text
2 decimal
```

contoh:

```text
85.00
```

Count:

```text
1.500
```

UI boleh mengikuti locale Indonesia.

Data API tetap numerik, bukan string formatted.

---

# 44. MOBILE TABLE

Urutan fallback:

1. sembunyikan kolom sekunder;
2. horizontal scroll;
3. compact record layout hanya jika table sudah tidak usable.

Jangan otomatis mengubah semua table menjadi card.

Kolom aksi tetap dapat dijangkau.

Bulk checkbox pada mobile hanya ditampilkan bila workflow bulk memang masuk akal di mobile.

---

# 45. LOADING & REQUEST CONTROL

Untuk AJAX data:

- abort request lama jika filter/search baru menggantikannya;
- jangan biarkan response lama menimpa state terbaru;
- tombol command disabled selama request;
- request idempotent diulang aman sesuai API contract.

Search/filter boleh memakai `AbortController`.

Monitoring polling tidak boleh membuat request overlap.

Jika request sebelumnya belum selesai:

```text
skip tick berikutnya
```

bukan menumpuk request.

---

# 46. PAGE REFRESH & STATE

Normal Manager CRUD tidak perlu full browser refresh setelah setiap aksi.

Prefer:

```text
API mutation
→ feedback
→ reload data table/section
```

Full navigation dipakai ketika:

- berpindah page utama;
- workflow kompleks;
- lifecycle transition membawa user ke context baru.

Filter state boleh disimpan pada URL query bila berguna untuk bookmark/share internal.

---

# 47. ACCESSIBILITY

Minimum:

- keyboard focus terlihat;
- label form eksplisit;
- button icon-only punya accessible name;
- status tidak warna-only;
- modal/offcanvas focus behavior benar;
- `aria-expanded` pada collapse/toggle;
- table header menggunakan semantic `<th>`;
- disabled visual bukan satu-satunya authority;
- browser zoom tidak dikunci.

---

# 48. FRONTEND LIBRARY LOAD POLICY

Global shared Manager:

```text
Bootstrap
Bootstrap Icons
CBT-HERO theme
Manager shell
```

Load per halaman:

```text
DataTables   → page table interaktif
SweetAlert2  → confirmation/toast yang perlu
Chart.js     → chart
SortableJS   → reorder/editor
```

Participant/exam:

```text
Dexie
DOMPurify
KaTeX
Panzoom
```

hanya saat dibutuhkan.

Tidak semua vendor dimuat global.

Tidak memakai:

```text
jQuery
React
Vue
Angular
Axios
```

---

# 49. DASHBOARD

Dashboard final hanya menampilkan informasi yang operasional.

Hapus metric development seperti:

```text
AUTH Aktif
UI SHELL Ready
Session Valid
```

setelah modul nyata tersedia.

Dashboard potensial:

- Kegiatan aktif;
- jadwal hari ini;
- peserta terdaftar;
- exam berjalan;
- status preparation;
- quick operational warning.

Chart hanya jika data sudah bermakna.

---

# 50. PARTICIPANT LIST / DAFTAR UJIAN

Participant tidak memakai DataTables.

Daftar ujian:

- card/list;
- jumlah item kecil sesuai membership participant;
- status jelas;
- primary action jelas.

State:

```text
BELUM DIBUKA
BISA DIMULAI
LANJUTKAN
SELESAI
```

---

# 51. PARTICIPANT WORKSPACE

Tidak memakai pagination server per soal.

Question package sudah prepared dan cache lokal.

Navigasi:

```text
local
```

Palette:

```text
desktop → panel kanan
mobile  → bottom sheet
```

Tidak membuat request server per pindah nomor soal.

---

# 52. PRINT UI

Print route/page memiliki stylesheet khusus.

Kartu Peserta:

```text
A4
2 kolom × 5 baris
10 kartu per lembar
```

Credential:

```text
NOMOR PESERTA
USERNAME
PASSWORD
```

memakai JetBrains Mono SemiBold/Bold dan lebih besar dari metadata biasa.

Print tidak membawa sidebar/topbar.

---

# 53. COMPONENT MATRIX PER MODUL

| Modul | Main Pattern | Data Handling |
|---|---|---|
| Dashboard | Summary + warning + quick action | selective |
| Rombel | Table/Form | small |
| Mata Pelajaran | Table/Form | small |
| Peserta | DataTables | server-side 50 |
| User Manager | Table/Form | small |
| Kegiatan | Table/Detail | 25 |
| Peserta Ujian | DataTables + bulk | server-side 50 |
| Ruang | Table/Form | small |
| Bank Soal | Table + Editor | hybrid |
| Import | Wizard/Staging | server-side 50 |
| Jadwal | Table/Form | 25 |
| Preparation | Status/Command | chunk/progress |
| Token | Operational control | no DataTables |
| Monitoring | Summary + live table | server-side 50 + delta |
| Live Scoring | Public display | full-cycle snapshot |
| Scoring | Table/Editor | server-side/lazy |
| Hasil Ujian | DataTables | server-side 50 |
| Rekap Nilai | Matrix | server-side participant |
| Analisis Soal | Table + selective chart | 50/lazy |
| Psikologis | Table + specialized editor | hybrid |
| Settings | Sectioned form | no DataTables |
| Logs | DataTables | server-side 50 |
| Backup | Status/list | small |
| Clear Data | Dependency wizard | command flow |

---

# 54. PERFORMANCE GUARD

Jangan:

- render ribuan row;
- load semua detail relation per row;
- membuat chart per row;
- polling overlap;
- destroy/recreate table setiap refresh;
- export dari DOM;
- load semua frontend library global;
- memakai animation berat di monitoring;
- menjalankan client-side sort/filter untuk dataset besar.

Manager data endpoint harus menghindari N+1.

---

# 55. STANDARD IMPLEMENTATION FILES

Global:

```text
assets/css/cbt-hero-theme.css
assets/css/manager-shell.css
assets/css/manager-sidebar-patch.css
assets/css/manager-ui-standard.css
```

Library:

```text
assets/vendor/
```

Halaman/modul hanya menambah CSS/JS khusus bila memang domain-specific.

Jika pola dipakai pada dua modul atau lebih:

> pindahkan menjadi shared component/style.

---

# 56. DEFINITION OF UI PASS

Sebuah halaman dianggap PASS jika:

```text
[ ] menggunakan shell/pattern yang benar
[ ] typography sesuai standar
[ ] spacing sesuai scale
[ ] desktop/mobile diuji
[ ] tidak ada overflow tidak sengaja
[ ] loading tersedia
[ ] empty state tersedia
[ ] error state tersedia
[ ] filter/reset jelas
[ ] pagination benar bila diperlukan
[ ] table server-side bila dataset besar
[ ] action hierarchy jelas
[ ] destructive confirmation sesuai rule
[ ] locked/disabled state dapat dimengerti
[ ] permission tetap server-side
[ ] tidak load vendor yang tidak dibutuhkan
[ ] tidak ada console/runtime error
[ ] zoom tidak dikunci
```

Untuk table besar tambahkan:

```text
[ ] search debounce
[ ] sort whitelist server
[ ] per_page limit
[ ] selection scope jelas
[ ] export mengikuti filter server
[ ] refresh tidak mereset context
```

Untuk Monitoring:

```text
[ ] polling tidak overlap
[ ] page/filter tidak hilang saat refresh
[ ] adaptive refresh dapat diterapkan
[ ] critical Exam API tidak terganggu
```

---

# 57. KEPUTUSAN FINAL

Standar Manager CBT-HERO memakai pola:

```text
Manager Shell
+ reusable toolbar
+ reusable filter
+ server-side table untuk dataset besar
+ pagination default 50
+ search debounce
+ controlled bulk action
+ shared status badge
+ modal/drawer sesuai kompleksitas
+ library load on demand
```

Tujuan akhirnya:

> **Satu aplikasi, satu bahasa visual, satu pola operasional.**

Halaman berikutnya tidak mendesain ulang table, pagination, filter, modal, card, atau responsive behavior dari nol.
