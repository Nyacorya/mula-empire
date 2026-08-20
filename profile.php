<?php
// profile.php - User Profile / Update Page (with password reminder)
require_once 'config.php';

// ---------- Authentication ----------
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    if (isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if (!$conn->connect_error) {
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
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header('Location: index.php');
        exit;
    }
}

$user_id = (int)$_SESSION['user_id'];
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Database error.");
}

// Fetch current user data
$stmt = $conn->prepare("SELECT username, email, number, country, password FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    $conn->close();
    die("User not found.");
}

// Determine which fields are currently NULL (to mark as required)
$required = [
    'username' => is_null($user['username']),
    'phone'    => is_null($user['number']),
    'country'  => is_null($user['country']),
    'password' => is_null($user['password']),
];

// Fetch ENUM values for country column
$country_options = [];
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'country'");
if ($result && $row = $result->fetch_assoc()) {
    $type = $row['Type'];
    if (preg_match("/^enum\((.*)\)$/", $type, $matches)) {
        $options_str = $matches[1];
        $parts = str_getcsv($options_str, ",", "'");
        foreach ($parts as $p) {
            $p = trim($p, "'");
            if ($p !== '') $country_options[] = $p;
        }
    }
}

$displayName = !empty($user['username']) ? $user['username'] : ($user['email'] ?? 'User');
$hasPassword = !empty($user['password']);
$message = '';
$error = '';

// ---------- Handle "Secure Account" password set ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_password') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($new_password === '' || $confirm_password === '') {
        $error = "Both password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_password) < 4) {
        $error = "Password must be at least 4 characters.";
    } else {
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->bind_param("si", $new_password_hash, $user_id);
        if ($stmt->execute()) {
            $message = "Account secured with a password!";
            $user['password'] = $new_password_hash;  // update local array
            $hasPassword = true;
            $required['password'] = false;
        } else {
            $error = "Database update failed. Please try again.";
        }
        $stmt->close();
    }
}

