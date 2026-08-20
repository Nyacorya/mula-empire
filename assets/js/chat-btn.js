document.addEventListener('DOMContentLoaded', function() {
  const whatsappBtn = document.getElementById('whatsappFloatBtn');
  const modalEl = document.getElementById('chatLoginModal');
  const blurOverlay = document.getElementById('blurOverlay');

  if (!modalEl) return;
  const modal = new bootstrap.Modal(modalEl);

  // ---------- LOGIN MODAL LOGIC (same as before) ----------
  if (whatsappBtn) {
    whatsappBtn.addEventListener('click', function(e) {
      e.preventDefault();
      const stepEmail = document.getElementById('step-email');
      const stepPassword = document.getElementById('step-password');
      const emailInput = document.getElementById('chatEmail');
      const passwordInput = document.getElementById('chatPassword');
      const errorDiv = document.getElementById('loginError');
      const passwordError = document.getElementById('passwordError');

      if (stepEmail) stepEmail.style.display = 'block';
      if (stepPassword) stepPassword.style.display = 'none';
      if (emailInput) emailInput.value = '';
      if (passwordInput) passwordInput.value = '';
      if (errorDiv) errorDiv.textContent = '';
      if (passwordError) passwordError.textContent = '';
      modal.show();
    });
  }

  if (modalEl) {
    modalEl.addEventListener('show.bs.modal', function() {
      if (blurOverlay) blurOverlay.style.display = 'block';
      document.body.style.overflow = 'hidden';
    });
    modalEl.addEventListener('hidden.bs.modal', function() {
      if (blurOverlay) blurOverlay.style.display = 'none';
      document.body.style.overflow = '';
    });
  }

  const stepEmail = document.getElementById('step-email');
  const stepPassword = document.getElementById('step-password');
  const emailInput = document.getElementById('chatEmail');
  const passwordInput = document.getElementById('chatPassword');
  const errorDiv = document.getElementById('loginError');
  const passwordError = document.getElementById('passwordError');
  const emailContinueBtn = document.getElementById('emailContinueBtn');
  const passwordLoginBtn = document.getElementById('passwordLoginBtn');
  const backToEmailBtn = document.getElementById('backToEmailBtn');
  let currentEmail = '';

if (emailContinueBtn) {
  emailContinueBtn.addEventListener('click', function() {
    const email = emailInput.value.trim();
    if (errorDiv) errorDiv.textContent = '';

    // Strict email validation – prevents invalid emails from reaching server
    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    if (!emailRegex.test(email)) {
      if (errorDiv) errorDiv.textContent = 'Please enter a valid email address.';
      return;
    }

    emailContinueBtn.disabled = true;
    emailContinueBtn.textContent = 'Checking...';

    fetch('/api.php?action=check_user', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: email })
    })
    .then(res => res.text())
    .then(text => {
      try { return JSON.parse(text); }
      catch (e) { throw new Error('Invalid response: ' + text.substring(0, 200)); }
    })
    .then(data => {
      emailContinueBtn.disabled = false;
      emailContinueBtn.textContent = 'Continue';

      if (data.exists) {
        if (data.has_password) {
          currentEmail = email;
          if (stepEmail) stepEmail.style.display = 'none';
          if (stepPassword) stepPassword.style.display = 'block';
          const passwordPrompt = document.getElementById('passwordPrompt');
          if (passwordPrompt) passwordPrompt.textContent = 'User ' + email + ' found. Enter your password.';
          if (passwordError) passwordError.textContent = '';
          if (passwordInput) {
            passwordInput.value = '';
            passwordInput.focus();
          }
        } else {
          performLogin(email, '');
        }
      } else {
        performLogin(email, '');
      }
    })
    .catch(err => {
      if (errorDiv) errorDiv.textContent = '❌ ' + err.message;
      emailContinueBtn.disabled = false;
      emailContinueBtn.textContent = 'Continue';
    });
  });
}
  if (passwordLoginBtn) {
    passwordLoginBtn.addEventListener('click', function() {
      const password = passwordInput.value.trim();
      if (passwordError) passwordError.textContent = '';
      if (!password) {
        if (passwordError) passwordError.textContent = 'Please enter your password.';
        return;
      }
      performLogin(currentEmail, password);
    });
  }

  if (backToEmailBtn) {
    backToEmailBtn.addEventListener('click', function() {
      if (stepEmail) stepEmail.style.display = 'block';
      if (stepPassword) stepPassword.style.display = 'none';
      if (errorDiv) errorDiv.textContent = '';
      if (passwordError) passwordError.textContent = '';
    });
  }

