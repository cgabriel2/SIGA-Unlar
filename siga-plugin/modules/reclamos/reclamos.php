
<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SIGA - Módulo de reclamos.
 * Procesa el formulario y guarda el reclamo.
 */
add_action(
    'admin_post_siga_guardar_reclamo',
    'siga_guardar_reclamo'
);

function siga_guardar_reclamo() {

    // Solo los usuarios autenticados pueden registrar reclamos.
    if (!is_user_logged_in()) {
        wp_die('Debés iniciar sesión.');
    }

    // Comprobamos que la solicitud tenga un token válido.
    $nonce = isset($_POST['siga_nonce'])
        ? sanitize_text_field(wp_unslash($_POST['siga_nonce']))
        : '';

    if (!wp_verify_nonce($nonce, 'siga_crear_reclamo')) {
        wp_die('Error de seguridad. Volvé a intentar.');
    }

    global $wpdb;

    $usuario_id = get_current_user_id();

    $vivienda_id = isset($_POST['vivienda_id'])
        ? absint($_POST['vivienda_id'])
        : 0;

    $tipo = isset($_POST['tipo_reclamo'])
        ? sanitize_key(wp_unslash($_POST['tipo_reclamo']))
        : '';

    $descripcion = isset($_POST['descripcion'])
        ? sanitize_textarea_field(
            wp_unslash($_POST['descripcion'])
        )
        : '';

    // Tipos de problemas permitidos.
    $tipos_validos = [
        'falta_agua',
        'baja_presion',
        'perdida',
        'otro'
    ];

    $resultado = 'error';

    if (
        $vivienda_id > 0 &&
        in_array($tipo, $tipos_validos, true) &&
        !empty($descripcion) &&
        mb_strlen($descripcion) <= 2000
    ) {

        // Verificamos que la vivienda pertenezca al usuario.
        $tabla_viviendas = $wpdb->prefix . 'siga_viviendas';

        $existe = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$tabla_viviendas}
                 WHERE id = %d AND usuario_id = %d",
                $vivienda_id,
                $usuario_id
            )
        );

        if ($existe) {

            $tabla_reclamos = $wpdb->prefix . 'siga_reclamos';

            $fecha = current_time('mysql');

            // Insertamos el reclamo en MySQL.
            $guardado = $wpdb->insert(
                $tabla_reclamos,
                [
                    'usuario_id'  => $usuario_id,
                    'vivienda_id' => $vivienda_id,
                    'tipo'        => $tipo,
                    'descripcion' => $descripcion,
                    'estado'      => 'pendiente',
                    'created_at'  => $fecha,
                    'updated_at'  => $fecha
                ],
                [
                    '%d', '%d', '%s', '%s',
                    '%s', '%s', '%s'
                ]
            );

            if ($guardado !== false) {
                $resultado = 'ok';
            }
        }
    }

    // Volvemos a la página del formulario.
    $url = wp_get_referer();

    if (!$url) {
        $url = home_url('/');
    }

    $url = add_query_arg(
        'siga_resultado',
        $resultado,
        $url
    );

    wp_safe_redirect($url);
    exit;
}

/**
 * Muestra el formulario usando un shortcode.
 */
