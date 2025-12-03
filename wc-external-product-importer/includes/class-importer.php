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
        // Fiyat (Türk formatını doğru parse et)
        // -------------------------
        $regular = self::normalize_price($p['regular'] ?? $p['price'] ?? '');
        $sale    = self::normalize_price($p['price'] ?? '');

        // Eğer regular boşsa ama price doluysa
        if (!$regular && $sale) {
            $regular = $sale;
        }

        if ($regular) {
            update_post_meta($postID, '_regular_price', $regular);
            update_post_meta($postID, '_price', $regular);
        }

        // İndirimli fiyat varsa
        if ($sale && $regular && $sale < $regular) {
            update_post_meta($postID, '_sale_price', $sale);
            update_post_meta($postID, '_price', $sale);
        }

        // Stok
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
     * Fiyatları normalize eder (Türk Lirası formatı: 299,90 veya 8.378,00)
     */
    private static function normalize_price($price) {

        if (!$price) return '';

        // HTML entitylerini decode et
        $price = html_entity_decode($price, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Para birimi sembollerini temizle (₺, TL, $, vb.)
        $price = preg_replace('/[^\d,.\s]/', '', $price);
        
        // Boşlukları temizle
        $price = trim($price);
        
        // Türk formatı tespiti
        // Örnek: 299,90 veya 8.378,00
        
        // Nokta VE virgül varsa -> Türk formatı (nokta binlik, virgül ondalık)
        if (strpos($price, '.') !== false && strpos($price, ',') !== false) {
            // 8.378,00 -> 8378.00
            $price = str_replace('.', '', $price);    // Binlikleri kaldır
            $price = str_replace(',', '.', $price);   // Ondalık ayracını düzelt
        }
        // Sadece virgül varsa -> 299,90 formatı
        elseif (strpos($price, ',') !== false) {
            // 299,90 -> 299.90
            $price = str_replace(',', '.', $price);
        }
        // Sadece nokta varsa ve 3 basamaktan fazla -> binlik ayraç olabilir
        elseif (strpos($price, '.') !== false) {
            $parts = explode('.', $price);
            // Son parça 2 basamaklıysa ondalık, değilse binlik ayraç
            if (count($parts) > 1 && strlen(end($parts)) === 2) {
                // 299.90 -> 299.90 (zaten doğru)
                // değişiklik yok
            } else {
                // 8.378 -> 8378
                $price = str_replace('.', '', $price);
            }
        }
        
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
        $firstImageSet = false;

        foreach ($images as $i => $url) {

            // URL'yi temizle
            $url = trim($url);
            
            // Boş URL'leri atla
            if (empty($url)) continue;

            $tmp = download_url($url);

            if (is_wp_error($tmp)) {
                @unlink($tmp);
                continue;
            }

            $file = [
                'name'     => basename(parse_url($url, PHP_URL_PATH)),
                'tmp_name' => $tmp
            ];

            $attachID = media_handle_sideload($file, $postID);

            @unlink($tmp);

            if (is_wp_error($attachID)) continue;

            // İlk görseli featured image yap
            if (!$firstImageSet) {
                set_post_thumbnail($postID, $attachID);
                $firstImageSet = true;
            } else {
                // Diğerlerini galeriye ekle
                $gallery[] = $attachID;
            }
        }

        // Galeri görselleri varsa ekle
        if (!empty($gallery)) {
            update_post_meta($postID, '_product_image_gallery', implode(',', $gallery));
        }
    }
}
