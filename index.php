<?php
// /public_html/pnl2/index.php
require_once __DIR__ . '/inc/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// show flash messages if set
$flashMessage = $_SESSION['flash_message'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
// clear after read
unset($_SESSION['flash_message'], $_SESSION['flash_error']);

// logo resolution
$logo = asset('/assets/img/logo.png');
try {
    $dbFile = __DIR__ . '/database.php';
    if (file_exists($dbFile)) {
        require_once $dbFile;
        if (function_exists('db_connect')) {
            $db = db_connect();
            $s = $db->query("SELECT logo_path FROM settings WHERE id=1")->fetch(PDO::FETCH_ASSOC);
            if ($s && !empty($s['logo_path'])) $logo = asset($s['logo_path']);
        }
    }
} catch (Throwable $e) {
    // ignore
}
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php require_once __DIR__ . '/inc/head.php'; ?>
  <title>Görsel / Dosya Yükle</title>

  <style>
    * { box-sizing: border-box; }
    html,body { height: 100%; }
    body {
      font-family: "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      margin: 0;
      padding: 24px;
      background-color: #eef6fb;
      display: flex;
      justify-content: center;
      align-items: flex-start;
      min-height: 100vh;
    }

    .container {
      background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
      padding: 26px 28px;
      border-radius: 14px;
      box-shadow: 0 10px 30px rgba(15,23,42,0.08);
      width: 100%;
      max-width: 420px;
      text-align: left;
      border: 1px solid rgba(0,0,0,0.04);
      overflow: hidden;
      position: relative;
    }

    .logo { display:block; margin: 6px auto 18px; width: 220px; height: auto; user-select: none; pointer-events: none; }

    .field { margin: 12px 0; }
    .small-label { display:block; margin-bottom:8px; font-size:13px; color:#374151; font-weight:600; letter-spacing:.2px; }

    input[type="text"], select, .custom-file-display, button, .file-label {
      margin: 0;
      padding: 10px 12px;
      width: 100%;
      border: 1px solid #E6E9EE;
      border-radius: 10px;
      background: #fff;
      color: #0F172A;
      font-size: 14px;
      outline: none;
      transition: border-color .12s ease, box-shadow .12s ease, transform .05s ease;
    }

    input[type="text"]:focus, select:focus, .file-label:focus, .custom-file-display:focus, button:focus { border-color:#2563EB; box-shadow:0 0 0 6px rgba(37,99,235,0.06); }

    .toggle-container { display:flex; align-items:center; gap:10px; margin-bottom:6px; }
    .toggle-container input[type="checkbox"] { width:18px; height:18px; accent-color:#2563EB; }
    .toggle-label { font-size:13px; color:#374151; }

    /* branch inline select */
    .branch-picker-inline { margin-top:8px; display:none; }
    .branch-select { text-transform: none; }

    /* FILE INPUT AREA */
    .file-input-row { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
    /* hide native file input, use label as button */
    input[type="file"] { display: none; }

    .file-label {
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:8px;
      padding:8px 14px;
      width:auto;
      min-width:140px;
      border-radius:10px;
      background:#ffffff;
      border:1px solid #E6E9EE;
      cursor:pointer;
      box-shadow: 0 2px 6px rgba(16,24,40,0.04);
      font-weight:700;
      color:#0F172A;
      text-transform:none;
    }
    .file-label:hover { transform: translateY(-1px); }

    button.primary {
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:8px;
      padding:10px 18px;
      min-width:160px;
      border-radius:10px;
      background: linear-gradient(180deg,#2563EB,#1D4ED8);
      color:white;
      border:none;
      cursor:pointer;
      font-weight:700;
      box-shadow: 0 6px 18px rgba(37,99,235,0.18);
    }
    button.primary:hover { filter:brightness(.98); transform: translateY(-1px); }

    .custom-file-display {
      background:#F8FAFC;
      border:1px solid #EDF2F7;
      color:#374151;
      padding:10px 12px;
      border-radius:10px;
      min-height:44px;
      display:flex;
      align-items:center;
      gap:8px;
      flex:1;
      justify-content:center;
      font-size:13px;
      text-align:center;
    }

    .file-error { margin-top:8px; color:#B91C1C; font-size:13px; font-weight:600; }

    .preview { margin-top:14px; display:flex; flex-wrap:wrap; gap:10px; justify-content:flex-start; }
    .preview img { width:92px; height:auto; border:1px solid #E6E9EE; border-radius:8px; object-fit:cover; }

    .file-item { display:flex; align-items:center; gap:8px; padding:8px 10px; border-radius:10px; background:#F8FAFC; border:1px solid #EDF2F7; color:#334155; font-size:13px; max-width:260px; overflow:hidden; }
    .file-icon { width:34px; height:34px; background:#E6EEF9; color:#2563EB; display:inline-flex; align-items:center; justify-content:center; border-radius:6px; font-weight:700; font-size:13px; }

    .remove-btn { margin-left:8px; background:transparent; border:none; color:#9CA3AF; cursor:pointer; font-size:16px; line-height:1; padding:2px 6px; border-radius:6px; }
    .remove-btn:hover { background:rgba(0,0,0,0.04); color:#ef4444; }

    .filename-chip { display:inline-flex; align-items:center; gap:8px; background:#fff; border-radius:8px; padding:6px 8px; border:1px solid #E6E9EE; margin-right:6px; max-width:160px; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; }

    .hint { font-size:12px; color:#6B7280; margin-top:6px; }

    input[type="submit"] { width:100%; padding:12px 14px; background-color:#2563EB; color:white; border:none; cursor:pointer; border-radius:10px; margin-top:12px; font-size:15px; font-weight:700; box-shadow: 0 6px 18px rgba(37,99,235,0.18); }
    input[type="submit"]:hover { filter:brightness(.98); }

    /* Camera overlay */
    .camera-overlay {
      position:fixed;
      inset:0;
      background:rgba(0,0,0,0.6);
      display:flex;
      align-items:center;
      justify-content:center;
      z-index:9999;
      padding:20px;
      /* allow top safe-area on modern phones */
      padding-top: calc(env(safe-area-inset-top, 0px) + 20px);
    }
    .camera-panel {
      background:#fff;
      border-radius:10px;
      padding:12px;
      max-width:720px;
      width:100%;
      box-shadow:0 10px 30px rgba(0,0,0,0.4);
      display:flex;
      flex-direction:column;
      gap:8px;
      align-items:center;
      position:relative;
      /* ensure panel doesn't exceed viewport on mobile and can scroll */
      max-height: calc(100vh - 40px);
      overflow: auto;
    }
    .camera-video { width:100%; border-radius:8px; background:#000; max-height:70vh; object-fit:contain; touch-action: none; }
    .camera-controls { display:flex; gap:8px; align-items:center; }

    /* zoom/focus UI */
    .camera-tools { width:100%; display:flex; gap:8px; align-items:center; justify-content:center; margin-top:6px; }
    .zoom-slider { width:220px; }
    .focus-indicator {
      position:absolute;
      width:80px;
      height:80px;
      border-radius:50%;
      border:2px solid rgba(37,99,235,0.9);
      pointer-events:none;
      transform:translate(-50%,-50%) scale(0.1);
      opacity:0;
      transition:opacity .25s ease, transform .25s ease;
    }

    @media (max-width:480px) {
      .container{padding:18px;}
      .logo{width:180px;}
      .zoom-slider{width:140px;}
      .file-input-row { gap:8px; }
      .file-label, button.primary { width:100%; min-width:unset; }
      .custom-file-display { width:100%; order: 3; }

      /* On small devices show camera panel from top so the top of video is visible.
         Desktop/large screens keep centered. */
      .camera-overlay {
        align-items: flex-start;
        justify-content: center;
        padding-top: calc(env(safe-area-inset-top, 8px) + 12px);
      }
      .camera-panel {
        margin-top: 6px;
        max-height: calc(100vh - 80px); /* leave space for browser chrome + controls */
        border-radius: 10px;
      }
      /* Make video a bit shorter so controls and descriptions are visible below */
      .camera-video {
        max-height: calc(100vh - 220px);
        height: auto;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo" class="logo">
    <?php if ($flashMessage): ?><div class="message"><?php echo htmlspecialchars($flashMessage); ?></div><?php endif; ?>
    <?php if ($flashError): ?><div class="error"><?php echo htmlspecialchars($flashError); ?></div><?php endif; ?>

    <form id="uploadForm" action="<?php echo asset('/upload.php'); ?>" method="POST" enctype="multipart/form-data">
      <div class="field">
        <div class="toggle-container">
          <input type="checkbox" id="pdi" name="pdi" value="1" />
          <label for="pdi" class="toggle-label">PDI / Plakasız araç (Şasi ile işlem yapılacak)</label>
        </div>
      </div>

      <!-- Inline branch select shown when PDI is checked -->
      <div class="field branch-picker-inline" id="branchPickerInline">
        <label class="small-label" for="branchSelect">Şube Seçimi (PDI için)</label>
        <select id="branchSelect" name="branch" class="branch-select">
          <option value="">-- Şube seçin --</option>
          <option value="1">1 - Bursa</option>
          <option value="2">2 - Kocaeli</option>
          <option value="3">3 - Orhanlı</option>
          <option value="4">4 - Hadımköy</option>
          <option value="5">5 - Keşan</option>
        </select>
        <div class="hint">PDI seçiliyse bir şube seçin; seçilen kod veritabanına branch_code olarak kaydedilecektir.</div>
      </div>

      <div class="field" id="plakaContainer">
        <label class="small-label" for="plaka">Plaka</label>
        <input type="text" name="plaka" id="plaka" placeholder="PLAKA (ÖRN: 34ABC123)" required pattern="[A-Z0-9]+" title="Sadece büyük harf ve rakam kullanın" />
      </div>

      <div class="field" id="isemriContainer">
        <label class="small-label" for="isemri">İş Emri No</label>
        <input type="text" name="isemri" id="isemri" placeholder="İŞ EMRİ NO (8 HANELİ)" required maxlength="8" />
      </div>

      <div class="field" id="vinContainer" style="display:none;">
        <label class="small-label" for="vin">VIN (Şasi No)</label>
        <input type="text" name="vin" id="vin" placeholder="ŞASİ NO (VIN)" maxlength="25" pattern="[A-Z0-9\-]" title="Sadece büyük harf, rakam ve kısa tire" />
        <div class="hint">PDI işaretlendiğinde bu alanı doldurun.</div>
      </div>

      <div class="field">
        <label class="small-label" for="files">Dosyalar</label>

        <div class="file-input-row" role="group" aria-label="Dosya seçimi">
          <!-- native file input (hidden) -->
          <input type="file" id="files" name="files[]" multiple accept=".tst,.pdf,.jpg,.jpeg,.png,.mp4,.oxps,.zip,.rar">

          <!-- nicer styled label acting as "Choose files" button -->
          <label for="files" class="file-label" title="Dosyaları seç">Dosyaları Seç</label>

          <!-- capture button -->
          <button type="button" id="captureBtn" class="primary" style="width:auto;padding:10px 16px;">FOTOĞRAF ÇEK</button>

          <!-- file names / status -->
          <div class="custom-file-display" id="fileNames" aria-live="polite">Dosya seçilmedi</div>
        </div>

        <div id="fileError" class="file-error" style="display:none;"></div>
        <div class="hint">İzin verilen uzantılar: .tst, .pdf, .jpg, .jpeg, .png, .mp4, .oxps, .zip, .rar. Görseller için önizleme gösterilir. Sunucu tarafı kontrolleri yapılmalıdır.</div>
      </div>

      <div class="preview" id="preview" aria-live="polite"></div>

      <input type="submit" value="Görsel Yükle">
    </form>
  </div>

  <!-- Camera overlay (hidden by default) -->
  <div id="cameraOverlay" class="camera-overlay" style="display:none;" aria-hidden="true" aria-modal="true" role="dialog">
    <div class="camera-panel" role="document">
      <div id="focusIndicator" class="focus-indicator" aria-hidden="true"></div>
      <video id="cameraVideo" class="camera-video" autoplay playsinline></video>
      <div class="camera-controls">
        <button id="takePhotoBtn" class="primary" type="button">Çek</button>
        <button id="closeCameraBtn" type="button">İptal</button>
        <label style="margin-left:12px;color:#6B7280;font-size:13px">Kalite: WhatsApp benzeri (resize + JPEG sıkıştırma)</label>
      </div>
      <div class="camera-tools" id="cameraTools" style="display:none;">
        <button id="zoomOutBtn" type="button">-</button>
        <input id="zoomSlider" class="zoom-slider" type="range" min="1" max="1" step="0.1" value="1" />
        <button id="zoomInBtn" type="button">+</button>
      </div>
    </div>
  </div>

<script>
  // Elements
  const form = document.getElementById('uploadForm');
  const pdiEl = document.getElementById('pdi');
  const branchPickerInline = document.getElementById('branchPickerInline');
  const branchSelect = document.getElementById('branchSelect');

  const plakaEl = document.getElementById('plaka');
  const isemriEl = document.getElementById('isemri');
  const vinEl = document.getElementById('vin');

  const plakaContainer = document.getElementById('plakaContainer');
  const isemriContainer = document.getElementById('isemriContainer');
  const vinContainer = document.getElementById('vinContainer');

  const filesEl = document.getElementById('files');
  const preview = document.getElementById('preview');
  const fileNamesEl = document.getElementById('fileNames');
  const fileErrorEl = document.getElementById('fileError');

  const captureBtn = document.getElementById('captureBtn');
  const cameraOverlay = document.getElementById('cameraOverlay');
  const cameraVideo = document.getElementById('cameraVideo');
  const takePhotoBtn = document.getElementById('takePhotoBtn');
  const closeCameraBtn = document.getElementById('closeCameraBtn');
  const cameraTools = document.getElementById('cameraTools');
  const zoomSlider = document.getElementById('zoomSlider');
  const zoomInBtn = document.getElementById('zoomInBtn');
  const zoomOutBtn = document.getElementById('zoomOutBtn');
  const focusIndicator = document.getElementById('focusIndicator');

  // Allowed extensions
  const allowedExts = ['tst','pdf','jpg','jpeg','png','mp4','oxps','zip','rar'];

  // Selected files array for manipulation
  let selectedFiles = [];

  // Camera stream handle
  let cameraStream = null;
  let videoTrack = null;
  let trackCapabilities = null;
  let currentZoom = 1;
  let lastPinch = null;

  // Helpers
  function getExt(filename) {
    const parts = filename.split('.');
    if (parts.length <= 1) return '';
    return parts.pop().toLowerCase();
  }

  function updateInputFiles() {
    try {
      const dt = new DataTransfer();
      selectedFiles.forEach(f => dt.items.add(f));
      filesEl.files = dt.files;
    } catch (err) {
      console.warn('DataTransfer not supported or failed:', err);
    }
  }

  function renderFilesUI() {
    preview.innerHTML = '';
    fileNamesEl.innerHTML = '';
    if (!selectedFiles.length) {
      fileNamesEl.textContent = 'Dosya seçilmedi';
      return;
    }

    // filename chips
    selectedFiles.forEach((file, idx) => {
      const chip = document.createElement('div');
      chip.className = 'filename-chip';
      const nameSpan = document.createElement('span');
      nameSpan.textContent = (file.name.length > 28 ? file.name.slice(0,24) + '…' : file.name);
      chip.appendChild(nameSpan);

      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'remove-btn';
      removeBtn.title = 'Kaldır';
      removeBtn.innerHTML = '&times;';
      removeBtn.dataset.index = idx;
      removeBtn.addEventListener('click', function() { removeFileAt(parseInt(this.dataset.index,10)); });
      chip.appendChild(removeBtn);

      fileNamesEl.appendChild(chip);
    });

    // previews
    selectedFiles.forEach((file, idx) => {
      if (file.type.startsWith('image/')) {
        const img = document.createElement('img');
        img.alt = file.name;
        img.title = file.name;

        const wrapper = document.createElement('div');
        wrapper.style.position = 'relative';
        wrapper.style.display = 'inline-block';
        wrapper.style.borderRadius = '8px';
        wrapper.style.overflow = 'hidden';
        wrapper.appendChild(img);

        const removeImgBtn = document.createElement('button');
        removeImgBtn.type = 'button';
        removeImgBtn.className = 'remove-btn';
        removeImgBtn.style.position = 'absolute';
        removeImgBtn.style.top = '6px';
        removeImgBtn.style.right = '6px';
        removeImgBtn.title = 'Kaldır';
        removeImgBtn.innerHTML = '&times;';
        removeImgBtn.dataset.index = idx;
        removeImgBtn.addEventListener('click', function() { removeFileAt(parseInt(this.dataset.index,10)); });

        wrapper.appendChild(removeImgBtn);
        preview.appendChild(wrapper);

        const reader = new FileReader();
        reader.onload = function(ev) { img.src = ev.target.result; };
        reader.readAsDataURL(file);
      } else {
        const item = document.createElement('div');
        item.className = 'file-item';
        const icon = document.createElement('div');
        icon.className = 'file-icon';
        const ext = getExt(file.name).toUpperCase().slice(0,4);
        icon.textContent = ext || 'FILE';
        const label = document.createElement('div');
        label.textContent = file.name.length > 40 ? file.name.slice(0,36) + '…' : file.name;

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'remove-btn';
        removeBtn.title = 'Kaldır';
        removeBtn.innerHTML = '&times;';
        removeBtn.dataset.index = idx;
        removeBtn.addEventListener('click', function() { removeFileAt(parseInt(this.dataset.index,10)); });

        item.appendChild(icon);
        item.appendChild(label);
        item.appendChild(removeBtn);
        preview.appendChild(item);
      }
    });
  }

  function removeFileAt(index) {
    if (index < 0 || index >= selectedFiles.length) return;
    selectedFiles.splice(index, 1);
    updateInputFiles();
    renderFilesUI();
    fileErrorEl.style.display = 'none';
    fileErrorEl.textContent = '';
  }

  // File input change handler
  filesEl.addEventListener('change', function(e) {
    preview.innerHTML = '';
    fileErrorEl.style.display = 'none';
    fileErrorEl.textContent = '';

    const files = Array.from(e.target.files || []);
    if (files.length === 0) {
      selectedFiles = [];
      renderFilesUI();
      return;
    }

    const invalid = files.filter(f => !allowedExts.includes(getExt(f.name)));
    if (invalid.length > 0) {
      fileErrorEl.style.display = 'block';
      fileErrorEl.textContent = 'Geçersiz dosya türü: ' + invalid.map(f => f.name).join(', ');
      filesEl.value = '';
      selectedFiles = [];
      renderFilesUI();
      return;
    }

    selectedFiles = files.slice();
    updateInputFiles();
    renderFilesUI();
  });

  // Uppercase enforcement
  plakaEl.addEventListener('input', function(){ this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g,''); });
  vinEl.addEventListener('input', function(){ this.value = this.value.toUpperCase().replace(/[^A-Z0-9\-]/g,''); });

  // Show/hide branch picker inline and toggle fields
  function showBranchInline(show) {
    branchPickerInline.style.display = show ? 'block' : 'none';
    if (!show) {
      branchSelect.value = '';
      branchSelect.removeAttribute('required');
    } else {
      branchSelect.setAttribute('required','required');
    }
  }

  function togglePDI(checked) {
    if (checked) {
      vinContainer.style.display = 'block';
      vinEl.required = true;
      plakaContainer.style.display = 'none';
      isemriContainer.style.display = 'none';
      plakaEl.removeAttribute('required');
      isemriEl.removeAttribute('required');
      plakaEl.disabled = true;
      isemriEl.disabled = true;
      plakaEl.value = '';
      isemriEl.value = '';
      showBranchInline(true);
    } else {
      vinContainer.style.display = 'none';
      vinEl.required = false;
      vinEl.value = '';
      plakaContainer.style.display = 'block';
      isemriContainer.style.display = 'block';
      plakaEl.disabled = false;
      isemriEl.disabled = false;
      plakaEl.setAttribute('required','required');
      isemriEl.setAttribute('required','required');
      showBranchInline(false);
    }
  }

  // Init
  togglePDI(pdiEl.checked);
  pdiEl.addEventListener('change', function(){ togglePDI(this.checked); });

  // Form submit validation: ensure branch selected when PDI
  form.addEventListener('submit', function(e) {
    try { plakaEl.setCustomValidity(''); } catch {}
    try { isemriEl.setCustomValidity(''); } catch {}
    try { vinEl.setCustomValidity(''); } catch {}

    if (pdiEl.checked) {
      if (!branchSelect.value) {
        fileErrorEl.style.display = 'block';
        fileErrorEl.textContent = 'PDI seçildiğinde lütfen bir şube seçin.';
        e.preventDefault();
        return false;
      }
      // VIN already required
    } else {
      // plaka + isemri required
    }
    // allow submit
  });

  //
  // Camera capture logic (WhatsApp-like quality) + zoom & focus (best-effort)
  //
  function openCamera() {
    // Basic validation: require isemri/plaka (or vin when PDI) so captured photos are associated
    if (!pdiEl.checked) {
      if (!isemriEl.value) { alert('Lütfen önce İş Emri numarasını girin.'); return; }
      if (!plakaEl.value)  { alert('Lütfen önce Plaka bilgisini girin.'); return; }
    } else {
      if (!vinEl.value) { alert('PDI için lütfen Şasi (VIN) girin.'); return; }
      if (!branchSelect.value) { alert('PDI için lütfen şube seçin.'); return; }
    }

    const constraints = {
      video: {
        facingMode: { ideal: 'environment' },
        width: { ideal: 1920 },
        height: { ideal: 1080 }
      },
      audio: false
    };
    navigator.mediaDevices.getUserMedia(constraints).then(stream => {
      cameraStream = stream;
      cameraVideo.srcObject = stream;
      cameraOverlay.style.display = 'flex';
      cameraOverlay.setAttribute('aria-hidden', 'false');
      cameraVideo.play();

      // prepare track / capabilities for zoom/focus
      videoTrack = stream.getVideoTracks()[0];
      try {
        trackCapabilities = videoTrack.getCapabilities ? videoTrack.getCapabilities() : null;
      } catch (e) {
        trackCapabilities = null;
      }

      setupCameraControls();
    }).catch(err => {
      alert('Kamera açılamadı: ' + (err.message || err));
      console.error(err);
    });
  }

  function closeCamera() {
    if (cameraStream) {
      cameraStream.getTracks().forEach(t => t.stop());
      cameraStream = null;
    }
    videoTrack = null;
    trackCapabilities = null;
    cameraVideo.pause();
    cameraVideo.srcObject = null;
    cameraOverlay.style.display = 'none';
    cameraOverlay.setAttribute('aria-hidden', 'true');
    // hide tools
    cameraTools.style.display = 'none';
    focusIndicator.style.opacity = 0;
  }

  // Setup zoom/focus UI based on capabilities
  function setupCameraControls() {
    cameraTools.style.display = 'none';
    // Reset slider
    zoomSlider.min = 1;
    zoomSlider.max = 1;
    zoomSlider.step = 0.1;
    zoomSlider.value = 1;
    currentZoom = 1;
    lastPinch = null;

    if (!trackCapabilities) return;

    // Zoom
    if ('zoom' in trackCapabilities) {
      const zmin = trackCapabilities.zoom.min || 1;
      const zmax = trackCapabilities.zoom.max || 1;
      const zstep = trackCapabilities.zoom.step || 0.1;
      zoomSlider.min = zmin;
      zoomSlider.max = zmax;
      zoomSlider.step = zstep;
      // apply initial if track has setting
      try {
        const settings = videoTrack.getSettings ? videoTrack.getSettings() : {};
        currentZoom = settings.zoom || 1;
        zoomSlider.value = currentZoom;
      } catch (e) {
        currentZoom = 1;
      }
      cameraTools.style.display = 'flex';
    } else {
      // no zoom support
      console.debug('Zoom not supported by this device.');
    }

    // Focus: best-effort - show instruction via tap; actual applyConstraints depends on support
    // We rely on potential 'focusMode' or 'pointsOfInterest' capability; many devices don't support programmatic focus.
    if ('focusMode' in trackCapabilities || 'pointsOfInterest' in trackCapabilities) {
      // show tap-to-focus feedback (focusIndicator) - no extra UI needed
      cameraVideo.style.cursor = 'crosshair';
    } else {
      cameraVideo.style.cursor = 'default';
      console.debug('Programmatic focus not supported (focusMode/pointsOfInterest absent).');
    }
  }

  // Apply zoom value to track
  async function applyZoom(value) {
    if (!videoTrack) return;
    if (!trackCapabilities || !('zoom' in trackCapabilities)) return;
    const z = Number(value);
    try {
      await videoTrack.applyConstraints({ advanced: [{ zoom: z }] });
      currentZoom = z;
      zoomSlider.value = z;
    } catch (e) {
      console.warn('applyConstraints(zoom) failed', e);
    }
  }

  zoomSlider.addEventListener('input', function() {
    applyZoom(this.value);
  });
  zoomInBtn.addEventListener('click', function() {
    const v = Math.min(Number(zoomSlider.max), Number(zoomSlider.value) + Number(zoomSlider.step));
    applyZoom(v);
  });
  zoomOutBtn.addEventListener('click', function() {
    const v = Math.max(Number(zoomSlider.min), Number(zoomSlider.value) - Number(zoomSlider.step));
    applyZoom(v);
  });

  // Pinch-to-zoom support (touch)
  cameraVideo.addEventListener('touchstart', function(ev) {
    if (ev.touches.length === 2) {
      lastPinch = distanceBetween(ev.touches[0], ev.touches[1]);
    }
  }, { passive: true });
  cameraVideo.addEventListener('touchmove', function(ev) {
    if (ev.touches.length === 2 && lastPinch) {
      const d = distanceBetween(ev.touches[0], ev.touches[1]);
      const delta = d - lastPinch;
      // adjust zoom by delta proportionally
      const range = Number(zoomSlider.max) - Number(zoomSlider.min);
      if (range > 0) {
        const change = (delta / 200) * range; // sensitivity
        let nv = Number(zoomSlider.value) + change;
        nv = Math.max(Number(zoomSlider.min), Math.min(Number(zoomSlider.max), nv));
        applyZoom(nv);
      }
      lastPinch = d;
    }
  }, { passive: true });
  cameraVideo.addEventListener('touchend', function(ev) {
    if (ev.touches.length < 2) lastPinch = null;
  });

  function distanceBetween(a, b) {
    const dx = a.clientX - b.clientX;
    const dy = a.clientY - b.clientY;
    return Math.sqrt(dx*dx + dy*dy);
  }

  // Tap-to-focus (best-effort)
  cameraVideo.addEventListener('click', function(ev) {
    // coordinates relative to video element (0..1)
    const rect = cameraVideo.getBoundingClientRect();
    const x = (ev.clientX - rect.left) / rect.width;
    const y = (ev.clientY - rect.top) / rect.height;

    // show visual feedback
    showFocusIndicator(ev.clientX - rect.left, ev.clientY - rect.top);

    if (!videoTrack || !trackCapabilities) return;

    // If device supports pointsOfInterest, try applyConstraints
    const supportsPOI = 'pointsOfInterest' in trackCapabilities;
    const supportsFocusMode = 'focusMode' in trackCapabilities;
    const supportsFocusDistance = 'focusDistance' in trackCapabilities;

    if (supportsPOI) {
      try {
        videoTrack.applyConstraints({ advanced: [{ pointsOfInterest: [{ x: x, y: y }] }] });
        console.debug('Applied pointsOfInterest', x, y);
        return;
      } catch (e) {
        console.warn('applyConstraints pointsOfInterest failed', e);
      }
    }

    // Try focusMode single-shot if available
    if (supportsFocusMode) {
      try {
        const modes = trackCapabilities.focusMode || [];
        if (modes.includes('single-shot')) {
          videoTrack.applyConstraints({ advanced: [{ focusMode: 'single-shot' }] });
          console.debug('Requested single-shot focus');
          return;
        } else if (modes.includes('continuous')) {
          // toggling continuous may trigger refocus
          videoTrack.applyConstraints({ advanced: [{ focusMode: 'continuous' }] });
        }
      } catch (e) {
        console.warn('applyConstraints focusMode failed', e);
      }
    }

    // Manual focusDistance if supported: attempt to set to mid-range (best-effort)
    if (supportsFocusDistance) {
      try {
        const fd = trackCapabilities.focusDistance;
        const min = fd.min || 0;
        const max = fd.max || 0;
        const mid = (min + max) / 2;
        videoTrack.applyConstraints({ advanced: [{ focusDistance: mid }] });
        console.debug('Applied focusDistance', mid);
        return;
      } catch (e) {
        console.warn('applyConstraints focusDistance failed', e);
      }
    }

    // If none supported, nothing to do - device likely handles auto-focus natively
    console.debug('Tap-to-focus not supported on this device.');
  });

  function showFocusIndicator(x, y) {
    focusIndicator.style.left = x + 'px';
    focusIndicator.style.top = y + 'px';
    focusIndicator.style.opacity = '1';
    focusIndicator.style.transform = 'translate(-50%,-50%) scale(1)';
    clearTimeout(focusIndicator._timeout);
    focusIndicator._timeout = setTimeout(() => {
      focusIndicator.style.opacity = '0';
      focusIndicator.style.transform = 'translate(-50%,-50%) scale(0.1)';
    }, 800);
  }

  // Resize and compress to approximate WhatsApp quality:
  // - Resize longest side to max 1280px
  // - JPEG quality ~0.65
  function imageBlobFromVideo(videoEl, quality = 0.65, maxSide = 1280) {
    const canvas = document.createElement('canvas');
    const vw = videoEl.videoWidth;
    const vh = videoEl.videoHeight;
    if (!vw || !vh) return Promise.reject(new Error('Video çözünürlüğü alınamadı.'));
    let nw = vw, nh = vh;
    if (Math.max(vw, vh) > maxSide) {
      if (vw > vh) {
        nw = maxSide;
        nh = Math.round((vh / vw) * maxSide);
      } else {
        nh = maxSide;
        nw = Math.round((vw / vh) * maxSide);
      }
    }
    canvas.width = nw;
    canvas.height = nh;
    const ctx = canvas.getContext('2d');
    // if you want mirrored preview handling you could flip here
    ctx.drawImage(videoEl, 0, 0, nw, nh);
    return new Promise((resolve) => {
      canvas.toBlob((blob) => {
        resolve(blob);
      }, 'image/jpeg', quality);
    });
  }

  takePhotoBtn.addEventListener('click', async function() {
    takePhotoBtn.disabled = true;
    takePhotoBtn.textContent = 'Kaydediliyor...';
    try {
      const blob = await imageBlobFromVideo(cameraVideo, 0.65, 1280);
      if (!blob) throw new Error('Fotoğraf alınamadı.');

      // Create filename: TIMESTAMP-ISEMRI-PLAKA.jpg (fallback values)
      const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
      const isemriVal = pdiEl.checked ? (vinEl.value || 'PDI') : (isemriEl.value || 'NOISE');
      const plakaVal = pdiEl.checked ? (vinEl.value || 'NOPL') : (plakaEl.value || 'NOPL');
      const safeIs = String(isemriVal).replace(/[^A-Za-z0-9\-]/g,'');
      const safePl = String(plakaVal).replace(/[^A-Za-z0-9\-]/g,'');
      const filename = `${timestamp}-${safeIs}-${safePl}.jpg`;

      const file = new File([blob], filename, { type: 'image/jpeg' });

      // Add to selectedFiles and update UI
      selectedFiles.push(file);
      updateInputFiles();
      renderFilesUI();

      // close camera after capture
      closeCamera();
    } catch (err) {
      console.error(err);
      alert('Fotoğraf alınırken hata: ' + (err.message || err));
    } finally {
      takePhotoBtn.disabled = false;
      takePhotoBtn.textContent = 'Çek';
    }
  });

  closeCameraBtn.addEventListener('click', function() {
    closeCamera();
  });

  captureBtn.addEventListener('click', function() {
    openCamera();
  });

  // Immediately render initial UI
  renderFilesUI();

  // Expose updateInputFiles/renderFilesUI for other code paths if needed
  window.__upload_helpers = { selectedFiles, updateInputFiles, renderFilesUI };

</script>
</body>
</html>