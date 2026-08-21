<?php
// api.php - Complete API with Ably Real-time and Web Push
session_start();
header('Content-Type: application/json');

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

require_once 'config.php';


$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

$siteId = SITE_ID;   // current site id from config

function sendJson($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function generateRememberToken() {
    return bin2hex(random_bytes(32));
}

// ---------- ABLY HELPERS (curl) ----------
function ablyPublish($channel, $event, $data) {
    if (!defined('ABLY_KEY') || !ABLY_KEY) return;
    $url = 'https://rest.ably.io/channels/' . rawurlencode($channel) . '/messages';
    $auth = base64_encode(ABLY_KEY);
    $payload = json_encode(['name' => $event, 'data' => $data]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Basic ' . $auth
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function ablyRequestToken($clientId) {
    if (!defined('ABLY_KEY') || !ABLY_KEY || strpos(ABLY_KEY, ':') === false) {
        error_log('Ably token failed: ABLY_KEY missing or malformed');
        return null;
    }
    if (!function_exists('curl_init')) {
        error_log('Ably token failed: cURL extension missing');
        return null;
    }

    $keyName = substr(ABLY_KEY, 0, strpos(ABLY_KEY, ':'));
    $url = 'https://rest.ably.io/keys/' . $keyName . '/requestToken';
    $auth = base64_encode(ABLY_KEY);
    $post = json_encode([
        'keyName'   => $keyName,
        'clientId'  => $clientId,
        'timestamp' => round(microtime(true) * 1000),
        'ttl'       => 3600000
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Basic ' . $auth
        ],
        CURLOPT_POSTFIELDS     => $post,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,   // local; set true in production
        CURLOPT_TIMEOUT        => 10,
    ]);

    $res = curl_exec($ch);
    if ($res === false) {
        error_log('Ably token curl error: ' . curl_error($ch));
        curl_close($ch);
        return null;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 && $httpCode !== 201) {
        error_log("Ably token HTTP $httpCode: $res");
        return null;
    }

    $json = json_decode($res, true);
    return $json['token'] ?? null;
}

// ---------- PURE PHP WEB PUSH ----------
// function sendWebPushNotification($userId, $title, $body, $data = []) {
//     global $conn;

//     $stmt = $conn->prepare("SELECT endpoint, auth_token, public_key FROM push_subscriptions WHERE user_id = ?");
//     $stmt->bind_param("i", $userId);
//     $stmt->execute();
//     $subscriptions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
//     $stmt->close();

//     if (empty($subscriptions)) return;

//     $payload = json_encode([
//         'title' => $title,
//         'body'  => $body,
//         'data'  => $data,
//     ]);

//     // VAPID keys from config
//     $vapidPublicKey  = VAPID_PUBLIC_KEY;
//     $vapidPrivateKey = VAPID_PRIVATE_KEY;
//     $vapidSubject    = defined('VAPID_SUBJECT') ? VAPID_SUBJECT : 'mailto:admin@example.com';

//     // Build VAPID headers
//     $vapidHeaders = getVapidHeaders($vapidPublicKey, $vapidPrivateKey, $vapidSubject);

//     foreach ($subscriptions as $sub) {
//         $endpoint  = $sub['endpoint'];
//         $authToken = $sub['auth_token'];   // URL-safe base64
//         $userKey   = $sub['public_key'];   // URL-safe base64

//         // Decode the user public key and auth secret from base64url
//         $userPublicKey = base64url_decode($userKey);
//         $authSecret    = base64url_decode($authToken);

//         // Generate salt and local key pair
//         $salt = random_bytes(16);
//         $localPrivateKey = openssl_pkey_new([
//             'private_key_bits' => 256,
//             'private_key_type' => OPENSSL_KEYTYPE_EC,
//             'curve_name'       => 'prime256v1'
//         ]);
//         openssl_pkey_export($localPrivateKey, $localPrivateKeyPem);
//         $localPublicKeyDetails = openssl_pkey_get_details($localPrivateKey);
//         $localPublicKey = $localPublicKeyDetails['key']; // PEM public key

//         // Convert PEM public key to raw 65 bytes (uncompressed point)
//         $localPublicKeyRaw = pemToUncompressedPoint($localPublicKey);

//         // ECDH shared secret
//         $sharedSecret = openssl_pkey_derive($userPublicKey, $localPrivateKey);

//         // HKDF
//         $prk = hash_hmac('sha256', $sharedSecret, $authSecret, true);
//         $keyInfo = "WebPush: info\0";
//         $ikm = $userPublicKey . $localPublicKeyRaw;
//         $cek = hkdf($prk, $salt, $keyInfo . $ikm, 32);
//         $nonce = hkdf($prk, $salt, "WebPush: nonce\0" . $ikm, 12);

//         // Encrypt payload with AES-128-GCM
//         $tag = '';
//         $ciphertext = openssl_encrypt(
//             $payload,
//             'aes-128-gcm',
//             $cek,
//             OPENSSL_RAW_DATA,
//             $nonce,
//             $tag
//         );

//         // Prepare the encrypted payload body
//         $encryptedBody = $salt . $localPublicKeyRaw . $tag . $ciphertext;

//         // Send via curl
//         $ch = curl_init($endpoint);
//         curl_setopt_array($ch, [
//             CURLOPT_POST           => true,
//             CURLOPT_HTTPHEADER     => array_merge($vapidHeaders, [
//                 'Content-Type: application/octet-stream',
//                 'Content-Length: ' . strlen($encryptedBody),
//                 'TTL: 2419200',
//             ]),
//             CURLOPT_POSTFIELDS     => $encryptedBody,
//             CURLOPT_RETURNTRANSFER => true,
//             CURLOPT_SSL_VERIFYPEER => true,
//         ]);
//         curl_exec($ch);
//         curl_close($ch);
//     }
// }

// Helper: base64url decode
// function base64url_decode($data) {
//     $padding = strlen($data) % 4;
//     if ($padding > 0) {
//         $data .= str_repeat('=', 4 - $padding);
//     }
//     return base64_decode(strtr($data, '-_', '+/'));
// }

// Helper: base64url encode
// function base64url_encode($data) {
//     return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
// }

// Helper: generate VAPID headers (Authorization + Crypto-Key)
// function getVapidHeaders($publicKey, $privateKey, $subject) {
//     $header = ['typ' => 'JWT', 'alg' => 'ES256'];
//     $payload = [
//         'aud' => parse_url($subject, PHP_URL_SCHEME) . '://' . parse_url($subject, PHP_URL_HOST),
//         'exp' => time() + 86400,
//         'sub' => $subject,
//     ];

//     $jwt = generateJWT($header, $payload, $privateKey);

//     return [
//         'Authorization: WebPush ' . $jwt,
//         'Crypto-Key: p256ecdsa=' . $publicKey,
//     ];
// }

// Helper: generate a JWT using ES256
// function generateJWT($header, $payload, $privateKeyPem) {
//     $segments = [];
//     $segments[] = base64url_encode(json_encode($header));
//     $segments[] = base64url_encode(json_encode($payload));
//     $signingInput = implode('.', $segments);
//     $signature = signWithES256($signingInput, $privateKeyPem);
//     $segments[] = base64url_encode($signature);
//     return implode('.', $segments);
// }

// Helper: sign with ES256 and return raw IEEE-P1363 signature
// function signWithES256($data, $privateKeyPem) {
//     $key = openssl_pkey_get_private($privateKeyPem);
//     if (!$key) return '';
//     openssl_sign($data, $derSignature, $key, OPENSSL_ALGO_SHA256);
//     // Convert DER signature to IEEE P1363 (r||s)
//     $der = $derSignature;
//     $r = '';
//     $s = '';
//     $offset = 4;
//     $len = ord($der[1]);
//     // skip remaining length bytes if any
//     if ($len > 127) {
//         $numBytes = $len & 0x7f;
//         $offset += $numBytes;
//     }
//     // r
//     $lenR = ord($der[$offset + 1]);
//     $r = substr($der, $offset + 2, $lenR);
//     $offset += 2 + $lenR;
//     // s
//     $lenS = ord($der[$offset + 1]);
//     $s = substr($der, $offset + 2, $lenS);

//     // Ensure r and s are 32 bytes each (pad/truncate)
//     $r = str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT);
//     $s = str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
//     return $r . $s;
// }

// Helper: HKDF (RFC 5869) using HMAC-SHA256
// function hkdf($key, $salt, $info, $length) {
//     $prk = hash_hmac('sha256', $key, $salt, true);
//     $t = '';
//     $okm = '';
//     for ($i = 1; $i <= ceil($length / 32); $i++) {
//         $t = hash_hmac('sha256', $t . $info . chr($i), $prk, true);
//         $okm .= $t;
//     }
//     return substr($okm, 0, $length);
// }

// Helper: convert PEM public key to uncompressed point (65 bytes)
// function pemToUncompressedPoint($pem) {
//     $key = openssl_pkey_get_public($pem);
//     $details = openssl_pkey_get_details($key);
//     if ($details['type'] !== OPENSSL_KEYTYPE_EC) return '';
//     // The 'ec' details contain x and y coordinates as binary strings
//     $x = $details['ec']['x'];
//     $y = $details['ec']['y'];
//     // Uncompressed point = 0x04 + X (32 bytes) + Y (32 bytes)
//     return "\x04" . $x . $y;
// }
// ==========================================
// PURE PHP WEB PUSH (no Composer)
// ==========================================
function base64url_decode($data) {
    $pad = strlen($data) % 4;
    if ($pad) $data .= str_repeat('=', 4 - $pad);
    return base64_decode(strtr($data, '-_', '+/'));
}

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function sendWebPushNotification($userId, $title, $body, $data = []) {
    global $conn;

    // Get subscriptions
    $stmt = $conn->prepare("SELECT endpoint, auth_token, public_key FROM push_subscriptions WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $subscriptions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($subscriptions)) return;

    // Compute unread count for badge if not already in data
    if (!isset($data['badge_count'])) {
        // For simplicity, set badge to 1 (can be improved later with real count)
        $data['badge_count'] = 1;
    }

    $payload = json_encode([
        'title' => $title,
        'body'  => $body,
        'data'  => $data,
    ]);

    $vapidPublicKey  = VAPID_PUBLIC_KEY;
    $vapidPrivateKey = VAPID_PRIVATE_KEY;
    $vapidSubject    = defined('VAPID_SUBJECT') ? VAPID_SUBJECT : 'mailto:admin@example.com';

    // Generate VAPID headers
    $vapidHeaders = getVapidHeaders($vapidPublicKey, $vapidPrivateKey, $vapidSubject);

    foreach ($subscriptions as $sub) {
        $endpoint  = $sub['endpoint'];
        $authToken = $sub['auth_token'];
        $userKey   = $sub['public_key'];

        // Decode subscription keys
        $userPublicKey = base64url_decode($userKey);
        $authSecret    = base64url_decode($authToken);

        // Generate local ECDH key pair
        $config = [
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name'       => 'prime256v1'
        ];
        $localKey = openssl_pkey_new($config);
        openssl_pkey_export($localKey, $localPrivateKeyPem);
        $details = openssl_pkey_get_details($localKey);
        $localPublicKeyPem = $details['key'];

        // Convert local public PEM to uncompressed raw point
        $localPublicKeyRaw = pemToUncompressedPoint($localPublicKeyPem);

        // Convert user raw point to PEM for ECDH
        $userPublicKeyPem = rawPointToPem($userPublicKey);

        // ECDH shared secret
        $sharedSecret = openssl_pkey_derive($userPublicKeyPem, $localPrivateKeyPem);

        // HKDF
        $prk = hash_hmac('sha256', $sharedSecret, $authSecret, true);
        $ikm = $userPublicKey . $localPublicKeyRaw;
        $cek = hkdf($prk, $ikm, "WebPush: info\0", 32);
        $nonce = hkdf($prk, $ikm, "WebPush: nonce\0", 12);

        // Encrypt
        $tag = '';
        $ciphertext = openssl_encrypt(
            $payload,
            'aes-128-gcm',
            $cek,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        $encryptedBody = $salt = random_bytes(16) . $localPublicKeyRaw . $tag . $ciphertext;

        // Send via curl
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => array_merge($vapidHeaders, [
                'Content-Type: application/octet-stream',
                'Content-Length: ' . strlen($encryptedBody),
                'TTL: 2419200',
            ]),
            CURLOPT_POSTFIELDS     => $encryptedBody,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}

function getVapidHeaders($publicKey, $privateKey, $subject) {
    $header = ['typ' => 'JWT', 'alg' => 'ES256'];
    $payload = [
        'aud' => parse_url($subject, PHP_URL_SCHEME) . '://' . parse_url($subject, PHP_URL_HOST),
        'exp' => time() + 86400,
        'sub' => $subject,
    ];
    $jwt = generateJWT($header, $payload, $privateKey);
    return [
        'Authorization: WebPush ' . $jwt,
        'Crypto-Key: p256ecdsa=' . $publicKey,
    ];
}

function generateJWT($header, $payload, $privateKeyPem) {
    $segments = [];
    $segments[] = base64url_encode(json_encode($header));
    $segments[] = base64url_encode(json_encode($payload));
    $signingInput = implode('.', $segments);
    $signature = signWithES256($signingInput, $privateKeyPem);
    $segments[] = base64url_encode($signature);
    return implode('.', $segments);
}

function signWithES256($data, $privateKeyPem) {
    $key = openssl_pkey_get_private($privateKeyPem);
    if (!$key) return '';
    openssl_sign($data, $derSignature, $key, OPENSSL_ALGO_SHA256);
    // Convert DER signature to raw IEEE P1363 (r||s)
    $der = $derSignature;
    $offset = 0;
    if (ord($der[0]) == 0x30) {
        $len1 = ord($der[1]);
        if ($len1 > 127) $offset = 2 + ($len1 & 0x7f);
        else $offset = 2;
        $offset++; // skip 0x02
        $lenR = ord($der[$offset]);
        $offset++;
        $r = substr($der, $offset, $lenR);
        $offset += $lenR;
        $offset++; // skip 0x02
        $lenS = ord($der[$offset]);
        $offset++;
        $s = substr($der, $offset, $lenS);
        $r = str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
        return $r . $s;
    }
    return '';
}

function hkdf($key, $info, $salt, $length) {
    $prk = hash_hmac('sha256', $key, $salt, true);
    $t = '';
    $okm = '';
    for ($i = 1; $i <= ceil($length / 32); $i++) {
        $t = hash_hmac('sha256', $t . $info . chr($i), $prk, true);
        $okm .= $t;
    }
    return substr($okm, 0, $length);
}

function pemToUncompressedPoint($pem) {
    $key = openssl_pkey_get_public($pem);
    $details = openssl_pkey_get_details($key);
    return "\x04" . $details['ec']['x'] . $details['ec']['y'];
}

function rawPointToPem($rawPoint) {
    // Construct a valid EC public key PEM from uncompressed point (0x04 + x + y)
    $pem = "-----BEGIN PUBLIC KEY-----\n";
    $pem .= chunk_split(base64_encode(
        "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01" .
        "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00" .
        $rawPoint
    ), 64, "\n");
    $pem .= "-----END PUBLIC KEY-----\n";
    return $pem;
}




// ---------- AUTHENTICATION HELPER ----------
function isAuthenticated() {
    global $conn;
    
    if (isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
        return true;
    }
    
    if (isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        $stmt = $conn->prepare("SELECT user_id, username, role, icon_url, remember_token FROM users WHERE remember_token IS NOT NULL AND token_expires > NOW()");
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                if (password_verify($token, $row['remember_token'])) {
                    $_SESSION['user_id'] = $row['user_id'];
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['role'] = $row['role'] ?? 'user';
                    $_SESSION['icon_url'] = $row['icon_url'] ?? '';
                    $_SESSION['logged_in'] = true;
                    return true;
                }
            }
        }
    }
    return false;
}

$action = $_GET['action'] ?? '';

// ---------- TEST ACTION ----------
if ($action === 'test') {
    sendJson(['status' => 'ok', 'message' => 'API is working']);
}

// ---------- PUBLIC: users ----------
if ($action === 'users') {
    $result = $conn->query("SELECT user_id, username, email, role, access FROM users ORDER BY username");
    if (!$result) sendJson(['error' => 'Database query failed: ' . $conn->error], 500);
    sendJson($result->fetch_all(MYSQLI_ASSOC));
}

// ---------- PUBLIC: check_user (email only) ----------
if ($action === 'check_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);

    // Fallback to form-encoded or query parameters
    if (!$input) {
        $input = $_POST;
        if (empty($input)) {
            parse_str($raw, $input);
        }
    }

    $email = trim($input['email'] ?? ($_REQUEST['email'] ?? ''));

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
        sendJson(['error' => 'Please enter a valid email address.'], 400);
    }

    $stmt = $conn->prepare("SELECT user_id, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $hasPassword = !empty($row['password']);
        sendJson(['exists' => true, 'has_password' => $hasPassword, 'user_id' => $row['user_id']]);
    } else {
        sendJson(['exists' => false]);
    }
}

// ---------- Chat Login ----------
if ($action === 'chat_login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Parse input (JSON or form-encoded)
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!$input) {
        $input = $_POST;
        if (empty($input)) {
            parse_str($raw, $input);
        }
    }

    $email = trim($input['email'] ?? '');
    $password = trim($input['password'] ?? '');

    // Strict email validation
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
        sendJson(['error' => 'Please enter a valid email address.'], 400);
    }

    // Check if theme column exists
    $themeColumn = false;
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'theme'");
    if ($result && $result->num_rows > 0) {
        $themeColumn = true;
    }

    // Select user (include access column)
    $sql = $themeColumn ? 
        "SELECT user_id, username, role, icon_url, password, theme, access FROM users WHERE email = ?" :
        "SELECT user_id, username, role, icon_url, password, access FROM users WHERE email = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        // Create new user (password null, no access)
        $username = null;
        $sql = $themeColumn ?
            "INSERT INTO users (username, email, role, status, theme, access) VALUES (?, ?, 'user', 'enabled', 'dark', NULL)" :
            "INSERT INTO users (username, email, role, status, access) VALUES (?, ?, 'user', 'enabled', NULL)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $username, $email);
        if (!$stmt->execute()) {
            sendJson(['error' => 'Failed to create user: ' . $conn->error], 500);
        }
        $userId = $conn->insert_id;
        
        // Create chat entry with site_id
        $stmt = $conn->prepare("INSERT INTO chats (user_id, site_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $userId, $siteId);
        if (!$stmt->execute()) {
            sendJson(['error' => 'Failed to create chat: ' . $conn->error], 500);
        }
        $user = [
            'user_id' => $userId,
            'username' => null,
            'role' => 'user',
            'icon_url' => 'https://zany-tech.com/img/user.png',
            'password' => null,
            'theme' => 'dark',
            'access' => null
        ];
    } else {
        // User exists - verify password if set
        if (!empty($user['password'])) {
            if (empty($password)) {
                sendJson(['error' => 'Password required for this account.'], 401);
            }
            if (!password_verify($password, $user['password'])) {
                sendJson(['error' => 'Incorrect password.'], 401);
            }
        } else {
            // No password set - allow login; if password provided, save it
            if (!empty($password)) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $stmt->bind_param("si", $hashed, $user['user_id']);
                $stmt->execute();
                $user['password'] = $hashed;
            }
        }
        
        $userId = $user['user_id'];
        // Ensure chat exists for this site
        $stmt = $conn->prepare("SELECT chat_id FROM chats WHERE user_id = ? AND site_id = ?");
        $stmt->bind_param("ii", $userId, $siteId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $stmt = $conn->prepare("INSERT INTO chats (user_id, site_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $userId, $siteId);
            if (!$stmt->execute()) {
                sendJson(['error' => 'Failed to create chat: ' . $conn->error], 500);
            }
        }
    }

    // Determine effective role for current site
    $accessList = array_map('intval', array_filter(explode(',', $user['access'] ?? '')));
    $effectiveRole = (($user['role'] === 'admin' || $user['role'] === 'ceo') && in_array($siteId, $accessList))
        ? $user['role']
        : 'user';

    // Set session
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $effectiveRole;
    $_SESSION['icon_url'] = $user['icon_url'] ?? 'https://zany-tech.com/img/user.png';
    $_SESSION['theme'] = $user['theme'] ?? 'dark';
    $_SESSION['logged_in'] = true;
    $_SESSION['email'] = $email;

    // Remember token
    try {
        $token = generateRememberToken();
        $expires = time() + (defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 31536000);
        $tokenHash = password_hash($token, PASSWORD_DEFAULT);
        $tokenExpires = date('Y-m-d H:i:s', $expires);
        $stmt = $conn->prepare("UPDATE users SET remember_token = ?, token_expires = ? WHERE user_id = ?");
        $stmt->bind_param("ssi", $tokenHash, $tokenExpires, $userId);
        $stmt->execute();
        setcookie('remember_token', $token, $expires, '/', '', false, true);
    } catch (Exception $e) {
        // Continue even if token fails
    }

    // Ensure correct default icon based on effective role
    if (empty($_SESSION['icon_url']) || $_SESSION['icon_url'] === '/img/user.png') {
        $defaultIcon = ($effectiveRole === 'admin' || $effectiveRole === 'ceo')
                        ? 'https://zany-tech.com/img/admin.jpg'
                        : 'https://zany-tech.com/img/user.png';
        $_SESSION['icon_url'] = $defaultIcon;

        $stmt = $conn->prepare("UPDATE users SET icon_url = ? WHERE user_id = ?");
        $stmt->bind_param("si", $defaultIcon, $userId);
        $stmt->execute();
    }

    sendJson(['success' => true]);
}

