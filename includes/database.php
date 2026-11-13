<?php
if (!defined('ABSPATH')) { exit; }
function siga_table_names() {
    global $wpdb;
    $p = $wpdb->prefix . 'siga_';
    return array_map(static fn($suffix) => $p . $suffix, ['barrios','viviendas','medidores','lecturas','reclamos','seguimiento','cortes','facturas','pagos']);
}
function siga_install_schema() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $p = $wpdb->prefix . 'siga_';
    $charset = $wpdb->get_charset_collate();
    $queries = [
        "CREATE TABLE {$p}barrios (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            nombre varchar(120) NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY nombre (nombre)
        ) $charset;",
        "CREATE TABLE {$p}viviendas (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            usuario_id bigint(20) unsigned NOT NULL,
            barrio_id bigint(20) unsigned NOT NULL,
            direccion varchar(220) NOT NULL,
            identificador varchar(60) DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY usuario_id (usuario_id),
            KEY barrio_id (barrio_id)
        ) $charset;",
        "CREATE TABLE {$p}medidores (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            vivienda_id bigint(20) unsigned NOT NULL,
            numero varchar(80) NOT NULL,
            estado varchar(30) NOT NULL DEFAULT 'activo',
            PRIMARY KEY  (id),
            UNIQUE KEY numero (numero),
            KEY vivienda_id (vivienda_id)
        ) $charset;",
        "CREATE TABLE {$p}lecturas (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            medidor_id bigint(20) unsigned NOT NULL,
            lectura_m3 decimal(12,3) NOT NULL,
            fecha datetime NOT NULL,
            origen varchar(25) NOT NULL DEFAULT 'manual',
            PRIMARY KEY  (id),
            KEY medidor_fecha (medidor_id,fecha)
        ) $charset;",
        "CREATE TABLE {$p}reclamos (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            usuario_id bigint(20) unsigned NOT NULL,
            vivienda_id bigint(20) unsigned NOT NULL,
            tipo varchar(50) NOT NULL,
            descripcion text NOT NULL,
            estado varchar(30) NOT NULL DEFAULT 'pendiente',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY vivienda_id (vivienda_id),
            KEY estado (estado)
        ) $charset;",
        "CREATE TABLE {$p}seguimiento (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            reclamo_id bigint(20) unsigned NOT NULL,
            actor_id bigint(20) unsigned NOT NULL,
            estado varchar(30) NOT NULL,
            comentario text NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY reclamo_id (reclamo_id)
        ) $charset;",
        "CREATE TABLE {$p}cortes (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            barrio_id bigint(20) unsigned NOT NULL,
            inicio datetime NOT NULL,
            fin_previsto datetime DEFAULT NULL,
            descripcion text NOT NULL,
            estado varchar(30) NOT NULL DEFAULT 'programado',
            PRIMARY KEY  (id),
            KEY barrio_id (barrio_id)
        ) $charset;",
        "CREATE TABLE {$p}facturas (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            vivienda_id bigint(20) unsigned NOT NULL,
            periodo varchar(7) NOT NULL,
            importe decimal(12,2) NOT NULL,
            estado varchar(25) NOT NULL DEFAULT 'pendiente',
            fecha_vencimiento date DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY vivienda_periodo (vivienda_id,periodo)
        ) $charset;",
        "CREATE TABLE {$p}pagos (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            factura_id bigint(20) unsigned NOT NULL,
            referencia_externa varchar(120) DEFAULT NULL,
            mp_payment_id varchar(120) DEFAULT NULL,
            importe decimal(12,2) NOT NULL,
            estado varchar(30) NOT NULL DEFAULT 'pendiente',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY factura_id (factura_id),
            KEY referencia_externa (referencia_externa)
        ) $charset;"
    ];
    foreach ($queries as $sql) { dbDelta($sql); }
    update_option('siga_schema_version', '0.1.0');
}
