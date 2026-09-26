# Jalankan dari root cbt-hero setelah mengekstrak patch revisi.
$ErrorActionPreference = 'Stop'
$root = (Get-Location).Path
if (-not (Test-Path -LiteralPath (Join-Path $root 'composer.json')) -or
    -not (Test-Path -LiteralPath (Join-Path $root 'app/Config/Routes.php'))) {
    throw 'Jalankan script dari root proyek cbt-hero.'
}
$obsolete = @(
    'app/Controllers/Api/Manager/Master/PeriodeController.php',
    'app/Controllers/Manager/Master/PeriodeController.php',
    'app/Models/PeriodeModel.php',
    'app/Services/PeriodeService.php',
    'app/Views/manager/master/periode/index.php',
    'assets/js/manager-periode.js'
)
foreach ($relative in $obsolete) {
    $file = Join-Path $root $relative
    if (Test-Path -LiteralPath $file) {
        Remove-Item -LiteralPath $file -Force
        Write-Host "Removed $relative"
    }
}
