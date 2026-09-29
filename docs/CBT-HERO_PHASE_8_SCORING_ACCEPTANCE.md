# CBT-HERO — PHASE 8A Scoring Akademik + Koreksi + Finalisasi Acceptance

Tanggal baseline: 2026-09-29

> **Status pengujian: DEFERRED.**
> Acceptance dijalankan bersama regression Master Ujian Akademis sebelum Master
> Ujian Psikologi. DEFERRED bukan PASS.

## Cakupan Phase 8A

Phase 8A menyelesaikan fondasi scoring akademik yang dipakai oleh Attempt FINISH,
koreksi Manager, rescore, dan finalisasi hasil:

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
- finalisasi hasil idempotent dan immutable.

Live Edit revision/VOID command lengkap tetap dilanjutkan sebagai bagian Phase 8 berikutnya.

## Kontrak API Phase 8A

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

## Status

**DEFERRED — implementasi Phase 8A selesai, acceptance belum dijalankan.**

Phase 8 belum dinyatakan selesai penuh sebelum Live Edit revision/VOID command dan
checkpoint Master Ujian Akademis selesai.
