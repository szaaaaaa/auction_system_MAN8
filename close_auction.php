<?php
session_start();
require_once("utilities.php");

// Read POST data
$auctionId = $_POST['auction_id'];
$action    = $_POST['action'];

// Fetch auction info
$auction = getAuction($auctionId);

// Extract item_id for redirect
$item_id = $auction['itemID'];

// Permission check: only seller can close the auction
if ($auction['sellerID'] != $_SESSION['user_id']) {
    die("You do not have permission to perform this action.");
}

// CANCEL AUCTION
if ($action == "cancel") {

    updateAuctionStatus($auctionId, 'cancelled');

    header("Location: listing.php?item_id=$item_id&msg=cancelled");
    exit();
}


// SETTLE AUCTION EARLY
if ($action == "settle") {

    // Get highest bid
    $highest = getHighestBid($auctionId);

    // If a bid exists, create a transaction

    if ($highest) {
        createTransaction(
            $auctionId,
            $highest['buyerID'],
            $highest['bidAmount']
        );
    }
    // Update auction status
    updateAuctionStatus($auctionId, 'ended');

    header("Location: listing.php?item_id=$item_id&msg=settled");
    exit();
}
?>
