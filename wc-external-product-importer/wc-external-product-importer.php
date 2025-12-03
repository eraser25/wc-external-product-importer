<?php
/**
 * Plugin Name: WooCommerce External Product Importer
 * Description: HTML Scraping ile dış siteden ürün çekip WooCommerce'e aktaran importer.
 * Version: 1.0
 * Author: Custom
 */

if (!defined('ABSPATH')) exit;

// URL sabitle (TasteWP için garanti çözüm)
define('WC_EXT_IMPORTER_URL', plugin_dir_url(__FILE__));

// Gerekli dosyaları dahil et
require_once __DIR__ . '/admin/admin-page.php';
require_once __DIR__ . '/includes/class-scraper.php';
require_once __DIR__ . '/includes/class-importer.php';

/*
|--------------------------------------------------------------------------
| Admin Menü
|--------------------------------------------------------------------------
*/
add_action('admin_menu', function () {
    add_menu_page(
        'Dış Ürün Importer',
        'Ürün Importer',
        'manage_options',
        'wc-external-importer',
        'wc_ext_admin_page_render',
        'dashicons-download',
        56
    );
});

/*
|--------------------------------------------------------------------------
| Admin CSS + JS
|--------------------------------------------------------------------------
*/
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'toplevel_page_wc-external-importer') return;

    wp_enqueue_style(
        'wc-ext-admin-style',
        WC_EXT_IMPORTER_URL . 'assets/css/admin.css'
    );

    wp_enqueue_script(
        'wc-ext-admin-js',
        WC_EXT_IMPORTER_URL . 'assets/js/admin.js',
        ['jquery'],
        false,
        true
    );

    wp_localize_script('wc-ext-admin-js', 'wcExtAjax', [
        'ajax' => admin_url('admin-ajax.php')
    ]);
});

/*
|--------------------------------------------------------------------------
| AJAX: URL’i Tara
|--------------------------------------------------------------------------
*/
add_action('wp_ajax_wc_ext_scan_url', function () {
    $url = sanitize_text_field($_POST['url'] ?? '');

    if (!$url) {
        wp_send_json([
            'success' => false,
            'message' => 'URL bulunamadı'
        ]);
    }

    $res = WC_Ext_Scraper::scan($url);
    wp_send_json($res);
});

/*
|--------------------------------------------------------------------------
| AJAX: Ürünleri WooCommerce'e Aktar
|--------------------------------------------------------------------------
*/
add_action('wp_ajax_wc_ext_import', function () {
    $products = $_POST['products'] ?? [];
    $selected = $_POST['selected'] ?? [];

    $res = WC_Ext_Importer::import($products, $selected);
    wp_send_json($res);
});

/*
|--------------------------------------------------------------------------
| Admin Panel Render
|--------------------------------------------------------------------------
*/
function wc_ext_admin_page_render() {
    include __DIR__ . '/admin/page-view.php';
}