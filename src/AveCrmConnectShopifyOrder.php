<?php

namespace franciscoblancojn\AveCrmConnectShopify;

use franciscoblancojn\AveConnectShopify\AveConnectShopify;

/**
 * Clase para manejar la creación de órdenes en Shopify
 * a partir de la información de un CRM y sincronizarla con
 * múltiples tiendas Shopify asociadas a una empresa.
 */
class AveCrmConnectShopifyOrder
{
    /**
     * Instancia del conector con Ave CRM → Shopify
     *
     * @var AveConnectShopifyApiAve
     */
    public AveConnectShopifyApiAve $ave;

    /**
     * Constructor
     *
     * @param AveConnectShopifyApiAve $ave Instancia del conector Ave
     */
    public function __construct(AveConnectShopifyApiAve $ave)
    {
        $this->ave = $ave;
    }

    /**
     * Genera el JSON con la estructura necesaria para crear
     * una orden en Shopify.
     *
     * @param string $clientEmail     Email del cliente
     * @param string $clientTel       Teléfono del cliente
     * @param string $clientName      Nombre completo del cliente
     * @param float  $grandTotal      Total de la orden
     * @param float  $vat             Valor del IVA
     * @param int    $pagado          1 = pagado, 0 = pendiente
     * @param array  $orderItems      Ítems estructurados de la orden
     * @param array  $orderProductName Lista de nombres de productos
     * @param array  $orderPost       Datos crudos del request
     * @param array  $products        Datos de referencia de productos
     *
     * @return array JSON de la orden listo para enviar a Shopify
     */
    public function getJsonCreateShopifyOrder(
        string $clientEmail,
        string $clientTel,
        string $clientName,
        float $grandTotal,
        float $vat,
        int $pagado,
        array $orderItems = [],
        array $orderProductName = [],
        array $orderPost = [],
        array $products = []
    ) {
        $orderItems = $orderPost['items'];
        $orderProductName = $orderPost['productName'];

        $orderStructure = [
            "order" => [
                "email" => $clientEmail,
                "phone" => $clientTel,
                "customer" => [
                    "email" => $clientEmail,
                    "first_name" => $clientName,
                    "last_name" => $clientName,
                ],
                "line_items" => [],
                "transactions" => [
                    [
                        "kind" => "sale",
                        "status" => $pagado == 1 ? "success" : "pending",
                        "amount" => (float) $grandTotal
                    ]
                ],
                "total_tax" => (float) $vat,
                "currency" => "COP"
            ]
        ];

        // Caso 1: ítems estructurados
        if (isset($orderItems) && count($orderItems) > 0) {
            foreach ($orderItems as $orderItem) {
                $orderStructure['order']['line_items'][] = [
                    "title" => $product['product_name'] ?? 'Producto',
                    "price" => (float) ($orderItem['rateValue'] ?? 0),
                    "grams" => (string) (($orderItem['peso'] ?? 0.5) * 1000),
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
        }
        // Caso 2: nombres de productos por separado
        elseif (isset($orderProductName) && count($orderProductName) > 0) {
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

    /**
     * Envía la orden a todas las tiendas Shopify asociadas a una empresa.
     *
     * @param string $idempresa        ID de la empresa
     * @param string $token            Token de autenticación del CRM
     * @param string $clientEmail      Email del cliente
     * @param string $clientTel        Teléfono del cliente
     * @param string $clientName       Nombre completo del cliente
     * @param float  $grandTotal       Total de la orden
     * @param float  $vat              Valor del IVA
     * @param int    $pagado           1 = pagado, 0 = pendiente
     * @param array  $orderItems       Ítems estructurados de la orden
     * @param array  $orderProductName Lista de nombres de productos
     * @param array  $orderPost        Datos crudos del request
     * @param array  $products         Datos de referencia de productos
     *
     * @return array|null Resultados de creación en cada tienda Shopify
     */
    public function post(
        string $idempresa,
        string $token,
        string $clientEmail,
        string $clientTel,
        string $clientName,
        float $grandTotal,
        float $vat,
        int $pagado,
        array $orderItems = [],
        array $orderProductName = [],
        array $orderPost = [],
        array $products = []
    ) {
        // Obtener tokens de Shopify asociados a la empresa
        $tokensShopify = $this->ave->onGetTokenShopifyByCompany(
            $idempresa,
            $token,
        );

        if ($tokensShopify == null) {
            return null;
        }

        // Generar JSON de la orden
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

        // Enviar orden a cada tienda Shopify asociada
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
                $resultCreateShopify[$shop] = [
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

    /**
     * Cancela la orden en todas la tienda Shopify asociada a una empresa y agente.
     *
     */
    public function cancelOrder(string $orderId, int $companyId, string $token, int $agentId, string $cancelReason = "DECLINED")
    {
        if (empty($orderId)) {
            throw new \InvalidArgumentException("El ID de la orden no puede estar vacio.");
        }
        if (empty($companyId)) {
            throw new \InvalidArgumentException("El ID de la empresa no puede estar vacio.");
        }
        if (empty($token)) {
            throw new \InvalidArgumentException("El token no puede estar vacio.");
        }
        if (empty($agentId)) {
            throw new \InvalidArgumentException("El ID del agente no puede estar vacio.");
        }

        $tokenShopify = $this->ave->onGetTokenShopifyByCompanyAgent(
            $companyId,
            $token,
            $agentId
        );

        if ($tokenShopify == null) {
            return array(
                "error" => "No se encontraron tiendas Shopify asociadas a la empresa y agente.",
            );
        }

        $existingOrder = null;
        $existingOrder = $this->ave->getShopifyOrderNumber($token, $orderId);

        if ($existingOrder == null || empty($existingOrder)) {
            return array(
                "error" => "No se encontraron ordenes de Shopify asociadas al  ID de la orden.",
            );
        }

        $shopifyOrderId = $existingOrder[0]['shopify_order_id'] ?? null;

        if (empty($shopifyOrderId)) {
            return array(
                "error" => "No se encontraron ordenes de Shopify asociadas al  ID de la orden.",
                "orderId" => $orderId
            );
        }


        try {
            $shopify = new AveConnectShopify($tokenShopify['url'], $tokenShopify['token']);
            $cancelResponse = $shopify->orderGraphQL->cancelOrder($shopifyOrderId, $cancelReason);
        } catch (\Throwable $th) {
            //throw $th;
            $cancelResponse = array(
                "error" => $th->getMessage(),
            );
        }
        return $cancelResponse;
    }


    private function getConfigSendDataOrder(
        string $orderId,
        int $companyId,
        string $token,
        int $agentId
    ) {
        if (empty($orderId)) {
            throw new \InvalidArgumentException("El ID de la orden no puede estar vacio.");
        }
        if (empty($companyId)) {
            throw new \InvalidArgumentException("El ID de la empresa no puede estar vacio.");
        }
        if (empty($token)) {
            throw new \InvalidArgumentException("El token no puede estar vacio.");
        }
        if (empty($agentId)) {
            throw new \InvalidArgumentException("El ID del agente no puede estar vacio.");
        }

        $tokenShopify = $this->ave->onGetTokenShopifyByCompanyAgent(
            $companyId,
            $token,
            $agentId
        );
        if ($tokenShopify == null) {
            throw new \InvalidArgumentException("No se encontraron tiendas Shopify asociadas a la empresa y agente.");
        }

        $existingOrder = null;
        $existingOrder = $this->ave->getShopifyOrderNumber($token, $orderId);
        if ($existingOrder == null || empty($existingOrder)) {
            throw new \InvalidArgumentException("No se encontraron ordenes de Shopify asociadas al  ID de la orden.");
        }

        $shopifyOrderId = $existingOrder[0]['shopify_order_id'] ?? null;
        if (empty($shopifyOrderId)) {
            throw new \InvalidArgumentException("No se encontraron ordenes de Shopify asociadas al  ID de la orden.");
        }

        return array(
            "tokenShopify" => $tokenShopify,
            "shopifyOrderId" => $shopifyOrderId,
        );
    }
    public function addNote(
        string $orderId,
        int $companyId,
        string $token,
        int $agentId,
        string $note
    ) {
        try {
            $config = $this->getConfigSendDataOrder(
                $orderId,
                $companyId,
                $token,
                $agentId
            );
            $tokenShopify = $config['tokenShopify'];
            $shopifyOrderId = $config['shopifyOrderId'];

            $shopify = new AveConnectShopify($tokenShopify['url'], $tokenShopify['token']);
            $result = $shopify->orderGraphQL->addNote([
                "order" => [
                    "id" => $shopifyOrderId,
                    "note" => $note
                ]
            ]);
            return array(
                "success" => true,
                "result" => $result
            );
        } catch (\Throwable $th) {
            return array(
                "success" => false,
                "error" => $th->getMessage(),
            );
        }
    }

    public function addTimelineComment(
        string $orderId,
        int $companyId,
        string $token,
        int $agentId,
        string $message
    ) {
        try {
            $config = $this->getConfigSendDataOrder(
                $orderId,
                $companyId,
                $token,
                $agentId
            );
            $tokenShopify = $config['tokenShopify'];
            $shopifyOrderId = $config['shopifyOrderId'];

            $shopify = new AveConnectShopify($tokenShopify['url'], $tokenShopify['token']);
            $result = $shopify->orderGraphQL->addTimelineComment([
                "order" => [
                    "id" => $shopifyOrderId,
                    "message" => $message
                ]
            ]);
            return array(
                "success" => true,
                "result" => $result
            );
        } catch (\Throwable $th) {
            return array(
                "success" => false,
                "error" => $th->getMessage(),
            );
        }
    }


    public function changeStatus(
        string $orderId,
        int $companyId,
        string $token,
        int $agentId,
        string $status
    ) {
        try {
            $config = $this->getConfigSendDataOrder(
                $orderId,
                $companyId,
                $token,
                $agentId
            );

            $tokenShopify = $config['tokenShopify'];
            $shopifyOrderId = $config['shopifyOrderId'];

            $shopify = new AveConnectShopify($tokenShopify['url'], $tokenShopify['token']);

            // 1️⃣ Agregar nota inicial al pedido
            $resultNote = $shopify->orderGraphQL->addNote([
                "order" => [
                    "id" => $shopifyOrderId,
                    "note" => "Nota desde Aveonline - estado de pedido: " . $status,
                ],
            ]);

            $resultChangeStatus = null;

            try {
                // 2️⃣ Normalizar el nombre del status (por si llega con mayúsculas o tildes)
                $statusNormalized = strtolower(trim($status));

                // 3️⃣ Determinar la acción en Shopify según el estado AveOnline
                switch ($statusNormalized) {
                    //OK:
                    case 'solicitada':
                    case 'sin confirmar':
                    case 'pre confirmado':
                    case 'en alistamiento':
                    case 'lista para despacho':
                    case 'procesando guia':
                    case 'en bodega de origen':
                    case 'en devolucion':
                        $resultChangeStatus = $shopify->orderGraphQL->openOrder($shopifyOrderId);
                        break;

                    //OK:
                    case 'en reparto':
                        $resultChangeStatus = $shopify->orderGraphQL->fulfillOrder($shopifyOrderId);
                        break;

                    //OK:
                    case 'entregada':
                        $resultChangeStatus = $shopify->orderGraphQL->closeOrder($shopifyOrderId);
                        break;

                    //OK:
                    case 'anulada':
                    case 'anulada full':
                        $shopify->orderGraphQL->addNote([
                            "order" => [
                                "id" => $shopifyOrderId,
                                "note" => "Pedido marcado como 'Anulado' desde Aveonline",
                            ],
                        ]);
                        $resultChangeStatus = $shopify->orderGraphQL->closeOrder($shopifyOrderId);
                        break;

                    //OK:
                    // ⚙️ Casos informativos (solo nota)
                    case 'modificado por comprador':
                    case 'error en cobertura':
                    case 'direccion incompleta':
                    case 'sin producto':
                    case 'sin inventario':
                    case 'preparado por otro operador':
                    case 'novedad':
                        $resultChangeStatus = $shopify->orderGraphQL->addNote([
                            "order" => [
                                "id" => $shopifyOrderId,
                                "note" => "Pedido con estado: " . ucfirst($statusNormalized),
                            ],
                        ]);
                        break;

                    //OK:
                    default:
                        $resultChangeStatus = [
                            "success" => false,
                            "error" => "Estado no reconocido: {$status}",
                        ];
                        break;
                }
            } catch (\Throwable $th) {
                $resultChangeStatus = [
                    "success" => false,
                    "error" => $th->getMessage(),
                ];
            }

            return [
                "success" => true,
                "resultNote" => $resultNote,
                "resultChangeStatus" => $resultChangeStatus,
            ];
        } catch (\Throwable $th) {
            return [
                "success" => false,
                "error" => $th->getMessage(),
            ];
        }
    }
}
