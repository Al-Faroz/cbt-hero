# Phase 4B — Komposisi Tipe Bank Soal

Tahap ini mengatur enam tipe teknis pada Bank Soal Akademik DRAFT melalui tombol **Komposisi** di daftar Bank Soal. Satu baris konfigurasi tersimpan per tipe yang dicentang pada `bank_type_config`. Untuk database yang dibuat sebelum revisi jumlah soal Bank, jalankan `CBT-HERO_BANK_COMPOSITION_UPGRADE.sql` satu kali sebelum menguji halaman ini.

Urutan tampilan: grup **Pilihan Ganda / Klik** (Pilihan Ganda, PG Kompleks, PG Bertingkat, Menjodohkan), kemudian grup **Isian & Uraian / Ketik** (Isian Singkat, Uraian). Pengelompokan ini hanya mengubah tampilan; kode keenam tipe dan data tersimpan tetap sama.

| ID | Langkah | Hasil yang diharapkan |
| --- | --- | --- |
| T01 | Buat Bank kosong DRAFT, buka Komposisi. Centang PG dan Isian Singkat; set jumlah soal Bank 20 dan 5, jumlah pilihan PG 4, bobot 80 dan 20, acak soal/opsi hanya pada PG. Simpan dan reload. | Dua tipe dan pengaturannya tetap tersimpan; total bobot 100%. Bank tetap DRAFT. |
| T02 | Centang Matching, pilih mode Per pasangan atau Semua benar, atur jumlah dan bobot sehingga total ≤100. | Mode Matching tersimpan. Tipe Isian Singkat/Uraian tidak menyediakan pengacakan. |
| T03 | Masukkan bobot yang totalnya lebih dari 100, jumlah soal 0, jumlah pilihan PG 1 atau 7, atau bobot dengan lebih dari tiga angka desimal. | Server menolak 422; konfigurasi sebelumnya tidak berubah. |
| T04 | Melalui API, kirim tipe duplikat, tipe yang tidak dikenal, mode Matching pada PG, atau pengacakan Isian Singkat. | Server menolak 422; konfigurasi sebelumnya tidak berubah. |
| T05 | Buka Komposisi di dua tab. Simpan perubahan di tab pertama, kemudian coba simpan tab kedua tanpa reload. | Tab kedua ditolak 409 dan diminta memuat ulang; perubahan tab pertama bertahan. |
| T06 | Matikan centang satu tipe pada Bank DRAFT yang belum berisi soal, simpan, kemudian buka lagi. | Tipe tersebut hilang dari konfigurasi. Konfigurasi tipe lain tetap ada. |
| T07 | Logout, lalu buka URL Komposisi atau API langsung. | Filter Manager menolak akses. |

Pada DRAFT bobot boleh di bawah 100 agar operator dapat menyusun Bank bertahap. Jumlah soal Bank adalah rencana pembuatan soal, bukan jumlah yang diambil Jadwal. Saat READY, jumlah soal aktif tiap tipe harus tepat sama dengan rencana dan bobot 100%. Pengambilan soal per tipe di Jadwal dikerjakan pada modul Jadwal. Jangan menguji penguncian BERJALAN sebelum lifecycle tersedia.
