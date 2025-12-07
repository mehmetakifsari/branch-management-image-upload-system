// assets/js/chat-widget.js
// Basit AJAX poll tabanlı chat widget (modern tarayıcılar için). Defer ile yükleyin.

(function () {
  const ENDPOINT_GET = '/panel/api/chat/get_messages.php';
  const ENDPOINT_SEND = '/panel/api/chat/send_message.php';
  const ENDPOINT_PRESENCE = '/panel/api/chat/presence.php';

  if (!window.CURRENT_USER || !window.CURRENT_USER.user_id) {
    return;
  }

  const CURRENT_ID = window.CURRENT_USER.user_id;
  const CURRENT_NAME = window.CURRENT_USER.username;

  let lastMessageId = 0;
  let pollInterval = 2000;
  let presenceInterval = 15000;
  let isOpen = false;

  const launcherWrap = document.createElement('div');
  launcherWrap.className = 'chat-widget-launcher';

  const launcherBtn = document.createElement('button');
  launcherBtn.className = 'chat-launcher-button';
  launcherBtn.title = 'Mesajlar';
  launcherBtn.innerText = '💬';
  launcherWrap.appendChild(launcherBtn);
  document.body.appendChild(launcherWrap);

  const chatWindow = document.createElement('div');
  chatWindow.className = 'chat-window';
  chatWindow.style.display = 'none';

  chatWindow.innerHTML = `
    <div class="chat-header">
      <div>
        <div class="chat-title">Sohbet</div>
        <div class="chat-users"><span id="chat-user-count">--</span> kullanıcı</div>
      </div>
      <div>
        <button id="chat-close-btn" title="Kapat">×</button>
      </div>
    </div>
    <div class="chat-body" id="chat-body" aria-live="polite"></div>
    <div class="chat-input">
      <textarea id="chat-input" placeholder="Mesaj yazın..." maxlength="5000"></textarea>
      <button id="chat-send-btn">Gönder</button>
    </div>
  `;
  document.body.appendChild(chatWindow);

  const chatBody = chatWindow.querySelector('#chat-body');
  const inputEl = chatWindow.querySelector('#chat-input');
  const sendBtn = chatWindow.querySelector('#chat-send-btn');
  const closeBtn = chatWindow.querySelector('#chat-close-btn');
  const userCountEl = chatWindow.querySelector('#chat-user-count');

  launcherBtn.addEventListener('click', () => {
    isOpen = !isOpen;
    chatWindow.style.display = isOpen ? 'flex' : 'none';
    if (isOpen) scrollToBottom();
  });

  closeBtn.addEventListener('click', () => {
    isOpen = false;
    chatWindow.style.display = 'none';
  });

  sendBtn.addEventListener('click', sendMessage);
  inputEl.addEventListener('keypress', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault(); sendMessage();
    }
  });

  function safeText(t) {
    const div = document.createElement('div');
    div.textContent = t;
    return div.innerHTML;
  }

  function renderMessage(msg) {
    const wrapper = document.createElement('div');
    wrapper.className = 'chat-message' + (msg.user_id == CURRENT_ID ? ' me' : '');
    const meta = document.createElement('div');
    meta.className = 'meta';
    meta.innerHTML = `<strong>${safeText(msg.username)}</strong> <div class="meta-time">${new Date(msg.created_at).toLocaleTimeString()}</div>`;
    const bubble = document.createElement('div');
    bubble.className = 'bubble';
    bubble.innerHTML = safeText(msg.message);
    wrapper.appendChild(meta);
    wrapper.appendChild(bubble);
    return wrapper;
  }

  function appendMessages(messages) {
    if (!Array.isArray(messages) || messages.length === 0) return;
    messages.forEach(m => {
      const el = renderMessage(m);
      chatBody.appendChild(el);
      lastMessageId = Math.max(lastMessageId, parseInt(m.id));
    });
    if (isOpen) scrollToBottom();
  }

  function scrollToBottom() {
    chatBody.scrollTop = chatBody.scrollHeight;
  }

  async function fetchMessages() {
    try {
      const res = await fetch(`${ENDPOINT_GET}?since_id=${lastMessageId}`, { credentials: 'same-origin' });
      const data = await res.json();
      if (data.success && Array.isArray(data.messages)) {
        appendMessages(data.messages);
      }
    } catch (e) {}
  }

  async function sendMessage() {
    const text = inputEl.value.trim();
    if (!text) return;
    sendBtn.disabled = true;
    try {
      const res = await fetch(ENDPOINT_SEND, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: text })
      });
      const data = await res.json();
      if (data.success && data.message) {
        appendMessages([data.message]);
        inputEl.value = '';
      } else {
        alert(data.error || 'Gönderilemedi');
      }
    } catch (e) {
      alert('Ağ hatası');
    } finally {
      sendBtn.disabled = false;
    }
  }

  async function updatePresence() {
    try {
      await fetch(`${ENDPOINT_PRESENCE}`, {
        method: 'POST',
        credentials: 'same-origin'
      });
    } catch (e) {}
  }

  async function fetchPresenceList() {
    try {
      const res = await fetch(`${ENDPOINT_PRESENCE}?list=1`, { credentials: 'same-origin' });
      const data = await res.json();
      if (data.success && Array.isArray(data.users)) {
        const onlineCount = data.users.filter(u => u.online).length;
        userCountEl.textContent = `${onlineCount}`;
      }
    } catch (e) {}
  }

  (async function init() {
    try {
      const res = await fetch(`${ENDPOINT_GET}`, { credentials: 'same-origin' });
      const data = await res.json();
      if (data.success && Array.isArray(data.messages)) {
        appendMessages(data.messages);
      }
    } catch (e) {}
    updatePresence();
    fetchPresenceList();
    setInterval(fetchMessages, pollInterval);
    setInterval(updatePresence, presenceInterval);
    setInterval(fetchPresenceList, presenceInterval + 2000);
  })();

})();