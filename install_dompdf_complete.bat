@echo off
echo ========================================
echo    INSTALLATION COMPLETE DE DOMPDF
echo ========================================
echo.

REM Vérifier si on est dans le bon dossier
if not exist "vendor" (
    echo ERREUR: Dossier vendor non trouvé !
    echo Assurez-vous d'être dans le dossier : 10_10_2024\10_10_2024
    echo.
    echo Dossier actuel :
    cd
    pause
    exit /b 1
)

echo ✅ Dossier vendor trouvé
echo.

REM Vérifier si Composer est installé
composer --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ Composer n'est pas installé ou n'est pas dans le PATH
    echo.
    echo Veuillez installer Composer depuis : https://getcomposer.org/
    echo Ou télécharger Composer-Setup.exe
    echo.
    pause
    exit /b 1
)

echo ✅ Composer détecté
composer --version
echo.

REM Vérifier si DOMPDF est déjà installé
if exist "vendor\dompdf" (
    echo ⚠️  DOMPDF semble déjà être installé
    echo Voulez-vous le réinstaller ? (O/N)
    set /p choice=
    if /i "%choice%"=="O" (
        echo Suppression de l'ancienne installation...
        rmdir /s /q "vendor\dompdf" 2>nul
    ) else (
        echo Installation annulée
        pause
        exit /b 0
    )
)

echo.
echo Installation de DOMPDF...
echo.

REM Installer DOMPDF
composer require dompdf/dompdf

if %errorlevel% equ 0 (
    echo.
    echo ✅ DOMPDF installé avec succès !
    echo.
    
    REM Vérifier l'installation
    echo Vérification de l'installation...
    if exist "vendor\dompdf\dompdf\src\Dompdf.php" (
        echo ✅ Fichier Dompdf.php trouvé
    ) else (
        echo ❌ Fichier Dompdf.php manquant
    )
    
    if exist "vendor\dompdf\dompdf\src\Options.php" (
        echo ✅ Fichier Options.php trouvé
    ) else (
        echo ❌ Fichier Options.php manquant
    )
    
    if exist "vendor\autoload.php" (
        echo ✅ Autoloader Composer trouvé
    ) else (
        echo ❌ Autoloader Composer manquant
    )
    
    echo.
    echo ========================================
    echo    INSTALLATION TERMINEE
    echo ========================================
    echo.
    echo Vous pouvez maintenant :
    echo 1. Tester l'installation : http://localhost/mis_a_jour%%20OFFICIELLE/10_10_2024/10_10_2024/test_dompdf.php
    echo 2. Utiliser l'impression d'inventaire
    echo.
    
) else (
    echo.
    echo ❌ ERREUR lors de l'installation de DOMPDF
    echo.
    echo Solutions possibles :
    echo 1. Vérifiez votre connexion internet
    echo 2. Vérifiez les permissions du dossier
    echo 3. Essayez : composer update
    echo 4. Essayez : composer install
    echo.
)

pause 