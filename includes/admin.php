<?php
defined('ABSPATH') || exit;

// ─── Menus & configurações ───────────────────────────────────────────────────
add_action('admin_menu', function () {
    add_menu_page(
        'BTP Gallery', 'BTP Gallery', 'manage_options',
        'btp-gallery', 'btp_gal_admin_page',
        'dashicons-format-gallery', 58
    );
});

add_action('admin_init', function () {
    register_setting('btp_gal', 'btp_gal_defaults');
    register_setting('btp_gal', 'btp_gal_settings');
});

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'toplevel_page_btp-gallery') return;
    wp_enqueue_media(); // necessário para o seletor de mídia (não usado aqui, mas útil)
    wp_enqueue_script(
        'btp-gal-admin',
        BTP_GAL_URL . 'assets/admin.js',
        ['jquery'],
        '0.3.1',
        true
    );
    wp_localize_script('btp-gal-admin', 'BTP_GAL_ADMIN', [
        'ajax'  => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('btp_gal_upload'),
    ]);
});

// ─── AJAX: listar álbuns (shortcode builder) ─────────────────────────────────
add_action('wp_ajax_btp_gal_list_albums', function () {
    if (!current_user_can('manage_options')) wp_send_json_error('forbidden', 403);
    $parent = isset($_POST['parent']) ? sanitize_text_field(wp_unslash($_POST['parent'])) : '';
    $depth  = isset($_POST['depth'])  ? (int) $_POST['depth'] : 1;
    wp_send_json_success(btp_gal_list_albums($parent, $depth));
});

// ─── AJAX: upload de imagens ──────────────────────────────────────────────────
add_action('wp_ajax_btp_gal_upload_images', function () {
    if (!current_user_can('manage_options')) wp_send_json_error('Permissão negada.', 403);
    check_ajax_referer('btp_gal_upload', 'nonce');

    $album = isset($_POST['album']) ? sanitize_text_field(wp_unslash($_POST['album'])) : '';
    $album = btp_gal_sanitize_album($album);

    if ($album === '') {
        wp_send_json_error('Informe o álbum de destino.');
    }

    $dir = btp_gal_ensure_album_dir($album);
    if ($dir === false) {
        wp_send_json_error('Não foi possível criar ou acessar o diretório: ' . esc_html($album));
    }

    if (empty($_FILES['files'])) {
        wp_send_json_error('Nenhum arquivo recebido.');
    }

    $allowed     = btp_gal_get_allowed_ext();
    $results     = [];
    $file_count  = count($_FILES['files']['name']);

    for ($i = 0; $i < $file_count; $i++) {
        $orig_name = sanitize_file_name(wp_unslash($_FILES['files']['name'][$i]));
        $tmp       = $_FILES['files']['tmp_name'][$i];
        $error     = $_FILES['files']['error'][$i];

        if ($error !== UPLOAD_ERR_OK) {
            $results[] = ['file' => $orig_name, 'ok' => false, 'msg' => 'Erro no upload (código ' . $error . ').'];
            continue;
        }

        $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            $results[] = ['file' => $orig_name, 'ok' => false, 'msg' => 'Extensão não permitida: ' . esc_html($ext)];
            continue;
        }

        // Validação adicional de MIME real (evita extensão falsa)
        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
        if ($finfo) {
            $real_mime = finfo_file($finfo, $tmp);
            finfo_close($finfo);
            $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($real_mime, $allowed_mimes, true)) {
                $results[] = ['file' => $orig_name, 'ok' => false, 'msg' => 'Tipo de arquivo inválido: ' . esc_html($real_mime)];
                continue;
            }
        }

        // Evita sobrescrever arquivos existentes: adiciona sufixo numérico se necessário
        $dest_name = $orig_name;
        $dest_path = $dir . '/' . $dest_name;
        $counter   = 1;
        $basename  = pathinfo($orig_name, PATHINFO_FILENAME);
        while (file_exists($dest_path)) {
            $dest_name = $basename . '-' . $counter . '.' . $ext;
            $dest_path = $dir . '/' . $dest_name;
            $counter++;
        }

        if (!move_uploaded_file($tmp, $dest_path)) {
            $results[] = ['file' => $orig_name, 'ok' => false, 'msg' => 'Falha ao mover arquivo para o destino.'];
            continue;
        }

        // Invalida cache do álbum
        btp_gal_cache_del(btp_gal_cache_key($album, false));
        btp_gal_cache_del(btp_gal_cache_key($album, true));

        $results[] = ['file' => $dest_name, 'ok' => true, 'msg' => 'Enviado com sucesso.'];
    }

    wp_send_json_success($results);
});

