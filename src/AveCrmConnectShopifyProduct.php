<?php

namespace franciscoblancojn\AveCrmConnectShopify;

use franciscoblancojn\AveConnectShopify\AveConnectShopify;

class AveCrmConnectShopifyProduct
{

    public AveConnectShopify $shopify;
    public AveConnectShopifyApiAve $ave;
    public function __construct(AveConnectShopify $shopify, AveConnectShopifyApiAve $ave)
    {
        $this->ave = $ave;
        $this->shopify = $shopify;
    }


    private function getJsonCreateShopifyProduct(
        string $productName,
        string $productRef,
        float $sugerido,
        float $peso,
        int $unidades,
        string $marcaName,
        string $categoryName,
        int $productStatus,
        string $productDesc,
        $etiquetas,
        array $variants = [],
        ?string $url = null,
        ?string $productId = null
    ) {
        function make_handle($text)
        {
            $t = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
            $t = preg_replace('/[^a-zA-Z0-9]+/', '-', $t);
            $t = trim($t, '-');
            return strtolower($t);
        }


        // --- Variants --- (si no hay variantes cargadas en $_POST['variants'])
        $shopifyVariants = [];
        $shopifyOptions = [];
        //example 
        // "options": [
        //     {
        //     "name": "Color",
        //     "values": ["red", "blue", "green"]
        //     },
        //     {
        //     "name": "Size",
        //     "values": ["s", "m", "l", "xl"]
        //     }
        // ],
        if (!empty($variants)) {
            foreach ($variants as $i => $variant) {
                $attributes = $variant['attributes'];
                //example   
                // "attributes": {
                //     "color": "red",
                //     "size": "s"
                // }
                $options = [];
                foreach ($attributes as $key => $value) {
                    $options[] = $value;
                    $shopifyOptions[$key] ??= [
                        "name" => $key,
                        "values" => []
                    ];
                    $shopifyOptions[$key]["values"][$value] = $value;
                }
                $shopifyVariants[] = [
                    "title"                => $variant['name'] ?? "Variante " . ($i + 1),
                    "price"                => ($variant['price'] ?? $sugerido) . "",
                    "sku"                  => $variant['sku'] ?? $productRef,
                    "position"             => $i + 1,
                    "inventory_policy"     => "deny",
                    "compare_at_price"     => ($variant['suggested_price'] ?? "") . "",
                    "option1"              => $options[0] ?? null,
                    "option2"              => $options[1] ?? null,
                    "option3"              => $options[2] ?? null,
                    "fulfillment_service"  => "manual",
                    "grams"                => (int)($variant['weight'] ?? $peso),
                    "inventory_management" => "shopify",
                    "requires_shipping"    => true,
                    "weight"               => (float)($variant['weight'] ?? $peso),
                    "weight_unit"          => "g",
                    "inventory_quantity"   => (int)($variant['stock'] ?? $unidades),
                    "old_inventory_quantity" => (int)($variant['stock'] ?? $unidades),
                ];
            }
        } else {
            // fallback: 1 variante por defecto
            $shopifyVariants[] = [
                "title"                => $productName,
                "price"                => ($sugerido) . "",
                "sku"                  => $productRef,
                "position"             => 1,
                "inventory_policy"     => "deny",
                "option1"              => null,
                "fulfillment_service"  => "manual",
                "grams"                => (int)$peso,
                "inventory_management" => "shopify",
                "requires_shipping"    => true,
                "weight"               => (float)$peso,
                "weight_unit"          => "g",
                "inventory_quantity"   => (int)$unidades,
                "old_inventory_quantity" => (int)$unidades,
            ];
        }
        foreach ($shopifyOptions as $key => $value) {
            $shopifyOptions[$key]["values"]  = array_values($shopifyOptions[$key]["values"]);
        }
        $shopifyOptions  = array_values($shopifyOptions);
        // Construir base URL dinámica
        $scheme   = isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http';
        $host     = $_SERVER['HTTP_HOST']; // incluye dominio y puerto
        $script   = $_SERVER['SCRIPT_NAME']; // ej: /ave/avestock/api/createProduct.php

        // Quitamos la parte /api/createProduct.php y dejamos solo el root de tu app
        $basePath = str_replace('/api/createProduct.php', '', $script);

        // Esto nos da algo como: http://localhost:3009/ave/avestock
        $baseUrl  = $scheme . '://' . $host . $basePath;

        // Ruta absoluta del proyecto en filesystem
        $projectRoot = realpath(dirname(__FILE__) . '/..');
        // ej: /opt/lampp/htdocs/ave/avestock

        // Ahora construimos la URL pública de la imagen
        $imagePath = $url; // viene de FileService::saveFile(), ej: ../public/images/stock/25505/file.webp
        $imageRealPath = realpath(dirname(__FILE__) . '/' . $imagePath);

        $imageUrl = str_replace($projectRoot, $baseUrl, $imageRealPath);
        $imageUrl = str_replace(DIRECTORY_SEPARATOR, "/", $imageUrl);

        // --- Images ---
        $shopifyImages = [];
        if (!empty($imageUrl)) {
            $shopifyImages[] = [
                "alt"        => $productName,
                "position"   => 1,
                "width"      => 600,
                "height"     => 600,
                "src"        => $imageUrl,
                "variant_ids" => []
            ];
        } elseif (!empty($_FILES['productImage']['tmp_name'])) {
            $shopifyImages[] = [
                "alt"        => $productName,
                "position"   => 1,
                "width"      => 600,
                "height"     => 600,
                "src"        => $url, // path generado por FileService::saveFile()
                "variant_ids" => []
            ];
        }

        // --- Producto Shopify ---
        $shopifyProduct = [
            // "idempresa" => $idempresa,
            // "token" => $token,
            "product" => [
                "id"                    => (string)($productId ?? ''), //PENDING::change to custom meta file
                "title"                 => $productName,
                "body_html"             => $productDesc ? $productDesc : "<strong>{$productName}</strong>",
                "vendor"                => (string)$marcaName,
                "product_type"          => (string)$categoryName,
                "handle"                => make_handle($productName),
                "tags"                  => is_array($etiquetas) ? implode(',', $etiquetas) : (string)$etiquetas,
                "status"                => ($productStatus == 1 ? "draft" : "active"),
                "options"               => $shopifyOptions,
                "variants"              => $shopifyVariants,
                "images"                => $shopifyImages,
                "image"                 => !empty($shopifyImages) ? $shopifyImages[0] : null
            ]
        ];
        return $shopifyProduct;
    }

