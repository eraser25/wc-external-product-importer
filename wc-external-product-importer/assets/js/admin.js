jQuery(function($) {

    const logBox = $("#wc-ext-status-log");
    const resultsBox = $("#wc-ext-results");

    function log(message) {
        logBox.show();
        logBox.append("<div>• " + message + "</div>");
        logBox.scrollTop(logBox[0].scrollHeight);
    }

    // -------------------------------------------------------------
    // URL TARA (SCAN)
    // -------------------------------------------------------------
    $("#wc-ext-scan").on("click", function() {

        const url = $("#wc-ext-url").val().trim();

        if (!url) {
            alert("URL girilmedi.");
            return;
        }

        log("Kategori sayfası taranıyor...");
        resultsBox.html("");

        $.ajax({
            url: wcExtAjax.ajax,
            method: "POST",
            data: {
                action: "wc_ext_scan_url",
                url: url
            },
            success: function(res) {

                if (!res.success) {
                    log("Hata: " + res.message);
                    alert(res.message);
                    return;
                }

                log(res.count + " ürün bulundu.");
                renderProducts(res.products);
            },
            error: function() {
                log("Taramada hata oluştu.");
            }
        });
    });

    // -------------------------------------------------------------
    // BULUNAN ÜRÜNLERİ TABLOYA BAS
    // -------------------------------------------------------------
    function renderProducts(products) {

        let html = `
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th style="width:40px;">Seç</th>
                        <th>Görsel</th>
                        <th>Başlık</th>
                        <th>Fiyat</th>
                        <th>Eski Fiyat</th>
                        <th>URL</th>
                    </tr>
                </thead>
                <tbody>
        `;

        products.forEach((p, i) => {

            html += `
                <tr>
                    <td><input type="checkbox" class="wc-ext-check" data-index="${i}"></td>
                    <td>${p.images.length ? `<img src="${p.images[0]}" style="width:70px;">` : "-"}</td>
                    <td>${p.title}</td>
                    <td>${p.price || "-"}</td>
                    <td>${p.regular || "-"}</td>
                    <td><a href="${p.url}" target="_blank">Ürüne Git</a></td>
                </tr>
            `;
        });

        html += `
                </tbody>
            </table>

            <button id="wc-ext-import" class="button button-primary" style="margin-top:20px;">
                Seçilenleri WooCommerce'e Aktar
            </button>
        `;

        resultsBox.html(html);
        resultsBox.data("products", products);
    }

    // -------------------------------------------------------------
    // ÜRÜNLERİ WOO'YA AKTAR
    // -------------------------------------------------------------
    $(document).on("click", "#wc-ext-import", function() {

        const products = resultsBox.data("products") || [];
        const selected = [];

        $(".wc-ext-check:checked").each(function() {
            selected.push($(this).data("index"));
        });

        if (!selected.length) {
            alert("Hiç ürün seçilmedi.");
            return;
        }

        log(selected.length + " ürün aktarılıyor...");

        $.ajax({
            url: wcExtAjax.ajax,
            method: "POST",
            data: {
                action: "wc_ext_import",
                products: products,
                selected: selected
            },
            success: function(res) {

                if (!res.success) {
                    log("Aktarım hatası: " + res.message);
                    return;
                }

                log(res.count + " ürün başarıyla aktarıldı.");

                res.products.forEach(p => {
                    log("✔ " + p.title + " eklendi → " + p.url);
                });
            },
            error: function() {
                log("Aktarım sırasında hata oluştu.");
            }
        });
    });

});
