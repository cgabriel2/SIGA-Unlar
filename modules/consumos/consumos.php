<?php
/**
 * SIGA - Módulo Consumos
 * Archivo: modules/consumos/consumos.php
 *
 * Tablas usadas (prefijo wp_ vía $wpdb->prefix):
 *   siga_viviendas  (id, usuario_id, barrio_id, direccion, ...)
 *   siga_medidores  (id, vivienda_id, numero, estado)
 *   siga_lecturas   (id, medidor_id, lectura_m3, fecha DATETIME, origen)
 *
 * La lectura es ACUMULADA (m3 totales del medidor).
 * Consumo de un mes = última lectura del mes - última lectura del mes anterior.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ---------------------------------------------------------
 * 1. Medidores activos del usuario (se llega por la vivienda)
 * --------------------------------------------------------- */
function siga_medidores_usuario( $usuario_id = 0 ) {
    global $wpdb;
    $usuario_id = $usuario_id ? (int) $usuario_id : get_current_user_id();

    return $wpdb->get_results( $wpdb->prepare(
        "SELECT m.id, m.numero, m.vivienda_id, v.direccion
         FROM {$wpdb->prefix}siga_medidores m
         INNER JOIN {$wpdb->prefix}siga_viviendas v ON v.id = m.vivienda_id
         WHERE v.usuario_id = %d AND m.estado = 'activo'
         ORDER BY m.id ASC",
        $usuario_id
    ), ARRAY_A );
}

/* ---------------------------------------------------------
 * 2. Registrar una lectura
 *    $fecha: cualquier formato que entienda strtotime (ej. '2026-11-10')
 *    $origen: 'manual' por defecto (la tabla tiene este campo)
 * --------------------------------------------------------- */
function siga_registrar_lectura( $medidor_id, $lectura_m3, $fecha = null, $origen = 'manual' ) {
    global $wpdb;
    $tabla = $wpdb->prefix . 'siga_lecturas';

    $medidor_id = (int) $medidor_id;
    $lectura_m3 = (float) $lectura_m3;

    // Fecha en formato DATETIME
    $ts = $fecha ? strtotime( sanitize_text_field( $fecha ) ) : false;
    if ( $fecha && ! $ts ) {
        return new WP_Error( 'fecha_invalida', 'La fecha no es válida.' );
    }
    $fecha_sql = $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : current_time( 'mysql' );

    // La lectura nueva no puede ser menor a la última registrada
    $ultima = $wpdb->get_var( $wpdb->prepare(
        "SELECT lectura_m3 FROM $tabla WHERE medidor_id = %d ORDER BY fecha DESC, id DESC LIMIT 1",
        $medidor_id
    ) );

    if ( $ultima !== null && $lectura_m3 < (float) $ultima ) {
        return new WP_Error( 'lectura_invalida', 'La lectura no puede ser menor a la anterior (' . $ultima . ' m3).' );
    }

    $ok = $wpdb->insert(
        $tabla,
        array(
            'medidor_id' => $medidor_id,
            'lectura_m3' => $lectura_m3,
            'fecha'      => $fecha_sql,
            'origen'     => sanitize_key( $origen ),
        ),
        array( '%d', '%f', '%s', '%s' )
    );

    return $ok ? $wpdb->insert_id : new WP_Error( 'error_db', 'No se pudo guardar la lectura.' );
}

/* ---------------------------------------------------------
 * 3. Consumo mensual de un medidor (últimos N meses)
 *    Devuelve: [ ['mes' => '2026-10', 'consumo' => 12.5], ... ]
 *    El formato 'YYYY-MM' coincide con wp_siga_facturas.periodo
 * --------------------------------------------------------- */
function siga_consumo_mensual( $medidor_id, $meses = 12 ) {
    global $wpdb;

    $filas = $wpdb->get_results( $wpdb->prepare(
        "SELECT DATE_FORMAT(fecha, '%%Y-%%m') AS mes, MAX(lectura_m3) AS lectura_fin
         FROM {$wpdb->prefix}siga_lecturas
         WHERE medidor_id = %d
         GROUP BY mes
         ORDER BY mes ASC",
        (int) $medidor_id
    ), ARRAY_A );

    $resultado = array();
    $anterior  = null;

    foreach ( $filas as $fila ) {
        if ( $anterior !== null ) {
            $resultado[] = array(
                'mes'     => $fila['mes'],
                'consumo' => round( (float) $fila['lectura_fin'] - $anterior, 3 ),
            );
        }
        $anterior = (float) $fila['lectura_fin'];
    }

    return array_slice( $resultado, -1 * (int) $meses );
}

