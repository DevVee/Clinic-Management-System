<?php
// /system/dashboard_config.php

// Configuration for dashboard stat cards
$dashboard_config = [
    'stat_cards' => [
        'patients' => true,          // Total Population
        'visits_today' => true,      // Patient Visits Today
        'visits_month' => true,      // Visits This Month
        'medicines_used_today' => true, // Medicines Used Today
        'medicine_items' => true,    // Medicine Items
        'low_stock' => true,         // Total Stock Status
        'specialist_visits' => true, // Specialist Visits
        'expiring_medicines' => true // Expiring Medicines
    ]
];

// Function to check if a stat card is enabled
function is_stat_card_enabled($card_name) {
    global $dashboard_config;
    return isset($dashboard_config['stat_cards'][$card_name]) && $dashboard_config['stat_cards'][$card_name] === true;
}
?>