// ---------- UPDATE THEME ----------
if ($action === 'update_theme' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isAuthenticated()) {
        sendJson(['error' => 'Unauthorized'], 401);
    }
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) sendJson(['error' => 'Invalid JSON'], 400);
    $theme = trim($input['theme'] ?? '');
    if (!in_array($theme, ['dark', 'light'])) sendJson(['error' => 'Invalid theme'], 400);
    $userId = (int)$_SESSION['user_id'];
    
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'theme'");
    if ($result && $result->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE users SET theme = ? WHERE user_id = ?");
        $stmt->bind_param("si", $theme, $userId);
        if (!$stmt->execute()) {
            sendJson(['error' => 'Failed to update theme: ' . $conn->error], 500);
        }
        $_SESSION['theme'] = $theme;
    } else {
        $_SESSION['theme'] = $theme;
    }
    sendJson(['success' => true, 'theme' => $theme]);
}

// ---------- UPDATE ONLINE STATUS ----------
if ($action === 'update_online_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isAuthenticated()) {
        sendJson(['error' => 'Unauthorized'], 401);
    }
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) sendJson(['error' => 'Invalid JSON'], 400);
    $status = trim($input['status'] ?? 'online');
    $validStatuses = ['online', 'offline', 'away'];
    if (!in_array($status, $validStatuses)) $status = 'online';
    $userId = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("UPDATE users SET online_status = ?, last_activity = NOW() WHERE user_id = ?");
    $stmt->bind_param("si", $status, $userId);
    $stmt->execute();
    sendJson(['success' => true, 'status' => $status]);
}

