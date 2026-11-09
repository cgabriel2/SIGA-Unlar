
<?php
if (!defined('ABSPATH')) {
    exit;
}

/*
 * SIGA - Gestión del suministro
 * Etapa 1: registrar cortes programados.
 */

// Crear el submenú de administración.
add_action('admin_menu', 'siga_menu_suministro', 30);

function siga_menu_suministro() {
    add_submenu_page(
        'siga-panel',
        'Gestión del suministro',
        'Gestión del suministro',
        'manage_options',
        'siga-suministro',
        'siga_panel_suministro'
    );
}

// Procesar el formulario.
add_action(
    'admin_post_siga_guardar_corte',
    'siga_guardar_corte'
);

function siga_guardar_corte() {

    // Comprobar permisos.
    if (!current_user_can('manage_options')) {
        wp_die('No tenés permisos para registrar cortes.');
    }

    // Validar el token de seguridad.
    check_admin_referer('siga_crear_corte', 'siga_nonce');

    global $wpdb;

    // Recuperar y limpiar los datos enviados.
    $barrio_id = isset($_POST['barrio_id'])
        ? absint($_POST['barrio_id'])
        : 0;

    $inicio = isset($_POST['inicio'])
        ? sanitize_text_field(wp_unslash($_POST['inicio']))
        : '';

    $fin = isset($_POST['fin'])
        ? sanitize_text_field(wp_unslash($_POST['fin']))
        : '';

    $descripcion = isset($_POST['descripcion'])
        ? sanitize_textarea_field(wp_unslash($_POST['descripcion']))
        : '';

    // Validar fechas reales con la zona horaria de WordPress.
    $validar_fecha = function ($valor) {
        $fecha = DateTimeImmutable::createFromFormat(
            '!Y-m-d\TH:i',
            $valor,
            wp_timezone()
        );

        return $fecha &&
            $fecha->format('Y-m-d\TH:i') === $valor
            ? $fecha
            : false;
    };

    $fecha_inicio = $validar_fecha($inicio);
    $fecha_fin = $validar_fecha($fin);

    // Comprobar que exista el barrio.
    $tabla_barrios = $wpdb->prefix . 'siga_barrios';

    $barrio_existe = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$tabla_barrios} WHERE id = %d",
            $barrio_id
        )
    );

    if (
        !$barrio_existe ||
        !$fecha_inicio ||
        !$fecha_fin ||
        $fecha_fin <= $fecha_inicio ||
        $descripcion === '' ||
        mb_strlen($descripcion) > 2000
    ) {
        wp_die('Datos inválidos. Revisá el formulario.');
    }

    // Insertar el corte en MySQL.
    $tabla_cortes = $wpdb->prefix . 'siga_cortes';

    $guardado = $wpdb->insert(
        $tabla_cortes,
        [
            'barrio_id' => $barrio_id,
            'inicio' => $fecha_inicio->format('Y-m-d H:i:s'),
            'fin_previsto' => $fecha_fin->format('Y-m-d H:i:s'),
            'descripcion' => $descripcion,
            'estado' => 'programado'
        ],
        ['%d', '%s', '%s', '%s', '%s']
    );

    if ($guardado === false) {
        wp_die('No se pudo guardar el corte.');
    }

    wp_safe_redirect(
        admin_url('admin.php?page=siga-suministro&guardado=1')
    );
    exit;
}

