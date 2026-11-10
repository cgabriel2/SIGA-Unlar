<?php
/**
 * SIGA / PC1 — Viviendas: crear, consultar, editar y eliminar.
 * Shortcode: [siga_viviendas]
 * Utiliza las tablas generadas por includes/database.php.
 */
if (!defined('ABSPATH')) {
    exit;
}

/** Registra el shortcode. */
add_shortcode('siga_viviendas', 'siga_mostrar_viviendas');

/** Procesa POST antes de imprimir HTML, permitiendo redirigir. */
add_action('template_redirect', 'siga_procesar_viviendas');

function siga_procesar_viviendas() {
    if (!is_page('mis-viviendas') || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }

    $accion = isset($_POST['siga_accion']) && is_string($_POST['siga_accion'])
        ? sanitize_key(wp_unslash($_POST['siga_accion'])) : '';
    if (!in_array($accion, ['registrar_vivienda', 'editar_vivienda', 'eliminar_vivienda'], true)) {
        return;
    }

    if (!is_user_logged_in()) {
        wp_die('Iniciá sesión para administrar viviendas.', 'SIGA - Acceso restringido', ['response' => 403]);
    }

    $nonce = isset($_POST['siga_nonce']) && is_string($_POST['siga_nonce'])
        ? sanitize_text_field(wp_unslash($_POST['siga_nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'siga_' . $accion)) {
        wp_die('Solicitud no válida. Actualizá la página e intentá nuevamente.', 'SIGA - Seguridad', ['response' => 403]);
    }

    global $wpdb;
    $tabla_viviendas = $wpdb->prefix . 'siga_viviendas';
    $tabla_barrios = $wpdb->prefix . 'siga_barrios';
    $usuario_id = get_current_user_id();
    $id = isset($_POST['vivienda_id']) ? absint($_POST['vivienda_id']) : 0;

    // En una edición o eliminación, comprobar propiedad en el SERVIDOR.
    if ($accion !== 'registrar_vivienda') {
        $propietario = $wpdb->get_var($wpdb->prepare(
            "SELECT usuario_id FROM {$tabla_viviendas} WHERE id = %d", $id
        ));
        if (!$id || (int) $propietario !== $usuario_id) {
            wp_die('No tenés permiso para modificar esta vivienda.', 'SIGA - Acceso restringido', ['response' => 403]);
        }
    }

    if ($accion === 'eliminar_vivienda') {
        // Preservar relaciones con los módulos de PC2 y PC3.
        foreach (['medidores', 'reclamos', 'facturas'] as $sufijo) {
            $tabla_dependiente = $wpdb->prefix . 'siga_' . $sufijo;
            $cantidad = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$tabla_dependiente} WHERE vivienda_id = %d", $id
            ));
            if ($cantidad > 0) {
                wp_die('Esta vivienda tiene medidores, reclamos o facturas asociados y no se puede eliminar.', 'SIGA - Registros relacionados', ['response' => 409]);
            }
        }
        $resultado = $wpdb->delete($tabla_viviendas, ['id' => $id, 'usuario_id' => $usuario_id], ['%d', '%d']);
        $mensaje = 'vivienda_eliminada';
    } else {
        $barrio_id = isset($_POST['barrio_id']) ? absint($_POST['barrio_id']) : 0;
        $direccion = isset($_POST['direccion']) && is_string($_POST['direccion'])
            ? trim(sanitize_text_field(wp_unslash($_POST['direccion']))) : '';

        if (!$barrio_id || $direccion === '' || strlen($direccion) > 220) {
            wp_die('Elegí un barrio e ingresá una dirección válida (máximo 220 caracteres).', 'SIGA - Datos inválidos', ['response' => 400]);
        }
        $existe = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tabla_barrios} WHERE id = %d", $barrio_id
        ));
        if (!$existe) {
            wp_die('El barrio seleccionado no existe.', 'SIGA - Barrio inválido', ['response' => 400]);
        }

        if ($accion === 'registrar_vivienda') {
            $resultado = $wpdb->insert($tabla_viviendas, [
                'usuario_id' => $usuario_id,
                'barrio_id' => $barrio_id,
                'direccion' => $direccion,
                'identificador' => 'SIGA-' . wp_generate_uuid4(),
                'created_at' => current_time('mysql'),
            ], ['%d', '%d', '%s', '%s', '%s']);
            $mensaje = 'vivienda_registrada';
        } else {
            $resultado = $wpdb->update(
                $tabla_viviendas,
                ['barrio_id' => $barrio_id, 'direccion' => $direccion],
                ['id' => $id, 'usuario_id' => $usuario_id],
                ['%d', '%s'],
                ['%d', '%d']
            );
            $mensaje = 'vivienda_actualizada';
        }
    }

    if ($resultado === false) {
        wp_die('No se pudo completar la operación. Intentá nuevamente.', 'SIGA - Error de base de datos', ['response' => 500]);
    }
    // Un GET posterior a POST evita repetir el envío al presionar F5.
    $url = add_query_arg('siga_mensaje', $mensaje, get_permalink(get_queried_object_id()));
    wp_safe_redirect($url, 303);
    exit;
}

