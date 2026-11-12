
# SIGA - Clasificador de reclamos
# PC5 - Inteligencia Artificial y MCP
# Primera versión: reglas de prueba

def clasificar_reclamo(descripcion: str) -> dict:

    texto = descripcion.lower()

    # Categoría sugerida
    if "perdida" in texto or "pérdida" in texto \
            or "fuga" in texto:
        categoria = "Pérdida de agua"

    elif "presion" in texto or "presión" in texto:
        categoria = "Baja presión"

    elif "sin agua" in texto or "no tengo agua" in texto:
        categoria = "Falta de suministro"

    else:
        categoria = "Otros"

    # Prioridad sugerida
    if "inundando" in texto or "inundación" in texto \
            or "muy grande" in texto:
        prioridad = "Alta"

    elif categoria == "Falta de suministro":
        prioridad = "Media"

    else:
        prioridad = "Baja"

    return {
        "categoria_sugerida": categoria,
        "prioridad_sugerida": prioridad,
        "requiere_revision": True
    }


if __name__ == "__main__":
    print("CLASIFICADOR SIGA - MODO PRUEBA")

    descripcion = input("Describí el reclamo: ")

    resultado = clasificar_reclamo(descripcion)

    print("\nResultado:")
    print("Categoría:", resultado["categoria_sugerida"])
    print("Prioridad:", resultado["prioridad_sugerida"])
    print("Requiere revisión:", resultado["requiere_revision"])
