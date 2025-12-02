<?php
require_once("utilities.php");
include_once("header.php");

if (!isset($_SESSION['user_id'])) {
    echo '
      <div class="container mt-5 text-center">
        <p>Please <a href="login.php">log in</a> to see your watchlist.</p>
      </div>
    ';
    include_once("footer.php");
    exit();
}

$buyer_id = (int)$_SESSION['user_id'];
$pdo      = get_db();

  // AUTO-CLEAN FEATURE:
   //Remove auctions that are no longer active
$cleanup_sql = "
    DELETE w FROM watchlist w
    JOIN auction a ON a.auctionID = w.auctionID
    WHERE 
        w.buyerID = :bid
        AND (a.status <> 'active' OR a.endDate <= NOW())
";
$cleanup_stmt = $pdo->prepare($cleanup_sql);
$cleanup_stmt->execute([':bid' => $buyer_id]);

   //Retrieve all active auctions still in the watchlist
$sql = "
  SELECT
    w.auctionID,
    w.watchDate,
    a.itemID,
    a.endDate,
    i.itemName,
    i.description
  FROM watchlist w
  JOIN auction a ON a.auctionID = w.auctionID
  JOIN item    i ON i.itemID    = a.itemID
  WHERE w.buyerID = :bid
    AND a.status = 'active'
    AND a.endDate > NOW()
  ORDER BY w.watchDate DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':bid' => $buyer_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-5">
  <h2 class="mb-4">My watchlist</h2>

  <?php if (empty($rows)): ?>

    <p>You have no active items in your watchlist.</p>

  <?php else: ?>

    <ul class="list-group">
      <?php foreach ($rows as $row): ?>
        <?php
          $item_id = (int)$row['itemID'];
          $title   = $row['itemName'];
          $desc    = $row['description'];
          $watch   = $row['watchDate'];

          $now      = new DateTime();
          $end_time = new DateTime($row['endDate']);
          $diff     = date_diff($now, $end_time);

          $remaining = display_time_remaining($diff) . " remaining";
        ?>

        <li class="list-group-item d-flex justify-content-between">
          <div>
            <h5>
              <a href="listing.php?item_id=<?php echo $item_id; ?>">
                <?php echo htmlspecialchars($title); ?>
              </a>
            </h5>

            <p class="mb-1">
              <?php echo nl2br(htmlspecialchars($desc)); ?>
            </p>

            <small class="text-muted d-block">
              Watched on: <?php echo htmlspecialchars($watch); ?>
            </small>

            <small class="text-muted d-block">
              <?php echo htmlspecialchars($remaining); ?>
            </small>
          </div>
        </li>

      <?php endforeach; ?>
    </ul>

  <?php endif; ?>
</div>

<?php include_once("footer.php"); ?>
