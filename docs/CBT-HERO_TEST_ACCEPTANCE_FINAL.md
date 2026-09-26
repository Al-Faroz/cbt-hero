# CBT-HERO — Test & Acceptance

**Versi:** 1.2 · **Fokus saat ini:** Revisi konteks Tahun/Semester setelah Phase 1F  
**Acuan:** `CBT-HERO_DOKUMEN_ACUAN_UTAMA.md`, `CBT-HERO_AUTH_SECURITY_SESSION_FINAL.md`, `CBT-HERO_IMPLEMENTATION_SPEC_ROADMAP_FINAL.md`  
**Status:** checklist eksekusi; Phase 1F menjadi FIX setelah pengujian localhost PASS.

**Pola acceptance:** satu submodul → uji tampilan dan fungsi yang tersedia →
PASS/FIX → push → submodul berikutnya. Setelah semua submodul dalam kelompok
selesai, uji integrasi antarmodul sebelum pindah kelompok. Skenario yang
memerlukan modul atau data masa depan dicatat untuk gerbang integrasi, bukan
syarat PASS submodul saat ini. Settings dasar boleh dimajukan bila menjadi
prasyarat Kegiatan atau Kartu; nilai dan hasil cetaknya diuji bersama modul
pemakai.

## 1. Persiapan Phase 1F

