
# SIGA - Chatbot con inteligencia artificial
# PC5 - Inteligencia Artificial y MCP

import os

from dotenv import load_dotenv
from openai import OpenAI

# Cargar la configuración local
load_dotenv()

# Cliente de OpenAI
cliente = OpenAI(
    api_key=os.getenv("OPENAI_API_KEY"),
    timeout=30.0,
    max_retries=0
)

# Instrucciones del chatbot de SIGA
INSTRUCCIONES = """
Sos el asistente virtual de SIGA,
Sistema Inteligente de Gestión del Agua.

SIGA es un prototipo universitario para gestionar
consumos de agua, reclamos y facturación.

Tu función es orientar a los vecinos.

Reglas:
- Respondé en español argentino.
- Usá lenguaje sencillo y respuestas breves.
- Ayudá con reclamos, consumos y facturación.
- No inventes cortes de agua ni avisos oficiales.
- No afirmes haber consultado datos de una vivienda.
- No inventes importes ni estados de reclamos.
- Si necesitás información del sistema que no está
  disponible, explicá que todavía no podés consultarla.
- No solicites contraseñas ni datos bancarios.
"""


def responder_con_ia(pregunta: str) -> str:
    """Genera una respuesta utilizando OpenAI."""

    if not isinstance(pregunta, str) or not pregunta.strip():
        return "Por favor, escribí una pregunta."

    if len(pregunta) > 1000:
        return "La pregunta es demasiado larga."

    try:
        respuesta = cliente.responses.create(
            model="gpt-6-luna",
            instructions=INSTRUCCIONES,
            input=pregunta,
            max_output_tokens=250,
            store=False
        )

        texto = respuesta.output_text.strip()

        if respuesta.status != "completed" or not texto:
            return (
                "No pude generar una respuesta en este "
                "momento. Intentá nuevamente más tarde."
            )

        return texto

    except Exception as error:
        print(
            "Error de OpenAI:",
            type(error).__name__
        )

        return (
            "El asistente de IA no está disponible "
            "en este momento."
        )


if __name__ == "__main__":
    print("CHATBOT INTELIGENTE SIGA")
    print("Escribí 'salir' para finalizar.")

    while True:
        pregunta = input("\nVos: ")

        if pregunta.strip().lower() == "salir":
            print("SIGA: ¡Hasta luego!")
            break

        respuesta = responder_con_ia(pregunta)

        print("\nSIGA:", respuesta)
