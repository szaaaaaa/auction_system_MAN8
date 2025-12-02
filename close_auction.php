<?php
session_start();
require_once("utilities.php");

$auctionId = $_POST['auction_id'];
$action = $_POST['action'];

// Fetch auction info
$auction = getAuction($auctionId);

// Permission check: only seller can close the auction
if ($auction['sellerID'] != $_SESSION['userID']) {
    die("You do not have permission to perform this action.");
}

// Cancel auction
if ($action == "cancel") {

    // Update status to cancelled
    updateAuctionStatus($auctionId, 'cancelled');

    header("Location: listing.php?auction_id=$auctionId&msg=cancelled");
    exit();
}

// Settle auction early
if ($action == "settle") {

    // Get highest bid
    $highest = getHighestBid($auctionId);

    // If there is a highest bid, create a transaction record
    if ($highest) {
        createTransaction($auctionId, $highest['bidderID'], $highest['amount']);
    }

    // Mark auction as ended
    updateAuctionStatus($auctionId, 'ended');

    header("Location: listing.php?auction_id=$auctionId&msg=settled");
    exit();
}
?>