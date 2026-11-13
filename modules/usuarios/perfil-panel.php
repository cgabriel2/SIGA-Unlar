<?php
/**
 * SIGA (PC1): perfil y panel del vecino.
 * Shortcodes: [siga_mi_perfil] y [siga_panel_vecino].
 * No crea tablas ni cambia los otros módulos.
 */
if (!defined('ABSPATH')) {
    exit;
}

/** Guarda el perfil antes de mostrar la página (admin-post.php). */
function siga_pc1_guardar_perfil() {
    if (!is_user_logged_in()) {
        wp_die('Tenés que iniciar sesión.', 'SIGA', ['response' => 403]);
    }
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        wp_die('Método no permitido.', 'SIGA', ['response' => 405]);
    }

    $nonce = isset($_POST['siga_perfil_nonce']) && is_string($_POST['siga_perfil_nonce'])
        ? sanitize_text_field(wp_unslash($_POST['siga_perfil_nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'siga_pc1_guardar_perfil')) {
        wp_die('Solicitud no autorizada.', 'SIGA', ['response' => 403]);
    }

    $nombre = isset($_POST['nombre_visible']) && is_string($_POST['nombre_visible'])
        ? trim(sanitize_text_field(wp_unslash($_POST['nombre_visible']))) : '';
    $telefono = isset($_POST['telefono']) && is_string($_POST['telefono'])
        ? trim(sanitize_text_field(wp_unslash($_POST['telefono']))) : '';

    if ($nombre === '' || mb_strlen($nombre) > 100 || mb_strlen($telefono) > 25 ||
        ($telefono !== '' && !preg_match('/^[0-9+()\s.-]+$/u', $telefono))) {
        $estado = 'datos';
    } else {
        $id = get_current_user_id();
        $resultado = wp_update_user(['ID' => $id, 'display_name' => $nombre]);
        if (is_wp_error($resultado)) {
            $estado = 'error';
        } else {
            update_user_meta($id, 'siga_telefono', $telefono);
            $estado = 'ok';
        }
    }

    $destino = wp_get_referer();
    if (!$destino) {
        $pagina = get_page_by_path('mi-perfil');
        $destino = $pagina ? get_permalink($pagina) : home_url('/');
    }
    $destino = remove_query_arg('siga_perfil', $destino);
    $destino = add_query_arg('siga_perfil', $estado, $destino);
    wp_safe_redirect($destino, 303);
    exit;
}
add_action('admin_post_siga_pc1_guardar_perfil', 'siga_pc1_guardar_perfil');

/** Muestra y permite modificar los datos del usuario actual. */
function siga_pc1_mostrar_perfil() {
    if (!is_user_logged_in()) {
        return '<p>Debés <a href="' . esc_url(wp_login_url(get_permalink())) . '">iniciar sesión</a> para consultar tu perfil.</p>';
    }
    $usuario = wp_get_current_user();
    $telefono = (string) get_user_meta($usuario->ID, 'siga_telefono', true);
    $estado = isset($_GET['siga_perfil']) && is_string($_GET['siga_perfil'])
        ? sanitize_key(wp_unslash($_GET['siga_perfil'])) : '';
    $mensajes = [
        'ok' => '¡Perfil actualizado correctamente!',
        'datos' => 'Revisá el nombre y el teléfono ingresados.',
        'error' => 'No se pudo actualizar el perfil.'
    ];

    ob_start();
    echo '<section class="siga-perfil"><h2>Mi perfil SIGA</h2>';
    if (isset($mensajes[$estado])) {
        echo '<p role="status">' . esc_html($mensajes[$estado]) . '</p>';
    }
    echo '<p><strong>Usuario:</strong> ' . esc_html($usuario->user_login) . '</p>';
    echo '<p><strong>Correo:</strong> ' . esc_html($usuario->user_email) . '</p>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="siga_pc1_guardar_perfil">';
    echo wp_nonce_field('siga_pc1_guardar_perfil', 'siga_perfil_nonce', true, false);
    echo '<p><label for="siga_nombre_visible">Nombre para mostrar</label><br>';
    echo '<input id="siga_nombre_visible" name="nombre_visible" type="text" maxlength="100" required value="' . esc_attr($usuario->display_name) . '"></p>';
    echo '<p><label for="siga_telefono">Teléfono (opcional)</label><br>';
    echo '<input id="siga_telefono" name="telefono" type="tel" maxlength="25" value="' . esc_attr($telefono) . '"></p>';
    echo '<button type="submit">Guardar cambios</button></form>';
    echo '</section>';
    return ob_get_clean();
}
add_shortcode('siga_mi_perfil', 'siga_pc1_mostrar_perfil');

/** Panel básico con acceso a los módulos del vecino. */
function siga_pc1_panel_vecino() {
    if (!is_user_logged_in()) {
        return '<p>Debés <a href="' . esc_url(wp_login_url(get_permalink())) . '">iniciar sesión</a> para acceder a tu panel.</p>';
    }
    global $wpdb;
    $usuario_id = get_current_user_id();
    $tabla_viviendas = $wpdb->prefix . 'siga_viviendas';
    $viviendas = $wpdb->get_results($wpdb->prepare(
        "SELECT id, direccion FROM {$tabla_viviendas} WHERE usuario_id = %d ORDER BY id DESC LIMIT 10",
        $usuario_id
    ));
    $cantidad = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$tabla_viviendas} WHERE usuario_id = %d",
        $usuario_id
    ));
    $pagina_viviendas = get_page_by_path('mis-viviendas');
    $url_viviendas = $pagina_viviendas ? get_permalink($pagina_viviendas) : home_url('/mis-viviendas/');
    $pagina_perfil = get_page_by_path('mi-perfil');
    $url_perfil = $pagina_perfil ? get_permalink($pagina_perfil) : home_url('/mi-perfil/');

    ob_start();
    echo '<section class="siga-panel">';
    echo '<h2>Bienvenido a SIGA, ' . esc_html(wp_get_current_user()->display_name) . '</h2>';
    echo '<p><strong>Viviendas registradas:</strong> ' . esc_html((string) $cantidad) . '</p>';
    echo '<p><a href="' . esc_url($url_viviendas) . '">Mis viviendas</a> | ';
    echo '<a href="' . esc_url($url_perfil) . '">Editar mi perfil</a> | ';
    echo '<a href="' . esc_url(wp_logout_url(home_url('/'))) . '">Cerrar sesión</a></p>';
    echo '<h3>Mis últimas viviendas</h3>';
    if ($viviendas) {
        echo '<ul>';
        foreach ($viviendas as $vivienda) {
            echo '<li>' . esc_html($vivienda->direccion) . ' (ID ' . esc_html((string) $vivienda->id) . ')</li>';
        }
        echo '</ul>';
    } else {
        echo '<p>Todavía no registraste viviendas.</p>';
    }
    echo '<h3>Próximas integraciones</h3>';
    echo '<p>Reclamos y suministro (PC2), medidores, consumos y facturación (PC3). Los accesos se habilitarán al integrar esos módulos.</p>';
    echo '</section>';
    return ob_get_clean();
}
add_shortcode('siga_panel_vecino', 'siga_pc1_panel_vecino');
