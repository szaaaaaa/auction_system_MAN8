 <?php
session_start();
require_once('utilities.php');

if (!isset($_POST['functionname']) || !isset($_POST['arguments'])) {
  return;
}
$pdo = get_db();
$auction_id = $_POST['arguments'][0];
$buyer_id = $_SESSION['userID'] ?? null;
$res = "error";


if ($_POST['functionname'] == "add_to_watchlist") {
  // TODO: Update database and return success/failure.
try {
    $sql = "INSERT IGNORE INTO watchlist (buyerID, auctionID, watchDate) VALUES (:buyerID, :auctionID, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':buyerID' => $buyer_id, ':auctionID' => $auction_id]);
    $res = "success";
  } catch (PDOException $e) {
    $res = "Error: " . $e->getMessage();
  }
}
else if ($_POST['functionname'] == "remove_from_watchlist") {
  // TODO: Update database and return success/failure.
try {
    $sql = "DELETE FROM watchlist WHERE buyerID = :buyerID AND auctionID = :auctionID";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':buyerID' => $buyer_id, ':auctionID' => $auction_id]);
    $res = "success";
  } catch (PDOException $e) {
     $res = "Error: " . $e->getMessage();
  }

} else {
    $res = "Please login first";
}


// Note: Echoing from this PHP function will return the value as a string.
// If multiple echo's in this file exist, they will concatenate together,
// so be careful. You can also return JSON objects (in string form) using
// echo json_encode($res).
echo $res;

?>