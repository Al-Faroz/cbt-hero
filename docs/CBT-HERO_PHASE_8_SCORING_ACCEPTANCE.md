# CBT-HERO — PHASE 8 Scoring Akademik + Live Edit + Finalisasi Acceptance

Tanggal baseline: 2026-09-29

> **Status pengujian: DEFERRED.**
> Acceptance dijalankan bersama regression Master Ujian Akademis sebelum Master
> Ujian Psikologi. DEFERRED bukan PASS.

## Cakupan Phase 8

Phase 8 menyelesaikan scoring akademik, koreksi operasional, Live Edit, VOID, dan finalisasi hasil:

- satu calculator authoritative untuk enam tipe soal akademik;
- nilai kelompok klik dinormalisasi 0–100;
- nilai kelompok ketik dinormalisasi 0–100 dan dapat berstatus DALAM PROSES;
- nilai akhir memakai kontribusi bobot tipe, bukan rata-rata dua kelompok;
- manual score/override dengan audit;
- rescore Jadwal idempotent;
- VOID dikeluarkan dari denominator oleh scoring core;
- bobot tipe aktif dinormalisasi kembali bila suatu tipe seluruhnya VOID;
- snapshot hasil versioned;
- Attempt FINISHED tidak sama dengan Result FINAL;
- finalisasi hasil idempotent dan immutable;
- Live Edit membuat revision baru tanpa menimpa revision lama;
- CONTENT / KEY_WEIGHT / STRUCTURAL divalidasi terpisah;
- STRUCTURAL mendukung PRESERVE atau REANSWER untuk Attempt aktif;
- jawaban lama pada REANSWER masuk audit sebelum response aktif dikosongkan;
- VOID tidak menghapus response historis;
- Bootstrap, Sync, Status, dan revision checkpoint membaca revision aktif;
- UI Manager menyediakan Penilaian Akademik dan Live Edit dari Daftar Soal.

## Kontrak API Phase 8

```text
GET  /manager/api/results/attempts/{attemptId}

GET  /manager/api/scoring/questions
GET  /manager/api/scoring/questions/{questionId}/responses
POST /manager/api/scoring/responses/{responseId}/manual-score

POST /manager/api/scoring/jadwal/{jadwalId}/rescore
POST /manager/api/results/jadwal/{jadwalId}/finalize
```

Mutation rescore dan finalisasi wajib memakai `Idempotency-Key`.

## Acceptance tersimpan

