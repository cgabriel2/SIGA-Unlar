# SIGA - API HTTP de IA
# PC5 - Inteligencia Artificial y MCP

import os
import hmac

from dotenv import load_dotenv
from flask import Flask, request, jsonify

# Importar nuestros módulos existentes
from chatbot import responder
from clasificador import clasificar_reclamo
from chatbot_ia import responder_con_ia
from clasificador_ia import clasificar_con_ia

app = Flask(__name__)

# SIGA - Protección de endpoints con OpenAI

load_dotenv()

TOKEN_INTERNO = os.getenv("SIGA_INTERNAL_TOKEN")

if not TOKEN_INTERNO:
    raise RuntimeError(
        "Falta SIGA_INTERNAL_TOKEN en el archivo .env"
    )


@app.before_request
def verificar_acceso_ia():
    # Proteger solamente las rutas que consumen OpenAI
    rutas_protegidas = (
        "/api/chatbot-ia",
        "/api/clasificar-ia"
    )

    if request.path not in rutas_protegidas:
        return None

    token_recibido = request.headers.get(
        "X-SIGA-Token", ""
    )

    if not hmac.compare_digest(
        token_recibido,
        TOKEN_INTERNO
    ):
        return jsonify({
            "error": "Acceso no autorizado"
        }), 401

    return None


# Endpoint del chatbot
@app.route("/api/chatbot", methods=["POST"])
def consultar_chatbot():
    datos = request.get_json(silent=True)

    if not isinstance(datos, dict):
        return jsonify({
            "error": "Se esperaba un objeto JSON"
        }), 400

    pregunta = datos.get("pregunta")

    if not isinstance(pregunta, str) or not pregunta.strip():
        return jsonify({
            "error": "La pregunta debe ser un texto no vacío"
        }), 400

    if len(pregunta) > 1000:
        return jsonify({
            "error": "La pregunta supera los 1000 caracteres"
        }), 400

    respuesta = responder(pregunta)

    return jsonify({
        "respuesta": respuesta,
        "origen": "chatbot_prueba"
    })


# Endpoint del clasificador de reclamos
@app.route("/api/clasificar", methods=["POST"])
def clasificar():
    datos = request.get_json(silent=True)

    if not isinstance(datos, dict):
        return jsonify({
            "error": "Se esperaba un objeto JSON"
        }), 400

    descripcion = datos.get("descripcion")

    if not isinstance(descripcion, str) or not descripcion.strip():
        return jsonify({
            "error": "La descripción debe ser un texto no vacío"
        }), 400

    if len(descripcion) > 2000:
        return jsonify({
            "error": "La descripción supera los 2000 caracteres"
        }), 400

    resultado = clasificar_reclamo(descripcion)

    return jsonify({
        "origen": "clasificador_reglas",
        **resultado
    })


# Endpoint del chatbot con OpenAI
@app.route("/api/chatbot-ia", methods=["POST"])
def consultar_chatbot_ia():
    datos = request.get_json(silent=True)

    if not isinstance(datos, dict):
        return jsonify({
            "error": "Se esperaba un objeto JSON"
        }), 400

    pregunta = datos.get("pregunta")

    if not isinstance(pregunta, str) or not pregunta.strip():
        return jsonify({
            "error": "La pregunta debe ser un texto no vacío"
        }), 400

    if len(pregunta) > 1000:
        return jsonify({
            "error": "La pregunta supera los 1000 caracteres"
        }), 400

    respuesta = responder_con_ia(pregunta)

    return jsonify({
        "origen": "openai",
        "respuesta": respuesta
    })


# Endpoint del clasificador con OpenAI
@app.route("/api/clasificar-ia", methods=["POST"])
def clasificar_reclamo_ia():
    datos = request.get_json(silent=True)

    if not isinstance(datos, dict):
        return jsonify({
            "error": "Se esperaba un objeto JSON"
        }), 400

    descripcion = datos.get("descripcion")

    if not isinstance(descripcion, str) or not descripcion.strip():
        return jsonify({
            "error": "La descripción debe ser un texto no vacío"
        }), 400

    if len(descripcion) > 2000:
        return jsonify({
            "error": "La descripción supera los 2000 caracteres"
        }), 400

    resultado = clasificar_con_ia(descripcion)

    if "error" in resultado:
        return jsonify(resultado), 502

    return jsonify({
        "origen": "openai",
        **resultado
    })


if __name__ == "__main__":
    app.run(host="127.0.0.1", port=5000, debug=False)

if __name__ == "__main__":
    # Servicio solamente para desarrollo local
    app.run(
        host="127.0.0.1",
        port=5000,
        debug=False
    )
