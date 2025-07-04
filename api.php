<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$api = "https://api.mail.tm";

function random_name($length = 10) {
    return substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyz'), 0, $length);
}

$action = $_GET['action'] ?? '';

if ($action === 'generate') {
    // Get domain
    $domainRes = file_get_contents("$api/domains");
    if (!$domainRes) {
        echo json_encode(["error" => "Failed to fetch domain"]);
        exit;
    }

    $domains = json_decode($domainRes, true);
    $domain = $domains['hydra:member'][0]['domain'] ?? null;
    if (!$domain) {
        echo json_encode(["error" => "No domain found"]);
        exit;
    }

    // Create email + password
    $email = random_name() . "@" . $domain;
    $password = "Password123!";
    $accountData = json_encode(["address" => $email, "password" => $password]);

    // Create account
    $createContext = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n",
            'content' => $accountData
        ]
    ]);
    $createResult = file_get_contents("$api/accounts", false, $createContext);

    // Get token
    $tokenContext = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n",
            'content' => $accountData
        ]
    ]);
    $tokenRes = @file_get_contents("$api/token", false, $tokenContext);

    if (!$tokenRes) {
        echo json_encode(["error" => "Failed to get token"]);
        exit;
    }

    $tokenData = json_decode($tokenRes, true);
    $token = $tokenData['token'] ?? null;

    if (!$token) {
        echo json_encode(["error" => "Invalid token response", "details" => $tokenData]);
        exit;
    }

    // Save to history
    file_put_contents("generated_emails.txt", "[" . date("Y-m-d H:i:s") . "] $email\n", FILE_APPEND);

    echo json_encode([
        "email" => $email,
        "token" => $token
    ]);
    exit;
}

if ($action === 'restore') {
    $lines = @file("generated_emails.txt");
    if (!$lines || count($lines) === 0) {
        echo json_encode(["error" => "No previous emails found"]);
        exit;
    }

    $last = trim(end($lines));
    if (!str_contains($last, "] ")) {
        echo json_encode(["error" => "Invalid history format"]);
        exit;
    }

    $email = explode("] ", $last)[1];
    $password = "Password123!";

    $tokenContext = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n",
            'content' => json_encode(["address" => $email, "password" => $password])
        ]
    ]);
    $tokenRes = @file_get_contents("$api/token", false, $tokenContext);

    if (!$tokenRes) {
        echo json_encode(["error" => "Failed to restore token"]);
        exit;
    }

    $tokenData = json_decode($tokenRes, true);
    $token = $tokenData['token'] ?? null;

    if (!$token) {
        echo json_encode(["error" => "Invalid token response", "details" => $tokenData]);
        exit;
    }

    echo json_encode([
        "email" => $email,
        "token" => $token
    ]);
    exit;
}

echo json_encode(["error" => "Invalid action"]);
