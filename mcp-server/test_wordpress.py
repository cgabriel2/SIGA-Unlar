
# SIGA - Prueba de API REST de WordPress
# PC5 - Inteligencia Artificial y MCP

import os
import requests
from dotenv import load_dotenv

# Cargar configuración del archivo .env
load_dotenv()

wordpress_url = os.getenv("SIGA_WORDPRESS_URL")

if not wordpress_url:
    print("ERROR: Falta SIGA_WORDPRESS_URL en .env")
    raise SystemExit(1)

# Consultar publicaciones públicas de WordPress
# Usamos rest_route porque /wp-json/ daba error 404
url = wordpress_url.rstrip("/") + "/"

try:
    respuesta = requests.get(
        url,
        params={
            "rest_route": "/wp/v2/posts",
            "per_page": 5
        },
        timeout=10
    )

    respuesta.raise_for_status()
    publicaciones = respuesta.json()

    print("CONEXION REST EXITOSA")
    print("Publicaciones encontradas:", len(publicaciones))

    for publicacion in publicaciones:
        print("ID:", publicacion["id"])
        print("Titulo:", publicacion["title"]["rendered"])
        print("---")

except requests.exceptions.RequestException as error:
    print("ERROR DE CONEXION:", error)

except (ValueError, KeyError, TypeError) as error:
    print("ERROR AL PROCESAR JSON:", error)
