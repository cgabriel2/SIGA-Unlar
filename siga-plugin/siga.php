<?php
/**
 * Plugin Name: SIGA - Sistema Inteligente de Gestión del Agua
 * Description: Base académica para SIGA UNLaR: tablas comunes y módulos separados.
 * Version: 0.1.0
 * Requires PHP: 8.0
 */
if (!defined('ABSPATH')) { exit; }
define('SIGA_PLUGIN_PATH', plugin_dir_path(__FILE__));
require_once SIGA_PLUGIN_PATH . 'includes/database.php';
register_activation_hook(__FILE__, 'siga_install_schema');

foreach (['modules/usuarios/usuarios.php','modules/usuarios/viviendas.php','modules/reclamos/reclamos.php','modules/reclamos/suministro.php','modules/consumos/medidores.php','modules/consumos/consumos.php','modules/consumos/facturas.php','modules/consumos/estadisticas.php'] as $file) {
    require_once SIGA_PLUGIN_PATH . $file;
}

add_action('admin_menu', function () {
    add_menu_page('SIGA', 'SIGA', 'manage_options', 'siga-panel', 'siga_admin_panel', 'dashicons-admin-multisite', 26);
});
function siga_admin_panel() {
    if (!current_user_can('manage_options')) { wp_die('Acceso denegado'); }
    global $wpdb;
    $names = siga_table_names();
    echo '<div class="wrap"><h1>SIGA — Estado del proyecto</h1><p>Este panel comprueba las tablas de la base de datos local.</p><table class="widefat striped"><thead><tr><th>Tabla</th><th>Estado</th></tr></thead><tbody>';
    foreach ($names as $name) {
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($name))) === $name;
        echo '<tr><td>' . esc_html($name) . '</td><td>' . ($exists ? 'Creada ✅' : 'No encontrada ❌') . '</td></tr>';
    }
    echo '</tbody></table><p><strong>Siguiente paso:</strong> programar los módulos en sus carpetas respectivas.</p></div>';
}

// Cargar estilos de Reclamos y Suministro.
function siga_pc2_cargar_estilos() {
    wp_enqueue_style(
        'siga-pc2-estilos',
        plugin_dir_url(__FILE__) . 'assets/css/siga-pc2.css',
        [],
        '1.0.0'
    );
}

add_action('wp_enqueue_scripts', 'siga_pc2_cargar_estilos');
