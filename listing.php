<?php
require_once "utilities.php";
$pdo = get_db();
include_once("header.php");



// read URL
if (!isset($_GET['item_id']) || !is_numeric($_GET['item_id'])) {
    die("Invalid item_id.");
}
$item_id = (int)$_GET['item_id'];

// Find the auction based on the item_id
$sql = "
SELECT a.auctionID, a.endDate, a.startingPrice, a.status,
       i.itemName, i.description, i.sellerID
FROM auction a
JOIN item i ON a.itemID = i.itemID
WHERE i.itemID = ?
LIMIT 1
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$item_id]);
$auction = $stmt->fetch();

if (!$auction) die("Auction not found.");

// Create auction_id
$auction_id = $auction['auctionID'];
$title = $auction['itemName'];
$description = $auction['description'];
$end_time = new DateTime($auction['endDate']);
$status = $auction['status'];

// Query the current bid
$sql = "
SELECT MAX(bidAmount) AS max_bid, COUNT(*) AS num_bids
FROM bid
WHERE auctionID = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$auction_id]);
$row = $stmt->fetch();

$current_price = $row['max_bid'] ?? $auction['startingPrice'];
$num_bids = $row['num_bids'] ?? 0;

// Calculate the remaining time
$now = new DateTime();
$time_remaining = '';

if ($now < $end_time) {
    $time_to_end = date_diff($now, $end_time);
    $time_remaining = ' (in ' . display_time_remaining($time_to_end) . ')';
}

