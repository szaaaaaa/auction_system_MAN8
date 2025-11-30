<?php
require_once "utilities.php";
session_start();
$pdo = get_db();

// 只允许 buyer 出价
if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'buyer') {
    die("You must log in as a buyer to place a bid.");
}

// 接收参数
$auction_id = $_POST['auction_id'] ?? null;
$bid_amount = $_POST['bid_amount'] ?? null;
$buyer_id   = $_SESSION['user_id'];

if (!$auction_id || !$bid_amount || !is_numeric($bid_amount)) {
    die("Invalid bid input.");
}

// 开启事务
$pdo->beginTransaction();

// 锁 Auction（防止并发出价）
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

// 判断是否过期
if (new DateTime() > new DateTime($auction['endDate'])) {
    $pdo->rollBack();
    die("Auction has already ended.");
}

// 取得当前最高价
$stmt = $pdo->prepare("
SELECT MAX(bidAmount)
FROM Bid WHERE auctionID = ?
");
$stmt->execute([$auction_id]);
$current_max = $stmt->fetchColumn();

// 校验新出价
if ($current_max !== null && $bid_amount <= $current_max) {
    $pdo->rollBack();
    die("Your bid must be higher than the current highest bid.");
}

// 插入新 bid
$stmt = $pdo->prepare("
INSERT INTO Bid (auctionID, buyerID, bidAmount, bidTime)
VALUES (?, ?, ?, NOW())
");
$stmt->execute([$auction_id, $buyer_id, $bid_amount]);

// 提交事务
$pdo->commit();

// 回跳页面
header("Location: listing.php?item_id=".$auction_id);
exit;
