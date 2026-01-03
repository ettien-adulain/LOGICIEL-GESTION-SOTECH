<?php
/**
 * Script d'optimisation des images de fond
 * Ce script optimise les images fond1.jpg à fond6.jpg pour améliorer les performances
 */

// Configuration
$imageDir = __DIR__;
$images = ['fond1.jpg', 'fond2.jpg', 'fond3.jpg', 'fond4.jpg', 'fond5.jpg', 'fond6.jpg'];
$maxWidth = 1920; // Largeur maximale
$maxHeight = 1080; // Hauteur maximale
$quality = 85; // Qualité JPEG (0-100)

echo "<h2>🚀 Optimisation des Images de Fond SOTECH</h2>\n";

foreach ($images as $imageName) {
    $imagePath = $imageDir . '/' . $imageName;
    
    if (!file_exists($imagePath)) {
        echo "❌ Image non trouvée: $imageName<br>\n";
        continue;
    }
    
    // Obtenir les informations de l'image
    $imageInfo = getimagesize($imagePath);
    if (!$imageInfo) {
        echo "❌ Impossible de lire l'image: $imageName<br>\n";
        continue;
    }
    
    $originalWidth = $imageInfo[0];
    $originalHeight = $imageInfo[1];
    $originalSize = filesize($imagePath);
    
    echo "<h3>📸 Optimisation de $imageName</h3>\n";
    echo "Taille originale: {$originalWidth}x{$originalHeight}px (" . formatBytes($originalSize) . ")<br>\n";
    
    // Créer une sauvegarde
    $backupPath = $imageDir . '/backup_' . $imageName;
    if (!copy($imagePath, $backupPath)) {
        echo "⚠️ Impossible de créer une sauvegarde<br>\n";
        continue;
    }
    
    // Charger l'image selon son type
    switch ($imageInfo[2]) {
        case IMAGETYPE_JPEG:
            $sourceImage = imagecreatefromjpeg($imagePath);
            break;
        case IMAGETYPE_PNG:
            $sourceImage = imagecreatefrompng($imagePath);
            break;
        case IMAGETYPE_GIF:
            $sourceImage = imagecreatefromgif($imagePath);
            break;
        default:
            echo "❌ Format d'image non supporté<br>\n";
            continue 2;
    }
    
    if (!$sourceImage) {
        echo "❌ Impossible de charger l'image source<br>\n";
        continue;
    }
    
    // Calculer les nouvelles dimensions
    $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
    $newWidth = intval($originalWidth * $ratio);
    $newHeight = intval($originalHeight * $ratio);
    
    // Créer la nouvelle image redimensionnée
    $newImage = imagecreatetruecolor($newWidth, $newHeight);
    
    // Préserver la transparence pour PNG
    if ($imageInfo[2] == IMAGETYPE_PNG) {
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
        imagefill($newImage, 0, 0, $transparent);
    }
    
    // Redimensionner l'image
    imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
    
    // Sauvegarder l'image optimisée
    $success = false;
    switch ($imageInfo[2]) {
        case IMAGETYPE_JPEG:
            $success = imagejpeg($newImage, $imagePath, $quality);
            break;
        case IMAGETYPE_PNG:
            $success = imagepng($newImage, $imagePath, 9);
            break;
        case IMAGETYPE_GIF:
            $success = imagegif($newImage, $imagePath);
            break;
    }
    
    if ($success) {
        $newSize = filesize($imagePath);
        $savings = $originalSize - $newSize;
        $savingsPercent = round(($savings / $originalSize) * 100, 1);
        
        echo "✅ Optimisé: {$newWidth}x{$newHeight}px (" . formatBytes($newSize) . ")<br>\n";
        echo "💾 Économie: " . formatBytes($savings) . " ({$savingsPercent}%)<br>\n";
        echo "📁 Sauvegarde: backup_$imageName<br>\n";
    } else {
        echo "❌ Erreur lors de la sauvegarde<br>\n";
        // Restaurer la sauvegarde
        copy($backupPath, $imagePath);
    }
    
    // Libérer la mémoire
    imagedestroy($sourceImage);
    imagedestroy($newImage);
    
    echo "<br>\n";
}

echo "<h3>🎯 Recommandations</h3>\n";
echo "• Les images sont maintenant optimisées pour le web<br>\n";
echo "• Taille maximale: {$maxWidth}x{$maxHeight}px<br>\n";
echo "• Qualité JPEG: {$quality}%<br>\n";
echo "• Les sauvegardes sont dans le dossier backup_*<br>\n";
echo "• Vous pouvez supprimer les sauvegardes après vérification<br>\n";

/**
 * Formate les bytes en unités lisibles
 */
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>
