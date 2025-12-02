<?php
require_once "email-utility.php";
require_once "utilities.php";

session_start();
$pdo = get_db();

/*
 * SMTP / Mailtrap configuration
 * (copied from the old place_bid.php written by your teammate)
 */
ini_set("SMTP", $mailHost);
ini_set("smtp_port", $mailPort);
ini_set("sendmail_from", "Man8auction@test.com");

// Mailtrap authentication
ini_set("mail.log", "mail.log");
ini_set("auth_username", $mailUsername);
ini_set("auth_password", $mailPassword);

// Only buyers are allowed to place bids
if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'buyer') {
    die("You must log in as a buyer to place a bid.");
}

// Retrieve and validate POST parameters
$auction_id = $_POST['auction_id'] ?? null;
$bid_amount = $_POST['bid_amount'] ?? null;
$buyer_id   = $_SESSION['user_id'];

if (!$auction_id || !$bid_amount || !is_numeric($bid_amount)) {
    die("Invalid bid input.");
}

try {
    // Start database transaction
    $pdo->beginTransaction();

    // Lock the auction row to prevent concurrent updates
    // Also retrieve itemID and itemName for redirect and email notification
    $stmt = $pdo->prepare("
        SELECT a.endDate, a.itemID, i.itemName
        FROM Auction a
        JOIN Item i ON a.itemID = i.itemID
        WHERE a.auctionID = ?
        FOR UPDATE
    ");
    $stmt->execute([$auction_id]);
    $auction = $stmt->fetch();

    if (!$auction) {
        $pdo->rollBack();
        die("Auction not found.");
    }

    $endDate = new DateTime($auction['endDate']);
    $now     = new DateTime();
    $item_id = $auction['itemID'];      // Used for redirecting back to listing.php
    $title   = $auction['itemName'];    // Used in notification email

    // Check if the auction has already ended
    if ($now > $endDate) {
        $pdo->rollBack();
        die("Auction has already ended.");
    }

    // Retrieve current highest bid
    $stmt = $pdo->prepare("
        SELECT MAX(bidAmount)
        FROM Bid
        WHERE auctionID = ?
    ");
    $stmt->execute([$auction_id]);
    $current_max = $stmt->fetchColumn();

    // Validate that the new bid is higher than the highest existing bid
    if ($current_max !== null && $bid_amount <= $current_max) {
        $pdo->rollBack();
        die("Your bid must be higher than the current highest bid.");
    }

    // Insert new bid record
    $stmt = $pdo->prepare("
        INSERT INTO Bid (auctionID, buyerID, bidAmount, bidTime)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$auction_id, $buyer_id, $bid_amount]);

    // Commit transaction
    $pdo->commit();

} catch (Exception $e) {
    // Roll back the transaction if any error occurs
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Error placing bid: " . $e->getMessage());
}

/*
 * ===== AFTER TRANSACTION IS COMMITTED =====
 * Send an email notification to the previous highest bidder,
 * informing them that their bid has been outbid.
 *
 * Logic:
 *  - Order bids by amount (desc) and timestamp.
 *  - The second highest bid (OFFSET 1) corresponds to the user
 *    who has just been outbid.
 */
try {
    $stmt = $pdo->prepare("
        SELECT buyerID
        FROM Bid
        WHERE auctionID = :aid
        ORDER BY bidAmount DESC, bidTime DESC
        LIMIT 1 OFFSET 1
    ");
    $stmt->execute(['aid' => $auction_id]);
    $prevBuyerId = $stmt->fetchColumn();

    if ($prevBuyerId) {
        // Retrieve the email address of the outbid user
        $userStmt = $pdo->prepare("
            SELECT email
            FROM user
            WHERE userID = :uid
        ");
        $userStmt->execute(['uid' => $prevBuyerId]);
        $email = $userStmt->fetchColumn();

        if ($email) {
            // Send notification email
            $subject = "Your bid was outbid";
            $body    = "<p>Your bid for auction <b>" . htmlspecialchars($title) . "</b> has been outbid.</p>";

            // sendEmail is defined in email-utility.php
            sendEmail($email, $subject, $body);
        }
    }
} catch (Exception $e) {
    // Email failure should not affect bid submission
    // error_log("Failed to send outbid email: " . $e->getMessage());
}

// Redirect back to the listing page using itemID (not auctionID)
// This matches the query logic used in listing.php
header("Location: listing.php?item_id=" . $item_id);
exit;
