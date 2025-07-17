Write-Host "Starting Rental Auto-Checker..."
while ($true) {
    php artisan rentals:check-expired
    Start-Sleep -Seconds 30
}