// ---------- CHECK SESSION ----------
if ($action === 'check_session' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isAuthenticated()) {
        sendJson([
            'logged_in' => true,
            'user_id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role'],
            'icon_url' => $_SESSION['icon_url'],
            'theme' => $_SESSION['theme'] ?? 'dark'
        ]);
    }
    sendJson(['logged_in' => false]);
}

// ---------- LOGOUT ----------
if ($action === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_SESSION['user_id'])) {
        $stmt = $conn->prepare("UPDATE users SET remember_token = NULL, token_expires = NULL WHERE user_id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
    }
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    setcookie('remember_token', '', time() - 3600, '/');
    sendJson(['success' => true]);
}

// ---------- ABLY TEST (AUTHENTICATED) ----------
if ($action === 'ably_test' && isAuthenticated()) {
    $token = ablyRequestToken('user-' . $_SESSION['user_id']);
    if ($token) {
        sendJson(['success' => true, 'token' => substr($token, 0, 10) . '...']);
    } else {
        sendJson(['error' => 'Token generation failed. Check php_errors.log'], 503);
    }
}

// ---------- ABLY TOKEN (AUTHENTICATED) ----------
if ($action === 'ably_token' && isAuthenticated()) {
    $token = ablyRequestToken('user-' . $_SESSION['user_id']);
    if ($token) {
        sendJson(['token' => $token]);
    } else {
        error_log('Ably token endpoint failed for user ' . $_SESSION['user_id']);
        sendJson(['error' => 'Token generation failed'], 503);
    }
}