1. Extract patch di `G:\xampp\htdocs\cbt-hero\` dengan struktur folder tetap. Tidak perlu import ulang schema.
2. `.env` localhost Anda sudah menunjuk database `cbt_hero` dan menetapkan `session.driver = 'CodeIgniter\Session\Handlers\DatabaseHandler'`, `session.savePath = 'ci_sessions'`, serta `session.cookieName = 'cbt_hero_session'`. Pertahankan pengaturan tersebut. Patch menyamakan default `Config\Session` dengan konfigurasi yang sudah aktif.
3. Siapkan satu ADMIN, satu OPERATOR, satu PESERTA `ACTIVE`, dan akun uji `INACTIVE` untuk tiap realm. Gunakan akun uji khusus untuk pengujian lockout. Jangan menguji lockout dengan akun operasional.
4. Gunakan browser yang sama untuk uji isolasi dua realm. Gunakan mode privat atau hapus cookie `cbt_hero_session` untuk memulai skenario baru.
5. Setelah perubahan handler session, login ulang. Session file lama tidak otomatis dipindahkan ke tabel DB.

**Batas fase:** Phase 1F tidak membuat Attempt, START/RESUME, token validation, answer sync, maupun Exam Browser. Tombol START pada halaman Konfirmasi tetap disabled.

## 2. Pemeriksaan cepat

Jalankan dari root proyek melalui terminal XAMPP dengan PHP yang tersedia:

```powershell
php spark routes
```

Pastikan route yang ada mencakup `/`, `/ujian`, `/ujian/{id}/konfirmasi`, `/api/auth/*`, `/api/ujian*`, `/manager`, `/manager/dashboard`, `/manager/system/users`, dan `/manager/api/*` dengan metode HTTP sesuai `app/Config/Routes.php`. Route otomatis harus nonaktif.

Setelah membuka halaman login, cek database:

```sql
SELECT id, ip_address, timestamp
FROM ci_sessions
ORDER BY timestamp DESC
LIMIT 5;
```

Harus ada row baru. Jangan menampilkan kolom `data` karena berisi state session. Jika tabel tetap kosong, periksa koneksi database. Cookie `cbt_hero_session` memakai path `/cbt-hero/`, `HttpOnly`, dan `SameSite=Lax`. Prefix cookie umum `cbthero_` tidak ditambahkan oleh CI4 pada cookie session. Di localhost HTTP, `Secure=false`; pada deployment HTTPS, set `cookie.secure=true`.

## 3. Matriks acceptance Phase 1F

Catat `PASS/FAIL`, HTTP status, dan temuan pada setiap baris. Bersihkan lockout akun uji sebelum beralih ke kasus lain.

| ID | Langkah | Hasil wajib |
| --- | --- | --- |
| A01 | Tanpa login akses `/manager/dashboard`, `/manager/system/users`, `/ujian`, `/ujian/123/konfirmasi` | UI dialihkan ke landing realm masing-masing. |
| A02 | Tanpa login akses `/manager/api/auth/session`, `/manager/api/users`, `/api/auth/session`, `/api/ujian`, `/api/ujian/123/konfirmasi` | HTTP 401 JSON `AUTH_REQUIRED`, bukan HTML login. |
| A03 | Login ADMIN dengan username huruf kecil; cek dashboard dan `/manager/api/auth/session` | Login sukses, username normal uppercase, session terisi, ID cookie berubah sesudah login. |
| A04 | Login PESERTA dengan username huruf kecil; cek `/ujian` dan `/api/auth/session` | Login sukses, username normal uppercase, ID cookie berubah; daftar berasal dari DB. |
| A05 | Salah password pada akun uji Manager sebanyak 5 kali; ulangi password benar sebelum 10 menit | Percobaan ke-5 dan login berikutnya HTTP 429; counter/audit sesuai. Setelah masa lock habis, login benar sukses dan counter kembali 0. |
| A06 | Salah password pada akun uji Peserta sebanyak 10 kali; ulangi password benar sebelum 5 menit | Percobaan ke-10 dan login berikutnya HTTP 429; setelah masa lock habis, login benar sukses dan counter kembali 0. |
| A07 | Coba login akun INACTIVE pada dua realm | Ditolak; tidak membuat state login. Sesudah akun yang sedang login dinonaktifkan, request protected berikutnya ditolak dan state realm itu dihapus. |
| A08 | Login ADMIN lalu PESERTA dalam browser yang sama | Kedua `/manager/api/auth/session` dan `/api/auth/session` sukses; login realm kedua tidak menghapus realm pertama. |
| A09 | Dengan kedua realm login, logout PESERTA; cek kedua session endpoint; kemudian login PESERTA lagi dan logout MANAGER | Logout hanya memutus realm terkait; realm lain tetap sukses. Session ID berubah setelah logout. |
| A10 | Login OPERATOR, akses `/manager/system/users`, `GET /manager/api/users`, `POST /manager/api/users` dengan CSRF valid | UI dan kedua endpoint User Manager HTTP 403; tidak ada account baru. ADMIN boleh mengakses. |
| A11 | Dengan session ADMIN, ubah role akun uji di DB dari ADMIN ke OPERATOR; ulangi akses User Manager | Akses langsung HTTP 403 tanpa login ulang. Kembalikan role akun uji sesudah tes. |
| A12 | POST login/logout atau create user tanpa `X-CSRF-TOKEN`, lalu dengan token salah; bandingkan dengan token benar | Mutation tanpa token/bertoken salah ditolak (HTTP 403) dan tidak mengubah state. Request valid diproses. GET tidak memerlukan token. |
| A13 | Review source `ParticipantExamController`, `ExamDiscoveryController`, dan `ParticipantExamDiscoveryService`; saat login Peserta coba ID jadwal yang tidak ada pada kedua URL konfirmasi | Kedua controller mengambil `peserta_id` dari session, bukan dari input request; `confirmationData()` mencari ID jadwal hanya pada `listForParticipant()` dan mengembalikan null jika tidak ada. ID yang tidak ada memberi UI 404 dan API 404 JSON. Ini pemeriksaan boundary awal, **belum membuktikan IDOR antar peserta**; uji R13 wajib saat data jadwal tersedia. |
| A14 | Login MANAGER saja lalu akses `/api/ujian`; login PESERTA saja lalu akses `/manager/api/users` | Keduanya HTTP 401 JSON; satu realm tidak menjadi authority realm lain. |
| A15 | Setelah login, hapus cookie `cbt_hero_session` atau tunggu session kedaluwarsa; ulangi endpoint protected | UI menuju login terkait, API HTTP 401 JSON; tidak ada loop redirect atau error SQL. |
| A16 | Pada login gagal dan logout, cek `auth_login_attempts` dan `audit_logs` | Realm, alasan gagal, dan login/logout Manager tercatat sesuai implementasi; response API tidak membocorkan hash/password atau exception SQL. |

### Catatan eksekusi

- Gunakan DevTools → Network untuk status HTTP, response JSON, `Set-Cookie`, dan token CSRF di `<meta name="csrf-token">` pada halaman login/shell. Untuk request JSON via `fetch`, kirim header `X-CSRF-TOKEN` dan `Content-Type: application/json` pada same origin.
- Uji A11 memakai akun ADMIN **uji**, bukan satu-satunya ADMIN. Kembalikan status/role setelah tes; perubahan langsung di DB hanya untuk simulasi revalidasi session.
- A13 memakai source review dan ID yang tidak ada karena data ujian belum tersedia. ID yang tidak ada **bukan** pengganti uji kepemilikan jadwal. Uji R13 di bawah tetap wajib sebelum fitur ujian dianggap siap.
- `auth_login_attempts` mencatat kegagalan login; `audit_logs` mencatat login/logout Manager. Jangan mengharapkan audit logout Peserta bila belum ditetapkan di fase ini.
- Phase 1E hanya menghitung status discovery untuk tampilan. START/RESUME harus melakukan pemeriksaan ulang secara authoritative pada Attempt Engine nanti, termasuk prepared assignment yang stale.

## 4. Kriteria PASS Phase 1F

Semua A01–A16 PASS sesuai metode pada tabel (A13 mencakup source review dan 404 untuk ID yang tidak ada), `ci_sessions` aktif sebagai penyimpanan session, dua auth realm terpisah, dan tidak ada mutation tanpa CSRF. **Jangan mengklaim uji IDOR antar peserta sudah PASS pada Phase 1F.** Catat R13 sebagai `DEFERRED — belum ada jadwal`, lalu jalankan setelah data Jadwal/Peserta Kegiatan tersedia. Jika gagal, lampirkan ID kasus, status HTTP, response ringkas, dan log error terkait tanpa kredensial. Setelah Phase 1F PASS, push revisi dokumen; audit commit sebelum memulai Phase 2.

## 5. Acceptance lanjutan

**R13 — IDOR jadwal (wajib ketika modul Jadwal dan Peserta Kegiatan siap):** buat Peserta A dan B dengan membership/jadwal yang terpisah. Login sebagai A, lalu panggil UI dan API konfirmasi dengan ID jadwal nyata milik B; wajib UI 404 dan API 404 JSON tanpa data B. Ulangi pada jadwal SUSULAN yang menarget B tetapi tidak menarget A. Pastikan jadwal milik A tetap dapat diakses. ID jadwal yang tidak ada tidak memenuhi R13. START/RESUME kelak memerlukan uji ownership authoritative tersendiri.

Setiap subphase setelah Phase 1F menambah kasus pengujian ke dokumen ini: Master Data (CRUD/import/credential), Kegiatan, Bank, Jadwal dan Prepared Assignment, Attempt/Answer/Timer, Monitoring, Scoring/Hasil, Psikologis, Backup/Restore, dan performance. Target load final tetap 500/1.000/1.500/2.000 concurrent; stress 2.500, stretch 3.000 jika lingkungan memungkinkan. Kasus masa depan belum dianggap PASS oleh dokumen Phase 1F ini.

## 6. Revisi rancangan Phase 2A — Konteks Tahun/Semester

Master Periode yang sempat diuji tidak diteruskan. Tahun Pelajaran/Semester akan menjadi default pengisian di Settings dan disalin ke setiap Kegiatan. UI dan API Settings/Kegiatan dibangun pada fase terkait; revisi ini menyelaraskan acuan, schema, dan database lama.

1. Buat backup database lokal. Extract patch revisi ke root proyek, lalu jalankan `tools/phase2a_remove_periode.ps1` dari root proyek agar enam file modul lama terhapus. Route, sidebar, dan trait lama telah diganti melalui patch.
2. Untuk database yang sudah diimport dari schema lama, jalankan `tools/phase2a_context_migration.sql` **sekali**. Pastikan `kegiatan_tanpa_konteks = 0` pada SELECT sebelum melanjutkan DROP. Jika query gagal, hentikan migrasi dan periksa backup/database. Database kosong yang baru dibuat memakai schema revisi dan tidak memerlukan migrasi.
3. Periksa `SHOW COLUMNS FROM kegiatan`: harus ada `tahun_pelajaran` dan `semester`, tidak ada `periode_id`. Periksa `SHOW TABLES LIKE 'periode'`: kosong. Bila ada Kegiatan lama, bandingkan Tahun/Semester dengan data sebelum migrasi.
4. Periksa `/manager/master-data/periode` dan `/manager/api/periode`: route tersebut tidak lagi tersedia. Pastikan menu Periode hilang dan fungsi login, dashboard, User Manager tetap berjalan.

**Catatan:** Uji CRUD Periode P2A lama membuktikan implementasi sementara saat itu, tetapi tidak lagi menjadi acceptance desain akhir. Pengujian default Settings, validasi Tahun/Semester pada Kegiatan, dan ketahanan riwayat ketika default berubah dilakukan saat fitur tersebut diimplementasikan.

## 7. Phase 2B — Rombel (uji yang dapat dilakukan sekarang)

Gunakan halaman `/manager/master-data/rombel` dengan akun Admin dan Operator. Cukup dua atau tiga Rombel uji yang belum dipakai Peserta. Tidak perlu membuat row Peserta, mengisi puluhan Rombel, menjalankan SQL manual, atau mengirim request API buatan untuk memperoleh PASS pada fase ini.

| ID | Langkah di halaman Rombel | Hasil yang diharapkan |
| --- | --- | --- |
| B01 | Login Admin, buka menu **Master Data → Rombel**. Ulangi dengan Operator. | Halaman dan tabel tampil tanpa error untuk kedua role. |
| B02 | Tambah tingkat `7` kode `A`, lalu tingkat `8` kode `A`. | Muncul dua row berbeda dengan nama otomatis `7-A` dan `8-A`. |
| B03 | Coba tambah tingkat `7` kode `a` lagi. | Muncul pesan Rombel sudah ada; tidak tercipta row duplikat. |
| B04 | Edit `8-A` menjadi `8-R`. Masuk mode edit sekali lagi lalu tekan **Batal Edit**. | Perubahan pertama tersimpan sebagai `8-R`; pembatalan tidak mengubah data. |
| B05 | Cari `7-A`, pilih filter tingkat `8`, lalu reset filter. | Daftar mengikuti pencarian/filter dan kembali lengkap sesudah reset. Tidak perlu menguji halaman kedua pagination karena data uji sedikit. |
| B06 | Nonaktifkan `7-A`, lalu aktifkan lagi. | Label status berubah sesuai tombol dan tetap benar setelah refresh halaman. |
| B07 | Pada `8-R` yang belum dipakai Peserta, klik **Hapus** dan pilih **Batal** pada dialog; klik lagi dan setujui. | Pembatalan mempertahankan row; persetujuan menghapus row. |
| B08 | Cek tampilan pada lebar desktop dan mobile yang tersedia. | Form, tabel, dan tombol dapat digunakan; tabel dapat digulir horizontal bila layar sempit. |

**PASS Phase 2B:** B01–B08 berhasil tanpa error PHP. Rombel `7-A` boleh dihapus setelah pengujian atau disimpan sebagai data awal jika memang akan dipakai.

**Uji lanjutan, bukan syarat PASS sekarang:** penolakan hapus Rombel yang dipakai Peserta diuji pada Phase 2C ketika UI/data Peserta tersedia; pagination ke halaman kedua diuji bila jumlah Rombel nyata melebihi satu halaman. Pengujian request API manual untuk payload tidak valid, ID tidak ada, sort tidak dikenal, dan CSRF dapat ditambahkan dalam pengujian integrasi. Proteksi auth/CSRF dasar sudah diuji pada Phase 1F.

## 8. Gerbang integrasi Master Data (setelah semua submodul selesai)

Uji alur Rombel → Peserta → import dan akun Peserta → Mata Pelajaran dengan
data nyata yang dibuat melalui UI. Di tahap ini verifikasi referensi Peserta ke
Rombel, penolakan penghapusan Rombel yang dipakai, status Rombel pada pilihan
Peserta baru, konsistensi impor, permission Admin/Operator, dan pagination
sesuai volume data yang benar-benar tersedia. Gerbang ini belum dijalankan
pada Phase 2B.

## 9. Phase 2C — Data inti Peserta (uji submodul)

Prasyarat: setidaknya satu Rombel `ACTIVE` dari Phase 2B. Pengujian ini hanya menggunakan halaman **Master Data → Peserta** dan Rombel yang sudah tersedia. Belum perlu Account Login, import Excel, Kegiatan, ataupun data ujian.

| ID | Langkah di UI | Hasil yang diharapkan |
| --- | --- | --- |
| C01 | Login Admin dan Operator secara bergantian, buka halaman Peserta. | Halaman tampil dan pilihan Rombel aktif tersedia tanpa error. |
| C02 | Tambah Peserta uji dengan NISN angka, nama, jenis kelamin L/P, Rombel aktif, serta keterangan opsional. | Row tampil dengan NISN, nama, JK, Rombel, dan status Aktif yang sesuai. Tidak perlu credential. |
| C03 | Tambah lagi dengan NISN yang sama. | Muncul pesan NISN sudah digunakan; hanya satu row tersimpan. |
| C04 | Edit nama/keterangan Peserta uji; buka edit lagi lalu klik **Batal Edit**. | Perubahan pertama tersimpan; pembatalan tidak mengubah data. |
| C05 | Cari NISN/nama, pilih filter Rombel dan status, lalu reset. | Daftar mengikuti filter dan kembali lengkap setelah reset. Tidak perlu membuat 51 row untuk memicu halaman kedua. |
| C06 | Nonaktifkan Peserta uji lalu aktifkan lagi. | Status berubah dan tetap benar setelah halaman di-refresh. |
| C07 | Buka halaman pada desktop dan mobile. | Form dan tabel dapat digunakan; tabel dapat digulir horizontal pada mobile. |

**PASS Phase 2C:** C01–C07 berhasil tanpa error PHP. Data Peserta uji dapat dibiarkan untuk langkah Account Login/Import berikutnya. Data yang belum diberi credential tetap berstatus `PENDING`; login Peserta belum dapat diuji dengan row baru ini.

**Gerbang integrasi nanti:** penolakan edit Peserta yang terikat Kegiatan `BERJALAN`, impor Peserta, credential, dan hubungan dengan Kartu/Ujian diuji setelah fitur yang memakainya tersedia. Uji pagination lintas halaman hanya bila volume data nyata cukup. Tidak ada DELETE Peserta pada langkah ini.