/** Muestra únicamente viviendas del usuario conectado. */
function siga_mostrar_viviendas() {
    if (!is_user_logged_in()) {
        return '<p>Debés iniciar sesión para consultar tus viviendas.</p>';
    }
    global $wpdb;
    $usuario_id = get_current_user_id();
    $tabla_barrios = $wpdb->prefix . 'siga_barrios';
    $tabla_viviendas = $wpdb->prefix . 'siga_viviendas';
    $barrios = $wpdb->get_results("SELECT id, nombre FROM {$tabla_barrios} ORDER BY nombre ASC");
    $viviendas = $wpdb->get_results($wpdb->prepare(
        "SELECT v.id, v.barrio_id, v.direccion, v.identificador, b.nombre AS barrio
         FROM {$tabla_viviendas} v
         INNER JOIN {$tabla_barrios} b ON b.id = v.barrio_id
         WHERE v.usuario_id = %d ORDER BY v.id DESC", $usuario_id
    ));
    $url_base = get_permalink();
    $id_editar = isset($_GET['editar_vivienda']) ? absint($_GET['editar_vivienda']) : 0;
    $id_eliminar = isset($_GET['eliminar_vivienda']) ? absint($_GET['eliminar_vivienda']) : 0;
    $en_edicion = null;
    $en_eliminacion = null;
    foreach ($viviendas as $v) {
        if ((int) $v->id === $id_editar) { $en_edicion = $v; }
        if ((int) $v->id === $id_eliminar) { $en_eliminacion = $v; }
    }

    ob_start();
    $mensajes = [
        'vivienda_registrada' => '¡Vivienda registrada correctamente!',
        'vivienda_actualizada' => '¡Vivienda actualizada correctamente!',
        'vivienda_eliminada' => 'Vivienda eliminada correctamente.',
    ];
    $mensaje = isset($_GET['siga_mensaje']) && is_string($_GET['siga_mensaje'])
        ? sanitize_key(wp_unslash($_GET['siga_mensaje'])) : '';
    if (isset($mensajes[$mensaje])) {
        echo '<p role="status" style="color:green">' . esc_html($mensajes[$mensaje]) . '</p>';
    }
    ?>
    <p>Usuario conectado: <?php echo esc_html(wp_get_current_user()->display_name); ?></p>

    <?php if ($id_editar && !$en_edicion || $id_eliminar && !$en_eliminacion) : ?>
        <p>No se encontró esa vivienda entre tus registros.</p>
    <?php endif; ?>

    <?php if ($en_eliminacion) : ?>
        <h2>Confirmar eliminación</h2>
        <p>¿Querés eliminar la vivienda <?php echo esc_html($en_eliminacion->direccion); ?>?</p>
        <p>Esta acción no se puede deshacer.</p>
        <form method="post" action="<?php echo esc_url($url_base); ?>">
            <input type="hidden" name="siga_accion" value="eliminar_vivienda">
            <input type="hidden" name="vivienda_id" value="<?php echo esc_attr($en_eliminacion->id); ?>">
            <?php wp_nonce_field('siga_eliminar_vivienda', 'siga_nonce'); ?>
            <button type="submit">Sí, eliminar</button>
            <a href="<?php echo esc_url($url_base); ?>">Cancelar</a>
        </form>
    <?php else : ?>
        <h2><?php echo $en_edicion ? 'Editar vivienda' : 'Registrar mi vivienda'; ?></h2>
        <form method="post" action="<?php echo esc_url($url_base); ?>">
            <input type="hidden" name="siga_accion" value="<?php echo $en_edicion ? 'editar_vivienda' : 'registrar_vivienda'; ?>">
            <?php if ($en_edicion) : ?>
                <input type="hidden" name="vivienda_id" value="<?php echo esc_attr($en_edicion->id); ?>">
            <?php endif; ?>
            <?php wp_nonce_field($en_edicion ? 'siga_editar_vivienda' : 'siga_registrar_vivienda', 'siga_nonce'); ?>
            <p>
                <label for="siga_barrio">Barrio</label><br>
                <select id="siga_barrio" name="barrio_id" required>
                    <option value="">Seleccionar barrio</option>
                    <?php foreach ($barrios as $b) : ?>
                        <option value="<?php echo esc_attr($b->id); ?>" <?php selected($en_edicion ? $en_edicion->barrio_id : 0, $b->id); ?>>
                            <?php echo esc_html($b->nombre); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <label for="siga_direccion">Dirección de la vivienda</label><br>
                <input id="siga_direccion" type="text" name="direccion" maxlength="220" required
                    value="<?php echo esc_attr($en_edicion ? $en_edicion->direccion : ''); ?>">
            </p>
            <button type="submit"><?php echo $en_edicion ? 'Guardar cambios' : 'Registrar vivienda'; ?></button>
            <?php if ($en_edicion) : ?>
                <a href="<?php echo esc_url($url_base); ?>">Cancelar</a>
            <?php endif; ?>
        </form>
    <?php endif; ?>

    <h2>Mis viviendas registradas</h2>
    <?php if (!$viviendas) : ?>
        <p>Todavía no registraste ninguna vivienda.</p>
    <?php else : ?>
        <table style="width:100%;border-collapse:collapse" border="1" cellpadding="8">
            <thead><tr><th>ID</th><th>Barrio</th><th>Dirección</th><th>Identificador</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($viviendas as $v) : ?>
                <tr>
                    <td><?php echo esc_html($v->id); ?></td>
                    <td><?php echo esc_html($v->barrio); ?></td>
                    <td><?php echo esc_html($v->direccion); ?></td>
                    <td style="overflow-wrap:anywhere"><?php echo esc_html($v->identificador); ?></td>
                    <td>
                        <a href="<?php echo esc_url(add_query_arg('editar_vivienda', $v->id, $url_base)); ?>">Editar</a>
                        <a href="<?php echo esc_url(add_query_arg('eliminar_vivienda', $v->id, $url_base)); ?>">Eliminar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}
