(function ($) {
    'use strict';

    /* ── Shortcode Builder ─────────────────────────────────────── */
    function escAttr(s) {
        return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    function buildShortcodes() {
        if (!$('#bg-year').length) return; // não está na aba do builder
        var y   = $('#bg-year').val().trim();
        var d   = parseInt($('#bg-depth').val() || '1', 10);
        var a   = $('#bg-album').val();
        var c   = $('#bg-columns').val();
        var pp  = $('#bg-per_page').val();
        var r   = $('#bg-recursive').val();
        var l   = $('#bg-link').val();
        var t   = $('#bg-title').val();
        var open = $('#bg-open').val();
        var back = $('#bg-back').val();
        var sep  = $('#bg-sep').val();
        var root = $('#bg-rootlabel').val();

        $('#bg-code-fixed').val(
            '[btp_gallery album="' + a + '" columns="' + c +
            '" per_page="' + pp + '" recursive="' + r + '" download="true"]'
        );
        $('#bg-code-index').val(
            '[btp_gallery_index year="' + y + '" link="' + l +
            '" columns="' + c + '" depth="' + d + '" title="' + t + '"]'
        );

        var tree = '[btp_gallery_tree year="' + y + '" link="' + l +
                   '" columns="' + c + '" title="' + t + '"';
        if (open) tree += ' open="' + escAttr(open) + '"';
        if (back) tree += ' back_label="' + escAttr(back) + '"';
        if (sep)  tree += ' sep="' + escAttr(sep) + '"';
        if (root) tree += ' root_label="' + escAttr(root) + '"';
        tree += ']';
        $('#bg-code-tree').val(tree);
    }

    $(document).on('click', '#bg-list', function () {
        var y = $('#bg-year').val().trim();
        var d = parseInt($('#bg-depth').val() || '1', 10);
        if (!y) { alert('Informe o ano/pasta pai.'); return; }
        $('#bg-album').html('<option>Carregando...</option>');
        $.post(BTP_GAL_ADMIN.ajax, { action: 'btp_gal_list_albums', parent: y, depth: d }, function (resp) {
            if (!resp || !resp.success) { alert('Erro ao listar.'); return; }
            var html = '<option value="">— selecione —</option>';
            resp.data.forEach(function (al) {
                html += '<option value="' + escAttr(al.album) + '">' +
                        escAttr(al.album) + ' (' + parseInt(al.count, 10) + ')</option>';
            });
            $('#bg-album').html(html);
            buildShortcodes();
        });
    });

    $('#bg-year,#bg-depth,#bg-album,#bg-columns,#bg-per_page,#bg-recursive,' +
      '#bg-link,#bg-title,#bg-open,#bg-back,#bg-sep,#bg-rootlabel')
        .on('change keyup', buildShortcodes);
    buildShortcodes();

    /* ── Uploader ──────────────────────────────────────────────── */

    // Preenche o campo de caminho ao selecionar álbum no dropdown
    $(document).on('change', '#bup-album-select', function () {
        var val = $(this).val();
        if (val) $('#bup-album-path').val(val);
    });

    // Lista álbuns para o uploader
    $(document).on('click', '#bup-list', function () {
        var y = $('#bup-year').val().trim();
        var d = parseInt($('#bup-depth').val() || '2', 10);
        if (!y) { alert('Informe o ano/pasta pai.'); return; }
        $(this).prop('disabled', true).text('Carregando...');
        var btn = this;
        $.post(BTP_GAL_ADMIN.ajax, { action: 'btp_gal_list_albums', parent: y, depth: d }, function (resp) {
            $(btn).prop('disabled', false).text('Listar álbuns');
            if (!resp || !resp.success) { alert('Erro ao listar álbuns.'); return; }
            var html = '<option value="">— selecione ou use o campo abaixo —</option>';
            resp.data.forEach(function (al) {
                html += '<option value="' + escAttr(al.album) + '">' +
                        escAttr(al.album) + ' (' + parseInt(al.count, 10) + ' imagens)</option>';
            });
            $('#bup-album-select').html(html);
        });
    });

    // Upload
    $(document).on('click', '#bup-submit', function () {
        var album = $('#bup-album-path').val().trim();
        var files = $('#bup-files')[0].files;

        if (!album) {
            alert('Informe o caminho do álbum de destino.');
            $('#bup-album-path').focus();
            return;
        }
        if (!files || files.length === 0) {
            alert('Selecione ao menos um arquivo para enviar.');
            return;
        }

        var $btn      = $('#bup-submit').prop('disabled', true).text('Enviando...');
        var $progress = $('#bup-progress').show();
        var $bar      = $('#bup-progress-bar').css('width', '0%');
        var $label    = $('#bup-progress-label');
        var $results  = $('#bup-results').html('');

        var total     = files.length;
        var done      = 0;

        function updateProgress() {
            var pct = Math.round((done / total) * 100);
            $bar.css('width', pct + '%');
            $label.text('Enviando ' + done + ' de ' + total + ' arquivo(s)...');
        }

        function addResult(file, ok, msg) {
            var color = ok ? '#00a32a' : '#d63638';
            var icon  = ok ? '✓' : '✗';
            $results.append(
                '<div style="color:' + color + ';padding:2px 0">' +
                '<strong>' + icon + '</strong> ' +
                $('<span>').text(file).html() + ' — ' +
                $('<span>').text(msg).html() +
                '</div>'
            );
        }

        function finish() {
            $btn.prop('disabled', false).text('Enviar imagens');
            $label.text('Concluído: ' + total + ' arquivo(s) processado(s).');
        }

        // Envia em lotes de 5 para não travar o servidor
        var BATCH = 5;
        var index = 0;

        function sendBatch() {
            if (index >= total) { finish(); return; }

            var formData = new FormData();
            formData.append('action', 'btp_gal_upload_images');
            formData.append('nonce', BTP_GAL_ADMIN.nonce);
            formData.append('album', album);

            var batchEnd = Math.min(index + BATCH, total);
            for (var i = index; i < batchEnd; i++) {
                formData.append('files[]', files[i]);
            }
            index = batchEnd;

            $.ajax({
                url: BTP_GAL_ADMIN.ajax,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (resp) {
                    if (resp && resp.success && Array.isArray(resp.data)) {
                        resp.data.forEach(function (r) {
                            done++;
                            addResult(r.file, r.ok, r.msg);
                            updateProgress();
                        });
                    } else {
                        var errMsg = (resp && resp.data) ? resp.data : 'Erro desconhecido.';
                        done += (batchEnd - (index - BATCH));
                        addResult('Lote', false, errMsg);
                        updateProgress();
                    }
                    sendBatch();
                },
                error: function (xhr) {
                    done += (batchEnd - (index - BATCH));
                    addResult('Lote', false, 'Falha HTTP ' + xhr.status + '.');
                    updateProgress();
                    sendBatch();
                }
            });
        }

        updateProgress();
        sendBatch();
    });

})(jQuery);
