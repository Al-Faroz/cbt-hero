# CBT-HERO — Batch 4 Live Scoring

Tanggal: 2026-10-01

> **Status: IMPLEMENTED / STATIC PASS / FUNCTIONAL REGRESSION DEFERRED**
>
> Batch ini hanya membahas Manager/Public Live Scoring akademik. Functional,
> browser, load, dan end-to-end regression tetap dilakukan pada checkpoint akhir
> Master Ujian Akademik.

## 1. Kontrak yang dikunci

Public Live Scoring adalah display terpisah dari hasil resmi/final.

Satu display hanya menunjuk satu Jadwal pada satu waktu.

Kolom public tepat:

```text
No | No Peserta | Nama | Skor | Status
```

Status:
- MENGERJAKAN;
- SELESAI.

Skor hanya kelompok Klik:
- PG;
- PG Kompleks;
- PG Bertingkat;
- Menjodohkan.

Isian Singkat dan Uraian tidak masuk public score.

## 2. Formula skor

Formula Live Scoring tetap ekuivalen dengan `click_score` pada
`AcademicScoringService`:

1. hitung raw/max per tipe Klik;
2. terapkan bobot tipe;
3. normalisasi terhadap total bobot tipe Klik aktif;
4. hasil akhir 0–100.

Aturan:
- jawaban belum ada = 0 sementara;
- VOID keluar dari denominator;
- raw score tidak boleh melebihi max point;
- jika tidak ada denominator Klik aktif, public score = 0.

## 3. Live Edit dan skor ACTIVE

Gap ditemukan:

Saat KEY_WEIGHT atau STRUCTURAL+PRESERVE dilakukan ketika peserta ACTIVE sudah
mempunyai jawaban, current revision berubah tetapi `attempt_response.effective_score`
sebelumnya belum otomatis direcompute.

Akibatnya Public Live Scoring dapat tetap membaca skor lama sampai peserta
mengirim mutation baru.

Perbaikan:
- setelah Live Edit KEY_WEIGHT / STRUCTURAL+PRESERVE, response ACTIVE pada soal
  terkait direcompute terhadap revision baru;
- `auto_score`, `effective_score`, dan `scoring_state` diperbarui;
- STRUCTURAL+REANSWER tetap memakai reset response;
- Attempt FINISHED tidak diubah diam-diam dan tetap mengikuti Rescore.

Dengan demikian public snapshot berikutnya dapat langsung memakai skor terbaru
untuk peserta ACTIVE tanpa melakukan scoring N+1 saat refresh public.

## 4. Cache definisi scoring

`AcademicAnswerService` diberi cache per instance untuk:
- opsi PG/PGK/PGB;
- pasangan Matching;
- accepted answer Isian TEXT.

Tujuan utama pada Live Edit:
- satu revision yang sama dapat dipakai merecompute banyak response ACTIVE;
- definisi soal tidak di-query ulang untuk setiap peserta.

Ini juga aman dipakai oleh scoring normal karena cache hidup hanya selama instance
service tersebut.

## 5. Query public dan performa

Public snapshot menggunakan:
1. satu query daftar Attempt ACTIVE/FINISHED;
2. satu query aggregate scoring per Attempt + tipe Klik.

Tidak ada query per peserta.

Hardening Batch 4:
- aggregate tidak lagi membuat `WHERE attempt_id IN (...)` ribuan ID;
- filter langsung memakai `a.jadwal_id` + status Attempt;
- index existing `attempt(jadwal_id, status, last_sync_at)` dapat dimanfaatkan.

Target sekitar 2.000–2.500 peserta tetap tidak mengubah endpoint menjadi N+1.

## 6. Ranking

Ranking:
- score descending;
- competition rank;
- nilai sama → rank sama;
- secondary order = No Peserta natural-order;
- tidak ada tie-break waktu.

Tambahan deterministic fallback:
- bila skor dan No Peserta sama/kosong, internal Attempt ID dipakai hanya untuk
  menjaga urutan render stabil;
- internal ID tersebut dibuang sebelum public payload dibuat;
- fallback ini tidak mengubah rank.

Contoh:

```text
90 → rank 1
90 → rank 1
80 → rank 3
```

## 7. Public payload privacy

Payload public hanya mempunyai display context:

```text
schedule:
  kegiatan
  ujian
  jenis_jadwal

rows:
  no
  no_peserta
  nama
  skor
  status

generated_at
```

Tidak diekspos:
- peserta_id;
- peserta_kegiatan_id;
- attempt_id;
- jadwal_id;
- NISN;
- username/password;
- rombel;
- ruang;
- jawaban;
- answer key;
- question detail;
- scoring revision;
- token version.

## 8. Bearer-like public URL

Token tetap dibuat dari 36 random bytes lalu Base64 URL-safe.

