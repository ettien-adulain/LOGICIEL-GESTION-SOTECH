Write-Host "Installation de DOMPDF via Composer..." -ForegroundColor Green
Write-Host ""

# Vérifier si Composer est installé
try {
    $composerVersion = composer --version 2>$null
    if ($LASTEXITCODE -ne 0) {
        throw "Composer non trouvé"
    }
    Write-Host "Composer détecté: $composerVersion" -ForegroundColor Yellow
} catch {
    Write-Host "ERREUR: Composer n'est pas installé ou n'est pas dans le PATH" -ForegroundColor Red
    Write-Host "Veuillez installer Composer depuis https://getcomposer.org/" -ForegroundColor Red
    Read-Host "Appuyez sur Entrée pour continuer"
    exit 1
}

Write-Host "Installation de DOMPDF..." -ForegroundColor Yellow
composer require dompdf/dompdf

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "SUCCÈS: DOMPDF a été installé avec succès!" -ForegroundColor Green
    Write-Host "Vous pouvez maintenant utiliser le système d'impression." -ForegroundColor Green
} else {
    Write-Host ""
    Write-Host "ERREUR: Échec de l'installation de DOMPDF" -ForegroundColor Red
    Write-Host "Vérifiez votre connexion internet et les permissions du dossier." -ForegroundColor Red
}

Read-Host "Appuyez sur Entrée pour continuer" 