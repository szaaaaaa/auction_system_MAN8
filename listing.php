<?php
require_once "utilities.php";
$pdo = get_db();
include_once("header.php");
?>

<?php
  // Get info from the URL:
  $item_id = $_GET['item_id'];

  // TODO: Use item_id to make a query to the database.

  // DELETEME: For now, using placeholder data.
$sql = "
SELECT A.auctionID, A.endDate, A.startingPrice,
       I.itemName, I.description
FROM Auction A
JOIN Item I ON A.itemID = I.itemID
WHERE A.auctionID = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$item_id]);
$auction = $stmt->fetch();

if (!$auction) die("Auction not found.");

$title = $auction['itemName'];
$description = $auction['description'];
$end_time = new DateTime($auction['endDate']);

// 获取当前出价
$sql = "
SELECT MAX(bidAmount) AS max_bid, COUNT(*) AS num_bids
FROM Bid WHERE auctionID = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$item_id]);
$row = $stmt->fetch();

$current_price = $row['max_bid'] ?? $auction['startingPrice'];
$num_bids = $row['num_bids'];

  // TODO: Note: Auctions that have ended may pull a different set of data,
  //       like whether the auction ended in a sale or was cancelled due
  //       to lack of high-enough bids. Or maybe not.
  
  // Calculate time to auction end:
  $now = new DateTime();
  
  if ($now < $end_time) {
    $time_to_end = date_diff($now, $end_time);
    $time_remaining = ' (in ' . display_time_remaining($time_to_end) . ')';
  }
  
  // TODO: If the user has a session, use it to make a query to the database
  //       to determine if the user is already watching this item.
  //       For now, this is hardcoded.
  $has_session = true;
  $watching = false;
?>


<div class="container">

<div class="row"> <!-- Row #1 with auction title + watch button -->
  <div class="col-sm-8"> <!-- Left col -->
    <h2 class="my-3"><?php echo($title); ?></h2>
  </div>
  <div class="col-sm-4 align-self-center"> <!-- Right col -->
<?php
  /* The following watchlist functionality uses JavaScript, but could
     just as easily use PHP as in other places in the code */
  if ($now < $end_time):
?>
    <div id="watch_nowatch" <?php if ($has_session && $watching) echo('style="display: none"');?> >
      <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addToWatchlist()">+ Add to watchlist</button>
    </div>
    <div id="watch_watching" <?php if (!$has_session || !$watching) echo('style="display: none"');?> >
      <button type="button" class="btn btn-success btn-sm" disabled>Watching</button>
      <button type="button" class="btn btn-danger btn-sm" onclick="removeFromWatchlist()">Remove watch</button>
    </div>
<?php endif /* Print nothing otherwise */ ?>
  </div>
</div>

<div class="row"> <!-- Row #2 with auction description + bidding info -->
  <div class="col-sm-8"> <!-- Left col with item info -->

    <div class="itemDescription">
    <?php echo($description); ?>
    </div>

  </div>

  <div class="col-sm-4"> <!-- Right col with bidding info -->

    <p>
<?php if ($now > $end_time): ?>

<?php
// ===== AUTO END AUCTION & GENERATE TRANSACTION =====

// 查询是否已有 Transaction
$stmt = $pdo->prepare("SELECT * FROM Transaction WHERE auctionID=?");
$stmt->execute([$item_id]);
$transaction = $stmt->fetch();

