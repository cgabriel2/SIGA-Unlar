<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header>
   <header class="site-header">
    <div class="header-container">

        <div class="logo">
            <h1>SIGA</h1>
            <p>Sistema Inteligente de Gestión del Agua</p>
        </div>

        <nav class="main-nav" aria-label="Navegación principal">
            <a href="<?php echo esc_url(home_url('/')); ?>">Inicio</a>
            <a href="<?php echo esc_url(home_url('/#servicios')); ?>">Servicios</a>
            <a href="#">Reclamos</a>
            <a href="#">Mi consumo</a>
            <a href="#">Facturas</a>
        </nav>

        <a class="btn-acceder" href="<?php echo esc_url(wp_login_url()); ?>">
            Acceder
        </a>

    </div>
</header>
</header>