/* ---------------------------------------------------------
 * 4. Consumo de un período puntual de un medidor ('YYYY-MM')
 * --------------------------------------------------------- */
function siga_consumo_periodo( $medidor_id, $periodo ) {
    foreach ( siga_consumo_mensual( $medidor_id, 120 ) as $fila ) {
        if ( $fila['mes'] === $periodo ) {
            return $fila['consumo'];
        }
    }
    return null; // No hay datos suficientes para ese mes
}

/* ---------------------------------------------------------
 * 5. Consumo de una vivienda en un período (suma de sus medidores activos)
 *    Es la que conviene usar desde facturas.php (facturas va por vivienda_id)
 * --------------------------------------------------------- */
function siga_consumo_vivienda_periodo( $vivienda_id, $periodo ) {
    global $wpdb;

    $ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}siga_medidores WHERE vivienda_id = %d AND estado = 'activo'",
        (int) $vivienda_id
    ) );

    $total = null;
    foreach ( $ids as $id ) {
        $c = siga_consumo_periodo( $id, $periodo );
        if ( $c !== null ) {
            $total = ( $total ?? 0 ) + $c;
        }
    }
    return $total;
}

/* ---------------------------------------------------------
 * 6. Shortcode [siga_consumos]: formulario de lectura + tabla por mes
 * --------------------------------------------------------- */
function siga_shortcode_consumos() {
    if ( ! is_user_logged_in() ) {
        return '<p>Iniciá sesión para ver tus consumos.</p>';
    }

    $medidores = siga_medidores_usuario();
    if ( empty( $medidores ) ) {
        return '<p>No tenés medidores activos asociados a tu cuenta.</p>';
    }

    $mensaje = '';

    if ( isset( $_POST['siga_lectura_nonce'] ) && wp_verify_nonce( $_POST['siga_lectura_nonce'], 'siga_guardar_lectura' ) ) {
        $medidor_id  = (int) $_POST['medidor_id'];
        $ids_propios = array_map( 'intval', wp_list_pluck( $medidores, 'id' ) );

        // Solo puede cargar lecturas en sus propios medidores
        if ( in_array( $medidor_id, $ids_propios, true ) ) {
            $r = siga_registrar_lectura( $medidor_id, wp_unslash( $_POST['lectura_m3'] ), wp_unslash( $_POST['fecha'] ) );
            $mensaje = is_wp_error( $r )
                ? '<p style="color:#b00">' . esc_html( $r->get_error_message() ) . '</p>'
                : '<p style="color:#080">Lectura guardada correctamente.</p>';
        } else {
            $mensaje = '<p style="color:#b00">Medidor no válido.</p>';
        }
    }

    ob_start();
    echo $mensaje;
    ?>
    <form method="post">
        <?php wp_nonce_field( 'siga_guardar_lectura', 'siga_lectura_nonce' ); ?>
        <label>Medidor
            <select name="medidor_id">
                <?php foreach ( $medidores as $m ) : ?>
                    <option value="<?php echo (int) $m['id']; ?>">
                        <?php echo esc_html( $m['numero'] . ' - ' . $m['direccion'] ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Fecha <input type="date" name="fecha" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required></label>
        <label>Lectura (m³) <input type="number" step="0.001" min="0" name="lectura_m3" required></label>
        <button type="submit">Guardar lectura</button>
    </form>

    <?php foreach ( $medidores as $m ) : ?>
        <h4>Medidor <?php echo esc_html( $m['numero'] ); ?></h4>
        <table>
            <tr><th>Mes</th><th>Consumo (m³)</th></tr>
            <?php foreach ( array_reverse( siga_consumo_mensual( $m['id'], 12 ) ) as $fila ) : ?>
                <tr>
                    <td><?php echo esc_html( $fila['mes'] ); ?></td>
                    <td><?php echo esc_html( $fila['consumo'] ); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endforeach;

    return ob_get_clean();
}
add_shortcode( 'siga_consumos', 'siga_shortcode_consumos' );