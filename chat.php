<?php
// chat.php - Complete Chat Application
require_once 'config.php';

// ---------- SESSION RESTORE ----------
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Try to restore from remember token
    if (isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            header('Location: index.php');
            exit;
        }
        $stmt = $conn->prepare("SELECT user_id, username, role, icon_url, remember_token FROM users WHERE remember_token IS NOT NULL AND token_expires > NOW()");
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                if (password_verify($token, $row['remember_token'])) {
                    $_SESSION['user_id'] = $row['user_id'];
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['role'] = $row['role'] ?? 'user';
                    $_SESSION['icon_url'] = $row['icon_url'] ?? (
                        ($row['role'] === 'admin' || $row['role'] === 'ceo')
                            ? 'https://zany-tech.com/img/admin.jpg'
                            : 'https://zany-tech.com/img/user.png'
                    );
                    $_SESSION['logged_in'] = true;
                    break;
                }
            }
        }
        $conn->close();
    }
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    setcookie('remember_token', '', time() - 3600, '/');
    header('Location: index.php');
    exit;
}

// Re-check admin access for current site
if (isset($_SESSION['user_id'])) {
    $connAccess = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$connAccess->connect_error) {
        $stmtAccess = $connAccess->prepare("SELECT access FROM users WHERE user_id = ?");
        $stmtAccess->bind_param("i", $_SESSION['user_id']);
        $stmtAccess->execute();
        $accessRow = $stmtAccess->get_result()->fetch_assoc();
        $stmtAccess->close();
        $connAccess->close();

        $accessList = array_map('intval', array_filter(explode(',', $accessRow['access'] ?? '')));
        if (($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'ceo') && !in_array(SITE_ID, $accessList)) {
            $_SESSION['role'] = 'user';
        }
    }
}

$currentUser = $_SESSION['username'] ?? '';
$currentEmail = $_SESSION['email'] ?? '';
$displayName = !empty($currentUser) ? $currentUser : $currentEmail;
$currentRole = $_SESSION['role'] ?? '';
$iconUrl = $_SESSION['icon_url'] ?? '';
$isAdmin = ($currentRole === 'admin' || $currentRole === 'ceo');
$theme = $_SESSION['theme'] ?? 'dark';
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

// Ensure correct default icon if session icon missing
if (empty($iconUrl) || $iconUrl === '/img/user.png') {
    $iconUrl = ($isAdmin) ? 'https://zany-tech.com/img/admin.jpg' : 'https://zany-tech.com/img/user.png';
}