Validation memakai `hash_equals()`.

Regenerate:
- membuat token baru;
- menaikkan `public_token_version`;
- token lama gagal pada request berikutnya.

STOP:
- endpoint public menjadi NOT_FOUND;
- token dapat dipakai kembali jika Operator START lagi tanpa regenerate.

## 9. Transaction / concurrency Manager

START, STOP, dan Regenerate sebelumnya melakukan read-update config tanpa row lock.

Batch 4 mengubah mutation menjadi:
- transaction;
- `SELECT ... FOR UPDATE` terhadap row `live_scoring_config(id=1)`;
- update state/token/version;
- commit;
- audit.

Fallback seed memakai `INSERT IGNORE`.

Akibatnya mutation Manager yang datang bersamaan diserialisasi dan tidak saling
menimpa token/version/state.

## 10. Manager UI mutation lock

`manager-live-scoring.js` sekarang mempunyai busy state.

Saat START/STOP/Ganti URL berlangsung:
- START disabled;
- STOP disabled;
- Regenerate disabled;
- pilih Jadwal disabled;
- Copy/Open disabled.

Setelah mutation selesai, UI membaca ulang state authoritative dari server.

Tujuannya:
- mencegah double-click;
- mencegah race UX;
- menghindari UI mempertahankan state optimistik yang salah.

## 11. Public security headers

HTML dan endpoint snapshot sekarang mengirim:
- `Cache-Control: no-store, no-cache, must-revalidate, max-age=0`;
- `Pragma: no-cache`;
- `Referrer-Policy: no-referrer`;
- `X-Robots-Tag: noindex, nofollow, noarchive`;
- `X-Content-Type-Options: nosniff`.

HTML juga mempunyai:
- `meta referrer=no-referrer`;
- `meta robots=noindex,nofollow,noarchive`.

Bearer-like URL tetap tidak ditampilkan sebagai isi tabel.

## 12. Siklus display

Implementasi tetap:

```text
FETCH SNAPSHOT
→ RENDER DARI ATAS
→ TUNGGU
→ SCROLL SAMPAI BARIS TERAKHIR
→ TUNGGU
→ FETCH SNAPSHOT BARU
→ KEMBALI KE ATAS
```

Tidak ada fetch di tengah scroll.

Jika seluruh data muat satu viewport:
- display menunggu siklus wajar;
- tidak melakukan polling agresif.

Jika STOP atau token diganti:
- display yang sudah terbuka berhenti ketika melakukan fetch berikutnya.

## 13. Psych firewall

Pilihan Jadwal Live Scoring memakai:
`j.psych_instrument_id IS NULL`.

Master Ujian Psikologi tidak masuk Public Live Scoring Akademik.

## 14. Acceptance yang ditambah

Phase 8:
- S47 Active response direcompute setelah KEY_WEIGHT /
  STRUCTURAL+PRESERVE.

Phase 9:
- P9L14 query agregat tanpa N+1 / large attempt-ID IN;
- P9L15 Live Edit ACTIVE tercermin di snapshot berikutnya;
- P9L16 concurrent Manager mutation diserialisasi;
- P9L17 public response privacy headers;
- P9L18 deterministic ordering pada tie ekstrem;
- P9L19 Manager UI menahan mutation ganda.

## 15. Static validation

JavaScript:
- `assets/js/manager-live-scoring.js` → syntax OK;
- inline JS `app/Views/public/live_scoring.php` → syntax OK setelah placeholder
  PHP initial JSON diganti untuk static parse.

PHP brace balance:
- LiveScoringService → 0;
- QuestionLiveEditService → 0;
- AcademicAnswerService → 0;
- Live/Public controller → 0.

Tidak ada duplicate `manager-live-scoring.js` pada `public/assets` yang perlu
disinkronkan.

## 16. Yang belum diklaim PASS

Masih DEFERRED:
- actual PHP/MySQL execution;
- START/STOP/Regenerate melalui browser nyata;
- URL lama setelah regenerate;
- capture OBS/browser display;
- 2.000–2.500 participant dataset;
- stress test query/CPU;
- full scroll dengan layar besar;
- Live Edit saat display sedang aktif;
- network interruption;
- concurrent Manager request nyata;
- mobile/public display regression;
- end-to-end Phase 9.

Status Batch 4:

**IMPLEMENTED / STATIC PASS / FUNCTIONAL REGRESSION DEFERRED**

## 17. Checkpoint berikutnya

Setelah Batch 4, seluruh blok besar Akademik yang tadi dipecah telah diaudit:

1. Lifecycle / Dependency;
2. Scoring / Finalisasi;
3. Hasil / Report / Export;
4. Live Scoring.

Functional regression akhir tetap belum dijalankan sesuai keputusan proyek.
