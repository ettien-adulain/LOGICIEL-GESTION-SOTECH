@echo off
echo ========================================
echo    CORRECTION DES CHEMINS VENDOR
echo ========================================
echo.

echo Situation actuelle :
echo - DOMPDF installé dans : ..\vendor\dompdf
echo - Code cherche dans : vendor\dompdf
echo.

echo Options disponibles :
echo 1. Créer un lien symbolique (recommandé)
echo 2. Copier DOMPDF dans le dossier local
echo 3. Installer DOMPDF dans le dossier local
echo.

set /p choice="Choisissez une option (1-3) : "

if "%choice%"=="1" (
    echo.
    echo Création du lien symbolique...
    
    REM Vérifier si le dossier vendor local existe
    if not exist "vendor" (
        mkdir vendor
    )
    
    REM Créer le lien symbolique
    mklink /D "vendor\dompdf" "..\vendor\dompdf"
    
    if %errorlevel% equ 0 (
        echo ✅ Lien symbolique créé avec succès !
    ) else (
        echo ❌ Erreur lors de la création du lien symbolique
        echo Essayez de lancer ce script en tant qu'administrateur
    )
    
) else if "%choice%"=="2" (
    echo.
    echo Copie de DOMPDF...
    
    REM Créer le dossier vendor local s'il n'existe pas
    if not exist "vendor" (
        mkdir vendor
    )
    
    REM Copier DOMPDF
    xcopy "..\vendor\dompdf" "vendor\dompdf" /E /I /Y
    
    if %errorlevel% equ 0 (
        echo ✅ DOMPDF copié avec succès !
    ) else (
        echo ❌ Erreur lors de la copie
    )
    
) else if "%choice%"=="3" (
    echo.
    echo Installation de DOMPDF dans le dossier local...
    
    composer require dompdf/dompdf
    
    if %errorlevel% equ 0 (
        echo ✅ DOMPDF installé localement !
    ) else (
        echo ❌ Erreur lors de l'installation
    )
    
) else (
    echo Option invalide
    pause
    exit /b 1
)

echo.
echo Test de l'installation...
echo Ouvrez : http://localhost/mis_a_jour%%20OFFICIELLE/10_10_2024/10_10_2024/test_dompdf.php
echo.

pause 