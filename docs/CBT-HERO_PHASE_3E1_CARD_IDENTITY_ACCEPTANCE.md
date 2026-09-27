# Phase 3E1 — Identitas Kartu Ujian

Tahap ini memasang pengaturan yang diperlukan sebelum membuat layout kartu. Data disimpan sebagai empat key baru di `sys_settings`; **tidak ada migrasi SQL**. Halaman hanya dapat diubah oleh ADMIN melalui Sistem → Pengaturan. Logo kustom berada dalam `writable/uploads/card-logos/` dan tidak dibuka langsung oleh web server.

| ID | Langkah | Hasil yang diharapkan |
| --- | --- | --- |
| I01 | Buka Sistem → Pengaturan. Isi Nama Instansi, Alamat, URL CBT lengkap (`http://localhost/cbt-hero/` pada lokal), lalu simpan tanpa logo. | Nilai tersimpan setelah reload; pratinjau tetap memakai logo bawaan. Default akademik yang sudah ada tetap sama. |
| I02 | Unggah PNG atau JPG madrasah kurang dari 1 MB, lalu simpan. | Pratinjau berubah menjadi logo madrasah; setelah reload gambar tetap tampil. |
| I03 | Coba unggah berkas teks dengan nama `.png`, berkas lebih dari 1 MB, atau gambar berdimensi lebih dari 2000×2000. | Penyimpanan ditolak; identitas sebelumnya tidak berubah. |
| I04 | Coba URL tanpa `http(s)://`, URL dengan kredensial, atau nama/alamat kosong. | Validasi menolak; perubahan sebelumnya tetap ada. |
| I05 | Centang **Gunakan kembali logo bawaan CBT-HERO**, lalu simpan. | Pratinjau kembali ke logo bawaan; nama, alamat, dan URL CBT tetap tersimpan. |
| I06 | Cek key melalui phpMyAdmin dengan query aman di bawah. | Hanya empat key identitas kartu yang baru/berubah pada pengujian ini; `credential_encryption_key` tetap `is_secret=1` dan nilainya tidak ditampilkan. |
| I07 | Buka endpoint `manager/api/settings/card-identity` sebagai ADMIN dan periksa respons JSON. | API menampilkan identitas dan URL logo, tanpa `credential_encryption_key` atau konten file lokal. |

```sql
SELECT setting_key,
       CASE WHEN is_secret = 1 THEN '[RAHASIA]' ELSE LEFT(setting_value, 120) END AS nilai_aman,
       is_secret
FROM sys_settings
WHERE setting_key IN ('card_institution_name', 'card_institution_address',
                      'card_cbt_url', 'card_logo_file', 'credential_encryption_key')
ORDER BY setting_key;
```

Layout/cetak kartu menjadi tahap berikutnya. Identitas ini bisa diubah tanpa mengubah Kegiatan yang sudah tersimpan.