| ID | Langkah | Hasil yang diharapkan |
|---|---|---|
| S01 | Selesaikan PG benar/salah. | Benar mendapat max point, salah 0. |
| S02 | PG Kompleks mode PARTIAL. | Rumus partial sesuai correct-selected minus wrong-selected, minimum 0. |
| S03 | PG Kompleks mode ALL_OR_NOTHING. | Nilai penuh hanya jika set pilihan tepat. |
| S04 | Matching mode PARTIAL. | Poin proporsional terhadap pasangan benar. |
| S05 | Matching mode ALL_OR_NOTHING. | Nilai penuh hanya jika seluruh pasangan benar. |
| S06 | PG Bertingkat. | Poin mengikuti point_value opsi dan tidak melebihi max point. |
| S07 | Isian TEXT. | Trim, lowercase/case-insensitive, dan whitespace normalization bekerja. |
| S08 | Isian NUMERIC koma Indonesia. | Koma dinormalisasi sebagai separator desimal. |
| S09 | Isian NUMERIC tolerance 0. | Hanya nilai numerik exact yang benar. |
| S10 | Uraian berisi jawaban tanpa nilai manual. | typed_score DALAM PROSES dan final_score belum tersedia. |
| S11 | Uraian kosong. | Bernilai 0 tanpa memaksa manual scoring. |
| S12 | Simpan manual score decimal 0..max dengan alasan. | Override tersimpan dan score_adjustment_log terbentuk. |
| S13 | Manual score di luar 0..max. | Ditolak VALIDATION_FAILED. |
| S14 | Ulang koreksi manual. | Snapshot baru terbentuk; snapshot lama tetap immutable. |
| S15 | Rescore setelah perubahan key/weight. | Seluruh FINISHED Attempt pada Jadwal dihitung ulang deterministik. |
| S16 | Retry rescore dengan Idempotency-Key sama. | Tidak menggandakan operasi. |
| S17 | VOID satu soal. | Soal keluar dari denominator; histori jawaban tetap ada. |
| S18 | Seluruh soal satu tipe VOID. | Bobot tipe aktif lain dinormalisasi sehingga final tetap valid 0–100. |
| S19 | Submit/Timeout/Paksa Selesai dengan scoring COMPLETE. | Snapshot dibuat non-final; results_finalized_at masih NULL. |
| S20 | Finalisasi saat masih ada Attempt ACTIVE. | Ditolak STATE_CONFLICT. |
| S21 | Finalisasi saat Uraian masih pending. | Ditolak SCORING_INCOMPLETE. |
| S22 | Finalisasi seluruh scoring COMPLETE. | Snapshot FINAL baru dibuat dan Jadwal results_finalized_at/by terisi. |
| S23 | Retry finalisasi setelah FINAL. | Mengembalikan current final state tanpa membuat reopen. |
| S24 | Manual score/rescore setelah FINAL. | Ditolak RESULT_ALREADY_FINAL. |
| S25 | Nilai Pilihan Ganda pada kombinasi bobot click 60 dari total 100. | Ditampilkan sebagai normalized 0–100, bukan maksimum 60. |
| S26 | Nilai Isian & Uraian pada bobot typed 40. | Ditampilkan sebagai normalized 0–100 saat complete. |
| S27 | Nilai akhir. | Sama dengan total weighted contribution semua tipe, bukan rata-rata click dan typed. |
| S28 | Buka detail Attempt hasil. | Manager melihat snapshot/item/response tanpa mengubah histori. |
| S29 | Live Edit CONTENT pada Bank READY. | Revision baru terbentuk; revision lama tetap immutable; jawaban aktif dipertahankan. |
| S30 | Live Edit CONTENT mencoba mengubah kunci/poin/struktur. | Ditolak CHANGE_KIND_MISMATCH. |
| S31 | Live Edit KEY_WEIGHT. | Kunci/poin dapat berubah tanpa mengubah struktur; rescore memakai revision aktif. |
| S32 | Live Edit STRUCTURAL + PRESERVE saat ada Attempt ACTIVE. | Client menerima revision baru dan jawaban lama dipertahankan jika masih kompatibel. |
| S33 | Live Edit STRUCTURAL + REANSWER saat ada Attempt ACTIVE. | Jawaban aktif dikosongkan, jawaban lama tercatat di audit, peserta diminta menjawab ulang. |
| S34 | VOID melalui Live Edit. | Revision VOID baru terbentuk, respons historis tetap ada, soal tidak dapat dijawab lagi dan dikeluarkan dari denominator. |
| S35 | Participant sedang idle saat Live Edit dilakukan. | Revision diterima melalui checkpoint Status/Revisions tanpa WebSocket dan cache IndexedDB diperbarui. |
| S36 | Live Edit menambahkan media pada revision baru. | Media baru masuk prepared assignment media dan dapat diakses participant yang berhak. |
| S37 | Live Edit setelah Attempt FINISHED tetapi sebelum FINAL. | Attempt FINISHED tidak dibuka kembali; hasil berubah hanya melalui rescore/snapshot baru. |
| S38 | Buka halaman Penilaian Akademik. | Operator dapat memilih Jadwal, melihat soal/respons, memberi nilai manual, rescore, dan finalisasi melalui UI Manager. |
| S39 | Buka Daftar Soal pada Bank READY. | Aksi Live Edit tersedia; editor meminta jenis perubahan, catatan, policy struktural, dan menyediakan VOID. |
| S40 | STRUCTURAL + PRESERVE mengubah option/pair key yang membuat jawaban aktif tidak kompatibel. | Ditolak `REANSWER_REQUIRED`; Operator wajib memakai REANSWER. |
| S41 | CONTENT mencoba mengubah option key atau mapping benar Menjodohkan. | Ditolak `CHANGE_KIND_MISMATCH`; perubahan tersebut bukan content-only. |
| S42 | Coba beri manual score pada soal VOID. | Ditolak `QUESTION_VOID`; UI juga menonaktifkan tombol koreksi. |
| S43 | Respons objektif masuk NEEDS_REVIEW sementara Isian/Uraian sudah complete. | Scoring global tetap IN_PROCESS, tetapi typed_score tetap tersedia/COMPLETE; pending objektif tidak membuat kelompok ketik palsu DALAM PROSES. |
| S44 | Finalisasi Jadwal lalu peserta tanpa Attempt mencoba START selama window masih terbuka. | START ditolak `RESULT_ALREADY_FINAL`; Daftar Ujian menampilkan Jadwal sebagai Ditutup. |
| S45 | Buat snapshot FINAL melalui service dengan scoring belum COMPLETE/final_score NULL. | Ditolak oleh invariant `ResultSnapshotService`. |
| S46 | Input nilai manual desimal `2,5`. | Diterima dan disimpan canonical sebagai nilai numerik yang sama dengan `2.5`. |

## Status

**DEFERRED — implementasi Phase 8 selesai, acceptance belum dijalankan.**

Phase 8 belum dinyatakan PASS sampai regression checkpoint akhir Master Ujian Akademis selesai.
