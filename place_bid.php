<?php
require_once "utilities.php";
session_start();
$pdo = get_db();

// only buyer can place bid
if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'buyer') {
    die("You must log in as a buyer to place a bid.");
}

// recieve and validate input
$auction_id = $_POST['auction_id'] ?? null;
$bid_amount = $_POST['bid_amount'] ?? null;
$buyer_id   = $_SESSION['user_id'];

if (!$auction_id || !$bid_amount || !is_numeric($bid_amount)) {
    die("Invalid bid input.");
}

// begin transaction
$pdo->beginTransaction();

// lock Auction to prevent race condition
$stmt = $pdo->prepare("
SELECT endDate FROM Auction
WHERE auctionID = ?
FOR UPDATE
");
$stmt->execute([$auction_id]);
$auction = $stmt->fetch();

if (!$auction) {
    $pdo->rollBack();
    die("Auction not found.");
}

// judge if auction is still ongoing
if (new DateTime() > new DateTime($auction['endDate'])) {
    $pdo->rollBack();
    die("Auction has already ended.");
}

// get current max bid
$stmt = $pdo->prepare("
SELECT MAX(bidAmount)
FROM Bid WHERE auctionID = ?
");
$stmt->execute([$auction_id]);
$current_max = $stmt->fetchColumn();

// validate bid amount
if ($current_max !== null && $bid_amount <= $current_max) {
    $pdo->rollBack();
    die("Your bid must be higher than the current highest bid.");
}

// insert new bid
$stmt = $pdo->prepare("
INSERT INTO Bid (auctionID, buyerID, bidAmount, bidTime)
VALUES (?, ?, ?, NOW())
");
$stmt->execute([$auction_id, $buyer_id, $bid_amount]);

// submit transaction
$pdo->commit();

// back to listing page
header("Location: listing.php?item_id=".$auction_id);
exit;
