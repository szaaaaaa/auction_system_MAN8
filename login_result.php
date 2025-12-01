<?php
require_once 'utilities.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    die('Please enter email and password.');
}

$db = get_db();

# Fetch user by email
$stmt = $db->prepare('SELECT userID, username, email, password FROM User WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die('No such user.');
}

if (!password_verify($password, $user['password'])) {
    die('Incorrect password.');
}

# Determine account type
$accountType = null;

$stmt = $db->prepare('SELECT userID FROM Buyer WHERE userID = ?');
$stmt->execute([$user['userID']]);
if ($stmt->fetch()) {
    $accountType = 'buyer';
}

$stmt = $db->prepare('SELECT userID FROM Seller WHERE userID = ?');
$stmt->execute([$user['userID']]);
if ($stmt->fetch()) {
    $accountType = 'seller';
}

$_SESSION['logged_in']    = true;
$_SESSION['user_id']      = (int)$user['userID'];
$_SESSION['username']     = $user['username'];
$_SESSION['account_type'] = $accountType;

header('Location: index.php');
exit;
