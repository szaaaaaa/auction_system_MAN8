<?php include_once("header.php"); ?>
<?php require("utilities.php"); ?>

<div class="container">

<h2 class="my-3">Browse listings</h2>

<div id="searchSpecs">
<!-- When this form is submitted, this PHP page is what processes it.
     Search/sort specs are passed to this page through parameters in the URL
     (GET method of passing data to a page). -->
<form method="get" action="browse.php">
  <div class="row">
    <div class="col-md-5 pr-0">
      <div class="form-group">
        <label for="keyword" class="sr-only">Search keyword:</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text bg-transparent pr-0 text-muted">
              <i class="fa fa-search"></i>
            </span>
          </div>
          <!-- add name="keyword" so it appears in $_GET -->
          <input type="text" class="form-control border-left-0"
                 id="keyword" name="keyword" placeholder="Search for anything">
        </div>
      </div>
    </div>
    <div class="col-md-3 pr-0">
      <div class="form-group">
        <label for="cat" class="sr-only">Search within:</label>
        <!-- add name="cat" -->
        <select class="form-control" id="cat" name="cat">
          <option selected value="all">All categories</option>
          <option value="fill">Fill me in</option>
          <option value="with">with options</option>
          <option value="populated">populated from a database?</option>
        </select>
      </div>
    </div>
    <div class="col-md-3 pr-0">
      <div class="form-inline">
        <label class="mx-2" for="order_by">Sort by:</label>
        <!-- add name="order_by" -->
        <select class="form-control" id="order_by" name="order_by">
          <option selected value="pricelow">Price (low to high)</option>
          <option value="pricehigh">Price (high to low)</option>
          <option value="date">Soonest expiry</option>
        </select>
      </div>
    </div>
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
  if (!isset($_GET['keyword'])) {
    $keyword = "";
  } else {
    $keyword = trim($_GET['keyword']);
  }

  // Category
  if (!isset($_GET['cat'])) {
    $category = "all";
  } else {
    $category = $_GET['cat'];
  }
  
  // Ordering
  if (!isset($_GET['order_by'])) {
    $ordering = "date";
  } else {
    $ordering = $_GET['order_by'];
  }
  
  // Current page
  if (!isset($_GET['page'])) {
    $curr_page = 1;
  } else {
    $curr_page = max(1, intval($_GET['page']));
  }

  /* =============================
     Build and execute PDO query
     ============================= */

  // Use the global PDO connection (defined in header/utilities)
  global $pdo;

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
    default: // soonest expiry
      $order_sql = "end_date ASC";
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

  // Bind search parameters (:kw, :cat)
  foreach ($params as $name => $value) {
    $stmt->bindValue($name, $value, PDO::PARAM_STR);
  }

  // Bind pagination parameters
  $stmt->bindValue(':limit',  (int)$results_per_page, PDO::PARAM_INT);
  $stmt->bindValue(':offset', (int)$offset,          PDO::PARAM_INT);

  $stmt->execute();
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Query to count total number of matching results
  $count_query = "SELECT COUNT(*) AS total FROM Items WHERE $where";
  $count_stmt  = $pdo->prepare($count_query);

  foreach ($params as $name => $value) {
    $count_stmt->bindValue($name, $value, PDO::PARAM_STR);
  }

  $count_stmt->execute();
  $num_results = (int)$count_stmt->fetchColumn();

  $max_page = max(1, ceil($num_results / $results_per_page));
?>

<div class="container mt-5">

<!-- If result set is empty, print an informative message. Otherwise... -->

<ul class="list-group">

<?php
  if ($num_results == 0) {
    echo "<li class='list-group-item'>No listings found.</li>";
  } else {
    // Loop through query results and print each listing
    foreach ($rows as $row) {
      $item_id       = $row['item_id'];
      $title         = $row['title'];
      $description   = $row['description'];
      $current_price = $row['current_price'];
      $num_bids      = 0; // or use get_num_bids($item_id) if implemented
      $end_date      = new DateTime($row['end_date']);

      // This uses a function defined in utilities.php
      print_listing_li($item_id, $title, $description, $current_price, $num_bids, $end_date);
    }
  }
?>

</ul>

<!-- Pagination for results listings -->
<nav aria-label="Search results pages" class="mt-5">
  <ul class="pagination justify-content-center">
  
<?php

  // Copy any currently-set GET variables to the URL.
  $querystring = "";
  foreach ($_GET as $key => $value) {
    if ($key != "page") {
      $querystring .= "$key=$value&amp;";
    }
  }
  
  $high_page_boost = max(3 - $curr_page, 0);
  $low_page_boost  = max(2 - ($max_page - $curr_page), 0);
  $low_page  = max(1, $curr_page - 2 - $low_page_boost);
  $high_page = min($max_page, $curr_page + 2 + $high_page_boost);
  
  if ($curr_page != 1) {
    echo('
    <li class="page-item">
      <a class="page-link" href="browse.php?' . $querystring . 'page=' . ($curr_page - 1) . '" aria-label="Previous">
        <span aria-hidden="true"><i class="fa fa-arrow-left"></i></span>
        <span class="sr-only">Previous</span>
      </a>
    </li>');
  }
    
  for ($i = $low_page; $i <= $high_page; $i++) {
    if ($i == $curr_page) {
      // Highlight the link
      echo('
    <li class="page-item active">');
    } else {
      // Non-highlighted link
      echo('
    <li class="page-item">');
    }
    
    // Do this in any case
    echo('
      <a class="page-link" href="browse.php?' . $querystring . 'page=' . $i . '">' . $i . '</a>
    </li>');
  }
  
  if ($curr_page != $max_page) {
    echo('
    <li class="page-item">
      <a class="page-link" href="browse.php?' . $querystring . 'page=' . ($curr_page + 1) . '" aria-label="Next">
        <span aria-hidden="true"><i class="fa fa-arrow-right"></i></span>
        <span class="sr-only">Next</span>
      </a>
    </li>');
  }
?>

  </ul>
</nav>

</div>

<?php include_once("footer.php"); ?>