    private function onGetTokenShopifyByCompany(
        string $idempresa,
        string $token
    ) {
        try {
            $data = $this->ave->getShopsTokens($idempresa, $token);
            if (!$data || !$data['data'] || count($data['data']) == 0) {
                return null;
            }
            return $data['data'];
        } catch (\Throwable $th) {
            return null;
        }
    }


    public function post(
        string $idempresa,
        string $token,
        string $productName,
        string $productRef,
        float $sugerido,
        float $peso,
        int $unidades,
        string $marcaName,
        string $categoryName,
        int $productStatus,
        string $productDesc,
        $etiquetas,
        array $variants = [],
        ?string $url = null,
        ?string $productId = null
    ) {
        $tokensShopify = $this->onGetTokenShopifyByCompany(
            $idempresa,          // string
            $token,              // string
        );
        if($tokensShopify == null){
            return null;
        }

        $jsonProductForCreate = $this->getJsonCreateShopifyProduct(
            $productName,        // string
            $productRef,         // string
            ($sugerido), // float
            ($peso),     // float
            ($unidades),   // int  <-- ESTE FALTABA
            $marcaName,          // string
            $categoryName,       // string
            ($productStatus), // int
            $productDesc,        // string
            $etiquetas,          // array
            $variants,           // array
            $url,                // ?string
            $productId           // ?string
        );
        $resultCreateShopify = [];
        for ($i = 0; $i < count($tokensShopify); $i++) {
            $shop = $tokensShopify[$i]['url'];
            $token = $tokensShopify[$i]['token'];
            $shopify = new AveConnectShopify($shop, $token);
            $result = $shopify->product->post($jsonProductForCreate);

            $resultCreateShopify[$shop] = [
                "shop" => $shop,
                "send" => $jsonProductForCreate,
                "result" => $result,
            ];
        }

        return $resultCreateShopify;
    }

}
