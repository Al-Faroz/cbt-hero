# Phase 3D — Nomor Peserta

Tahap ini memakai `peserta_kegiatan.nomor_peserta` dan indeks unik `(kegiatan_id, nomor_peserta)` yang sudah ada pada database Phase 3B. **Tidak ada SQL migrasi.** Siapkan satu Kegiatan yang belum pernah START dengan beberapa anggota dari dua Rombel. Format hasil adalah `PREFIX-0001` (minimal empat digit; angka lebih besar tidak dipotong). Urutannya rombel snapshot, nama snapshot, lalu ID anggota.

| ID | Langkah | Hasil yang diharapkan |
| --- | --- | --- |
| N01 | Buka **Kegiatan Ujian → Peserta Ujian**, isi prefix `UJIAN26`, nomor awal `1`, tindakan **Isi yang kosong**, cakupan **Semua anggota**. Jalankan dan setujui konfirmasi. | Seluruh anggota tanpa nomor mendapat `UJIAN26-0001`, `UJIAN26-0002`, dan seterusnya; jumlah yang belum bernomor menjadi nol. |
| N02 | Jalankan kembali tindakan **Isi yang kosong** dengan prefix dan nomor awal yang sama. | Permintaan ditolak karena tidak ada nomor kosong; nomor yang sudah ada tidak berubah. |
| N03 | Tambahkan dua anggota baru, ulangi **Isi yang kosong** mulai dari `1`. | Hanya anggota baru mendapat nomor; generator melewati nomor yang sudah terpakai dalam Kegiatan. |
| N04 | Centang satu atau dua anggota pada halaman aktif, pilih **Anggota terpilih**, tindakan **Regenerate cakupan**, prefix `SUSULAN`, mulai `10`. | Hanya pilihan tersebut berubah menjadi `SUSULAN-0010` dan seterusnya. Nomor anggota lain tidak berubah. |
| N05 | Pilih cakupan **Rombel** lalu **Tingkat**, isi nomor kosong atau regenerate dengan prefix lain. | Hanya anggota pada snapshot Rombel/Tingkat yang dipilih berubah. Semua ID masih unik dalam Kegiatan. |
| N06 | Coba prefix kosong, prefix dengan spasi/strip akhir, nomor awal `0`, atau cakupan Anggota terpilih tanpa mencentang anggota. | Validasi menolak sebelum penyimpanan; seluruh nomor lama tetap ada. |
| N07 | Buat Kegiatan yang belum pernah START kedua dengan anggota yang sama, lalu generate menggunakan prefix dan nomor awal dari N01. | Nomor boleh sama di Kegiatan berbeda; nomor anggota Kegiatan pertama tidak berubah. |
| N08 | Muat ulang Peserta Ujian dan periksa username anggota yang sama pada Master Data Peserta. | Nomor Peserta tetap tersimpan; username Master Peserta tidak berubah. |

Peringatan regenerasi menyebut bahwa kartu yang sudah dicetak perlu diperbarui. Uji penolakan dilakukan setelah dependency pelaksanaan/first START terbentuk; status Kegiatan tidak menjadi gate.