// ---------- ALL OTHER ENDPOINTS REQUIRE AUTH ----------
if (!isAuthenticated()) {
    sendJson(['error' => 'Unauthorized'], 401);
}

$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';
$isAdmin = ($role === 'admin' || $role === 'ceo');

// ---------- my_chat (site-specific) ----------
if ($action === 'my_chat' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $conn->prepare("SELECT chat_id FROM chats WHERE user_id = ? AND site_id = ?");
    $stmt->bind_param("ii", $userId, $siteId);
    $stmt->execute();
    $chat = $stmt->get_result()->fetch_assoc();
    if ($chat) {
        sendJson(['chat_id' => $chat['chat_id']]);
    } else {
        sendJson(['error' => 'No chat found'], 404);
    }
}

// ---------- chats (admin only, site-filtered) ----------
if ($action === 'chats' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!$isAdmin) sendJson(['error' => 'Forbidden'], 403);
    $sql = "SELECT c.chat_id, c.user_id, u.username, u.email, u.icon_url,
                   (SELECT CASE WHEN deleted = 'yes' THEN 'This message was deleted!' ELSE message_body END FROM messages WHERE chat_id = c.chat_id ORDER BY created_at DESC LIMIT 1) AS last_msg,
                   (SELECT created_at FROM messages WHERE chat_id = c.chat_id ORDER BY created_at DESC LIMIT 1) AS last_time,
                   (SELECT COUNT(*) FROM messages WHERE chat_id = c.chat_id AND sender_id != ? AND message_status IN ('unread','delivered')) AS unread
            FROM chats c
            JOIN users u ON c.user_id = u.user_id
            WHERE c.site_id = ?
            ORDER BY last_time DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $userId, $siteId);
    $stmt->execute();
    $chats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($chats as &$chat) {
        if (empty($chat['username'])) $chat['username'] = $chat['email'];
    }
    sendJson($chats);
}

