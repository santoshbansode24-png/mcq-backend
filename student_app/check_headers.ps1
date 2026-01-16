
$assetsPath = "C:\xampp\htdocs\veeru\student_app\assets"
$files = Get-ChildItem -Path $assetsPath -Filter *.png

foreach ($file in $files) {
    $bytes = Get-Content $file.FullName -Encoding Byte -TotalCount 8
    $hex = ($bytes | ForEach-Object { $_.ToString("X2") }) -join " "
    
    if ($hex -match "^89 50 4E 47 0D 0A 1A 0A") {
        Write-Host "OK: $($file.Name) is a valid PNG." -ForegroundColor Green
    }
    elseif ($hex -match "^FF D8 FF") {
        Write-Host "FAIL: $($file.Name) is a JPG disguised as PNG!" -ForegroundColor Red
    }
    else {
        Write-Host "WARN: $($file.Name) has unknown header: $hex" -ForegroundColor Yellow
    }
}