// --- Count missing profile fields for non-admin user (for badge) ---
$profileMissingCount = 0;
if (!$isAdmin) {
    $connCheck = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$connCheck->connect_error) {
        $stmtCheck = $connCheck->prepare("SELECT username, number, country, password FROM users WHERE user_id = ?");
        $stmtCheck->bind_param("i", $_SESSION['user_id']);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();
        $userCheck = $resultCheck->fetch_assoc();
        if ($userCheck) {
            foreach (['username', 'number', 'country', 'password'] as $field) {
                if (is_null($userCheck[$field])) $profileMissingCount++;
            }
        }
        $stmtCheck->close();
        $connCheck->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Chat</title>
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" href="/img/favicon.ico">
    <link rel="apple-touch-icon" href="/img/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/chat.css">
    <style>
        .whatsapp-float { display: none !important; }
        /* Badge on 3-dot button */
        .three-dot-btn { position: relative; }
        .profile-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        .profile-badge-dropdown {
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            padding: 0.1rem 0.4rem;
            font-size: 0.7rem;
            margin-left: auto;
            font-weight: bold;
        }
    </style>
</head>
<body class="<?= $theme === 'dark' ? 'dark-theme' : '' ?>">

<?php if ($isAdmin): ?>
    <!-- ADMIN HEADERS (dual) -->
    <!-- Main header (chat list) -->
    <header id="appHeaderMain" class="main-header">
        <div class="header-left">
            <a href="index.php" class="back-home"><i class="fas fa-arrow-left"></i> <span>Home</span></a>
            <h1>Chats</h1>
        </div>
        <div class="header-right">
            <!-- Mobile hamburger menu button -->
            <button id="adminMenuBtn" class="hamburger-btn" title="Menu">
                <i class="fas fa-bars"></i>
            </button>

            <!-- Mobile hamburger dropdown -->
            <div class="admin-menu-dropdown" id="adminMenuDropdown" style="display:none;">
                <button class="dropdown-item" id="themeToggleAdminMenu"><i class="fas fa-palette"></i> Change Theme</button>
                <a href="/profile.php" class="dropdown-item"><i class="fas fa-user-edit"></i> Update Profile</a>
                <a href="/chat.php?view=starred" class="dropdown-item"><i class="fas fa-star"></i> Starred Messages</a>
                <a href="/chat.php?view=files" class="dropdown-item"><i class="fas fa-folder"></i> My Files</a>
                <button class="dropdown-item" id="logoutBtnAdminMenu"><i class="fas fa-sign-out-alt"></i> Log Out</button>
            </div>

            <!-- Desktop full header items (hidden on mobile) -->
            <div class="header-right-full">
                <button class="theme-toggle" title="Toggle theme">
                    <i class="fas <?= $theme === 'dark' ? 'fa-sun' : 'fa-moon' ?>" id="themeIcon"></i>
                </button>
                <div class="user-info">
                    <img src="<?= htmlspecialchars($iconUrl) ?>" alt="avatar" class="avatar-border" onerror="this.style.display='none'">
                    <span><?= htmlspecialchars($displayName) ?></span>
                    <span class="role"><?= htmlspecialchars($currentRole) ?></span>
                </div>
                <span id="selectedCountMain" class="selected-count" style="display:none;"></span>
                <button class="logout-btn" id="logoutBtn">Logout</button>
            </div>

            <!-- Message actions (three-dot) – remains hidden until messages selected -->
            <div class="dropdown">
                <button id="messageActionsBtn" class="three-dot-btn" style="display:none;"><i class="fas fa-ellipsis-v"></i></button>
                <div id="messageActionsMenu" class="dropdown-menu" style="display:none;">
                    <button class="dropdown-item" id="forwardAction"><i class="fas fa-share"></i> Forward</button>
                    <button class="dropdown-item" id="deleteAction"><i class="fas fa-trash-alt"></i> Delete</button>
                    <button class="dropdown-item" id="starAction"><i class="fas fa-star"></i> Star</button>
                    <button class="dropdown-item" id="pinAction"><i class="fas fa-thumbtack"></i> Pin</button>
                </div>
            </div>
        </div>
    </header>

    <!-- Chat-specific header (shown when a chat is open on mobile/tablet) -->
    <header id="appHeaderChat" class="chat-header-bar hidden">
        <div class="header-left">
            <button id="adminBackToChats" class="back-home">
                <i class="fas fa-arrow-left"></i> <span>Chats</span>
            </button>
            <div class="chat-user-info">
                <img id="headerChatUserIcon" src="" alt="" class="header-chat-icon avatar-border">
                <span id="headerChatUserName"></span>
            </div>
            <span id="selectedCountChat" class="selected-count" style="display:none;"></span>
        </div>
        <div class="header-right">
            <button class="theme-toggle" id="themeToggle2" title="Toggle theme">
                <i class="fas <?= $theme === 'dark' ? 'fa-sun' : 'fa-moon' ?>"></i>
            </button>
            <div class="dropdown">
                <button id="messageActionsBtnChat" class="three-dot-btn" style="display:none;"><i class="fas fa-ellipsis-v"></i></button>
                <div id="messageActionsMenuChat" class="dropdown-menu" style="display:none;">
                    <button class="dropdown-item" id="forwardActionChat"><i class="fas fa-share"></i> Forward</button>
                    <button class="dropdown-item" id="deleteActionChat"><i class="fas fa-trash-alt"></i> Delete</button>
                    <button class="dropdown-item" id="starActionChat"><i class="fas fa-star"></i> Star</button>
                    <button class="dropdown-item" id="pinActionChat"><i class="fas fa-thumbtack"></i> Pin</button>
                </div>
            </div>
        </div>
    </header>
<?php else: ?>
    <!-- USER HEADER (single) -->
    <header id="appHeaderUser">
        <div class="header-left">
            <a href="index.php" class="back-home"><i class="fas fa-arrow-left"></i> <span>Home</span></a>
            <h1>Chat</h1>
        </div>
        <div class="header-right">
            <div class="user-info-mini">
                <img src="<?= htmlspecialchars($iconUrl) ?>" alt="avatar" class="avatar-border user-avatar-small">
                <span><?= htmlspecialchars($displayName) ?></span>
            </div>
            <div class="dropdown">
                <button class="three-dot-btn" id="userMenuBtn">
                    <i class="fas fa-ellipsis-v"></i>
                    <?php if ($profileMissingCount > 0): ?>
                        <span class="profile-badge"><?= $profileMissingCount ?></span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu" id="userDropdown" style="display:none;">
                    <button class="dropdown-item" id="themeToggleUser"><i class="fas fa-palette"></i> Change Theme</button>
                    <a href="/profile.php" class="dropdown-item">
                        <i class="fas fa-user-edit"></i> Update Profile
                        <?php if ($profileMissingCount > 0): ?>
                            <span class="profile-badge-dropdown"><?= $profileMissingCount ?></span>
                        <?php endif; ?>
                    </a>
                    <button class="dropdown-item" id="logoutBtnUser"><i class="fas fa-sign-out-alt"></i> Logout</button>
                </div>
            </div>
        </div>
    </header>
<?php endif; ?>

    <div class="app-container <?= ($isAdmin) ? 'admin-mode' : 'user-mode' ?>" id="appContainer">
        <!-- CHAT LIST VIEW (admin only) -->
        <div class="chat-list-view" id="chatListView">
            <div id="chatList"></div>
            <!-- Forward send button -->
            <button id="forwardSendBtn" style="display:none; position:absolute; bottom:20px; right:20px; z-index:999; background:#25d366; color:white; border:none; border-radius:50%; width:56px; height:56px; font-size:1.5rem; cursor:pointer; box-shadow:0 4px 12px rgba(0,0,0,0.3); align-items: center; justify-content: center;">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>

        <!-- CHAT VIEW -->
        <div class="chat-view" id="chatView">
            <div class="chat-header">
                <button class="back-btn" id="backBtn"><i class="fas fa-arrow-left"></i> Back</button>
                <div class="avatar" id="chatAvatar">
                    <img src="" alt="" id="chatAvatarImg" class="avatar-border" style="display:none;">
                    <span id="chatAvatarPlaceholder">👤</span>
                </div>
                <span class="chat-title" id="chatTitle">
                    <?= $isAdmin ? 'Select a chat' : '' ?>
                </span>
            </div>
            <div class="message-list" id="messageList">
                <?php if ($isAdmin): ?>
                    <div style="text-align:center; color:var(--text-light); padding:2rem; font-style:italic;">Select a chat from the list</div>
                <?php else: ?>
                    <div style="text-align:center; color:var(--text-light); padding:2rem; font-style:italic;">Loading your messages...</div>
                <?php endif; ?>
            </div>
            <!-- Typing indicator -->
            <div id="typingIndicator" class="typing-indicator"></div>
            <div class="compose">
                <div class="reply-preview-container" id="replyPreview" style="display:none;">
                    <div id="composeReplyContent" style="flex:1; display:flex; align-items:center; gap:0.5rem; min-width:0;">
                        <!-- JS fills this -->
                    </div>
                    <button class="cancel-reply" id="cancelReply" title="Cancel reply">✕</button>
                </div>
                <form id="messageForm">
                    <div class="input-row">
                        <button type="button" class="icon-btn" id="attachBtn" title="Attach media">
                            <i class="fas fa-paperclip"></i>
                        </button>
                        <input type="file" id="fileInput" accept="image/*,video/*,audio/*" style="display:none;">
                        <button type="button" class="icon-btn" id="voiceBtn" title="Record voice note">
                            <i class="fas fa-microphone"></i>
                        </button>
                        <textarea name="message_body" id="msgBody" placeholder="Type a message..." required></textarea>
                        <button type="submit" class="send-btn" title="Send message">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                    <input type="hidden" id="mediaUrl" value="">
                </form>

                <!-- Voice Recording UI -->
                <div id="voiceRecordingUI" style="display:none;">
                    <div class="voice-timer" id="voiceTimer">0:00</div>
                    <div id="voiceWave" class="voice-wave">
                        <span></span><span></span><span></span><span></span><span></span>
                    </div>
                    <button type="button" id="pauseVoiceBtn" class="icon-btn" title="Pause"><i class="fas fa-pause"></i></button>
                    <button type="button" id="stopVoiceBtn" class="icon-btn" title="Stop"><i class="fas fa-stop"></i></button>
                    <button type="button" id="discardVoiceBtn" class="icon-btn" style="color:#e74c3c;" title="Discard"><i class="fas fa-trash-alt"></i></button>
                    <button type="button" id="sendVoiceBtn" class="btn-next">Next</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification prompt (shown only for non-admin users) -->
    <div id="notificationPrompt" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#1a2744; padding:2rem; border-radius:12px; max-width:400px; text-align:center; color:#ecf0f1; box-shadow:0 8px 20px rgba(0,0,0,0.5);">
            <h3 style="margin-bottom:0.5rem;">🔔 Stay Updated</h3>
            <p style="margin-bottom:1.5rem;">Enable notifications to get real-time alerts when the helpline responds to your chat.</p>
            <button id="enableNotificationsBtn" style="background:#f5c842; color:#0c0920; padding:0.6rem 1.5rem; border:none; border-radius:8px; font-weight:bold; cursor:pointer;">Enable Notifications</button>
            <button id="dismissPromptBtn" style="background:transparent; border:none; color:#888; margin-left:1rem; cursor:pointer;">Not now</button>
        </div>
    </div>

    <!-- Pass PHP config to external JS -->
    <script>
    window.chatConfig = <?= json_encode([
        'currentUserId' => (int)($_SESSION['user_id'] ?? 0),
        'currentRole'   => $_SESSION['role'] ?? 'user',
        'theme'         => $theme ?? 'dark',
        'vapidPublicKey'=> defined('VAPID_PUBLIC_KEY') ? VAPID_PUBLIC_KEY : '',
        'isLoggedIn'    => $isLoggedIn,
        'siteId'        => defined('SITE_ID') ? SITE_ID : 1
    ]) ?>;
    </script>
    <script src="https://cdn.ably.io/lib/ably.min-1.js"></script>
    <script src="assets/js/chat.js"></script>
</body>
</html>