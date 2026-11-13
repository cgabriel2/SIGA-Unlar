<?php
/**
 * SIGA - Módulo Medidores
 * Archivo: modules/consumos/medidores.php
 *
 * Administración de medidores (alta, edición, activar/desactivar) y vista de solo lectura para usuarios.
 * Shortcode: [siga_medidores]
 *
 * Tablas: siga_medidores (id, vivienda_id, numero, estado), siga_viviendas, siga_lecturas, wp users
 * Los medidores NO se borran: tienen lecturas asociadas. Se desactivan (estado = 'inactivo').
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* =========================================================
 * 1. FUNCIONES DE DATOS
 * ========================================================= */

/** Estados permitidos para un medidor. */
function siga_estados_medidor() {
    return array( 'activo', 'inactivo' );
}

/** Todas las viviendas con su titular (para el selector del formulario). */
function siga_viviendas_listado() {
    global $wpdb;
    return $wpdb->get_results(
        "SELECT v.id, v.direccion, v.usuario_id, u.display_name
         FROM {$wpdb->prefix}siga_viviendas v
         LEFT JOIN {$wpdb->users} u ON u.ID = v.usuario_id
         ORDER BY v.direccion ASC",
        ARRAY_A
    );
}

/** Un medidor por ID. */
function siga_obtener_medidor( $id ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}siga_medidores WHERE id = %d",
        (int) $id
    ), ARRAY_A );
}

/** Listado completo para el administrador, con la última lectura de cada medidor. */
function siga_medidores_todos() {
    global $wpdb;
    $p = $wpdb->prefix;

    return $wpdb->get_results(
        "SELECT m.id, m.numero, m.estado, m.vivienda_id, v.direccion, u.display_name,
            (SELECT l.lectura_m3 FROM {$p}siga_lecturas l WHERE l.medidor_id = m.id ORDER BY l.fecha DESC, l.id DESC LIMIT 1) AS ultima_lectura,
            (SELECT l.fecha      FROM {$p}siga_lecturas l WHERE l.medidor_id = m.id ORDER BY l.fecha DESC, l.id DESC LIMIT 1) AS ultima_fecha
         FROM {$p}siga_medidores m
         INNER JOIN {$p}siga_viviendas v ON v.id = m.vivienda_id
         LEFT JOIN {$wpdb->users} u ON u.ID = v.usuario_id
         ORDER BY m.id ASC",
        ARRAY_A
    );
}

/** Medidores del usuario logueado (activos e inactivos), con su última lectura. */
function siga_medidores_con_lectura_usuario( $usuario_id = 0 ) {
    global $wpdb;
    $p          = $wpdb->prefix;
    $usuario_id = $usuario_id ? (int) $usuario_id : get_current_user_id();

    return $wpdb->get_results( $wpdb->prepare(
        "SELECT m.id, m.numero, m.estado, v.direccion,
            (SELECT l.lectura_m3 FROM {$p}siga_lecturas l WHERE l.medidor_id = m.id ORDER BY l.fecha DESC, l.id DESC LIMIT 1) AS ultima_lectura,
            (SELECT l.fecha      FROM {$p}siga_lecturas l WHERE l.medidor_id = m.id ORDER BY l.fecha DESC, l.id DESC LIMIT 1) AS ultima_fecha
         FROM {$p}siga_medidores m
         INNER JOIN {$p}siga_viviendas v ON v.id = m.vivienda_id
         WHERE v.usuario_id = %d
         ORDER BY m.id ASC",
        $usuario_id
    ), ARRAY_A );
}

/** Valida vivienda y número. Devuelve true o WP_Error. $id_actual excluye al propio medidor al editar. */
function siga_validar_medidor( $vivienda_id, $numero, $id_actual = 0 ) {
    global $wpdb;

    if ( $numero === '' ) {
        return new WP_Error( 'numero_vacio', 'El número de medidor es obligatorio.' );
    }

    $vivienda = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}siga_viviendas WHERE id = %d",
        (int) $vivienda_id
    ) );
    if ( ! $vivienda ) {
        return new WP_Error( 'vivienda_invalida', 'La vivienda seleccionada no existe.' );
    }

    $duplicado = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}siga_medidores WHERE numero = %s AND id <> %d",
        $numero,
        (int) $id_actual
    ) );
    if ( $duplicado ) {
        return new WP_Error( 'numero_duplicado', 'Ya existe un medidor con ese número.' );
    }

    return true;
}

function siga_crear_medidor( $vivienda_id, $numero ) {
    global $wpdb;
    $numero = sanitize_text_field( $numero );

    $valido = siga_validar_medidor( $vivienda_id, $numero );
    if ( is_wp_error( $valido ) ) {
        return $valido;
    }

    $ok = $wpdb->insert(
        $wpdb->prefix . 'siga_medidores',
        array( 'vivienda_id' => (int) $vivienda_id, 'numero' => $numero, 'estado' => 'activo' ),
        array( '%d', '%s', '%s' )
    );

    return $ok ? $wpdb->insert_id : new WP_Error( 'error_db', 'No se pudo crear el medidor.' );
}

