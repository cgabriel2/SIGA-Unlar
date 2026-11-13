<?php
/**
 * SIGA - Módulo Facturas y pagos (Mercado Pago Checkout Pro)
 * Archivo: modules/consumos/facturas.php
 *
 * Depende de consumos.php (siga_consumo_vivienda_periodo).
 * Shortcode: [siga_facturas]
 *
 * CONFIGURACIÓN (en wp-config.php, FUERA del plugin y fuera de Git):
 *   define( 'SIGA_MP_ACCESS_TOKEN', 'TEST-xxxxxxxx' );             // Access Token de Mercado Pago
 *   define( 'SIGA_MP_NOTIFICATION_URL', 'https://xxxx.ngrok-free.app/wp-json/siga/v1/mp-webhook' ); // opcional (pruebas locales)
 *   define( 'SIGA_PRECIO_M3', 150 );    // opcional, precio por m3 (default 100)
 *   define( 'SIGA_CARGO_FIJO', 2000 );  // opcional, cargo fijo mensual (default 0)
 *
 * Tablas: siga_facturas, siga_pagos, siga_viviendas, siga_medidores
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* =========================================================
 * 1. FACTURAS
 * ========================================================= */

/** Genera la factura de una vivienda para un período 'YYYY-MM'. */
function siga_generar_factura( $vivienda_id, $periodo ) {
    global $wpdb;
    $tabla = $wpdb->prefix . 'siga_facturas';

    if ( ! preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $periodo ) ) {
        return new WP_Error( 'periodo_invalido', 'El período debe tener formato AAAA-MM.' );
    }

    $existe = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM $tabla WHERE vivienda_id = %d AND periodo = %s",
        (int) $vivienda_id,
        $periodo
    ) );
    if ( $existe ) {
        return new WP_Error( 'factura_existente', 'Ya existe una factura para ese período.' );
    }

    $consumo = siga_consumo_vivienda_periodo( $vivienda_id, $periodo );
    if ( $consumo === null ) {
        return new WP_Error( 'sin_consumo', 'No hay lecturas suficientes para calcular el consumo.' );
    }

    $precio = defined( 'SIGA_PRECIO_M3' ) ? (float) SIGA_PRECIO_M3 : 100.0;
    $fijo   = defined( 'SIGA_CARGO_FIJO' ) ? (float) SIGA_CARGO_FIJO : 0.0;
    $importe = round( $fijo + ( $consumo * $precio ), 2 );

    // Vence el día 10 del mes siguiente
    $vencimiento = gmdate( 'Y-m-d', strtotime( $periodo . '-01 +1 month +9 days' ) );

    $ok = $wpdb->insert(
        $tabla,
        array(
            'vivienda_id'       => (int) $vivienda_id,
            'periodo'           => $periodo,
            'importe'           => $importe,
            'estado'            => 'pendiente',
            'fecha_vencimiento' => $vencimiento,
        ),
        array( '%d', '%s', '%f', '%s', '%s' )
    );

    return $ok ? $wpdb->insert_id : new WP_Error( 'error_db', 'No se pudo crear la factura.' );
}

/** Genera facturas del período para todas las viviendas con medidor activo. */
function siga_generar_facturas_periodo( $periodo ) {
    global $wpdb;
    $ids = $wpdb->get_col(
        "SELECT DISTINCT vivienda_id FROM {$wpdb->prefix}siga_medidores WHERE estado = 'activo'"
    );

    $generadas = 0;
    $omitidas  = 0;
    foreach ( $ids as $vid ) {
        $r = siga_generar_factura( $vid, $periodo );
        is_wp_error( $r ) ? $omitidas++ : $generadas++;
    }
    return array( 'generadas' => $generadas, 'omitidas' => $omitidas );
}

/** Facturas del usuario logueado (se llega por la vivienda). */
function siga_facturas_usuario( $usuario_id = 0 ) {
    global $wpdb;
    $usuario_id = $usuario_id ? (int) $usuario_id : get_current_user_id();

    return $wpdb->get_results( $wpdb->prepare(
        "SELECT f.*, v.direccion
         FROM {$wpdb->prefix}siga_facturas f
         INNER JOIN {$wpdb->prefix}siga_viviendas v ON v.id = f.vivienda_id
         WHERE v.usuario_id = %d
         ORDER BY f.periodo DESC",
        $usuario_id
    ), ARRAY_A );
}

