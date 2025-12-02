<?php
require("utilities.php");

// Get DB connection
$pdo = get_db();

/******************************************
 *   Read search parameters from URL
 ******************************************/
$keyword   = isset($_GET['keyword']) ? trim($_GET['keyword']) : "";
$category  = isset($_GET['cat']) ? $_GET['cat'] : "all";   // categoryID or "all"
$ordering  = isset($_GET['order_by']) ? $_GET['order_by'] : "pricelow";
$curr_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

/******************************************
 *   Fetch categories for the dropdown
 ******************************************/
$categories = [];
try {
    $cat_stmt = $pdo->query("SELECT categoryID, categoryName FROM category ORDER BY categoryName ASC");
    $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If there is an error, we simply show no categories in the dropdown
    $categories = [];
}

/******************************************
 *   Build WHERE clause for active auctions
 ******************************************/
$where  = "a.status = 'active' AND a.endDate > NOW()";
$params = [];

// Keyword search (itemName + description)
if ($keyword !== "") {
    $where .= " AND (i.itemName LIKE :kw OR i.description LIKE :kw)";
    $params[':kw'] = '%' . $keyword . '%';
}

// Category filter (by categoryID)
$categoryId = null;
if ($category !== "all" && $category !== "" && ctype_digit((string)$category)) {
    $categoryId = (int)$category;
    $where .= " AND i.categoryID = :cat";
    $params[':cat'] = $categoryId;
}

/******************************************
 *   Sorting
 ******************************************/
switch ($ordering) {
    case "pricelow":
        $order_sql = "current_price ASC";
        break;
    case "pricehigh":
        $order_sql = "current_price DESC";
        break;
    case "date":
    default:
        $order_sql = "a.endDate ASC";
        $ordering  = "date";
        break;
}

/******************************************
 *   Pagination settings
 ******************************************/
$results_per_page = 10;
$offset = ($curr_page - 1) * $results_per_page;

/******************************************
 *   Count total matching auctions
 ******************************************/
$count_sql = "
    SELECT COUNT(DISTINCT a.auctionID)
    FROM auction a
    JOIN item i ON a.itemID = i.itemID
    WHERE $where
";

$count_stmt = $pdo->prepare($count_sql);
foreach ($params as $name => $value) {
    // :kw is string, :cat is int
    $type = ($name === ':cat') ? PDO::PARAM_INT : PDO::PARAM_STR;
    $count_stmt->bindValue($name, $value, $type);
}
$count_stmt->execute();
$total_results = (int)$count_stmt->fetchColumn();
$max_page      = max(1, (int)ceil($total_results / $results_per_page));

/******************************************
 *   Fetch current page of results
 ******************************************/
$query = "
    SELECT
        a.auctionID,
        i.itemID,
        i.itemName,
        i.description,
        a.endDate,
        COALESCE(MAX(b.bidAmount), a.startingPrice) AS current_price,
        COUNT(b.bidID) AS num_bids
    FROM auction a
    JOIN item i ON a.itemID = i.itemID
    LEFT JOIN bid b ON b.auctionID = a.auctionID
    WHERE $where
    GROUP BY
        a.auctionID,
        i.itemID,
        i.itemName,
        i.description,
        a.endDate,
        a.startingPrice
    ORDER BY $order_sql
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($query);

// Bind search parameters again
foreach ($params as $name => $value) {
    $type = ($name === ':cat') ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stmt->bindValue($name, $value, $type);
}

// Bind pagination parameters
$stmt->bindValue(':limit',  $results_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,           PDO::PARAM_INT);

$stmt->execute();
$rows        = $stmt->fetchAll(PDO::FETCH_ASSOC);
$num_results = count($rows);

include_once("header.php");
?>

