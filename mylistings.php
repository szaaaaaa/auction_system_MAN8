<?php 
include_once("header.php");
require_once("utilities.php");

$pdo = get_db();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    die("<div class='container'><div class='alert alert-danger mt-3'>Please log in to view your listings.</div></div>");
}

$user_id = $_SESSION['user_id'];

// Verify this user is a seller
$stmt = $pdo->prepare("SELECT 1 FROM seller WHERE userID = ?");
$stmt->execute([$user_id]);
$is_seller = $stmt->fetch();

if (!$is_seller) {
    die("<div class='container'><div class='alert alert-warning mt-3'>You are not registered as a seller.</div></div>");
}

// Fetch all auctions created by this seller
$sql = "
    SELECT 
        a.auctionID, 
        a.endDate, 
        a.startingPrice,
        a.status,
        i.itemID,
        i.itemName,
        i.description,
        (
            SELECT MAX(bidAmount) 
            FROM bid 
            WHERE auctionID = a.auctionID
        ) AS highest_bid
    FROM auction a
    JOIN item i ON a.itemID = i.itemID
    WHERE i.sellerID = ?
    ORDER BY a.endDate DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$auctions = $stmt->fetchAll();

?>

<div class="container mt-4">

<h2>My Listings</h2>
<hr>

<?php if (empty($auctions)) : ?>

    <div class="alert alert-info">You have not created any auctions yet.</div>

<?php else: ?>

    <ul class="list-group">

    <?php foreach ($auctions as $a): ?>

        <li class="list-group-item d-flex justify-content-between">
            <div>
                <h5>
                    <a href="listing.php?item_id=<?= $a['itemID'] ?>">
                        <?= htmlspecialchars($a['itemName']) ?>
                    </a>
                </h5>

                <div><?= htmlspecialchars(substr($a['description'], 0, 120)) ?>...</div>

                <div class="text-muted">
                    Status: <?= htmlspecialchars($a['status']) ?>
                </div>
            </div>

            <div class="text-right">
                <strong>
                    Current Price: £<?= number_format($a['highest_bid'] ?? $a['startingPrice'], 2) ?>
                </strong>
                <br>
                <?php 
                    $end = new DateTime($a['endDate']);
                    $now = new DateTime();
                    echo ($now > $end) 
                        ? "<span class='text-danger'>Ended</span>" 
                        : format_time_remaining($a['endDate']);
                ?>
            </div>
        </li>

    <?php endforeach; ?>

    </ul>

<?php endif; ?>

</div>

<?php include_once("footer.php"); ?>