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


    function getImg($baseUrl, $name, $url_img)
    {
        // "image_url": "stock\/..\/public\/images\/stock\/25505\/13755990068c4848803366.webp",
        $url_f =  $url_img ? ($baseUrl .  $url_img) : "";
        $url_f =  str_replace("stock/../", "/", $url_f);
        $url_f =  str_replace("../", "/", $url_f);
        if ($url_f == "") {
            return null;
        }
        if (preg_match('/^https?:\/\//', $url_img)) {
            $url_f = $url_img;
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
        return strtolower($t) . rand(1, 1000);
    }
    function normalizeId($value)
    {
        return preg_replace('/\D/', '', $value);
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

        // Ahora construimos la URL pública de la imagen
        $imagePath = $url; // viene de FileService::saveFile(), ej: ../public/images/stock/25505/file.webp

        $principalImg = $this->getImg($baseUrl, $productName, $imagePath);
        // --- Variants --- (si no hay variantes cargadas en $_POST['variants'])
        $shopifyVariants = [];
        $shopifyOptions = [];
        // --- Images ---
        $shopifyImages = $principalImg ? [$principalImg] : [];

        if (!empty($variants)) {
            foreach ($variants as $i => $variant) {
                $attributes = $variant['attributes'] ?? [];
                $options = [];
                foreach ($attributes as $key => $value) {
                    $options[] = $value;
                    $shopifyOptions[$key] ??= [
                        "name" => $key,
                        "values" => []
                    ];
                    $shopifyOptions[$key]["values"][$value] = $value;
                }
                $img = null;
                if (!empty($variant['image_url'])) { // <-- evita undefined index
                    $img =  $this->getImg(
                        $baseUrl,
                        $variant['sku'] ?? $productRef,
                        $variant['image_url'],
                        // [$variant['id']]
                    );
                }
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
                    "dropshipping_id"       => $variant['dropshipping_id'] ?? null,
                ];
            }
        } else if ($defaultVariantId != -1) {
            $key = "Por Defecto";
            $value = "Por Defecto";
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
                "weight_unit"          => "kg",
                "inventory_quantity"   => (int)$unidades,
                "old_inventory_quantity" => (int)$unidades,
                "dropshipping_id"       => null,
            ];
        }
        if (count($shopifyOptions) == 0 && $defaultVariantId != -1) {
            $key = "Por Defecto";
            $value = "Por Defecto";
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
                "product_type"          => "",
                "handle"                =>  $this->make_handle($productName . $productRef),
                "tags"                  => is_array($etiquetas) ? implode(',', $etiquetas) : (string)$etiquetas,
                "status"                => ($productStatus == 1 ? "ACTIVE" : "DRAFT"),
                "options"               => $shopifyOptions,
                "variants"              => $shopifyVariants,
                "images"                => $shopifyImages,
                "image"                 => $principalImg,
                "categoryName"          => $categoryName, // custom field   
                "created_by"            => "AveCRM", // custom field
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
        ?string $defaultVariantId = null,
        ?array $tokensShopify_forUse = null,
        ?string $product_dropshipping_id = null
    ) {
        $tokensShopify = $tokensShopify_forUse ?? $this->ave->onGetTokenShopifyByCompany(
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
            $defaultVariantId,
        );


        $product_ref_data = [];
        if ($product_dropshipping_id) {
            $products_dropshipping_id = [$product_dropshipping_id];
            for ($j = 0; $j < count($jsonProductForCreate['product']['variants'] ?? []); $j++) {
                if ($jsonProductForCreate['product']['variants'][$j]['dropshipping_id']) {
                    $products_dropshipping_id[] = $jsonProductForCreate['product']['variants'][$j]['dropshipping_id'];
                }
            }
            $product_dropshipping_ref_result = $this->ave->getProductIdRef($token, $products_dropshipping_id);
            $product_ref_data = $product_dropshipping_ref_result['data'];
        }
        if (count($product_ref_data) == 0) {
            $products_id = [$productId];
            for ($j = 0; $j < count($jsonProductForCreate['product']['variants'] ?? []); $j++) {
                $products_id[] = $jsonProductForCreate['product']['variants'][$j]['id'];
            }
            $product_ref_result = $this->ave->getProductIdRef($token, $products_id);
            $product_ref_data = $product_ref_result['data'];
        }
        $resultRefForShopCreated = [];
        if ($product_ref_data && count($product_ref_data) > 0) {
            foreach ($product_ref_data as $key => $ref) {
                $resultRefForShopCreated[$ref['token_id']] ??= [];
                $resultRefForShopCreated[$ref['token_id']][$ref['product_id']] = $ref['product_ref'];
            }
        }
        $resultCreateShopify = [];
        for ($i = 0; $i < count($tokensShopify); $i++) {
            $variations_put = [];
            $token_id = $tokensShopify[$i]['id'];
            $shop = $tokensShopify[$i]['url'];
            $token = $tokensShopify[$i]['token'];
            $id_agente = $tokensShopify[$i]['id_agente'];
            $shoPreRef = $resultRefForShopCreated[$token_id] ?? [];
            if (isset($shoPreRef[$productId])) {
                $products_refs = [];
                $products_refs[] = [
                    "product_id"  => $productId,
                    "parent_id"   => null,
                    "product_ref" => $this->normalizeId($shoPreRef[$productId]),
                    "token_id"    => $token_id,
                    "product_type"    => $product_dropshipping_id ? 2 : 1,
                    "product_dropshipping_id"    => $product_dropshipping_id,
                ];
                $variants = $jsonProductForCreate['product']['variants'];
                for ($j = 0; $j < count($variants); $j++) {
                    $variant_id = $variants[$j]['id'];
                    $variant_dropshipping_id = $variants[$j]['dropshipping_id'] ?? null;
                    $variant_sku = $variants[$j]['sku'];
                    // Buscar en $variantsResult el product_ref que coincida con el sku
                    if (isset($shoPreRef[$variant_id])) {
                        $product_ref = $shoPreRef[$variant_id];
                        $products_refs[] = [
                            "product_id"  => $variant_id,
                            "parent_id"   => $productId,
                            "product_ref" => $this->normalizeId("$product_ref"),
                            "token_id"    => $token_id,
                            "product_type"    => $variant_dropshipping_id ? 2 : 1,
                            "product_dropshipping_id"    => $variant_dropshipping_id,
                        ];
                    }
                }
                $resultCreateShopify[$shop] = [
                    "precreated" => true,
                    "success" => true,
                    "id_agente" => $id_agente,
                    "shop" => $shop,
                    "send" => $jsonProductForCreate,
                    // "result" => $result,
                    // "variations_put" => $variations_put,
                    "products_refs" => $products_refs,
                    // "products_refs_result" => $products_refs_result,
                ];
                continue;
            }
            try {
                $shopify = new AveConnectShopify($shop, $token);
                $result = $shopify->productGraphQL->post($jsonProductForCreate);
                $variants = $jsonProductForCreate['product']['variants'];
                $productResult = $result['productCreate']['product'] ?? null;
                $product_ref = $productResult ? $productResult['id'] : null;
                $variantsResult = $result['variants'] ?? [];

                $products_refs  = [];
                $products_refs[] = [
                    "product_id"  => $productId,
                    "parent_id"   => null,
                    "product_ref" => $this->normalizeId("$product_ref"),
                    "token_id"    => $token_id,
                    "product_type"    => $product_dropshipping_id ? 2 : 1,
                    "product_dropshipping_id"    => $product_dropshipping_id,
                ];
                for ($j = 0; $j < count($variants); $j++) {
                    $variant_id = $variants[$j]['id'];
                    $variant_dropshipping_id = $variants[$j]['dropshipping_id'] ?? null;
                    $variant_sku = $variants[$j]['sku'];
                    // Buscar en $variantsResult el product_ref que coincida con el sku
                    $product_ref = null;
                    if ($variant_sku && !empty($variantsResult)) {
                        foreach ($variantsResult as $vr) {
                            if (($vr['sku'] ?? null) === $variant_sku) {
                                $product_ref = $vr['id'] ?? null;
                                break;
                            }
                        }
                    }
                    $products_refs[] = [
                        "product_id"  => $variant_id,
                        "parent_id"   => $productId,
                        "product_ref" => $this->normalizeId("$product_ref"),
                        "token_id"    => $token_id,
                        "product_type"    => $variant_dropshipping_id ? 2 : 1,
                        "product_dropshipping_id"    => $variant_dropshipping_id,
                    ];
                }
                $products_refs_result = $this->ave->postProductIdRef($token, $products_refs);

                $resultCreateShopify[$shop] = [
                    "success" => true,
                    "id_agente" => $id_agente,
                    "shop" => $shop,
                    "send" => $jsonProductForCreate,
                    "result" => $result,
                    "variations_put" => $variations_put,
                    "products_refs" => $products_refs,
                    "products_refs_result" => $products_refs_result,
                ];
            } catch (\Throwable $e) {
                $resultCreateShopify[$shop] = [
                    "id_agente" => $id_agente,
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
        string $productId = null,
        ?string $product_dropshipping_id = null
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


        $product_ref_data = null;
        if ($product_dropshipping_id) {
            $products_dropshipping_id = [$product_dropshipping_id];
            for ($j = 0; $j < count($jsonProductForUpdate['product']['variants'] ?? []); $j++) {
                if ($jsonProductForUpdate['product']['variants'][$j]['dropshipping_id']) {
                    $products_dropshipping_id[] = $jsonProductForUpdate['product']['variants'][$j]['dropshipping_id'];
                }
            }

            $product_dropshipping_ref_result = $this->ave->getProductIdRef($token, $products_dropshipping_id);
            $product_ref_data = $product_dropshipping_ref_result['data'];
        }
        if ($product_ref_data == null) {
            $products_id = [$productId];
            for ($j = 0; $j < count($jsonProductForUpdate['product']['variants'] ?? []); $j++) {
                $products_id[] = $jsonProductForUpdate['product']['variants'][$j]['id'];
            }

            $product_ref_result = $this->ave->getProductIdRef($token, $products_id);
            $product_ref_data = $product_ref_result['data'];
        }


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
                $product_exits = false;
                for ($j = 0; $j < count($product_ref_data_filter); $j++) {
                    $product_id = $product_ref_data_filter[$j]['product_id'];
                    $product_ref = $product_ref_data_filter[$j]['product_ref'];

                    if ($jsonProductForUpdate['product']['id'] == $product_id) {
                        $jsonProductForUpdate['product']['id_ave'] = $product_id;
                        $jsonProductForUpdate['product']['id'] = $product_ref;
                        $product_exits = true;
                    } else {
                        for ($k = 0; $k < count($jsonProductForUpdate['product']['variants']); $k++) {
                            if ($jsonProductForUpdate['product']['variants'][$k]['id'] == $product_id) {
                                $jsonProductForUpdate['product']['variants'][$k]['id_ave'] = $product_id;
                                $jsonProductForUpdate['product']['variants'][$k]['id'] = $product_ref;
                            }
                        }
                    }
                }
                $result = null;
                if (!$product_exits) {
                    // $result = $this->post(
                    //     $idempresa,
                    //     $token,
                    //     $productName,
                    //     $productRef,
                    //     $sugerido,
                    //     $peso,
                    //     $unidades,
                    //     $marcaName,
                    //     $categoryName,
                    //     $productStatus,
                    //     $productDesc,
                    //     $etiquetas,
                    //     $variants,
                    //     $url,
                    //     null,
                    //     null,
                    // );
                    $result = "Producto no existe en la tienda Shopify.";
                } else {
                    // El método put de AveConnectShopify requiere el ID del producto como primer parámetro
                    $result = $shopify->productGraphQL->put($jsonProductForUpdate);
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


    public function sync($idempresa, $token, $id, string $status = "completed", string $message = "Sincronizado correctamente")
    {
        // Validar que el productId sea requerido para actualización
        if (empty($id)) {
            throw new \InvalidArgumentException('El ID del producto es requerido para actualizar en Shopify');
        }
        $product_ref_result = $this->ave->getProductIdRef($token, $id);
        $product_ref_data = $product_ref_result['data'];
        $tokensShopify = $this->ave->onGetTokenShopifyByCompany(
            $idempresa,
            $token
        );
        $resultSync = [];
        for ($i = 0; $i < count($tokensShopify); $i++) {
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

                    if ($id == $product_id) {
                        $result = $shopify->productGraphQL->sync(
                            $product_ref,
                            $status,
                            $message,
                        );
                        $resultSync[$shop] = [
                            "shop" => $shop,
                            "shopId" => $shopId,
                            "result" => $result,
                            "success" => true,
                        ];
                    }
                }
            } catch (\Throwable $e) {
                $resultSync[$shop] = [
                    "shop" => $shop,
                    "shopId" => $shopId,
                    "result" => null,
                    "success" => false,
                    "error" => $e->getMessage()
                ];
            }
        }
        return $resultSync;
    }

    /**
     * Actualiza el stock de un producto y sus variantes asociadas en múltiples tiendas Shopify.
     *
     * Este método recibe la empresa, el token de acceso interno, el ID del producto interno
     * y una lista de variantes con sus cantidades. Luego:
     * 
     * 1. Obtiene los tokens de Shopify asociados a la empresa.
     * 2. Obtiene los IDs reales en Shopify para el producto y sus variantes.
     * 3. Construye un paquete de actualizaciones por tienda.
     * 4. Llama al método `putStock()` del módulo GraphQL interno para actualizar 
     *    el stock variante por variante.
     * 5. Genera un informe por cada tienda con el resultado.
     *
     * --- Flujo general ---
     *
     * - Recibe un producto con variantes (del sistema interno).
     * - Traduce esos IDs internos a IDs de Shopify usando `getProductIdRef()`.
     * - Agrupa variantes por tienda según el token.
     * - Actualiza cada variante de forma individual en Shopify usando GraphQL.
     * - Retorna un array con éxito o error por cada tienda procesada.
     *
     * @param string $idempresa   ID de la empresa dueña del producto dentro del sistema interno.
     * @param string $token       Token de autenticación interno para validar acceso.
     * @param string $productId   ID del producto interno cuyo stock se desea actualizar.
     * @param array  $variants    Lista de variantes internas. Cada variante debe incluir:
     *                            - id: ID interno de la variante.
     *                            - quantity: nueva cantidad de inventario.
     *
     * @throws \InvalidArgumentException si el ID del producto está vacío.
     *
     * @return array|null
     *         Array asociativo donde cada key es una tienda Shopify procesada y contiene:
     *         - success (bool)     : Si la tienda procesó correctamente las actualizaciones.
     *         - shop (string)      : URL de la tienda procesada.
     *         - product_id (string): ID interno del producto original.
     *         - send (array)       : Payload enviado a Shopify.
     *         - result (array)     : Resultado de cada llamada a `putStock` por variante.
     *         - product_ref_data (array)          : Datos completos de referencias internos/Shopify.
     *         - product_ref_data_filter (array)   : Datos filtrados por tienda.
     *
     *         Retorna null si no existen tokens de Shopify para la empresa.
     */
    public function putStock(
        string $idempresa,
        string $token,
        string $productId,
        array $variants,
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

        $resultUpdateShopify = [];

        $products_id = [$productId];
        for ($j = 0; $j < count($variants); $j++) {
            $products_id[] = $variants[$j]['id'];
        }
        $product_ref_result = $this->ave->getProductIdRef($token, $products_id);
        $product_ref_data = $product_ref_result['data'];


        for ($i = 0; $i < count($tokensShopify); $i++) {
            $variantsForUpdate = [
                "variants" => []
            ];
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

                    if ($productId == $product_id) {
                        $variantsForUpdate['product_id'] = $product_ref;
                    } else {
                        for ($k = 0; $k < count($variants); $k++) {
                            if ($variants['id'] == $product_id) {
                                $variantsForUpdate['variants'][] = [
                                    "id" => $product_ref,
                                    "quantity" => $variants[$k]['quantity'],
                                ];
                            }
                        }
                    }
                }
                $result = [];
                for ($i = 0; $i < count($variantsForUpdate['variants']); $i++) {
                    $result[] = $shopify->productGraphQL->putStock(
                        $variantsForUpdate['product_id'],
                        $variantsForUpdate['variants'][$i]['id'],
                        $variantsForUpdate['variants'][$i]['quantity'],
                    );
                }

                $resultUpdateShopify[$shop] = [
                    "success" => true,
                    "shop" => $shop,
                    "product_id" => $productId,
                    "send" => $variantsForUpdate,
                    "result" => $result,
                    "product_ref_data" => $product_ref_data,
                    "product_ref_data_filter" => $product_ref_data_filter,
                ];
            } catch (\Throwable $e) {
                $resultUpdateShopify[$shop] = [
                    "shop" => $shop,
                    "product_id" => $productId,
                    "send" => $variantsForUpdate,
                    "result" => null,
                    "success" => false,
                    "error" => $e->getMessage()
                ];
            }
        }

        return $resultUpdateShopify;
    }
}
