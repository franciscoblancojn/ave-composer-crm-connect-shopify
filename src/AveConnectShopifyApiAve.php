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
}
