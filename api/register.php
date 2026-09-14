<?php
// api/register.php
require_once '../includes/db_connect.php';
require_once '../includes/locations.php';

header('Content-Type: application/json; charset=utf-8');

// Allow only POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

// Sanitize and get input data
$full_name = trim(filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING));
$clinic_name = trim(filter_input(INPUT_POST, 'clinic_name', FILTER_SANITIZE_STRING));
$email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
$password = $_POST['password'] ?? '';
$phone = trim(filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING));
$location_scope = trim((string) filter_input(INPUT_POST, 'location_scope', FILTER_SANITIZE_STRING));
$governorate = trim((string) filter_input(INPUT_POST, 'governorate', FILTER_SANITIZE_STRING));
$outside_country = trim((string) filter_input(INPUT_POST, 'outside_country', FILTER_SANITIZE_STRING));

// Validate required fields
if (empty($full_name) || empty($clinic_name) || empty($email) || empty($password) || empty($phone) || empty($location_scope)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'All fields are required.']);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid email format.']);
    exit;
}

// Validate password length (e.g., min 8 characters)
if (strlen($password) < 8) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Password must be at least 8 characters long.']);
    exit;
}

if (!in_array($location_scope, ['inside_egypt', 'outside_egypt'], true)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid clinic location selection.']);
    exit;
}

if ($location_scope === 'inside_egypt') {
    if (!array_key_exists($governorate, getAvailableEgyptGovernorates($pdo))) {
        http_response_code(400);
        echo json_encode(['error' => 'This governorate is not currently available. Please select another governorate.']);
        exit;
    }
    $country = 'egypt';
} else {
    if (!in_array($outside_country, getOutsideEgyptCountries(), true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Please enter a valid country name.']);
        exit;
    }
    $country = $outside_country;
    $governorate = null;
}

try {
    // Check if email already exists
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
        http_response_code(409); // Conflict
        echo json_encode(['error' => 'Email is already registered.']);
        exit;
    }

    // Hash password strongly using BCRYPT (default in modern PHP)
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert user (default role 'clinic' and status 'pending' as defined in DB schema)
    $stmt = $pdo->prepare('
        INSERT INTO users (full_name, clinic_name, email, password, phone, country, governorate)
        VALUES (:full_name, :clinic_name, :email, :password, :phone, :country, :governorate)
    ');

    $stmt->execute([
        ':full_name'   => $full_name,
        ':clinic_name' => $clinic_name,
        ':email'       => $email,
        ':password'    => $hashed_password,
        ':phone'       => $phone,
        ':country'     => $country,
        ':governorate' => $governorate
    ]);

    http_response_code(201); // Created
    echo json_encode(['success' => 'Account created successfully. Awaiting admin approval.']);

} catch (\PDOException $e) {
    // Log exception for debugging (don't send to frontend)
    error_log("Registration DB Error: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Registration failed due to a server error.']);
}
?>
