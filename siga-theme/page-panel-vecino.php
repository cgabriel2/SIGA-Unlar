
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
            <h1>
                ¡Hola, <?php echo esc_html($usuario->display_name); ?>!
            </h1>
            <p>Este es el resumen de tu servicio de agua.</p>
        </div>

        <div class="panel-tarjetas">

            <article class="panel-tarjeta">
                <div class="panel-icono">💧</div>
                <h2>Consumo del mes</h2>
                <strong>12,8 m³</strong>
                <p>Datos simulados</p>
            </article>

            <article class="panel-tarjeta">
                <div class="panel-icono">📢</div>
                <h2>Reclamos activos</h2>
                <strong>1</strong>
                <p>Dato simulado</p>
            </article>

            <article class="panel-tarjeta">
                <div class="panel-icono">🧾</div>
                <h2>Factura pendiente</h2>
                <strong>$4.320</strong>
                <p>Importe ficticio</p>
            </article>

            <article class="panel-tarjeta">
                <div class="panel-icono">✅</div>
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
                    <div class="panel-acceso-icono">💧</div>
                    <h3>Mis consumos</h3>
                    <p>Consultá tu historial de consumo de agua.</p>
                    <span class="panel-acceso-pendiente">
                        Próximamente
                    </span>
                </article>

                <article class="panel-acceso">
                    <div class="panel-acceso-icono">📢</div>
                    <h3>Mis reclamos</h3>
                    <p>Registrá problemas y consultá su seguimiento.</p>
                    <span class="panel-acceso-pendiente">
                        Próximamente
                    </span>
                </article>

                <article class="panel-acceso">
                    <div class="panel-acceso-icono">🧾</div>
                    <h3>Mis facturas</h3>
                    <p>Consultá tus facturas y pagos.</p>
                    <span class="panel-acceso-pendiente">
                        Próximamente
                    </span>
                </article>

                <article class="panel-acceso">
                    <div class="panel-acceso-icono">🚰</div>
                    <h3>Cortes de agua</h3>
                    <p>Consultá los avisos de cortes de suministro.</p>
                    <span class="panel-acceso-pendiente">
                        Próximamente
                    </span>
                </article>

            </div>

        </section>

    </div>

</main>

<?php get_footer(); ?>
