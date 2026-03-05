<?php
/**
 * export_excel.php – Export de TOUS les champs JSON vers Excel
 * Format : SpreadsheetML (XML 2003) – aucune extension PHP requise.
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

// ---- Récupérer TOUTES les lignes -------------------------------------------
$sql  = "SELECT id, submitted_at, original_filename, full_json
         FROM submissions $whereSql ORDER BY submitted_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---- Aplatir récursivement le JSON ----------------------------------------
function flattenJson(array $node, string $prefix = '', array &$result = []): array
{
    foreach ($node as $k => $v) {
        $fullKey = $prefix !== '' ? $prefix . '.' . $k : (string)$k;
        if (is_array($v)) {
            flattenJson($v, $fullKey, $result);
        } else {
            if ($v === true)                                           $v = 'Oui';
            elseif ($v === false)                                      $v = 'Non';
            elseif ($v === null)                                       $v = '';
            elseif (is_string($v) && str_starts_with($v, 'data:'))    $v = '[image]';
            else                                                       $v = (string)$v;
            $result[$fullKey] = $v;
        }
    }
    return $result;
}

// ---- 1re passe : clés de data{} uniquement --------------------------------
$allJsonKeys = [];
$flatRows    = [];

foreach ($rows as $i => $row) {
    $json = json_decode($row['full_json'] ?? '{}', true) ?? [];
    $dataNode = $json['data'] ?? [];
    $flat = flattenJson($dataNode);   // aplati sans préfixe 'data.'
    $flatRows[$i] = $flat;
    foreach (array_keys($flat) as $k) {
        $allJsonKeys[$k] = true;
    }
}

// Tri alphabétique
ksort($allJsonKeys);
$allJsonKeys = array_keys($allJsonKeys);

// ---- Nom du fichier ---------------------------------------------------------
$filterParts = [];
if ($distrika !== '') $filterParts[] = preg_replace('/[^a-zA-Z0-9_-]/', '_', $distrika);
if ($search   !== '') $filterParts[] = 'recherche';
$suffix   = $filterParts ? '_' . implode('_', $filterParts) : '';
$filename = 'PRODUIR2_export' . $suffix . '_' . date('Ymd_His') . '.xls';

// ---- Fonction d'échappement XML --------------------------------------------
function x(string $v): string
{
    return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

// ---- En-têtes HTTP ---------------------------------------------------------
$headers = $allJsonKeys;

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// ---- Génération SpreadsheetML (XML 2003) -----------------------------------
echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
echo '<?mso-application progid="Excel.Sheet"?>', "\n";
echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"', "\n";
echo '  xmlns:o="urn:schemas-microsoft-com:office:office"', "\n";
echo '  xmlns:x="urn:schemas-microsoft-com:office:excel"', "\n";
echo '  xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"', "\n";
echo '  xmlns:html="http://www.w3.org/TR/REC-html40">', "\n";

// Styles
echo '<Styles>', "\n";
echo '  <Style ss:ID="sHeader">',
       '<Font ss:Bold="1" ss:Size="11"/>',
       '<Interior ss:Color="#DCE6F1" ss:Pattern="Solid"/>',
       '</Style>', "\n";
echo '  <Style ss:ID="sData"><Alignment ss:WrapText="0"/></Style>', "\n";
echo '</Styles>', "\n";

echo '<Worksheet ss:Name="Export">', "\n";



echo '<Table>', "\n";

// ---- Ligne d'en-têtes -------------------------------------------------------
echo '<Row>', "\n";
foreach ($headers as $h) {
    echo '  <Cell ss:StyleID="sHeader"><Data ss:Type="String">', x($h), '</Data></Cell>', "\n";
}
echo '</Row>', "\n";

// ---- Lignes de données ------------------------------------------------------
foreach ($rows as $i => $row) {
    $flat = $flatRows[$i];
    echo '<Row>', "\n";

    // Colonnes JSON (data{} uniquement)
    foreach ($allJsonKeys as $key) {
        $val = $flat[$key] ?? '';
        if ($val !== '' && is_numeric($val) && !str_starts_with($val, '0')) {
            echo '  <Cell ss:StyleID="sData"><Data ss:Type="Number">', x($val), '</Data></Cell>', "\n";
        } else {
            echo '  <Cell ss:StyleID="sData"><Data ss:Type="String">', x($val), '</Data></Cell>', "\n";
        }
    }

    echo '</Row>', "\n";
}

echo '</Table>', "\n";
echo '</Worksheet>', "\n";
echo '</Workbook>', "\n";
exit;
