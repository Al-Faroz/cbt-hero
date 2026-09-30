# CBT-HERO — Participant Renderer + Mobile Acceptance

Tanggal baseline: 2026-09-30

> **Status pengujian: DEFERRED — code-level hardening sudah diimplementasikan, uji browser/device nyata belum PASS.**
> Dokumen ini menjadi checkpoint wajib sebelum Phase 9 Akademik dilanjutkan penuh dan
> wajib diselesaikan sebelum Master Ujian Psikologi.

## Scope

Acceptance ini mencakup workspace peserta untuk enam tipe soal Akademik dan seluruh
rich-content yang didukung:

- PG;
- PG Kompleks;
- PG Bertingkat;
- Menjodohkan;
- Isian Singkat;
- Uraian;
- tabel;
- gambar + zoom/pan;
- rumus;
- audio Google Drive;
- video Google Drive;
- A− / A / A+;
- navigasi, palette, status lokal/sinkron, dan perilaku offline pada mobile.

## Baseline viewport

Minimal uji:

- desktop 1366 × 768;
- mobile sempit sekitar 360 × 800;
- mobile umum sekitar 412 × 915;
- portrait wajib; landscape diuji sebagai sanity check.

Tidak boleh ada horizontal scroll pada halaman utama. Horizontal scroll hanya boleh
muncul pada komponen yang memang membutuhkannya, terutama tabel rich-content,
rumus lebar, dan dua tabel referensi Menjodohkan.

## Acceptance

