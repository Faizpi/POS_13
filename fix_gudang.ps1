$dir = "c:\Projek Website\Hibiscusefsya POS\sales_hibiscusefsya_laravel13\app\Filament"
$files = Get-ChildItem -Path $dir -Filter "*.php" -Recurse

foreach ($file in $files) {
    $content = Get-Content -Path $file.FullName -Raw
    
    # Replace ->getCurrentGudang( with ?->getCurrentGudang(
    # Also replace ->getCurrentGudang() with ?->getCurrentGudang()
    # But only if it's not already ?->getCurrentGudang
    $newContent = [regex]::Replace($content, '(?<!\?)->getCurrentGudang', '?->getCurrentGudang')
    
    if ($content -cne $newContent) {
        Set-Content -Path $file.FullName -Value $newContent -NoNewline
        Write-Host "Fixed $($file.Name)"
    }
}
