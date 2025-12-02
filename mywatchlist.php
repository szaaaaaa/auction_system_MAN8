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


$sql = "
  SELECT
    w.auctionID,
    w.watchDate,
    a.itemID,
    a.endDate,
    i.itemName,
    i.description
  FROM watchlist w
  LEFT JOIN auction a ON a.auctionID = w.auctionID
  LEFT JOIN item    i ON i.itemID    = a.itemID
  WHERE w.buyerID = :bid
  ORDER BY w.watchDate DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':bid' => $buyer_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-5">
  <h2 class="mb-4">My watchlist</h2>

  <?php if (empty($rows)): ?>

    <p>You have no items in your watchlist yet.</p>

  <?php else: ?>

    <ul class="list-group">
      <?php foreach ($rows as $row): ?>
        <?php
          $item_id = (int)$row['itemID'];
          $title   = $row['itemName'] ?? ('Auction #' . $row['auctionID']);
          $desc    = $row['description'] ?? '';
          $watch   = $row['watchDate'] ?? '';
          $end_str = $row['endDate'] ?? '';

          $time_remaining_text = '';
          if ($end_str !== '') {
            $now      = new DateTime();
            $end_time = new DateTime($end_str);

            if ($now < $end_time) {
              $diff = date_diff($now, $end_time);
              $time_remaining_text = display_time_remaining($diff) . ' remaining';
            } else {
              $time_remaining_text = 'Ended';
            }
          }
        ?>
        <li class="list-group-item d-flex justify-content-between">
          <div>
            <h5>
              <a href="listing.php?item_id=<?php echo $item_id; ?>">
                <?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>
              </a>
            </h5>
            <p class="mb-1">
              <?php echo nl2br(htmlspecialchars($desc, ENT_QUOTES, 'UTF-8')); ?>
            </p>
            <small class="text-muted d-block">
              Watched on:
              <?php echo htmlspecialchars($watch, ENT_QUOTES, 'UTF-8'); ?>
            </small>
            <?php if ($time_remaining_text !== ''): ?>
              <small class="text-muted d-block">
                <?php echo htmlspecialchars($time_remaining_text, ENT_QUOTES, 'UTF-8'); ?>
              </small>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>

  <?php endif; ?>
</div>

