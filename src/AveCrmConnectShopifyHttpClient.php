<?php

namespace franciscoblancojn\AveCrmConnectShopify;

/**
 * Cliente HTTP para interactuar con APIs (ej. Shopify) usando cURL.
 *
 * Esta clase permite realizar solicitudes HTTP con diferentes métodos
 * (GET, POST, PUT, DELETE, etc.) y devuelve la respuesta decodificada en formato array.
 *
 * Ejemplo de uso:
 *
 * ```php
 * $client = new AveCrmConnectShopifyHttpClient();
 * 
 * // Hacer una petición GET
 * $response = $client->request(
 *     "GET",
 *     "https://api.example.com/products",
 *     ["Authorization: Bearer YOUR_TOKEN"]
 * );
 *
 * // Hacer una petición POST con datos
 * $response = $client->request(
 *     "POST",
 *     "https://api.example.com/products",
 *     ["Authorization: Bearer YOUR_TOKEN"],
 *     ["name" => "Producto de prueba", "price" => 1000]
 * );
 * ```
 */
class AveCrmConnectShopifyHttpClient
{
    /**
     * Realiza una petición HTTP con cURL.
     *
     * @param string $method  Método HTTP a usar (GET, POST, PUT, DELETE...).
     * @param string $url     URL completa de la petición.
     * @param array  $headers Encabezados HTTP (opcional). Ej: ["Authorization: Bearer ..."].
     * @param array  $data    Datos a enviar en el cuerpo de la petición (para POST/PUT).
     *
     * @return array|null     Respuesta decodificada como array asociativo, o null si no es JSON.
     *
     * @throws \Exception     Si ocurre un error durante la petición cURL.
     */
    public function request(string $method, string $url, array $headers = [], array $data = [])
    {
        $headers[] = "Content-Type: application/json";

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
        ];

        if (!empty($data)) {
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("Request error: " . $error);
        }

        return json_decode($response, true);
    }
}