// Mostrar el formulario administrativo.
function siga_panel_suministro() {

    if (!current_user_can('manage_options')) {
        wp_die('Acceso denegado.');
    }

    global $wpdb;

    $tabla_barrios = $wpdb->prefix . 'siga_barrios';

    $barrios = $wpdb->get_results(
        "SELECT id, nombre FROM {$tabla_barrios}
         ORDER BY nombre ASC"
    );

    echo '<div class="wrap">';
    echo '<h1>SIGA - Gestión del suministro</h1>';

    if (
        isset($_GET['guardado']) &&
        sanitize_key(wp_unslash($_GET['guardado'])) === '1'
    ) {
        echo '<div class="notice notice-success"><p>';
        echo 'Corte registrado correctamente.';
        echo '</p></div>';
    }

    if (empty($barrios)) {
        echo '<p>Primero necesitás registrar un barrio.</p>';
        echo '</div>';
        return;
    }
    ?>

    <h2>Registrar corte programado</h2>

    <form method="post"
          action="<?php echo esc_url(admin_url('admin-post.php')); ?>">

        <input type="hidden"
               name="action"
               value="siga_guardar_corte">

        <?php wp_nonce_field(
            'siga_crear_corte',
            'siga_nonce'
        ); ?>

        <table class="form-table">

            <tr>
                <th><label for="barrio_id">Barrio afectado</label></th>
                <td>
                    <select name="barrio_id" id="barrio_id" required>
                        <option value="">Seleccionar barrio</option>

                        <?php foreach ($barrios as $barrio): ?>
                            <option value="<?php echo esc_attr($barrio->id); ?>">
                                <?php echo esc_html($barrio->nombre); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <tr>
                <th><label for="inicio">Fecha de inicio</label></th>
                <td>
                    <input type="datetime-local"
                           id="inicio"
                           name="inicio"
                           required>
                </td>
            </tr>

            <tr>
                <th><label for="fin">Finalización estimada</label></th>
                <td>
                    <input type="datetime-local"
                           id="fin"
                           name="fin"
                           required>
                </td>
            </tr>

            <tr>
                <th><label for="descripcion">Descripción</label></th>
                <td>
                    <textarea id="descripcion"
                              name="descripcion"
                              rows="4"
                              cols="50"
                              maxlength="2000"
                              required></textarea>
                </td>
            </tr>

        </table>

        <?php submit_button('Registrar corte'); ?>

    </form>

    <?php

    if (isset($_GET['actualizado']) &&
        sanitize_key(wp_unslash($_GET['actualizado'])) === '1') {
        echo '<div class="notice notice-success"><p>';
        echo 'Estado del corte actualizado correctamente.';
        echo '</p></div>';
    }

    siga_tabla_gestion_cortes();

    echo '</div>';
}

/**
 * SIGA - Consulta pública de cortes de suministro.
 */
function siga_mostrar_avisos_suministro() {

    global $wpdb;

    $tabla_cortes = $wpdb->prefix . 'siga_cortes';
    $tabla_barrios = $wpdb->prefix . 'siga_barrios';

    // Fecha y hora actual, según WordPress.
    $ahora = current_time('mysql');

    // Consultar cortes programados que todavía
    // no alcanzaron su finalización estimada.
    $cortes = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT
                c.id,
                c.inicio,
                c.fin_previsto,
                c.descripcion,
                c.estado,
                b.nombre AS barrio
             FROM {$tabla_cortes} AS c
             INNER JOIN {$tabla_barrios} AS b
                ON c.barrio_id = b.id
             WHERE (
                c.estado = %s
                AND (
                c.fin_previsto IS NULL
                OR c.fin_previsto >= %s
                    )
                )
            OR c.estado = %s
             ORDER BY c.inicio ASC",
            'programado',
            $ahora,
            'en_curso'
        )
    );

    if ($cortes === null) {
        return '<p>No se pudieron consultar los avisos.</p>';
    }

    if (empty($cortes)) {
        return '<p>No hay cortes programados vigentes
                o próximos para mostrar.</p>';
    }

    ob_start();
    ?>

    <div class="siga-avisos">

        <h2>Avisos del suministro de agua</h2>

        <?php foreach ($cortes as $corte): ?>

            <div style="
                border: 1px solid #ddd;
                border-radius: 10px;
                padding: 20px;
                margin-bottom: 15px;
            ">

                <h3>
                    <?php echo esc_html($corte->barrio); ?>
                </h3>

                <p>
                    <strong>Estado:</strong>
                        <?php
                        $estados = [
                            'programado' => 'Programado',
                            'en_curso' => 'En curso'
                        ];

                        echo esc_html(
                            $estados[$corte->estado] ?? $corte->estado
                        );
                        ?>
                </p>

                <p>
                    <strong>Motivo:</strong>
                    <?php echo esc_html($corte->descripcion); ?>
                </p>

                <p>
                    <strong>Inicio:</strong>
                    <?php
                    echo esc_html(
                        mysql2date('d/m/Y H:i', $corte->inicio)
                    );
                    ?>
                </p>

                <p>
                    <strong>Finalización estimada:</strong>
                    <?php
                    echo $corte->fin_previsto
                        ? esc_html(
                            mysql2date(
                                'd/m/Y H:i',
                                $corte->fin_previsto
                            )
                        )
                        : 'Sin estimación';
                    ?>
                </p>

            </div>

        <?php endforeach; ?>

    </div>

    <?php
    return ob_get_clean();
}

