<?php
/**
 * export.php – Export des soumissions au format Excel (CSV UTF-8 avec BOM)
 * Respecte les filtres : ?q=... &dist=...
 */
require_once __DIR__ . '/db.php';

// ---- Filtres (même logique que index.php) -----------------------------------
$search   = trim($_GET['q']    ?? '');
$distrika = trim($_GET['dist'] ?? '');

$where  = [];
$params = [];

if ($search !== '') {
    $where[] = "(
        JSON_UNQUOTE(JSON_EXTRACT(full_json, '$.metadata.enqueteur')) LIKE ?
     OR JSON_UNQUOTE(JSON_EXTRACT(full_json, '$.data.sec0_identification.laharana_fisy')) LIKE ?
     OR TRIM(CONCAT(
            COALESCE(JSON_UNQUOTE(JSON_EXTRACT(full_json, '$.data.sec1_pap.anarana')),''),
            ' ',
            COALESCE(JSON_UNQUOTE(JSON_EXTRACT(full_json, '$.data.sec1_pap.fanampiny')),'')
        )) LIKE ?
    )";
    $like   = "%$search%";
    $params = array_merge($params, [$like, $like, $like]);
}

if ($distrika !== '') {
    $where[]  = "JSON_UNQUOTE(JSON_EXTRACT(full_json, '$.data.sec0_identification.distrika')) = ?";
    $params[] = $distrika;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ---- Récupérer TOUTES les lignes correspondantes (sans pagination) ----------
$sql  = "SELECT id, submitted_at, original_filename, full_json FROM submissions $whereSql ORDER BY submitted_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---- Fonction : aplatir récursivement un tableau JSON ----------------------
/**
 * Transforme un tableau JSON imbriqué en tableau plat clé=>valeur.
 * Les valeurs base64 (images) sont remplacées par '[image]'.
 * Les booléens sont convertis en 'Oui'/'Non'.
 */
function flattenJson(array $node, string $prefix = '', array &$result = []): array
{
    foreach ($node as $k => $v) {
        $fullKey = $prefix !== '' ? $prefix . '.' . $k : (string)$k;
        if (is_array($v)) {
            flattenJson($v, $fullKey, $result);
        } else {
            if ($v === true)                                          $v = 'Oui';
            elseif ($v === false)                                     $v = 'Non';
            elseif ($v === null)                                      $v = '';
            elseif (is_string($v) && str_starts_with($v, 'data:'))   $v = '[image]';
            else                                                      $v = (string)$v;
            $result[$fullKey] = $v;
        }
    }
    return $result;
}

// ---- 1re passe : collecter toutes les clés JSON uniques --------------------
$allJsonKeys = [];
$flatRows    = [];   // cache pour la 2e passe

foreach ($rows as $i => $row) {
    $json = json_decode($row['full_json'] ?? '{}', true) ?? [];
    $flat = flattenJson($json);
    $flatRows[$i] = $flat;
    foreach (array_keys($flat) as $k) {
        $allJsonKeys[$k] = true;
    }
}

// Tri : metadata.* en premier, data.* / questions.* ensuite, reste après
uksort($allJsonKeys, function (string $a, string $b): int {
    $order = static function (string $k): int {
        if (str_starts_with($k, 'metadata'))  return 0;
        if (str_starts_with($k, 'data'))      return 1;
        if (str_starts_with($k, 'questions')) return 2;
        return 3;
    };
    $cmp = $order($a) <=> $order($b);
    return $cmp !== 0 ? $cmp : strcmp($a, $b);
});
$allJsonKeys = array_keys($allJsonKeys);

// ---- Nom du fichier ---------------------------------------------------------
$filterParts = [];
if ($distrika !== '') $filterParts[] = preg_replace('/[^a-zA-Z0-9_-]/', '_', $distrika);
if ($search   !== '') $filterParts[] = 'recherche';
$suffix   = $filterParts ? '_' . implode('_', $filterParts) : '';
$filename = 'PRODUIR2_export' . $suffix . '_' . date('Ymd_His') . '.csv';

// ---- Envoi des en-têtes HTTP ------------------------------------------------
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Ouvrir le flux de sortie
$out = fopen('php://output', 'w');

// BOM UTF-8 pour que Excel reconnaisse l'encodage correctement
fwrite($out, "\xEF\xBB\xBF");

// ---- En-têtes de colonnes --------------------------------------------------
// Colonnes fixes BD + toutes les clés JSON découvertes dynamiquement
$dbHeaders = ['ID', 'Date soumission (BD)', 'Fichier source'];
fputcsv($out, array_merge($dbHeaders, $allJsonKeys), ';');

// ---- Lignes de données -----------------------------------------------------
foreach ($rows as $i => $row) {
    $flat = $flatRows[$i];
    $line = [
        (string)($row['id']                ?? ''),
        (string)($row['submitted_at']      ?? ''),
        (string)($row['original_filename'] ?? ''),
    ];
    foreach ($allJsonKeys as $key) {
        $line[] = $flat[$key] ?? '';
    }
    fputcsv($out, $line, ';');
}

fclose($out);
exit;
