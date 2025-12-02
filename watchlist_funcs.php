<?php
require_once("utilities.php");
session_start();

header("Content-Type: text/plain; charset=utf-8");

if (!isset($_POST['functionname'], $_POST['arguments'])) {
    echo "missing_params";
    exit;
}


$arg = $_POST['arguments'];
if (is_array($arg)) {
    $auction_id = (int)$arg[0];
} else {
    $auction_id = (int)$arg;
}


$buyer_id = (int)$_SESSION['user_id']; 

$pdo = get_db();
$fn  = $_POST['functionname'];

if ($fn === "add_to_watchlist") {

    try {
        $stmt = $pdo->prepare("
            INSERT INTO watchlist (buyerID, auctionID, watchDate)
            VALUES (:bid, :aid, :wdate)
        ");
        $stmt->execute([
            ':bid'   => $buyer_id,
            ':aid'   => $auction_id,
            ':wdate' => date('Y-m-d H:i:s'),
        ]);

        echo "success";

    } catch (PDOException $e) {
        if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062) {
            echo "success";
        } else {
            echo "error";
        }
    }

} elseif ($fn === "remove_from_watchlist") {

    $stmt = $pdo->prepare("
        DELETE FROM watchlist
        WHERE buyerID = :bid AND auctionID = :aid
    ");
    $ok = $stmt->execute([
        ':bid' => $buyer_id,
        ':aid' => $auction_id,
    ]);

    echo $ok ? "success" : "error";

} else {
    echo "unknown_function";
}
