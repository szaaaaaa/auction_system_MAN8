<?php include_once("header.php")?>
<?php require("utilities.php")?>

<div class="container">

<h2 class="my-3">My bids</h2>

<?php
  if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'buyer') {
      echo "<p>You must be logged in as a buyer to see your bids.</p>";
  } else {

      $buyer_id = $_SESSION['user_id'];
      $pdo = get_db();

      //  b_user：Retrieve the list of auctions participated in by the current user
      //  b_all：All bids for this auction, used to calculate the current highest bid and the total number of bids
      $sql = "
          SELECT 
              i.itemID,
              i.itemName,
              i.description,
              a.endDate,
              COALESCE(MAX(b_all.bidAmount), a.startingPrice) AS currentPrice,
              COUNT(DISTINCT b_all.bidID) AS numBids
          FROM auction a
          JOIN item i       ON a.itemID      = i.itemID
          JOIN bid  b_user  ON b_user.auctionID = a.auctionID
          LEFT JOIN bid b_all ON b_all.auctionID = a.auctionID
          WHERE b_user.buyerID = :buyer_id
          AND a.status = 'active'
          AND a.endDate > NOW()
          GROUP BY 
              a.auctionID,
              i.itemID,
              i.itemName,
              i.description,
              a.endDate,
              a.startingPrice
          ORDER BY a.endDate ASC
      ";

      $stmt = $pdo->prepare($sql);
      $stmt->execute([':buyer_id' => $buyer_id]);
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

      echo '<ul class="list-group mt-4">';

      if (empty($rows)) {
          echo "<li class='list-group-item'>You haven't placed any bids yet.</li>";
      } else {
          foreach ($rows as $row) {
              $item_id       = $row['itemID'];
              $title         = $row['itemName'];
              $description   = $row['description'];
              $current_price = $row['currentPrice'];
              $num_bids      = $row['numBids'];
              $end_time      = new DateTime($row['endDate']);

              print_listing_li($item_id, $title, $description, $current_price, $num_bids, $end_time);
          }
      }

      echo '</ul>';
  }
?>

<?php include_once("footer.php")?>