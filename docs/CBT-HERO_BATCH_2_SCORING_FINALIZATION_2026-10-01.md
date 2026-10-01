# CBT-HERO — Batch 2 Scoring / Finalisasi

Tanggal: 2026-10-01

> **Status: IMPLEMENTED / STATIC PASS / FUNCTIONAL REGRESSION DEFERRED**
>
> Batch ini hanya membahas scoring akademik, Live Edit/VOID, snapshot, rescore,
> manual override, dan finalisasi hasil. Functional test tetap dijalankan pada
> checkpoint akhir Master Ujian Akademik.

## 1. Scope

Audit dan hardening mencakup:

- scoring enam tipe akademik;
- nilai kelompok klik;
- nilai kelompok ketik;
- final score berbobot;
- manual override;
- rescore;
- Live Edit CONTENT / KEY_WEIGHT / STRUCTURAL;
- PRESERVE / REANSWER;
- VOID;
- snapshot versioned;
- official result pointer;
- Finalisasi Hasil;
- lock setelah FINAL;
- START participant setelah FINAL.

## 2. Scoring core

`AcademicScoringService` tetap menjadi calculator authoritative.

Enam tipe:
- PG;
- PG Kompleks;
- PG Bertingkat;
- Menjodohkan;
- Isian Singkat;
- Uraian.

Aturan yang diverifikasi:
- PG benar = max point, salah = 0;
- PG Kompleks PARTIAL = correct-selected minus wrong-selected, minimum 0;
- PG Kompleks ALL_OR_NOTHING = penuh hanya jika set tepat;
- PG Bertingkat memakai `point_value`;
- Matching PARTIAL proporsional pasangan benar;
- Matching ALL_OR_NOTHING penuh hanya jika seluruh mapping benar;
- Isian TEXT memakai trim/lowercase/whitespace normalization;
- Isian NUMERIC menerima decimal comma;
- Uraian kosong auto 0;
- Uraian terisi tetap PENDING_MANUAL sampai dinilai.

### Perbaikan pending state

Sebelumnya satu respons objektif `NEEDS_REVIEW` membuat typed score ikut
`DALAM PROSES`.

Sekarang dibedakan:
- `pending_manual` = pending global, mengunci final score;
- `pending_typed_manual` = pending khusus Isian/Uraian.

Akibatnya:
- final score tetap NULL jika ada item mana pun yang belum selesai;
- typed score tetap dapat COMPLETE bila masalah hanya berada di kelompok klik.

## 3. Manual override

Hardening:
- hanya Attempt FINISHED;
- ditolak setelah Jadwal FINAL;
- rentang 0..max point;
- alasan wajib;
- audit `score_adjustment_log`;
- snapshot provisional baru dibuat setelah koreksi;
- soal VOID tidak boleh diberi nilai manual;
- input decimal comma seperti `2,5` diterima server dan dinormalisasi menjadi angka canonical.

UI Penilaian juga menonaktifkan tombol koreksi pada item VOID.

## 4. Live Edit

### Signature structure

Bug lama:
- shape PG hanya membandingkan jumlah opsi;
- shape Matching hanya membandingkan jumlah pasangan;
- mapping benar Matching belum masuk scoring signature.

Akibatnya perubahan key/mapping tertentu dapat salah diklasifikasikan sebagai
CONTENT atau KEY_WEIGHT.

Perbaikan:
- shape PG/PGK/PGB memakai canonical option-key set;
- shape Matching memakai canonical left/right key set;
- scoring PG/PGK memakai mapping option_key → correct;
- scoring PGB memakai mapping option_key → point;
- scoring Matching memakai left_key → right_key + scoring mode;
- accepted Isian TEXT dinormalisasi/sort sebelum signature.

CONTENT sekarang benar-benar tidak boleh mengubah struktur/kunci/poin.
KEY_WEIGHT tidak boleh mengubah struktur jawaban.

### STRUCTURAL + PRESERVE

Sebelum revisi STRUCTURAL dengan policy PRESERVE diaktifkan:
- seluruh response ACTIVE divalidasi terhadap revisi baru;
- jika ada jawaban tidak kompatibel, transaksi dibatalkan;
- response: `REANSWER_REQUIRED`;
- Operator harus memilih REANSWER.

### REANSWER race

Audit memastikan mutation lama yang masih in-flight tidak dapat menghidupkan
kembali jawaban lama.

`AnswerSyncService` memakai reset sentinel:
- client_revision = 0;
- answer_payload = NULL;
- last_mutation_id = NULL;
- server_revision baru.

Mutation dari base revision lama ditolak dengan `REANSWER_REQUIRED`.

## 5. VOID

VOID:
- tidak menghapus response historis;
- dikeluarkan dari denominator scoring;
- item runtime tampil sebagai soal dibatalkan;
- participant tidak dapat mengubah jawaban VOID;
- manual override VOID ditolak.

VOID tetap berada dalam blueprint/assignment agar urutan/histori konsisten;
runtime menampilkan status dibatalkan dan scoring menormalkan bobot aktif.

