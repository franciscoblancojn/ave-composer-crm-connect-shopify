# AveCrmConnectShopify

Conector en **PHP** para interactuar con la API de Shopify de manera sencilla.  
Proporciona clases listas para manejar **Productos** y **Órdenes** usando el cliente HTTP de Shopify.

---

## 📦 Instalación

Instala el paquete mediante **Composer**:

```bash
composer require franciscoblancojn/ave-connect-shopify
```

---

## 🚀 Uso

Inicialización del cliente

```php
<?php
require 'vendor/autoload.php';

use franciscoblancojn\AveCrmConnectShopify\AveCrmConnectShopify;

$shop = 'mi-tienda.myshopify.com';
$token = 'shpat_XXXXXXXXXXXXXXXXXXXX';
$version = '2025-01'; // opcional

$shopify = new AveCrmConnectShopify($shop, $token, $version);
```

---

# 🚀 AveConnect Shopify

Conector en PHP para interactuar fácilmente con la API Admin de **Shopify**.  

---

## ⚙️ Clases principales

### `AveCrmConnectShopify`  
Clase principal que inicializa la conexión y expone:

- `$shopify->product` → para trabajar con productos.  
- `$shopify->order` → para trabajar con órdenes.  

---

### `ShopifyProduct`  
Métodos relacionados con productos:

- `get()`  
- `post(array $data)`  
- `put(array $data)`  
- `delete()`  

---

### `ShopifyOrder`  
Métodos relacionados con órdenes:

- `get()`  
- `post(array $data)`  
- `put(array $data)`  
- `delete()`  

---

## 📖 Requisitos

- PHP >= 8.0  
- Extensión **cURL** habilitada  
- Una tienda Shopify con acceso a la API Admin  

---

## 📝 Licencia

Este proyecto está bajo la licencia **MIT**.  
Eres libre de usarlo, modificarlo y distribuirlo.  
