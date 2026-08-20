// ==========================================
// Chat Application – Full JavaScript
// ==========================================

// Config from PHP
const currentUserId = window.chatConfig.currentUserId;
const currentRole = window.chatConfig.currentRole;
const currentTheme = window.chatConfig.theme;
const isAdmin = (currentRole === 'admin' || currentRole === 'ceo');
const siteId = window.chatConfig.siteId;

let currentChatId = null;
let allMessages = [];
let replyToMessageId = null;
let replyToUsername = null;
let replyToText = null;

// Selection / action state
const selectedMessages = new Set();
let selectionMode = false;
let isForwarding = false;
const forwardTargetChats = new Set();
let forwardMessageIds = [];

// DOM references
const headerMain = document.getElementById('appHeaderMain');
const headerChat = document.getElementById('appHeaderChat');
const headerUser = document.getElementById('appHeaderUser');
const adminBackToChats = document.getElementById('adminBackToChats');
const headerChatUserIcon = document.getElementById('headerChatUserIcon');
const headerChatUserName = document.getElementById('headerChatUserName');
const msgList = document.getElementById('messageList');
const chatTitle = document.getElementById('chatTitle');
const chatAvatarImg = document.getElementById('chatAvatarImg');
const chatAvatarPlaceholder = document.getElementById('chatAvatarPlaceholder');
const chatListDiv = document.getElementById('chatList');
const form = document.getElementById('messageForm');
const msgBody = document.getElementById('msgBody');
const mediaUrl = document.getElementById('mediaUrl');
const logoutBtn = document.getElementById('logoutBtn');
const logoutBtnUser = document.getElementById('logoutBtnUser');
const chatView = document.getElementById('chatView');
const chatListView = document.getElementById('chatListView');
const backBtn = document.getElementById('backBtn');
const replyPreview = document.getElementById('replyPreview');
const composeReplyContent = document.getElementById('composeReplyContent');
const cancelReply = document.getElementById('cancelReply');
const selectedCountMain = document.getElementById('selectedCountMain');
const selectedCountChat = document.getElementById('selectedCountChat');

// Attachment & voice
const attachBtn = document.getElementById('attachBtn');
const fileInput = document.getElementById('fileInput');
const voiceBtn = document.getElementById('voiceBtn');
const voiceRecordingUI = document.getElementById('voiceRecordingUI');
const voiceWave = document.getElementById('voiceWave');
const voiceTimer = document.getElementById('voiceTimer');
const pauseVoiceBtn = document.getElementById('pauseVoiceBtn');
const stopVoiceBtn = document.getElementById('stopVoiceBtn');
const discardVoiceBtn = document.getElementById('discardVoiceBtn');
const sendVoiceBtn = document.getElementById('sendVoiceBtn');

// User menu
const userMenuBtn = document.getElementById('userMenuBtn');
const userDropdown = document.getElementById('userDropdown');

// Notification prompt
const notifPrompt = document.getElementById('notificationPrompt');
const enableNotifBtn = document.getElementById('enableNotificationsBtn');
const dismissNotifBtn = document.getElementById('dismissPromptBtn');

// Admin action buttons
const actionBtnMain = document.getElementById('messageActionsBtn');
const actionMenuMain = document.getElementById('messageActionsMenu');
const actionBtnChat = document.getElementById('messageActionsBtnChat');
const actionMenuChat = document.getElementById('messageActionsMenuChat');
const forwardSendBtn = document.getElementById('forwardSendBtn');

// Voice state
let mediaRecorder;
let audioChunks = [];
let voiceBlob = null;
let voiceStartTime = 0;
let timerInterval = null;
let isRecording = false;
let isPaused = false;
let voiceStream = null;
let shouldSendVoice = false;

const audioPlayers = new Map();

// Double tap/click state
let lastTapTime = 0;
let lastTapElement = null;
let doubleTapDetected = false;

// Ably realtime
let ably = null;
let currentChannel = null;
let typingTimeout = null;

// Back-to-bottom button state
let isNearBottom = true;
let unreadBottomCount = 0;
let backToBottomBtn = null;
let backToBottomBadge = null;

// Bind cancel reply button
if (cancelReply) {
    cancelReply.addEventListener('click', clearReply);
}

// ---------- THEME ----------
function setTheme(theme) {
    if (theme === 'dark') {
        document.body.classList.add('dark-theme');
        document.body.classList.remove('light-theme');
        document.querySelectorAll('.theme-toggle i, #themeToggleUser i, #themeIcon').forEach(icon => {
            icon.className = 'fas fa-sun';
        });
    } else {
        document.body.classList.add('light-theme');
        document.body.classList.remove('dark-theme');
        document.querySelectorAll('.theme-toggle i, #themeToggleUser i, #themeIcon').forEach(icon => {
            icon.className = 'fas fa-moon';
        });
    }
    localStorage.setItem('theme', theme);
    fetch('/api.php?action=update_theme', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ theme: theme })
    }).catch(err => console.log('Theme update error:', err));
}

document.addEventListener('DOMContentLoaded', function() {
    const saved = localStorage.getItem('theme');
    if (saved && saved !== currentTheme) {
        setTheme(saved);
    } else {
        setTheme(currentTheme);
    }
    bindThemeToggles();
    updateMessageRequired();
    bindActionMenus();
    initAbly();
    createBackToBottomButton();

    // Scroll listener: update isNearBottom and button
    msgList.addEventListener('scroll', () => {
        const near = msgList.scrollHeight - msgList.scrollTop - msgList.clientHeight < 120;
        if (near !== isNearBottom) {
            isNearBottom = near;
            if (isNearBottom) {
                unreadBottomCount = 0;
            }
            updateBackToBottomButton();
        }
    });
});

function bindThemeToggles() {
    document.querySelectorAll('.theme-toggle, #themeToggleUser').forEach(btn => {
        btn.addEventListener('click', () => {
            const isDark = document.body.classList.contains('dark-theme');
            setTheme(isDark ? 'light' : 'dark');
        });
    });
}

// ---------- USER MENU ----------
if (userMenuBtn) {
    userMenuBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        userDropdown.style.display = userDropdown.style.display === 'block' ? 'none' : 'block';
    });
    document.addEventListener('click', () => {
        userDropdown.style.display = 'none';
    });
}
if (logoutBtnUser) {
    logoutBtnUser.addEventListener('click', () => {
        window.location.href = '?logout';
    });
}
if (logoutBtn) {
    logoutBtn.addEventListener('click', () => {
        window.location.href = '?logout';
    });
}

// ---------- API HELPER ----------
async function apiFetch(action, method = 'GET', body = null) {
    const opts = { method, headers: { 'Content-Type': 'application/json' } };
    if (body) opts.body = JSON.stringify(body);
    const res = await fetch(`/api.php?action=${action}`, opts);
    if (!res.ok) {
        const text = await res.text();
        throw new Error(`HTTP ${res.status}: ${text}`);
    }
    return res.json();
}

// ---------- MESSAGE REQUIRED TOGGLE ----------
function updateMessageRequired() {
    if (mediaUrl.value.trim()) {
        msgBody.removeAttribute('required');
    } else {
        msgBody.setAttribute('required', 'required');
    }
}

// ---------- ATTACH BUTTON ----------
attachBtn.addEventListener('click', () => fileInput.click());

fileInput.addEventListener('change', async () => {
    const file = fileInput.files[0];
    if (!file) return;
    mediaUrl.value = '';
    voiceBtn.style.color = '';
    attachBtn.style.color = '';
    await uploadFile(file, false);
    fileInput.value = '';
});

// ---------- VOICE RECORDING ----------
voiceBtn.addEventListener('click', () => {
    if (isRecording) return;
    startVoiceRecording();
});

