# Phase 3E2 — Kartu Ujian

Kartu dapat dicetak dari Master Ujian → Kegiatan Ujian → **Kartu** atau dari halaman Peserta Ujian → **Cetak Kartu Ujian**. Tidak ada migrasi SQL. Tampilan cetak A4 memuat 10 kartu per lembar (2 kolom × 5 baris). Setiap batch memuat maksimal 100 peserta; pindah batch untuk mencetak sisanya. Pada dialog cetak browser, gunakan A4, skala 100%, tanpa margin dan matikan header/footer; **Simpan sebagai PDF** tersedia dari dialog tersebut.

| ID | Langkah | Hasil yang diharapkan |
| --- | --- | --- |
| K01 | Siapkan Kegiatan dengan anggota, nomor peserta, ruang, identitas kartu, serta username dan password cetak. Buka tombol Kartu. | Nama Kegiatan dan identitas instansi sesuai pengaturan; tiap kartu memuat nama, rombel, ruang, nomor, username, password dan URL CBT. Password hanya ditampilkan pada halaman cetak yang dilindungi login Manager. |
| K02 | Pilih cakupan Rombel, lalu Ruang (termasuk Tanpa Ruang). | Hanya anggota Kegiatan dalam cakupan yang ditampilkan. Nilai filter dan batch tetap saat navigasi batch. |
| K03 | Gunakan dialog cetak untuk melihat pratinjau A4. | Satu lembar berisi maksimal 10 kartu dengan susunan 2×5; bagian filter dan tombol tidak ikut tercetak. Lembar terakhir hanya memuat sisa kartu. |
| K04 | Siapkan lebih dari 100 anggota dalam satu Kegiatan. | Batch pertama berisi maksimal 100 kartu, tombol batch berikutnya menampilkan sisanya tanpa memuat semua credential sekaligus. |
| K05 | Coba anggota tanpa nomor, ruang, atau password cetak. | Peringatan jumlah kartu yang belum lengkap tampil; bagian kosong ditandai “Belum tersedia” tanpa membuka ciphertext/key. Lengkapi data sebelum membagikan kartu. |
| K06 | Buka URL kartu setelah logout Manager atau sebagai pengguna tanpa izin Master Ujian. | Halaman ditolak oleh filter akses server; credential tidak dapat diambil. Respons cetak memakai `Cache-Control: no-store, private`. |
| K07 | Ubah identitas/logo melalui Sistem → Pengaturan, lalu muat ulang kartu. | Tampilan mengikuti pengaturan terbaru; data Kegiatan dan keanggotaan tidak berubah. |

Sesi pada kartu tertulis **Ikuti jadwal ujian**. Modul Sesi/Jadwal belum tersedia pada Phase 3, sehingga kartu tidak mengklaim nomor sesi yang belum ditetapkan. Nomor peserta dan ruang dibaca dari keanggotaan Kegiatan; username/password dari akun Peserta saat halaman cetak dibuka. Setelah reset password, cetak ulang kartu.
