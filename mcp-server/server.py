
# SIGA - Sistema Inteligente de Gestión del Agua
# PC5 - Servidor MCP
# Primera herramienta real: consultar_reclamos

from mcp.server.mcpserver import MCPServer

# Creamos el servidor MCP de SIGA
mcp = MCPServer("SIGA")

# Datos ficticios para probar la herramienta
# Todavía NO estamos conectados a WordPress
reclamos = [
    {
        "id": 1,
        "barrio": "Centro",
        "categoria": "Falta de suministro",
        "estado": "pendiente"
    },
    {
        "id": 2,
        "barrio": "San Vicente",
        "categoria": "Baja presión",
        "estado": "pendiente"
    },
    {
        "id": 3,
        "barrio": "Centro",
        "categoria": "Falta de suministro",
        "estado": "resuelto"
    },
    {
        "id": 4,
        "barrio": "Facundo Quiroga",
        "categoria": "Pérdida de agua",
        "estado": "en proceso"
    },
    {
        "id": 5,
        "barrio": "San Vicente",
        "categoria": "Falta de suministro",
        "estado": "pendiente"
    }
]


@mcp.tool()
def saludar(nombre: str) -> str:
    """Devuelve un saludo personalizado."""
    return f"¡Hola, {nombre}! Bienvenido a SIGA."


@mcp.tool()
def consultar_reclamos() -> dict:
    """
    Consulta los reclamos de prueba de SIGA.

    Devuelve:
    - Cantidad total de reclamos.
    - Cantidad de reclamos pendientes.
    - Cantidad de reclamos por categoría.
    - Cantidad de reclamos por estado.
    """

    total = len(reclamos)

    pendientes = 0
    por_categoria = {}
    por_estado = {}

    for reclamo in reclamos:

        # Contar los reclamos pendientes
        if reclamo["estado"] == "pendiente":
            pendientes += 1

        # Agrupar los reclamos por categoría
        categoria = reclamo["categoria"]

        if categoria not in por_categoria:
            por_categoria[categoria] = 0

        por_categoria[categoria] += 1

        # Agrupar los reclamos por estado
        estado = reclamo["estado"]

        if estado not in por_estado:
            por_estado[estado] = 0

        por_estado[estado] += 1

    return {
        "origen": "datos_ficticios",
        "total_reclamos": total,
        "reclamos_pendientes": pendientes,
        "por_categoria": por_categoria,
        "por_estado": por_estado
    }


# Datos ficticios de medidores y consumos
# Más adelante serán obtenidos desde WordPress

consumos = {
    1: {
        "barrio": "Centro",
        "periodo": "2026-10",
        "lectura_anterior": 120,
        "lectura_actual": 135
    },
    2: {
        "barrio": "San Vicente",
        "periodo": "2026-10",
        "lectura_anterior": 250,
        "lectura_actual": 268
    },
    3: {
        "barrio": "Facundo Quiroga",
        "periodo": "2026-10",
        "lectura_anterior": 310,
        "lectura_actual": 322
    }
}


@mcp.tool()
def consultar_consumo(vivienda_id: int) -> dict:
    """
    Consulta el consumo de agua de una vivienda ficticia.

    Recibe el ID de una vivienda y devuelve
    sus lecturas y el consumo calculado en m3.
    """

    # Comprobar si la vivienda existe
    if vivienda_id not in consumos:
        return {
            "error": "Vivienda no encontrada",
            "vivienda_id": vivienda_id
        }

    # Obtener los datos de la vivienda
    vivienda = consumos[vivienda_id]

    lectura_anterior = vivienda["lectura_anterior"]
    lectura_actual = vivienda["lectura_actual"]

    # Calcular el consumo
    consumo_total = lectura_actual - lectura_anterior

    # Devolver la información
    return {
        "origen": "datos_ficticios",
        "vivienda_id": vivienda_id,
        "barrio": vivienda["barrio"],
        "periodo": vivienda["periodo"],
        "lectura_anterior_m3": lectura_anterior,
        "lectura_actual_m3": lectura_actual,
        "consumo_m3": consumo_total
    }

if __name__ == "__main__":
    mcp.run()