// Registrar el shortcode.
add_shortcode(
    'siga_avisos_suministro',
    'siga_mostrar_avisos_suministro'
);


/**
 * SIGA - Actualización del estado de un corte.
 */

add_action(
    'admin_post_siga_actualizar_corte',
    'siga_actualizar_corte'
);

function siga_actualizar_corte() {

    // Solo administradores.
    if (!current_user_can('manage_options')) {
        wp_die('No tenés permisos.');
    }

    $corte_id = isset($_POST['corte_id'])
        ? absint($_POST['corte_id'])
        : 0;

    check_admin_referer(
        'siga_actualizar_corte_' . $corte_id,
        'siga_nonce'
    );

    $estado = isset($_POST['estado'])
        ? sanitize_key(wp_unslash($_POST['estado']))
        : '';

    $estados_validos = [
        'programado',
        'en_curso',
        'finalizado',
        'cancelado'
    ];

    if (
        $corte_id <= 0 ||
        !in_array($estado, $estados_validos, true)
    ) {
        wp_die('Datos inválidos.');
    }

    global $wpdb;

    $tabla = $wpdb->prefix . 'siga_cortes';

    // Comprobar que existe el corte.
    $actual = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT estado FROM {$tabla} WHERE id = %d",
            $corte_id
        )
    );

    if ($actual === null) {
        wp_die('El corte no existe.');
    }

    if ($actual === $estado) {
        wp_safe_redirect(
            admin_url('admin.php?page=siga-suministro&sin_cambios=1')
        );
        exit;
    }

    // Actualizar el estado en MySQL.
    $resultado = $wpdb->update(
        $tabla,
        ['estado' => $estado],
        ['id' => $corte_id],
        ['%s'],
        ['%d']
    );

    if ($resultado === false) {
        wp_die('Error al actualizar el corte.');
    }

    wp_safe_redirect(
        admin_url('admin.php?page=siga-suministro&actualizado=1')
    );
    exit;
}


/**
 * Tabla administrativa de cortes.
 */