<div class="container">

  <h2 class="my-3">Browse listings</h2>

  <div id="searchSpecs">
    <!-- Search form -->
    <form method="get" action="browse.php">
      <div class="row">
        <!-- Keyword -->
        <div class="col-md-5 pr-0">
          <div class="form-group">
            <label for="keyword" class="sr-only">Search keyword:</label>
            <div class="input-group">
              <span class="input-group-text bg-transparent pr-0 text-muted">
                <i class="fa fa-search"></i>
              </span>
              <input
                type="text"
                class="form-control border-left-0"
                id="keyword"
                name="keyword"
                placeholder="Search for anything"
                value="<?php echo isset($_GET['keyword']) ? htmlspecialchars($_GET['keyword']) : ''; ?>"
              >
            </div>
          </div>
        </div>

        <!-- Category (from category table) -->
        <div class="col-md-3 pr-0">
          <div class="form-group">
            <label for="cat" class="sr-only">Category:</label>
            <select class="form-control" id="cat" name="cat">
              <option value="all"<?php echo ($category === "all" ? " selected" : ""); ?>>
                All categories
              </option>
              <?php foreach ($categories as $catRow): ?>
                <?php
                  $catId   = (int)$catRow['categoryID'];
                  $catName = htmlspecialchars($catRow['categoryName']);
                  $selected = ((string)$category === (string)$catId) ? " selected" : "";
                ?>
                <option value="<?php echo $catId; ?>"<?php echo $selected; ?>>
                  <?php echo $catName; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Ordering -->
        <div class="col-md-2 pr-0">
          <div class="form-group">
            <label for="order_by" class="sr-only">Sort by:</label>
            <select class="form-control" id="order_by" name="order_by">
              <option value="pricelow"<?php echo ($ordering === "pricelow" ? " selected" : ""); ?>>
                Price (low to high)
              </option>
              <option value="pricehigh"<?php echo ($ordering === "pricehigh" ? " selected" : ""); ?>>
                Price (high to low)
              </option>
              <option value="date"<?php echo ($ordering === "date" ? " selected" : ""); ?>>
                Ending soon
              </option>
            </select>
          </div>
        </div>

        <!-- Search button -->
        <div class="col-md-2">
          <button type="submit" class="btn btn-primary btn-block">Search</button>
        </div>
      </div>
    </form>
  </div>

  <div class="mt-4 mb-2">
    <?php if ($total_results > 0): ?>
      <p>Found <strong><?php echo htmlspecialchars($total_results); ?></strong> listings.</p>
    <?php else: ?>
      <p>No listings found.</p>
    <?php endif; ?>
  </div>

  <ul class="list-group">
    <?php
    if ($num_results === 0) {
        echo "<li class='list-group-item'>No listings found.</li>";
    } else {
        foreach ($rows as $row) {
            $auction_id    = $row['auctionID'];      // Treated as "listing id"
            $item_id       = $row['itemID'];
            $title         = $row['itemName'];
            $description   = $row['description'];
            $current_price = (float)$row['current_price'];
            $num_bids      = (int)$row['num_bids'];
            $end_date      = new DateTime($row['endDate']);

            // We pass auctionID into print_listing_li, which links to listing.php?item_id=...
            print_listing_li($auction_id, $title, $description, $current_price, $num_bids, $end_date);
        }
    }
    ?>
  </ul>

  <!-- Pagination -->
  <?php if ($total_results > 0): ?>
    <nav aria-label="Search results pages" class="mt-5">
      <ul class="pagination justify-content-center">
        <?php
        // Build query string without the "page" parameter
        $qs_array = $_GET;
        unset($qs_array['page']);
        $querystring = http_build_query($qs_array);
        if ($querystring !== '') {
            $querystring .= '&';
        }
        $qs_html = htmlspecialchars($querystring, ENT_QUOTES);

        // Show a window of pages around the current page
        $page_window = 2;
        $low_page  = max(1, $curr_page - $page_window);
        $high_page = min($max_page, $curr_page + $page_window);

        // Previous button
        if ($curr_page > 1) {
            echo '
            <li class="page-item">
              <a class="page-link" href="browse.php?' . $qs_html . 'page=' . ($curr_page - 1) . '" aria-label="Previous">
                <span aria-hidden="true"><i class="fa fa-arrow-left"></i></span>
                <span class="sr-only">Previous</span>
              </a>
            </li>';
        }

        // Page numbers
        for ($i = $low_page; $i <= $high_page; $i++) {
            $active = ($i == $curr_page) ? " active" : "";
            echo '
            <li class="page-item' . $active . '">
              <a class="page-link" href="browse.php?' . $qs_html . 'page=' . $i . '">' . $i . '</a>
            </li>';
        }

        // Next button
        if ($curr_page < $max_page) {
            echo '
            <li class="page-item">
              <a class="page-link" href="browse.php?' . $qs_html . 'page=' . ($curr_page + 1) . '" aria-label="Next">
                <span aria-hidden="true"><i class="fa fa-arrow-right"></i></span>
                <span class="sr-only">Next</span>
              </a>
            </li>';
        }
        ?>
      </ul>
    </nav>
  <?php endif; ?>

</div>

<?php include_once("footer.php"); ?>
