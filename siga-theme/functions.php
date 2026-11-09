<?php

function siga_cargar_estilos() {
    wp_enqueue_style(
        'siga-estilos',
        get_stylesheet_uri(),
        array(),
        '1.0'
    );
}

add_action('wp_enqueue_scripts', 'siga_cargar_estilos');