async function startVoiceRecording() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        alert('Voice recording not supported in this browser.');
        return;
    }
    try {
        voiceStream = await navigator.mediaDevices.getUserMedia({ audio: true });
        mediaRecorder = new MediaRecorder(voiceStream);
        audioChunks = [];
        shouldSendVoice = false;

        mediaRecorder.addEventListener('dataavailable', event => {
            audioChunks.push(event.data);
        });

        mediaRecorder.addEventListener('stop', () => {
            voiceBlob = new Blob(audioChunks, { type: 'audio/webm' });
            clearInterval(timerInterval);
            voiceWave.classList.remove('recording');

            if (shouldSendVoice) {
                shouldSendVoice = false;
                sendVoiceNoteImmediately();
            } else {
                voiceRecordingUI.style.display = 'flex';
                pauseVoiceBtn.style.display = 'none';
                stopVoiceBtn.style.display = 'none';
                discardVoiceBtn.style.display = 'inline-flex';
                sendVoiceBtn.style.display = 'inline-flex';
            }
            isRecording = false;
        });

        mediaRecorder.start();
        isRecording = true;
        isPaused = false;
        voiceStartTime = Date.now();
        timerInterval = setInterval(updateVoiceTimer, 200);

        voiceRecordingUI.style.display = 'flex';
        voiceWave.classList.add('recording');
        pauseVoiceBtn.innerHTML = '<i class="fas fa-pause"></i>';
        pauseVoiceBtn.style.display = 'inline-flex';
        stopVoiceBtn.style.display = 'inline-flex';
        discardVoiceBtn.style.display = 'inline-flex';
        sendVoiceBtn.style.display = 'inline-flex';
        document.querySelector('.compose .input-row').style.display = 'none';

        voiceBtn.innerHTML = '<i class="fas fa-microphone-alt"></i>';
        voiceBtn.style.color = '#e74c3c';
    } catch (err) {
        alert('Could not access microphone: ' + err.message);
    }
}

function updateVoiceTimer() {
    const elapsed = Math.floor((Date.now() - voiceStartTime) / 1000);
    const mins = Math.floor(elapsed / 60);
    const secs = elapsed % 60;
    voiceTimer.textContent = `${mins}:${secs < 10 ? '0' : ''}${secs}`;
}

pauseVoiceBtn.addEventListener('click', () => {
    if (!mediaRecorder) return;
    if (mediaRecorder.state === 'recording') {
        mediaRecorder.pause();
        isPaused = true;
        pauseVoiceBtn.innerHTML = '<i class="fas fa-play"></i>';
        voiceWave.classList.remove('recording');
    } else if (mediaRecorder.state === 'paused') {
        mediaRecorder.resume();
        isPaused = false;
        pauseVoiceBtn.innerHTML = '<i class="fas fa-pause"></i>';
        voiceWave.classList.add('recording');
    }
});

stopVoiceBtn.addEventListener('click', () => {
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        mediaRecorder.stop();
    }
});

discardVoiceBtn.addEventListener('click', () => {
    if (!voiceBlob && !isRecording) return;
    if (confirm('Discard this voice recording?')) {
        cleanupRecording();
        voiceBlob = null;
        mediaUrl.value = '';
        attachBtn.style.color = '';
        voiceBtn.style.color = '';
        voiceBtn.innerHTML = '<i class="fas fa-microphone"></i>';
        document.querySelector('.compose .input-row').style.display = 'flex';
        updateMessageRequired();
    }
});

sendVoiceBtn.addEventListener('click', () => {
    if (isRecording) {
        shouldSendVoice = true;
        mediaRecorder.stop();
    } else {
        sendVoiceNoteImmediately();
    }
});

async function sendVoiceNoteImmediately() {
    if (!voiceBlob) return;
    await uploadFile(voiceBlob, true);
    const body = msgBody.value.trim();
    const attachedMedia = mediaUrl.value.trim();
    if (!attachedMedia) return;
    try {
        await sendMessageNow(body || '', attachedMedia);
        msgBody.value = '';
        mediaUrl.value = '';
        clearReply();
        updateMessageRequired();
        attachBtn.style.color = '';
        voiceBtn.style.color = '';
        voiceBtn.innerHTML = '<i class="fas fa-microphone"></i>';
    } catch (err) {
        alert('Failed to send voice note: ' + err.message);
    }
    cleanupRecording();
    voiceBlob = null;
    document.querySelector('.compose .input-row').style.display = 'flex';
}

function cleanupRecording() {
    if (voiceStream) {
        voiceStream.getTracks().forEach(track => track.stop());
        voiceStream = null;
    }
    if (mediaRecorder) {
        if (mediaRecorder.state !== 'inactive') mediaRecorder.stop();
        mediaRecorder = null;
    }
    clearInterval(timerInterval);
    voiceWave.classList.remove('recording');
    voiceRecordingUI.style.display = 'none';
    document.querySelector('.compose .input-row').style.display = 'flex';
    isRecording = false;
}

// ---------- FILE UPLOAD ----------
async function uploadFile(file, isVoice = false) {
    const formData = new FormData();
    formData.append('file', file);
    if (isVoice) {
        formData.append('subfolder', 'voice');
    }

    attachBtn.disabled = true;
    voiceBtn.disabled = true;
    const originalAttachHtml = attachBtn.innerHTML;
    attachBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    try {
        const response = await fetch('/api.php?action=upload_attachment', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            mediaUrl.value = data.url;
            updateMessageRequired();
            if (isVoice) {
                voiceBtn.style.color = '#f5c842';
            } else {
                attachBtn.style.color = '#f5c842';
            }
        } else {
            alert('Upload failed: ' + (data.error || 'Unknown error'));
            throw new Error(data.error || 'Upload failed');
        }
    } catch (err) {
        alert('Upload error: ' + err.message);
        throw err;
    } finally {
        attachBtn.disabled = false;
        voiceBtn.disabled = false;
        attachBtn.innerHTML = originalAttachHtml;
    }
}

// ---------- REPLY ----------
function clearReply() {
    replyToMessageId = null;
    replyToUsername = null;
    replyToText = null;
    replyPreview.classList.remove('show');
    replyPreview.style.display = 'none';
    if (composeReplyContent) composeReplyContent.innerHTML = '';
}

function buildReplyPreviewContent(msg) {
    if (!msg) return { left: '', right: '' };
    if (msg.deleted === 'yes') {
        return { left: `<div class="deleted-text">This message was deleted!</div>`, right: '' };
    }
    const sender = msg.username || msg.email || 'User';
    const hasMedia = msg.media_url ? true : false;
    let leftHtml = '';
    let rightHtml = '';

    leftHtml += `<div class="reply-sender"><i class="fas fa-reply"></i> ${escapeHtml(sender)}</div>`;
    if (hasMedia) {
        const ext = msg.media_url.split('.').pop().toLowerCase();
        let mediaType = '';
        let iconClass = '';
        if (['jpg','jpeg','png','gif','webp'].includes(ext)) {
            mediaType = 'Image';
            iconClass = 'fa-image';
            rightHtml = `<img src="${msg.media_url}" alt="thumb" style="width:100%; height:100%; object-fit:cover;">`;
        } else if (['mp4','webm','ogg'].includes(ext)) {
            mediaType = 'Video';
            iconClass = 'fa-video';
            rightHtml = `<i class="fas fa-video" style="font-size:1.2rem; color:var(--text-light);"></i>`;
        } else if (msg.media_url.includes('/voice/')) {
            mediaType = 'Voice Note';
            iconClass = 'fa-microphone';
            rightHtml = `<i class="fas fa-play" style="font-size:1.2rem; color:var(--text-light);"></i>`;
        } else {
            mediaType = 'Audio';
            iconClass = 'fa-music';
            rightHtml = `<i class="fas fa-play" style="font-size:1.2rem; color:var(--text-light);"></i>`;
        }
        leftHtml += `<div class="reply-media-line"><i class="fas ${iconClass}"></i> ${mediaType}</div>`;
        leftHtml += `<div class="reply-caption">${escapeHtml(msg.message_body || '')}</div>`;
    } else {
        const text = msg.message_body || '';
        leftHtml += `<div class="reply-text-only">${escapeHtml(text)}</div>`;
    }

    return { left: leftHtml, right: rightHtml };
}