/** 'vencida' no se guarda en la base: se calcula al mostrar. */
function siga_estado_factura_visible( $factura ) {
    if ( $factura['estado'] === 'pendiente' && ! empty( $factura['fecha_vencimiento'] )
        && $factura['fecha_vencimiento'] < current_time( 'Y-m-d' ) ) {
        return 'vencida';
    }
    return $factura['estado'];
}

/* =========================================================
 * 2. MERCADO PAGO
 * ========================================================= */

/**
 * Crea una preferencia de pago y devuelve la URL de checkout (init_point).
 * Guarda el intento en siga_pagos con estado 'pendiente'.
 */
function siga_mp_crear_preferencia( $factura, $volver ) {
    global $wpdb;

    if ( ! defined( 'SIGA_MP_ACCESS_TOKEN' ) || ! SIGA_MP_ACCESS_TOKEN ) {
        return new WP_Error( 'sin_token', 'Falta configurar SIGA_MP_ACCESS_TOKEN en wp-config.php.' );
    }

    $referencia = 'siga-' . (int) $factura['id'] . '-' . time();
    $notif      = defined( 'SIGA_MP_NOTIFICATION_URL' ) ? SIGA_MP_NOTIFICATION_URL : rest_url( 'siga/v1/mp-webhook' );

    $body = array(
        'items' => array( array(
            'title'       => 'Servicio de agua SIGA - ' . $factura['periodo'],
            'quantity'    => 1,
            'currency_id' => 'ARS',
            'unit_price'  => round( (float) $factura['importe'], 2 ),
        ) ),
        'external_reference' => $referencia,
        'notification_url'   => $notif,
        'back_urls'          => array(
            'success' => add_query_arg( 'siga_pago', 'success', $volver ),
            'failure' => add_query_arg( 'siga_pago', 'failure', $volver ),
            'pending' => add_query_arg( 'siga_pago', 'pending', $volver ),
        ),
    );
    // auto_return solo funciona con back_urls públicas (https)
    if ( is_ssl() ) {
        $body['auto_return'] = 'approved';
    }

    $resp = wp_remote_post( 'https://api.mercadopago.com/checkout/preferences', array(
        'timeout' => 20,
        'headers' => array(
            'Authorization' => 'Bearer ' . SIGA_MP_ACCESS_TOKEN,
            'Content-Type'  => 'application/json',
        ),
        'body'    => wp_json_encode( $body ),
    ) );

    if ( is_wp_error( $resp ) ) {
        return $resp;
    }

    $codigo = wp_remote_retrieve_response_code( $resp );
    $data   = json_decode( wp_remote_retrieve_body( $resp ), true );

    if ( $codigo < 200 || $codigo >= 300 || empty( $data['init_point'] ) ) {
        error_log( 'SIGA MP preferencia error (' . $codigo . '): ' . wp_remote_retrieve_body( $resp ) );
        return new WP_Error( 'mp_error', 'Mercado Pago rechazó la solicitud.' );
    }

    $wpdb->insert(
        $wpdb->prefix . 'siga_pagos',
        array(
            'factura_id'         => (int) $factura['id'],
            'referencia_externa' => $referencia,
            'importe'            => (float) $factura['importe'],
            'estado'             => 'pendiente',
            'created_at'         => current_time( 'mysql' ),
        ),
        array( '%d', '%s', '%f', '%s', '%s' )
    );

    return $data['init_point'];
}

/**
 * Consulta un pago a la API de Mercado Pago y actualiza siga_pagos / siga_facturas.
 * Nunca confía en lo que llega por URL o webhook: siempre verifica contra la API.
 * Es idempotente: se puede llamar varias veces con el mismo pago.
 */
