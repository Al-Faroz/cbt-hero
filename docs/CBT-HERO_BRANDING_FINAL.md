# CBT-HERO — BRANDING FINAL

**Status:** FIX / FINAL BRANDING  
**Tanggal:** 25 September 2026

Dokumen ini mengunci branding aplikasi CBT-HERO untuk Landing Peserta,
Landing Admin/Operator, dan Manager Shell.

## 1. Nama

```text
CBT-HERO
```

## 2. Product Label

```text
Computer Based Test
```

## 3. Tagline Final

```text
Ujian digital yang ringan, stabil, dan siap untuk skala besar.
```

## 4. Description Final

```text
Satu platform untuk persiapan, pelaksanaan, monitoring, dan hasil ujian.
```

## 5. Footer Branding

```text
CBT-HERO · Computer Based Test Platform
```

## 6. Copy Login Manager

```text
Admin / Operator

Masuk ke CBT-HERO

Akses Manager untuk mengelola persiapan, pelaksanaan, monitoring,
dan hasil ujian.
```

## 7. Copy Login Peserta

```text
Peserta Ujian

Masuk ke CBT-HERO

Masukkan Username dan Password yang tertera pada kartu peserta
untuk mengakses ujian.
```

## 8. Asset Resmi

Karena deployment CBT-HERO menggunakan project root sebagai web root
(`public/` CI4 telah dipindahkan ke root), asset branding diletakkan di:

```text
assets/images/branding/
├── cbt-hero-logo.png
└── cbt-hero-logo-text.png
```

Bukan di:

```text
app/
writable/
docs/
vendor/
```

Penggunaan:

- `cbt-hero-logo-text.png` → Landing Peserta dan Landing Manager.
- `cbt-hero-logo.png` → Manager Sidebar, favicon, area sempit.
- File asli tidak dimodifikasi oleh aplikasi.

## 9. Aturan

Branding copy dan asset path di atas tidak diubah pada implementasi berikutnya
kecuali ada keputusan eksplisit untuk revisi branding.
