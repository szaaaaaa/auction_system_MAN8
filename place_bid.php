<?php
session_start();
require("db.php");

// 检查 POST 是否存在
if (!isset($_POST['bid_amount'], $_POST['auction_id'])) {
    die("Invalid request.");
}

$bid_amount = floatval($_POST['bid_amount']);
$auction_id = intval($_POST['auction_id']);

// 测试阶段：如果你们没做登录系统，先用 buyerID = 1
$buyer_id = $_SESSION['user_id'] ?? 1;

// 1. 查询 auction 是否存在 + 是否结束
$stmt = $pdo->prepare("SELECT endDate, startingPrice FROM Auction WHERE auctionID = ?");
$stmt->execute([$auction_id]);
$auction = $stmt->fetch();

if (!$auction) {
    die("Auction not found.");
}

$now = new DateTime();
$end = new DateTime($auction['endDate']);

if ($now > $end) {
    die("Auction has ended.");
}

// 2. 获取当前最高价
$stmt = $pdo->prepare("SELECT MAX(bidAmount) AS max_bid FROM Bid WHERE auctionID = ?");
$stmt->execute([$auction_id]);
$row = $stmt->fetch();

$current_max = $row['max_bid'] ?? $auction['startingPrice'];

// 3. 校验新出价
if ($bid_amount <= $current_max) {
    die("Your bid must be higher than £" . $current_max);
}

// 4. 插入 Bid
$stmt = $pdo->prepare("
    INSERT INTO Bid (auctionID, buyerID, bidAmount, bidTime)
    VALUES (?, ?, ?, NOW())
");
$stmt->execute([$auction_id, $buyer_id, $bid_amount]);

// 5. 返回 listing 页面
header("Location: listing.php?item_id=$auction_id");
exit;
