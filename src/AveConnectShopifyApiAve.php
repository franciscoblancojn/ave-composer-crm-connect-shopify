<?php

namespace franciscoblancojn\AveCrmConnectShopify;

/**
 * Clase para interactuar con la API de AveOnline relacionada con Shopify.
 *
 * Esta clase se encarga de obtener los tokens de las tiendas asociadas a una empresa
 * utilizando la API de AveOnline.
 *
 * Ejemplo de uso:
 *
 * ```php
 * use franciscoblancojn\AveCrmConnectShopify\AveCrmConnectShopifyHttpClient;
 * use franciscoblancojn\AveCrmConnectShopify\AveConnectShopifyApiAve;
 *
 * $httpClient = new AveCrmConnectShopifyHttpClient();
 * $api = new AveConnectShopifyApiAve($httpClient);
 *
 * $idempresa = "12345";
 * $token = "Bearer TU_TOKEN_AQUI";
 *
 * try {
 *     $tokens = $api->getShopsTokens($idempresa, $token);
 *     print_r($tokens);
 * } catch (\Exception $e) {
 *     echo "Error al obtener tokens: " . $e->getMessage();
 * }
 * ```
 */
class AveConnectShopifyApiAve
{
    /**
     * Cliente HTTP para realizar las peticiones.
     *
     * @var AveCrmConnectShopifyHttpClient
     */
    private AveCrmConnectShopifyHttpClient $client;

    /**
     * Constructor de la clase.
     *
     * @param AveCrmConnectShopifyHttpClient $client Cliente HTTP que se inyecta para realizar las solicitudes.
     */
    public function __construct(AveCrmConnectShopifyHttpClient $client)
    {
        $this->client = $client;
    }

    /**
     * Obtiene los tokens de las tiendas asociadas a una empresa en AveOnline.
     *
     * @param string $idempresa ID de la empresa.
     * @param string $token     Token de autenticación (ej. "Bearer ...").
     *
     * @return array|null       Respuesta decodificada como array asociativo, o null si no es JSON.
     *
     * @throws \Exception       Si ocurre un error durante la petición HTTP.
     */
    public function getShopsTokens(
        string $idempresa,
        string $token
    ) {
        return $this->client->request(
            "GET",
            "https://api.aveonline.co/api-shopify/public/api/token/$idempresa",
            ['Authorization: ' . $token]
        );
    }

    /**
     * Obtiene el token de la tienda Shopify asociada a un agente.
     *
     * @param string $idempresa ID de la empresa en AveCRM.
     * @param string $token Token de autenticaci n para AveCRM.
     * @param int $agentId ID del agente en AveCRM.
     *
     * @return array|null Token de la tienda Shopify asociada al agente, o null si no hay
     *
     * @throws \Exception Si ocurre un error durante la petición HTTP
     */
    public function getAgentShopToken(
        string $idempresa,
        string $token,
        int $agentId
    ) {

        return $this->client->request(
            "GET",
            "https://api.aveonline.co/api-shopify/public/api/token/$idempresa/$agentId",
            ['Authorization: ' . $token]
        );
        
    }


    /**
     * Obtiene el token de la tienda Shopify asociada a un agente.
     *
     * @param string $idempresa ID de la empresa en AveCRM
     * @param string $token     Token de autenticaci n para AveCRM
     * @param int $agentId ID del agente en AveCRM
     *
     * @return array|null Token de la tienda Shopify asociada al agente, o null si no hay
     *
     * @throws \Exception Si ocurre un error durante la petici n HTTP
     */
    public function onGetTokenShopifyByCompanyAgent(
        string $idempresa,
        string $token,
        int $agentId
    ) {
        try {
            $data = $this->getAgentShopToken($idempresa, $token, $agentId);
            if (!$data || !$data['data'] || count($data['data']) == 0) {
                return null;
            }
            return $data['data'][0];
        } catch (\Throwable $th) {
            return array(
                'error' => $th->getMessage()
            );
        }
    }


    /**
     * Obtiene los tokens de las tiendas Shopify asociadas a una empresa.
     *
     * @param string $idempresa ID de la empresa en AveCRM
     * @param string $token     Token de autenticación para AveCRM
     *
     * @return array|null Lista de tiendas con URL y token, o null si no hay
     */
    public function onGetTokenShopifyByCompany(
        string $idempresa,
        string $token
    ) {
        try {
            $data = $this->getShopsTokens($idempresa, $token);
            if (!$data || !$data['data'] || count($data['data']) == 0) {
                return null;
            }
            return $data['data'];
        } catch (\Throwable $th) {
            return null;
        }
    }

    /**
     * Procesa un listado de productos para guardar las ids:
     * [
     *   [
     *     "product_id" => 123,
     *     "parent_id" => null,
     *     "product_ref" => "REF-001",
     *     "token_id" => 45
     *   ],
     *   [
     *     "product_id" => 124,
     *     "parent_id" => 123,
     *     "product_ref" => "REF-002",
     *     "token_id" => 45
     *   ]
     * ]
     *
     * @param string $token
     * @param array<int, array{
     *     product_id:int,
     *     parent_id:int|null,
     *     product_ref:string,
     *     token_id:int
     * }> $products
     * @return array Lista normalizada de productos
     */
    public function postProductIdRef(
        string $token,
        array $data
    ) {
        return $this->client->request(
            "POST",
            "https://api.aveonline.co/api-shopify/public/api/productEcommerce",
            ['Authorization: ' . $token],
            $data
        );
    }

    /**
     * Obtener producto 
     *
     * @param string $token
     * @param array<int, int> $products_id
     */
    public function getProductIdRef(
        string $token,
        array $products_id
    ) {
        return $this->client->request(
            "GET",
            "https://api.aveonline.co/api-shopify/public/api/productEcommerce",
            ['Authorization: ' . $token],
            [
                "products_id" => $products_id
            ]
        );
    }

    /**
     * Obtener orden Shopify
     *
     * @param string $token
     * @param string $order_number, número de la orden guardada en el log de Ave
     */
    public function getShopifyOrderNumber(
        string $token,
        string $order_number
    ) {
        return $this->client->request(
            "GET",
            "https://api.aveonline.co/api-shopify/public/api/orders/log/$order_number",
            ['Authorization: ' . $token]
        );
    }
    /**
     * Obtener producto
     *
     * @param string $token
     * @param array<int, int> $product_dropshipping_id
     */
    public function getDropshippingIdRef(
        string $token,
        array $product_dropshipping_id
    ) {
        return $this->client->request(
            "GET",
            "https://api.aveonline.co/api-shopify/public/api/productEcommerce",
            ['Authorization: ' . $token],
            [
                "product_dropshipping_id" => $product_dropshipping_id
            ]
        );
    }
}
