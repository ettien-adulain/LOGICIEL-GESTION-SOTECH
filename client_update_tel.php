<?php
session_start();
include('db/connecting.php');
header('Content-Type: application/json');
if (!isset($_SESSION['nom_utilisateur'])) {
    echo json_encode(['success'=>false, 'message'=>'Non autorisé']);
    exit;
}
$id_client = isset($_POST['id_client']) ? intval($_POST['id_client']) : 0;
$tel = isset($_POST['tel']) ? trim($_POST['tel']) : '';
if (!$id_client || !$tel) {
    echo json_encode(['success'=>false, 'message'=>'ID client et téléphone requis']);
    exit;
}
try {
    $stmt = $cnx->prepare("UPDATE client SET Telephone = ? WHERE IDCLIENT = ?");
    $stmt->execute([$tel, $id_client]);
    echo json_encode(['success'=>true]);
} catch (Exception $e) {
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
} 