<?php
require_once __DIR__ . '/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare('SELECT * FROM submissions WHERE id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) { header('Location: index.php?msg=error'); exit; }

// ---- Décoder le JSON complet ----
$raw  = $row['full_json'] ?? '{}';
$json = json_decode($raw, true) ?? [];

$meta      = $json['metadata']  ?? [];
$questions = $json['questions'] ?? [];  // NEW: {fieldname: {libelle, valeur}}
$data      = $json['data']      ?? [];  // kept for signatures & documents

// ---- Helpers ----------------------------------------------------------------

/** Retourne la valeur d'une question depuis $questions */
function qVal(array $q, string $key): mixed {
    return $q[$key]['valeur'] ?? '';
}

/** Retourne le libellé HTML-safe d'une question */
function qLabel(array $q, string $key): string {
    if (isset($q[$key]['libelle'])) return htmlspecialchars($q[$key]['libelle']);
    return htmlspecialchars(ucfirst(str_replace('_', ' ', $key)));
}

function displayValue(mixed $val): string {
    if ($val === true)  return '<span class="badge bg-success">Oui</span>';
    if ($val === false) return '<span class="badge bg-secondary">Non</span>';
    if ($val === null || $val === '') return '<span class="text-muted fst-italic">—</span>';
    if (is_array($val)) return '<code class="small">' . htmlspecialchars(json_encode($val, JSON_UNESCAPED_UNICODE)) . '</code>';
    return nl2br(htmlspecialchars((string)$val));
}

function isBase64Image(mixed $val): bool {
    return is_string($val) && str_starts_with($val, 'data:image/');
}

$papName = trim(qVal($questions,'anarana') . ' ' . qVal($questions,'fanampiny'));
$fisy    = qVal($questions,'laharana_fisy');

