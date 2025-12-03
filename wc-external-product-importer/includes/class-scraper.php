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

        // WooCommerce standart ürün linkleri
        preg_match_all('/<a[^>]+href="([^"]+)"[^>]*class="[^"]*(woocommerce-LoopProduct-link|product-link)[^"]*"/i', $html, $match);

        if (!empty($match[1])) {
            foreach ($match[1] as $link) {
                $links[] = self::absolute_url($link, $baseUrl);
            }
        }

        // Fallback: product class içeren linkler
        if (empty($links)) {
            preg_match_all('/<li[^>]+class="[^"]*product[^"]*"[^>]*>.*?<a[^>]+href="([^"]+)"/is', $html, $match2);
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
        preg_match('/<h1[^>]*class="[^"]*product[_-]title[^"]*"[^>]*>(.*?)<\/h1>/is', $html, $titleMatch);
        if (empty($titleMatch)) {
            preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $titleMatch);
        }
        $title = isset($titleMatch[1]) ? wp_strip_all_tags($titleMatch[1]) : 'Başlık bulunamadı';

        // ---------------------------
        // AÇIKLAMA (Short Description)
        // ---------------------------
        $desc = '';
        
        // WooCommerce standart short description
        preg_match('/<div[^>]+class="[^"]*woocommerce-product-details__short-description[^"]*"[^>]*>(.*?)<\/div>/is', $html, $descMatch);
        
        if (!empty($descMatch[1])) {
            $desc = wp_strip_all_tags($descMatch[1]);
        } else {
            // Fallback
            preg_match('/<div[^>]+class="[^"]*(product-short-description|summary)[^"]*"[^>]*>(.*?)<\/div>/is', $html, $descMatch2);
            if (!empty($descMatch2[2])) {
                $desc = wp_strip_all_tags($descMatch2[2]);
            }
        }

        // ---------------------------
        // GÖRSELLER (Sadece Ürün Galerisi)
        // ---------------------------
        $images = [];
        
        // WooCommerce product-gallery yapısı
        preg_match('/<div[^>]+class="[^"]*woocommerce-product-gallery[^"]*"[^>]*>(.*?)<\/div>/is', $html, $galleryMatch);
        
        if (!empty($galleryMatch[1])) {
            $galleryHtml = $galleryMatch[1];
            
            // Ana görseli bul (data-large_image veya href)
            preg_match_all('/(?:data-large_image|href)="([^"]+\.(?:jpg|jpeg|png|webp|gif)[^"]*)"/i', $galleryHtml, $galleryImages);
            
            if (!empty($galleryImages[1])) {
                foreach ($galleryImages[1] as $img) {
                    // Küçük thumbnail'leri filtrele
                    if (strpos($img, '-150x150') === false && 
                        strpos($img, '-100x100') === false &&
                        strpos($img, '-300x300') === false &&
                        strpos($img, 'thumbnail') === false) {
                        $images[] = $img;
                    }
                }
            }
        }
        
        // Fallback: wp-post-image (featured image)
        if (empty($images)) {
            preg_match('/<img[^>]+class="[^"]*wp-post-image[^"]*"[^>]+data-large_image="([^"]+)"/i', $html, $featuredMatch);
            if (!empty($featuredMatch[1])) {
                $images[] = $featuredMatch[1];
            } else {
                preg_match('/<img[^>]+class="[^"]*wp-post-image[^"]*"[^>]+src="([^"]+)"/i', $html, $featuredMatch2);
                if (!empty($featuredMatch2[1])) {
                    $images[] = $featuredMatch2[1];
                }
            }
        }
        
        $images = array_values(array_unique($images));

        // ---------------------------
        // FİYAT (Doğru WooCommerce Parse)
        // ---------------------------
        $price = '';
        $regular = '';

        // Önce <p class="price"> wrapper'ını bul
        preg_match('/<p[^>]+class="[^"]*price[^"]*"[^>]*>(.*?)<\/p>/is', $html, $priceWrapper);
        
        if (!empty($priceWrapper[1])) {
            $priceBlock = $priceWrapper[1];
            
            // HTML entitylerini decode et
            $priceBlock = html_entity_decode($priceBlock);
            
            // İndirimli fiyat (ins > bdi > span içindeki)
            if (preg_match('/<ins[^>]*>.*?<bdi[^>]*>.*?<span[^>]*class="[^"]*woocommerce-Price-amount[^"]*"[^>]*>(.*?)<\/span>/is', $priceBlock, $insMatch)) {
                $price = wp_strip_all_tags($insMatch[1]);
            }
            
            // Normal fiyat (del > bdi > span içindeki)
            if (preg_match('/<del[^>]*>.*?<bdi[^>]*>.*?<span[^>]*class="[^"]*woocommerce-Price-amount[^"]*"[^>]*>(.*?)<\/span>/is', $priceBlock, $delMatch)) {
                $regular = wp_strip_all_tags($delMatch[1]);
            }
            
            // Eğer indirim yoksa tek fiyat
            if (!$price) {
                if (preg_match('/<span[^>]*class="[^"]*woocommerce-Price-amount[^"]*"[^>]*>(.*?)<\/span>/is', $priceBlock, $singleMatch)) {
                    $price = wp_strip_all_tags($singleMatch[1]);
                    $regular = $price;
                }
            }
        }
        
        // Fallback: Meta tag
        if (!$price) {
            preg_match('/<meta[^>]+property="product:price:amount"[^>]+content="([^"]+)"/i', $html, $metaPrice);
            if (!empty($metaPrice[1])) {
                $price = $metaPrice[1];
                $regular = $price;
            }
        }
        
        // Fiyat temizleme - sadece rakam, nokta ve virgül bırak
        $price = self::clean_price($price);
        $regular = self::clean_price($regular);

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
     * Fiyattan para birimi sembollerini temizle
     */
    private static function clean_price($price) {
        if (!$price) return '';
        
        // HTML entityleri decode et
        $price = html_entity_decode($price, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Para birimi sembollerini ve gereksiz karakterleri temizle
        // Sadece rakam, nokta ve virgül bırak
        $price = preg_replace('/[^\d,.]/', '', $price);
        
        return trim($price);
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
