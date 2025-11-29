<?php include_once("header.php")?>

<div class="container my-5">

<?php

// This function takes the form data and adds the new auction to the database.

    require_once('database_connect.php');

    if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $title = $_POST['auctionTitle'] ?? '';
    $details = $_POST['auctionDetails'] ?? '';
    $categoryID = $_POST['auctionCategory'] ?? '';
    $startPrice = $_POST['auctionStartPrice'] ?? '';
    $reservePrice = $_POST['auctionReservePrice'] ?? '';
    $endDate = $_POST['auctionEndDate'] ?? '';

    
    // Set start date to now
    $startDate = date(format: 'Y-m-d H:i:s');

    
    // Retrieve current user from session (Seller)
    $username = $_SESSION['username'] ?? 'test_seller'; 

    try {
        // 1. Get the Seller's User ID
        $stmtUser = $pdo->prepare("SELECT userID FROM User WHERE username = :username LIMIT 1");
        $stmtUser->execute([':username' => $username]);
        $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);

        // Fallback for testing if user is not found (Assumes ID 1 exists)
        $sellerID = $userRow ? $userRow['userID'] : 1;

        // 2. Data Validation
        if (empty($title) || empty($categoryID) || empty($startPrice) || empty($endDate)) {
            throw new Exception("Please fill in all required fields.");
        }
        
        // Ensure Category ID is numeric (The starter code had text values like 'fill')
        if (!is_numeric($categoryID)) {
             throw new Exception("Invalid Category selected. Please ensure the dropdown sends a numeric ID.");
        }
        // --- Step A: Insert into Item table ---
        $pdo->beginTransaction();
        $sqlItem = "INSERT INTO Item (itemName, description, categoryID, sellerID) 
                    VALUES (:itemName, :description, :categoryID, :sellerID)";
        $stmtItem = $pdo->prepare($sqlItem);
        $stmtItem->execute([
            ':itemName' => $title,
            ':description' => $details,
            ':categoryID' => $categoryID,
            ':sellerID' => $sellerID
        ]);
        // Get the ID of the item we just created
        $newItemID = $pdo->lastInsertId();

        // --- Step B: Insert into Auction table ---
        // Schema: auctionID, itemID, startDate, endDate, startingPrice, reservePrice, status
        
        // Handle optional reserve price
        $reservePriceValue = empty($reservePrice) ? 0 : $reservePrice;

        $sqlAuction = "INSERT INTO Auction (itemID, startDate, endDate, startingPrice, reservePrice, status) 
                       VALUES (:itemID, :startDate, :endDate, :startingPrice, :reservePrice, 'active')";
        $stmtAuction = $pdo->prepare($sqlAuction);
        $stmtAuction->execute([
            ':itemID' => $newItemID,
            ':startDate' => $startDate,
            ':endDate' => $endDate,
            ':startingPrice' => $startPrice,
            ':reservePrice' => $reservePriceValue
        ]);

        // Commit the transaction (save changes)
        $pdo->commit();

        // Success Message
        echo('<div class="alert alert-success text-center" role="alert">
                <h4 class="alert-heading">Auction Created Successfully!</h4>
                <p>Your item "'.htmlspecialchars($title).'" is now live.</p>
                <hr>
                <p class="mb-0">Item ID: '.$newItemID.' | <a href="browse.php" class="alert-link">View in Browse Page</a></p>
              </div>');
        } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Error Message
        echo('<div class="alert alert-danger text-center" role="alert">
                <h4>Creation Failed</h4>
                <p>Error: ' . $e->getMessage() . '</p>
                <a href="create_auction.php" class="btn btn-danger">Try Again</a>
              </div>');
    }
    }

    else {
    // If someone accesses this page directly without submitting the form
    echo('<div class="text-center mt-5">
            <p>Please submit the form via the Create Auction page.</p>
            <a href="create_auction.php" class="btn btn-primary">Go to Form</a>
          </div>');
}
?>

</div>

<?php include_once("footer.php")?>
