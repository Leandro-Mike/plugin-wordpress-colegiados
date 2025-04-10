<?php 
/*
Plugin Name: Importador de Colegiados
Description: Importa desde un archivo los Colegiados
Version: 1.0
Author: MikeWordpress
*/

add_action('admin_menu', 'crear_menu_importador');

function crear_menu_importador(){
    add_menu_page(
        'Cargar Colegiados',
        'Cargar Colegiados',
        'manage_options',
        'importar-colegiados',
        'importar_colegiados_form',
        plugins_url('/assets/upload-colegiados.svg', __FILE__),
        20
    ); 
}

function importar_colegiados_form() {
    ?>
    <div class="importar-colegiados">
        <h2>Importar Colegiados</h2>
        <p>Solo se admiten cargas de archivos .csv delimitado por comas </p>
        <p><strong>ATENCIÓN: Al importar, los registros actuales se eliminarán y serán reemplazados por los nuevos.</strong></p>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('importar_csv_nonce_action', 'importar_csv_nonce'); ?>
            <input type="file" name="archivo_csv" accept=".csv, .xlsx, .xls" required>
            <br><br>
            <input type="submit" name="importar_csv" class="button button-primary" value="Importar Colegiados">
        </form>

        <p>Plugin desarrollado por <b>Barullo Estudio </b></p>
        
    </div>
    <?php

    if (isset($_POST['importar_csv']) && check_admin_referer('importar_csv_nonce_action', 'importar_csv_nonce')) {
        if (!empty($_FILES['archivo_csv']['tmp_name'])) {
            $archivo = $_FILES['archivo_csv']['tmp_name'];
            $nombre_archivo = $_FILES['archivo_csv']['name'];
            $extension = pathinfo($nombre_archivo, PATHINFO_EXTENSION);

            if ($extension === 'csv') {
                $posts = get_posts([
                    'post_type' => 'colegiados',
                    'posts_per_page' => -1,
                    'post_status' => 'any'
                ]);

                foreach ($posts as $post) {
                    wp_delete_post($post->ID, true);
                }

                $resultados = [];

                // Detectamos delimitador: coma o punto y coma
                $contenido_prueba = file_get_contents($archivo, false, null, 0, 1000);
                $delimitador = substr_count($contenido_prueba, ';') > substr_count($contenido_prueba, ',') ? ';' : ',';

                if (($handle = fopen($archivo, 'r')) !== false) {
                    $headers = array_map(function($header) {
                        return strtolower(trim($header));
                    }, fgetcsv($handle, 0, $delimitador));

                    while (($data = fgetcsv($handle, 0, $delimitador)) !== false) {
                        $titulo = null;
                        $numero_de_colegiado = null;

                        foreach ($headers as $i => $meta_key) {
                            $valor = sanitize_text_field($data[$i] ?? '');

                            if (empty($meta_key)) continue;

                            if (strpos($meta_key, 'nombre') !== false) {
                                $titulo = $valor;
                            } elseif (
                                strpos($meta_key, 'socio') !== false || 
                                strpos($meta_key, 'nº') !== false || 
                                strpos($meta_key, 'numero') !== false
                            ) {
                                $numero_de_colegiado = $valor;
                            }
                        }

                        if (!empty($titulo) && !empty($numero_de_colegiado)) {
                            $post_id = wp_insert_post([
                                'post_type' => 'colegiados',
                                'post_title' => $titulo,
                                'post_status' => 'publish'
                            ]);

                            if (is_wp_error($post_id)) {
                                $resultados[] = "❌ Error al crear post de '$titulo': " . $post_id->get_error_message();
                            } else {
                                update_post_meta($post_id, 'numero_de_colegiado', $numero_de_colegiado);
                                $resultados[] = "✔ Post creado: <strong>$titulo</strong> (N° $numero_de_colegiado)";
                            }
                        } else {
                            $resultados[] = "⚠ Fila ignorada: faltan datos. Título = '$titulo', Número = '$numero_de_colegiado'";
                        }
                    }

                    fclose($handle);
                    echo '<div class="notice notice-success"><p><strong>Importación completada.</strong></p><ul>';
                    foreach ($resultados as $linea) {
                        echo "<li>$linea</li>";
                    }
                    echo '</ul></div>';
                } else {
                    echo '<div class="notice notice-error"><p>❌ Error al abrir el archivo.</p></div>';
                }
            } else {
                echo '<div class="notice notice-error"><p>❌ Solo se permiten archivos CSV.</p></div>';
            }
        }
    }
}

add_action('admin_enqueue_scripts', 'estilos_importador_colegiados');

function estilos_importador_colegiados($hook) {
    if ($hook = 'toplevel_page_importar-colegiados') {
        wp_enqueue_style(
            'estilos-importador',
            plugin_dir_url(__FILE__) . '/assets/admin-style.css'
        );
    }
}
