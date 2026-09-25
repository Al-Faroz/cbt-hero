param(
    [switch]$Force
)

$ErrorActionPreference = "Stop"
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

$ProjectRoot = Split-Path -Parent $PSScriptRoot
$VendorRoot  = Join-Path $ProjectRoot "assets\vendor"

function Ensure-Directory {
    param([Parameter(Mandatory = $true)][string]$Path)

    if (-not (Test-Path $Path)) {
        New-Item -ItemType Directory -Path $Path -Force | Out-Null
    }
}

function Download-Asset {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [Parameter(Mandatory = $true)][string]$Destination
    )

    Ensure-Directory (Split-Path -Parent $Destination)

    if ((Test-Path $Destination) -and (-not $Force)) {
        Write-Host "[SKIP] $Destination"
        return
    }

    $attempt = 0
    $maxAttempts = 3

    while ($attempt -lt $maxAttempts) {
        $attempt++

        try {
            Write-Host "[GET ] $Url"
            Invoke-WebRequest `
                -Uri $Url `
                -OutFile $Destination `
                -UseBasicParsing

            if ((Get-Item $Destination).Length -le 0) {
                throw "File hasil download kosong."
            }

            Write-Host "[ OK ] $Destination"
            return
        }
        catch {
            if (Test-Path $Destination) {
                Remove-Item $Destination -Force -ErrorAction SilentlyContinue
            }

            if ($attempt -ge $maxAttempts) {
                throw
            }

            Write-Host "[RETRY $attempt/$maxAttempts] $Url"
            Start-Sleep -Seconds 2
        }
    }
}

Write-Host ""
Write-Host "============================================================"
Write-Host " CBT-HERO - Install Frontend Assets"
Write-Host " Project : $ProjectRoot"
Write-Host "============================================================"
Write-Host ""

# ------------------------------------------------------------------
# Bootstrap 5.3.8
# ------------------------------------------------------------------
Download-Asset `
    "https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" `
    (Join-Path $VendorRoot "bootstrap\css\bootstrap.min.css")

Download-Asset `
    "https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" `
    (Join-Path $VendorRoot "bootstrap\js\bootstrap.bundle.min.js")

# ------------------------------------------------------------------
# Bootstrap Icons 1.13.1
# ------------------------------------------------------------------
Download-Asset `
    "https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" `
    (Join-Path $VendorRoot "bootstrap-icons\font\bootstrap-icons.min.css")

Download-Asset `
    "https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/fonts/bootstrap-icons.woff2" `
    (Join-Path $VendorRoot "bootstrap-icons\font\fonts\bootstrap-icons.woff2")

Download-Asset `
    "https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/fonts/bootstrap-icons.woff" `
    (Join-Path $VendorRoot "bootstrap-icons\font\fonts\bootstrap-icons.woff")

# ------------------------------------------------------------------
# DataTables 2.x - 2.3.8
# Sengaja tetap major 2 sesuai baseline frontend CBT-HERO.
# ------------------------------------------------------------------
Download-Asset `
    "https://cdn.datatables.net/2.3.8/css/dataTables.bootstrap5.min.css" `
    (Join-Path $VendorRoot "datatables\css\dataTables.bootstrap5.min.css")

Download-Asset `
    "https://cdn.datatables.net/2.3.8/js/dataTables.min.js" `
    (Join-Path $VendorRoot "datatables\js\dataTables.min.js")

Download-Asset `
    "https://cdn.datatables.net/2.3.8/js/dataTables.bootstrap5.min.js" `
    (Join-Path $VendorRoot "datatables\js\dataTables.bootstrap5.min.js")

# ------------------------------------------------------------------
# SweetAlert2 11.26.25
# ------------------------------------------------------------------
Download-Asset `
    "https://cdn.jsdelivr.net/npm/sweetalert2@11.26.25/dist/sweetalert2.min.css" `
    (Join-Path $VendorRoot "sweetalert2\sweetalert2.min.css")

Download-Asset `
    "https://cdn.jsdelivr.net/npm/sweetalert2@11.26.25/dist/sweetalert2.all.min.js" `
    (Join-Path $VendorRoot "sweetalert2\sweetalert2.all.min.js")

# ------------------------------------------------------------------
# Chart.js 4.5.1
# ------------------------------------------------------------------
Download-Asset `
    "https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js" `
    (Join-Path $VendorRoot "chartjs\chart.umd.min.js")

# ------------------------------------------------------------------
# SortableJS 1.15.7
# ------------------------------------------------------------------
Download-Asset `
    "https://cdn.jsdelivr.net/npm/sortablejs@1.15.7/Sortable.min.js" `
    (Join-Path $VendorRoot "sortablejs\Sortable.min.js")

# ------------------------------------------------------------------
# Dexie 4.4.6 - IndexedDB runtime
# ------------------------------------------------------------------
Download-Asset `
    "https://cdn.jsdelivr.net/npm/dexie@4.4.6/dist/dexie.min.js" `
    (Join-Path $VendorRoot "dexie\dexie.min.js")

# ------------------------------------------------------------------
# DOMPurify 3.4.16
# ------------------------------------------------------------------
Download-Asset `
    "https://cdn.jsdelivr.net/npm/dompurify@3.4.16/dist/purify.min.js" `
    (Join-Path $VendorRoot "dompurify\purify.min.js")

# ------------------------------------------------------------------
# KaTeX 0.18.7
# Dipin ke stable 0.18.x yang sudah terpakai luas.
# ------------------------------------------------------------------
Download-Asset `
    "https://cdn.jsdelivr.net/npm/katex@0.18.7/dist/katex.min.css" `
    (Join-Path $VendorRoot "katex\katex.min.css")

Download-Asset `
    "https://cdn.jsdelivr.net/npm/katex@0.18.7/dist/katex.min.js" `
    (Join-Path $VendorRoot "katex\katex.min.js")

Download-Asset `
    "https://cdn.jsdelivr.net/npm/katex@0.18.7/dist/contrib/auto-render.min.js" `
    (Join-Path $VendorRoot "katex\auto-render.min.js")

$KaTeXFonts = @(
    "KaTeX_AMS-Regular",
    "KaTeX_Caligraphic-Bold",
    "KaTeX_Caligraphic-Regular",
    "KaTeX_Fraktur-Bold",
    "KaTeX_Fraktur-Regular",
    "KaTeX_Main-Bold",
    "KaTeX_Main-BoldItalic",
    "KaTeX_Main-Italic",
    "KaTeX_Main-Regular",
    "KaTeX_Math-BoldItalic",
    "KaTeX_Math-Italic",
    "KaTeX_SansSerif-Bold",
    "KaTeX_SansSerif-Italic",
    "KaTeX_SansSerif-Regular",
    "KaTeX_Script-Regular",
    "KaTeX_Size1-Regular",
    "KaTeX_Size2-Regular",
    "KaTeX_Size3-Regular",
    "KaTeX_Size4-Regular",
    "KaTeX_Typewriter-Regular"
)

foreach ($Font in $KaTeXFonts) {
    Download-Asset `
        "https://cdn.jsdelivr.net/npm/katex@0.18.7/dist/fonts/$Font.woff2" `
        (Join-Path $VendorRoot "katex\fonts\$Font.woff2")
}

# ------------------------------------------------------------------
# Panzoom 4.6.2
# ------------------------------------------------------------------
Download-Asset `
    "https://cdn.jsdelivr.net/npm/@panzoom/panzoom@4.6.2/dist/panzoom.min.js" `
    (Join-Path $VendorRoot "panzoom\panzoom.min.js")

# ------------------------------------------------------------------
# Verify
# ------------------------------------------------------------------
$Required = @(
    "bootstrap\css\bootstrap.min.css",
    "bootstrap\js\bootstrap.bundle.min.js",
    "bootstrap-icons\font\bootstrap-icons.min.css",
    "bootstrap-icons\font\fonts\bootstrap-icons.woff2",
    "datatables\css\dataTables.bootstrap5.min.css",
    "datatables\js\dataTables.min.js",
    "datatables\js\dataTables.bootstrap5.min.js",
    "sweetalert2\sweetalert2.min.css",
    "sweetalert2\sweetalert2.all.min.js",
    "chartjs\chart.umd.min.js",
    "sortablejs\Sortable.min.js",
    "dexie\dexie.min.js",
    "dompurify\purify.min.js",
    "katex\katex.min.css",
    "katex\katex.min.js",
    "katex\auto-render.min.js",
    "panzoom\panzoom.min.js"
)

$Missing = @()

foreach ($RelativePath in $Required) {
    $FullPath = Join-Path $VendorRoot $RelativePath

    if (-not (Test-Path $FullPath)) {
        $Missing += $FullPath
    }
}

Write-Host ""
if ($Missing.Count -gt 0) {
    Write-Host "ERROR: Ada asset wajib yang belum tersedia:" -ForegroundColor Red
    $Missing | ForEach-Object { Write-Host " - $_" -ForegroundColor Red }
    exit 1
}

Write-Host "Frontend assets CBT-HERO lengkap." -ForegroundColor Green
Write-Host ""
Write-Host "Selanjutnya:"
Write-Host "  git add assets/vendor tools/install_frontend_assets.ps1"
Write-Host "  git status"
Write-Host ""