// ---- Groupes de questions par section ---------------------------------------
$sectionGroups = [
    'sec0_identification' => [
        'title' => '<i class="bi bi-geo-alt me-2"></i>Identification',
        'keys'  => ['daty','distrika','kaomina','fokontany','kaody_renirano','laharana_fisy'],
    ],
    'sec1_pap' => [
        'title' => '<i class="bi bi-person me-2"></i>Fizarana I – PAP',
        'keys'  => [
            'anarana','fanampiny','taona','fananahana','fanambadiana',
            'laharana_kp','daty_kp','toerana_kp','daty_duplicata','toerana_duplicata',
            'anarana_vady','taona_vady','kp_vady','daty_kp_vady','toerana_kp_vady','daty_duplicata_vady','toerana_duplicata_vady',
            'finday','gps_x','gps_y','gps_precision',
        ],
    ],
    'sec2_fahafantarana' => [
        'title' => '<i class="bi bi-info-circle me-2"></i>Fizarana II – Fahafantarana ny Tetikasa',
        'keys'  => ['fantatra_tetikasa','inona_tetikasa','ho_an_iza','aiza','ahoana','fantatra_vokatra','vokatra_inona'],
    ],
    'sec3_tokatrano' => [
        'title'   => '<i class="bi bi-house me-2"></i>Fizarana III – Tokatrano sy Ankohonana',
        'keys'    => ['kilasy','mamaky'],
        'special' => 'household_members',
    ],
    'sec4_fanabeazana' => [
        'title' => '<i class="bi bi-book me-2"></i>Fizarana IV – Fanabeazana',
        'keys'  => [
            'isan_ankizy_sekoly_taona',
            'miditra_fototra','miditra_fototra_nb',
            'miditra_faharoa','miditra_faharoa_nb',
            'miditra_ambony','miditra_ambony_nb',
            'ray_fari','reny_fari',
            'vola_fototra','vola_fototra_mt',
            'vola_faharoa','vola_faharoa_mt',
            'vola_ambony','vola_ambony_mt',
            'famp_fototra','famp_faharoa','famp_ambony',
            'elan_fototra','elan_fototra_km',
            'elan_faharoa','elan_faharoa_km',
            'elan_ambony','elan_ambony_km',
        ],
    ],
    'sec5_fahasalamana' => [
        'title' => '<i class="bi bi-heart-pulse me-2"></i>Fizarana V – Fahasalamana',
        'keys'  => [
            'aretina1','aretina1_fahavaratra','aretina1_ririnina','aretina1_mandava','aretina1_note',
            'aretina2','aretina2_fahavaratra','aretina2_ririnina','aretina2_mandava','aretina2_note',
            'aretina3','aretina3_fahavaratra','aretina3_ririnina','aretina3_mandava','aretina3_note',
            'tsab_tsy_misy','tsab_nentim','tsab_mpanent','tsab_csb','tsab_hopitaly','tsab_tsy_miank',
            'fitsab_pct','elan_fitsab',
        ],
    ],
    'sec6_tolotra_fototra' => [
        'title' => '<i class="bi bi-droplet me-2"></i>Fizarana VI – Tolotra Fototra',
        'keys'  => [
            'rano_paompy_trano','rano_paompy_iomb','rano_lavadrano_man','rano_lavadrano_iomb','rano_loharano','rano_renirano',
            'maloto_trano','maloto_iomb','maloto_ankal','maloto_amoron',
            'diov_trano','diov_iomb','diov_renirano',
            'jiro_jirama','jiro_masoandro','jiro_torche','jiro_labozia','jiro_petrole','jiro_tsy_misy',
            'afand_arina','afand_herin','afand_kitay','afand_entona',
        ],
    ],
    'sec7_vola' => [
        'title' => '<i class="bi bi-cash-coin me-2"></i>Fizarana VII – Vola',
        'keys'  => [
            'vola_f_tsy_misy','vola_f_mamboly','vola_f_mivarotra','vola_f_mpanjono','vola_f_miasa_ori','vola_f_asa_tanana','vola_f_biriky','vola_f_fasika','vola_f_antselika','vola_f_hafa',
            'vola_a_tsy_misy','vola_a_mamboly','vola_a_mivarotra','vola_a_mpanjono','vola_a_miasa_ori','vola_a_asa_tanana','vola_a_biriky','vola_a_fasika','vola_a_antselika','vola_a_hafa',
            'vola_miditra',
            'fand_sakafo','fand_sakafo_taha','fand_fahasalamana','fand_fahasalamana_taha',
            'fand_fanabeazana','fand_fanabeazana_taha','fand_trano','fand_trano_taha',
            'fand_fitafiana','fand_fitafiana_taha','fand_hafa','fand_hafa_taha',
            'tahiry','tahiry_pct','tsy_tahiry_antony',
            'fitahiry',
            'fndram_tsy_misy','fndram_banky','fndram_mpamp','fndram_microf','fndram_varymaitso','fndram_mobile','fndram_hafa',
        ],
    ],
    'sec8_tany' => [
        'title' => '<i class="bi bi-map me-2"></i>Fizarana VIII – Tany',
        'keys'  => [
            'tany_tokotany','tany_voajary','tany_sasatra','tany_tanimbary',
            'voly_vary','voly_ananana','voly_mangahazo','voly_katsaka','voly_vomanga','voly_legioma',
            'refin_tany_total','refin_tany_voak',
            'tar_titra','tar_kadasitra','tar_karatany','tar_tsy_misy',
            'mpampiasa_tany','fifanar_tany',
            'vesatra_renirano','vesatra_fefiloha','vesatra_zotry','vesatra_hafa',
            'tombam_tany',
        ],
    ],
    'sec9_trano' => [
        'title' => '<i class="bi bi-buildings me-2"></i>Fizarana IX – Trano',
        'keys'  => [
            'trano_fonenana','trano_fivarotana_l','trano_fivarotana_m','trano_hafa',
            'trano_taona','trano_rihana','trano_efitrano',
            'mat_vato','mat_biriky','mat_hazo','mat_vy','mat_hafa',
            'refin_trano_total','refin_trano_voak','tombam_trano',
        ],
    ],
    'sec10_fanelingelenana' => [
        'title' => '<i class="bi bi-exclamation-triangle me-2"></i>Fizarana X – Fanelingelenana',
        'keys'  => ['faneling_fianakaviana','fifindrana_pct','faneling_fivelomana','miarina_pct'],
    ],
    'sec11_fananana' => [
        'title' => '<i class="bi bi-bank me-2"></i>Fizarana XI – Fananana',
        'keys'  => ['fanak_tany','fanak_trano'],
    ],
    'sec12_hazo' => [
        'title'  => '<i class="bi bi-tree me-2"></i>Fizarana XII – Hazo',
        'prefix' => 'hazo_',
    ],
    'sec13_faharefoana' => [
        'title' => '<i class="bi bi-people me-2"></i>Fizarana XIII – Faharefoana sy Fiarovana',
        'keys'  => [
            'tsalama_ray','tsalama_reny','isan_sembanana','semb_batana','semb_tsaina','isan_bevohoka',
            'herisetra','her_batana','her_moraly','her_bola','her_hafa','her_vady','her_zanaka',
            'fiat_tsy_toky','fiat_zadam','fiat_loka','fiat_hafa',
            'fitarain_tsy_misy','fitarain_namana','fitarain_havana','fitarain_fikamb','fitarain_hafa','fitarain_aiza','fitarain_inona',
            'hevitra_87',
        ],
    ],
    'sec14_tan_tsoroka' => [
        'title' => '<i class="bi bi-tools me-2"></i>Fizarana XIV – Tan-tsoroka',
        'keys'  => ['tans_tsy_misy','tans_fanofanana','tans_lalambarotra','tans_hafa'],
    ],
    'sec15_fanonerana' => [
        'title' => '<i class="bi bi-currency-exchange me-2"></i>Fizarana XV – Fanonerana',
        'keys'  => ['fandravana','fanonerana','antony_safidy'],
    ],
    'sec16_fitarainana' => [
        'title' => '<i class="bi bi-chat-dots me-2"></i>Fizarana XVI – Fitarainana',
        'keys'  => ['fitarain_boaty','fitarain_boky','fitarain_olona','fomba_fifanar','fanamarihana_94'],
    ],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Détail #<?= $id ?> – PRODUIR 2</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  body { background: #f0f4f8; }
  .section-card { margin-bottom: 1.5rem; }
  .section-card .card-header { background: #0d6efd; color: #fff; font-weight: 600; }
  .field-label { color: #555; font-size: .85rem; font-weight: 600; width: 40%; min-width: 180px; }
  .field-value { font-size: .92rem; }
  tr.field-row:hover { background: #f8f9ff; }
  .signature-box { border: 2px solid #dee2e6; border-radius: 6px; padding: 8px; background: #fff; display:inline-block; }
  .signature-box img { display: block; max-width: 280px; }
  .signature-label { font-size:.8rem; color:#666; margin-top:4px; text-align:center; }
  .doc-card { border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; background: #fff; }
  .doc-card img { width: 100%; max-height: 220px; object-fit: cover; border-bottom: 1px solid #dee2e6; }
  .member-table th { background: #e7f0ff; font-size: .82rem; }
  .member-table td { font-size: .85rem; }
  .meta-badge { font-size: .8rem; }
  .img-zoom { cursor: zoom-in; transition: opacity .15s; }
  .img-zoom:hover { opacity: .85; }
  /* Lightbox */
  #lightboxModal .modal-dialog { max-width: 95vw; }
  #lightboxModal .modal-body { background: #000; text-align: center; padding: .5rem; }
  #lightboxModal img { max-width: 100%; max-height: 90vh; object-fit: contain; }
  .print-btn { display: none; }
  @media print {
    .no-print { display: none !important; }
    .print-btn { display: inline-block; }
    body { background: white; }
  }
</style>
</head>
<body>

<nav class="navbar navbar-dark bg-primary mb-4 shadow-sm no-print">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php">
      <i class="bi bi-arrow-left me-2"></i>PRODUIR 2 – Backoffice
    </a>
    <div class="d-flex gap-2">
      <a href="download.php?id=<?= $id ?>" class="btn btn-light btn-sm">
        <i class="bi bi-download me-1"></i>JSON
      </a>
      <button onclick="window.print()" class="btn btn-outline-light btn-sm">
        <i class="bi bi-printer me-1"></i>Imprimer
      </button>
      <a href="delete.php?id=<?= $id ?>" class="btn btn-outline-danger btn-sm"
         onclick="return confirm('Supprimer cet enregistrement ?')">
        <i class="bi bi-trash me-1"></i>Supprimer
      </a>
    </div>
  </div>
</nav>

<div class="container-fluid px-4 pb-5">

  <!-- ===== Entête métadonnées ===== -->
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <div class="d-flex flex-wrap align-items-start gap-3 justify-content-between">
        <div>
          <h4 class="mb-1">
            <?= htmlspecialchars($papName ?: 'PAP inconnu') ?>
            <?php if ($fisy): ?>
              <span class="badge bg-secondary ms-2 meta-badge"><?= htmlspecialchars($fisy) ?></span>
            <?php endif; ?>
          </h4>
          <div class="text-muted small">
            <i class="bi bi-person me-1"></i>Enquêteur : <strong><?= htmlspecialchars($meta['enqueteur'] ?? '–') ?></strong>
            &nbsp;|&nbsp;
            <i class="bi bi-calendar me-1"></i><?= htmlspecialchars($meta['submissionDateLocal'] ?? $meta['submissionDate'] ?? '–') ?>
          </div>
        </div>
        <div class="d-flex flex-column gap-1 text-end">
          <span class="badge bg-light text-dark border meta-badge">
            <i class="bi bi-hash"></i> ID <?= $id ?>
          </span>
          <span class="badge bg-light text-dark border meta-badge">
            <?= htmlspecialchars($meta['formName'] ?? 'PRODUIR 2') ?>
            v<?= htmlspecialchars($meta['formVersion'] ?? '?') ?>
          </span>
          <?php if ($row['original_filename']): ?>
          <span class="badge bg-light text-dark border meta-badge text-truncate" style="max-width:220px"
                title="<?= htmlspecialchars($row['original_filename']) ?>">
            <i class="bi bi-file-earmark-code me-1"></i><?= htmlspecialchars($row['original_filename']) ?>
          </span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ===== Sections génériques depuis $questions ===== -->
  <?php foreach ($sectionGroups as $secKey => $secDef):
      $secTitle  = $secDef['title'];
      $prefix    = $secDef['prefix'] ?? null;
      $special   = $secDef['special'] ?? null;
      $keys      = $secDef['keys'] ?? [];

      // Pour les sections à préfixe (ex: hazo_*), on collecte toutes les clés correspondantes
      if ($prefix) {
          $keys = array_keys(array_filter($questions, fn($k) => str_starts_with($k, $prefix), ARRAY_FILTER_USE_KEY));
      }

      // Toujours initialiser $members pour la section spéciale
      if ($special === 'household_members') {
          $members = isset($questions['household_members']['valeur']) ? $questions['household_members']['valeur'] : [];
      } else {
          $members = [];
      }
  ?>
  <div class="card section-card shadow-sm">
    <div class="card-header"><?= $secTitle ?></div>
    <div class="card-body p-0">
      <table class="table table-bordered mb-0">
        <?php foreach ($keys as $field):
            $val   = qVal($questions, $field);
            $label = qLabel($questions, $field);
        ?>
          <tr class="field-row">
            <td class="field-label"><?= $label ?></td>
            <td class="field-value">
              <?php if (isBase64Image($val)): ?>
                <img src="<?= $val ?>" alt="<?= $label ?>" class="img-zoom"
                     onclick="openLightbox(this.src, '<?= addslashes($label) ?>')"
                     style="max-width:200px;max-height:150px;border-radius:4px;border:1px solid #dee2e6">
              <?php elseif (is_array($val)): ?>
                <pre class="small mb-0 bg-light p-2 rounded border"><?= htmlspecialchars(json_encode($val, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></pre>
              <?php else: ?>
                <?= displayValue($val) ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>

      <?php if (isset($special) && $special === 'household_members' && !empty($members)): ?>
        <div class="px-3 pb-3 pt-2">
          <div class="fw-semibold small text-muted mb-2">
            <?= qLabel($questions, 'household_members') ?>
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-bordered member-table mb-0">
              <thead>
                <tr>
                  <th>#</th>
                  <?php $memberKeys = array_keys($members[0] ?? []);
                  foreach ($memberKeys as $mk): if ($mk === 'id') continue; ?>
                    <th><?= htmlspecialchars(ucfirst(str_replace('_',' ',$mk))) ?></th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($members as $mi => $member): ?>
                  <tr>
                    <td><?= $mi + 1 ?></td>
                    <?php foreach ($memberKeys as $mk): if ($mk === 'id') continue; ?>
                      <td><?= displayValue($member[$mk] ?? null) ?></td>
                    <?php endforeach; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

    </div><!-- /card-body -->
  </div><!-- /card -->
  <?php endforeach; // sectionGroups ?>

  <!-- ===== Documents (depuis questions.attached_documents) ===== -->
  <?php
  $docs = isset($questions['attached_documents']['valeur']) ? $questions['attached_documents']['valeur'] : [];
  if (!empty($docs) && is_array($docs)):
  ?>
  <div class="card section-card shadow-sm">
    <div class="card-header">
      <i class="bi bi-paperclip me-2"></i>Fizarana XVII – Documents jointes
    </div>
    <div class="card-body">
      <div class="row g-3">
        <?php foreach ($docs as $idx => $doc):
            $imgData     = $doc['data']      ?? '';
            $docType     = $doc['type']      ?? '';
            $autreDesc   = $doc['autreDesc'] ?? '';
            $fileName    = $doc['fileName']  ?? "doc_$idx";
            $fileSize    = isset($doc['fileSize']) ? round($doc['fileSize'] / 1024, 1) . ' Ko' : '';
            $displayType = $autreDesc ?: $docType;
        ?>
          <div class="col-sm-6 col-md-4 col-xl-3">
            <div class="doc-card shadow-sm h-100">
              <?php if (isBase64Image($imgData)): ?>
                <img src="<?= $imgData ?>" alt="<?= htmlspecialchars($displayType) ?>" class="img-zoom"
                     onclick="openLightbox(this.src, '<?= addslashes(htmlspecialchars($displayType)) ?>')"
                     style="width:100%;max-height:220px;object-fit:cover;border-bottom:1px solid #dee2e6;cursor:zoom-in">
              <?php else: ?>
                <div class="d-flex align-items-center justify-content-center bg-light" style="height:120px">
                  <i class="bi bi-file-earmark fs-1 text-muted"></i>
                </div>
              <?php endif; ?>
              <div class="p-2">
                <div class="fw-semibold small"><?= htmlspecialchars($displayType) ?></div>
                <div class="text-muted" style="font-size:.75rem">
                  <?= htmlspecialchars($fileName) ?><?= $fileSize ? " · $fileSize" : '' ?>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ===== Signatures (depuis data.sec18_sonia) ===== -->
  <?php
  $soniaSec  = $data['sec18_sonia'] ?? [];
  $sigs      = $soniaSec['signatures'] ?? [];
  $sigLabels = ['enqueteur' => 'Signature Enquêteur', 'enquete' => 'Signature Enquêté'];
  if (!empty($soniaSec)):
  ?>
  <div class="card section-card shadow-sm">
    <div class="card-header">
      <i class="bi bi-pen me-2"></i>Fizarana XVIII – Sonia (Signatures)
    </div>
    <div class="card-body p-0">
      <table class="table table-bordered mb-0">
        <?php foreach ($soniaSec as $field => $val):
            if ($field === 'signatures') continue;
        ?>
          <tr class="field-row">
            <td class="field-label"><?= htmlspecialchars(ucfirst(str_replace('_',' ',$field))) ?></td>
            <td class="field-value"><?= displayValue($val) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
      <?php if (!empty($sigs)): ?>
      <div class="p-3 d-flex flex-wrap gap-4">
        <?php foreach ($sigLabels as $sigKey => $sigLabel):
            $imgSrc = $sigs[$sigKey] ?? '';
        ?>
          <div class="text-center">
            <div class="signature-box">
              <?php if (isBase64Image($imgSrc)): ?>
                <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($sigLabel) ?>" class="img-zoom"
                     onclick="openLightbox(this.src, '<?= addslashes(htmlspecialchars($sigLabel)) ?>')">
              <?php else: ?>
                <div class="text-muted fst-italic p-4">Aucune signature</div>
              <?php endif; ?>
            </div>
            <div class="signature-label"><?= htmlspecialchars($sigLabel) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Lien retour -->
  <div class="no-print mt-2">
    <a href="index.php" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Retour à la liste
    </a>
  </div>

</div><!-- /container -->

<!-- Lightbox modal -->
<div class="modal fade no-print" id="lightboxModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0">
      <div class="modal-header py-2 bg-dark text-white border-0">
        <span class="modal-title small" id="lightboxCaption"></span>
        <div class="d-flex gap-2 ms-auto">
          <a id="lightboxDownload" href="#" download="image.jpg" class="btn btn-sm btn-outline-light">
            <i class="bi bi-download"></i>
          </a>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
      </div>
      <div class="modal-body">
        <img id="lightboxImg" src="" alt="">
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openLightbox(src, caption) {
  document.getElementById('lightboxImg').src = src;
  document.getElementById('lightboxCaption').textContent = caption || '';
  document.getElementById('lightboxDownload').href = src;
  var ext = src.startsWith('data:image/png') ? 'png' : 'jpg';
  document.getElementById('lightboxDownload').download = (caption || 'image') + '.' + ext;
  new bootstrap.Modal(document.getElementById('lightboxModal')).show();
}
</script>
</body>
</html>
