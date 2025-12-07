// assets/js/chat-widget.js
(function () {
  'use strict';

  // BASE detect (chat-init.php pre-sets window.CHAT_BASE)
  var BASE = (window.CHAT_BASE || '');

  if (!BASE) {
    try {
      var scriptEl = document.querySelector('script[src*="chat-widget.js"], script[src*="chat-widget-debug.js"]');
      if (scriptEl) {
        var src = scriptEl.getAttribute('src');
        var m = src.match(/^(?:https?:\/\/[^\/]+)?(.*)\/assets\/js\/chat-widget/);
        if (m && m[1]) BASE = m[1];
      }
    } catch (e) {
      console.warn('CHAT WIDGET: base autodetect failed', e);
    }
  }
  if (BASE && BASE.charAt(0) !== '/') BASE = '/' + BASE;
  if (BASE && BASE !== '/' && BASE.endsWith('/')) BASE = BASE.slice(0, -1);
  window.CHAT_BASE = BASE || '';

  var ENDPOINT_GET = (window.CHAT_BASE || '') + '/panel/api/chat/get_messages.php';
  var ENDPOINT_SEND = (window.CHAT_BASE || '') + '/panel/api/chat/send_message.php';
  var ENDPOINT_PRESENCE = (window.CHAT_BASE || '') + '/panel/api/chat/presence.php';

  console.log('CHAT WIDGET: BASE=', window.CHAT_BASE, 'GET=', ENDPOINT_GET);

  // If no current user, do not initialize interactive widget (security)
  if (!window.CURRENT_USER || !window.CURRENT_USER.user_id) {
    console.log('CHAT WIDGET: CURRENT_USER missing, widget will not initialize.');
    return;
  }

  var CURRENT_ID = window.CURRENT_USER.user_id;
  var CURRENT_NAME = window.CURRENT_USER.username;

  // State
  var lastMessageId = 0;
  var pollInterval = 2500;
  var presenceInterval = 15000;
  var isOpen = false;

  // Notification sound state
  var SOUND_KEY = 'chat_sound_enabled';
  var soundEnabled = (localStorage.getItem(SOUND_KEY) !== '0'); // default on
  var lastSoundAt = 0;
  var SOUND_MIN_INTERVAL = 800; // ms

  // Try to create an HTMLAudioElement for a bundled sound file
  var audioEl = null;
  var soundUrl = (window.CHAT_BASE || '') + '/assets/sounds/notify.mp3';
  (function tryLoadAudio() {
    audioEl = new Audio(soundUrl);
    audioEl.preload = 'auto';
    // try to load; if fails we'll fallback to WebAudio
    audioEl.addEventListener('error', function () {
      audioEl = null;
    });
  })();

  // WebAudio fallback beep generator
  var audioCtx = null;
  function ensureAudioContext() {
    if (!audioCtx) {
      try { audioCtx = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) { audioCtx = null; }
    }
    return audioCtx;
  }
  function playBeep(freq, duration) {
    var ctx = ensureAudioContext();
    if (!ctx) return;
    try {
      var o = ctx.createOscillator();
      var g = ctx.createGain();
      o.type = 'sine';
      o.frequency.value = freq || 880;
      g.gain.value = 0.001;
      o.connect(g);
      g.connect(ctx.destination);
      var now = ctx.currentTime;
      g.gain.linearRampToValueAtTime(0.12, now + 0.005);
      o.start(now);
      g.gain.exponentialRampToValueAtTime(0.0001, now + (duration || 0.12));
      o.stop(now + (duration || 0.12) + 0.02);
    } catch (e) {
      // ignore
    }
  }

  function playNotificationSound() {
    if (!soundEnabled) return;
    var now = Date.now();
    if (now - lastSoundAt < SOUND_MIN_INTERVAL) return;
    lastSoundAt = now;

    // First, try HTMLAudio
    if (audioEl) {
      // Some browsers disallow autoplay until user gesture — resume context on touch/click
      var playPromise = audioEl.play();
      if (playPromise !== undefined) {
        playPromise.catch(function (err) {
          // fallback to WebAudio
          playBeep(880, 0.12);
        });
      }
      return;
    }
    // fallback to WebAudio beep
    playBeep(880, 0.12);
  }

  // Build DOM
  var launcherWrap = document.createElement('div');
  launcherWrap.className = 'chat-widget-launcher';

  var launcherBtn = document.createElement('button');
  launcherBtn.className = 'chat-launcher-button';
  launcherBtn.title = 'Mesajlar';
  launcherBtn.innerText = '💬';
  launcherWrap.appendChild(launcherBtn);
  document.body.appendChild(launcherWrap);

  var chatWindow = document.createElement('div');
  chatWindow.className = 'chat-window';
  chatWindow.style.display = 'none';
  chatWindow.innerHTML = '\
    <div class="chat-header">\
      <div>\
        <div class="chat-title">Sohbet</div>\
        <div class="chat-users"><span id="chat-user-count">--</span> kullanıcı</div>\
      </div>\
      <div class="chat-controls">\
        <button id="chat-sound-toggle" title="Ses Aç/Kapa" aria-pressed="false">🔔</button>\
        <button id="chat-close-btn" title="Kapat">×</button>\
      </div>\
    </div>\
    <div class="chat-body" id="chat-body" aria-live="polite"></div>\
    <div class="chat-input">\
      <textarea id="chat-input" placeholder="Mesaj yazın..." maxlength="5000"></textarea>\
      <button id="chat-send-btn">Gönder</button>\
    </div>';
  document.body.appendChild(chatWindow);

  // small CSS tweak for sound button (in case your CSS doesn't include it)
  (function injectSmallStyles() {
    var s = document.createElement('style');
    s.innerHTML = '\
      .chat-controls{display:flex;gap:6px;align-items:center}\
      #chat-sound-toggle{background:transparent;border:none;cursor:pointer;font-size:16px;padding:6px;border-radius:4px}\
      #chat-sound-toggle[aria-pressed="true"]{background:#e6f7ff}';
    document.head.appendChild(s);
  })();

  var chatBody = chatWindow.querySelector('#chat-body');
  var inputEl = chatWindow.querySelector('#chat-input');
  var sendBtn = chatWindow.querySelector('#chat-send-btn');
  var closeBtn = chatWindow.querySelector('#chat-close-btn');
  var soundToggleBtn = chatWindow.querySelector('#chat-sound-toggle');
  var userCountEl = chatWindow.querySelector('#chat-user-count');

  // initialize sound button state
  function updateSoundButtonUI() {
    soundToggleBtn.setAttribute('aria-pressed', soundEnabled ? 'true' : 'false');
    soundToggleBtn.title = soundEnabled ? 'Ses açık (tıklayarak kapat)' : 'Ses kapalı (tıklayarak aç)';
    soundToggleBtn.textContent = soundEnabled ? '🔔' : '🔕';
  }
  updateSoundButtonUI();

  // Ensure user interaction to allow audio on some browsers
  function userGestureInit() {
    document.removeEventListener('click', userGestureInit);
    document.removeEventListener('keydown', userGestureInit);
    var ctx = ensureAudioContext();
    if (ctx && ctx.state === 'suspended') {
      ctx.resume().catch(function(){});
    }
  }
  document.addEventListener('click', userGestureInit, { once: true });
  document.addEventListener('keydown', userGestureInit, { once: true });

  soundToggleBtn.addEventListener('click', function () {
    soundEnabled = !soundEnabled;
    localStorage.setItem(SOUND_KEY, soundEnabled ? '1' : '0');
    updateSoundButtonUI();
    // hint: play short sound when enabling to confirm (if allowed)
    if (soundEnabled) playNotificationSound();
  });

  launcherBtn.addEventListener('click', function () {
    isOpen = !isOpen;
    chatWindow.style.display = isOpen ? 'flex' : 'none';
    if (isOpen) scrollToBottom();
  });
  closeBtn.addEventListener('click', function () { isOpen = false; chatWindow.style.display = 'none'; });

  sendBtn.addEventListener('click', sendMessage);
  inputEl.addEventListener('keypress', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
  });

  function safeText(t) {
    var d = document.createElement('div'); d.textContent = t; return d.innerHTML;
  }

  function renderMessage(msg) {
    var wrapper = document.createElement('div');
    wrapper.className = 'chat-message' + (msg.user_id == CURRENT_ID ? ' me' : '');
    var meta = document.createElement('div'); meta.className = 'meta';
    meta.innerHTML = '<strong>' + safeText(msg.username) + '</strong> <div class="meta-time">' + (new Date(msg.created_at)).toLocaleTimeString() + '</div>';
    var bubble = document.createElement('div'); bubble.className = 'bubble'; bubble.innerHTML = safeText(msg.message);
    wrapper.appendChild(meta); wrapper.appendChild(bubble);
    return wrapper;
  }

  function appendMessages(messages) {
    if (!Array.isArray(messages) || messages.length === 0) return;
    messages.forEach(function (m) {
      var el = renderMessage(m);
      chatBody.appendChild(el);
      // Play sound only if message is from someone else
      if (m.user_id != CURRENT_ID) {
        playNotificationSound();
      }
      lastMessageId = Math.max(lastMessageId, parseInt(m.id));
    });
    if (isOpen) scrollToBottom();
  }

  function scrollToBottom() { chatBody.scrollTop = chatBody.scrollHeight; }

  async function fetchMessages() {
    try {
      var res = await fetch(ENDPOINT_GET + '?since_id=' + lastMessageId, { credentials: 'same-origin' });
      if (!res.ok) return;
      var data = await res.json();
      if (data.success && Array.isArray(data.messages)) {
        // Only append new messages that have id > lastMessageId
        var newMsgs = data.messages.filter(function(m){ return parseInt(m.id) > lastMessageId; });
        appendMessages(newMsgs);
      }
    } catch (e) { /* silent */ }
  }

  async function sendMessage() {
    var text = inputEl.value.trim();
    if (!text) return;
    sendBtn.disabled = true;
    try {
      var res = await fetch(ENDPOINT_SEND, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: text })
      });
      if (!res.ok) {
        var t = await res.text();
        console.error('send_message HTTP error', res.status, t);
        alert('Ağ hatası (' + res.status + ')');
        return;
      }
      var data = await res.json();
      if (data.success && data.message) {
        appendMessages([data.message]); // will not play sound for own message (m.user_id == CURRENT_ID)
        inputEl.value = '';
      } else {
        alert(data.error || 'Gönderilemedi');
      }
    } catch (e) {
      console.error('sendMessage fetch error', e);
      alert('Ağ hatası');
    } finally { sendBtn.disabled = false; }
  }

  async function updatePresence() {
    try { await fetch(ENDPOINT_PRESENCE, { method: 'POST', credentials: 'same-origin' }); } catch (e) {}
  }

  async function fetchPresenceList() {
    try {
      var res = await fetch(ENDPOINT_PRESENCE + '?list=1', { credentials: 'same-origin' });
      if (!res.ok) return;
      var data = await res.json();
      if (data.success && Array.isArray(data.users)) {
        var onlineCount = data.users.filter(function (u) { return u.online; }).length;
        userCountEl.textContent = '' + onlineCount;
      }
    } catch (e) {}
  }

  // initial load
  (function init() {
    // restore sound preference from localStorage
    var stored = localStorage.getItem(SOUND_KEY);
    if (stored === '0') soundEnabled = false;
    else if (stored === '1') soundEnabled = true;
    updateSoundButtonUI();

    fetchMessages();
    updatePresence();
    fetchPresenceList();
    setInterval(fetchMessages, pollInterval);
    setInterval(updatePresence, presenceInterval);
    setInterval(fetchPresenceList, presenceInterval + 2000);
  })();

})();