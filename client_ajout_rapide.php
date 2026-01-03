<?php
session_start();
include('db/connecting.php');
header('Content-Type: application/json');
if (!isset($_SESSION['nom_utilisateur'])) {
    echo json_encode(['success'=>false, 'message'=>'Non autorisé']);
    exit;
}
$nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
$tel = isset($_POST['tel']) ? trim($_POST['tel']) : '';
if (!$nom || !$tel) {
    echo json_encode(['success'=>false, 'message'=>'Nom et téléphone requis']);
    exit;
}
try {
    $stmt = $cnx->prepare("INSERT INTO client (NomPrenomClient, Telephone) VALUES (?, ?)");
    $stmt->execute([$nom, $tel]);
    $id = $cnx->lastInsertId();
    echo json_encode(['success'=>true, 'id'=>$id]);
} catch (Exception $e) {
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
} 