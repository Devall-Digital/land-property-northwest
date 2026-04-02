<?php
if (!defined('ABSPATH')) return;
add_action('init', function() {
    if (empty($_GET['lpnw_kf']) || 'check' !== $_GET['lpnw_kf']) return;
    if (empty($_GET['key']) || 'lpnw2026setup' !== $_GET['key']) return;
    header('Content-Type: text/plain; charset=utf-8');
    global $wpdb;
    $rows = $wpdb->get_results("SELECT id, raw_data FROM {$wpdb->prefix}lpnw_properties WHERE raw_data IS NOT NULL LIMIT 3");
    foreach ($rows as $row) {
        $data = json_decode($row->raw_data, true);
        echo "Property ID: {$row->id}\n";
        if (isset($data['keyFeatures'])) {
            echo "  keyFeatures type: " . gettype($data['keyFeatures']) . "\n";
            echo "  keyFeatures count: " . count($data['keyFeatures']) . "\n";
            echo "  First 3: " . wp_json_encode(array_slice($data['keyFeatures'], 0, 3)) . "\n";
        } else {
            echo "  keyFeatures: NOT SET\n";
            echo "  Available keys: " . implode(', ', array_slice(array_keys($data ?? []), 0, 20)) . "\n";
        }
        echo "\n";
    }
    @unlink(__FILE__);
    exit;
}, 1);
