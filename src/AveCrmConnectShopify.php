<?php

namespace franciscoblancojn\AveCrmConnectShopify;


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
     * Conector con Shopify con productos.
     *
     * @var AveCrmConnectShopifyProduct
     */
    public AveCrmConnectShopifyProduct $product;
    /**
     * Conector con Shopify con order.
     *
     * @var AveCrmConnectShopifyOrder
     */
    public AveCrmConnectShopifyOrder $order;

    /**
     * Constructor de la clase AveCrmConnectShopify.
     *
     * @param string $shop    El dominio de la tienda de Shopify (ejemplo: midominio.myshopify.com).
     * @param string $token   Token de acceso para la API de Shopify.
     * @param string $version Versión de la API de Shopify a utilizar. Por defecto '2025-07'.
     */
    public function __construct()
    {
        $client = new AveCrmConnectShopifyHttpClient();
        $ave = new AveConnectShopifyApiAve($client);
        $this->product = new AveCrmConnectShopifyProduct($ave);
        $this->order = new AveCrmConnectShopifyOrder($ave);
    }
}
