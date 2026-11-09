
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
                <th>Acciones</th>
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
                    
                <td>
                    <?php
                    // Buscamos la página de seguimiento por su ID local.
                    $pagina_seguimiento = get_page_by_path(
                        'seguimiento-de-reclamo'
                    );

                    if ($pagina_seguimiento) {

                        // Construimos una URL diferente para cada reclamo.
                        $url_historial = add_query_arg(
                            'reclamo_id',
                            absint($reclamo->id),
                            get_permalink($pagina_seguimiento->ID)
                        );
                        ?>

                        <a href="<?php echo esc_url($url_historial); ?>">
                            Ver seguimiento
                        </a>

                    <?php } else { ?>

                        <span>Seguimiento no disponible</span>

                    <?php } ?>
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

/**
 * SIGA - Panel administrativo de reclamos.
 */

// Agregamos una opción dentro del menú SIGA.
add_action('admin_menu', function () {
    add_submenu_page(
        'siga-panel',
        'Gestión de reclamos',
        'Gestión de reclamos',
        'manage_options',
        'siga-reclamos',
        'siga_panel_gestion_reclamos'
    );
}, 20);


/**
 * Procesar un cambio de estado.
 */
add_action(
    'admin_post_siga_actualizar_reclamo',
    'siga_actualizar_reclamo'
);

function siga_actualizar_reclamo() {

    // Solo un administrador puede realizar cambios.
    if (!current_user_can('manage_options')) {
        wp_die('No tenés permisos para modificar reclamos.');
    }

    $reclamo_id = isset($_POST['reclamo_id'])
        ? absint($_POST['reclamo_id'])
        : 0;

    // Verificamos el token del formulario.
    check_admin_referer(
        'siga_actualizar_' . $reclamo_id,
        'siga_nonce'
    );

    $estado = isset($_POST['estado'])
        ? sanitize_key(wp_unslash($_POST['estado']))
        : '';

    $comentario = isset($_POST['comentario'])
        ? sanitize_textarea_field(
            wp_unslash($_POST['comentario'])
        )
        : '';

    $estados_validos = [
        'pendiente',
        'en_proceso',
        'resuelto'
    ];

    if (
        $reclamo_id <= 0 ||
        !in_array($estado, $estados_validos, true) ||
        mb_strlen($comentario) > 1000
    ) {
        wp_die('Los datos enviados no son válidos.');
    }

    global $wpdb;

    $tabla_reclamos = $wpdb->prefix . 'siga_reclamos';
    $tabla_seguimiento = $wpdb->prefix . 'siga_seguimiento';

    // Iniciamos una transacción para guardar ambas
    // operaciones juntas o cancelar ambas.
    if ($wpdb->query('START TRANSACTION') === false) {
        wp_die('No se pudo iniciar la operación.');
    }

    // Bloqueamos el reclamo durante la actualización.
    $reclamo = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, estado
             FROM {$tabla_reclamos}
             WHERE id = %d FOR UPDATE",
            $reclamo_id
        )
    );

    if (!$reclamo) {
        $wpdb->query('ROLLBACK');
        wp_die('El reclamo no existe.');
    }

    // Evitamos guardar movimientos vacíos.
    if ($reclamo->estado === $estado && $comentario === '') {
        $wpdb->query('ROLLBACK');
        wp_die('No hay cambios para guardar.');
    }

    $fecha = current_time('mysql');

    // Primera operación: actualizar el reclamo.
    $actualizado = $wpdb->update(
        $tabla_reclamos,
        [
            'estado' => $estado,
            'updated_at' => $fecha
        ],
        ['id' => $reclamo_id],
        ['%s', '%s'],
        ['%d']
    );

    // Segunda operación: registrar el historial.
    $seguimiento = false;

    if ($actualizado !== false) {
        $seguimiento = $wpdb->insert(
            $tabla_seguimiento,
            [
                'reclamo_id' => $reclamo_id,
                'actor_id' => get_current_user_id(),
                'estado' => $estado,
                'comentario' => $comentario,
                'created_at' => $fecha
            ],
            ['%d', '%d', '%s', '%s', '%s']
        );
    }

    if ($actualizado === false || $seguimiento === false) {
        $wpdb->query('ROLLBACK');
        wp_die('No se pudo guardar el cambio.');
    }

    if ($wpdb->query('COMMIT') === false) {
        wp_die('No se pudo confirmar la operación.');
    }

    // Volvemos al panel con mensaje de éxito.
    wp_safe_redirect(
        add_query_arg(
            [
                'page' => 'siga-reclamos',
                'actualizado' => '1'
            ],
            admin_url('admin.php')
        )
    );
    exit;
}


/**
 * Mostrar la administración de reclamos.
 */
