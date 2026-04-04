# ------------------------------------------------------
# Run this Powershell script to prepare OSPOS for deployment
# via GitHub Actions.
# ------------------------------------------------------
# This script executes the following steps:
# 1. Frontend Assets (NPM/Gulp)
# 2. Deployment Package (Gulp package-uncompressed)y
#
# NOTE: It does not run `composer install` and does not include
# the `vendor` directory, as that is expected to run on the server.
# ------------------------------------------------------

$ErrorActionPreference = "Stop"

Write-Output "============================================================================="
Write-Output "Step 1: Installing Frontend Dependencies and Building Assets"
Write-Output "============================================================================="
npm install
npm run build

Write-Output ""
Write-Output "============================================================================="
Write-Output "Step 2: Copying files to deployment directory (No Compression)"
Write-Output "============================================================================="
$deployPath = "C:\Users\diego\Documents\Open_POS\Despliegues\web_app"
if (Test-Path $deployPath) {
    # Remove all contents except git-related files and folders (.git, .gitignore, etc.)
    Get-ChildItem -Path $deployPath -Force | Where-Object { $_.Name -notmatch '^\.git' } | Remove-Item -Recurse -Force
}

# We use a custom gulp task in gulpfile.js that excludes the 'vendor' folder
# and instead copies files directly to dist/deployment.
npx gulp package-uncompressed

Write-Output ""
Write-Output "============================================================================="
Write-Output "Build Complete!"
Write-Output "The uncompressed deployment files are located in the 'dist/deployment' folder."
Write-Output "You can use this folder to create a separate GitHub repo."
Write-Output "============================================================================="