// ---------- messages (site-specific for user) ----------
if ($action === 'messages' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $chatId = isset($_GET['chat_id']) ? (int)$_GET['chat_id'] : null;
    if (!$isAdmin) {
        $stmt = $conn->prepare("SELECT chat_id FROM chats WHERE user_id = ? AND site_id = ?");
        $stmt->bind_param("ii", $userId, $siteId);
        $stmt->execute();
        $chat = $stmt->get_result()->fetch_assoc();
        if (!$chat) sendJson(['error' => 'No chat found'], 404);
        $chatId = $chat['chat_id'];
    } else {
        if (!$chatId) sendJson(['error' => 'chat_id required'], 400);
        $stmt = $conn->prepare("SELECT chat_id FROM chats WHERE chat_id = ? AND site_id = ?");
        $stmt->bind_param("ii", $chatId, $siteId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) sendJson(['error' => 'Chat not found'], 404);
    }

    $sql = "SELECT m.*, u.username, u.email, u.icon_url
            FROM messages m
            JOIN users u ON m.sender_id = u.user_id
            WHERE m.chat_id = ?
            ORDER BY m.created_at ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $chatId);
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($messages as &$msg) {
        if (empty($msg['username'])) $msg['username'] = $msg['email'];
        $msg['valid'] = ($msg['expires_at'] === null || strtotime($msg['expires_at']) > time());
    }
    sendJson($messages);
}