// ---------- Handle full profile update ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $username = trim($_POST['username'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $country  = trim($_POST['country'] ?? '');
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate required fields
    if ($required['username'] && $username === '') {
        $error = "Username is required.";
    } elseif ($required['phone'] && $phone === '') {
        $error = "Phone number is required.";
    } elseif ($required['country'] && $country === '') {
        $error = "Country is required.";
    } elseif ($username !== '' && mb_strlen($username) < 4) {
        $error = "Username must be at least 4 characters.";
    } elseif ($username !== '' && $username !== $user['username']) {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
        $stmt->bind_param("si", $username, $user_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error = "Username already taken.";
        }
        $stmt->close();
    }

    // Phone validation
    if (empty($error) && $phone !== '') {
        $numericPart = ltrim($phone, '+');
        if (!preg_match('/^\+?[0-9]+$/', $phone)) {
            $error = "Phone must contain only + and numbers.";
        } elseif (strlen($numericPart) < 8 || strlen($numericPart) > 16) {
            $error = "Phone number must be between 8 and 16 digits.";
        }
    }

    // Password handling
    if (empty($error)) {
        $password_update = false;
        $new_password_hash = null;

        if ($required['password']) {
            // Password is still required (shouldn't happen if they secured via the reminder, but just in case)
            if ($new_password === '' || $confirm_password === '') {
                $error = "You must set a password (at least 4 characters).";
            } elseif ($new_password !== $confirm_password) {
                $error = "New password and confirmation do not match.";
            } elseif (strlen($new_password) < 4) {
                $error = "Password must be at least 4 characters.";
            } else {
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $password_update = true;
            }
        } else {
            if (!empty($new_password) || !empty($confirm_password)) {
                if ($new_password !== $confirm_password) {
                    $error = "New password and confirmation do not match.";
                } elseif (strlen($new_password) < 4) {
                    $error = "New password must be at least 4 characters.";
                } else {
                    if ($hasPassword) {
                        if (empty($old_password)) {
                            $error = "Old password is required to set a new password.";
                        } elseif (!password_verify($old_password, $user['password'])) {
                            $error = "Old password is incorrect.";
                        }
                    }
                    if (empty($error)) {
                        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                        $password_update = true;
                    }
                }
            }
        }
    }

    if (empty($error)) {
        if ($password_update) {
            $stmt = $conn->prepare("UPDATE users SET username = ?, number = ?, country = ?, password = ? WHERE user_id = ?");
            $stmt->bind_param("ssssi", $username, $phone, $country, $new_password_hash, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = ?, number = ?, country = ? WHERE user_id = ?");
            $stmt->bind_param("sssi", $username, $phone, $country, $user_id);
        }
        if ($stmt->execute()) {
            $message = "Profile updated successfully.";
            $_SESSION['username'] = $username;
            // Refresh user data
            $stmt = $conn->prepare("SELECT username, email, number, country, password FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $hasPassword = !empty($user['password']);
            $displayName = !empty($user['username']) ? $user['username'] : ($user['email'] ?? 'User');
            $required = [
                'username' => is_null($user['username']),
                'phone'    => is_null($user['number']),
                'country'  => is_null($user['country']),
                'password' => is_null($user['password']),
            ];
        } else {
            $error = "Database update failed. Please try again.";
        }
        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - <?= htmlspecialchars($displayName) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            background: #121d35;
            color: #ecf0f1;
            font-family: 'Inter', sans-serif;
            padding-top: 60px;
            padding: 1.2rem;
        }
        .profile-container {
            max-width: 700px;
            margin: 2rem auto;
            background: #1a2744;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 8px 24px rgba(0,0,0,0.4);
        }
        .profile-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .profile-header .back-btn {
            color: #f5c842;
            font-size: 1.2rem;
            text-decoration: none;
        }
        .profile-header h2 {
            margin: 0;
            font-weight: 600;
        }
        /* Warning banner */
        .password-warning {
            border: 2px solid #e74c3c;
            background: rgba(231, 76, 60, 0.1);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.8rem;
        }
        .password-warning .warning-text {
            color: #e74c3c;
            font-weight: 500;
        }
        .btn-secure {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 0.5rem 1.2rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-secure:hover {
            background: #c0392b;
        }
        .info-item {
            padding: 0.8rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        .info-label {
            color: #8888aa;
            font-weight: 500;
            width: 120px;
        }
        .info-value {
            flex: 1;
            color: #ecf0f1;
        }
        .btn-update {
            margin-top: 2rem;
            background: #f5c842;
            color: #0c0920;
            font-weight: 600;
            padding: 0.6rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn-update:hover {
            opacity: 0.9;
        }
        .alert {
            margin-bottom: 1rem;
            padding: 0.8rem;
            border-radius: 6px;
        }
        .alert-success {
            background: rgba(46, 204, 113, 0.2);
            color: #2ecc71;
        }
        .alert-danger {
            background: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
        }
        .modal-content {
            background: #1a2744;
            color: #ecf0f1;
            border: 1px solid #2a2a4a;
        }
        .modal-header {
            border-bottom: 1px solid #2a2a4a;
        }
        .modal-footer {
            border-top: 1px solid #2a2a4a;
        }
        .form-control, .form-select {
            background: #0f192a;
            border-color: #2a2a4a;
            color: #ecf0f1;
        }
        .form-control:focus, .form-select:focus {
            background: #0f192a;
            border-color: #f5c842;
            color: #ecf0f1;
            box-shadow: none;
        }
        .form-control[readonly] {
            background: #1a2744;
            opacity: 0.7;
        }
        .btn-close-white {
            filter: invert(1);
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <div class="profile-header">
            <a href="chat.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
            <h2>Personal Information</h2>
        </div>

        <!-- Password reminder (only if password is null) -->
        <?php if ($required['password']): ?>
        <div class="password-warning">
            <span class="warning-text"><i class="fas fa-exclamation-triangle"></i> Secure your account with a password to protect your chats.</span>
            <button class="btn-secure" data-bs-toggle="modal" data-bs-target="#secureAccountModal">
                <i class="fas fa-lock"></i> Secure Now
            </button>
        </div>
        <?php endif; ?>

        <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="info-item">
            <span class="info-label">Username</span>
            <span class="info-value"><?= htmlspecialchars($user['username'] ?? '—') ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Email</span>
            <span class="info-value"><?= htmlspecialchars($user['email'] ?? '—') ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Phone</span>
            <span class="info-value"><?= htmlspecialchars($user['number'] ?? '—') ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Country</span>
            <span class="info-value"><?= htmlspecialchars($user['country'] ?? '—') ?></span>
        </div>

        <button class="btn-update" data-bs-toggle="modal" data-bs-target="#editProfileModal">
            Update Profile
        </button>
    </div>

    <!-- Secure Account Modal (only password) -->
    <div class="modal fade" id="secureAccountModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <form method="POST" action="profile.php">
                    <input type="hidden" name="action" value="set_password">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-lock"></i> Set Password</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" required minlength="4" placeholder="At least 4 characters">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" required minlength="4" placeholder="Re-enter password">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Secure Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Full Edit Profile Modal -->
    <div class="modal fade" id="editProfileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="profile.php" id="updateProfileForm">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Profile</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Username<?= $required['username'] ? ' *' : '' ?></label>
                            <input type="text" name="username" class="form-control"
                                   value="<?= htmlspecialchars($user['username'] ?? '') ?>"
                                   <?= $required['username'] ? 'required' : '' ?>
                                   minlength="4">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone<?= $required['phone'] ? ' *' : '' ?></label>
                            <input type="text" name="phone" id="phoneInput" class="form-control"
                                   value="<?= htmlspecialchars($user['number'] ?? '') ?>"
                                   <?= $required['phone'] ? 'required' : '' ?>
                                   pattern="\+?[0-9]{8,16}"
                                   title="8-16 digits, optional leading +">
                            <small class="text-muted" style="font-size:0.75rem;">8‑16 digits, optional +</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Country<?= $required['country'] ? ' *' : '' ?></label>
                            <?php if (!empty($country_options)): ?>
                            <select name="country" class="form-select" <?= $required['country'] ? 'required' : '' ?>>
                                <option value="">-- Select Country --</option>
                                <?php foreach ($country_options as $opt): ?>
                                    <option value="<?= htmlspecialchars($opt) ?>"
                                        <?= (isset($user['country']) && $user['country'] === $opt) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($opt) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php else: ?>
                            <input type="text" name="country" class="form-control"
                                   value="<?= htmlspecialchars($user['country'] ?? '') ?>"
                                   <?= $required['country'] ? 'required' : '' ?>>
                            <small class="text-warning">Could not load country list. Type carefully.</small>
                            <?php endif; ?>
                        </div>

                        <?php if (!$required['password'] && $hasPassword): ?>
                        <div class="mb-3">
                            <label class="form-label">Old Password</label>
                            <input type="password" name="old_password" class="form-control" placeholder="Enter current password">
                        </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">New Password<?= $required['password'] ? ' *' : '' ?></label>
                            <input type="password" name="new_password" class="form-control"
                                   placeholder="<?= $required['password'] ? 'Required' : 'Leave blank to keep current' ?>"
                                   <?= $required['password'] ? 'required' : '' ?>
                                   minlength="4">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password<?= $required['password'] ? ' *' : '' ?></label>
                            <input type="password" name="confirm_password" class="form-control"
                                   placeholder="Re-enter new password"
                                   <?= $required['password'] ? 'required' : '' ?>>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning" style="background:#f5c842; color:#0c0920;">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Restrict phone input to + and digits
        const phoneInput = document.getElementById('phoneInput');
        if (phoneInput) {
            phoneInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^+0-9]/g, '');
            });
        }

        // Show appropriate modal if there was an error in the previous submission
        <?php if ($error): ?>
            <?php if (isset($_POST['action']) && $_POST['action'] === 'set_password'): ?>
                var secureModal = new bootstrap.Modal(document.getElementById('secureAccountModal'));
                secureModal.show();
            <?php else: ?>
                var editModal = new bootstrap.Modal(document.getElementById('editProfileModal'));
                editModal.show();
            <?php endif; ?>
        <?php endif; ?>
    </script>
</body>
</html>