| ID | Langkah | Hasil yang diharapkan |
| --- | --- | --- |
| PRM01 | Buka PG dengan teks pendek/panjang, baris baru, tebal/miring, tabel, rumus, dan gambar pada pertanyaan maupun opsi. | Semua konten terbaca, baris baru tidak hilang, opsi tidak overflow, radio tetap mudah disentuh. |
| PRM02 | Buka PG Kompleks. Pilih satu, beberapa, lalu batalkan salah satu pilihan. | Petunjuk **“Pilih satu atau lebih jawaban yang benar.”** tampil; checkbox bekerja; state tetap sesuai pilihan terakhir setelah pindah soal lalu kembali. |
| PRM03 | Buka PG Bertingkat. | UI tetap single-choice seperti PG; poin/kunci opsi tidak pernah bocor ke participant. |
| PRM04 | Buka Menjodohkan dengan teks panjang, gambar/rumus pada kedua sisi. | Tabel kiri bernomor dan tabel kanan berhuruf tampil utuh; blok referensi boleh scroll horizontal; halaman utama tidak ikut overflow. |
| PRM05 | Isi pasangan Menjodohkan. Coba memakai pilihan kanan yang sama pada dua item. | Pilihan yang sudah dipakai dinonaktifkan pada baris lain; progress pasangan berubah; state pulih setelah pindah soal/kembali. |
| PRM06 | Pada Isian Singkat TEXT, ketik lalu segera tekan **Berikutnya** sebelum 400 ms; kembali ke soal. | Jawaban terakhir tetap ada di IndexedDB/UI dan masuk antrean sync; tidak hilang karena debounce. |
| PRM07 | Pada Isian Singkat NUMERIC, isi angka desimal dengan koma. | Keyboard/input mobile nyaman; nilai tidak terpotong oleh UI dan kontrak server menerima format sesuai aturan angka Indonesia. |
| PRM08 | Pada Uraian, ketik beberapa kalimat lalu segera pindah soal. Ulangi dengan langsung membuka modal Selesai. | Teks terakhir tetap tersimpan; ringkasan Selesai membaca state terbaru. |
| PRM09 | Saat sedang mengetik Isian/Uraian, biarkan timer habis sebelum debounce selesai. | Snapshot input yang sudah diketik tetap dipersist sebelum drain/finalize timeout; tidak hilang hanya karena input sudah dikunci. |
| PRM10 | Uji tabel rich-content yang lebih lebar dari layar. | Scroll horizontal hanya di wrapper tabel; cell tetap terbaca; arah teks per-cell mengikuti konten. |
| PRM11 | Uji rumus inline dan block yang panjang. | KaTeX ter-render; rumus lebar dapat digeser tanpa membuat seluruh halaman overflow. |
| PRM12 | Ketuk gambar pada soal/opsi/pasangan. Gunakan +, −, reset, pan, dan roda mouse di desktop. | Modal zoom tampil; gambar tetap proporsional; pan/zoom tidak memecah layout workspace. |
| PRM13 | Uji Audio Google Drive dan Video Google Drive. | Keduanya tampil inline. Audio tetap compact; video responsif 16:9 dan tidak memaksa tinggi berlebih di layar kecil. |
| PRM14 | Ganti A− / A / A+, pindah soal, refresh workspace. | Ukuran teks berubah konsisten pada pertanyaan, opsi, matching, tabel, dan input yang relevan; pilihan tersimpan di localStorage. |
| PRM15 | Putus jaringan saat workspace aktif. | Banner offline terlihat tetapi tidak menutup tombol **Sebelumnya/Berikutnya** pada mobile; navigasi soal tetap dapat digunakan dari cache. |
| PRM16 | Buka **Daftar Soal** pada mobile, pilih nomor lain, lalu buka lagi dan tekan **Selesai Ujian**. | Palette muncul sebagai bottom sheet, backdrop bekerja, nomor mudah disentuh, palette menutup setelah pilihan, dan modal konfirmasi Selesai selalu tampil di atas tanpa tertutup bottom sheet. |
| PRM17 | Uji soal dengan string panjang/tanpa spasi, Unicode, Arab, dan aksara Jawa. | Konten tidak memaksa halaman melebar; Unicode tidak rusak; arah teks tetap wajar. |
| PRM18 | Audit bootstrap/network response seluruh enam tipe. | Tidak ada kunci jawaban, poin opsi PG Bertingkat, pasangan benar Menjodohkan, accepted answer Isian, atau rubrik Uraian yang bocor. |
| PRM19 | Uji refresh/re-entry setelah jawaban sudah ACK server. | Jawaban pulih dari server/cache sesuai kontrak Attempt; renderer menampilkan state yang sama. |
| PRM20 | Uji mobile portrait dari awal sampai submit dengan kombinasi keenam tipe. | Tidak ada elemen bertumpuk, kontrol penting tertutup, atau kebutuhan zoom browser untuk membaca/mengisi jawaban. |

## Code-level hardening 2026-09-30

Implementasi yang sudah masuk sebelum acceptance manual:

- write jawaban dari renderer diserialisasi agar klik/input cepat tidak berlomba pada
  revision lokal;
- debounce Isian/Uraian di-flush sebelum pindah soal, submit, page hide, dan timeout;
- flush yang berasal dari input sebelum timeout tetap boleh menyelesaikan persist lokal
  walau workspace sudah masuk state input-locked;
- line break rich-content pada opsi dan sisi Menjodohkan dipertahankan;
- long content memakai wrapping agar tidak memaksa page overflow;
- tabel rich-content memakai wrapper horizontal-scroll;
- cell tabel memakai direction auto;
- player Drive dibedakan audio/video; audio compact dan video responsif;
- banner offline pada mobile dipindah di atas fixed navigation;
- palette mobile ditutup sebelum modal submit agar tidak menutupi konfirmasi;
- select Matching dan field Isian/Uraian mendapat ukuran input/touch yang lebih aman
  pada layar kecil.

## Gate

Checkpoint ini baru boleh diberi status **PASS** setelah PRM01–PRM20 diuji pada
environment CBT-HERO lokal/deployment yang mempunyai data soal representatif.
Kegagalan harus dicatat dengan ID kasus, viewport/device, tipe soal, dan gejala.