// ---------- send (site-specific for user) ----------
// ---------- send (site-specific for user) ----------
if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Parse input (JSON or form-encoded)
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!$input) {
        $input = $_POST;
        if (empty($input)) {
            parse_str($raw, $input);
        }
    }

    $body = trim($input['message_body'] ?? '');
    $mediaUrl = isset($input['media_url']) && $input['media_url'] ? $input['media_url'] : null;

    if (empty($body) && empty($mediaUrl)) {
        sendJson(['error' => 'Message body or attachment is required.'], 400);
    }

    $chatId = isset($input['chat_id']) ? (int)$input['chat_id'] : null;
    if ($isAdmin) {
        if (!$chatId) sendJson(['error' => 'chat_id required'], 400);
        $stmt = $conn->prepare("SELECT chat_id FROM chats WHERE chat_id = ? AND site_id = ?");
        $stmt->bind_param("ii", $chatId, $siteId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) sendJson(['error' => 'Invalid chat_id'], 400);
    } else {
        $stmt = $conn->prepare("SELECT chat_id FROM chats WHERE user_id = ? AND site_id = ?");
        $stmt->bind_param("ii", $userId, $siteId);
        $stmt->execute();
        $chat = $stmt->get_result()->fetch_assoc();
        if (!$chat) sendJson(['error' => 'No chat found'], 404);
        $chatId = $chat['chat_id'];
    }

    $tagId = isset($input['tag_id']) && $input['tag_id'] ? (int)$input['tag_id'] : null;
    $expiresAt = isset($input['expires_at']) && $input['expires_at'] ? $input['expires_at'] : null;
    $mediaUrl = isset($input['media_url']) && $input['media_url'] ? $input['media_url'] : null;

    $stmt = $conn->prepare("INSERT INTO messages (chat_id, sender_id, message_body, tag_id, media_url, expires_at) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisiss", $chatId, $userId, $body, $tagId, $mediaUrl, $expiresAt);
    if (!$stmt->execute()) {
        sendJson(['error' => 'Failed to send message: ' . $conn->error], 500);
    }
    $messageId = $conn->insert_id;

    // Prepare realtime data for Ably
    $data = [
        'message_id'   => $messageId,
        'chat_id'      => $chatId,
        'sender_id'    => $userId,
        'sender_role'  => $role,
        'sender_name'  => $_SESSION['username'] ?? $_SESSION['email'] ?? 'User',
        'sender_icon'  => $_SESSION['icon_url'] ?? '',
        'message_body' => $body,
        'media_url'    => $mediaUrl,
        'created_at'   => date('Y-m-d H:i:s'),
        'tag_id'       => $tagId,
        'deleted'      => 'no',
        'star'         => 'no',
        'pin'          => '0',
        'message_status'=> 'unread',
        'valid'        => true
    ];

    // Publish real-time events via Ably
    try {
        ablyPublish('chat_' . $chatId, 'new-message', $data);
        ablyPublish('site_' . $siteId, 'new-message-site', $data);
    } catch (Exception $e) {
        error_log('Ably publish failed: ' . $e->getMessage());
    }

    // Send Web Push notifications
    try {
        if ($isAdmin) {
            // Admin sends to user: notify the user
            $stmt = $conn->prepare("SELECT user_id FROM chats WHERE chat_id = ?");
            $stmt->bind_param("i", $chatId);
            $stmt->execute();
            $chatRow = $stmt->get_result()->fetch_assoc();
            $recipientId = $chatRow['user_id'] ?? null;
            if ($recipientId) {
                sendWebPushNotification($recipientId, 'New message', $body ?: 'Media file', [
                    'chat_id' => $chatId,
                    'badge_count' => 1
                ]);
            }
        } else {
            // User sends: notify admins with access to this site
            $admins = $conn->query("SELECT user_id FROM users WHERE role IN ('admin','ceo') AND FIND_IN_SET($siteId, REPLACE(access, ' ', ''))")->fetch_all(MYSQLI_ASSOC);
            foreach ($admins as $admin) {
                sendWebPushNotification($admin['user_id'], 'New message', $body ?: 'Media file', [
                    'chat_id' => $chatId,
                    'badge_count' => 1
                ]);
            }
        }
    } catch (Exception $e) {
        error_log('WebPush failed: ' . $e->getMessage());
    }

    // Always return success after insertion.
    sendJson(['success' => true, 'message_id' => $messageId]);
}

// ---------- mark_chat_read (with Ably publish via curl) ----------
if ($action === 'mark_chat_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) sendJson(['error' => 'Invalid JSON'], 400);
    $chatId = (int)($input['chat_id'] ?? 0);
    if (!$chatId) sendJson(['error' => 'Missing chat_id'], 400);

    $stmt = $conn->prepare("UPDATE messages SET message_status = 'read' WHERE chat_id = ? AND sender_id != ? AND message_status IN ('unread','delivered')");
    $stmt->bind_param("ii", $chatId, $userId);
    $stmt->execute();
    $affected = $stmt->affected_rows;

    if ($affected > 0) {
        ablyPublish('chat_' . $chatId, 'messages-read', [
            'chat_id' => $chatId,
            'reader_id' => $userId,
            'reader_role' => $role
        ]);
    }

    sendJson(['success' => true, 'updated' => $affected]);
}

