<?php
require 'email-utility.php';

sendEmail("test@example.com", "Mailtrap Test", "<h2>Hello! Mailtrap Works!</h2>");
echo "Mail sent (check Mailtrap)";
