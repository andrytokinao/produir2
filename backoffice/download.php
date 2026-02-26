<?php
require_once __DIR__ . '/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die('ID invalide');
}

$stmt = $pdo->prepare('SELECT original_filename, full_json FROM submissions WHERE id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    die('Enregistrement introuvable');
}

$filename = $row['original_filename'] ?: "submission_$id.json";
header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="'.basename($filename).'"');
echo $row['full_json'];
exit;
