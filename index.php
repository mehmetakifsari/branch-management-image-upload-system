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
      background: #ffffff;
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

    input[type="text"], input[type="file"], select, .custom-file-display, button {
      margin: 0;
      padding: 10px 12px;
      width: 100%;
      border: 1px solid #E6E9EE;
      border-radius: 10px;
      background: #fff;
      color: #0F172A;
      font-size: 14px;
      outline: none;
      transition: border-color .12s ease, box-shadow .12s ease;
      text-transform: uppercase;
    }

    input[type="text"]:focus, select:focus, input[type="file"]:focus, button:focus { border-color:#2563EB; box-shadow:0 0 0 6px rgba(37,99,235,0.06); }

    .toggle-container { display:flex; align-items:center; gap:10px; margin-bottom:6px; }
    .toggle-container input[type="checkbox"] { width:18px; height:18px; accent-color:#2563EB; }
    .toggle-label { font-size:13px; color:#374151; }

    /* branch inline select */
    .branch-picker-inline { margin-top:8px; display:none; }
    .branch-select { text-transform: none; }

    .file-input-row { display:flex; gap:10px; align-items:center; }
    .file-input-row input[type="file"] { width:auto; padding:0; border:none; background:transparent; font-size:0; }
    input[type="file"]::file-selector-button { padding:8px 12px; margin-right:8px; border:1px solid #CBD5E1; background:#fff; border-radius:8px; cursor:pointer; font-weight:700; color:#0F172A; font-size:13px; }
    input[type="file"]::-webkit-file-upload-text { display:none; }
    input[type="file"]::-webkit-file-upload-button { font-size:13px; }

    .custom-file-display { background:#F8FAFC; border:1px solid #EDF2F7; color:#374151; padding:10px 12px; border-radius:10px; min-height:44px; display:flex; align-items:center; gap:8px; flex:1; font-size:13px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; text-transform:none; }

    .file-error { margin-top:8px; color:#B91C1C; font-size:13px; font-weight:600; }

    .preview { margin-top:14px; display:flex; flex-wrap:wrap; gap:10px; justify-content:flex-start; }
    .preview img { width:92px; height:auto; border:1px solid #E6E9EE; border-radius:8px; object-fit:cover; }

    .file-item { display:flex; align-items:center; gap:8px; padding:8px 10px; border-radius:10px; background:#F8FAFC; border:1px solid #EDF2F7; color:#334155; font-size:13px; max-width:260px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .file-icon { width:34px; height:34px; background:#E6EEF9; color:#2563EB; display:inline-flex; align-items:center; justify-content:center; border-radius:6px; font-weight:700; font-size:13px; }

    .remove-btn { margin-left:8px; background:transparent; border:none; color:#9CA3AF; cursor:pointer; font-size:16px; line-height:1; padding:2px 6px; border-radius:6px; }
    .remove-btn:hover { background:rgba(0,0,0,0.04); color:#ef4444; }

    .filename-chip { display:inline-flex; align-items:center; gap:8px; background:#fff; border-radius:8px; padding:6px 8px; border:1px solid #E6E9EE; margin-right:6px; max-width:160px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:13px; color:#1f2937; text-transform:none; }

    .hint { font-size:12px; color:#6B7280; margin-top:6px; }

    input[type="submit"], button.primary { width:100%; padding:10px 14px; background-color:#2563EB; color:white; border:none; cursor:pointer; border-radius:10px; margin-top:12px; font-size:15px; font-weight:700; box-shadow:0 8px 22px rgba(37,99,235,0.12); }
    input[type="submit"]:hover, button.primary:hover { filter:brightness(.98); }

    @media (max-width:480px) { .container{padding:18px;} .logo{width:180px;} }
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
        <div class="file-input-row">
          <input type="file" id="files" name="files[]" multiple accept=".tst,.pdf,.jpg,.jpeg,.png,.mp4,.oxps,.zip,.rar">
          <!-- Default display text, not hardcoded filename chips -->
          <div class="custom-file-display" id="fileNames">Dosya seçilmedi</div>
        </div>
        <div id="fileError" class="file-error" style="display:none;"></div>
        <div class="hint">İzin verilen uzantılar: .tst, .pdf, .jpg, .jpeg, .png, .mp4, .oxps, .zip, .rar. Görseller için önizleme gösterilir. Sunucu tarafı kontrolleri yapılmalıdır.</div>
      </div>

      <div class="preview" id="preview" aria-live="polite"></div>

      <input type="submit" value="Görsel Yükle">
    </form>
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

  // Allowed extensions
  const allowedExts = ['tst','pdf','jpg','jpeg','png','mp4','oxps','zip','rar'];

  // Selected files array for manipulation
  let selectedFiles = [];

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
</script>
</body>
</html>