<?php
if (!defined('ABSPATH')) exit;
?>

<div class="wrap">

    <h1>Dış Ürün Importer</h1>

    <!-- URL GİRİŞ ALANI -->
    <div style="max-width:600px; margin-top:20px;">
        <label for="wc-ext-url"><strong>Kaynak URL</strong></label>
        <input 
            type="text" 
            id="wc-ext-url" 
            style="width:100%; padding:10px; margin-top:5px;" 
            placeholder="Ürün veya kategori sayfasının URL'si"
        >

        <button 
            id="wc-ext-scan" 
            class="button button-primary" 
            style="margin-top:10px;"
        >
            Tara
        </button>
    </div>

    <!-- TARANAN ÜRÜNLER TABLOSU BURADA GÖRÜNECEK -->
    <div id="wc-ext-results" style="margin-top:25px;"></div>

    <!-- LOG PANELİ -->
    <div 
        id="wc-ext-status-log"
        style="
            display:none; 
            margin-top:30px;
            padding:15px; 
            background:#f7f7f7; 
            border-left:4px solid #2271b1;
            max-height:300px;
            overflow-y:auto;
            font-size:14px;
        "
    ></div>

</div>
