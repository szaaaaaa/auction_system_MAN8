<?php
// test_winner_email.php
require_once "email-utility.php";

$buyerEmail = "Testbuyer11@example.com";   // ← 改成你真实邮箱
$buyerName  = "Testbuyer11@example.com";

$itemName   = "Test9";
$finalPrice = "&pound;50";

$subject = "Congratulations! You won the auction for {$itemName}";

$body = "
    <h2>Congratulations, {$buyerName}!</h2>
    <p>You have won the auction for:</p>
    <h3>{$itemName}</h3>
    <p>Your winning bid was: <b>{$finalPrice}</b></p>
    <br>
    <p>Thank you for participating in our auction platform.</p>
    <p>Please check your account for more details.</p>
";

$result = sendEmail($buyerEmail, $subject, $body);

if ($result) {
    echo "Winner email sent successfully!";
} else {
    echo "Email failed. Check logs for details.";
}
?>