function siga_actualizar_medidor( $id, $vivienda_id, $numero ) {
    global $wpdb;
    $numero = sanitize_text_field( $numero );

    if ( ! siga_obtener_medidor( $id ) ) {
        return new WP_Error( 'no_existe', 'El medidor no existe.' );
    }

    $valido = siga_validar_medidor( $vivienda_id, $numero, $id );
    if ( is_wp_error( $valido ) ) {
        return $valido;
    }

    $wpdb->update(
        $wpdb->prefix . 'siga_medidores',
        array( 'vivienda_id' => (int) $vivienda_id, 'numero' => $numero ),
        array( 'id' => (int) $id ),
        array( '%d', '%s' ),
        array( '%d' )
    );

    return true;
}

function siga_cambiar_estado_medidor( $id, $estado ) {
    global $wpdb;

    if ( ! in_array( $estado, siga_estados_medidor(), true ) ) {
        return new WP_Error( 'estado_invalido', 'Estado no válido.' );
    }
    if ( ! siga_obtener_medidor( $id ) ) {
        return new WP_Error( 'no_existe', 'El medidor no existe.' );
    }

    $wpdb->update(
        $wpdb->prefix . 'siga_medidores',
        array( 'estado' => $estado ),
        array( 'id' => (int) $id ),
        array( '%s' ),
        array( '%d' )
    );

    return true;
}

/* =========================================================
 * 2. ACCIONES (admin-post): solo administradores
 * ========================================================= */

function siga_med_verificar( $accion_nonce ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'No tenés permisos para esta acción.' );
    }
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], $accion_nonce ) ) {
        wp_die( 'Solicitud no válida.' );
    }
    return wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['volver'] ?? '' ) ), home_url( '/' ) );
}

/** Alta o edición (si viene medidor_id). */
function siga_accion_guardar_medidor() {
    $volver      = siga_med_verificar( 'siga_guardar_medidor' );
    $medidor_id  = (int) ( $_POST['medidor_id'] ?? 0 );
    $vivienda_id = (int) ( $_POST['vivienda_id'] ?? 0 );
    $numero      = wp_unslash( $_POST['numero'] ?? '' );

    $r = $medidor_id
        ? siga_actualizar_medidor( $medidor_id, $vivienda_id, $numero )
        : siga_crear_medidor( $vivienda_id, $numero );

    $args = is_wp_error( $r )
        ? array( 'siga_med' => 'err', 'siga_cod' => $r->get_error_code() )
        : array( 'siga_med' => $medidor_id ? 'editado' : 'creado' );

    wp_safe_redirect( add_query_arg( $args, remove_query_arg( 'siga_editar_medidor', $volver ) ) );
    exit;
}
add_action( 'admin_post_siga_guardar_medidor', 'siga_accion_guardar_medidor' );

/** Activar / desactivar. */
function siga_accion_estado_medidor() {
    $volver = siga_med_verificar( 'siga_estado_medidor' );
    $r      = siga_cambiar_estado_medidor(
        (int) ( $_POST['medidor_id'] ?? 0 ),
        sanitize_key( $_POST['estado'] ?? '' )
    );

    $args = is_wp_error( $r )
        ? array( 'siga_med' => 'err', 'siga_cod' => $r->get_error_code() )
        : array( 'siga_med' => 'estado' );

    wp_safe_redirect( add_query_arg( $args, $volver ) );
    exit;
}
add_action( 'admin_post_siga_estado_medidor', 'siga_accion_estado_medidor' );

/* =========================================================
 * 3. SHORTCODE [siga_medidores]
 * ========================================================= */

function siga_medidores_mensaje() {
    if ( ! isset( $_GET['siga_med'] ) ) {
        return '';
    }
    $ok = array(
        'creado'  => 'Medidor creado correctamente.',
        'editado' => 'Medidor actualizado correctamente.',
        'estado'  => 'Estado del medidor actualizado.',
    );
    $errores = array(
        'numero_vacio'      => 'El número de medidor es obligatorio.',
        'vivienda_invalida' => 'La vivienda seleccionada no existe.',
        'numero_duplicado'  => 'Ya existe un medidor con ese número.',
        'no_existe'         => 'El medidor no existe.',
        'estado_invalido'   => 'Estado no válido.',
        'error_db'          => 'No se pudo guardar. Intentá nuevamente.',
    );

    $clave = sanitize_key( $_GET['siga_med'] );
    if ( $clave === 'err' ) {
        $cod = sanitize_key( $_GET['siga_cod'] ?? '' );
        return '<p style="color:#b00">' . esc_html( $errores[ $cod ] ?? 'Ocurrió un error.' ) . '</p>';
    }
    return isset( $ok[ $clave ] ) ? '<p style="color:#080">' . esc_html( $ok[ $clave ] ) . '</p>' : '';
}

function siga_fmt_ultima_lectura( $fila ) {
    if ( $fila['ultima_lectura'] === null ) {
        return 'Sin lecturas';
    }
    return number_format( (float) $fila['ultima_lectura'], 3, ',', '.' ) . ' m³ (' . gmdate( 'd/m/Y', strtotime( $fila['ultima_fecha'] ) ) . ')';
}

