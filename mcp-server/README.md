# SIGA — Módulo de Inteligencia Artificial y MCP

**Responsable:** PC5 — Inteligencia Artificial, MCP e integración técnica.

Este módulo agrega funcionalidades de inteligencia artificial al Sistema Inteligente de Gestión del Agua (SIGA).

## 1. Funcionalidades

- **Chatbot básico:** responde preguntas mediante reglas predefinidas.
- **Chatbot con OpenAI:** responde consultas sobre el funcionamiento de SIGA.
- **Clasificador inteligente:** analiza descripciones de reclamos y sugiere categoría, prioridad y justificación.
- **Servidor Flask:** conecta WordPress con los servicios desarrollados en Python.
- **Servidor MCP:** expone herramientas para consultar reclamos y consumos.

**Importante:** las herramientas MCP actualmente utilizan datos simulados. No consultan directamente la base de datos de WordPress.

## 2. Requisitos

- Python 3.12.
- WordPress instalado en XAMPP.
- Apache y MySQL funcionando.
- Clave de API de OpenAI con créditos disponibles.
- Dependencias incluidas en `requirements.txt`.

## 3. Instalación

Abrir Git Bash en la carpeta `mcp-server`.

Crear un entorno virtual:

```bash
python -m venv .venv
```

Activarlo:

```bash
source .venv/Scripts/activate
```

Instalar las dependencias:

```bash
python -m pip install -r requirements.txt
```

Los comandos anteriores están preparados para Git Bash en Windows.

## 4. Configuración

Crear un archivo `.env` dentro de `mcp-server`.

Agregar las siguientes variables:

```env
SIGA_WORDPRESS_URL=http://localhost/siga
OPENAI_API_KEY=TU_CLAVE_PRIVADA
SIGA_INTERNAL_TOKEN=TU_TOKEN_INTERNO
```

Reemplazar los valores de ejemplo con los correspondientes a la instalación.

El token interno debe ser un valor aleatorio, secreto y suficientemente largo.

El mismo token debe configurarse en el archivo `wp-config.php` de WordPress:

```php
define('SIGA_INTERNAL_TOKEN', 'TU_TOKEN_INTERNO');
```

**Seguridad:** nunca subir `.env`, las claves de OpenAI ni los tokens privados a GitHub. Tampoco incluirlos en capturas o documentación.

## 5. Iniciar Flask

Desde la carpeta `mcp-server`, ejecutar:

```bash
python api_chatbot.py
```

El servidor Flask funciona localmente en:

`http://127.0.0.1:5000`

Dejar esta terminal abierta mientras se utilizan las funciones de IA en WordPress.

## 6. Integración con WordPress

El plugin PHP está ubicado en el repositorio, dentro de:

`wordpress-integraciones/siga-chatbot-integracion/`

Para utilizarlo:

1. Copiar la carpeta `siga-chatbot-integracion` a `wp-content/plugins/` de la instalación local de WordPress.
2. Activar el plugin desde el panel de administración.
3. Configurar `SIGA_INTERNAL_TOKEN` en `wp-config.php`.
4. Iniciar Flask.
5. Acceder a WordPress con una cuenta administradora.

Las páginas de prueba están disponibles en:

- **Herramientas → Chatbot IA SIGA**
- **Herramientas → Clasificador IA SIGA**

Estas páginas están restringidas a administradores.

## 7. Prueba del chatbot

Desde **Herramientas → Chatbot IA SIGA**, escribir una pregunta como:

> ¿Cómo puedo informar una pérdida de agua?

Presionar **Consultar con IA**.

El sistema envía la pregunta desde WordPress a Flask, consulta OpenAI y muestra la respuesta.

## 8. Prueba del clasificador

Desde **Herramientas → Clasificador IA SIGA**, escribir:

> Se rompió una cañería en la calle y está saliendo mucha agua.

Presionar **Analizar con IA**.

El sistema devuelve una categoría, prioridad y justificación sugeridas por OpenAI.

La clasificación requiere revisión humana y no modifica automáticamente los reclamos registrados en MySQL.

## 9. Servidor MCP

El archivo `server.py` contiene las herramientas MCP:

- `saludar(nombre)`
- `consultar_reclamos()`
- `consultar_consumo(vivienda_id)`

Las herramientas pueden probarse utilizando un cliente MCP compatible, como MCP Inspector.

Actualmente las consultas de reclamos y consumos utilizan información simulada para fines demostrativos.

## 10. Alcance del trabajo práctico

Este módulo está desarrollado para una demostración académica local.

El chatbot y el clasificador funcionan mediante OpenAI. El servidor MCP permite demostrar herramientas de consulta independientes.

No se implementaron despliegue público, integración de MCP con datos reales, automatización de decisiones administrativas ni controles de producción.

Para realizar consultas a OpenAI se necesita una conexión a Internet y saldo disponible en la API.