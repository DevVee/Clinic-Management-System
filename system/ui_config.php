<?php
// /system/ui_config.php

// UI configuration for dashboard
$ui_config = [
    'dashboard' => [
        'theme' => 'default', // Example: Theme setting (light, dark, etc.)
        'animation' => true,   // Enable/disable animations
        'card_layout' => 'grid', // Layout style (e.g., grid, list)
        'toggles' => [
            'stat_cards' => [
                'patients' => true,
                'visits_today' => true,
                'visits_month' => true,
                'medicines_used_today' => true,
                'medicine_items' => true,
                'low_stock' => true,
                'specialist_visits' => true,
                'expiring_medicines' => true,
            ],
            'quick_actions' => [
                'log_patient' => true,
                'appointments' => true,
                'daily_reports' => true,
                'manage_inventory' => true,
                'add_new_medicine' => true,
            ],
            'sections' => [
                'approved_appointments' => true,
            ]
        ]
    ]
];

// Function to get UI config value
function get_ui_config($key, $default = null) {
    global $ui_config;
    return isset($ui_config['dashboard'][$key]) ? $ui_config['dashboard'][$key] : $default;
}

// Function to check if a UI toggle is enabled
function is_ui_toggle_enabled($category, $toggle_name) {
    global $ui_config;
    return isset($ui_config['dashboard']['toggles'][$category][$toggle_name]) 
           && $ui_config['dashboard']['toggles'][$category][$toggle_name] === true;
}
?>