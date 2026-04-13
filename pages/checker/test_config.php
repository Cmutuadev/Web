<?php
require_once __DIR__ . "/../../includes/config.php";
echo "Config loaded successfully\n";
echo "Logged in: " . (isLoggedIn() ? "Yes" : "No") . "\n";
echo "Credits: " . getUserCredits() . "\n";
$gates = loadGates();
echo "Total gates: " . count($gates) . "\n";
?>
