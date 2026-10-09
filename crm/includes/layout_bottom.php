    </div>
  </div>
</div>
<?php if ($user): ?>
<div id="crmNotifyWrap" aria-live="polite"></div>
<style>
#crmNotifyWrap{position:fixed;top:16px;right:16px;z-index:9999;display:flex;flex-direction:column;gap:10px;max-width:340px;}
.crm-toast{background:#fff;border-left:4px solid var(--brand,#0a2472);border-radius:8px;box-shadow:0 10px 30px rgba(0,0,0,.18);padding:12px 14px;font-size:.88rem;color:#1c2438;cursor:pointer;animation:crmToastIn .25s ease;}
.crm-toast.new_order{border-left-color:#1a7f37;}
.crm-toast.status_change{border-left-color:#0a2472;}
.crm-toast b{display:block;margin-bottom:2px;font-size:.78rem;text-transform:uppercase;letter-spacing:.03em;color:#6b7490;}
@keyframes crmToastIn{from{opacity:0;transform:translateX(20px);}to{opacity:1;transform:translateX(0);}}
.crm-toast.crm-toast-out{animation:crmToastOut .2s ease forwards;}
@keyframes crmToastOut{to{opacity:0;transform:translateX(20px);}}
</style>
<script>
(function(){
  var POLL_MS = 15000;
  var SINCE_KEY = 'crm_notify_since';
  var since = localStorage.getItem(SINCE_KEY) || <?= json_encode(date('Y-m-d H:i:s')) ?>;

  var audioCtx = null;
  function ensureAudio(){
    if (!audioCtx) {
      try { audioCtx = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) { return null; }
    }
    if (audioCtx.state === 'suspended') { audioCtx.resume(); }
    return audioCtx;
  }
  document.addEventListener('click', ensureAudio, { once: true });

  function beep(freq, delay, dur){
    var ctx = ensureAudio();
    if (!ctx) return;
    setTimeout(function(){
      var osc = ctx.createOscillator();
      var gain = ctx.createGain();
      osc.connect(gain); gain.connect(ctx.destination);
      osc.type = 'sine';
      osc.frequency.value = freq;
      gain.gain.setValueAtTime(0.0001, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.35, ctx.currentTime + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + dur);
      osc.start();
      osc.stop(ctx.currentTime + dur + 0.05);
    }, delay);
  }
  function playChime(){ beep(880, 0, 0.3); beep(1320, 150, 0.28); }

  function showToast(ev){
    var wrap = document.getElementById('crmNotifyWrap');
    var el = document.createElement('div');
    el.className = 'crm-toast ' + ev.type;
    var label = ev.type === 'new_order' ? 'Новая заявка' : 'Смена статуса';
    el.innerHTML = '<b>' + label + '</b>' + ev.text.replace(/</g, '&lt;');
    el.addEventListener('click', function(){
      window.location.href = '/crm/order.php?id=' + ev.order_id;
    });
    wrap.appendChild(el);
    setTimeout(function(){
      el.classList.add('crm-toast-out');
      setTimeout(function(){ el.remove(); }, 250);
    }, 8000);
  }

  function poll(){
    fetch('/crm/api/notifications_poll.php?since=' + encodeURIComponent(since), { credentials: 'same-origin' })
      .then(function(r){ return r.ok ? r.json() : null; })
      .then(function(data){
        if (!data) return;
        if (data.events && data.events.length) {
          data.events.forEach(showToast);
          playChime();
        }
        if (data.server_time) {
          since = data.server_time;
          localStorage.setItem(SINCE_KEY, since);
        }
      })
      .catch(function(){ /* тихо игнорируем сбой одного опроса */ });
  }

  setInterval(poll, POLL_MS);
})();
</script>
<?php endif; ?>
</body>
</html>
