<?php
/**
 * Gate Router - Routes each gate to its appropriate dedicated page
 * Based on gate type and key
 */

function getGatePage($gateKey, $gateType = 'auto_checker') {
    // Define routing for different gate types
    $routes = [
        // Key Based Gates
        'key_stripe' => '/pages/checker/key-stripe.php',
        'key_paypal' => '/pages/checker/key-paypal.php',
        
        // Auto Checkers
        'shopify' => '/pages/checker/shopify.php',
        'stripe_auth' => '/pages/checker/stripe-auth.php',
        'razorpay' => '/pages/checker/razorpay.php',
        
        // Checkers
        'auth' => '/pages/checker/auth.php',
        'charge' => '/pages/checker/charge.php',
        'auth_charge' => '/pages/checker/auth-charge.php',
        
        // Hitters
        'stripe_checkout' => '/pages/checker/stripe-checkout.php',
        'stripe_invoice' => '/pages/checker/stripe-invoice.php',
        'stripe_inbuilt' => '/pages/checker/stripe-inbuilt.php',
        
        // Tools
        'address_gen' => '/pages/tools/address-gen.php',
        'bin_lookup' => '/pages/tools/bin-lookup.php',
        'cc_cleaner' => '/pages/tools/cc-cleaner.php',
        'cc_generator' => '/pages/tools/cc-generator.php',
        'proxy_checker' => '/pages/tools/proxy-checker.php',
        'vbv_checker' => '/pages/tools/vbv-checker.php',
        
        // Preferences
        'proxies' => '/pages/preferences/proxies.php',
        'assets' => '/pages/preferences/assets.php'
    ];
    
    // Check if gate has a dedicated page
    if (isset($routes[$gateKey])) {
        return $routes[$gateKey];
    }
    
    // For custom gates or unknown gates, use universal checker
    return '/pages/checker/universal.php?gate=' . urlencode($gateKey);
}

// Helper to generate gate link
function getGateLink($gateKey, $gateType = 'auto_checker') {
    $page = getGatePage($gateKey, $gateType);
    
    // If it's universal with params
    if (strpos($page, 'universal.php') !== false) {
        return $page;
    }
    
    // For dedicated pages, just return the page
    return $page;
}
?>