function renderReplyPreview(msg) {
    if (!msg) return '';
    const content = buildReplyPreviewContent(msg);
    return `
        <div class="reply-preview-wrap" data-reply-id="${msg.message_id}" onclick="scrollToOriginal(this)">
            <div class="reply-info" style="flex:1; min-width:0;">${content.left}</div>
            ${content.right ? `<div class="reply-thumb">${content.right}</div>` : ''}
        </div>
    `;
}

function setReply(messageId, msg) {
    replyToMessageId = messageId;
    replyToUsername = msg.username || msg.email || 'User';
    replyToText = msg.message_body || '';

    if (!composeReplyContent || !replyPreview) return;

    const content = buildReplyPreviewContent(msg);
    composeReplyContent.innerHTML = `
        <div class="reply-info" style="flex:1; min-width:0;">${content.left}</div>
        ${content.right ? `<div class="reply-thumb">${content.right}</div>` : ''}
    `;
    replyPreview.classList.add('show');
    replyPreview.style.display = 'flex';
    msgBody.focus();
}

window.scrollToOriginal = function(el) {
    const replyId = el.dataset.replyId;
    if (!replyId) return;
    const targetMessage = document.querySelector(`.message-wrapper[data-message-id="${replyId}"]`);
    if (targetMessage) {
        targetMessage.scrollIntoView({ behavior: 'smooth', block: 'center' });
        targetMessage.classList.add('highlight');
        setTimeout(() => targetMessage.classList.remove('highlight'), 3000);
    }
};

// ---------- NAVIGATION (ADMIN) ----------
function showChatView(show) {
    if (!isAdmin) return;
    const isMobile = window.innerWidth <= 768;

    if (isMobile) {
        if (show) {
            headerMain.classList.add('hidden');
            headerChat.classList.remove('hidden');
            chatView.classList.add('open');
            chatListView.classList.add('hidden');
        } else {
            headerMain.classList.remove('hidden');
            headerChat.classList.add('hidden');
            chatView.classList.remove('open');
            chatListView.classList.remove('hidden');
        }
    } else {
        headerMain.classList.remove('hidden');
        headerChat.classList.add('hidden');
        chatView.classList.remove('open');
        chatListView.classList.remove('hidden');
    }
}

function resetChatUI() {
    chatTitle.textContent = 'Select a chat';
    chatAvatarImg.style.display = 'none';
    chatAvatarPlaceholder.textContent = '👤';
    msgList.innerHTML = `<div style="text-align:center; color:var(--text-light); padding:2rem; font-style:italic;">Select a chat from the list</div>`;
    currentChatId = null;
    allMessages = [];
    clearReply();
    clearSelection();
    if (currentChannel) currentChannel.unsubscribe();
    document.querySelectorAll('.chat-list-item').forEach(item => item.classList.remove('selected'));
}

backBtn.addEventListener('click', () => {
    if (isAdmin) {
        showChatView(false);
        resetChatUI();
    }
});

if (adminBackToChats) {
    adminBackToChats.addEventListener('click', () => {
        showChatView(false);
        resetChatUI();
    });
}

// ---------- LOAD CHATS (ADMIN) ----------
async function loadChats() {
    if (!isAdmin) return;
    try {
        const chats = await apiFetch('chats');
        chatListDiv.innerHTML = '';
        if (chats.length === 0) {
            chatListDiv.innerHTML = '<div style="color:var(--text-light); padding:0.5rem;">No chats yet</div>';
            return;
        }
        for (const c of chats) {
            const div = document.createElement('div');
            div.className = 'chat-list-item';
            div.dataset.chatId = c.chat_id;
            const displayName = c.username || c.email || 'User';
            const icon = c.icon_url || (
                (c.role === 'admin' || c.role === 'ceo')
                    ? 'https://zany-tech.com/img/admin.jpg'
                    : 'https://zany-tech.com/img/user.png'
            );
            const badgeHtml = c.unread > 0 ? `<span class="badge">${c.unread}</span>` : '';
            const lastMsg = c.last_msg || 'No messages';
            const lastTime = c.last_time ? new Date(c.last_time).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) : '';

            div.innerHTML = `
                <img src="${icon}" alt="" class="avatar-large avatar-border">
                <div class="info">
                    <div class="top-row">
                        <span class="name">${displayName}</span>
                        ${badgeHtml}
                    </div>
                    <div class="bottom-row">
                        <span class="last-msg">${escapeHtml(lastMsg)}</span>
                        <span class="time">${lastTime}</span>
                    </div>
                </div>
            `;
            if (isForwarding) {
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'forward-check';
                checkbox.checked = forwardTargetChats.has(String(c.chat_id));
                checkbox.addEventListener('change', () => {
                    if (checkbox.checked) forwardTargetChats.add(String(c.chat_id));
                    else forwardTargetChats.delete(String(c.chat_id));
                });
                div.appendChild(checkbox);
            }
            div.addEventListener('click', (e) => {
                if (isForwarding) {
                    if (e.target.type !== 'checkbox') {
                        const cb = div.querySelector('.forward-check');
                        if (cb) {
                            cb.checked = !cb.checked;
                            cb.dispatchEvent(new Event('change'));
                        }
                    }
                    return;
                }
                openChat(c.chat_id, displayName, icon);
            });
            chatListDiv.appendChild(div);
        }
    } catch (e) {
        chatListDiv.innerHTML = `<div style="color:red;">Error loading chats: ${e.message}</div>`;
    }
}

// ---------- OPEN CHAT ----------
async function openChat(chatId, username, iconUrl) {
    if (!isAdmin) return;
    currentChatId = chatId;

    chatTitle.textContent = username || 'Chat';
    if (iconUrl) {
        chatAvatarImg.src = iconUrl;
        chatAvatarImg.style.display = 'block';
        chatAvatarPlaceholder.style.display = 'none';
    } else {
        chatAvatarImg.style.display = 'none';
        chatAvatarPlaceholder.style.display = 'flex';
        chatAvatarPlaceholder.textContent = username ? username.charAt(0).toUpperCase() : '👤';
    }

    headerChatUserIcon.src = iconUrl || 'https://zany-tech.com/img/user.png';
    headerChatUserName.textContent = username || 'User';

    showChatView(true);
    clearReply();

    document.querySelectorAll('.chat-list-item').forEach(item => item.classList.remove('selected'));
    const selectedItem = document.querySelector(`.chat-list-item[data-chat-id="${chatId}"]`);
    if (selectedItem) selectedItem.classList.add('selected');

    // Subscribe to realtime channel
    subscribeToChatChannel(chatId);

    try {
        const data = await apiFetch(`messages&chat_id=${chatId}`);
        allMessages = data;
        isNearBottom = true;
        unreadBottomCount = 0;
        renderMessages();

        // Mark as read immediately on open
        await apiFetch('mark_chat_read', 'POST', { chat_id: chatId });
        const badge = document.querySelector(`.chat-list-item[data-chat-id="${chatId}"] .badge`);
        if (badge) badge.remove();
        if (navigator.clearAppBadge) navigator.clearAppBadge().catch(() => {});
    } catch (e) {
        msgList.innerHTML = `<div style="color:red;">Error loading messages: ${e.message}</div>`;
    }
}

// ---------- SWIPE & DOUBLE TAP/CLICK ----------
let touchStartX = 0, touchCurrentX = 0, touchStartY = 0, touchCurrentY = 0, isSwiping = false, currentSwipeElement = null;

