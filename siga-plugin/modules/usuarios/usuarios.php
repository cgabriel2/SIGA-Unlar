<?php
/**
 * SIGA - PC1: roles y registro de vecinos (prototipo académico local).
 * Shortcode: [siga_registro_vecino]
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Los roles se registran una sola vez. Si existen, add_role no los modifica.
 * El operador solo recibe 'read': todavía NO puede administrar reclamos.
 */
function siga_crear_roles() {
    if (!get_role('siga_vecino')) {
        add_role('siga_vecino', 'Vecino SIGA', array('read' => true));
    }
    if (!get_role('siga_operador')) {
        add_role('siga_operador', 'Operador SIGA', array('read' => true));
    }
}
add_action('init', 'siga_crear_roles');

/** Formulario público de registro; no muestra contraseñas después de enviarlas. */
function siga_formulario_registro_vecino() {
    if (is_user_logged_in()) {
        return '<p>Ya iniciaste sesión. Podés ir a <a href="' . esc_url(home_url('/mis-viviendas/')) . '">Mis Viviendas</a>.</p>';
    }

    $mensajes = array(
        'ok'          => 'Cuenta creada. Ya podés iniciar sesión.',
        'datos'       => 'Completá todos los campos correctamente.',
        'clave'       => 'Las contraseñas no coinciden o tienen menos de 8 caracteres.',
        'duplicado'   => 'El usuario o el correo ya están registrados.',
        'error'       => 'No pudimos crear la cuenta. Revisá los datos.',
    );

    $estado = isset($_GET['siga_registro']) && is_string($_GET['siga_registro'])
        ? sanitize_key(wp_unslash($_GET['siga_registro']))
        : '';

    $html = '<h2>Registrarse como vecino en SIGA</h2>';
    if (isset($mensajes[$estado])) {
        $html .= '<p role="status">' . esc_html($mensajes[$estado]) . '</p>';
    }
    if ($estado === 'ok') {
        $html .= '<p><a href="' . esc_url(wp_login_url(home_url('/mis-viviendas/'))) . '">Iniciar sesión</a></p>';
        return $html;
    }

    $html .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    $html .= '<input type="hidden" name="action" value="siga_registrar_vecino">';
    $html .= wp_nonce_field('siga_registrar_vecino', 'siga_nonce_registro', true, false);
    // Campo trampa contra envíos automáticos sencillos.
    $html .= '<div style="position:absolute;left:-10000px" aria-hidden="true"><label>No completar<input type="text" name="siga_web" tabindex="-1" autocomplete="off"></label></div>';
    $html .= '<p><label for="siga_nombre">Nombre</label><br><input id="siga_nombre" name="nombre" maxlength="100" required></p>';
    $html .= '<p><label for="siga_usuario">Usuario</label><br><input id="siga_usuario" name="usuario" maxlength="60" required></p>';
    $html .= '<p><label for="siga_email">Correo electrónico</label><br><input type="email" id="siga_email" name="correo" required></p>';
    $html .= '<p><label for="siga_clave">Contraseña (mínimo 8 caracteres)</label><br><input type="password" id="siga_clave" name="clave" minlength="8" autocomplete="new-password" required></p>';
    $html .= '<p><label for="siga_confirmar">Repetir contraseña</label><br><input type="password" id="siga_confirmar" name="confirmar" minlength="8" autocomplete="new-password" required></p>';
    $html .= '<p><button type="submit">Crear cuenta de vecino</button></p></form>';

    return $html;
}
add_shortcode('siga_registro_vecino', 'siga_formulario_registro_vecino');

/** Redirige sin conservar datos sensibles en la URL. */
function siga_redirigir_registro($estado) {
    $url = add_query_arg('siga_registro', $estado, home_url('/registro-de-vecino/'));
    wp_safe_redirect($url, 303);
    exit;
}

/** Procesa solicitudes POST desde usuarios sin sesión. */
function siga_procesar_registro_vecino() {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        wp_die('Método no permitido.', 'SIGA', array('response' => 405));
    }
    if (is_user_logged_in()) {
        wp_safe_redirect(home_url('/mis-viviendas/'));
        exit;
    }
    $nonce = isset($_POST['siga_nonce_registro']) && is_string($_POST['siga_nonce_registro'])
        ? sanitize_text_field(wp_unslash($_POST['siga_nonce_registro']))
        : '';
    if (!wp_verify_nonce($nonce, 'siga_registrar_vecino')) {
        wp_die('Solicitud no autorizada.', 'SIGA', array('response' => 403));
    }
    if (!empty($_POST['siga_web'])) {
        siga_redirigir_registro('error');
    }

    $nombre = isset($_POST['nombre']) && is_string($_POST['nombre'])
        ? sanitize_text_field(wp_unslash($_POST['nombre'])) : '';
    $usuario = isset($_POST['usuario']) && is_string($_POST['usuario'])
        ? sanitize_user(wp_unslash($_POST['usuario']), true) : '';
    $correo = isset($_POST['correo']) && is_string($_POST['correo'])
        ? sanitize_email(wp_unslash($_POST['correo'])) : '';
    $clave = isset($_POST['clave']) && is_string($_POST['clave'])
        ? wp_unslash($_POST['clave']) : '';
    $confirmar = isset($_POST['confirmar']) && is_string($_POST['confirmar'])
        ? wp_unslash($_POST['confirmar']) : '';

    if ($nombre === '' || $usuario === '' || strlen($usuario) > 60 || !is_email($correo)) {
        siga_redirigir_registro('datos');
    }
    if (strlen($clave) < 8 || $clave !== $confirmar) {
        siga_redirigir_registro('clave');
    }
    if (username_exists($usuario) || email_exists($correo)) {
        siga_redirigir_registro('duplicado');
    }

    // Nunca se permite que el formulario elija un rol: siempre Vecino.
    $id = wp_insert_user(array(
        'user_login'   => $usuario,
        'user_email'   => $correo,
        'user_pass'    => $clave,
        'display_name' => $nombre,
        'role'         => 'siga_vecino',
    ));

    if (is_wp_error($id)) {
        siga_redirigir_registro('error');
    }
    siga_redirigir_registro('ok');
}
add_action('admin_post_nopriv_siga_registrar_vecino', 'siga_procesar_registro_vecino');
add_action('admin_post_siga_registrar_vecino', 'siga_procesar_registro_vecino');

// Cargamos el perfil y panel del vecino.
require_once __DIR__ . '/perfil-panel.php';

// Cargamos los estilos de PC1.
add_action('wp_enqueue_scripts', function () {

    if (!is_page([
        'mis-viviendas',
        'registro-de-vecino',
        'mi-perfil',
        'panel-vecino'
    ])) {
        return;
    }

    wp_enqueue_style(
        'siga-pc1-estilos',
        plugin_dir_url(SIGA_PLUGIN_PATH . 'siga.php') .
            'assets/css/siga-pc1.css',
        [],
        filemtime(
            SIGA_PLUGIN_PATH . 'assets/css/siga-pc1.css'
        )
    );
});

// Identificamos las páginas de PC1.
add_filter('body_class', function ($classes) {

    if (is_page([
        'mis-viviendas',
        'registro-de-vecino',
        'mi-perfil',
        'panel-vecino'
    ])) {
        $classes[] = 'siga-pc1-page';
    }

    return $classes;
});