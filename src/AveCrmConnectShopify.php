<?php

namespace franciscoblancojn\AveCrmConnectShopify;

use franciscoblancojn\AveConnectShopify\AveConnectShopify;

/**
 * Class AveCrmConnectShopify
 *
 * Clase principal para conectarse Ave CRM a la API de Shopify.
 * Inicializa los recursos principales como shopify,
 * usando un cliente HTTP configurado con la tienda, token y versión de API.
 *
 * @package franciscoblancojn\AveCrmConnectShopify
 */
class AveCrmConnectShopify 
{
    /**
     * Conector con shopify.
     *
     * @var AveConnectShopify
     */
    public AveConnectShopify $shopify;
    /**
     * Conector con Ave.
     *
     * @var AveConnectShopifyApiAve
     */
    public AveConnectShopifyApiAve $ave;
    /**
     * Conector con Shopify con productos.
     *
     * @var AveCrmConnectShopifyProduct
     */
    public AveCrmConnectShopifyProduct $product;

    /**
     * Constructor de la clase AveCrmConnectShopify.
     *
     * @param string $shop    El dominio de la tienda de Shopify (ejemplo: midominio.myshopify.com).
     * @param string $token   Token de acceso para la API de Shopify.
     * @param string $version Versión de la API de Shopify a utilizar. Por defecto '2025-01'.
     */
    public function __construct(string $shop, string $token, $version = '2025-01')
    {
        $client = new AveCrmConnectShopifyHttpClient();
        $this->ave = new AveConnectShopifyApiAve($client);
        $this->shopify = new AveConnectShopify($shop, $token, $version);
        $this->product = new AveCrmConnectShopifyProduct($this->shopify,$this->ave);
    }
}
