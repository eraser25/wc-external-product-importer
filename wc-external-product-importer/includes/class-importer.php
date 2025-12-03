<?php
if (!defined('ABSPATH')) exit;

class WC_Ext_Importer {

    /**
     * Ürünleri Woocommerce'e aktar
     */
    public static function import($products, $selectedIndexes) {

        if (empty($selectedIndexes)) {
            return [
                'success' => false,
                'message' => 'Hiç ürün seçilmedi.'
            ];
        }

        $imported = [];

        foreach ($selectedIndexes as $index) {

            if (!isset($products[$index])) continue;

            $p = $products[$index];

            $newID = self::import_single_product($p);

            if ($newID) {
                $imported[] = [
                    'id'    => $newID,
                    'title' => $p['title'],
                    'url'   => get_permalink($newID)
                ];
            }
        }

        return [
            'success'  => true,
            'count'    => count($imported),
            'products' => $imported
        ];
    }

    /**
     * Tek ürün WooCommerce'e ekle
     */
    private static function import_single_product($p) {

        // -------------------------
        // WP ürün oluştur
        // -------------------------
        $postID = wp_insert_post([
            'post_title'   => wp_strip_all_tags($p['title']),
            'post_content' => $p['description'] ?? '',
            'post_status'  => 'publish',
            'post_type'    => 'product'
        ]);

        if (is_wp_error($postID)) {
            return false;
        }

        // Ürün tipini ayarla
        wp_set_object_terms($postID, 'simple', 'product_type');

        // -------------------------
        // SKU oluştur
        // -------------------------
        $sku = 'EXT-' . md5($p['url'] . time());
        update_post_meta($postID, '_sku', $sku);

        // -------------------------
        // Fiyat
        // -------------------------
        $regular = self::normalize_price($p['regular'] ?? $p['price'] ?? '');
        $sale    = self::normalize_price($p['price'] ?? '');

        if ($regular) {
            update_post_meta($postID, '_regular_price', $regular);
            update_post_meta($postID, '_price', $regular);
        }

        if ($sale && $sale < $regular) {
            update_post_meta($postID, '_sale_price', $sale);
            update_post_meta($postID, '_price', $sale);
        }

        // stok
        update_post_meta($postID, '_stock_status', 'instock');
        update_post_meta($postID, '_manage_stock', 'no');

        // -------------------------
        // Görsel import
        // -------------------------
        if (!empty($p['images'])) {
            self::import_images($postID, $p['images']);
        }

        return $postID;
    }

    /**
     * Fiyatları normalize eder
     */
    private static function normalize_price($price) {

        if (!$price) return '';

        $price = str_replace(['.', ',', ' '], ['', '.', ''], $price);

        return floatval($price);
    }

    /**
     * Görselleri indir ve ata
     */
    private static function import_images($postID, $images) {

        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $gallery = [];

        foreach ($images as $i => $url) {

            $tmp = download_url($url);

            if (is_wp_error($tmp)) continue;

            $file = [
                'name'     => basename($url),
                'tmp_name' => $tmp
            ];

            $attachID = media_handle_sideload($file, $postID);

            @unlink($tmp);

            if (is_wp_error($attachID)) continue;

            if ($i === 0) {
                set_post_thumbnail($postID, $attachID);
            } else {
                $gallery[] = $attachID;
            }
        }

        if (!empty($gallery)) {
            update_post_meta($postID, '_product_image_gallery', implode(',', $gallery));
        }
    }
}
