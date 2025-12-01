<?php
// ===============================
// Database configuration
// ===============================
$DB_HOST = 'localhost';
$DB_NAME = 'auction_db';   // <-- database name you created in phpMyAdmin
$DB_USER = 'root';
$DB_PASS = '';             // XAMPP default password is empty ("")

// Return a shared PDO connection
function get_db(): PDO {
    static $pdo = null;
    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;

    if ($pdo === null) {
        $dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4";
        $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}


// ===============================
// Helper: display_time_remaining
// ===============================
function display_time_remaining($interval) {

    if ($interval->days == 0 && $interval->h == 0) {
        // Less than one hour remaining: mins + seconds
        $time_remaining = $interval->format('%im %Ss');
    } else if ($interval->days == 0) {
        // Less than one day remaining: hours + mins
        $time_remaining = $interval->format('%hh %im');
    } else {
        // At least one day remaining: days + hours
        $time_remaining = $interval->format('%ad %hh');
    }

    return $time_remaining;
}


// ===============================
// Helper: print_listing_li
// ===============================
function print_listing_li($item_id, $title, $desc, $price, $num_bids, $end_time)
{
    // Truncate long descriptions
    if (strlen($desc) > 250) {
        $desc_shortened = substr($desc, 0, 250) . '...';
    } else {
        $desc_shortened = $desc;
    }

    // Fix language of bid vs. bids
    if ($num_bids == 1) {
        $bid = ' bid';
    } else {
        $bid = ' bids';
    }

    // Calculate time to auction end
    $now = new DateTime();
    if ($now > $end_time) {
        $time_remaining = 'This auction has ended';
    } else {
        $time_to_end    = date_diff($now, $end_time);
        $time_remaining = display_time_remaining($time_to_end) . ' remaining';
    }

    // Print HTML
    echo '
    <li class="list-group-item d-flex justify-content-between">
        <div class="p-2 mr-5">
            <h5><a href="listing.php?item_id=' . (int)$item_id . '">' . htmlspecialchars($title) . '</a></h5>
            ' . htmlspecialchars($desc_shortened) . '
        </div>
        <div class="text-center text-nowrap">
            <span style="font-size: 1.5em">£' . number_format($price, 2) . '</span><br/>
            ' . (int)$num_bids . $bid . '<br/>
            ' . $time_remaining . '
        </div>
    </li>';
}
?>