function siga_tabla_gestion_cortes() {

    global $wpdb;

    $tabla_cortes = $wpdb->prefix . 'siga_cortes';
    $tabla_barrios = $wpdb->prefix . 'siga_barrios';

    $cortes = $wpdb->get_results(
        "SELECT c.id, c.inicio, c.descripcion,
                c.estado, b.nombre AS barrio
         FROM {$tabla_cortes} c
         INNER JOIN {$tabla_barrios} b
             ON c.barrio_id = b.id
         ORDER BY c.inicio DESC, c.id DESC
         LIMIT 100"
    );

    echo '<h2>Cortes registrados</h2>';

    if ($cortes === null) {
        echo '<p>Error al consultar los cortes.</p>';
        return;
    }

    if (empty($cortes)) {
        echo '<p>No hay cortes registrados.</p>';
        return;
    }

    $estados = [
        'programado' => 'Programado',
        'en_curso' => 'En curso',
        'finalizado' => 'Finalizado',
        'cancelado' => 'Cancelado'
    ];

    echo '<table class="widefat striped">';
    echo '<thead><tr>';
    echo '<th>ID</th><th>Barrio</th>';
    echo '<th>Inicio</th><th>Motivo</th>';
    echo '<th>Estado</th><th>Acción</th>';
    echo '</tr></thead><tbody>';

    foreach ($cortes as $corte) {

        echo '<tr>';
        echo '<td>' . esc_html($corte->id) . '</td>';
        echo '<td>' . esc_html($corte->barrio) . '</td>';
        echo '<td>' . esc_html($corte->inicio) . '</td>';
        echo '<td>' . esc_html($corte->descripcion) . '</td>';
        echo '<td>' . esc_html(
            $estados[$corte->estado] ?? $corte->estado
        ) . '</td>';

        echo '<td>';
        echo '<form method="post" action="' .
            esc_url(admin_url('admin-post.php')) . '">';

        echo '<input type="hidden" name="action"
               value="siga_actualizar_corte">';

        echo '<input type="hidden" name="corte_id"
               value="' . esc_attr($corte->id) . '">';

        wp_nonce_field(
            'siga_actualizar_corte_' . $corte->id,
            'siga_nonce'
        );

        echo '<select name="estado">';

        foreach ($estados as $valor => $nombre) {
            echo '<option value="' . esc_attr($valor) . '" ' .
                selected($corte->estado, $valor, false) . '>' .
                esc_html($nombre) . '</option>';
        }

        echo '</select> ';
        echo '<button type="submit" class="button button-primary">';
        echo 'Actualizar</button>';
        echo '</form></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
}


/**
 * SIGA - Avisos personalizados según los barrios
 * de las viviendas del usuario conectado.
 */
function siga_mostrar_avisos_mi_barrio() {

    // El usuario debe estar autenticado.
    if (!is_user_logged_in()) {
        return '<p>Iniciá sesión para consultar los avisos de tus barrios.</p>';
    }

    global $wpdb;

    $usuario_id = get_current_user_id();

    $tabla_viviendas = $wpdb->prefix . 'siga_viviendas';
    $tabla_barrios = $wpdb->prefix . 'siga_barrios';
    $tabla_cortes = $wpdb->prefix . 'siga_cortes';

    // Comprobamos si tiene viviendas registradas.
    $cantidad_viviendas = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$tabla_viviendas}
             WHERE usuario_id = %d",
            $usuario_id
        )
    );

    if ($cantidad_viviendas == 0) {
        return '<p>Primero necesitás registrar una vivienda para consultar los avisos de tu barrio.</p>';
    }

    $ahora = current_time('mysql');

    // Buscar cortes en los barrios de sus viviendas.
    $cortes = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT
                c.id,
                c.inicio,
                c.fin_previsto,
                c.descripcion,
                c.estado,
                b.nombre AS barrio
             FROM {$tabla_cortes} AS c
             INNER JOIN {$tabla_barrios} AS b
                ON b.id = c.barrio_id
             WHERE (
                 (
                     c.estado = %s
                     AND (
                         c.fin_previsto IS NULL
                         OR c.fin_previsto >= %s
                     )
                 )
                 OR c.estado = %s
             )
             AND EXISTS (
                 SELECT 1
                 FROM {$tabla_viviendas} AS v
                 WHERE v.barrio_id = c.barrio_id
                   AND v.usuario_id = %d
             )
             ORDER BY c.inicio ASC
             LIMIT 100",
            'programado',
            $ahora,
            'en_curso',
            $usuario_id
        )
    );

    if ($cortes === null) {
        return '<p>No se pudieron consultar los avisos.</p>';
    }

    if (empty($cortes)) {
        return '<p>No hay cortes activos o próximos registrados para los barrios de tus viviendas.</p>';
    }

    $estados = [
        'programado' => 'Programado',
        'en_curso' => 'En curso'
    ];

    ob_start();
    ?>

    <div class="siga-avisos-personalizados">

        <h2>Avisos de mis barrios</h2>

        <?php foreach ($cortes as $corte): ?>

            <article style="
                border: 1px solid #ddd;
                border-radius: 10px;
                padding: 20px;
                margin-bottom: 15px;
            ">
                <h3>
                    <?php echo esc_html($corte->barrio); ?>
                </h3>

                <p>
                    <strong>Estado:</strong>
                    <?php echo esc_html(
                        $estados[$corte->estado] ?? $corte->estado
                    ); ?>
                </p>

                <p>
                    <strong>Motivo:</strong>
                    <?php echo esc_html($corte->descripcion); ?>
                </p>

                <p>
                    <strong>Inicio:</strong>
                    <?php echo esc_html(
                        mysql2date('d/m/Y H:i', $corte->inicio)
                    ); ?>
                </p>

                <p>
                    <strong>Finalización estimada:</strong>
                    <?php
                    echo $corte->fin_previsto
                        ? esc_html(
                            mysql2date(
                                'd/m/Y H:i',
                                $corte->fin_previsto
                            )
                        )
                        : 'Sin estimación';
                    ?>
                </p>
            </article>

        <?php endforeach; ?>

    </div>

    <?php
    return ob_get_clean();
}

add_shortcode(
    'siga_avisos_mi_barrio',
    'siga_mostrar_avisos_mi_barrio'
);
