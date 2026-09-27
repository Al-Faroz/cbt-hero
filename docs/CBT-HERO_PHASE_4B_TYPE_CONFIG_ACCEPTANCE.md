# Phase 4B — Komposisi Tipe Bank Soal

Tahap ini mengatur enam tipe teknis pada Bank Soal Akademik DRAFT melalui tombol **Komposisi** di daftar Bank Soal. Tidak ada migrasi SQL. Satu baris konfigurasi tersimpan per tipe yang dicentang pada `bank_type_config`. Status READY belum tersedia sampai editor dan validasi soal selesai.

| ID | Langkah | Hasil yang diharapkan |
| --- | --- | --- |
| T01 | Buat Bank kosong DRAFT, buka Komposisi. Centang PG dan Isian Singkat; set jumlah dipilih 20 dan 5, bobot 80 dan 20, acak soal/opsi hanya pada PG. Simpan dan reload. | Dua tipe dan pengaturannya tetap tersimpan; total bobot 100%. Bank tetap DRAFT. |
| T02 | Centang Matching, pilih mode Per pasangan atau Semua benar, atur jumlah dan bobot sehingga total ≤100. | Mode Matching tersimpan. Tipe Isian Singkat/Uraian tidak menyediakan pengacakan. |
| T03 | Masukkan bobot yang totalnya lebih dari 100, jumlah 0, atau bobot dengan lebih dari tiga angka desimal. | Server menolak 422; konfigurasi sebelumnya tidak berubah. |
| T04 | Melalui API, kirim tipe duplikat, tipe yang tidak dikenal, mode Matching pada PG, atau pengacakan Isian Singkat. | Server menolak 422; konfigurasi sebelumnya tidak berubah. |
| T05 | Buka Komposisi di dua tab. Simpan perubahan di tab pertama, kemudian coba simpan tab kedua tanpa reload. | Tab kedua ditolak 409 dan diminta memuat ulang; perubahan tab pertama bertahan. |
| T06 | Matikan centang satu tipe pada Bank DRAFT yang belum berisi soal, simpan, kemudian buka lagi. | Tipe tersebut hilang dari konfigurasi. Konfigurasi tipe lain tetap ada. |
| T07 | Logout, lalu buka URL Komposisi atau API langsung. | Filter Manager menolak akses. |

Pada DRAFT bobot boleh di bawah 100 agar operator dapat menyusun Bank bertahap. Jumlah soal terpilih boleh melebihi soal yang saat ini ada karena editor soal belum tersedia; ketepatan jumlah dan bobot 100% menjadi syarat READY di tahap berikutnya. Jangan menguji penguncian BERJALAN atau penghapusan tipe yang berisi soal sebelum lifecycle dan editor tersedia.
