@echo off
echo Installation de DOMPDF via Composer...
echo.

REM Vérifier si Composer est installé
composer --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ERREUR: Composer n'est pas installé ou n'est pas dans le PATH
    echo Veuillez installer Composer depuis https://getcomposer.org/
    pause
    exit /b 1
)

echo Composer detecte. Installation de DOMPDF...
composer require dompdf/dompdf

if %errorlevel% equ 0 (
    echo.
    echo SUCCES: DOMPDF a ete installe avec succes!
    echo Vous pouvez maintenant utiliser le systeme d'impression.
) else (
    echo.
    echo ERREUR: Echec de l'installation de DOMPDF
    echo Verifiez votre connexion internet et les permissions du dossier.
)

pause 