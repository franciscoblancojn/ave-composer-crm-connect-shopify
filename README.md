# AVE Composer CRM Connect Shopify

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D7.4-blue.svg)](https://php.net/)
![License](https://img.shields.io/badge/License-MIT-green.svg)

Una librería PHP moderna y eficiente para conectar sistemas CRM con Shopify a través de su API REST y GraphQL. Diseñada para facilitar la sincronización de datos, gestión de pedidos, clientes y productos entre tu CRM y tu tienda Shopify.

## 🚀 Características

- ✅ **Conexión completa con Shopify API REST y GraphQL**
- ✅ **Gestión de productos**
- ✅ **Fácil configuración e integración**

## 📦 Instalación

### Via Composer

```bash
composer require franciscoblancojn/ave-composer-crm-connect-shopify
```

### Instalación Manual

```bash
git clone https://github.com/franciscoblancojn/ave-composer-crm-connect-shopify.git
cd ave-composer-crm-connect-shopify
composer install
```

## ⚡ Inicio Rápido

### Configuración Básica

```php
<?php

require_once 'vendor/autoload.php';

use franciscoblancojn\AveCrmConnectShopify\AveCrmConnectShopify;

$api = new AveCrmConnectShopify();
```

---

# 🚀 AveCrmConnectShopify

Conector en PHP para interactuar fácilmente con la API Admin de **Shopify**.

---

## ⚙️ Clases principales

### `AveCrmConnectShopify`

Clase principal que inicializa la conexión y expone:

- `$api->product` → para trabajar con productos.

---

### `AveCrmConnectShopifyProduct`

Métodos relacionados con productos:

- `post(...arg)`

---

## 📖 Requisitos

- PHP >= 8.0
- Extensión **cURL** habilitada
- Una tienda Shopify con acceso a la API Admin

---

## 📝 Licencia

Este proyecto está bajo la licencia **MIT**.  
Eres libre de usarlo, modificarlo y distribuirlo.
