<?php

namespace franciscoblancojn\AveCrmConnectShopify;

use franciscoblancojn\AveConnectShopify\AveConnectShopify;

/**
 * Clase encargada de construir y publicar productos en Shopify
 * usando los tokens obtenidos de AveCRM.
 * 
 * Integra:
 * - Construcción del JSON requerido por Shopify para la creación de productos.
 * - Obtención de tokens de Shopify asociados a una empresa (desde AveCRM).
 * - Publicación de productos en múltiples tiendas Shopify al mismo tiempo.
 */
class AveCrmConnectShopifyProduct
{

    public AveConnectShopifyApiAve $ave;

    /**
     * Constructor
     *
     * @param AveConnectShopify $shopify  Cliente de conexión a Shopify
     * @param AveConnectShopifyApiAve $ave Cliente para la API de AveCRM
     */
    public function __construct(AveConnectShopifyApiAve $ave)
    {
        $this->ave = $ave;
    }

    /**
     * Construye el JSON de creación de producto en Shopify.
     *
     * @param string $productName   Nombre del producto
     * @param string $productRef    Referencia o SKU principal
     * @param float  $sugerido      Precio sugerido
     * @param float  $peso          Peso del producto (en gramos)
     * @param int    $unidades      Unidades en inventario
     * @param string $marcaName     Marca / vendor
     * @param string $categoryName  Categoría / tipo de producto
     * @param int    $productStatus Estado (1 = draft, 0 = active)
     * @param string $productDesc   Descripción en HTML
     * @param array|string $etiquetas Etiquetas (array o string)
     * @param array  $variants      Variantes (cada variante con atributos, precio, sku, stock, etc.)
     * @param ?string $url          URL de la imagen asociada
     * @param ?string $productId    ID del producto (para update o sync)
     *
     * @return array JSON estructurado para enviar a la API de Shopify
     */
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
        ?string $productId = null,
        ?string $defaultVariantId = null
    ) {
        // Construir base URL dinámica
        $scheme   = isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http';
        $host     = $_SERVER['HTTP_HOST']; // incluye dominio y puerto
        $script   = $_SERVER['SCRIPT_NAME']; // ej: /ave/avestock/api/createProduct.php

        // Quitamos la parte /api/createProduct.php y dejamos solo el root de tu app
        $basePath = str_replace('/api/createProduct.php', '', $script);
        $basePath = str_replace('/api/editProduct.php', '', $basePath);

        // Esto nos da algo como: http://localhost:3009/ave/avestock
        $baseUrl  = $scheme . '://' . $host . $basePath;

        function getImg($baseUrl, $name, $url_img)
        {
            // "image_url": "stock\/..\/public\/images\/stock\/25505\/13755990068c4848803366.webp",
            $url_f =  $url_img ? ($baseUrl .  $url_img) : "";
            $url_f =  str_replace("stock/../", "/", $url_f);
            $url_f =  str_replace("../", "/", $url_f);
            if ($url_f == "") {
                return null;
            }
            return [
                "alt"        => $name,
                "position"   => 1,
                "width"      => 600,
                "height"     => 600,
                "src"        => $url_f,
            ];
        }
        function make_handle($text)
        {
            $t = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
            $t = preg_replace('/[^a-zA-Z0-9]+/', '-', $t);
            $t = trim($t, '-');
            return strtolower($t);
        }


        // Ahora construimos la URL pública de la imagen
        $imagePath = $url; // viene de FileService::saveFile(), ej: ../public/images/stock/25505/file.webp

        $principalImg = getImg($baseUrl, $productName, $imagePath);
        // --- Variants --- (si no hay variantes cargadas en $_POST['variants'])
        $shopifyVariants = [];
        $shopifyOptions = [];
        // --- Images ---
        $shopifyImages = $principalImg ? [$principalImg] : [];

        if (!empty($variants)) {
            foreach ($variants as $i => $variant) {
                $attributes = $variant['attributes'];
                $options = [];
                foreach ($attributes as $key => $value) {
                    $options[] = $value;
                    $shopifyOptions[$key] ??= [
                        "name" => $key,
                        "values" => []
                    ];
                    $shopifyOptions[$key]["values"][$value] = $value;
                }
                $img = getImg(
                    $baseUrl,
                    $variant['sku'] ?? $productRef,
                    $variant['image_url'],
                    // [$variant['id']]
                );
                if ($img) {
                    $shopifyImages[] = $img;
                }
                $shopifyVariants[] = [
                    "id"                   => $variant['id'] ?? '',
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
            $key = "default_variant";
            $value = "default_variant";
            $shopifyOptions[$key] ??= [
                "name" => $key,
                "values" => []
            ];
            $shopifyOptions[$key]["values"][$value] = $value;
            $shopifyVariants[] = [
                "id"                   => $defaultVariantId ? ((string)($defaultVariantId ?? '')) : ($productId  ? (string)($productId ?? '') : ''),
                "title"                => $productName,
                "price"                => ($sugerido) . "",
                "sku"                  => $productRef,
                "position"             => 1,
                "inventory_policy"     => "deny",
                "option1"              => $value,
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
        if (count($shopifyOptions) == 0) {
            $key = "default_variant";
            $value = "default_variant";
            $shopifyOptions[$key] ??= [
                "name" => $key,
                "values" => []
            ];
            $shopifyOptions[$key]["values"][$value] = $value;
        }
        foreach ($shopifyOptions as $key => $value) {
            $shopifyOptions[$key]["values"]  = array_values($shopifyOptions[$key]["values"]);
        }
        $shopifyOptions  = array_values($shopifyOptions);



        // --- Producto Shopify ---
        $shopifyProduct = [
            "product" => [
                "id"                    => $productId  ? (string)($productId ?? '') : '', //PENDING::change to custom meta file
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
                "image"                 => $principalImg
            ]
        ];
        return $shopifyProduct;
    }

    /**
     * Publica un producto en Shopify en todas las tiendas asociadas a una empresa.
     *
     * @param string $idempresa    ID de la empresa en AveCRM
     * @param string $token        Token de autenticación
     * @param string $productName  Nombre del producto
     * @param string $productRef   Referencia o SKU principal
     * @param float  $sugerido     Precio sugerido
     * @param float  $peso         Peso en gramos
     * @param int    $unidades     Cantidad en stock
     * @param string $marcaName    Marca del producto
     * @param string $categoryName Categoría del producto
     * @param int    $productStatus Estado del producto (1 = draft, 0 = active)
     * @param string $productDesc  Descripción en HTML
     * @param array|string $etiquetas Etiquetas (tags)
     * @param array  $variants     Variantes del producto
     * @param ?string $url         URL de la imagen
     * @param ?string $productId   ID de producto (opcional, para actualizar)
     *
     * @return array|null Resultado de la creación por cada tienda Shopify,
     *                    o null si no hay tokens configurados.
     */
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
        ?string $productId = null,
        ?string $defaultVariantId = null
    ) {
        $tokensShopify = $this->ave->onGetTokenShopifyByCompany(
            $idempresa,          // string
            $token,              // string
        );
        if ($tokensShopify == null) {
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
            $productId,           // ?string
            $defaultVariantId
        );
        $resultCreateShopify = [];
        for ($i = 0; $i < count($tokensShopify); $i++) {
            $variations_put = [];
            $token_id = $tokensShopify[$i]['id'];
            $shop = $tokensShopify[$i]['url'];
            $token = $tokensShopify[$i]['token'];
            try {
                $shopify = new AveConnectShopify($shop, $token);
                $result = $shopify->product->post($jsonProductForCreate);
                $variants = $jsonProductForCreate['product']['variants'];
                $productResult = $result['product'];
                $product_ref = $productResult['id'];
                $variantsResult = $productResult['variants'];
                $imagesResult = $productResult['images'];

                $products_refs  = [];
                $products_refs[] = [
                    "product_id"  => $productId,
                    "parent_id"   => null,
                    "product_ref" => "$product_ref",
                    "token_id"    => $token_id,
                ];
                for ($j = 0; $j < count($variants); $j++) {
                    $variant_id = $variants[$j]['id'];
                    $variant_sku = $variants[$j]['sku'];
                    // Buscar en $variantsResult el product_ref que coincida con el sku
                    $product_ref = null;
                    foreach ($variantsResult as $vr) {
                        if ($vr['sku'] === $variant_sku) {
                            $product_ref = $vr['id'];
                            break;
                        }
                    }
                    $variant_img_id = null;
                    foreach ($imagesResult as $img) {
                        if ($img['alt'] === $variant_sku) {
                            $variant_img_id = $img['id'];
                            break;
                        }
                    }
                    if ($variant_img_id) {
                        $s = [
                            "variant" => [
                                "id" => $product_ref,
                                "image_id" => $variant_img_id,
                            ]
                        ];
                        try {
                            $r = $shopify->variation->put($product_ref, $s);
                            $variations_put[] = [
                                "send" => $s,
                                "result" => $r,
                            ];
                        } catch (\Throwable $e) {
                            $variations_put[] = [
                                "send" => $s,
                                "error" => $e->getMessage()
                            ];
                        }
                    }
                    $products_refs[] = [
                        "product_id"  => $variant_id,
                        "parent_id"   => $productId,
                        "product_ref" => "$product_ref",
                        "token_id"    => $token_id,
                    ];
                }
                $products_refs_result = $this->ave->postProductIdRef($token, $products_refs);

                $resultCreateShopify[$shop] = [
                    "shop" => $shop,
                    "send" => $jsonProductForCreate,
                    "result" => $result,
                    "variations_put" => $variations_put,
                    "products_refs" => $products_refs,
                    "products_refs_result" => $products_refs_result,
                ];
            } catch (\Throwable $e) {
                $resultCreateShopify[$shop] = [
                    "shop" => $shop,
                    "product_id" => $productId,
                    "send" => $jsonProductForCreate,
                    "result" => null,
                    "success" => false,
                    "error" => $e->getMessage()
                ];
            }
        }

        return [
            "resultCreateShopify" => $resultCreateShopify,
            "tokensShopify" => $tokensShopify,
        ];
    }

    /**
     * Actualiza un producto existente en Shopify en todas las tiendas asociadas a una empresa.
     *
     * @param string $idempresa    ID de la empresa en AveCRM
     * @param string $token        Token de autenticación
     * @param string $productName  Nombre del producto
     * @param string $productRef   Referencia o SKU principal
     * @param float  $sugerido     Precio sugerido
     * @param float  $peso         Peso en gramos
     * @param int    $unidades     Cantidad en stock
     * @param string $marcaName    Marca del producto
     * @param string $categoryName Categoría del producto
     * @param int    $productStatus Estado del producto (1 = draft, 0 = active)
     * @param string $productDesc  Descripción en HTML
     * @param array|string $etiquetas Etiquetas (tags)
     * @param array  $variants     Variantes del producto
     * @param ?string $url         URL de la imagen
     * @param string $productId    ID de producto en Shopify (requerido para actualizar)
     *
     * @return array|null Resultado de la actualización por cada tienda Shopify,
     *                    o null si no hay tokens configurados.
     */
    public function put(
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
        string $productId = null
    ) {
        // Validar que el productId sea requerido para actualización
        if (empty($productId)) {
            throw new \InvalidArgumentException('El ID del producto es requerido para actualizar en Shopify');
        }

        $tokensShopify = $this->ave->onGetTokenShopifyByCompany(
            $idempresa,
            $token
        );

        if ($tokensShopify == null) {
            return null;
        }

        $jsonProductForUpdate = $this->getJsonCreateShopifyProduct(
            $productName,
            $productRef,
            $sugerido,
            $peso,
            $unidades,
            $marcaName,
            $categoryName,
            $productStatus,
            $productDesc,
            $etiquetas,
            $variants,
            $url,
            $productId
        );

        $resultUpdateShopify = [];

        $products_id = [$productId];
        for ($j = 0; $j < count($jsonProductForUpdate['product']['variants'] ?? []); $j++) {
            $products_id[] = $jsonProductForUpdate['product']['variants'][$j]['id'];
        }

        $product_ref_result = $this->ave->getProductIdRef($token, $products_id);
        $product_ref_data = $product_ref_result['data'];

        $jsonProductForUpdate_CONST = $jsonProductForUpdate;

        for ($i = 0; $i < count($tokensShopify); $i++) {
            $variations_put = [];
            $jsonProductForUpdate = $jsonProductForUpdate_CONST;
            $shop = $tokensShopify[$i]['url'];
            $shopId = $tokensShopify[$i]['id'];
            $shopToken = $tokensShopify[$i]['token'];

            try {
                $shopify = new AveConnectShopify($shop, $shopToken);

                $product_ref_data_filter = array_values(array_filter($product_ref_data, function ($e) use ($shopId) {
                    return $e['token_id'] == $shopId;
                }));
                for ($j = 0; $j < count($product_ref_data_filter); $j++) {
                    $product_id = $product_ref_data_filter[$j]['product_id'];
                    $product_ref = $product_ref_data_filter[$j]['product_ref'];

                    if ($jsonProductForUpdate['product']['id'] == $product_id) {
                        $jsonProductForUpdate['product']['id_ave'] = $product_id;
                        $jsonProductForUpdate['product']['id'] = $product_ref;
                    } else {
                        for ($k = 0; $k < count($jsonProductForUpdate['product']['variants']); $k++) {
                            if ($jsonProductForUpdate['product']['variants'][$k]['id'] == $product_id) {
                                $jsonProductForUpdate['product']['variants'][$k]['id_ave'] = $product_id;
                                $jsonProductForUpdate['product']['variants'][$k]['id'] = $product_ref;
                            }
                        }
                    }
                }

                // El método put de AveConnectShopify requiere el ID del producto como primer parámetro
                $result = $shopify->product->put($jsonProductForUpdate['product']['id'], $jsonProductForUpdate);

                $variationResult = $result['product']['variants'];
                $imagesResult = $result['product']['images'];
                for ($j = 0; $j < count($variationResult); $j++) {
                    $product_ref = $variationResult[$j]['id'];
                    $variant_sku = $variationResult[$j]['sku'];
                    $variant_img_id = null;
                    foreach ($imagesResult as $img) {
                        if ($img['alt'] === $variant_sku) {
                            $variant_img_id = $img['id'];
                            break;
                        }
                    }
                    if ($variant_img_id) {
                        $s = [
                            "variant" => [
                                "id" => $product_ref,
                                "image_id" => $variant_img_id,
                            ]
                        ];
                        try {
                            $r = $shopify->variation->put($product_ref, $s);
                            $variations_put[] = [
                                "send" => $s,
                                "result" => $r,
                            ];
                        } catch (\Throwable $e) {
                            $variations_put[] = [
                                "send" => $s,
                                "error" => $e->getMessage()
                            ];
                        }
                    }
                }

                $resultUpdateShopify[$shop] = [
                    "shop" => $shop,
                    "product_id" => $productId,
                    "send" => $jsonProductForUpdate,
                    "result" => $result,
                    "variations_put" => $variations_put,
                    "success" => true,
                    "product_ref_data" => $product_ref_data,
                    "product_ref_data_filter" => $product_ref_data_filter,
                ];
            } catch (\Throwable $e) {
                $resultUpdateShopify[$shop] = [
                    "shop" => $shop,
                    "product_id" => $productId,
                    "send" => $jsonProductForUpdate,
                    "result" => null,
                    "success" => false,
                    "error" => $e->getMessage()
                ];
            }
        }

        return $resultUpdateShopify;
    }
}
