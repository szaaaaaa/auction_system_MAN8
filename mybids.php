<?php include_once("header.php")?>
<?php require("utilities.php")?>

<div class="container">

<h2 class="my-3">My bids</h2>

<?php
// Ensure the user is logged in as a buyer
if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'buyer') {
    echo "<p>You must be logged in as a buyer to see your bids.</p>";
} else {

    $buyer_id = $_SESSION['user_id'];
    $pdo = get_db();

    // Retrieve all auctions the buyer has placed bids on
    // Includes both active and ended auctions
    $sql = "
        SELECT 
            a.auctionID,
            i.itemID,
            i.itemName,
            i.description,
            a.endDate,
            a.status,
            COALESCE(MAX(b_all.bidAmount), a.startingPrice) AS currentPrice,
            COUNT(DISTINCT b_all.bidID) AS numBids
        FROM auction a
        JOIN item i ON a.itemID = i.itemID
        JOIN bid b_user ON b_user.auctionID = a.auctionID
        LEFT JOIN bid b_all ON b_all.auctionID = a.auctionID
        WHERE b_user.buyerID = :buyer_id
        GROUP BY 
            a.auctionID,
            i.itemID,
            i.itemName,
            i.description,
            a.endDate,
            a.status,
            a.startingPrice
        ORDER BY 
            (a.status = 'active') DESC,   -- Active auctions first
            a.endDate ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':buyer_id' => $buyer_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo '<ul class="list-group mt-4">';

    if (empty($rows)) {
        echo "<li class='list-group-item'>You haven't placed any bids yet.</li>";
    } else {
        foreach ($rows as $row) {
            print_listing_li(
                $row['itemID'],
                $row['itemName'],
                $row['description'],
                $row['currentPrice'],
                $row['numBids'],
                new DateTime($row['endDate']),
                $row['status']   // pass auction status
            );
        }
    }

    echo '</ul>';
}
?>

<?php include_once("footer.php")?>
