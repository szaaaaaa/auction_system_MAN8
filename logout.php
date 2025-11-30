<?php
session_start();

$_SESSION = [];
setcookie(session_name(), "", time() - 360);
session_destroy();
header("Location: index.php");
exit;