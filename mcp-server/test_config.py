
# SIGA - Prueba de configuración
# PC5 - Inteligencia Artificial y MCP

import os
from dotenv import load_dotenv

# Leer variables desde el archivo .env
load_dotenv()

# Obtener la dirección de WordPress
wordpress_url = os.getenv("SIGA_WORDPRESS_URL")

if wordpress_url:
    print("Configuración cargada correctamente")
    print("URL de WordPress:", wordpress_url)
else:
    print("ERROR: No se encontró SIGA_WORDPRESS_URL")
