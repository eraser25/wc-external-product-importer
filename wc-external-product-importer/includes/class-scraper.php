<?php
if (!defined('ABSPATH')) exit;

class WC_Ext_Scraper {

    /**
     * Kategori sayfasını tara -> tüm ürünleri bul
     * Her ürünün sayfasına giderek detayları çek
     */
    public static function scan($url) {

        $page = self::fetch($url);

        if (!$page) {
            return [
                'success' => false,
                'message' => 'Kategori sayfası okunamadı.'
            ];
        }

        // Kategori sayfasındaki tüm ürün linklerini bul
        $productLinks = self::extract_product_links($page, $url);

        if (empty($productLinks)) {
            return [
                'success' => false,
                'message' => 'Kategori sayfasında ürün bulunamadı.'
            ];
        }

        $products = [];

        // Her ürün sayfasına gir ve detaylı scraping yap
        foreach ($productLinks as $pUrl) {

            $details = self::scrape_single_product($pUrl);

            if ($details) {
                $products[] = $details;
            }
        }

        return [
            'success'  => true,
            'count'    => count($products),
            'products' => $products
        ];
    }

    /**
     * URL'den HTML çek
     */
    private static function fetch($url) {

        $res = wp_remote_get($url, [
            'timeout' => 20,
            'headers' => [
                'user-agent' => 'Mozilla/5.0'
            ]
        ]);

        return wp_remote_retrieve_body($res);
    }

    /**
     * Kategori sayfasındaki tüm ürün linklerini bulur
     */
    private static function extract_product_links($html, $baseUrl) {

        $links = [];

        // <a> içinde product-link class
        preg_match_all('/<a[^>]+href="([^"]+)"[^>]*class="[^"]*(product|item|box)[^"]*"/i', $html, $match);

        if (!empty($match[1])) {
            foreach ($match[1] as $link) {
                $links[] = self::absolute_url($link, $baseUrl);
            }
        }

        // ekstra fallback — img içeren linkler
        if (empty($links)) {
            preg_match_all('/<a[^>]+href="([^"]+)"[^>]*>\s*<img/i', $html, $match2);
            if (!empty($match2[1])) {
                foreach ($match2[1] as $link) {
                    $links[] = self::absolute_url($link, $baseUrl);
                }
            }
        }

        // Tekrarlanan linkleri temizle
        return array_values(array_unique($links));
    }

    /**
     * Tek ürün sayfasını tara
     */
    private static function scrape_single_product($url) {

        $html = self::fetch($url);

        if (!$html) return null;

        // ---------------------------
        // BAŞLIK
        // ---------------------------
        preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $titleMatch);
        $title = isset($titleMatch[1]) ? strip_tags($titleMatch[1]) : 'Başlık bulunamadı';

        // ---------------------------
        // AÇIKLAMA
        // ---------------------------
        $desc = '';
        preg_match('/<div[^>]+class="[^"]*(description|product-desc)[^"]*"[^>]*>(.*?)<\/div>/is', $html, $descMatch);
        if (!empty($descMatch[2])) {
            $desc = trim($descMatch[2]);
        }

        // ---------------------------
        // GÖRSELLER
        // ---------------------------
        $images = [];
        preg_match_all('/<img[^>]+src="([^"]+)"/i', $html, $imgMatch);

        if (!empty($imgMatch[1])) {
            foreach ($imgMatch[1] as $img) {
                if (strpos($img, 'http') !== false) {
                    $images[] = $img;
                }
            }
            $images = array_unique($images);
        }

        // ---------------------------
        // FİYAT & İNDİRİM FARKI
        // ---------------------------

        $price = '';
        $regular = '';

        // indirimli fiyat
        preg_match('/<ins[^>]*>(.*?)<\/ins>/is', $html, $ins);
        if (!empty($ins[1])) {
            preg_match('/([0-9.,]+)/', $ins[1], $num);
            $price = $num[1] ?? '';
        }

        // normal fiyat
        preg_match('/<del[^>]*>(.*?)<\/del>/is', $html, $del);
        if (!empty($del[1])) {
            preg_match('/([0-9.,]+)/', $del[1], $num2);
            $regular = $num2[1] ?? '';
        }

        // fallback – tek fiyat
        if (!$price) {
            preg_match('/([0-9.,]+)\s*(TL|₺|EUR|USD)/i', $html, $single);
            if (!empty($single[1])) {
                $price = $single[1];
                $regular = $single[1];
            }
        }

        return [
            'title'       => $title,
            'description' => $desc,
            'images'      => $images,
            'price'       => $price,
            'regular'     => $regular,
            'url'         => $url
        ];
    }

    /**
     * Göreceli URL → Tam URL
     */
    private static function absolute_url($url, $base) {

        // Zaten tam URL ise
        if (strpos($url, 'http') === 0) {
            return $url;
        }

        $baseParts = parse_url($base);

        $host = $baseParts['scheme'] . '://' . $baseParts['host'];

        // /images/xxx.jpg → root relative
        if (substr($url, 0, 1) === '/') {
            return $host . $url;
        }

        // kategori/page/product → relative
        $path = rtrim(dirname($baseParts['path']), '/');
        return $host . $path . '/' . $url;
    }
}