function handleDoubleTap(e) {
    const now = Date.now();
    const currentTarget = e.currentTarget;
    if (lastTapElement === currentTarget && now - lastTapTime < 300) {
        const messageId = currentTarget.dataset.messageId;
        if (messageId && isAdmin) {
            doubleTapDetected = true;
            enterSelectionMode(messageId);
        }
        lastTapTime = 0;
        lastTapElement = null;
    } else {
        lastTapTime = now;
        lastTapElement = currentTarget;
    }
}

function handleDoubleClick(e) {
    const messageId = e.currentTarget.dataset.messageId;
    if (messageId && isAdmin) {
        enterSelectionMode(messageId);
    }
}

function enterSelectionMode(messageId) {
    selectionMode = true;
    selectedMessages.add(messageId);
    updateSelectionUI();
    highlightSelectedMessages();
}

function toggleMessageSelection(messageId) {
    if (selectedMessages.has(messageId)) {
        selectedMessages.delete(messageId);
    } else {
        selectedMessages.add(messageId);
    }
    updateSelectionUI();
    highlightSelectedMessages();
}

function clearSelection() {
    selectedMessages.clear();
    selectionMode = false;
    updateSelectionUI();
    highlightSelectedMessages();
}

function highlightSelectedMessages() {
    document.querySelectorAll('.message-wrapper').forEach(wrapper => {
        const id = wrapper.dataset.messageId;
        if (selectedMessages.has(id)) {
            wrapper.classList.add('selected');
        } else {
            wrapper.classList.remove('selected');
        }
    });
}

function updateSelectionUI() {
    const hasSelection = selectedMessages.size > 0 && selectionMode;

    // Toggle action buttons
    if (actionBtnMain) actionBtnMain.style.display = hasSelection ? 'inline-flex' : 'none';
    if (actionBtnChat) actionBtnChat.style.display = hasSelection ? 'inline-flex' : 'none';
    if (actionMenuMain) actionMenuMain.style.display = 'none';
    if (actionMenuChat) actionMenuChat.style.display = 'none';

    // Update selected messages count
    const count = selectedMessages.size;
    const countText = `${count} Message${count !== 1 ? 's' : ''} Selected`;

    if (selectedCountMain) {
        if (hasSelection) {
            selectedCountMain.textContent = countText;
            selectedCountMain.style.display = 'inline-flex';
        } else {
            selectedCountMain.style.display = 'none';
        }
    }

    if (selectedCountChat) {
        if (hasSelection) {
            selectedCountChat.textContent = countText;
            selectedCountChat.style.display = 'inline-flex';
        } else {
            selectedCountChat.style.display = 'none';
        }
    }

    // Toggle class on chat-specific header for small screens
    if (headerChat) {
        headerChat.classList.toggle('selection-active', hasSelection);
    }

    // Also toggle class on main header for large screens (optional)
    if (headerMain) {
        headerMain.classList.toggle('selection-active', hasSelection);
    }
}

function showActionMenu(menu) {
    const isVisible = menu.style.display === 'block';
    hideAllActionMenus();
    if (!isVisible) menu.style.display = 'block';
}

function hideAllActionMenus() {
    if (actionMenuMain) actionMenuMain.style.display = 'none';
    if (actionMenuChat) actionMenuChat.style.display = 'none';
}

async function deleteSelected() {
    if (selectedMessages.size === 0) return;
    if (!confirm('Delete selected message(s)?')) return;
    const ids = Array.from(selectedMessages);
    await apiFetch('delete_messages', 'POST', { message_ids: ids });
    clearSelection();
    reloadCurrentChat();
}

async function starSelected() {
    if (selectedMessages.size === 0) return;
    for (const id of selectedMessages) {
        const msg = allMessages.find(m => m.message_id == id);
        if (!msg) continue;
        const newStar = msg.star === 'yes' ? 'no' : 'yes';
        await apiFetch('star_message', 'POST', { message_id: id, star: newStar });
    }
    clearSelection();
    reloadCurrentChat();
}

async function pinSelected() {
    if (selectedMessages.size !== 1) {
        alert('Please select only one message to pin/unpin.');
        return;
    }
    const id = Array.from(selectedMessages)[0];
    await apiFetch('pin_message', 'POST', { message_id: id });
    clearSelection();
    reloadCurrentChat();
}

function startForwardMode() {
    if (selectedMessages.size === 0) return;
    forwardMessageIds = Array.from(selectedMessages);
    isForwarding = true;
    forwardTargetChats.clear();
    clearSelection();
    if (window.innerWidth <= 768) showChatView(false);
    loadChats();
    forwardSendBtn.style.display = 'flex';
}

async function executeForward() {
    if (forwardTargetChats.size === 0) {
        alert('Select at least one chat to forward to.');
        return;
    }
    const messageIds = forwardMessageIds;
    const chatIds = Array.from(forwardTargetChats);
    await apiFetch('forward_messages', 'POST', { message_ids: messageIds, chat_ids: chatIds });
    exitForwardMode();
}

function exitForwardMode() {
    isForwarding = false;
    forwardTargetChats.clear();
    forwardMessageIds = [];
    forwardSendBtn.style.display = 'none';
    loadChats();
    selectedMessages.clear();
    updateSelectionUI();
}

async function reloadCurrentChat() {
    if (currentChatId) {
        const data = await apiFetch(`messages&chat_id=${currentChatId}`);
        allMessages = data;
        renderMessages();
    }
}

function setupSwipeHandlers() {
    const wrappers = document.querySelectorAll('.message-wrapper');
    wrappers.forEach(wrapper => {
        wrapper.removeEventListener('touchstart', handleTouchStart);
        wrapper.removeEventListener('touchmove', handleTouchMove);
        wrapper.removeEventListener('touchend', handleTouchEnd);
        wrapper.removeEventListener('mousedown', handleMouseDown);
        wrapper.removeEventListener('mouseup', handleMouseUp);
        wrapper.removeEventListener('mouseleave', handleMouseUp);
        wrapper.removeEventListener('touchend', handleDoubleTap);
        wrapper.removeEventListener('dblclick', handleDoubleClick);

        wrapper.addEventListener('touchstart', handleTouchStart, { passive: true });
        wrapper.addEventListener('touchmove', handleTouchMove, { passive: false });
        wrapper.addEventListener('touchend', handleTouchEnd, { passive: true });
        wrapper.addEventListener('mousedown', handleMouseDown);
        wrapper.addEventListener('mouseup', handleMouseUp);
        wrapper.addEventListener('mouseleave', handleMouseUp);

        wrapper.addEventListener('touchend', handleDoubleTap, { passive: true });
        wrapper.addEventListener('dblclick', handleDoubleClick);

        wrapper.addEventListener('click', (e) => {
            if (doubleTapDetected) {
                doubleTapDetected = false;
                return;
            }
            if (selectionMode && isAdmin) {
                const id = wrapper.dataset.messageId;
                if (id) toggleMessageSelection(id);
            }
        });
    });
}

function handleTouchStart(e) {
    const touch = e.touches[0];
    touchStartX = touch.clientX;
    touchStartY = touch.clientY;
    currentSwipeElement = e.currentTarget;
    isSwiping = true;
    touchCurrentX = touchStartX;
    touchCurrentY = touchStartY;
}

function handleTouchMove(e) {
    if (!isSwiping || !currentSwipeElement) return;
    const touch = e.touches[0];
    const diffX = touch.clientX - touchStartX;
    const diffY = touch.clientY - touchStartY;

    // Only consider it a swipe if horizontal movement is dominant and meaningful
    if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 15) {
        if (e.cancelable) {
            e.preventDefault();
        }
        const swipeHint = currentSwipeElement.querySelector('.swipe-hint');
        if (diffX > 40) {
            currentSwipeElement.classList.add('swiped');
            if (swipeHint) swipeHint.classList.add('show');
        } else {
            currentSwipeElement.classList.remove('swiped');
            if (swipeHint) swipeHint.classList.remove('show');
        }
    }

    touchCurrentX = touch.clientX;
    touchCurrentY = touch.clientY;
}