// Session and watchlist status
$has_session = isset($_SESSION['user_id']);
$watching = false;
$is_seller = false;
if ($has_session) {
    $stmt = $pdo->prepare("SELECT 1 FROM seller WHERE userID = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $is_seller = $stmt->fetch() ? true : false;
}

if ($has_session) {
    $buyer_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("
        SELECT 1 FROM watchlist
        WHERE buyerID=? AND auctionID=?
    ");
    $stmt->execute([$buyer_id, $auction_id]);
    $watching = $stmt->fetch() ? true : false;
}
?>

<div class="container">
<div class="row">
  <div class="col-sm-8">
    <h2 class="my-3"><?php echo htmlspecialchars($title); ?></h2>
    <p class="text-muted"><?php echo $num_bids; ?> bids so far</p>
  </div>

  <div class="col-sm-4 align-self-center">
<?php if ($now < $end_time && $status === 'active' && !$is_seller): ?>
    <div id="watch_nowatch" <?php if ($has_session && $watching) echo 'style="display:none"'; ?>>
      <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addToWatchlist()">+ Add to watchlist</button>
    </div>

    <div id="watch_watching" <?php if (!$has_session || !$watching) echo 'style="display:none"'; ?>>
      <button type="button" class="btn btn-success btn-sm" disabled>Watching</button>
      <button type="button" class="btn btn-danger btn-sm" onclick="removeFromWatchlist()">Remove watch</button>
    </div>
<?php endif ?>
  </div>
</div>

<div class="row">
<div class="col-sm-8">
  <div class="itemDescription">
    <?php echo nl2br(htmlspecialchars($description)); ?>
  </div>
</div>

<div class="col-sm-4">
<p>
<?php if ($now > $end_time): ?>

<?php
// AUTO END AUCTION + CREATE TRANSACTION 

// Check if the transaction exists
$stmt = $pdo->prepare("SELECT * FROM transaction WHERE auctionID=?");
$stmt->execute([$auction_id]);
$transaction = $stmt->fetch();

if (!$transaction) {

    $stmt = $pdo->prepare("
        SELECT buyerID, bidAmount
        FROM bid
        WHERE auctionID=?
        ORDER BY bidAmount DESC
        LIMIT 1
    ");
    $stmt->execute([$auction_id]);
    $winner = $stmt->fetch();

    if ($winner) {

        // transaction
        $stmt = $pdo->prepare("
            INSERT INTO transaction
            (auctionID, buyerID, finalPrice, date, status)
            VALUES (?, ?, ?, NOW(), 'COMPLETED')
        ");
        $stmt->execute([
            $auction_id,
            $winner['buyerID'],
            $winner['bidAmount']
        ]);

        // auction become ENDED
        $stmt = $pdo->prepare("
            UPDATE auction SET status='ENDED'
            WHERE auctionID=?
        ");
        $stmt->execute([$auction_id]);

        // reload transaction
        $stmt = $pdo->prepare("SELECT * FROM transaction WHERE auctionID=?");
        $stmt->execute([$auction_id]);
        $transaction = $stmt->fetch();
    }
}

// DISPLAY THE RESULT 
if ($transaction) {

    echo "<div class='alert alert-success'>
            Winner: Buyer #{$transaction['buyerID']}<br>
            Final Price: £{$transaction['finalPrice']}
          </div>";

} else {

    // Fallback：there is no transaction, watch the highest bid
    $stmt = $pdo->prepare("
        SELECT buyerID, bidAmount
        FROM bid
        WHERE auctionID = ?
        ORDER BY bidAmount DESC
        LIMIT 1
    ");
    $stmt->execute([$auction_id]);
    $winner = $stmt->fetch();

    if ($winner) {
        echo "<div class='alert alert-info'>
                Current highest bid by Buyer #{$winner['buyerID']}<br>
                £{$winner['bidAmount']}
              </div>";
    } else {
        echo "<div class='alert alert-warning'>
                No bids placed.
              </div>";
    }

}
?>

<?php else: ?>

  <p>Auction ends <?php echo date_format($end_time,'j M H:i') . $time_remaining; ?></p>
  <p class="lead">Current bid: £<?php echo number_format($current_price,2); ?></p>

<?php
if (isset($_SESSION['user_id'])) {

    $owner = $auction['sellerID'];

    if ($owner == $_SESSION['user_id'] && $status === 'active') {
?>
        <form method="POST" action="close_auction.php" class="mb-3">

            <input type="hidden" name="auction_id" value="<?php echo $auction_id; ?>">

            <!-- Button to cancel auction early -->
            <button type="submit" name="action" value="cancel"
                class="btn btn-danger form-control mb-2">
                Cancel Auction Early
            </button>

            <!-- Button to end and settle auction early -->
            <button type="submit" name="action" value="settle"
                class="btn btn-warning form-control">
                Settle Auction Early
            </button>

        </form>
<?php
    }
}
?>

<?php
// Only logged-in users who are NOT sellers can place bids
if ($has_session && !$is_seller && $status === 'active' && $now < $end_time):
?>
<form method="POST" action="place_bid.php">
  <div class="input-group">
    <div class="input-group-prepend">
      <span class="input-group-text">£</span>
    </div>

    <input type="number" name="bid_amount" step="0.01"
           class="form-control"
           min="<?php echo number_format($current_price + 0.01,2,'.',''); ?>"
           required>

    <input type="hidden" name="auction_id"
           value="<?php echo $auction_id; ?>">
  </div>

  <button type="submit" class="btn btn-primary form-control mt-2">
    Place bid
  </button>
</form>

<?php else: ?>
<div class="alert alert-info mt-3">
Please log in to place a bid.
</div>
<?php endif ?>

<?php endif ?>
</div>
</div>
</div>

<?php include_once("footer.php") ?>

<script>
function addToWatchlist() {
  $.ajax('watchlist_funcs.php', {
    type: 'POST',
    data: {
      functionname: 'add_to_watchlist',
      arguments: [<?php echo $auction_id; ?>]
    },
    success: function(obj) {
      // return result
      var text = (obj || "").toString().toLowerCase().trim();

      if (text.indexOf("success") !== -1) {
        // success
        $("#watch_nowatch").hide();
        $("#watch_watching").show();
      } else {
        // Add to watch failed
        var mydiv = document.getElementById("watch_nowatch");
        mydiv.appendChild(document.createElement("br"));
        mydiv.appendChild(
          document.createTextNode("Add to watch failed. Try again later.")
        );
      }
    },
    error: function() {
      // AJAX request failed due to iteself
      var mydiv = document.getElementById("watch_nowatch");
      mydiv.appendChild(document.createElement("br"));
      mydiv.appendChild(
        document.createTextNode("Add to watch request failed. Please try again later.")
      );
    }
  });
}

function removeFromWatchlist() {
  $.ajax('watchlist_funcs.php', {
    type: 'POST',
    data: {
      functionname: 'remove_from_watchlist',
      arguments: [<?php echo $auction_id; ?>]
    },
    success: function(obj) {
      var text = (obj || "").toString().toLowerCase().trim();

      if (text.indexOf("success") !== -1) {
        // when success
        $("#watch_watching").hide();
        $("#watch_nowatch").show();
      } else {
        // Remove from watch failed
        var mydiv = document.getElementById("watch_watching");
        mydiv.appendChild(document.createElement("br"));
        mydiv.appendChild(
          document.createTextNode("Remove from watch failed. Try again later.")
        );
      }
    },
    error: function() {
      // AJAX fail to request
      var mydiv = document.getElementById("watch_watching");
      mydiv.appendChild(document.createElement("br"));
      mydiv.appendChild(
        document.createTextNode("Remove from watch request failed. Please try again later.")
      );
    }
  });
}
</script>
