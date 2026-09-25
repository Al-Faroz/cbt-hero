# CBT-HERO Frontend Vendor Assets

Seluruh library runtime disimpan lokal di `assets/vendor/`.

## Prinsip

- Tidak bergantung CDN saat ujian berjalan.
- Tidak memakai jQuery.
- Library **tidak dimuat semua secara global**.
- Bootstrap dipakai oleh shared shell.
- DataTables / SweetAlert2 / Chart.js / SortableJS dimuat hanya pada halaman Manager yang memerlukan.
- Dexie / DOMPurify / KaTeX / Panzoom dimuat hanya pada participant/exam runtime atau preview yang memerlukan.
- Public Live Scoring tetap custom HTML/CSS/Vanilla dan tidak wajib membawa library Manager.

## Instalasi / Refresh

Jalankan dari PowerShell pada root project:

```powershell
powershell -ExecutionPolicy Bypass -File .\tools\install_frontend_assets.ps1
```

Untuk mengunduh ulang file yang sudah ada:

```powershell
powershell -ExecutionPolicy Bypass -File .\tools\install_frontend_assets.ps1 -Force
```

Setelah sukses, seluruh file hasil download harus ikut Git agar repository tetap deployment-ready.

## Struktur

```text
assets/vendor/
├── bootstrap/
├── bootstrap-icons/
├── datatables/
├── sweetalert2/
├── chartjs/
├── sortablejs/
├── dexie/
├── dompurify/
├── katex/
└── panzoom/
```

Versi pin ada di `manifest.json`.