if (!$transaction) {

    // 找最高 bid
    $stmt = $pdo->prepare("
        SELECT buyerID, bidAmount
        FROM Bid
        WHERE auctionID = ?
        ORDER BY bidAmount DESC
        LIMIT 1
    ");
    $stmt->execute([$item_id]);
    $winner = $stmt->fetch();

    if ($winner) {

        // 创建 Transaction
        $stmt = $pdo->prepare("
            INSERT INTO Transaction 
            (auctionID, buyerID, finalPrice, date, status)
            VALUES (?, ?, ?, NOW(), 'COMPLETED')
        ");
        $stmt->execute([
            $item_id,
            $winner['buyerID'],
            $winner['bidAmount']
        ]);

        // 更新 Auction 为 ENDED
        $stmt = $pdo->prepare("UPDATE Auction SET status='ENDED' WHERE auctionID=?");
        $stmt->execute([$item_id]);
    }
}

// ===== DISPLAY RESULT =====
// ===== DISPLAY RESULT =====

if ($transaction) {

    // ✅ 核心原则：有 Transaction，就以它为权威结果
    echo "<div class='alert alert-success'>
            Winner: Buyer #{$transaction['buyerID']}<br>
            Final Price: £{$transaction['finalPrice']}
          </div>";

}
else {

    // 没有 Transaction，才去 Bid 表推测当前 winner
    $stmt = $pdo->prepare("
        SELECT buyerID, bidAmount
        FROM Bid
        WHERE auctionID = ?
        ORDER BY bidAmount DESC
        LIMIT 1
    ");
    $stmt->execute([$item_id]);
    $winner = $stmt->fetch();

    if ($winner) {
        echo "<div class='alert alert-info'>
                Current highest bid by Buyer #{$winner['buyerID']}<br>
                £{$winner['bidAmount']}
              </div>";
    }
    else {
        echo "<div class='alert alert-warning'>
                No bids yet.
              </div>";
    }

}
?>

<?php else: ?>

    <p>Auction ends <?php echo(date_format($end_time, 'j M H:i') . $time_remaining) ?></p>  
    <p class="lead">Current bid: £<?php echo(number_format($current_price, 2)) ?></p>

    <!-- Bidding form -->
    <form method="POST" action="place_bid.php">
      <div class="input-group">
        <div class="input-group-prepend">
          <span class="input-group-text">£</span>
        </div>
	    <input type="number"
       class="form-control"
       name="bid_amount"
       step="0.01"
       min="<?php echo($current_price + 0.01); ?>"
       required>

<input type="hidden"
       name="auction_id"
       value="<?php echo($item_id); ?>">

      </div>
      <button type="submit" class="btn btn-primary form-control">Place bid</button>
    </form>

<?php endif ?>

  
  </div> <!-- End of right col with bidding info -->

</div> <!-- End of row #2 -->



<?php include_once("footer.php")?>


<script> 
// JavaScript functions: addToWatchlist and removeFromWatchlist.

function addToWatchlist(button) {
  console.log("These print statements are helpful for debugging btw");

  // This performs an asynchronous call to a PHP function using POST method.
  // Sends item ID as an argument to that function.
  $.ajax('watchlist_funcs.php', {
    type: "POST",
    data: {functionname: 'add_to_watchlist', arguments: [<?php echo($item_id);?>]},

    success: 
      function (obj, textstatus) {
        // Callback function for when call is successful and returns obj
        console.log("Success");
        var objT = obj.trim();
 
        if (objT == "success") {
          $("#watch_nowatch").hide();
          $("#watch_watching").show();
        }
        else {
          var mydiv = document.getElementById("watch_nowatch");
          mydiv.appendChild(document.createElement("br"));
          mydiv.appendChild(document.createTextNode("Add to watch failed. Try again later."));
        }
      },

    error:
      function (obj, textstatus) {
        console.log("Error");
      }
  }); // End of AJAX call

} // End of addToWatchlist func

function removeFromWatchlist(button) {
  // This performs an asynchronous call to a PHP function using POST method.
  // Sends item ID as an argument to that function.
  $.ajax('watchlist_funcs.php', {
    type: "POST",
    data: {functionname: 'remove_from_watchlist', arguments: [<?php echo($item_id);?>]},

    success: 
      function (obj, textstatus) {
        // Callback function for when call is successful and returns obj
        console.log("Success");
        var objT = obj.trim();
 
        if (objT == "success") {
          $("#watch_watching").hide();
          $("#watch_nowatch").show();
        }
        else {
          var mydiv = document.getElementById("watch_watching");
          mydiv.appendChild(document.createElement("br"));
          mydiv.appendChild(document.createTextNode("Watch removal failed. Try again later."));
        }
      },

    error:
      function (obj, textstatus) {
        console.log("Error");
      }
  }); // End of AJAX call

} // End of addToWatchlist func
</script>