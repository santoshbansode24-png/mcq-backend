
Add-Type -AssemblyName System.Drawing

function Convert-ToPng {
    param (
        [string]$Path
    )
    
    if (Test-Path $Path) {
        try {
            # Load the image (even if extension is wrong, FromFile usually handles it)
            $img = [System.Drawing.Image]::FromFile($Path)
            
            # Check if it's already PNG format
            if ($img.RawFormat.Guid -eq [System.Drawing.Imaging.ImageFormat]::Png.Guid) {
                Write-Host "Skipping $Path - It is already a valid PNG."
                $img.Dispose()
                return
            }
            
            Write-Host "Converting $Path to real PNG..."
            
            # Save to a temp file as PNG
            $tempPath = $Path + ".temp.png"
            $img.Save($tempPath, [System.Drawing.Imaging.ImageFormat]::Png)
            $img.Dispose()
            
            # Replace original
            Remove-Item $Path -Force
            Move-Item $tempPath $Path -Force
            Write-Host "Successfully converted $Path"
        }
        catch {
            Write-Error "Failed to convert $Path : $_"
        }
    }
    else {
        Write-Warning "File not found: $Path"
    }
}

# Convert the specific files identified by Expo Doctor
Convert-ToPng "C:\xampp\htdocs\veeru\student_app\assets\icon.png"
Convert-ToPng "C:\xampp\htdocs\veeru\student_app\assets\adaptive-icon.png"
Convert-ToPng "C:\xampp\htdocs\veeru\student_app\assets\notification-icon.png"
Convert-ToPng "C:\xampp\htdocs\veeru\student_app\assets\veeru_splash_transparent.png"