function siga_mp_sincronizar_pago( $payment_id ) {
    global $wpdb;

    if ( ! defined( 'SIGA_MP_ACCESS_TOKEN' ) || ! SIGA_MP_ACCESS_TOKEN ) {
        return new WP_Error( 'sin_token', 'Falta SIGA_MP_ACCESS_TOKEN.' );
    }

    $resp = wp_remote_get(
        'https://api.mercadopago.com/v1/payments/' . rawurlencode( $payment_id ),
        array(
            'timeout' => 20,
            'headers' => array( 'Authorization' => 'Bearer ' . SIGA_MP_ACCESS_TOKEN ),
        )
    );
    if ( is_wp_error( $resp ) ) {
        return $resp;
    }
    if ( wp_remote_retrieve_response_code( $resp ) !== 200 ) {
        return new WP_Error( 'mp_consulta', 'No se pudo consultar el pago en Mercado Pago.' );
    }

    $p   = json_decode( wp_remote_retrieve_body( $resp ), true );
    $ref = isset( $p['external_reference'] ) ? (string) $p['external_reference'] : '';

    if ( strpos( $ref, 'siga-' ) !== 0 ) {
        return new WP_Error( 'ref_ajena', 'El pago no pertenece a SIGA.' );
    }

    $pago = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}siga_pagos WHERE referencia_externa = %s",
        $ref
    ), ARRAY_A );
    if ( ! $pago ) {
        return new WP_Error( 'pago_no_encontrado', 'No hay un intento de pago con esa referencia.' );
    }

    $estado = sanitize_key( $p['status'] ?? 'pendiente' ); // approved, pending, rejected, ...

    $wpdb->update(
        $wpdb->prefix . 'siga_pagos',
        array( 'mp_payment_id' => (string) $payment_id, 'estado' => $estado ),
        array( 'id' => (int) $pago['id'] ),
        array( '%s', '%s' ),
        array( '%d' )
    );

    // Marcar la factura como pagada solo si está aprobado y el monto cubre el importe
    if ( $estado === 'approved' && ( (float) ( $p['transaction_amount'] ?? 0 ) + 0.001 ) >= (float) $pago['importe'] ) {
        $wpdb->update(
            $wpdb->prefix . 'siga_facturas',
            array( 'estado' => 'pagada' ),
            array( 'id' => (int) $pago['factura_id'] ),
            array( '%s' ),
            array( '%d' )
        );
    }

    return $estado;
}

/** Webhook: POST /wp-json/siga/v1/mp-webhook */
function siga_mp_webhook( WP_REST_Request $request ) {
    $body = $request->get_json_params();
    $tipo = $body['type'] ?? $request->get_param( 'type' ) ?? $request->get_param( 'topic' );

    if ( $tipo === 'payment' ) {
        $id = $body['data']['id'] ?? $request->get_param( 'data_id' ) ?? $request->get_param( 'id' );
        if ( $id ) {
            $r = siga_mp_sincronizar_pago( $id );
            if ( is_wp_error( $r ) ) {
                error_log( 'SIGA MP webhook: ' . $r->get_error_message() );
            }
        }
    }

    // Siempre 200 para que Mercado Pago no reintente indefinidamente
    return new WP_REST_Response( array( 'ok' => true ), 200 );
}

add_action( 'rest_api_init', function () {
    register_rest_route( 'siga/v1', '/mp-webhook', array(
        'methods'             => array( 'POST', 'GET' ),
        'callback'            => 'siga_mp_webhook',
        'permission_callback' => '__return_true', // Mercado Pago no se autentica; se verifica contra su API
    ) );
} );

/* =========================================================
 * 3. ACCIONES (admin-post)
 * ========================================================= */

/** Botón "Pagar con Mercado Pago" (solo usuarios logueados). */
function siga_accion_pagar() {
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'siga_pagar' ) ) {
        wp_die( 'Solicitud no válida.' );
    }

    global $wpdb;
    $factura_id = (int) ( $_POST['factura_id'] ?? 0 );
    $volver     = wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['volver'] ?? '' ) ), home_url( '/' ) );

    // La factura debe ser del usuario y no estar pagada
    $factura = $wpdb->get_row( $wpdb->prepare(
        "SELECT f.* FROM {$wpdb->prefix}siga_facturas f
         INNER JOIN {$wpdb->prefix}siga_viviendas v ON v.id = f.vivienda_id
         WHERE f.id = %d AND v.usuario_id = %d AND f.estado <> 'pagada'",
        $factura_id,
        get_current_user_id()
    ), ARRAY_A );

    if ( ! $factura ) {
        wp_safe_redirect( add_query_arg( 'siga_err', 'factura', $volver ) );
        exit;
    }

    $url = siga_mp_crear_preferencia( $factura, $volver );
    if ( is_wp_error( $url ) ) {
        wp_safe_redirect( add_query_arg( 'siga_err', 'mp', $volver ) );
        exit;
    }

    wp_redirect( $url ); // destino externo (Mercado Pago)
    exit;
}
add_action( 'admin_post_siga_pagar', 'siga_accion_pagar' );

