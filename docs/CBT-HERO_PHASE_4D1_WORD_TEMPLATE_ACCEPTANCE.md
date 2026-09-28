# Phase 4D1 — Template Word Dinamis + Parser Word V2

## Tujuan

Mengganti workflow Word teknis/JSON dengan template manusiawi yang dibuat langsung
dari Komposisi Bank. Format visual mengikuti draf tujuh halaman CBT-HERO:
Ketentuan, PG, PG Kompleks, PG Bertingkat, Menjodohkan, Isian Singkat, dan Uraian.

## Kontrak Generator

Endpoint:

```text
GET /manager/api/bank-soal/{bankId}/template/docx
```

Syarat:

- Bank harus ada dan mempunyai Komposisi.
- `question_count` menentukan jumlah soal tiap tipe.
- `option_count` menentukan jumlah pilihan PG/PG Kompleks/PG Bertingkat.
- `option_count` menentukan jumlah pasangan Menjodohkan.
- Total maksimal 200 soal per file, sama dengan batas satu job impor.
- Layout A4 landscape.
- Tipe yang tidak aktif tidak dibuat.
- Setiap tipe dimulai pada halaman baru; tipe dengan banyak soal boleh melanjutkan
  ke halaman berikutnya.

## Format Final per Tipe

### PG

```text
Bagian | Isi | Benar
Soal
Pilihan A..N
```

Tepat satu tanda benar. Poin maksimum otomatis 1.

### PG Kompleks

```text
Bagian | Isi | Benar
Soal
Pilihan A..N
```

Boleh beberapa benar, tetapi minimal satu benar dan satu salah. Poin maksimum
otomatis 1.

### PG Bertingkat

```text
Bagian | Isi | Poin pilihan
Soal
Pilihan A..N
```

Poin maksimum dihitung dari poin pilihan tertinggi.

### Menjodohkan

```text
Soal | Sisi kiri | Pasangan benar di sisi kanan
...
Cara penilaian | <dari Komposisi>
Poin maksimum | <diisi guru>
```

Jumlah pasangan dan cara penilaian tidak ditentukan ulang oleh guru.

### Isian Singkat

```text
No | Soal | Mode | Jawaban diterima / Angka harapan | Toleransi | Poin maksimum
```

Mode `TEKS`: satu jawaban diterima per baris di sel jawaban.
Mode `ANGKA`: isi angka harapan dan toleransi.

### Uraian

```text
No | Soal | Rubrik / Pedoman Penilaian | Poin maksimum
```

## Marker Internal

Generator menyisipkan marker `CBT-HERO-WORD-V2` sebagai run Word tersembunyi.
Marker tidak menjadi field yang harus dipahami/diisi guru. Parser menggunakan marker
untuk mengenali tipe dan nomor soal tanpa mengandalkan JSON atau urutan tabel secara
buta.

## Parser

- Word V2 dan Word teknis lama tetap dapat dibaca.
- Placeholder utuh seperti `[Tulis soal di sini]` diperlakukan sebagai kosong.
- Tanda benar yang diterima: `✓`, `✔`, `V`, `X`, `1`, `BENAR`, `TRUE`.
- Gambar JPG/PNG/WebP yang ditempel di sel tetap diimpor sebagai media.
- Bold/italic, tabel dalam sel, teks Arab/RTL, dan formula berbasis teks tetap
  mengikuti mekanisme parser/renderer yang ada.
- Upload masuk staging dahulu; tidak ada commit langsung ke Bank.

## Acceptance

| ID | Langkah | Hasil |
| --- | --- | --- |
| W01 | Komposisi PG 10 soal × 4 pilihan, unduh Word. | Ada 10 blok PG dan tiap blok A–D. |
| W02 | Komposisi Matching 2 soal × 6 pasangan mode PARTIAL. | Ada 2 blok Matching, masing-masing 6 pasangan dan mode tampil Poin sebagian. |
| W03 | Aktifkan hanya PG + Uraian. | Template hanya memuat dua tipe tersebut setelah halaman Ketentuan. |
| W04 | Isi PG, PG Kompleks, PG Bertingkat, Matching, Isian TEKS/ANGKA, dan Uraian lalu upload. | Staging menghasilkan payload normalized keenam tipe tanpa input JSON. |
| W05 | Sisakan placeholder/field wajib kosong. | Row staging INVALID dengan pesan validasi; tidak auto-commit. |
| W06 | Sisipkan gambar pada sel soal/pilihan lalu upload. | Media menjadi referensi staging dan tampil pada Preview. |
| W07 | Ubah jumlah pilihan/pasangan template secara manual sehingga tidak sesuai Komposisi. | Validasi staging menolak jumlah yang berbeda. |
| W08 | Commit job valid, panggil commit ulang dengan job sama. | Soal hanya masuk sekali; job tetap COMMITTED. |
| W09 | Bank tanpa Komposisi mencoba unduh Word. | Ditolak dengan pesan atur Komposisi terlebih dahulu. |
| W10 | Total Komposisi lebih dari 200 soal. | Generator menolak dan menjelaskan batas satu file. |

## Belum termasuk

Template Excel manusiawi/dinamis dikerjakan pada Phase 4D2. File Excel teknis lama
tetap tersedia agar workflow lama tidak terputus selama transisi.
