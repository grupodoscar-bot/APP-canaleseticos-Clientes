(function(){
  document.querySelectorAll('[data-copy]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var t = document.querySelector(btn.getAttribute('data-copy'));
      if (!t) return;
      var txt = t.innerText || t.textContent;
      if (navigator.clipboard) { navigator.clipboard.writeText(txt); }
      var old = btn.innerText; btn.innerText = 'Copiado';
      setTimeout(function(){ btn.innerText = old; }, 1800);
    });
  });
  document.querySelectorAll('form[data-confirm]').forEach(function(f){
    f.addEventListener('submit', function(e){
      if (!confirm(f.getAttribute('data-confirm'))) e.preventDefault();
    });
  });
  var qrEl = document.getElementById('qrcode');
  if (qrEl && qrEl.dataset.uri) {
    var s = document.createElement('script');
    s.src = '/assets/js/qrcode.min.js';
    s.onload = function(){
      qrEl.innerHTML = '';
      new QRCode(qrEl, {text: qrEl.dataset.uri, width: 180, height: 180, colorDark:'#000', colorLight:'#fff', correctLevel: QRCode.CorrectLevel.M});
    };
    s.onerror = function(){ qrEl.innerHTML = '<span style="font-size:.8rem;color:#888">QR no disponible</span>'; };
    document.head.appendChild(s);
  }
})();
