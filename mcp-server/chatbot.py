
# SIGA - Chatbot de prueba
# PC5 - Inteligencia Artificial y MCP

def responder(pregunta: str) -> str:
    texto = pregunta.lower()

    if "reclamo" in texto:
        return (
            "Para realizar un reclamo, ingresá a la "
            "sección Reclamos de SIGA y describí "
            "el problema."
        )

    elif "consumo" in texto:
        return (
            "Podrás consultar el consumo de agua "
            "de tu vivienda desde la sección Consumos."
        )

    elif "factura" in texto:
        return (
            "Podrás consultar tus facturas desde "
            "la sección Facturación de SIGA."
        )

    else:
        return (
            "Soy el asistente de SIGA. Puedo ayudarte "
            "con preguntas sobre reclamos, consumos "
            "y facturación."
        )


if __name__ == "__main__":
    print("CHATBOT SIGA - MODO PRUEBA")
    print("Escribí 'salir' para finalizar.")

    while True:
        pregunta = input("\nVos: ")

        if pregunta.lower() == "salir":
            print("SIGA: ¡Hasta luego!")
            break

        respuesta = responder(pregunta)
        print("SIGA:", respuesta)
