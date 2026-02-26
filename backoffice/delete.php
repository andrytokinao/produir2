<?php
require_once __DIR__ . '/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die('ID invalide');
}

$stmt = $pdo->prepare('DELETE FROM submissions WHERE id = ?');
$stmt->execute([$id]);

header('Location: index.php?msg=deleted');
exit;
