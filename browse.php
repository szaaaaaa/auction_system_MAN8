<?php
include_once("header.php");
require("utilities.php");
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
              <input type="text"
                     class="form-control border-left-0"
                     id="keyword"
                     name="keyword"
                     placeholder="Search for anything"
                     value="<?php echo isset($_GET['keyword']) ? htmlspecialchars($_GET['keyword']) : ''; ?>">
            </div>
          </div>
        </div>

        <!-- Category -->
        <div class="col-md-3 pr-0">
          <div class="form-group">
            <label for="cat" class="sr-only">Search within:</label>
            <select class="form-control" id="cat" name="cat">
              <option value="all" <?php echo (!isset($_GET['cat']) || $_GET['cat'] === 'all') ? 'selected' : ''; ?>>All categories</option>
              <option value="fill" <?php echo (isset($_GET['cat']) && $_GET['cat'] === 'fill') ? 'selected' : ''; ?>>Fill me in</option>
              <option value="with" <?php echo (isset($_GET['cat']) && $_GET['cat'] === 'with') ? 'selected' : ''; ?>>with options</option>
              <option value="populated" <?php echo (isset($_GET['cat']) && $_GET['cat'] === 'populated') ? 'selected' : ''; ?>>populated from a database?</option>
            </select>
          </div>
        </div>

        <!-- Ordering -->
        <div class="col-md-3 pr-0">
          <div class="form-inline">
            <label class="mx-2" for="order_by">Sort by:</label>
            <select class="form-control" id="order_by" name="order_by">
              <option value="pricelow"  <?php echo (!isset($_GET['order_by']) || $_GET['order_by'] === 'pricelow') ? 'selected' : ''; ?>>Price (low to high)</option>
              <option value="pricehigh" <?php echo (isset($_GET['order_by']) && $_GET['order_by'] === 'pricehigh') ? 'selected' : ''; ?>>Price (high to low)</option>
              <option value="date"      <?php echo (isset($_GET['order_by']) && $_GET['order_by'] === 'date') ? 'selected' : ''; ?>>Soonest expiry</option>
            </select>
          </div>
        </div>

        <!-- Submit -->
        <div class="col-md-1 px-0">
          <button type="submit" class="btn btn-primary">Search</button>
        </div>
      </div>
    </form>
  </div> <!-- end search specs bar -->

</div>

<?php
/* =============================
   Read parameters from the URL
   ============================= */

// Keyword
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : "";

// Category
$category = isset($_GET['cat']) ? $_GET['cat'] : "all";

// Ordering
$ordering = isset($_GET['order_by']) ? $_GET['order_by'] : "pricelow";

// Current page
$curr_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

/* =============================
   Build and execute PDO query
   ============================= */

// Get PDO connection from utilities.php
$pdo = get_db();

// Base WHERE clause: only active auctions that have not yet ended
$where  = "status = 'active' AND end_date > NOW()";
$params = [];

// Keyword filter
if ($keyword !== "") {
  $where .= " AND (title LIKE :kw OR description LIKE :kw)";
  $params[':kw'] = "%{$keyword}%";
}

// Category filter
if ($category !== "all") {
  $where .= " AND category = :cat";
  $params[':cat'] = $category;
}

// Sorting rules
switch ($ordering) {
  case "pricelow":
    $order_sql = "current_price ASC";
    break;
  case "pricehigh":
    $order_sql = "current_price DESC";
    break;
  case "date":
  default:
    $order_sql = "end_date ASC";
    $ordering  = "date";
    break;
}

// Pagination setup
$results_per_page = 10;
$offset = ($curr_page - 1) * $results_per_page;

// Main query to get current page of results
$query = "
  SELECT item_id, title, description, current_price, end_date
  FROM Items
  WHERE $where
  ORDER BY $order_sql
  LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($query);

// Bind search parameters
foreach ($params as $name => $value) {
  $stmt->bindValue($name, $value, PDO::PARAM_STR);
}

// Bind pagination parameters
$stmt->bindValue(':limit',  (int)$results_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset,          PDO::PARAM_INT);

$stmt->execute();
$rows = $stmt->fetchAll();

// Query to count total number of matching results
$count_query = "SELECT COUNT(*) AS total FROM Items WHERE $where";
$count_stmt  = $pdo->prepare($count_query);

foreach ($params as $name => $value) {
  $count_stmt->bindValue($name, $value, PDO::PARAM_STR);
}

$count_stmt->execute();
$num_results = (int)$count_stmt->fetchColumn();

$max_page = max(1, (int)ceil($num_results / $results_per_page));
?>

<div class="container mt-5">

  <ul class="list-group">
    <?php
    if ($num_results === 0) {
      echo "<li class='list-group-item'>No listings found.</li>";
    } else {
      foreach ($rows as $row) {
        $item_id       = $row['item_id'];
        $title         = $row['title'];
        $description   = $row['description'];
        $current_price = $row['current_price'];
        $num_bids      = 0; // placeholder
        $end_date      = new DateTime($row['end_date']);

        print_listing_li($item_id, $title, $description, $current_price, $num_bids, $end_date);
      }
    }
    ?>
  </ul>

  <!-- Pagination -->
  <nav aria-label="Search results pages" class="mt-5">
    <ul class="pagination justify-content-center">
      <?php
      // Build querystring without "page"
      $qs_array = $_GET;
      unset($qs_array['page']);
      $querystring = http_build_query($qs_array);
      if ($querystring !== "") {
        $querystring .= '&';
      }

      // Previous
      if ($curr_page > 1) {
        echo '
        <li class="page-item">
          <a class="page-link" href="browse.php?' . htmlspecialchars($querystring) . 'page=' . ($curr_page - 1) . '" aria-label="Previous">
            <span aria-hidden="true"><i class="fa fa-arrow-left"></i></span>
            <span class="sr-only">Previous</span>
          </a>
        </li>';
      }

      // Numbered pages
      $high_page_boost = max(3 - $curr_page, 0);
      $low_page_boost  = max(2 - ($max_page - $curr_page), 0);
      $low_page  = max(1, $curr_page - 2 - $low_page_boost);
      $high_page = min($max_page, $curr_page + 2 + $high_page_boost);

      for ($i = $low_page; $i <= $high_page; $i++) {
        if ($i == $curr_page) {
          echo '<li class="page-item active">';
        } else {
          echo '<li class="page-item">';
        }

        echo '
          <a class="page-link" href="browse.php?' . htmlspecialchars($querystring) . 'page=' . $i . '">' . $i . '</a>
        </li>';
      }

      // Next
      if ($curr_page < $max_page) {
        echo '
        <li class="page-item">
          <a class="page-link" href="browse.php?' . htmlspecialchars($querystring) . 'page=' . ($curr_page + 1) . '" aria-label="Next">
            <span aria-hidden="true"><i class="fa fa-arrow-right"></i></span>
            <span class="sr-only">Next</span>
          </a>
        </li>';
      }
      ?>
    </ul>
  </nav>

</div>

<?php include_once("footer.php"); ?>
