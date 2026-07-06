/* admin2fa_domain - toggle de ativar/desativar 2FA via AJAX */
(function () {
    function bindButtons() {
        var buttons = document.querySelectorAll('.admin2fa-btn');

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var userId = btn.getAttribute('data-user-id');
                var action = btn.getAttribute('data-action');
                var label = btn.textContent;

                btn.disabled = true;
                btn.textContent = '...';

                var xhr = new XMLHttpRequest();
                xhr.open('POST', rcmail.url('plugin.admin2fa_domain-toggle'), true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function () {
                    if (xhr.readyState !== 4) {
                        return;
                    }

                    btn.disabled = false;

                    var resp;
                    try {
                        resp = JSON.parse(xhr.responseText);
                    } catch (e) {
                        resp = { success: false, error: 'parse_error' };
                    }

                    if (resp.success) {
                        rcmail.display_message(
                            resp.active
                                ? rcmail.gettext('active', 'admin2fa_domain')
                                : rcmail.gettext('inactive', 'admin2fa_domain'),
                            'confirmation'
                        );
                        // recarrega a lista (mantendo a pagina atual) para refletir o novo estado
                        var params = new URLSearchParams(window.location.search);
                        var currentPage = params.get('_p') || 1;
                        rcmail.goto_url('plugin.admin2fa_domain', { _p: currentPage });
                    } else {
                        var key = 'admin2fa_error_' + (resp.error || 'unknown');
                        var msg = rcmail.gettext(key, 'admin2fa_domain');
                        rcmail.display_message(msg && msg !== key ? msg : (resp.error || 'Erro'), 'error');
                        btn.textContent = label;
                    }
                };

                var body = 'user_id=' + encodeURIComponent(userId) +
                    '&action=' + encodeURIComponent(action) +
                    '&_token=' + encodeURIComponent(rcmail.env.request_token);

                xhr.send(body);
            });
        });
    }

    if (window.rcmail) {
        rcmail.addEventListener('init', bindButtons);
    }
})();
