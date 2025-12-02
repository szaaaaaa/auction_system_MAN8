<?php
require_once "utilities.php";
require_once "email-utility.php";

$pdo = get_db(); 

date_default_timezone_set("Europe/London");
$now = date("Y-m-d H:i:s");

$stmt = $pdo->prepare("
    SELECT * FROM auction 
    WHERE endDate <= :now AND status = 'active'
");
$stmt->execute(['now' => $now]);
$endedAuctions = $stmt->fetchAll();

foreach ($endedAuctions as $auction) {

    $auctionID = $auction['auctionID'];

    $stmt = $pdo->prepare("
        SELECT b.bidAmount, b.buyerID, u.email, u.username
        FROM bid b
        JOIN user u ON b.buyerID = u.userID
        WHERE b.auctionID = :aid
        ORDER BY b.bidAmount DESC
        LIMIT 1
    ");
    $stmt->execute(['aid' => $auctionID]);
    $winner = $stmt->fetch();

    $pdo->prepare("
        UPDATE auction SET status = 'ended' WHERE auctionID = :aid
    ")->execute(['aid' => $auctionID]);

    if ($winner) {

        sendEmail(
            $winner['email'],
            "You won auction #" . $auctionID,
            "
                <h2>Congratulations, {$winner['username']}!</h2>
                <p>You won the auction for item <b>{$auction['itemID']}</b></p>
                <p>Your winning bid: <b>£{$winner['bidAmount']}</b></p>
            "
        );
    }
}

echo "Auction auto-check complete.";
