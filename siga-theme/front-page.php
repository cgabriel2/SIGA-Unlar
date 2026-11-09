
<?php get_header(); ?>

<main>
    <section class="hero" id="inicio">

        <div class="hero-contenedor">

            <div class="hero-texto">

                <h1>
                    Una mejor gestión del agua
                    para <span>La Rioja.</span>
                </h1>

                <p>
                    Controlá tu consumo, realizá reclamos
                    y consultá el estado del suministro
                    desde un solo lugar.
                </p>

                <div class="hero-botones">
                    <a href="<?php echo esc_url(wp_login_url()); ?>"
                       class="boton-principal">
                        Ingresar al panel
                    </a>

                    <a href="#servicios" class="boton-secundario">
                        Reportar problema
                    </a>
                </div>

            </div>

            <div class="hero-dashboard">

                <div class="dashboard-titulo">
                    <span>💧 SIGA</span>
                    <span>Vista previa</span>
                </div>

                <div class="dashboard-consumo">
                    <p>Mi consumo mensual</p>
                    <h3>12,8 m³</h3>
                    <small>Datos simulados</small>
                </div>

                <div class="dashboard-grafico">
                    <span style="height: 40%;"></span>
                    <span style="height: 60%;"></span>
                    <span style="height: 50%;"></span>
                    <span style="height: 75%;"></span>
                    <span style="height: 65%;"></span>
                    <span style="height: 90%;"></span>
                </div>

                <div class="dashboard-estados">
                    <div>
                        <p>Estado del suministro</p>
                        <strong>Normal</strong>
                    </div>

                    <div>
                        <p>Reclamos activos</p>
                        <strong>1</strong>
                    </div>
                </div>

            </div>

        </div>

    </section>
    <section class="servicios" id="servicios">

    <div class="servicios-contenedor">

        <h2>Nuestros servicios</h2>

        <div class="servicios-grid">

            <article class="servicio-card">
                <div class="servicio-icono">💧</div>
                <h3>Mi consumo</h3>
                <p>Consultá tu consumo mensual y su evolución.</p>
            </article>

            <article class="servicio-card">
                <div class="servicio-icono">📢</div>
                <h3>Reclamos</h3>
                <p>Reportá inconvenientes y seguí tus solicitudes.</p>
            </article>

            <article class="servicio-card">
                <div class="servicio-icono">📅</div>
                <h3>Cortes programados</h3>
                <p>Informate sobre interrupciones del servicio.</p>
            </article>

            <article class="servicio-card">
                <div class="servicio-icono">🧾</div>
                <h3>Facturas</h3>
                <p>Consultá tus facturas y pagos pendientes.</p>
            </article>

        </div>

    </div>

</section>
</main>

<?php get_footer(); ?>