/** Botón de administrador: generar facturas de un período. */
function siga_accion_generar_facturas() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'No tenés permisos para esta acción.' );
    }
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'siga_generar' ) ) {
        wp_die( 'Solicitud no válida.' );
    }

    $periodo = sanitize_text_field( wp_unslash( $_POST['periodo'] ?? '' ) );
    $volver  = wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['volver'] ?? '' ) ), home_url( '/' ) );
    $r       = siga_generar_facturas_periodo( $periodo );

    wp_safe_redirect( add_query_arg( array(
        'siga_gen'   => $r['generadas'],
        'siga_omit'  => $r['omitidas'],
    ), $volver ) );
    exit;
}
add_action( 'admin_post_siga_generar_facturas', 'siga_accion_generar_facturas' );

/* =========================================================
 * 4. SHORTCODE [siga_facturas]
 * ========================================================= */
function siga_shortcode_facturas() {
    if ( ! is_user_logged_in() ) {
        return '<p>Iniciá sesión para ver tus facturas.</p>';
    }

    $volver  = get_permalink();
    $mensaje = '';

    // Al volver de Mercado Pago llegan payment_id y status por URL.
    // Se verifica contra la API (no se confía en la URL); sirve también si el webhook aún no llegó.
    if ( isset( $_GET['payment_id'] ) && is_numeric( $_GET['payment_id'] ) ) {
        siga_mp_sincronizar_pago( sanitize_text_field( wp_unslash( $_GET['payment_id'] ) ) );
    }

    if ( isset( $_GET['siga_pago'] ) ) {
        $textos = array(
            'success' => 'Recibimos tu pago. Si no figura como pagada, se acreditará en unos instantes.',
            'pending' => 'Tu pago está pendiente de acreditación.',
            'failure' => 'El pago no se pudo completar. Podés intentarlo nuevamente.',
        );
        $clave = sanitize_key( $_GET['siga_pago'] );
        if ( isset( $textos[ $clave ] ) ) {
            $mensaje .= '<p><strong>' . esc_html( $textos[ $clave ] ) . '</strong></p>';
        }
    }
    if ( isset( $_GET['siga_err'] ) ) {
        $mensaje .= '<p style="color:#b00">No se pudo iniciar el pago. Intentá nuevamente más tarde.</p>';
    }
    if ( isset( $_GET['siga_gen'] ) ) {
        $mensaje .= '<p>Facturas generadas: ' . (int) $_GET['siga_gen'] . ' - omitidas: ' . (int) ( $_GET['siga_omit'] ?? 0 ) . '.</p>';
    }

    $facturas = siga_facturas_usuario();

    ob_start();
    echo $mensaje;

    // Panel de administrador
    if ( current_user_can( 'manage_options' ) ) : ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:1.5em">
            <input type="hidden" name="action" value="siga_generar_facturas">
            <input type="hidden" name="volver" value="<?php echo esc_url( $volver ); ?>">
            <?php wp_nonce_field( 'siga_generar' ); ?>
            <label>Generar facturas del período
                <input type="month" name="periodo" value="<?php echo esc_attr( gmdate( 'Y-m', strtotime( 'first day of last month' ) ) ); ?>" required>
            </label>
            <button type="submit">Generar</button>
        </form>
    <?php endif;

    if ( empty( $facturas ) ) {
        echo '<p>Todavía no tenés facturas.</p>';
        return ob_get_clean();
    }
    ?>
    <table>
        <tr><th>Período</th><th>Domicilio</th><th>Importe</th><th>Vencimiento</th><th>Estado</th><th></th></tr>
        <?php foreach ( $facturas as $f ) :
            $estado = siga_estado_factura_visible( $f ); ?>
            <tr>
                <td><?php echo esc_html( $f['periodo'] ); ?></td>
                <td><?php echo esc_html( $f['direccion'] ); ?></td>
                <td>$ <?php echo esc_html( number_format( (float) $f['importe'], 2, ',', '.' ) ); ?></td>
                <td><?php echo esc_html( $f['fecha_vencimiento'] ? gmdate( 'd/m/Y', strtotime( $f['fecha_vencimiento'] ) ) : '-' ); ?></td>
                <td><?php echo esc_html( ucfirst( $estado ) ); ?></td>
                <td>
                    <?php if ( $estado !== 'pagada' ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="siga_pagar">
                            <input type="hidden" name="factura_id" value="<?php echo (int) $f['id']; ?>">
                            <input type="hidden" name="volver" value="<?php echo esc_url( $volver ); ?>">
                            <?php wp_nonce_field( 'siga_pagar' ); ?>
                            <button type="submit">Pagar con Mercado Pago</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    <?php
    return ob_get_clean();
}
add_shortcode( 'siga_facturas', 'siga_shortcode_facturas' );