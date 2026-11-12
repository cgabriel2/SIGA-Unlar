
# SIGA - Clasificador inteligente de reclamos
# PC5 - Inteligencia Artificial y MCP

import os
import json

from dotenv import load_dotenv
from openai import OpenAI

# Leer API key desde .env
load_dotenv()

cliente = OpenAI(
    api_key=os.getenv("OPENAI_API_KEY"),
    timeout=30.0,
    max_retries=0
)

INSTRUCCIONES = """
Sos un asistente de clasificación de reclamos
del Sistema Inteligente de Gestión del Agua (SIGA).

Analizá la descripción de un reclamo y sugerí:

1. Una categoría:
   - Falta de suministro
   - Baja presión
   - Pérdida de agua
   - Otros

2. Una prioridad:
   - Alta
   - Media
   - Baja

3. Una justificación breve de tu clasificación.

Criterios orientativos:
- Alta: posible riesgo inmediato para personas,
  propiedades o infraestructura, según el texto.
- Media: interrupción o problema significativo
  del servicio sin riesgo inmediato evidente.
- Baja: consultas o inconvenientes menores.

Reglas importantes:
- No inventes hechos que no estén en la descripción.
- No afirmes que verificaste el reclamo.
- La clasificación es una sugerencia.
- Siempre requiere revisión de un administrador.
- Respondé únicamente mediante el JSON solicitado.
"""

CATEGORIAS = [
    "Falta de suministro",
    "Baja presión",
    "Pérdida de agua",
    "Otros"
]

PRIORIDADES = ["Alta", "Media", "Baja"]


def clasificar_con_ia(descripcion: str) -> dict:
    """Analiza un reclamo utilizando OpenAI."""

    if not isinstance(descripcion, str):
        return {"error": "La descripción debe ser texto."}

    if not descripcion.strip():
        return {"error": "La descripción está vacía."}

    if len(descripcion) > 2000:
        return {"error": "La descripción es demasiado larga."}

    try:
        respuesta = cliente.responses.create(
            model="gpt-6-luna",
            instructions=INSTRUCCIONES,
            input=descripcion,
            max_output_tokens=350,
            store=False,
            text={
                "format": {
                    "type": "json_schema",
                    "name": "clasificacion_reclamo",
                    "strict": True,
                    "schema": {
                        "type": "object",
                        "properties": {
                            "categoria_sugerida": {
                                "type": "string",
                                "enum": CATEGORIAS
                            },
                            "prioridad_sugerida": {
                                "type": "string",
                                "enum": PRIORIDADES
                            },
                            "justificacion": {
                                "type": "string"
                            },
                            "requiere_revision": {
                                "type": "boolean",
                                "enum": [True]
                            }
                        },
                        "required": [
                            "categoria_sugerida",
                            "prioridad_sugerida",
                            "justificacion",
                            "requiere_revision"
                        ],
                        "additionalProperties": False
                    }
                }
            }
        )

        if respuesta.status != "completed":
            return {
                "error": "La clasificación no se completó."
            }

        if not respuesta.output_text.strip():
            return {
                "error": "La IA no devolvió una clasificación."
            }

        resultado = json.loads(respuesta.output_text)

        # La decisión final siempre corresponde
        # al administrador de SIGA
        resultado["requiere_revision"] = True

        return resultado

    except Exception as error:
        print("Error de OpenAI:", type(error).__name__)

        return {
            "error": "No fue posible clasificar el reclamo."
        }


if __name__ == "__main__":
    print("CLASIFICADOR INTELIGENTE SIGA")
    print("Modo: OpenAI")

    descripcion = input("\nDescribí el reclamo: ")

    resultado = clasificar_con_ia(descripcion)

    print("\nResultado:")
    print(
        json.dumps(
            resultado,
            ensure_ascii=False,
            indent=2
        )
    )
