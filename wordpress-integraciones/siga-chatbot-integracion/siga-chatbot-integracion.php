<?php
/**
 * Plugin Name: SIGA - Integración Chatbot
 * Description: Conecta WordPress con el chatbot Python de SIGA.
 * Version: 0.1.0
 * Author: Equipo SIGA - PC5
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ruta REST para conectar WordPress con Flask.
 * Solo disponible para usuarios que pueden
 * administrar opciones de WordPress.
 */
add_action('rest_api_init', function () {
    register_rest_route('siga/v1', '/chatbot', [
        'methods' => 'POST',
        'callback' => 'siga_consultar_chatbot',
        'permission_callback' => function () {
            return current_user_can('manage_options');
        }
    ]);
});

function siga_consultar_chatbot(WP_REST_Request $request) {
    // Leer la pregunta enviada a WordPress
    $pregunta = $request->get_param('pregunta');

    if (!is_string($pregunta) || trim($pregunta) === '') {
        return new WP_Error(
            'pregunta_invalida',
            'Debes escribir una pregunta.',
            ['status' => 400]
        );
    }

    if (mb_strlen($pregunta) > 1000) {
        return new WP_Error(
            'pregunta_larga',
            'La pregunta supera los 1000 caracteres.',
            ['status' => 400]
        );
    }

    // WordPress se comunica con Flask local
    $respuesta = wp_remote_post(
        'http://127.0.0.1:5000/api/chatbot',
        [
            'timeout' => 10,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode([
                'pregunta' => $pregunta
            ])
        ]
    );

    if (is_wp_error($respuesta)) {
        return new WP_Error(
            'chatbot_no_disponible',
            'El servicio de chatbot no está disponible.',
            ['status' => 502]
        );
    }

    $codigo = wp_remote_retrieve_response_code($respuesta);
    $datos = json_decode(
        wp_remote_retrieve_body($respuesta),
        true
    );

    if ($codigo !== 200 || !is_array($datos)
        || !isset($datos['respuesta'])) {
        return new WP_Error(
            'chatbot_error',
            'El chatbot devolvió una respuesta inválida.',
            ['status' => 502]
        );
    }

    return rest_ensure_response($datos);
}

/**
 * Página de pruebas del chatbot.
 * Accesible únicamente para administradores.
 */
add_action('admin_menu', function () {
    add_management_page(
        'Prueba Chatbot SIGA',
        'Prueba Chatbot SIGA',
        'manage_options',
        'siga-prueba-chatbot',
        'siga_pagina_prueba_chatbot'
    );
});

function siga_pagina_prueba_chatbot() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $rest_url = rest_url('siga/v1/chatbot');
    $nonce = wp_create_nonce('wp_rest');
    ?>
    <div class="wrap">
        <h1>Prueba de integración SIGA</h1>

        <p>Enviar una pregunta desde WordPress a Flask.</p>

        <input
            type="text"
            id="siga-pregunta"
            value="Como puedo hacer un reclamo?"
            style="width: 400px; max-width: 100%;"
        >

        <button
            type="button"
            class="button button-primary"
            id="siga-enviar"
        >
            Enviar pregunta
        </button>

        <pre id="siga-resultado"></pre>
    </div>

    <script>
    document.getElementById('siga-enviar').addEventListener(
        'click',
        async function () {
            const resultado = document.getElementById(
                'siga-resultado'
            );

            const pregunta = document.getElementById(
                'siga-pregunta'
            ).value;

            resultado.textContent = 'Consultando chatbot...';

            try {
                const respuesta = await fetch(
                    <?php echo wp_json_encode($rest_url); ?>,
                    {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce':
                                <?php echo wp_json_encode($nonce); ?>
                        },
                        body: JSON.stringify({
                            pregunta: pregunta
                        })
                    }
                );

                const datos = await respuesta.json();

                resultado.textContent = JSON.stringify(
                    datos,
                    null,
                    2
                );

            } catch (error) {
                resultado.textContent =
                    'Error de conexión: ' + error.message;
            }
        }
    );
    </script>
    <?php
}
