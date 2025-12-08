// assets/js/tickets.js
// Drag & drop, preview, DataTransfer ile dosya yönetimi
document.addEventListener('DOMContentLoaded', function () {
  const dropZone = document.getElementById('dropZone');
  const fileInput = document.getElementById('fileInput');
  const chooseBtn = document.getElementById('chooseFilesBtn');
  const preview = document.getElementById('preview');
  const form = document.getElementById('ticket-create-form');

  const MAX_FILES = 5;
  const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB

  if (!fileInput || !form || !dropZone) {
    console.warn('tickets.js: gerekli DOM elemanları bulunamadı.');
    return;
  }

  // Butona tıklama file input'u tetikler
  chooseBtn?.addEventListener('click', () => fileInput.click());
  dropZone.addEventListener('click', () => fileInput.click());

  ['dragenter','dragover'].forEach(eventName => {
    dropZone.addEventListener(eventName, ev => {
      ev.preventDefault(); ev.stopPropagation();
      dropZone.classList.add('dragover');
    });
  });
  ['dragleave','dragend','drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, ev => {
      ev.preventDefault(); ev.stopPropagation();
      dropZone.classList.remove('dragover');
    });
  });

  dropZone.addEventListener('drop', function(e) {
    const dt = e.dataTransfer;
    if (!dt || !dt.files || dt.files.length === 0) return;
    addFiles(dt.files);
  });

  fileInput.addEventListener('change', updatePreview);

  function addFiles(fileList) {
    const currentFiles = Array.from(fileInput.files || []);
    const incoming = Array.from(fileList || []);
    const combined = currentFiles.concat(incoming);
    if (combined.length > MAX_FILES) {
      alert(`En fazla ${MAX_FILES} dosya seçebilirsiniz.`);
      return;
    }
    for (const f of incoming) {
      if (f.size > MAX_FILE_SIZE) {
        alert(`"${f.name}" dosyası ${MAX_FILE_SIZE/1024/1024} MB'dan büyük.`);
        return;
      }
    }
    const dt = new DataTransfer();
    combined.forEach(f => dt.items.add(f));
    fileInput.files = dt.files;
    updatePreview();
  }

  function updatePreview() {
    preview.innerHTML = '';
    const files = Array.from(fileInput.files || []);
    if (files.length === 0) {
      preview.innerHTML = '<div class="text-muted small">Henüz dosya seçilmedi.</div>';
      return;
    }

    files.forEach((file, idx) => {
      const container = document.createElement('div');
      container.style.margin = '4px';
      container.style.width = '110px';
      container.style.textAlign = 'center';

      const wrap = document.createElement('div');
      wrap.className = 'file-thumb';
      wrap.style.position = 'relative';

      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.innerText = '×';
      removeBtn.className = 'remove-btn';
      removeBtn.addEventListener('click', () => removeFile(idx));
      wrap.appendChild(removeBtn);

      if (file.type && file.type.startsWith('image/')) {
        const img = document.createElement('img');
        img.style.maxWidth = '100%';
        img.style.maxHeight = '100%';
        const reader = new FileReader();
        reader.onload = e => { img.src = e.target.result; };
        reader.readAsDataURL(file);
        wrap.appendChild(img);
      } else {
        const icon = document.createElement('div');
        icon.innerHTML = '<svg width="32" height="32" viewBox="0 0 24 24"><path fill="#6c757d" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path></svg>';
        wrap.appendChild(icon);
      }

      const meta = document.createElement('div');
      meta.className = 'file-meta';
      meta.style.fontSize = '12px';
      meta.style.marginTop = '4px';
      meta.innerText = file.name;

      container.appendChild(wrap);
      container.appendChild(meta);
      preview.appendChild(container);
    });
  }

  function removeFile(index) {
    const files = Array.from(fileInput.files || []);
    if (index < 0 || index >= files.length) return;
    files.splice(index, 1);
    const dt = new DataTransfer();
    files.forEach(f => dt.items.add(f));
    fileInput.files = dt.files;
    updatePreview();
  }

  // İlk yüklemede preview'ı güncelle
  updatePreview();
});