<?php
require_once 'utilities.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit;
}

$accountType          = $_POST['accountType'] ?? '';
$email                = trim($_POST['email'] ?? '');
$password             = $_POST['password'] ?? '';
$passwordConfirmation = $_POST['passwordConfirmation'] ?? '';

if ($accountType === '' || $email === '' || $password === '' || $password !== $passwordConfirmation) {
    die('Invalid input or passwords do not match.');
}

$db = get_db();

// Check if email already registered
$stmt = $db->prepare('SELECT userID FROM User WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    die('Email already registered.');
}

// Insert User (use email as username for simplicity)
$username = $email;
$hash     = password_hash($password, PASSWORD_DEFAULT);

$stmt = $db->prepare('INSERT INTO User (username, email, password) VALUES (?, ?, ?)');
$stmt->execute([$username, $email, $hash]);

$userId = (int)$db->lastInsertId();

// Insert into Buyer or Seller based on accountType
if ($accountType === 'buyer') {
    $stmt = $db->prepare('INSERT INTO Buyer (userID) VALUES (?)');
    $stmt->execute([$userId]);
} elseif ($accountType === 'seller') {
    $stmt = $db->prepare('INSERT INTO Seller (userID) VALUES (?)');
    $stmt->execute([$userId]);
}

// Registration complete, redirect to homepage/login page
header('Location: index.php');
exit;
