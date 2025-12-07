// assets/js/chat-widget-debug.js (debug only)
console.log('CHAT WIDGET DEBUG: script loaded');

(function () {
  const BASE = (window.CHAT_BASE || '');
  console.log('CHAT WIDGET DEBUG: window.CHAT_BASE=', BASE);
  const ENDPOINT_GET = BASE + '/panel/api/chat/get_messages.php';
  const ENDPOINT_PRESENCE = BASE + '/panel/api/chat/presence.php';
  const ENDPOINT_SEND = BASE + '/panel/api/chat/send_message.php';

  if (!window.CURRENT_USER || !window.CURRENT_USER.user_id) {
    console.warn('CHAT WIDGET DEBUG: CURRENT_USER not set, creating temporary guest for debug');
    window.CURRENT_USER = { user_id: 0, username: 'guest-debug' };
  }
  console.log('CHAT WIDGET DEBUG: CURRENT_USER=', window.CURRENT_USER);

  // Create UI immediately
  const launcher = document.createElement('div');
  launcher.className = 'chat-widget-launcher';
  const btn = document.createElement('button');
  btn.className = 'chat-launcher-button';
  btn.textContent = '💬';
  launcher.appendChild(btn);
  document.body.appendChild(launcher);

  const win = document.createElement('div');
  win.className = 'chat-window';
  win.style.display = 'flex';
  win.innerHTML = `
    <div class="chat-header">
      <div><div class="chat-title">Sohbet (DEBUG)</div><div class="chat-users"><span id="chat-user-count">--</span> kullanıcı</div></div>
      <div><button id="chat-close-btn">×</button></div>
    </div>
    <div class="chat-body" id="chat-body"></div>
    <div class="chat-input"><textarea id="chat-input"></textarea><button id="chat-send-btn">Gönder</button></div>
  `;
  document.body.appendChild(win);

  const chatBody = win.querySelector('#chat-body');
  const input = win.querySelector('#chat-input');
  const sendBtn = win.querySelector('#chat-send-btn');

  btn.addEventListener('click', () => { win.style.display = (win.style.display === 'none') ? 'flex' : 'none'; });

  function logAndAppend(msg) {
    const p = document.createElement('div');
    p.textContent = msg;
    chatBody.appendChild(p);
  }

  async function tryFetchMessages() {
    console.log('CHAT WIDGET DEBUG: fetching messages from', ENDPOINT_GET);
    try {
      const r = await fetch(ENDPOINT_GET, { credentials: 'same-origin' });
      const data = await r.json();
      console.log('CHAT WIDGET DEBUG: get_messages response', data);
      logAndAppend('GET response: ' + JSON.stringify(data).slice(0,200));
    } catch (e) {
      console.warn('CHAT WIDGET DEBUG: get_messages error', e);
      logAndAppend('GET error: ' + e.message);
    }
  }

  async function tryPresence() {
    console.log('CHAT WIDGET DEBUG: calling presence', ENDPOINT_PRESENCE);
    try {
      const r = await fetch(ENDPOINT_PRESENCE, { method: 'POST', credentials: 'same-origin' });
      const data = await r.json();
      console.log('CHAT WIDGET DEBUG: presence response', data);
      logAndAppend('PRESENCE: ' + JSON.stringify(data).slice(0,200));
    } catch (e) {
      console.warn('CHAT WIDGET DEBUG: presence error', e);
      logAndAppend('PRESENCE error: ' + e.message);
    }
  }

  // Run once
  tryPresence();
  tryFetchMessages();
})();