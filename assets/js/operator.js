(function () {
  var WEB_ROOT = typeof window.WEB_ROOT !== 'undefined' ? window.WEB_ROOT : '/repair';
  var scanModal = document.getElementById('scan_modal');
  var formModal = document.getElementById('form_modal');
  var btnAdd = document.getElementById('btn_add_breakdown');
  var scanResult = document.getElementById('scan_result');
  var scanConfirm = document.getElementById('scan_confirm');
  var scanCancel = document.getElementById('scan_cancel');
  var formBack = document.getElementById('form_back');
  var breakdownForm = document.getElementById('breakdown_form');
  var formEquipmentLabel = document.getElementById('form_equipment_label');
  var fPlaceType = document.getElementById('f_place_type');
  var wrapSite = document.getElementById('wrap_site');
  var wrapOther = document.getElementById('wrap_other');
  var uploadedPhotos = [];
  var pendingUploads = 0;
  var lastScannedCode = null;
  var lastScannedName = null;
  var html5QrCode = null;
  var scanStarted = false;
  var formSubmitting = false;

  function showScanModal() {
    lastScannedCode = null;
    lastScannedName = null;
    scanResult.classList.add('hidden');
    scanResult.className = 'scan-result hidden';
    scanConfirm.classList.add('hidden');
    scanModal.classList.remove('hidden');
    startScanner();
  }

  function hideScanModal() {
    stopScanner();
    scanModal.classList.add('hidden');
  }

  function startScanner() {
    if (scanStarted) return;
    var readerEl = document.getElementById('reader');
    readerEl.innerHTML = '';
    html5QrCode = new Html5Qrcode('reader');
    var config = {
      fps: 10,
      qrbox: { width: 280, height: 220 },
      aspectRatio: 1.2,
      formatsToSupport: [
        Html5QrcodeSupportedFormats.QR_CODE,
        Html5QrcodeSupportedFormats.EAN_13,
        Html5QrcodeSupportedFormats.EAN_8,
        Html5QrcodeSupportedFormats.CODE_128,
        Html5QrcodeSupportedFormats.CODE_39,
        Html5QrcodeSupportedFormats.CODE_93,
        Html5QrcodeSupportedFormats.DATA_MATRIX,
        Html5QrcodeSupportedFormats.ITF,
        Html5QrcodeSupportedFormats.UPC_A,
        Html5QrcodeSupportedFormats.UPC_E
      ]
    };
    var constraints = {
      facingMode: 'environment'
    };
    if (/iPhone|iPad|iPod|Android/i.test(navigator.userAgent)) {
      constraints.facingMode = { exact: 'environment' };
    }
    function doStart() {
      html5QrCode.start(
        { facingMode: constraints.facingMode },
        config,
        function (decodedText) {
          if (lastScannedCode === decodedText) return;
          lastScannedCode = decodedText;
          html5QrCode.pause();
          checkBarcode(decodedText);
        },
        function () {}
      ).then(function () {
        scanStarted = true;
      }).catch(function (err) {
        scanResult.classList.remove('hidden');
        scanResult.className = 'scan-result error';
        scanResult.textContent = 'Не удалось запустить камеру. Разрешите доступ к камере и обновите страницу.';
      });
    }
    setTimeout(doStart, 100);
  }

  function stopScanner() {
    if (!html5QrCode || !scanStarted) return;
    html5QrCode.stop().then(function () {
      scanStarted = false;
    }).catch(function () {
      scanStarted = false;
    });
  }

  function checkBarcode(code) {
    scanResult.classList.remove('hidden');
    scanResult.textContent = 'Проверка...';
    scanResult.className = 'scan-result';
    var xhr = new XMLHttpRequest();
    xhr.open('GET', WEB_ROOT + '/api/check_barcode.php?code=' + encodeURIComponent(code));
    xhr.onload = function () {
      var data = {};
      try { data = JSON.parse(xhr.responseText); } catch (e) {}
      if (data.found) {
        lastScannedName = data.name || data.inventory_number;
        scanResult.className = 'scan-result';
        scanResult.textContent = data.name + ', ШК: ' + data.inventory_number + '. Подтвердите.';
        scanConfirm.classList.remove('hidden');
      } else {
        lastScannedCode = null;
        scanResult.className = 'scan-result error';
        scanResult.textContent = 'Код не найден в номенклатуре. Попробуйте снова.';
        html5QrCode.resume();
      }
    };
    xhr.onerror = function () {
      lastScannedCode = null;
      scanResult.className = 'scan-result error';
      scanResult.textContent = 'Ошибка связи. Попробуйте снова.';
      html5QrCode.resume();
    };
    xhr.send();
  }

  function showFormModal() {
    hideScanModal();
    formEquipmentLabel.textContent = lastScannedName + ', ШК: ' + lastScannedCode;
    document.getElementById('f_inventory_number').value = lastScannedCode;
    document.getElementById('f_description').value = '';
    document.getElementById('f_reproduction').value = '';
    document.getElementById('f_place_site').value = '';
    document.getElementById('f_place_other').value = '';
    document.getElementById('f_photos').value = '';
    uploadedPhotos = [];
    pendingUploads = 0;
    formSubmitting = false;
    document.getElementById('photo_previews').innerHTML = '';
    var us = document.getElementById('upload_status');
    us.textContent = '';
    us.classList.add('hidden');
    var submitBtn = document.getElementById('form_submit_btn');
    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Отправить'; }
    togglePlaceFields();
    formModal.classList.remove('hidden');
  }

  function updateUploadStatus() {
    var el = document.getElementById('upload_status');
    if (!el) return;
    if (pendingUploads > 0) {
      el.textContent = 'Загрузка фото: ' + pendingUploads + '…';
      el.classList.remove('hidden');
      el.classList.add('uploading');
    } else {
      el.classList.add('hidden');
      el.textContent = '';
    }
  }

  function togglePlaceFields() {
    var v = fPlaceType.value;
    wrapSite.classList.toggle('hidden', v !== 'site');
    wrapOther.classList.toggle('hidden', v !== 'other');
  }

  btnAdd.addEventListener('click', showScanModal);
  scanCancel.addEventListener('click', hideScanModal);
  scanConfirm.addEventListener('click', showFormModal);
  formBack.addEventListener('click', function () {
    formModal.classList.add('hidden');
    showScanModal();
  });
  fPlaceType.addEventListener('change', togglePlaceFields);

  breakdownForm.addEventListener('submit', function (e) {
    e.preventDefault();
    var submitBtn = document.getElementById('form_submit_btn');
    if (formSubmitting) return;
    if (pendingUploads > 0) {
      alert('Дождитесь окончания загрузки фото, затем нажмите «Отправить» ещё раз.');
      return;
    }
    formSubmitting = true;
    if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Отправка…'; }
    var fd = new FormData(breakdownForm);
    uploadedPhotos.forEach(function (id) { fd.append('photo_ids[]', id); });
    var xhr = new XMLHttpRequest();
    xhr.open('POST', WEB_ROOT + '/api/save_breakdown.php');
    xhr.onload = function () {
      var raw = (xhr.responseText || '').trim();
      var data = {};
      try { data = JSON.parse(raw); } catch (err) {
        if (xhr.status === 200 && (raw.indexOf('"ok":true') !== -1 || raw.indexOf('"ok": true') !== -1)) {
          data = { ok: true };
        }
      }
      if (data.ok) {
        formModal.classList.add('hidden');
        alert('Поломка внесена.');
        window.location.reload();
      } else {
        formSubmitting = false;
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Отправить';
        }
        alert((data.error || 'Ошибка отправки') + '\n\nЕсли поломка уже появилась в реестре — не нажимайте «Отправить» повторно.');
      }
    };
    xhr.onerror = function () {
      formSubmitting = false;
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Отправить';
      }
      alert('Ошибка связи. Проверьте интернет и попробуйте снова.\n\nЕсли поломка уже появилась в реестре — не нажимайте «Отправить» повторно.');
    };
    xhr.send(fd);
  });

  function isHeic(file) {
    var t = (file.type || '').toLowerCase();
    var n = (file.name || '').toLowerCase();
    return t === 'image/heic' || t === 'image/heif' || /\.heic$/.test(n) || /\.heif$/.test(n);
  }

  function uploadOneFile(file, preview) {
    var upload = function (f) {
      pendingUploads++;
      updateUploadStatus();
      var fd = new FormData();
      fd.append('photo', f);
      var xhr = new XMLHttpRequest();
      xhr.open('POST', WEB_ROOT + '/api/upload_photo.php');
      xhr.onload = function () {
        pendingUploads--;
        updateUploadStatus();
        var data = {};
        try { data = JSON.parse(xhr.responseText); } catch (e) {}
        if (data.ok) {
          uploadedPhotos.push(data.filename);
          var img = document.createElement('img');
          img.src = WEB_ROOT + '/uploads/breakdowns/' + data.filename;
          img.style.maxWidth = '80px'; img.style.maxHeight = '80px'; img.style.objectFit = 'cover'; img.style.borderRadius = '8px';
          preview.appendChild(img);
        }
      };
      xhr.onerror = function () {
        pendingUploads--;
        updateUploadStatus();
      };
      xhr.send(fd);
    };
    if (isHeic(file) && typeof heic2any === 'function') {
      heic2any({ blob: file, toType: 'image/jpeg', quality: 0.9 })
        .then(function (blob) {
          var jpeg = new File([blob], (file.name || 'photo').replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' });
          upload(jpeg);
        })
        .catch(function () { upload(file); });
    } else {
      upload(file);
    }
  }

  document.getElementById('f_photos').addEventListener('change', function () {
    var files = this.files;
    var preview = document.getElementById('photo_previews');
    for (var i = 0; i < files.length; i++) {
      uploadOneFile(files[i], preview);
    }
    this.value = '';
  });
})();