function siga_shortcode_medidores() {
    if ( ! is_user_logged_in() ) {
        return '<p>Iniciá sesión para ver tus medidores.</p>';
    }

    $volver = get_permalink();
    ob_start();
    echo siga_medidores_mensaje();

    /* ---------- Vista de usuario (solo lectura) ---------- */
    if ( ! current_user_can( 'manage_options' ) ) {
        $medidores = siga_medidores_con_lectura_usuario();
        if ( empty( $medidores ) ) {
            echo '<p>No tenés medidores asociados a tu cuenta.</p>';
            return ob_get_clean();
        }
        ?>
        <table>
            <tr><th>Medidor</th><th>Domicilio</th><th>Estado</th><th>Última lectura</th></tr>
            <?php foreach ( $medidores as $m ) : ?>
                <tr>
                    <td><?php echo esc_html( $m['numero'] ); ?></td>
                    <td><?php echo esc_html( $m['direccion'] ); ?></td>
                    <td><?php echo esc_html( ucfirst( $m['estado'] ) ); ?></td>
                    <td><?php echo esc_html( siga_fmt_ultima_lectura( $m ) ); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        <?php
        return ob_get_clean();
    }

    /* ---------- Vista de administrador ---------- */
    $viviendas = siga_viviendas_listado();
    $editando  = isset( $_GET['siga_editar_medidor'] ) ? siga_obtener_medidor( (int) $_GET['siga_editar_medidor'] ) : null;

    if ( empty( $viviendas ) ) {
        echo '<p>Primero tiene que existir al menos una vivienda para poder asociar un medidor.</p>';
    } else {
        ?>
        <h4><?php echo $editando ? 'Editar medidor' : 'Nuevo medidor'; ?></h4>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:1.5em">
            <input type="hidden" name="action" value="siga_guardar_medidor">
            <input type="hidden" name="volver" value="<?php echo esc_url( $volver ); ?>">
            <input type="hidden" name="medidor_id" value="<?php echo $editando ? (int) $editando['id'] : 0; ?>">
            <?php wp_nonce_field( 'siga_guardar_medidor' ); ?>
            <label>Vivienda
                <select name="vivienda_id" required>
                    <?php foreach ( $viviendas as $v ) : ?>
                        <option value="<?php echo (int) $v['id']; ?>" <?php selected( $editando ? (int) $editando['vivienda_id'] : 0, (int) $v['id'] ); ?>>
                            <?php echo esc_html( $v['direccion'] . ' (' . ( $v['display_name'] ?: 'sin usuario' ) . ')' ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Número de medidor
                <input type="text" name="numero" maxlength="80" required value="<?php echo esc_attr( $editando['numero'] ?? '' ); ?>">
            </label>
            <button type="submit"><?php echo $editando ? 'Guardar cambios' : 'Crear medidor'; ?></button>
            <?php if ( $editando ) : ?>
                <a href="<?php echo esc_url( remove_query_arg( array( 'siga_editar_medidor', 'siga_med', 'siga_cod' ), $volver ) ); ?>">Cancelar</a>
            <?php endif; ?>
        </form>
        <?php
    }

    $medidores = siga_medidores_todos();
    if ( empty( $medidores ) ) {
        echo '<p>Todavía no hay medidores cargados.</p>';
        return ob_get_clean();
    }
    ?>
    <table>
        <tr><th>ID</th><th>Número</th><th>Domicilio</th><th>Titular</th><th>Estado</th><th>Última lectura</th><th></th></tr>
        <?php foreach ( $medidores as $m ) :
            $nuevo_estado = $m['estado'] === 'activo' ? 'inactivo' : 'activo'; ?>
            <tr>
                <td><?php echo (int) $m['id']; ?></td>
                <td><?php echo esc_html( $m['numero'] ); ?></td>
                <td><?php echo esc_html( $m['direccion'] ); ?></td>
                <td><?php echo esc_html( $m['display_name'] ?: '-' ); ?></td>
                <td><?php echo esc_html( ucfirst( $m['estado'] ) ); ?></td>
                <td><?php echo esc_html( siga_fmt_ultima_lectura( $m ) ); ?></td>
                <td>
                    <a href="<?php echo esc_url( add_query_arg( 'siga_editar_medidor', (int) $m['id'], remove_query_arg( array( 'siga_med', 'siga_cod' ), $volver ) ) ); ?>">Editar</a>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
                        <input type="hidden" name="action" value="siga_estado_medidor">
                        <input type="hidden" name="volver" value="<?php echo esc_url( $volver ); ?>">
                        <input type="hidden" name="medidor_id" value="<?php echo (int) $m['id']; ?>">
                        <input type="hidden" name="estado" value="<?php echo esc_attr( $nuevo_estado ); ?>">
                        <?php wp_nonce_field( 'siga_estado_medidor' ); ?>
                        <button type="submit"><?php echo $nuevo_estado === 'inactivo' ? 'Desactivar' : 'Activar'; ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    <?php
    return ob_get_clean();
}
add_shortcode( 'siga_medidores', 'siga_shortcode_medidores' );