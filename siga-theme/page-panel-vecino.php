
<?php

if (!is_user_logged_in()) {
    wp_safe_redirect(wp_login_url(get_permalink()));
    exit;
}

$usuario = wp_get_current_user();

get_header();
?>

<main class="panel-vecino">

    <div class="panel-contenedor">

        <div class="panel-encabezado">
            <h1>¡Hola, <?php echo esc_html($usuario->display_name); ?>!</h1>
            <p>Este es el resumen de tu servicio de agua.</p>
        </div>

        <div class="panel-tarjetas">

            <article class="panel-tarjeta">
                <div class="panel-icono icono-agua">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round"
                         stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 22a8 8 0 0 0 8-8c0-4.42-8-12-8-12S4 9.58 4 14a8 8 0 0 0 8 8Z"/>
                    </svg>
                </div>
                <h2>Consumo del mes</h2>
                <strong>12,8 m³</strong>
                <p>Datos simulados</p>
            </article>

            <article class="panel-tarjeta">
                <div class="panel-icono icono-reclamos">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round"
                         stroke-linejoin="round" aria-hidden="true">
                        <path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9H13a8.5 8.5 0 0 1 8 8v.5Z"/>
                        <path d="M12.5 8v4"/>
                        <path d="M12.5 16h.01"/>
                    </svg>
                </div>
                <h2>Reclamos activos</h2>
                <strong>1</strong>
                <p>Dato simulado</p>
            </article>

            <article class="panel-tarjeta">
                <div class="panel-icono icono-facturas">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round"
                         stroke-linejoin="round" aria-hidden="true">
                        <path d="M4 3v18l4-2 4 2 4-2 4 2V3l-4 2-4-2-4 2-4-2Z"/>
                        <path d="M8 10h8"/>
                        <path d="M8 14h6"/>
                    </svg>
                </div>
                <h2>Factura pendiente</h2>
                <strong>$4.320</strong>
                <p>Importe ficticio</p>
            </article>

            <article class="panel-tarjeta">
                <div class="panel-icono icono-estado">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round"
                         stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="m8 12 2.5 2.5L16 9"/>
                    </svg>
                </div>
                <h2>Estado del suministro</h2>
                <strong>Normal</strong>
                <p>Estado simulado</p>
            </article>

        </div>

        <section class="panel-consumo-grafico">

            <div class="grafico-encabezado">
                <h2>Consumo de agua mensual</h2>
                <p>Últimos seis meses - Datos simulados</p>
            </div>

            <div class="grafico-barras">

                <div class="grafico-mes">
                    <div class="barra" style="height: 56%;"></div>
                    <span>Jun</span>
                </div>

                <div class="grafico-mes">
                    <div class="barra" style="height: 67.5%;"></div>
                    <span>Jul</span>
                </div>

                <div class="grafico-mes">
                    <div class="barra" style="height: 54%;"></div>
                    <span>Ago</span>
                </div>

                <div class="grafico-mes">
                    <div class="barra" style="height: 70.5%;"></div>
                    <span>Sep</span>
                </div>

                <div class="grafico-mes">
                    <div class="barra" style="height: 63%;"></div>
                    <span>Oct</span>
                </div>

                <div class="grafico-mes">
                    <div class="barra" style="height: 64%;"></div>
                    <span>Nov</span>
                </div>

            </div>

            <p class="grafico-nota">
                Valores ilustrativos. Todavía no están conectados con los medidores.
            </p>

        </section>

        <section class="panel-accesos">

            <div class="panel-accesos-encabezado">
                <h2>Accesos rápidos</h2>
                <p>Gestioná tu servicio de agua desde un solo lugar.</p>
            </div>

            <div class="panel-accesos-grid">

<article class="panel-acceso">
    <div class="panel-acceso-icono icono-viviendas">
        <svg viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round"
             aria-hidden="true">
            <path d="m3 10 9-7 9 7"/>
            <path d="M5 9v12h14V9"/>
            <path d="M9 21v-7h6v7"/>
        </svg>
    </div>

    <h3>Mis viviendas</h3>
    <p>Consultá y gestioná las viviendas asociadas a tu cuenta.</p>

    <a class="panel-acceso-enlace"
       href="<?php echo esc_url(home_url('/mis-viviendas/')); ?>">
        Ir a mis viviendas →
    </a>
</article>

                <article class="panel-acceso">
                    <div class="panel-acceso-icono icono-agua">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round"
                             stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 22a8 8 0 0 0 8-8c0-4.42-8-12-8-12S4 9.58 4 14a8 8 0 0 0 8 8Z"/>
                        </svg>
                    </div>
                    <h3>Mis consumos</h3>
                    <p>Consultá tu historial de consumo de agua.</p>
                    <span class="panel-acceso-pendiente">Próximamente</span>
                </article>

                <article class="panel-acceso">
                    <div class="panel-acceso-icono icono-reclamos">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round"
                             stroke-linejoin="round" aria-hidden="true">
                            <path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9H13a8.5 8.5 0 0 1 8 8v.5Z"/>
                            <path d="M12.5 8v4"/>
                            <path d="M12.5 16h.01"/>
                        </svg>
                    </div>
                    <h3>Mis reclamos</h3>
                    <p>Registrá problemas y consultá su seguimiento.</p>
                    <span class="panel-acceso-pendiente">Próximamente</span>
                </article>

                <article class="panel-acceso">
                    <div class="panel-acceso-icono icono-facturas">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round"
                             stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 3v18l4-2 4 2 4-2 4 2V3l-4 2-4-2-4 2-4-2Z"/>
                            <path d="M8 10h8"/>
                            <path d="M8 14h6"/>
                        </svg>
                    </div>
                    <h3>Mis facturas</h3>
                    <p>Consultá tus facturas y pagos.</p>
                    <span class="panel-acceso-pendiente">Próximamente</span>
                </article>

                <article class="panel-acceso">
                    <div class="panel-acceso-icono icono-cortes">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round"
                             stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 9v4"/>
                            <path d="M12 17h.01"/>
                            <path d="M10.3 3.9 2.2 18a2 2 0 0 0 1.7 3h16.2a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/>
                        </svg>
                    </div>
                    <h3>Cortes de agua</h3>
                    <p>Consultá los avisos de cortes de suministro.</p>
                    <span class="panel-acceso-pendiente">Próximamente</span>
                </article>

            </div>

        </section>

    </div>

</main>

<?php get_footer(); ?>
