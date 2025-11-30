<?php include_once("header.php")?>

<div class="container my-5">

<?php

// This function takes the form data and adds the new auction to the database.

    require_once("utilities.php");
    $pdo = get_db();
    

    if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $title = $_POST['auctionTitle'] ?? '';
    $details = $_POST['auctionDetails'] ?? '';
    $categoryID = $_POST['auctionCategory'] ?? '';
    $startPrice = $_POST['auctionStartPrice'] ?? '';
    $reservePrice = $_POST['auctionReservePrice'] ?? '';
    $endDate = $_POST['auctionEndDate'] ?? '';
    $startDate = date(format: 'Y-m-d H:i:s');
    $username = $_SESSION['username'] ?? 'test_seller'; 

    try {
        $sqlUser = "SELECT u.userID 
                    FROM User u 
                    JOIN Seller s ON u.userID = s.userID 
                    WHERE u.username = :username 
                    LIMIT 1";
        $stmtUser = $pdo->prepare("SELECT userID FROM User WHERE username = :username LIMIT 1");
        $stmtUser->execute([':username' => $username]);
        $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
        if ($userRow) {
            $sellerID = $userRow['userID'];
        } else {
            throw new Exception(message: "Current user is not a registered seller.");
        }

       
        if (empty($title) || empty($categoryID) || empty($startPrice) || empty($endDate)) {
            throw new Exception(message: "Please fill in all required fields.");
        }
        
      
        if (!is_numeric(value: $categoryID)) {
             throw new Exception(message: "Invalid Category selected. ");
        }

        $pdo->beginTransaction();
        $sqlItem = "INSERT INTO Item (itemName, description, categoryID, sellerID) 
                    VALUES (:itemName, :description, :categoryID, :sellerID)";
        $stmtItem = $pdo->prepare(query: $sqlItem);
        $stmtItem->execute([
            ':itemName' => $title,
            ':description' => $details,
            ':categoryID' => $categoryID,
            ':sellerID' => $sellerID
        ]);

        $newItemID = $pdo->lastInsertId();

        // Insert into Auction table ---
        $reservePriceValue = empty($reservePrice) ? 0 : $reservePrice;
        $status = 'ACTIVE'; 
        $isAnonymous = isset($_POST['isAnonymous']) ? 1 : 0; 

        $sqlAuction = "INSERT INTO Auction (itemID, startDate, endDate, startingPrice, reservePrice, status, isAnonymous) 
                       VALUES (:itemID, :startDate, :endDate, :startingPrice, :reservePrice, 'active', :isAnonymous )";
        $stmtAuction = $pdo->prepare($sqlAuction);
        $stmtAuction->execute([
            ':itemID' => $newItemID,
            ':startDate' => $startDate,
            ':endDate' => $endDate,
            ':startingPrice' => $startPrice,
            ':reservePrice' => $reservePriceValue,
            ':isAnonymous' => $isAnonymous
        ]);

        // Commit the transaction
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