// ─── Página admin ─────────────────────────────────────────────────────────────
function btp_gal_admin_page() {
    if (!current_user_can('manage_options')) return;

    $pages     = get_pages(['sort_column' => 'post_title', 'sort_order' => 'ASC']);
    $settings  = (array) get_option('btp_gal_settings', []);
    $base_path = isset($settings['base_path']) ? $settings['base_path'] : BTP_GAL_BASE_PATH;
    $active    = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'upload';
    ?>
    <div class="wrap">
        <h1>BTP Gallery</h1>

        <nav class="nav-tab-wrapper" style="margin-bottom:20px">
            <a href="?page=btp-gallery&tab=upload"
               class="nav-tab <?php echo $active === 'upload' ? 'nav-tab-active' : ''; ?>">
               Upload de Imagens
            </a>
            <a href="?page=btp-gallery&tab=builder"
               class="nav-tab <?php echo $active === 'builder' ? 'nav-tab-active' : ''; ?>">
               Gerador de Shortcodes
            </a>
            <a href="?page=btp-gallery&tab=config"
               class="nav-tab <?php echo $active === 'config' ? 'nav-tab-active' : ''; ?>">
               Configurações
            </a>
        </nav>

        <?php if ($active === 'config') : ?>

        <h2>Configurações</h2>
        <form method="post" action="options.php">
            <?php settings_fields('btp_gal'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="btp_base_path">Base Path (filesystem)</label></th>
                    <td>
                        <input id="btp_base_path" type="text"
                               name="btp_gal_settings[base_path]"
                               value="<?php echo esc_attr($base_path); ?>"
                               class="regular-text" />
                        <p class="description">Caminho absoluto no servidor onde estão os álbuns
                        (ex.: <code>/var/www/html/wp-content/uploads/btp/galerias</code>
                        ou <code>E:/uploads/btp/galerias</code>).</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Salvar configurações'); ?>
        </form>

        <?php elseif ($active === 'upload') : ?>

        <h2>Upload de Imagens</h2>
        <p class="description">
            Selecione um álbum existente ou informe um novo caminho relativo para criar automaticamente.
            Formatos aceitos: <code>jpg, jpeg, png, gif, webp</code>.
        </p>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="bup-year">Pasta raiz / ano</label></th>
                <td>
                    <input id="bup-year" type="text" placeholder="2025" class="regular-text">
                    Depth: <input id="bup-depth" type="number" value="2" min="1" max="5" style="width:70px">
                    <button type="button" class="button" id="bup-list">Listar álbuns</button>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="bup-album-select">Álbum existente</label></th>
                <td>
                    <select id="bup-album-select">
                        <option value="">— selecione ou use o campo abaixo —</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="bup-album-path">Caminho do álbum</label></th>
                <td>
                    <input id="bup-album-path" type="text"
                           placeholder="2025/MeuEvento" class="regular-text">
                    <p class="description">
                        Relativo ao base path. Será criado automaticamente se não existir.
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="bup-files">Arquivos</label></th>
                <td>
                    <input id="bup-files" type="file" multiple
                           accept=".jpg,.jpeg,.png,.gif,.webp">
                    <p class="description">Selecione uma ou mais imagens para enviar.</p>
                </td>
            </tr>
        </table>

        <p>
            <button type="button" class="button button-primary" id="bup-submit">
                Enviar imagens
            </button>
        </p>

        <div id="bup-progress" style="display:none;margin-top:16px">
            <div style="background:#e0e0e0;border-radius:4px;height:20px;width:100%;max-width:500px">
                <div id="bup-progress-bar"
                     style="background:#0073aa;height:20px;border-radius:4px;width:0%;transition:width .3s"></div>
            </div>
            <p id="bup-progress-label" style="margin-top:4px;color:#555"></p>
        </div>

        <div id="bup-results" style="margin-top:16px"></div>

        <?php elseif ($active === 'builder') : ?>

        <h2>Gerador de Shortcodes</h2>
        <div id="btp-gal-builder">
            <table class="form-table" role="presentation">
                <tr>
                    <th>Ano</th>
                    <td>
                        <input id="bg-year" type="text" placeholder="2025">
                        Depth: <input id="bg-depth" type="number" value="1" min="1" max="4" style="width:70px">
                        <button type="button" class="button" id="bg-list">Listar</button>
                    </td>
                </tr>
                <tr>
                    <th>Diretório do evento</th>
                    <td><select id="bg-album"><option value="">— selecione —</option></select></td>
                </tr>
                <tr>
                    <th>Página (Página B)</th>
                    <td>
                        <select id="bg-link">
                            <option value="">— esta página —</option>
                            <?php foreach ($pages as $p) {
                                $plink = get_permalink($p->ID);
                                echo '<option value="' . esc_attr($plink) . '">'
                                    . esc_html($p->post_title) . '</option>';
                            } ?>
                        </select>
                    </td>
                </tr>
                <tr><th>Colunas</th><td><input id="bg-columns" type="number" min="2" max="6" value="4"></td></tr>
                <tr><th>Per page</th><td><input id="bg-per_page" type="number" min="1" value="24"></td></tr>
                <tr>
                    <th>Recursive</th>
                    <td>
                        <select id="bg-recursive">
                            <option value="false">false</option>
                            <option value="true">true</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Título</th>
                    <td>
                        <select id="bg-title">
                            <option value="human">human</option>
                            <option value="raw">raw</option>
                        </select>
                    </td>
                </tr>
            </table>

            <h3>Árvore</h3>
            <table class="form-table" role="presentation">
                <tr><th>open</th><td><input id="bg-open" type="text" placeholder="2025/CopaBTP2025"></td></tr>
                <tr><th>back_label</th><td><input id="bg-back" type="text" value="← Voltar"></td></tr>
                <tr><th>sep</th><td><input id="bg-sep" type="text" value=" / "></td></tr>
                <tr><th>root_label</th><td><input id="bg-rootlabel" type="text" placeholder="2025"></td></tr>
            </table>

            <p><strong>Shortcode (Galeria):</strong></p>
            <textarea id="bg-code-fixed" class="large-text code" rows="2" readonly></textarea>
            <p><strong>Shortcode (Índice):</strong></p>
            <textarea id="bg-code-index" class="large-text code" rows="2" readonly></textarea>
            <p><strong>Shortcode (Árvore):</strong></p>
            <textarea id="bg-code-tree" class="large-text code" rows="2" readonly></textarea>
        </div>

        <?php endif; ?>
    </div>
    <?php
}