// ---------- mark_chat_delivered (with Ably publish via curl) ----------
if ($action === 'mark_chat_delivered' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) sendJson(['error' => 'Invalid JSON'], 400);
    $chatId = (int)($input['chat_id'] ?? 0);
    if (!$chatId) sendJson(['error' => 'Missing chat_id'], 400);

    $stmt = $conn->prepare("UPDATE messages SET message_status = 'delivered' WHERE chat_id = ? AND sender_id != ? AND message_status = 'unread'");
    $stmt->bind_param("ii", $chatId, $userId);
    $stmt->execute();
    $affected = $stmt->affected_rows;

    if ($affected > 0) {
        ablyPublish('chat_' . $chatId, 'messages-delivered', [
            'chat_id' => $chatId,
            'reader_id' => $userId,
            'reader_role' => $role
        ]);
    }

    sendJson(['success' => true, 'updated' => $affected]);
}

// ---------- typing (with Ably publish via curl) ----------
if ($action === 'typing' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $chatId = (int)($input['chat_id'] ?? 0);
    if (!$chatId) sendJson(['error' => 'Missing chat_id'], 400);

    $typingData = [
        'chat_id' => $chatId,
        'user_id' => $userId,
        'name'    => $_SESSION['username'] ?? $_SESSION['email'] ?? 'User',
        'role'    => $role,
        'time'    => time()
    ];

    ablyPublish('chat_' . $chatId, 'typing', $typingData);
    ablyPublish('site_' . $siteId, 'typing-site', $typingData);

    sendJson(['success' => true]);
}

// ---------- read (single) ----------
if ($action === 'read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) sendJson(['error' => 'Invalid JSON'], 400);
    $msgId = (int)($input['message_id'] ?? 0);
    if (!$msgId) sendJson(['error' => 'Missing message_id'], 400);
    $stmt = $conn->prepare("UPDATE messages SET message_status = 'read' WHERE message_id = ?");
    $stmt->bind_param("i", $msgId);
    $stmt->execute();
    sendJson(['success' => true]);
}

// ---------- mark_notified ----------
if ($action === 'mark_notified' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) sendJson(['error' => 'Invalid JSON'], 400);
    $messageId = (int)($input['message_id'] ?? 0);
    if (!$messageId) sendJson(['error' => 'Missing message_id'], 400);
    $stmt = $conn->prepare("UPDATE messages SET notified = 1 WHERE message_id = ? AND sender_id != ?");
    $stmt->bind_param("ii", $messageId, $userId);
    $stmt->execute();
    sendJson(['success' => true]);
}

// ---------- unread_count (site-specific, includes delivered) ----------
if ($action === 'unread_count' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!$isAdmin) {
        $sql = "SELECT COUNT(*) as total FROM messages m 
                JOIN chats c ON m.chat_id = c.chat_id 
                WHERE c.user_id = ? AND c.site_id = ? AND m.sender_id != ? AND m.message_status IN ('unread','delivered')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $userId, $siteId, $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $unread = (int)$row['total'];
    } else {
        $sql = "SELECT COUNT(*) as total FROM messages m 
                JOIN chats c ON m.chat_id = c.chat_id 
                WHERE c.site_id = ? AND m.sender_id != ? AND m.message_status IN ('unread','delivered')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $siteId, $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $unread = (int)$row['total'];
    }
    sendJson(['unread' => $unread]);
}

// ---------- unread_messages (site-specific, includes delivered) ----------
if ($action === 'unread_messages' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    if (!$isAdmin) {
        $sql = "SELECT m.*, u.username, u.email, u.icon_url
                FROM messages m 
                JOIN chats c ON m.chat_id = c.chat_id 
                JOIN users u ON m.sender_id = u.user_id
                WHERE c.user_id = ? AND c.site_id = ? AND m.sender_id != ? AND m.message_status IN ('unread','delivered')
                ORDER BY m.created_at DESC LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiii", $userId, $siteId, $userId, $limit);
        $stmt->execute();
        $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $sql = "SELECT m.*, u.username, u.email, u.icon_url, c.user_id as chat_owner_id
                FROM messages m 
                JOIN chats c ON m.chat_id = c.chat_id 
                JOIN users u ON m.sender_id = u.user_id
                WHERE c.site_id = ? AND m.sender_id != ? AND m.message_status IN ('unread','delivered')
                ORDER BY m.created_at DESC LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $siteId, $userId, $limit);
        $stmt->execute();
        $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    sendJson($messages);
}

// ---------- save_subscription (push) ----------
if ($action === 'save_subscription' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) sendJson(['error' => 'Invalid JSON'], 400);
    $endpoint = trim($input['endpoint'] ?? '');
    $authToken = trim($input['authToken'] ?? '');
    $publicKey = trim($input['publicKey'] ?? '');
    if (empty($endpoint) || empty($authToken) || empty($publicKey)) {
        sendJson(['error' => 'Missing subscription data'], 400);
    }
    $stmt = $conn->prepare("SELECT id FROM push_subscriptions WHERE user_id = ? AND endpoint = ?");
    $stmt->bind_param("is", $userId, $endpoint);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE push_subscriptions SET auth_token = ?, public_key = ?, updated_at = NOW() WHERE user_id = ? AND endpoint = ?");
        $stmt->bind_param("ssis", $authToken, $publicKey, $userId, $endpoint);
    } else {
        $stmt = $conn->prepare("INSERT INTO push_subscriptions (user_id, endpoint, auth_token, public_key) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $userId, $endpoint, $authToken, $publicKey);
    }
    if ($stmt->execute()) {
        sendJson(['success' => true]);
    } else {
        sendJson(['error' => 'Failed to save subscription: ' . $conn->error], 500);
    }
}

// ---------- remove_subscription ----------
if ($action === 'remove_subscription' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) sendJson(['error' => 'Invalid JSON'], 400);
    $endpoint = trim($input['endpoint'] ?? '');
    if (empty($endpoint)) sendJson(['error' => 'Missing endpoint'], 400);
    $stmt = $conn->prepare("DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint = ?");
    $stmt->bind_param("is", $userId, $endpoint);
    $stmt->execute();
    sendJson(['success' => true]);
}