function siga_mostrar_formulario_reclamo() {

    if (!is_user_logged_in()) {
        return '<p>Debés iniciar sesión para registrar un reclamo.</p>';
    }

    global $wpdb;

    $usuario_id = get_current_user_id();
    $tabla = $wpdb->prefix . 'siga_viviendas';

    $viviendas = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, direccion
             FROM {$tabla}
             WHERE usuario_id = %d",
            $usuario_id
        )
    );

    if (empty($viviendas)) {
        return '<p>Primero necesitás registrar una vivienda.</p>';
    }

    ob_start();

    $resultado = isset($_GET['siga_resultado'])
        ? sanitize_key(wp_unslash($_GET['siga_resultado']))
        : '';

    if ($resultado === 'ok') {
        echo '<p style="color:green;">
                Reclamo registrado correctamente.
              </p>';
    } elseif ($resultado === 'error') {
        echo '<p style="color:red;">
                No se pudo registrar el reclamo.
                Revisá los datos ingresados.
              </p>';
    }
    ?>

    <h2>Registrar un reclamo</h2>

    <form method="post"
          action="<?php echo esc_url(admin_url('admin-post.php')); ?>">

        <input type="hidden"
               name="action"
               value="siga_guardar_reclamo">

        <?php wp_nonce_field(
            'siga_crear_reclamo',
            'siga_nonce'
        ); ?>

        <p>
            <label for="vivienda_id">Vivienda:</label>

            <select name="vivienda_id"
                    id="vivienda_id"
                    required>
                <option value="">Seleccionar vivienda</option>

                <?php foreach ($viviendas as $vivienda): ?>
                    <option value="<?php echo esc_attr($vivienda->id); ?>">
                        <?php echo esc_html($vivienda->direccion); ?>
                    </option>
                <?php endforeach; ?>

            </select>
        </p>

        <p>
            <label for="tipo_reclamo">Tipo de problema:</label>

            <select name="tipo_reclamo"
                    id="tipo_reclamo"
                    required>
                <option value="">Seleccionar problema</option>
                <option value="falta_agua">Falta de agua</option>
                <option value="baja_presion">Baja presión</option>
                <option value="perdida">Pérdida de agua</option>
                <option value="otro">Otro problema</option>
            </select>
        </p>

        <p>
            <label for="descripcion">Descripción:</label>

            <textarea name="descripcion"
                      id="descripcion"
                      rows="5"
                      maxlength="2000"
                      required></textarea>
        </p>

        <p>
            <button type="submit">
                Registrar reclamo
            </button>
        </p>

    </form>

    <?php
    return ob_get_clean();
}

add_shortcode(
    'siga_nuevo_reclamo',
    'siga_mostrar_formulario_reclamo'
);


/**
 * SIGA - Mostrar los reclamos del usuario.
 */
function siga_mostrar_mis_reclamos() {

    if (!is_user_logged_in()) {
        return '<p>Debés iniciar sesión para consultar tus reclamos.</p>';
    }

    global $wpdb;

    $usuario_id = get_current_user_id();
    $tabla = $wpdb->prefix . 'siga_reclamos';

    // Consultamos los reclamos del usuario conectado.
    $reclamos = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, tipo, descripcion, estado, created_at
             FROM {$tabla}
             WHERE usuario_id = %d
             ORDER BY created_at DESC, id DESC",
            $usuario_id
        )
    );

    if ($reclamos === null) {
        return '<p>No se pudieron consultar los reclamos.</p>';
    }

    if (empty($reclamos)) {
        return '<p>Todavía no registraste ningún reclamo.</p>';
    }

    $tipos = [
        'falta_agua'   => 'Falta de agua',
        'baja_presion' => 'Baja presión',
        'perdida'      => 'Pérdida de agua',
        'otro'         => 'Otro problema'
    ];

    $estados = [
        'pendiente'  => 'Pendiente',
        'en_proceso' => 'En proceso',
        'resuelto'   => 'Resuelto'
    ];

    ob_start();
    ?>

    <h2>Mis reclamos</h2>

    <table style="width:100%; border-collapse:collapse;">
        <thead>
            <tr>
                <th>N.º</th>
                <th>Problema</th>
                <th>Descripción</th>
                <th>Estado</th>
                <th>Fecha</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($reclamos as $reclamo): ?>
                <tr>
                    <td><?php echo esc_html($reclamo->id); ?></td>

                    <td>
                        <?php
                        echo esc_html(
                            $tipos[$reclamo->tipo]
                            ?? $reclamo->tipo
                        );
                        ?>
                    </td>

                    <td>
                        <?php
                        echo esc_html(
                            $reclamo->descripcion
                        );
                        ?>
                    </td>

                    <td>
                        <?php
                        echo esc_html(
                            $estados[$reclamo->estado]
                            ?? $reclamo->estado
                        );
                        ?>
                    </td>

                    <td>
                        <?php
                        echo esc_html(
                            mysql2date(
                                'd/m/Y H:i',
                                $reclamo->created_at
                            )
                        );
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php

    return ob_get_clean();
}

// Registramos el shortcode.
add_shortcode(
    'siga_mis_reclamos',
    'siga_mostrar_mis_reclamos'
);
