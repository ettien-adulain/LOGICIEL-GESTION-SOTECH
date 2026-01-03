<?php
/**
 * Script d'optimisation rapide des images de fond
 * Version optimisée pour les très grandes images
 */

// Augmenter la limite de mémoire
ini_set('memory_limit', '512M');

// Configuration
$imageDir = __DIR__;
$images = ['fond1.jpg', 'fond2.jpg', 'fond3.jpg', 'fond4.jpg', 'fond5.jpg', 'fond6.jpg'];
$maxWidth = 1920;
$maxHeight = 1080;
$quality = 80; // Qualité réduite pour de meilleures performances

echo "<h2>🚀 Optimisation Rapide des Images SOTECH</h2>\n";

foreach ($images as $imageName) {
    $imagePath = $imageDir . '/' . $imageName;
    
    if (!file_exists($imagePath)) {
        echo "❌ Image non trouvée: $imageName<br>\n";
        continue;
    }
    
    $originalSize = filesize($imagePath);
    echo "<h3>📸 Optimisation de $imageName</h3>\n";
    echo "Taille originale: " . formatBytes($originalSize) . "<br>\n";
    
    // Créer une sauvegarde
    $backupPath = $imageDir . '/backup_' . $imageName;
    if (!copy($imagePath, $backupPath)) {
        echo "⚠️ Impossible de créer une sauvegarde<br>\n";
        continue;
    }
    
    // Utiliser ImageMagick si disponible, sinon GD avec gestion mémoire
    if (extension_loaded('imagick')) {
        optimizeWithImageMagick($imagePath, $maxWidth, $maxHeight, $quality);
    } else {
        optimizeWithGD($imagePath, $maxWidth, $maxHeight, $quality);
    }
    
    $newSize = filesize($imagePath);
    $savings = $originalSize - $newSize;
    $savingsPercent = round(($savings / $originalSize) * 100, 1);
    
    echo "✅ Optimisé: " . formatBytes($newSize) . "<br>\n";
    echo "💾 Économie: " . formatBytes($savings) . " ({$savingsPercent}%)<br>\n";
    echo "📁 Sauvegarde: backup_$imageName<br><br>\n";
}

function optimizeWithImageMagick($imagePath, $maxWidth, $maxHeight, $quality) {
    try {
        $imagick = new Imagick($imagePath);
        $imagick->setImageCompressionQuality($quality);
        $imagick->resizeImage($maxWidth, $maxHeight, Imagick::FILTER_LANCZOS, 1, true);
        $imagick->writeImage($imagePath);
        $imagick->clear();
        $imagick->destroy();
    } catch (Exception $e) {
        echo "❌ Erreur ImageMagick: " . $e->getMessage() . "<br>\n";
    }
}

function optimizeWithGD($imagePath, $maxWidth, $maxHeight, $quality) {
    // Obtenir les informations de l'image
    $imageInfo = getimagesize($imagePath);
    if (!$imageInfo) {
        echo "❌ Impossible de lire l'image<br>\n";
        return;
    }
    
    $originalWidth = $imageInfo[0];
    $originalHeight = $imageInfo[1];
    
    // Calculer les nouvelles dimensions
    $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
    $newWidth = intval($originalWidth * $ratio);
    $newHeight = intval($originalHeight * $ratio);
    
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
            echo "❌ Format non supporté<br>\n";
            return;
    }
    
    if (!$sourceImage) {
        echo "❌ Impossible de charger l'image<br>\n";
        return;
    }
    
    // Créer la nouvelle image
    $newImage = imagecreatetruecolor($newWidth, $newHeight);
    
    // Préserver la transparence pour PNG
    if ($imageInfo[2] == IMAGETYPE_PNG) {
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
        imagefill($newImage, 0, 0, $transparent);
    }
    
    // Redimensionner
    imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
    
    // Sauvegarder
    switch ($imageInfo[2]) {
        case IMAGETYPE_JPEG:
            imagejpeg($newImage, $imagePath, $quality);
            break;
        case IMAGETYPE_PNG:
            imagepng($newImage, $imagePath, 9);
            break;
        case IMAGETYPE_GIF:
            imagegif($newImage, $imagePath);
            break;
    }
    
    // Libérer la mémoire
    imagedestroy($sourceImage);
    imagedestroy($newImage);
}

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

echo "<h3>🎯 Optimisation terminée !</h3>\n";
echo "• Images optimisées pour le web<br>\n";
echo "• Taille maximale: {$maxWidth}x{$maxHeight}px<br>\n";
echo "• Qualité: {$quality}%<br>\n";
echo "• Les sauvegardes sont dans backup_*<br>\n";
?>