// ---------- upload_attachment ----------
if ($action === 'upload_attachment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isAuthenticated()) {
        sendJson(['error' => 'Unauthorized'], 401);
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        sendJson(['error' => 'No file uploaded or upload error.'], 400);
    }

    $file = $_FILES['file'];
    $maxSize = 20 * 1024 * 1024; // 20 MB

    if ($file['size'] > $maxSize) {
        sendJson(['error' => 'File too large. Max 20 MB.'], 400);
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $requestedSubfolder = $_POST['subfolder'] ?? '';

    if ($requestedSubfolder === 'voice') {
        $subfolder = 'voice';
    } else {
        if (str_starts_with($mime, 'image/')) {
            $subfolder = 'image';
        } elseif (str_starts_with($mime, 'video/')) {
            $subfolder = 'video';
        } elseif (str_starts_with($mime, 'audio/')) {
            $subfolder = 'audio';
        } else {
            sendJson(['error' => 'Unsupported file format.'], 400);
        }
    }

    $uploadDir = __DIR__ . '/attachments/' . $subfolder . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
        file_put_contents($uploadDir . '.htaccess', "Deny from all\n");
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newName = uniqid() . ($extension ? '.' . $extension : '');
    $destination = $uploadDir . $newName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        sendJson(['error' => 'Failed to save file.'], 500);
    }

    $relativeUrl = 'attachments/' . $subfolder . '/' . $newName;
    sendJson(['success' => true, 'url' => $relativeUrl]);
}

// ---------- ADMIN MESSAGE ACTIONS ----------
if ($isAdmin) {
    // Delete selected messages
    if ($action === 'delete_messages' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['message_ids']) || !is_array($input['message_ids'])) {
            sendJson(['error' => 'Missing message_ids array'], 400);
        }
        $ids = array_map('intval', $input['message_ids']);
        if (empty($ids)) sendJson(['error' => 'No messages selected'], 400);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare("UPDATE messages SET deleted = 'yes' WHERE message_id IN ($placeholders)");
        $types = str_repeat('i', count($ids));
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        sendJson(['success' => true, 'affected' => $stmt->affected_rows]);
    }

    // Toggle star for a message
    if ($action === 'star_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['message_id']) || !isset($input['star'])) {
            sendJson(['error' => 'Missing message_id or star'], 400);
        }
        $messageId = (int)$input['message_id'];
        $star = $input['star'] === 'yes' ? 'yes' : 'no';
        $stmt = $conn->prepare("UPDATE messages SET star = ? WHERE message_id = ?");
        $stmt->bind_param("si", $star, $messageId);
        $stmt->execute();
        sendJson(['success' => true]);
    }

    // Toggle pin for a message (max 5 per chat)
    if ($action === 'pin_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['message_id'])) {
            sendJson(['error' => 'Missing message_id'], 400);
        }
        $messageId = (int)$input['message_id'];
        $stmt = $conn->prepare("SELECT chat_id, pin FROM messages WHERE message_id = ?");
        $stmt->bind_param("i", $messageId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        if (!$row) sendJson(['error' => 'Message not found'], 404);
        $chatId = $row['chat_id'];
        $currentPin = $row['pin'];

        if ($currentPin === '0') {
            $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM messages WHERE chat_id = ? AND pin != '0'");
            $stmt->bind_param("i", $chatId);
            $stmt->execute();
            $cnt = $stmt->get_result()->fetch_assoc()['cnt'];
            $stmt->close();
            if ($cnt >= 5) {
                sendJson(['error' => 'Cannot pin more than 5 messages per chat'], 400);
            }
            $newPin = '1';
        } else {
            $newPin = '0';
        }
        $stmt = $conn->prepare("UPDATE messages SET pin = ? WHERE message_id = ?");
        $stmt->bind_param("si", $newPin, $messageId);
        $stmt->execute();
        sendJson(['success' => true, 'pin' => $newPin]);
    }

    // Forward selected messages to target chats
    if ($action === 'forward_messages' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['message_ids']) || !isset($input['chat_ids']) ||
            !is_array($input['message_ids']) || !is_array($input['chat_ids'])) {
            sendJson(['error' => 'Missing message_ids or chat_ids array'], 400);
        }
        $messageIds = array_map('intval', $input['message_ids']);
        $chatIds = array_map('intval', $input['chat_ids']);
        if (empty($messageIds) || empty($chatIds)) sendJson(['error' => 'Empty selection'], 400);

        $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
        $stmt = $conn->prepare("SELECT * FROM messages WHERE message_id IN ($placeholders) AND deleted = 'no'");
        $types = str_repeat('i', count($messageIds));
        $stmt->bind_param($types, ...$messageIds);
        $stmt->execute();
        $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($messages)) sendJson(['error' => 'No valid messages to forward'], 400);

        $conn->begin_transaction();
        try {
            $insertStmt = $conn->prepare("INSERT INTO messages (chat_id, sender_id, message_body, media_url, tag_id, deleted, star, pin) VALUES (?, ?, ?, ?, NULL, 'no', 'no', '0')");
            foreach ($chatIds as $targetChatId) {
                foreach ($messages as $msg) {
                    $insertStmt->bind_param("iiss", $targetChatId, $userId, $msg['message_body'], $msg['media_url']);
                    $insertStmt->execute();
                }
            }
            $insertStmt->close();
            $conn->commit();
            sendJson(['success' => true, 'forwarded' => count($messages) * count($chatIds)]);
        } catch (Exception $e) {
            $conn->rollback();
            sendJson(['error' => 'Forward failed: ' . $e->getMessage()], 500);
        }
    }
}

// Fallback
sendJson(['error' => 'Invalid action'], 400);