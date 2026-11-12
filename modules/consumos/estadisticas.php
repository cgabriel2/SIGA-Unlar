<?php
/**
 * SIGA - Módulo Estadísticas
 * Archivo: modules/consumos/estadisticas.php
 *
 * Depende de consumos.php (siga_medidores_usuario, siga_consumo_mensual).
 * Gráficos con Chart.js. Shortcode: [siga_estadisticas]
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ---------------------------------------------------------
 * 1. Estadísticas resumidas de un medidor (en m3)
 * --------------------------------------------------------- */
function siga_estadisticas_medidor( $medidor_id, $meses = 12 ) {
    $serie = siga_consumo_mensual( $medidor_id, $meses );

    if ( empty( $serie ) ) {
        return array(
            'serie'    => array(),
            'total'    => 0,
            'promedio' => 0,
            'maximo'   => null,
            'ultimo'   => null,
            'variacion'=> null,
        );
    }

    $valores = wp_list_pluck( $serie, 'consumo' );
    $total   = array_sum( $valores );
    $max_idx = array_search( max( $valores ), $valores, true );

    $ultimo   = end( $valores );
    $previo   = count( $valores ) > 1 ? $valores[ count( $valores ) - 2 ] : null;
    $variacion = ( $previo !== null && $previo > 0 )
        ? round( ( ( $ultimo - $previo ) / $previo ) * 100, 1 )
        : null;

    return array(
        'serie'     => $serie,
        'total'     => round( $total, 3 ),
        'promedio'  => round( $total / count( $valores ), 3 ),
        'maximo'    => array( 'mes' => $serie[ $max_idx ]['mes'], 'consumo' => $valores[ $max_idx ] ),
        'ultimo'    => $ultimo,
        'variacion' => $variacion, // % respecto al mes anterior
    );
}

/* ---------------------------------------------------------
 * 2. Shortcode [siga_estadisticas]
 * --------------------------------------------------------- */
function siga_shortcode_estadisticas() {
    if ( ! is_user_logged_in() ) {
        return '<p>Iniciá sesión para ver tus estadísticas.</p>';
    }

    $medidores = siga_medidores_usuario();
    if ( empty( $medidores ) ) {
        return '<p>No tenés medidores activos asociados a tu cuenta.</p>';
    }

    // Datos de todos los medidores; el selector cambia el gráfico sin recargar
    $datos = array();
    foreach ( $medidores as $m ) {
        $datos[ (int) $m['id'] ] = siga_estadisticas_medidor( $m['id'], 12 );
    }

    // Chart.js (versión fija) en el footer
    wp_enqueue_script(
        'siga-chartjs',
        'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
        array(),
        '4.4.1',
        true
    );

    $js = 'window.SIGA_STATS = ' . wp_json_encode( $datos ) . ';' . <<<'JS'
(function () {
  var datos = window.SIGA_STATS || {};
  var selMedidor = document.getElementById('siga-est-medidor');
  var radios = document.querySelectorAll('input[name="siga-est-unidad"]');
  var canvas = document.getElementById('siga-est-grafico');
  if (!selMedidor || !canvas) return;
  var chart = null;

  function unidad() {
    var r = document.querySelector('input[name="siga-est-unidad"]:checked');
    return r && r.value === 'l' ? { factor: 1000, texto: 'L' } : { factor: 1, texto: 'm³' };
  }

  function fmt(n, u) {
    return new Intl.NumberFormat('es-AR', { maximumFractionDigits: u.factor === 1 ? 3 : 0 }).format(n * u.factor) + ' ' + u.texto;
  }

  function render() {
    var d = datos[selMedidor.value];
    var u = unidad();
    if (!d) return;

    // Tarjetas de resumen
    document.getElementById('siga-est-total').textContent = fmt(d.total, u);
    document.getElementById('siga-est-promedio').textContent = fmt(d.promedio, u);
    document.getElementById('siga-est-maximo').textContent = d.maximo ? d.maximo.mes + ' (' + fmt(d.maximo.consumo, u) + ')' : '-';
    var v = document.getElementById('siga-est-variacion');
    if (d.variacion === null) { v.textContent = '-'; }
    else { v.textContent = (d.variacion > 0 ? '+' : '') + d.variacion + '%'; }

    // Gráfico
    var etiquetas = d.serie.map(function (f) { return f.mes; });
    var valores = d.serie.map(function (f) { return +(f.consumo * u.factor).toFixed(3); });
    var promedio = d.serie.map(function () { return +(d.promedio * u.factor).toFixed(3); });

    if (chart) chart.destroy();
    chart = new Chart(canvas, {
      data: {
        labels: etiquetas,
        datasets: [
          { type: 'bar', label: 'Consumo mensual (' + u.texto + ')', data: valores, backgroundColor: 'rgba(30,136,229,0.65)' },
          { type: 'line', label: 'Promedio', data: promedio, borderColor: '#e53935', borderDash: [6, 4], pointRadius: 0, fill: false }
        ]
      },
      options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } },
        scales: { y: { beginAtZero: true, title: { display: true, text: u.texto } } }
      }
    });
  }

  selMedidor.addEventListener('change', render);
  radios.forEach(function (r) { r.addEventListener('change', render); });
  render();
})();
JS;

    wp_add_inline_script( 'siga-chartjs', $js );

    ob_start();
    ?>
    <div class="siga-estadisticas">
        <div style="margin-bottom:1em">
            <label>Medidor
                <select id="siga-est-medidor">
                    <?php foreach ( $medidores as $m ) : ?>
                        <option value="<?php echo (int) $m['id']; ?>">
                            <?php echo esc_html( $m['numero'] . ' - ' . $m['direccion'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            &nbsp;
            <label><input type="radio" name="siga-est-unidad" value="m3" checked> m³</label>
            <label><input type="radio" name="siga-est-unidad" value="l"> Litros</label>
        </div>

        <div style="display:flex;gap:1em;flex-wrap:wrap;margin-bottom:1em">
            <div><strong>Total (12 meses)</strong><br><span id="siga-est-total">-</span></div>
            <div><strong>Promedio mensual</strong><br><span id="siga-est-promedio">-</span></div>
            <div><strong>Mes de mayor consumo</strong><br><span id="siga-est-maximo">-</span></div>
            <div><strong>Variación vs. mes anterior</strong><br><span id="siga-est-variacion">-</span></div>
        </div>

        <canvas id="siga-est-grafico" height="110"></canvas>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'siga_estadisticas', 'siga_shortcode_estadisticas' );