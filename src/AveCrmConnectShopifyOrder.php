<?php

namespace franciscoblancojn\AveCrmConnectShopify;

use franciscoblancojn\AveConnectShopify\AveConnectShopify;

class AveCrmConnectShopifyOrder
{

    public AveConnectShopifyApiAve $ave;

    public function __construct(AveConnectShopifyApiAve $ave)
    {
        $this->ave = $ave;
    }
    public function getJsonCreateShopifyOrder(
        string $clientEmail,
        string $clientTel,
        string $clientName,
        float $grandTotal,
        float $vat,
        int $pagado,
        array $orderItems = [],           // Estructura con ítems [{...}, {...}]
        array $orderProductName = [],     // Alternativa si los nombres vienen por separado
        array $orderPost = [],            // Datos crudos del request
        array $products = []              // Productos de referencia
    ) {

        $orderItems = $_POST['items'];
        $orderProductName = $_POST['productName'];
        $orderPost = $_POST;
        $orderStructure = [
            "order" => [
                "email" => $clientEmail,
                "phone" => $clientTel,
                "customer" => [
                    "email" => $clientEmail,
                    "first_name" => $clientName, // Podrías necesitar dividir el nombre
                    "last_name" => $clientName, // Extraer apellido del clientName
                    // "phone" => $clientTel
                ],
                "line_items" => [], // Se llena con el bucle de productos
                "transactions" => [
                    [
                        "kind" => "sale",
                        "status" => $pagado == 1 ? "success" : "pending",
                        "amount" => (float) $grandTotal
                    ]
                ],
                "total_tax" => (float) $vat,
                "currency" => "COP" // Asumiendo pesos colombianos
            ]
        ];

        // Llenar line_items basándose en los productos del pedido
        if (isset($orderItems) && count($orderItems) > 0) {
            foreach ($orderItems as $orderItem) {
                $orderStructure['order']['line_items'][] = [
                    "title" => $product['product_name'] ?? 'Producto',
                    "price" => (float) ($orderItem['rateValue'] ?? 0),
                    "grams" => (string) (($orderItem['peso'] ?? 0.5) * 1000), // Convertir kg a gramos
                    "sku" => $orderItem['productRef'] ?? '',
                    "quantity" => (int) ($orderItem['quantity'] ?? 1),
                    "tax_lines" => [
                        [
                            "price" => (float) ($orderItem['ivaValue'] ?? 0),
                            "rate" => (float) (($orderItem['ivaValue'] ?? 0) / 100),
                            "title" => "IVA"
                        ]
                    ]
                ];
            }
        } elseif (isset($orderProductName) && count($orderProductName) > 0) {
            foreach ($orderProductName as $key => $productName) {
                $orderStructure['order']['line_items'][] = [
                    "title" => $products[$key]['product_name'] ?? 'Producto',
                    "price" => (float) ($orderPost['rateValue'][$key] ?? 0),
                    "grams" => (string) (($orderPost['peso'][$key] ?? 0.5) * 1000),
                    "sku" => $products[$key]['product_ref'] ?? '',
                    "quantity" => (int) ($orderPost['quantity'][$key] ?? 1),
                    "tax_lines" => [
                        [
                            "price" => (float) ($orderPost['ivaValue'][$key] ?? 0),
                            "rate" => (float) (($orderPost['ivaValue'][$key] ?? 0) / 100),
                            "title" => "IVA"
                        ]
                    ]
                ];
            }
        }
        return $orderStructure;
    }
    public function post(
        string $idempresa,
        string $token,
        string $clientEmail,
        string $clientTel,
        string $clientName,
        float $grandTotal,
        float $vat,
        int $pagado,
        array $orderItems = [],           // Estructura con ítems [{...}, {...}]
        array $orderProductName = [],     // Alternativa si los nombres vienen por separado
        array $orderPost = [],            // Datos crudos del request
        array $products = []              // Productos de referencia
    ) {
        $tokensShopify = $this->ave->onGetTokenShopifyByCompany(
            $idempresa,          // string
            $token,              // string
        );
        if ($tokensShopify == null) {
            return null;
        }

        $jsonOrderForCreate = $this->getJsonCreateShopifyOrder(
            $clientEmail,
            $clientTel,
            $clientName,
            $grandTotal,
            $vat,
            $pagado,
            $orderItems,
            $orderProductName,
            $orderPost,
            $products
        );
        $resultCreateShopify = [];
        for ($i = 0; $i < count($tokensShopify); $i++) {
            $shop = $tokensShopify[$i]['url'];
            $token = $tokensShopify[$i]['token'];
            try {
                $shopify = new AveConnectShopify($shop, $token);
                $result = $shopify->order->post($jsonOrderForCreate);

                $resultCreateShopify[$shop] = [
                    "shop" => $shop,
                    "send" => $jsonOrderForCreate,
                    "result" => $result,
                ];
            } catch (\Throwable $e) {
                $resultUpdateShopify[$shop] = [
                    "shop" => $shop,
                    "send" => $jsonOrderForCreate,
                    "result" => null,
                    "success" => false,
                    "error" => $e->getMessage()
                ];
            }
        }

        return $resultCreateShopify;
    }

}