function performLogin(email, password) {
  const btn = document.activeElement;
  const originalText = btn.textContent;

  // Strict email validation
  const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
  if (!emailRegex.test(email)) {
    const errDiv = document.getElementById('step-email').style.display !== 'none' ? errorDiv : passwordError;
    if (errDiv) errDiv.textContent = 'Please enter a valid email address.';
    btn.textContent = originalText;
    btn.disabled = false;
    return;
  }

  btn.textContent = 'Please wait...';
  btn.disabled = true;

  fetch('/api.php?action=chat_login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email: email, password: password })
  })
  .then(res => res.text())
  .then(text => {
    try { return JSON.parse(text); }
    catch (e) { throw new Error('Invalid response: ' + text.substring(0, 200)); }
  })
  .then(data => {
    if (data.success) {
      window.location.href = '/chat.php';
    } else {
      const errDiv = document.getElementById('step-password') && document.getElementById('step-password').style.display !== 'none' ? passwordError : errorDiv;
      if (errDiv) errDiv.textContent = data.error || 'Login failed.';
      btn.textContent = originalText;
      btn.disabled = false;
    }
  })
  .catch(err => {
    const errDiv = document.getElementById('step-password') && document.getElementById('step-password').style.display !== 'none' ? passwordError : errorDiv;
    if (errDiv) errDiv.textContent = '❌ ' + err.message;
    btn.textContent = originalText;
    btn.disabled = false;
  });
}

  // ---------- REAL-TIME DELIVERY ON INDEX PAGE ----------
function getAblyTokenForIndex(tokenParams, callback) {
  fetch('/api.php?action=ably_token')
    .then(res => res.json())
    .then(data => {
      if (data.token) {
        callback(null, data.token);
      } else {
        callback(new Error('No token'));
      }
    })
    .catch(err => callback(err));
}

async function initAblyForIndex() {
  if (!window.chatConfig || !window.chatConfig.isLoggedIn) return;
  try {
    const ably = new Ably.Realtime({
      authCallback: getAblyTokenForIndex
    });
    ably.connection.once('connected', async () => {
      try {
        const chatRes = await fetch('/api.php?action=my_chat');
        const chatData = await chatRes.json();
        if (chatData.chat_id) {
          const channel = ably.channels.get('chat_' + chatData.chat_id);
          channel.subscribe('new-message', (msg) => {
            fetch('/api.php?action=mark_chat_delivered', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ chat_id: msg.data.chat_id })
            }).catch(() => {});

            fetch('/api.php?action=unread_count')
              .then(res => res.json())
              .then(data => {
                const badge = document.querySelector('.unread-badge');
                if (badge) {
                  const count = data.unread || 0;
                  if (count > 0) {
                    badge.textContent = count > 9 ? '9+' : count;
                    badge.style.display = 'flex';
                  } else {
                    badge.style.display = 'none';
                  }
                }
              })
              .catch(() => {});
          });
        }
      } catch (e) {}
    });
  } catch (e) {}
}

  // Service worker registration (keep existing)
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/service-worker.js')
      .then(reg => console.log('Service Worker registered'))
      .catch(err => console.log('Service Worker registration failed:', err));
  }

  initAblyForIndex();
});

function updateAppBadge(count) {
    if (navigator.setAppBadge) {
        if (count > 0) {
            navigator.setAppBadge(count).catch(() => {});
        } else {
            navigator.clearAppBadge().catch(() => {});
        }
    }
}