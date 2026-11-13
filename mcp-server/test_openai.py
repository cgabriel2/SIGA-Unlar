
# SIGA - Diagnostico de conexion con OpenAI
# PC5 - Inteligencia Artificial y MCP

import os
from dotenv import load_dotenv
from openai import OpenAI

load_dotenv()

api_key = os.getenv("OPENAI_API_KEY")

if not api_key:
    print("ERROR: Falta OPENAI_API_KEY en .env")
    raise SystemExit(1)

cliente = OpenAI(
    api_key=api_key,
    timeout=30.0,
    max_retries=0
)

print("SIGA - Diagnostico de OpenAI")

try:
    respuesta = cliente.responses.create(
        model="gpt-6-luna",
        instructions=(
            "Sos un asistente de SIGA, un proyecto "
            "universitario de gestion del agua. "
            "Responde en espanol con una sola frase."
        ),
        input="Saluda a un vecino de SIGA.",
        max_output_tokens=150,
        store=False
    )

    print("\nEstado:", respuesta.status)
    print("Modelo:", respuesta.model)
    print("ID de respuesta:", respuesta.id)

    print("\nTexto recibido:")
    print(repr(respuesta.output_text))

    print("\nDetalles de error:")
    print(respuesta.error)

    print("\nDetalles de respuesta incompleta:")
    print(respuesta.incomplete_details)

    print("\nContenido de salida:")
    for elemento in respuesta.output:
        print(elemento.model_dump(exclude_none=True))

    print("\nUso de tokens:")
    if respuesta.usage:
        print("Entrada:", respuesta.usage.input_tokens)
        print("Salida:", respuesta.usage.output_tokens)
    else:
        print("No se informo uso de tokens.")

except Exception as error:
    print("\nError de API:")
    print("Tipo:", type(error).__name__)
    print("Mensaje:", str(error))
