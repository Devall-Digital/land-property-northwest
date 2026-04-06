<?php
if (!defined('ABSPATH')) return;
add_action('wp_loaded', function() {
    if (empty($_GET['lpnw_dbstats']) || 'run' !== $_GET['lpnw_dbstats']) return;
    $key = isset( $_GET['key'] ) ? (string) wp_unslash( $_GET['key'] ) : '';
    if ( ! lpnw_tool_query_key_ok( $key ) ) return;
    header('Content-Type: text/plain; charset=utf-8');
    global $wpdb;
    $t = $wpdb->prefix . 'lpnw_properties';
    echo "Total: " . $wpdb->get_var("SELECT COUNT(*) FROM $t") . "\n";
    $sources = $wpdb->get_results("SELECT source, COUNT(*) as cnt FROM $t GROUP BY source ORDER BY cnt DESC");
    echo "\nBy source:\n";
    foreach ($sources as $s) { echo "  {$s->source}: {$s->cnt}\n"; }
    echo "\nNewest 5:\n";
    $newest = $wpdb->get_results("SELECT source, address, postcode, created_at FROM $t ORDER BY created_at DESC LIMIT 5");
    foreach ($newest as $n) { echo "  [{$n->source}] {$n->address} | {$n->postcode} | {$n->created_at}\n"; }
    echo "\nFeed log (last 10):\n";
    $logs = $wpdb->get_results("SELECT feed_name, status, properties_found, properties_new, started_at FROM {$wpdb->prefix}lpnw_feed_log ORDER BY started_at DESC LIMIT 10");
    foreach ($logs as $l) { echo "  {$l->feed_name} | {$l->status} | found={$l->properties_found} new={$l->properties_new} | {$l->started_at}\n"; }
    @unlink(__FILE__);
    exit;
}, 0);
