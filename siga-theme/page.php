
<?php get_header(); ?>

<main class="pagina-siga">
    <div class="pagina-contenedor">

        <?php if (have_posts()) : ?>
            <?php while (have_posts()) : the_post(); ?>

                <h1><?php the_title(); ?></h1>

                <div class="pagina-contenido">
                    <?php the_content(); ?>
                </div>

            <?php endwhile; ?>
        <?php endif; ?>

    </div>
</main>

<?php get_footer(); ?>
