<?php include_once("header.php")?>
<?php require("utilities.php")?>

<div class="container">

<h2 class="my-3">Recommendations for you</h2>

<?php
  // TODO 1: 检查用户是否登录（cookie / session）
  if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
      echo '<p>You need to log in to see recommendations.</p>';
      echo '</div>';
      include_once("footer.php");
      exit();
  }

  // 当前买家的 ID（根据你的登录逻辑，可能是 userID / id / buyerID，自行调整）
  $buyer_id = $_SESSION['user_id'];
  $pdo = get_db();   
  // TODO 2: 根据用户的出价历史，查询他们可能感兴趣的拍卖
  $sql = "
      SELECT 
          a.auctionID,
          i.itemID,
          i.itemName,
          i.description,
          a.endDate,
          a.startingPrice,
          COUNT(DISTINCT b_all.bidID) AS numBids,
          COALESCE(MAX(b_all.bidAmount), a.startingPrice) AS currentPrice
      FROM auction a
      JOIN item i ON a.itemID = i.itemID
      LEFT JOIN bid b_all ON b_all.auctionID = a.auctionID
      WHERE a.status = 'active'
        AND EXISTS (
            SELECT 1
            FROM bid b_u
            JOIN auction a_u ON b_u.auctionID = a_u.auctionID
            JOIN item i_u ON a_u.itemID = i_u.itemID
            WHERE b_u.buyerID = :buyer_id
              AND i_u.categoryID = i.categoryID
        )
        AND NOT EXISTS (
            SELECT 1
            FROM bid b_self
            WHERE b_self.auctionID = a.auctionID
              AND b_self.buyerID = :buyer_id
        )
      GROUP BY 
          a.auctionID,
          i.itemID,
          i.itemName,
          i.description,
          a.endDate,
          a.startingPrice
      ORDER BY a.endDate ASC
      LIMIT 20
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute([':buyer_id' => $buyer_id]);

  // TODO 3: 循环结果，输出为列表项
  echo '<ul class="list-group">';

  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $auction_id    = $row['auctionID'];
      $item_id       = $row['itemID'];
      $title         = $row['itemName'];
      $description   = $row['description'];
      $current_price = $row['currentPrice'];
      $num_bids      = $row['numBids'];
      $end_time      = new DateTime($row['endDate']);
      
   print_listing_li($item_id, $title, $description, $current_price, $num_bids, $end_time);
  }

  echo '</ul>';

  if (!$has_results) {
      echo '<p>No recommendations yet. Try bidding on some items first!</p>';
  }
  ?>

  echo '</ul>';
  ?>

</div>

<?php include_once("footer.php") ?>
