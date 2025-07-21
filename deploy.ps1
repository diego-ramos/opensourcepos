# Define source and destination paths
$sourcePath = Get-Location
$tempCopyPath = Join-Path $env:TEMP "project_temp_$(Get-Random)"
$destinationZipPath = "C:\Users\diego\Documents\Open_POS\opos_darp_3.4.1.zip"  # <-- CHANGE THIS

# List of items to copy (excluding .env)
$itemsToCopy = @(
    "LICENSE",
    "README.md",
    "SECURITY.md",
    "UPGRADE.md",
    "vendor",
    "writable",
    "public",
    "tests",
    "app",
    ".htaccess",
    "CODE_OF_CONDUCT.md"
)

# Create temp directory
New-Item -ItemType Directory -Path $tempCopyPath -Force | Out-Null

# Copy items
foreach ($item in $itemsToCopy) {
    $src = Join-Path $sourcePath $item
    if (Test-Path $src) {
        Copy-Item $src -Destination $tempCopyPath -Recurse -Force
    } else {
        Write-Warning "Item not found: $item"
    }
}

# Replace .env with copy of .env-example
$envExample = Join-Path $sourcePath ".env-example"
$envDest = Join-Path $tempCopyPath ".env"

if (Test-Path $envExample) {
    Copy-Item $envExample -Destination $envDest -Force
} else {
    Write-Warning ".env-example not found, .env will not be created."
}

# Compress to zip
Compress-Archive -Path "$tempCopyPath\*" -DestinationPath $destinationZipPath -Force

# Clean up temp folder
Remove-Item -Path $tempCopyPath -Recurse -Force

Write-Output "Archive created at: $destinationZipPath"