function handleTouchEnd(e) {
    if (!currentSwipeElement) return;
    const diffX = touchCurrentX - touchStartX;
    const diffY = touchCurrentY - touchStartY;

    if (Math.abs(diffX) > Math.abs(diffY) && diffX > 40) {
        const messageId = currentSwipeElement.dataset.messageId;
        if (messageId) {
            const msg = allMessages.find(m => m.message_id == messageId);
            if (msg) setReply(messageId, msg);
        }
    }

    currentSwipeElement.classList.remove('swiped');
    const swipeHint = currentSwipeElement.querySelector('.swipe-hint');
    if (swipeHint) swipeHint.classList.remove('show');
    isSwiping = false;
    currentSwipeElement = null;
    touchStartX = 0;
    touchStartY = 0;
    touchCurrentX = 0;
    touchCurrentY = 0;
}

function handleMouseDown(e) {
    if (e.button !== 0) return;
    touchStartX = e.clientX;
    currentSwipeElement = e.currentTarget;
    isSwiping = true;
    touchCurrentX = touchStartX;
}

function handleMouseUp(e) {
    if (!currentSwipeElement || !isSwiping) return;
    const diff = e.clientX - touchStartX;
    if (diff > 40) {
        const messageId = currentSwipeElement.dataset.messageId;
        if (messageId) {
            const msg = allMessages.find(m => m.message_id == messageId);
            if (msg) setReply(messageId, msg);
        }
    }
    currentSwipeElement.classList.remove('swiped');
    const swipeHint = currentSwipeElement.querySelector('.swipe-hint');
    if (swipeHint) swipeHint.classList.remove('show');
    isSwiping = false;
    currentSwipeElement = null;
    touchStartX = 0;
    touchCurrentX = 0;
}

// ---------- LINKIFY TEXT ----------
function linkify(text) {
    if (!text) return '';
    const urlRegex = /(\b(https?|ftp):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ig;
    return text.replace(urlRegex, function(url) {
        return '<a href="' + url + '" target="_blank" rel="noopener noreferrer" class="chat-link">' + url + '</a>';
    });
}

// ---------- RENDER MEDIA ----------
function renderMedia(url) {
    if (!url) return '';
    const ext = url.split('.').pop().toLowerCase();
    if (url.includes('/voice/') || ['mp3', 'wav', 'ogg', 'webm', 'm4a'].includes(ext)) {
        const uniqueId = 'aud_' + Date.now() + Math.random().toString(36).substr(2, 9);
        return `
            <div class="chat-audio" id="container_${uniqueId}">
                <audio id="${uniqueId}" src="${url}" preload="metadata"></audio>
                <button class="voice-play-btn" onclick="toggleVoicePlay(this, '${uniqueId}')">
                    <i class="fas fa-play"></i>
                </button>
                <div class="voice-progress">
                    <div class="voice-bar-wrap">
                        <div class="voice-bar-fill" id="bar_${uniqueId}" style="width:0%"></div>
                    </div>
                    <span class="voice-time" id="time_${uniqueId}">0:00 / 0:00</span>
                </div>
            </div>
        `;
    } else if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
        return `<div class="chat-image"><img src="${url}" alt="Attachment" loading="lazy" onclick="window.open('${url}')"></div>`;
    } else if (['mp4', 'webm', 'ogg'].includes(ext)) {
        return `<div class="chat-video"><video controls><source src="${url}" type="video/${ext}"></video></div>`;
    } else {
        return `<div><a href="${url}" target="_blank"><i class="fas fa-paperclip"></i> Attachment</a></div>`;
    }
}

function updateAudioDurations() {
    document.querySelectorAll('.chat-audio audio').forEach(audio => {
        const timeSpan = document.getElementById('time_' + audio.id);
        if (!timeSpan) return;
        if (audio.readyState >= 1) {
            timeSpan.textContent = '0:00 / ' + formatTime(audio.duration);
        } else {
            audio.addEventListener('loadedmetadata', function() {
                timeSpan.textContent = '0:00 / ' + formatTime(audio.duration);
            }, { once: true });
        }
    });
}

window.toggleVoicePlay = function(btn, audioId) {
    const audio = document.getElementById(audioId);
    const barFill = document.getElementById('bar_' + audioId);
    const timeSpan = document.getElementById('time_' + audioId);

    if (!audio) return;

    audioPlayers.forEach((otherAudio, otherId) => {
        if (otherId !== audioId && !otherAudio.paused) {
            otherAudio.pause();
            const otherBtn = document.querySelector(`.chat-audio audio[id="${otherId}"]`)?.previousElementSibling;
            if (otherBtn) otherBtn.innerHTML = '<i class="fas fa-play"></i>';
            const otherBar = document.getElementById('bar_' + otherId);
            if (otherBar) otherBar.style.width = '0%';
        }
    });

    audioPlayers.set(audioId, audio);

    if (audio.paused) {
        audio.play();
        btn.innerHTML = '<i class="fas fa-pause"></i>';
        function updateProgress() {
            if (!audio.duration) return;
            const percent = (audio.currentTime / audio.duration) * 100;
            barFill.style.width = percent + '%';
            timeSpan.textContent = formatTime(audio.currentTime) + ' / ' + formatTime(audio.duration);
        }
        audio.addEventListener('timeupdate', updateProgress);
        audio.addEventListener('ended', () => {
            btn.innerHTML = '<i class="fas fa-play"></i>';
            barFill.style.width = '0%';
            timeSpan.textContent = '0:00 / ' + formatTime(audio.duration);
            audio.currentTime = 0;
            audio.removeEventListener('timeupdate', updateProgress);
        }, { once: true });
        if (audio.duration) {
            timeSpan.textContent = '0:00 / ' + formatTime(audio.duration);
        }
    } else {
        audio.pause();
        btn.innerHTML = '<i class="fas fa-play"></i>';
    }
};