## 6. Snapshot revision consistency

`ScoringQueryService::attemptDetail()` sebelumnya masih menampilkan pertanyaan
dari revision baseline Preparation.

Sekarang detail Attempt membaca:
- `scoring_revision_id` dari `result_item_snapshot.payload_json`;
- fallback ke baseline revision hanya bila metadata snapshot lama tidak memilikinya.

Dengan demikian detail scoring konsisten dengan revision yang benar-benar dipakai
saat snapshot dibuat.

## 7. Snapshot FINAL invariant

`ResultSnapshotService` sekarang melakukan defense-in-depth:

Snapshot dengan `final=true` ditolak jika:
- `scoring_status != COMPLETE`; atau
- `final_score == NULL`.

Aturan utama tetap:

```text
Attempt FINISHED ≠ Result FINAL
Scoring COMPLETE ≠ Result FINAL
Paksa Selesai ≠ Finalisasi Hasil
```

Submit / Timeout / Paksa Selesai / Manual Override / Rescore tetap membuat snapshot
provisional.

Hanya `ResultFinalizationService` yang membuat snapshot FINAL.

## 8. Finalization closes new START

Bug integrasi yang ditemukan:

Jadwal yang sudah mempunyai `results_finalized_at` masih dapat menerima START baru
selama window waktunya belum berakhir.

Perbaikan dua lapis:
- `AttemptStartService` menolak dengan `RESULT_ALREADY_FINAL`;
- `ParticipantExamDiscoveryService` menampilkan state `DITUTUP`.

Urutan participant UI:
- LANJUTKAN;
- BISA DIMULAI;
- BELUM DIBUKA;
- DITUTUP;
- SELESAI.

Modal Finalisasi Manager juga menjelaskan bahwa FINAL menutup START baru pada Jadwal.

## 9. UI Penilaian setelah FINAL

`MonitoringService::options()` sekarang membawa:
- `results_finalized_at`;
- `results_finalized_by`.

UI Penilaian:
- menampilkan suffix FINAL pada Jadwal;
- tetap boleh reload/read;
- Rescore disabled setelah FINAL;
- Finalisasi disabled setelah FINAL;
- manual override disabled melalui response-level final state.

## 10. Replacement / Susulan

Static audit memastikan:
- replacement START menandai Attempt lama `SUPERSEDED`;
- snapshot lama tidak dihapus;
- Rescore hanya memproses Attempt FINISHED;
- Finalisasi hanya memproses Attempt FINISHED pada Jadwal terkait;
- `results_finalized_at` mengunci scoring normal untuk Jadwal tersebut;
- Susulan lain tetap dapat mempunyai histori/official result sendiri.

## 11. Concurrency / versioning

Schema:
- UNIQUE `(attempt_id, snapshot_version)`;
- UNIQUE `(root_jadwal_id, peserta_kegiatan_id)` pada official pointer.

Jalur mutasi scoring memakai transaction + row lock:
- participant finalize;
- manual override;
- rescore;
- result finalization.

Ini menjaga `selectMax(snapshot_version)+1` terserialisasi melalui lock Jadwal/Attempt
pada transaction pemanggil.

## 12. Acceptance ditambah

`CBT-HERO_PHASE_8_SCORING_ACCEPTANCE.md` ditambah:
- S40 PRESERVE incompatible → REANSWER_REQUIRED;
- S41 CONTENT mengubah key/mapping → CHANGE_KIND_MISMATCH;
- S42 manual score VOID → QUESTION_VOID;
- S43 typed pending terisolasi dari pending objektif;
- S44 START setelah FINAL → RESULT_ALREADY_FINAL;
- S45 final snapshot incomplete ditolak;
- S46 decimal comma manual score.

## 13. Static validation

JavaScript:
- `assets/js/manager-scoring.js` → syntax OK;
- `assets/js/participant-exam-list.js` → syntax OK.

PHP service yang diubah:
- AcademicScoringService;
- ManualOverrideService;
- QuestionLiveEditService;
- ScoringQueryService;
- ResultSnapshotService;
- AttemptStartService;
- ParticipantExamDiscoveryService;
- MonitoringService.

Brace balance seluruh file di atas = 0.

## 14. Yang belum diklaim PASS

Masih DEFERRED:
- actual PHP/MySQL execution;
- scoring dengan dataset nyata keenam tipe;
- concurrent manual/rescore/finalize;
- Live Edit saat participant aktif;
- REANSWER race nyata di browser;
- replacement Susulan end-to-end;
- decimal comma melalui browser;
- Finalisasi dengan peserta absent/belum START;
- full regression Phase 8.

Status Batch 2:

**IMPLEMENTED / STATIC PASS / FUNCTIONAL REGRESSION DEFERRED**

## 15. Batch berikutnya

Setelah checkpoint ini:

**Batch 3 — Hasil / Report / Export**

Fokus:
- Hasil Ujian;
- official result selection;
- Rekap Nilai;
- Analisis Soal;
- snapshot revision consistency;
- Export Excel/PDF;
- final vs provisional display.
