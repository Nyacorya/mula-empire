<?php
// chat-btn.php – floating chat button + login modal + session restore (no headers)

// ---------- SESSION RESTORE (read-only, no cookies) ----------
$isLoggedIn = false;
$currentUser = '';
$currentRole = '';
$iconUrl = '';
$userId = 0;
$unreadCount = 0;

if (isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $isLoggedIn = true;
    $userId = (int)$_SESSION['user_id'];
    $currentUser = $_SESSION['username'] ?? '';
    $currentRole = $_SESSION['role'] ?? '';
    $iconUrl = $_SESSION['icon_url'] ?? '';

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$conn->connect_error) {
        $siteId = defined('SITE_ID') ? SITE_ID : 1;
        if ($currentRole === 'admin' || $currentRole === 'ceo') {
            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM messages m JOIN chats c ON m.chat_id = c.chat_id WHERE c.site_id = ? AND m.sender_id != ? AND m.message_status IN ('unread','delivered')");
            $stmt->bind_param("ii", $siteId, $userId);
        } else {
            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM messages m JOIN chats c ON m.chat_id = c.chat_id WHERE c.user_id = ? AND c.site_id = ? AND m.sender_id != ? AND m.message_status IN ('unread','delivered')");
            $stmt->bind_param("iii", $userId, $siteId, $userId);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $unreadCount = (int)($row['total'] ?? 0);
        $stmt->close();
        $conn->close();
    }
} else {
    // Try restore from remember token without setting cookie (cookie refresh happens in api login)
    if (isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if (!$conn->connect_error) {
            $stmt = $conn->prepare("SELECT user_id, username, role, icon_url, remember_token FROM users WHERE remember_token IS NOT NULL AND token_expires > NOW()");
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                if (password_verify($token, $row['remember_token'])) {
                    $_SESSION['user_id'] = $row['user_id'];
                    $_SESSION['username'] = $row['username'] ?? null;
                    $_SESSION['role'] = $row['role'] ?? 'user';
                    $_SESSION['icon_url'] = $row['icon_url'] ?? 'https://zany-tech.com/img/user.png';
                    $_SESSION['logged_in'] = true;
                    $isLoggedIn = true;
                    $userId = $row['user_id'];
                    $currentUser = $row['username'] ?? '';
                    $currentRole = $row['role'] ?? 'user';
                    $iconUrl = $row['icon_url'] ?? '';
                    break;
                }
            }
            $stmt->close();
            $conn->close();
        }
    }
}

if (empty($iconUrl) || $iconUrl === '/img/user.png') {
    $iconUrl = ($currentRole === 'admin' || $currentRole === 'ceo')
        ? 'https://zany-tech.com/img/admin.jpg'
        : 'https://zany-tech.com/img/user.png';
}
?>
<div id="blurOverlay"></div>

<!-- CHAT FLOATING BUTTON -->
<?php if ($isLoggedIn): ?>
<a href="chat.php" class="whatsapp-float" style="text-decoration:none; position:fixed;">
  <i class="fab fa-whatsapp"></i>
  <?php if ($unreadCount > 0): ?>
  <span class="unread-badge"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span>
  <?php endif; ?>
</a>
<?php else: ?>
<button class="whatsapp-float" id="whatsappFloatBtn">
  <i class="fab fa-whatsapp"></i>
</button>
<?php endif; ?>

<!-- CHAT LOGIN MODAL -->
<div class="modal fade" id="chatLoginModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title"><i class="fab fa-whatsapp text-success"></i> Start Chatting</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="step-email">
          <p>Enter your email to start chatting.</p>
          <div class="mb-3">
            <input type="email" class="form-control bg-dark text-white border-secondary" id="chatEmail" placeholder="you@example.com" required>
          </div>
          <div id="loginError" class="text-danger"></div>
          <button class="btn btn-primary w-100" id="emailContinueBtn">Continue</button>
        </div>
        <div id="step-password" style="display:none;">
          <p id="passwordPrompt">Enter your password to continue.</p>
          <div class="mb-3">
            <input type="password" class="form-control bg-dark text-white border-secondary" id="chatPassword" placeholder="Enter your password">
          </div>
          <div id="passwordError" class="text-danger"></div>
          <button class="btn btn-primary w-100" id="passwordLoginBtn">Login</button>
          <button class="btn btn-link text-muted mt-2" id="backToEmailBtn">← Use a different email</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Pass config to JS (no PHP headers) -->
<script>
window.chatConfig = <?= json_encode([
    'currentUserId' => (int)($userId ?? 0),
    'currentRole'   => $currentRole ?? 'user',
    'theme'         => $_SESSION['theme'] ?? 'dark',
    'vapidPublicKey'=> defined('VAPID_PUBLIC_KEY') ? VAPID_PUBLIC_KEY : '',
    'isLoggedIn'    => $isLoggedIn,
    'siteId'        => defined('SITE_ID') ? SITE_ID : 1
]) ?>;
</script>