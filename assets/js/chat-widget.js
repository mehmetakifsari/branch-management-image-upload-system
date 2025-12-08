// assets/js/chat-widgets.js
// Geliştirilmiş chat widget: mevcut message poll/send mantığını korur ve header'da tek user-count butonu + hover/click popup ile online kullanıcı listesini gösterir.
// Defer ile yüklenmeye uygun, global isimleri kirletmez.

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
  const pollInterval = 2000;
  const presenceInterval = 15000;
  let isOpen = false;

  // Create launcher button
  const launcherWrap = document.createElement('div');
  launcherWrap.className = 'chat-widget-launcher';

  const launcherBtn = document.createElement('button');
  launcherBtn.className = 'chat-launcher-button';
  launcherBtn.title = 'Mesajlar';
  launcherBtn.innerText = '💬';
  launcherWrap.appendChild(launcherBtn);
  document.body.appendChild(launcherWrap);

  // Create chat window
  const chatWindow = document.createElement('div');
  chatWindow.className = 'chat-window';
  chatWindow.style.display = 'none';

  // New header with single user-count button and online popup
  chatWindow.innerHTML = `
    <div class="chat-header">
      <div>
        <div class="chat-title">Sohbet</div>
      </div>
      <div style="display:flex;align-items:center;gap:8px">
        <button id="chat-user-count-btn" class="user-count" aria-haspopup="true" aria-expanded="false" title="Çevrimiçi kullanıcıları göster">
          <span class="count-dot" aria-hidden="true"></span>
          <span id="chat-user-count">--</span>
        </button>
        <button id="chat-close-btn" title="Kapat">×</button>
      </div>

      <div id="chat-online-popup" class="online-list" role="dialog" aria-label="Çevrimiçi kullanıcılar" aria-hidden="true">
        <div id="chat-online-list-inner" class="online-list-inner">
          <div class="empty">Çevrimiçi kullanıcı yok</div>
        </div>
      </div>
    </div>

    <div class="chat-body" id="chat-body" aria-live="polite"></div>
    <div class="chat-input">
      <textarea id="chat-input" placeholder="Mesaj yazın..." maxlength="5000"></textarea>
      <button id="chat-send-btn">Gönder</button>
    </div>
  `;
  document.body.appendChild(chatWindow);

  // Elements
  const chatBody = chatWindow.querySelector('#chat-body');
  const inputEl = chatWindow.querySelector('#chat-input');
  const sendBtn = chatWindow.querySelector('#chat-send-btn');
  const closeBtn = chatWindow.querySelector('#chat-close-btn');
  const userCountBtn = chatWindow.querySelector('#chat-user-count-btn');
  const userCountEl = chatWindow.querySelector('#chat-user-count');
  const onlinePopup = chatWindow.querySelector('#chat-online-popup');
  const onlineListInner = chatWindow.querySelector('#chat-online-list-inner');

  // Launcher interactions
  launcherBtn.addEventListener('click', () => {
    isOpen = !isOpen;
    chatWindow.style.display = isOpen ? 'flex' : 'none';
    if (isOpen) scrollToBottom();
  });
  closeBtn.addEventListener('click', () => {
    isOpen = false;
    chatWindow.style.display = 'none';
  });

  // Send interactions
  sendBtn.addEventListener('click', sendMessage);
  inputEl.addEventListener('keypress', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  });

  // Helpers
  function safeText(t) {
    const div = document.createElement('div');
    div.textContent = t;
    return div.innerHTML;
  }

  function renderMessage(msg) {
    const wrapper = document.createElement('div');
    wrapper.className = 'chat-message' + (String(msg.user_id) === String(CURRENT_ID) ? ' me' : '');
    const meta = document.createElement('div');
    meta.className = 'meta';
    const time = msg.created_at ? new Date(msg.created_at).toLocaleTimeString() : new Date().toLocaleTimeString();
    meta.innerHTML = `<strong>${safeText(msg.username || 'Anonim')}</strong> <span class="meta-time">${time}</span>`;
    const bubble = document.createElement('div');
    bubble.className = 'bubble';
    bubble.innerHTML = safeText(msg.message || '');

    wrapper.appendChild(meta);
    wrapper.appendChild(bubble);
    return wrapper;
  }

  function appendMessages(messages) {
    if (!Array.isArray(messages) || messages.length === 0) return;
    messages.forEach(m => {
      const el = renderMessage(m);
      chatBody.appendChild(el);
      if (m.id) lastMessageId = Math.max(lastMessageId, parseInt(m.id, 10) || 0);
    });
    if (isOpen) scrollToBottom();
  }

  function scrollToBottom() {
    chatBody.scrollTop = chatBody.scrollHeight;
  }

  // Message polling
  async function fetchMessages() {
    try {
      const res = await fetch(`${ENDPOINT_GET}?since_id=${lastMessageId}`, { credentials: 'same-origin' });
      if (!res.ok) return;
      const data = await res.json();
      const messages = (data && (data.messages || data));
      if (Array.isArray(messages)) {
        appendMessages(messages);
      } else if (data && data.success && Array.isArray(data.messages)) {
        appendMessages(data.messages);
      }
    } catch (e) {
      console.warn('fetchMessages error', e);
    }
  }

  // Send message
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
      if (!res.ok) throw new Error('Ağ hatası');
      const data = await res.json();
      if (data && data.success && data.message) {
        appendMessages([data.message]);
        inputEl.value = '';
      } else if (data && data.message) {
        appendMessages([data.message]);
        inputEl.value = '';
      } else {
        await fetchMessages();
        inputEl.value = '';
      }
    } catch (e) {
      console.warn('sendMessage error', e);
      alert('Mesaj gönderilemedi. Lütfen tekrar deneyin.');
    } finally {
      sendBtn.disabled = false;
    }
  }

  // Presence: heartbeat & list
  async function updatePresence() {
    try {
      await fetch(ENDPOINT_PRESENCE, { method: 'POST', credentials: 'same-origin' });
    } catch (e) { /* ignore */ }
  }

  async function fetchPresenceList() {
    try {
      const res = await fetch(`${ENDPOINT_PRESENCE}?list=1`, { credentials: 'same-origin' });
      if (!res.ok) return;
      const data = await res.json();
      let users = [];
      if (Array.isArray(data.users)) users = data.users;
      else if (Array.isArray(data.data)) users = data.data;
      else if (Array.isArray(data)) users = data;
      else if (data && data.success && Array.isArray(data.users)) users = data.users;
      updateOnlineList(users);
    } catch (e) {
      console.warn('fetchPresenceList error', e);
    }
  }

  function updateOnlineList(users) {
    onlineListInner.innerHTML = '';
    if (!users || users.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'empty';
      empty.textContent = 'Çevrimiçi kullanıcı yok';
      onlineListInner.appendChild(empty);
    } else {
      users.forEach(u => {
        const item = document.createElement('div');
        item.className = 'online-item';
        const avatar = document.createElement('div');
        avatar.className = 'avatar';
        if (u.avatar_url) {
          const img = document.createElement('img');
          img.src = u.avatar_url;
          img.alt = u.username || 'Kullanıcı';
          avatar.appendChild(img);
        } else {
          const initials = (u.username || '').split(' ').map(s => s[0]||'').join('').slice(0,2).toUpperCase();
          avatar.textContent = initials || '?';
        }
        const meta = document.createElement('div');
        const name = document.createElement('div');
        name.className = 'user-name';
        name.textContent = u.username || 'Anonim';
        const sub = document.createElement('div');
        sub.className = 'user-meta';
        sub.textContent = u.status || 'Çevrimiçi';
        meta.appendChild(name);
        meta.appendChild(sub);
        item.appendChild(avatar);
        item.appendChild(meta);
        onlineListInner.appendChild(item);
      });
    }
    userCountEl.textContent = String(users ? users.length : 0);
  }

  // Popup show/hide behavior
  let hideTimeout = null;
  function showPopup() {
    clearTimeout(hideTimeout);
    onlinePopup.setAttribute('aria-hidden', 'false');
    userCountBtn.setAttribute('aria-expanded', 'true');
  }
  function hidePopup() {
    clearTimeout(hideTimeout);
    onlinePopup.setAttribute('aria-hidden', 'true');
    userCountBtn.setAttribute('aria-expanded', 'false');
  }
  function hidePopupDelayed() {
    clearTimeout(hideTimeout);
    hideTimeout = setTimeout(hidePopup, 300);
  }

  userCountBtn.addEventListener('mouseenter', showPopup);
  userCountBtn.addEventListener('focus', showPopup);
  userCountBtn.addEventListener('mouseleave', hidePopupDelayed);
  userCountBtn.addEventListener('blur', hidePopup);
  onlinePopup.addEventListener('mouseenter', () => clearTimeout(hideTimeout));
  onlinePopup.addEventListener('mouseleave', hidePopupDelayed);
  userCountBtn.addEventListener('click', () => {
    const expanded = userCountBtn.getAttribute('aria-expanded') === 'true';
    if (expanded) hidePopup(); else showPopup();
  });

  // Init
  (async function init() {
    try {
      try {
        const res = await fetch(ENDPOINT_GET, { credentials: 'same-origin' });
        if (res.ok) {
          const data = await res.json();
          if (data && Array.isArray(data.messages)) appendMessages(data.messages);
          else if (Array.isArray(data)) appendMessages(data);
        }
      } catch (e) { /* ignore */ }

      updatePresence();
      await fetchPresenceList();
    } finally {
      setInterval(fetchMessages, pollInterval);
      setInterval(updatePresence, presenceInterval);
      setInterval(fetchPresenceList, presenceInterval + 2000);
    }
  })();

})();