<?php
// api/login.php
require_once '../includes/db_connect.php';

header('Content-Type: application/json; charset=utf-8');

// Only allow POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

// Sanitize inputs
$email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Email and password are required.']);
    exit;
}

try {
    // Check if user exists
    $stmt = $pdo->prepare('SELECT id, full_name, clinic_name, password, role, status FROM users WHERE email = :email');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Successful login
        
        // Prevent users with "pending", "paused" or "rejected" status from logging in
        if ($user['role'] === 'clinic' && $user['status'] !== 'approved') {
            http_response_code(403); // Forbidden
            $errorMsg = 'Your account is pending approval by the administration.';
            if ($user['status'] === 'paused') {
                $errorMsg = 'Your account has been temporarily paused by the administration. Please contact support.';
            } else if ($user['status'] === 'rejected') {
                $errorMsg = 'Your account application was rejected.';
            }
            echo json_encode(['error' => $errorMsg]);
            exit;
        }

        // Regenerate session ID to prevent session fixation attacks
        session_regenerate_id(true);

        // Store user data in session securely
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['clinic_name'] = $user['clinic_name'];
        
        // Log last activity time to timeout sessions
        $_SESSION['last_activity'] = time();

        http_response_code(200); // OK
        echo json_encode([
            'success' => 'Logged in successfully.',
            'redirect' => $user['role'] === 'admin' ? 'admin/admin_dashboard.php' : 'clinic_dashboard.php'
        ]);
        exit;
    } else {
        // Invalid credentials (generic message to prevent email enumeration)
        http_response_code(401); // Unauthorized
        echo json_encode(['error' => 'Invalid email or password.']);
        exit;
    }

} catch (\PDOException $e) {
    error_log("Login DB Error: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Login failed due to a server error.']);
}
?>