function formatTime(seconds) {
    if (isNaN(seconds)) return '0:00';
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs < 10 ? '0' : ''}${secs}`;
}

// ---------- RENDER MESSAGES ----------
function renderMessages() {
    let filtered = allMessages;
    if (!isAdmin) filtered = allMessages.filter(m => m.valid === true);
    if (filtered.length === 0) {
        msgList.innerHTML = `<div style="text-align:center; color:var(--text-light); padding:2rem; font-style:italic;">No messages to show</div>`;
        updateBackToBottomButton();
        return;
    }

    const previousScrollTop = msgList.scrollTop;

    let html = '';
    for (const m of filtered) {
        const isOwn = m.sender_id == currentUserId;
        const bubbleClass = isOwn ? 'sent' : 'received';
        const isExpired = !m.valid;
        const expiredLabel = isExpired ? '<div class="expired-label">⏰ Expired</div>' : '';
        let senderDisplay;
        if (m.role === 'admin' || m.role === 'ceo') {
            senderDisplay = 'Admin';
        } else {
            senderDisplay = m.username || m.email || 'User';
        }
        let replyHtml = '';
        if (m.tag_id) {
            const repliedMsg = allMessages.find(msg => msg.message_id == m.tag_id);
            if (repliedMsg) replyHtml = renderReplyPreview(repliedMsg);
        }
        let senderIconHtml = '';
        if (m.icon_url) {
            senderIconHtml = `<img src="${m.icon_url}" alt="" class="msg-sender-avatar avatar-border">`;
        } else {
            const isAdminSender = (m.role === 'admin' || m.role === 'ceo');
            const defaultAvatar = isAdminSender ? 'https://zany-tech.com/img/admin.jpg' : 'https://zany-tech.com/img/user.png';
            senderIconHtml = `<img src="${defaultAvatar}" alt="" class="msg-sender-avatar avatar-border">`;
        }

        // Status ticks (only for own messages)
        let statusHtml = '';
        if (isOwn) {
            if (m.message_status === 'read') {
                statusHtml = `<span class="status-badge read"><i class="fas fa-check"></i><i class="fas fa-check"></i></span>`;
            } else if (m.message_status === 'delivered') {
                statusHtml = `<span class="status-badge delivered"><i class="fas fa-check"></i><i class="fas fa-check"></i></span>`;
            } else {
                statusHtml = `<span class="status-badge sent"><i class="fas fa-check"></i></span>`;
            }
        }

        const bodyHtml = (m.deleted === 'yes') ? '<div class="deleted-text">This message was deleted!</div>' : linkify(escapeHtml(m.message_body));
        const starIcon = (m.star === 'yes') ? '<i class="fas fa-star star-icon"></i>' : '';

        html += `
            <div class="message-wrapper ${bubbleClass}" data-message-id="${m.message_id}">
                <div class="message ${bubbleClass}">
                    ${replyHtml}
                    <div class="meta">
                        <span class="sender">
                            ${senderIconHtml}
                            ${senderDisplay}
                        </span>
                    </div>
                    ${(m.deleted === 'yes') ? '' : renderMedia(m.media_url)}
                    <div class="body">${bodyHtml}</div>
                    ${expiredLabel}
                    <div class="footer">
                        <span class="time">${new Date(m.created_at).toLocaleString()} ${starIcon}</span>
                        ${statusHtml}
                    </div>
                </div>
                <div class="swipe-hint"><i class="fas fa-reply"></i> Reply</div>
            </div>
        `;
    }
    msgList.innerHTML = html;
    setupSwipeHandlers();
    updateAudioDurations();
    highlightSelectedMessages();
    updateBackToBottomButton();

    if (isNearBottom) {
        msgList.scrollTop = msgList.scrollHeight;
    } else {
        msgList.scrollTop = previousScrollTop;
    }
}

// ---------- SEND MESSAGE ----------
async function sendMessageNow(body, media) {
    if (!currentChatId) throw new Error('No chat selected.');
    const payload = {
        chat_id: currentChatId,
        message_body: body,
        media_url: media || null,
        expires_at: null,
        tag_id: replyToMessageId || null,
    };
    await apiFetch('send', 'POST', payload);
    if (isAdmin) {
        const chatItem = document.querySelector(`.chat-list-item[data-chat-id="${currentChatId}"]`);
        if (chatItem) {
            const name = chatItem.querySelector('.name').textContent;
            const icon = chatItem.querySelector('img').src;
            openChat(currentChatId, name, icon);
        }
        loadChats();
    } else {
        const data = await apiFetch(`messages&chat_id=${currentChatId}`);
        allMessages = data;
        renderMessages();
    }
}

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const body = msgBody.value.trim();
    const attachedMedia = mediaUrl.value.trim();
    if (!body && !attachedMedia) {
        alert('Please type a message or attach a file.');
        return;
    }
    try {
        await sendMessageNow(body || '', attachedMedia);
        msgBody.value = '';
        mediaUrl.value = '';
        clearReply();
        updateMessageRequired();
        attachBtn.style.color = '';
        voiceBtn.style.color = '';
    } catch (err) {
        alert('Failed to send: ' + err.message);
    }
});

// Auto-resize textarea
msgBody.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 100) + 'px';
});
msgBody.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && e.shiftKey) {
        e.preventDefault();
        form.dispatchEvent(new Event('submit'));
    }
});

// ---------- TYPING INDICATOR ----------
msgBody.addEventListener('input', function() {
    if (!currentChatId) return;
    fetch('/api.php?action=typing', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ chat_id: currentChatId })
    }).catch(() => {});
});

function showTypingIndicator(name) {
    let typingDiv = document.getElementById('typingIndicator');
    if (!typingDiv) return;
    typingDiv.innerHTML = `<em>${name} is typing...</em>`;
    typingDiv.style.display = 'block';
    clearTimeout(typingTimeout);
    typingTimeout = setTimeout(hideTypingIndicator, 1500);
}

function hideTypingIndicator() {
    const typingDiv = document.getElementById('typingIndicator');
    if (typingDiv) {
        typingDiv.style.display = 'none';
        typingDiv.innerHTML = '';
    }
}

// ---------- UTILITIES ----------
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ---------- BACK TO BOTTOM BUTTON ----------
function createBackToBottomButton() {
    if (backToBottomBtn) return;
    backToBottomBtn = document.createElement('button');
    backToBottomBtn.className = 'back-to-bottom-btn';
    backToBottomBtn.innerHTML = '<i class="fas fa-chevron-down"></i>';
    backToBottomBadge = document.createElement('span');
    backToBottomBadge.className = 'badge';
    backToBottomBadge.style.display = 'none';
    backToBottomBtn.appendChild(backToBottomBadge);
    backToBottomBtn.addEventListener('click', () => {
        msgList.scrollTop = msgList.scrollHeight;
        isNearBottom = true;
        unreadBottomCount = 0;
        updateBackToBottomButton();
    });
    chatView.appendChild(backToBottomBtn);
    updateBackToBottomButton();
}

function updateBackToBottomButton() {
    if (!backToBottomBtn) return;
    if (!isNearBottom) {
        backToBottomBtn.style.display = 'flex';
    } else {
        backToBottomBtn.style.display = 'none';
        unreadBottomCount = 0;
    }
    if (backToBottomBadge) {
        if (unreadBottomCount > 0) {
            backToBottomBadge.textContent = unreadBottomCount > 99 ? '99+' : unreadBottomCount;
            backToBottomBadge.style.display = 'flex';
        } else {
            backToBottomBadge.style.display = 'none';
        }
    }
}

// ---------- ABLY REALTIME ----------
function getAblyToken(tokenParams, callback) {
    fetch('/api.php?action=ably_token')
        .then(res => res.json())
        .then(data => {
            if (data.token) {
                callback(null, data.token);
            } else {
                callback(new Error('No token received'));
            }
        })
        .catch(err => callback(err));
}
async function initAbly() {
    try {
        ably = new Ably.Realtime({
            authCallback: getAblyToken
        });
        ably.connection.on('connected', () => {
            console.log('Ably connected');
            subscribeToSiteChannelForAdmin();
        });
    } catch (e) {
        console.log('Ably init failed, falling back to polling', e);
    }
}

function subscribeToSiteChannelForAdmin() {
    if (!ably || !isAdmin) return;
    const siteChannel = ably.channels.get('site_' + siteId);

    // New message site event
    siteChannel.subscribe('new-message-site', (msg) => {
        const data = msg.data;
        if (currentChatId == data.chat_id) return;
        apiFetch('mark_chat_delivered', 'POST', { chat_id: data.chat_id })
            .then(() => loadChats())
            .catch(() => {});
    });

    // Typing site event
    siteChannel.subscribe('typing-site', (msg) => {
        const data = msg.data;
        if (currentChatId == data.chat_id) return; // handled by chat channel
        const chatItem = document.querySelector(`.chat-list-item[data-chat-id="${data.chat_id}"]`);
        if (chatItem) {
            const lastMsgSpan = chatItem.querySelector('.last-msg');
            if (lastMsgSpan) {
                lastMsgSpan.textContent = `${data.name} is typing...`;
                clearTimeout(typingTimeout);
                typingTimeout = setTimeout(() => loadChats(), 1500);
            }
        }
    });
}

function subscribeToChatChannel(chatId) {
    if (!ably) return;
    if (currentChannel) currentChannel.unsubscribe();
    currentChannel = ably.channels.get('chat_' + chatId);

    currentChannel.subscribe('new-message', (msg) => {
        const data = msg.data;
        if (data.sender_id == currentUserId) return;
        const exists = allMessages.some(m => m.message_id == data.message_id);
        if (exists) return;
        allMessages.push(data);

        if (currentChatId == data.chat_id) {
            if (!document.hidden) {
                apiFetch('mark_chat_delivered', 'POST', { chat_id: data.chat_id })
                    .then(() => apiFetch('mark_chat_read', 'POST', { chat_id: data.chat_id }))
                    .then(() => {
                        allMessages.forEach(m => {
                            if (m.sender_id != currentUserId && m.chat_id == data.chat_id) {
                                m.message_status = 'read';
                            }
                        });
                        renderMessages();
                    })
                    .catch(() => {});
            } else {
                apiFetch('mark_chat_delivered', 'POST', { chat_id: data.chat_id })
                    .then(() => {
                        allMessages.forEach(m => {
                            if (m.sender_id != currentUserId && m.chat_id == data.chat_id && m.message_status === 'unread') {
                                m.message_status = 'delivered';
                            }
                        });
                        if (!isNearBottom) unreadBottomCount++;
                        renderMessages();
                    })
                    .catch(() => {});
            }
        } else {
            apiFetch('mark_chat_delivered', 'POST', { chat_id: data.chat_id })
                .catch(() => {});
            if (isAdmin) loadChats();
        }
    });

    currentChannel.subscribe('messages-delivered', (msg) => {
        const data = msg.data;
        if (data.reader_id == currentUserId) return;
        allMessages.forEach(m => {
            if (m.sender_id == currentUserId && m.chat_id == data.chat_id && m.message_status === 'unread') {
                m.message_status = 'delivered';
            }
        });
        if (currentChatId == data.chat_id) renderMessages();
    });

    currentChannel.subscribe('messages-read', (msg) => {
        const data = msg.data;
        if (data.reader_id == currentUserId) return;
        allMessages.forEach(m => {
            if (m.sender_id == currentUserId && m.chat_id == data.chat_id) {
                m.message_status = 'read';
            }
        });
        if (currentChatId == data.chat_id) renderMessages();
    });

    currentChannel.subscribe('typing', (msg) => {
        const data = msg.data;
        if (data.user_id == currentUserId) return;
        if (currentChatId == data.chat_id) {
            showTypingIndicator(data.name);
            clearTimeout(typingTimeout);
            typingTimeout = setTimeout(hideTypingIndicator, 1500);
        }
    });
}

// ---------- NOTIFICATION PROMPT ----------
function showNotificationPrompt() {
    if (isAdmin) return;
    if (Notification.permission !== 'default') return;
    if (!currentChatId || allMessages.length === 0) return;
    if (!notifPrompt) return;
    notifPrompt.style.display = 'flex';
}

if (enableNotifBtn) {
    enableNotifBtn.addEventListener('click', async () => {
        notifPrompt.style.display = 'none';
        const permission = await Notification.requestPermission();
        if (permission === 'granted') {
            subscribeToPush();
        }
    });
}
if (dismissNotifBtn) {
    dismissNotifBtn.addEventListener('click', () => {
        notifPrompt.style.display = 'none';
    });
}

// ---------- AUTO-REFRESH (FALLBACK) ----------
setInterval(() => {
    if (isAdmin) {
        loadChats();
        if (currentChatId) {
            (async function() {
                try {
                    const data = await apiFetch(`messages&chat_id=${currentChatId}`);
                    allMessages = data;
                    renderMessages();
                } catch (e) {}
            })();
        }
    } else {
        if (currentChatId) {
            (async function() {
                try {
                    const data = await apiFetch(`messages&chat_id=${currentChatId}`);
                    allMessages = data;
                    renderMessages();
                } catch (e) {}
            })();
        }
    }
}, 30000);

// ---------- INITIAL LOAD ----------
const urlParams = new URLSearchParams(window.location.search);
const openChatId = urlParams.get('open_chat');

if (isAdmin) {
    loadChats();
    chatTitle.textContent = 'Select a chat';
    chatAvatarImg.style.display = 'none';
    chatAvatarPlaceholder.textContent = '👤';
    msgList.innerHTML = `<div style="text-align:center; color:var(--text-light); padding:2rem; font-style:italic;">Select a chat from the list</div>`;
    if (headerMain) headerMain.classList.remove('hidden');
    if (headerChat) headerChat.classList.add('hidden');
    if (window.innerWidth <= 768) {
        chatView.classList.remove('open');
        chatListView.classList.remove('hidden');
    }
    if (openChatId) {
        setTimeout(() => {
            const targetItem = document.querySelector(`.chat-list-item[data-chat-id="${openChatId}"]`);
            if (targetItem) targetItem.click();
        }, 800);
    }
} else {
    (async function() {
        try {
            const chatRes = await apiFetch('my_chat');
            currentChatId = chatRes.chat_id;
            const usersRes = await fetch('/api.php?action=users');
            const users = await usersRes.json();
            const admin = users.find(u => u.role === 'admin');
            if (admin) {
                chatTitle.textContent = 'Admin';
                chatAvatarImg.src = 'https://zany-tech.com/img/admin.jpg';
                chatAvatarImg.style.display = 'block';
                chatAvatarPlaceholder.style.display = 'none';
            } else {
                chatTitle.textContent = 'Chat';
            }
            const data = await apiFetch(`messages&chat_id=${currentChatId}`);
            allMessages = data;
            isNearBottom = true;
            unreadBottomCount = 0;
            renderMessages();

            await apiFetch('mark_chat_read', 'POST', { chat_id: currentChatId });
            if (navigator.clearAppBadge) navigator.clearAppBadge().catch(() => {});
            subscribeToChatChannel(currentChatId);
            showNotificationPrompt();
        } catch (e) {
            msgList.innerHTML = `<div style="color:red;">Error: ${e.message}</div>`;
        }
    })();
}

// ---------- RESIZE HANDLER ----------
window.addEventListener('resize', () => {
    if (!isAdmin) return;
    const isMobile = window.innerWidth <= 768;
    if (!isMobile) {
        headerMain.classList.remove('hidden');
        headerChat.classList.add('hidden');
        chatView.classList.remove('open');
        chatListView.classList.remove('hidden');
    } else {
        if (currentChatId) {
            headerMain.classList.add('hidden');
            headerChat.classList.remove('hidden');
        } else {
            headerMain.classList.remove('hidden');
            headerChat.classList.add('hidden');
        }
    }
});

// ---------- ONLINE STATUS ----------
let isUserActive = true;
function updateOnlineStatus(status = 'online') {
    fetch('/api.php?action=update_online_status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ status: status })
    }).catch(err => console.log('Error updating online status:', err));
}
updateOnlineStatus('online');
document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
        isUserActive = true;
        updateOnlineStatus('online');
        if (currentChatId) {
            apiFetch('mark_chat_read', 'POST', { chat_id: currentChatId })
                .then(() => {
                    allMessages.forEach(m => {
                        if (m.sender_id != currentUserId && m.chat_id == currentChatId) {
                            m.message_status = 'read';
                        }
                    });
                    renderMessages();
                    if (navigator.clearAppBadge) navigator.clearAppBadge().catch(() => {});
                })
                .catch(() => {});
        }
    } else {
        isUserActive = false;
        updateOnlineStatus('away');
    }
});
setInterval(function() {
    if (isUserActive) updateOnlineStatus('online');
}, 60000);
window.addEventListener('beforeunload', function() {
    updateOnlineStatus('offline');
});
['click', 'touchstart', 'keydown', 'scroll', 'mousemove'].forEach(event => {
    document.addEventListener(event, function() {
        if (document.hidden) return;
        if (!isUserActive) {
            isUserActive = true;
            updateOnlineStatus('online');
        }
    }, { passive: true });
});

// ---------- NOTIFICATION SYSTEM ----------
let lastUnreadCount = 0;
let isPageVisible = true;
function createNotificationSound() {
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        oscillator.frequency.value = 800;
        oscillator.type = 'sine';
        gainNode.gain.value = 0.3;
        oscillator.start();
        setTimeout(() => { oscillator.stop(); }, 150);
        setTimeout(() => {
            const osc2 = audioContext.createOscillator();
            const gain2 = audioContext.createGain();
            osc2.connect(gain2);
            gain2.connect(audioContext.destination);
            osc2.frequency.value = 1000;
            osc2.type = 'sine';
            gain2.gain.value = 0.2;
            osc2.start();
            setTimeout(() => osc2.stop(), 150);
        }, 200);
    } catch (e) {}
}
function updateFavicon(count) {
    const favicon = document.querySelector('link[rel="icon"]');
    if (!favicon) {
        const link = document.createElement('link');
        link.rel = 'icon';
        document.head.appendChild(link);
        return updateFavicon(count);
    }
    if (count > 0) {
        const canvas = document.createElement('canvas');
        canvas.width = 32;
        canvas.height = 32;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#e74c3c';
        ctx.beginPath();
        ctx.arc(16, 16, 16, 0, 2 * Math.PI);
        ctx.fill();
        ctx.fillStyle = 'white';
        ctx.font = 'bold 18px Arial';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(count > 9 ? '9+' : count.toString(), 16, 18);
        favicon.href = canvas.toDataURL('image/png');
    } else {
        favicon.href = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">💬</text></svg>';
    }
    if (navigator.setAppBadge) {
        if (count > 0) {
            navigator.setAppBadge(count).catch(() => {});
        } else {
            navigator.clearAppBadge().catch(() => {});
        }
    }
}
function updateAppBadge(count) {
    if (navigator.setAppBadge) {
        if (count > 0) navigator.setAppBadge(count).catch(() => {});
        else navigator.clearAppBadge().catch(() => {});
    }
}
function updateTitle(count) {
    const baseTitle = 'Chat';
    if (count > 0) {
        document.title = `(${count}) ${baseTitle}`;
    } else {
        document.title = baseTitle;
    }
}
// function showDesktopNotification(message, sender, chatId) {
//     if (!('Notification' in window)) return;
//     if (Notification.permission === 'granted') {
//         const notification = new Notification(`💬 New message from ${sender}`, {
//             body: message.length > 100 ? message.substring(0, 100) + '...' : message,
//             icon: 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/svgs/solid/comment.svg',
//             tag: 'chat_message_' + Date.now(),
//             requireInteraction: true,
//             data: { chat_id: chatId }
//         });
//         notification.onclick = function() {
//             window.focus();
//             if (chatId && isAdmin) {
//                 const chatItem = document.querySelector(`.chat-list-item[data-chat-id="${chatId}"]`);
//                 if (chatItem) chatItem.click();
//             }
//             notification.close();
//         };
//         setTimeout(() => notification.close(), 10000);
//     } else if (Notification.permission === 'default') {
//         // Custom prompt handles it
//     }
// }
async function checkUnreadMessages() {
    try {
        const response = await fetch('/api.php?action=unread_count');
        if (!response.ok) throw new Error('Failed to fetch unread count');
        const data = await response.json();
        const count = data.unread || 0;
        updateFavicon(count);
        updateTitle(count);
        if (count > lastUnreadCount) {
            if (!isPageVisible) {
                createNotificationSound();
            }
            const msgResponse = await fetch('/api.php?action=unread_messages&limit=1');
            if (msgResponse.ok) {
                const messages = await msgResponse.json();
                if (messages.length > 0) {
                    const msg = messages[0];
                    const senderDisplay = msg.username || msg.email || 'User';
                    // showDesktopNotification(msg.message_body, senderDisplay, msg.chat_id);
                }
            }
            if (currentChatId) {
                if (isAdmin) {
                    const chatItem = document.querySelector(`.chat-list-item[data-chat-id="${currentChatId}"]`);
                    if (chatItem) {
                        const name = chatItem.querySelector('.name').textContent;
                        const icon = chatItem.querySelector('img').src;
                        openChat(currentChatId, name, icon);
                    }
                } else {
                    (async function() {
                        try {
                            const data = await apiFetch(`messages&chat_id=${currentChatId}`);
                            allMessages = data;
                            renderMessages();
                        } catch (e) {}
                    })();
                }
            }
        }
        lastUnreadCount = count;
    } catch (e) {}
}
// if ('Notification' in window && Notification.permission === 'default') {
//     // Custom prompt handles it
// }
setInterval(checkUnreadMessages, 5000);
setTimeout(checkUnreadMessages, 1000);
document.addEventListener('visibilitychange', function() {
    isPageVisible = !document.hidden;
    if (isPageVisible) {
        checkUnreadMessages();
    }
});

// ---------- PUSH SUBSCRIPTION ----------
function subscribeToPush() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
    navigator.serviceWorker.ready
        .then(reg => reg.pushManager.getSubscription())
        .then(sub => {
            if (sub) return sub;
            const applicationServerKey = urlBase64ToUint8Array(window.chatConfig.vapidPublicKey);
            return navigator.serviceWorker.ready
                .then(reg => reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: applicationServerKey
                }));
        })
        .then(sub => {
            return fetch('/api.php?action=save_subscription', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    endpoint: sub.endpoint,
                    authToken: btoa(String.fromCharCode.apply(null, new Uint8Array(sub.getKey('auth')))),
                    publicKey: btoa(String.fromCharCode.apply(null, new Uint8Array(sub.getKey('p256dh'))))
                })
            });
        })
        .then(res => res.json())
        .then(data => { if (data.success) console.log('Push subscribed'); })
        .catch(err => console.log('Push error:', err));
}
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/service-worker.js')
        .then(reg => {
            console.log('Service Worker registered');
            if (window.chatConfig.isLoggedIn) {
                setTimeout(subscribeToPush, 3000);
            }
        })
        .catch(err => console.log('SW registration failed:', err));
}

setTimeout(() => updateOnlineStatus('online'), 1000);

// ---------- ADMIN ACTION MENUS ----------
function bindActionMenus() {
    if (actionBtnMain) actionBtnMain.addEventListener('click', () => showActionMenu(actionMenuMain));
    if (actionBtnChat) actionBtnChat.addEventListener('click', () => showActionMenu(actionMenuChat));
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.three-dot-btn') && !e.target.closest('.dropdown-menu')) {
            hideAllActionMenus();
        }
    });

    const bindAction = (id, handler) => {
        const btn = document.getElementById(id);
        if (btn) btn.addEventListener('click', handler);
    };
    bindAction('forwardAction', startForwardMode);
    bindAction('forwardActionChat', startForwardMode);
    bindAction('deleteAction', deleteSelected);
    bindAction('deleteActionChat', deleteSelected);
    bindAction('starAction', starSelected);
    bindAction('starActionChat', starSelected);
    bindAction('pinAction', pinSelected);
    bindAction('pinActionChat', pinSelected);

    if (forwardSendBtn) forwardSendBtn.addEventListener('click', executeForward);

    // Admin mobile menu
    const adminMenuBtn = document.getElementById('adminMenuBtn');
    const adminMenuDropdown = document.getElementById('adminMenuDropdown');
    if (adminMenuBtn && adminMenuDropdown) {
        adminMenuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isVisible = adminMenuDropdown.style.display === 'block';
            adminMenuDropdown.style.display = isVisible ? 'none' : 'block';
        });
        document.addEventListener('click', (e) => {
            if (!e.target.closest('#adminMenuBtn') && !e.target.closest('#adminMenuDropdown')) {
                adminMenuDropdown.style.display = 'none';
            }
        });
    }

    const themeToggleAdminMenu = document.getElementById('themeToggleAdminMenu');
    if (themeToggleAdminMenu) {
        themeToggleAdminMenu.addEventListener('click', () => {
            const isDark = document.body.classList.contains('dark-theme');
            setTheme(isDark ? 'light' : 'dark');
            adminMenuDropdown.style.display = 'none';
        });
    }

    const logoutBtnAdminMenu = document.getElementById('logoutBtnAdminMenu');
    if (logoutBtnAdminMenu) {
        logoutBtnAdminMenu.addEventListener('click', () => {
            window.location.href = '?logout';
        });
    }
}