function siga_panel_gestion_reclamos() {

    if (!current_user_can('manage_options')) {
        wp_die('Acceso denegado.');
    }

    global $wpdb;

    $tabla = $wpdb->prefix . 'siga_reclamos';

    $reclamos = $wpdb->get_results(
        "SELECT id, tipo, descripcion, estado, created_at
         FROM {$tabla}
         ORDER BY created_at DESC, id DESC
         LIMIT 100"
    );

    $estados = [
        'pendiente' => 'Pendiente',
        'en_proceso' => 'En proceso',
        'resuelto' => 'Resuelto'
    ];

    $tipos = [
        'falta_agua' => 'Falta de agua',
        'baja_presion' => 'Baja presión',
        'perdida' => 'Pérdida de agua',
        'otro' => 'Otro problema'
    ];

    echo '<div class="wrap">';
    echo '<h1>SIGA - Gestión de reclamos</h1>';

    if (
        isset($_GET['actualizado']) &&
        sanitize_key(wp_unslash($_GET['actualizado'])) === '1'
    ) {
        echo '<div class="notice notice-success"><p>';
        echo 'Reclamo actualizado correctamente.';
        echo '</p></div>';
    }

    if ($reclamos === null) {
        echo '<p>Error al consultar los reclamos.</p>';
    } elseif (empty($reclamos)) {
        echo '<p>Todavía no hay reclamos registrados.</p>';
    } else {
        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>ID</th>';
        echo '<th>Problema</th>';
        echo '<th>Descripción</th>';
        echo '<th>Estado actual</th>';
        echo '<th>Actualizar</th>';
        echo '</tr></thead><tbody>';

        foreach ($reclamos as $reclamo) {

            echo '<tr>';

            echo '<td>' . esc_html($reclamo->id) . '</td>';

            echo '<td>' . esc_html(
                $tipos[$reclamo->tipo] ?? $reclamo->tipo
            ) . '</td>';

            echo '<td>' . esc_html(
                $reclamo->descripcion
            ) . '</td>';

            echo '<td>' . esc_html(
                $estados[$reclamo->estado] ?? $reclamo->estado
            ) . '</td>';

            echo '<td>';

            echo '<form method="post" action="' .
                esc_url(admin_url('admin-post.php')) . '">';

            echo '<input type="hidden" name="action"
                   value="siga_actualizar_reclamo">';

            echo '<input type="hidden" name="reclamo_id"
                   value="' . esc_attr($reclamo->id) . '">';

            wp_nonce_field(
                'siga_actualizar_' . $reclamo->id,
                'siga_nonce'
            );

            echo '<select name="estado">';

            foreach ($estados as $valor => $etiqueta) {
                echo '<option value="' . esc_attr($valor) . '" ' .
                    selected($reclamo->estado, $valor, false) . '>' .
                    esc_html($etiqueta) .
                    '</option>';
            }

            echo '</select>';

            echo '<p><textarea name="comentario"
                   rows="2" maxlength="1000"
                   placeholder="Observación"></textarea></p>';

            echo '<button type="submit" class="button button-primary">
                    Guardar cambio
                  </button>';

            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    echo '</div>';
}


/**
 * SIGA - Historial de seguimiento de un reclamo.
 */
function siga_mostrar_historial_reclamo() {

    if (!is_user_logged_in()) {
        return '<p>Debés iniciar sesión.</p>';
    }

    global $wpdb;

    $usuario_id = get_current_user_id();
    $tabla_reclamos = $wpdb->prefix . 'siga_reclamos';
    $tabla_seguimiento = $wpdb->prefix . 'siga_seguimiento';

    // Recuperamos el reclamo solicitado.
    $reclamo_id = isset($_GET['reclamo_id'])
        ? absint($_GET['reclamo_id'])
        : 0;

    if (!$reclamo_id) {
        return '<p>Seleccioná un reclamo para ver su historial.</p>';
    }

    // Solo permitimos consultar reclamos propios.
    $reclamo = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, tipo, estado, created_at
             FROM {$tabla_reclamos}
             WHERE id = %d AND usuario_id = %d",
            $reclamo_id,
            $usuario_id
        )
    );

    if (!$reclamo) {
        return '<p>No se encontró el reclamo o no tenés acceso.</p>';
    }

    // Recuperamos los movimientos del reclamo.
    $movimientos = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT estado, comentario, created_at
             FROM {$tabla_seguimiento}
             WHERE reclamo_id = %d
             ORDER BY created_at ASC, id ASC",
            $reclamo_id
        )
    );

    if ($movimientos === null) {
        return '<p>No se pudo consultar el historial.</p>';
    }

    $estados = [
        'pendiente' => 'Pendiente',
        'en_proceso' => 'En proceso',
        'resuelto' => 'Resuelto'
    ];

    ob_start();
    ?>

    <section class="siga-historial">

        <h2>
            Historial del reclamo
            #<?php echo esc_html($reclamo->id); ?>
        </h2>

        <p>
            <strong>Estado actual:</strong>
            <?php
            echo esc_html(
                $estados[$reclamo->estado] ?? $reclamo->estado
            );
            ?>
        </p>

        <h3>Movimientos</h3>

        <ol>
            <li>
                <strong>Reclamo registrado</strong>
                <p>
                    <?php
                    echo esc_html(
                        mysql2date(
                            'd/m/Y H:i',
                            $reclamo->created_at
                        )
                    );
                    ?>
                </p>
                <p>El vecino realizó el reclamo.</p>
            </li>

            <?php foreach ($movimientos as $movimiento): ?>
                <li>
                    <strong>
                        <?php
                        echo esc_html(
                            $estados[$movimiento->estado]
                            ?? $movimiento->estado
                        );
                        ?>
                    </strong>

                    <p>
                        <?php
                        echo esc_html(
                            mysql2date(
                                'd/m/Y H:i',
                                $movimiento->created_at
                            )
                        );
                        ?>
                    </p>

                    <?php if (!empty($movimiento->comentario)): ?>
                        <p>
                            <?php
                            echo esc_html(
                                $movimiento->comentario
                            );
                            ?>
                        </p>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>

    </section>

    <?php
    return ob_get_clean();
}

add_shortcode(
    'siga_historial_reclamo',
    'siga_mostrar_historial_reclamo'
);
