
# SIGA - API HTTP del chatbot
# PC5 - Inteligencia Artificial y MCP

from flask import Flask, request, jsonify
from chatbot import responder

# Crear la aplicación Flask
app = Flask(__name__)


@app.route("/api/chatbot", methods=["POST"])
def consultar_chatbot():
    # Leer el JSON enviado por el cliente
    datos = request.get_json(silent=True)

    # Verificar que recibimos un objeto JSON
    if not isinstance(datos, dict):
        return jsonify({
            "error": "Se esperaba un objeto JSON"
        }), 400

    pregunta = datos.get("pregunta")

    # Validar que sea texto y no esté vacío
    if not isinstance(pregunta, str) or not pregunta.strip():
        return jsonify({
            "error": "La pregunta debe ser un texto no vacío"
        }), 400

    # Evitar preguntas excesivamente largas
    if len(pregunta) > 1000:
        return jsonify({
            "error": "La pregunta supera los 1000 caracteres"
        }), 400

    # Utilizar nuestro chatbot existente
    respuesta = responder(pregunta)

    return jsonify({
        "respuesta": respuesta,
        "origen": "chatbot_prueba"
    })


if __name__ == "__main__":
    # Solo para pruebas locales
    app.run(host="127.0.0.1", port=5000, debug=False)
