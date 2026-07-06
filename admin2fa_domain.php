<?php

/**
 * Admin 2FA Domain Manager
 *
 * Permite que usuarios definidos como "admin" no config.inc.php ativem ou
 * desativem a autenticacao de dois fatores (plugin twofactor_gauthenticator)
 * para os usuarios do MESMO dominio de e-mail do admin logado.
 *
 * Este plugin NAO substitui o twofactor_gauthenticator: ele apenas
 * le/escreve a mesma chave de preferencia ('twofactor_gauthenticator')
 * usada por aquele plugin, reproduzindo exatamente a mesma logica de
 * leitura/decodificacao que ele usa internamente (metodo __get2FAconfig
 * do twofactor_gauthenticator.php), incluindo suporte a
 * $config['twofactor_pref_encrypt'].
 *
 * IMPORTANTE - por que "Ativar" pode nao aparecer para todo mundo:
 * o twofactor_gauthenticator so guarda um "secret" TOTP depois que o
 * PROPRIO usuario configura o Google Authenticator em Configuracoes >
 * Autenticacao de dois fatores. Se n?o existe secret, ligar 'activate'
 * remotamente deixaria o usuario travado no login (pedindo um codigo que
 * ele nunca podera gerar). Por seguranca, o botao "Ativar" so aparece
 * quando o usuario ja possui um secret salvo (ou seja, ja configurou o
 * 2FA antes e foi desativado por um admin). Caso contrario mostramos um
 * aviso pedindo que o proprio usuario finalize a configuracao primeiro.
 *
 * @author eth1consultoria
 * @license GPL-3.0+
 */
class admin2fa_domain extends rcube_plugin
{
    public $task = 'settings';

    /** quantidade de usuarios por pagina na listagem */
    const PAGE_SIZE = 12;

    /** @var rcmail */
    private $rc;

    /** @var string dominio (parte apos o @) do admin logado */
    private $domain = '';

    /** @var bool usuario logado e um admin de dominio valido? */
    private $is_admin = false;

    public function init()
    {
        $this->rc = rcmail::get_instance();
        $this->load_config();

        $this->is_admin = $this->_isDomainAdmin();

        if (!$this->is_admin) {
            return;
        }

        $this->domain = $this->_userDomain($this->rc->user->data['username']);

        $this->add_texts('localization/', true);

        $this->add_hook('settings_actions', array($this, 'settings_actions'));

        $this->register_action('plugin.admin2fa_domain', array($this, 'main_page'));
        $this->register_action('plugin.admin2fa_domain-toggle', array($this, 'toggle_action'));

        $this->include_script('admin2fa_domain.js');
        $this->include_stylesheet($this->local_skin_path() . '/admin2fa_domain.css');
    }

    /**
     * Adiciona o item de menu em Configuracoes.
     */
    public function settings_actions($args)
    {
        $args['actions'][] = array(
            'action' => 'plugin.admin2fa_domain',
            'class'  => 'admin2fadomain',
            'label'  => 'menutitle',
            'title'  => 'menutitle',
            'domain' => 'admin2fa_domain',
        );

        return $args;
    }

    /**
     * Renderiza a pagina principal (lista de usuarios do dominio).
     */
    public function main_page()
    {
        $this->register_handler('plugin.body', array($this, 'render_list'));
        $this->rc->output->set_pagetitle($this->gettext('menutitle'));
        $this->rc->output->send('plugin');
    }

    public function render_list()
    {
        $page  = max(1, (int) rcube_utils::get_input_value('_p', rcube_utils::INPUT_GET));
        $total = $this->_domainUsersCount();
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page  = min($page, $pages);

        $users = $this->_domainUsers($page);

        $table = new html_table(array('cols' => 3, 'class' => 'records-table', 'id' => 'admin2fa-table'));
        $table->add_header(array('class' => 'email'), $this->gettext('email'));
        $table->add_header(array('class' => 'status'), $this->gettext('status'));
        $table->add_header(array('class' => 'action'), $this->gettext('action'));

        foreach ($users as $u) {
            $status = $this->_get2FAStatus($u['user_id']);

            $table->add(array('class' => 'email'), rcube::Q($u['username']));

            $badge_class = $status['active'] ? 'admin2fa-badge admin2fa-on' : 'admin2fa-badge admin2fa-off';
            $badge_text  = $status['active'] ? $this->gettext('active') : $this->gettext('inactive');
            $table->add(array('class' => 'status'), html::span(array('class' => $badge_class), $badge_text));

            if ($status['active']) {
                $btn = html::tag('button', array(
                    'type'         => 'button',
                    'class'        => 'button admin2fa-btn',
                    'data-user-id' => $u['user_id'],
                    'data-action'  => 'deactivate',
                ), rcube::Q($this->gettext('deactivate')));
            } elseif ($status['has_secret']) {
                $btn = html::tag('button', array(
                    'type'         => 'button',
                    'class'        => 'button mainaction admin2fa-btn',
                    'data-user-id' => $u['user_id'],
                    'data-action'  => 'activate',
                ), rcube::Q($this->gettext('activate')));
            } else {
                $btn = html::span(
                    array('class' => 'admin2fa-nosecret', 'title' => rcube::Q($this->gettext('nosecrettitle'))),
                    rcube::Q($this->gettext('nosecret'))
                );
            }

            $table->add(array('class' => 'action'), $btn);
        }

        $out = html::div(array('class' => 'settingsbox'),
            html::tag('h3', array('id' => 'prefs-title'), rcube::Q($this->gettext('menutitle') . ' - @' . $this->domain)) .
            html::div(array('class' => 'boxcontent'),
                html::p(array('class' => 'admin2fa-intro'), rcube::Q(
                    str_replace('{total}', $total, $this->gettext('introcount'))
                )) .
                html::div(array('class' => 'admin2fa-scroll'), $table->show()) .
                $this->_renderPager($page, $pages)
            )
        );

        return $out;
    }

    /**
     * Monta os links de paginacao (anterior / numeros / proxima).
     */
    private function _renderPager($page, $pages)
    {
        if ($pages <= 1) {
            return '';
        }

        $baseUrl = function ($p) {
            return $this->rc->url(array(
                '_task'   => 'settings',
                '_action' => 'plugin.admin2fa_domain',
                '_p'      => $p,
            ));
        };

        $links = array();

        $links[] = $page > 1
            ? html::a(array('href' => $baseUrl($page - 1), 'class' => 'button admin2fa-page-link'), rcube::Q($this->gettext('pageprev')))
            : html::span(array('class' => 'button disabled'), rcube::Q($this->gettext('pageprev')));

        // janela de ate 7 numeros ao redor da pagina atual
        $start = max(1, $page - 3);
        $end   = min($pages, $start + 6);
        $start = max(1, $end - 6);

        for ($i = $start; $i <= $end; $i++) {
            if ($i === $page) {
                $links[] = html::span(array('class' => 'admin2fa-page-current'), $i);
            } else {
                $links[] = html::a(array('href' => $baseUrl($i), 'class' => 'admin2fa-page-link'), $i);
            }
        }

        $links[] = $page < $pages
            ? html::a(array('href' => $baseUrl($page + 1), 'class' => 'button admin2fa-page-link'), rcube::Q($this->gettext('pagenext')))
            : html::span(array('class' => 'button disabled'), rcube::Q($this->gettext('pagenext')));

        return html::div(array('class' => 'admin2fa-pager'), implode(' ', $links));
    }

    /**
     * Endpoint AJAX chamado pelo JS para ativar/desativar o 2FA de um usuario.
     * Sempre revalida no servidor: (1) que quem chama e um admin valido,
     * (2) que o usuario alvo pertence ao MESMO dominio do admin.
     */
    public function toggle_action()
    {
        $this->rc->output->reset();
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->is_admin) {
            echo json_encode(array('success' => false, 'error' => 'forbidden'));
            exit;
        }

        $user_id = (int) rcube_utils::get_input_value('user_id', rcube_utils::INPUT_POST);
        $action  = rcube_utils::get_input_value('action', rcube_utils::INPUT_POST);

        if (!$user_id || !in_array($action, array('activate', 'deactivate'), true)) {
            echo json_encode(array('success' => false, 'error' => 'invalid_request'));
            exit;
        }

        $target = new rcube_user($user_id);

        if (empty($target->ID)) {
            echo json_encode(array('success' => false, 'error' => 'user_not_found'));
            exit;
        }

        // Trava de seguranca: so mexe em usuario do MESMO dominio do admin logado.
        $target_domain = $this->_userDomain($target->data['username']);
        if (strcasecmp($target_domain, $this->domain) !== 0) {
            echo json_encode(array('success' => false, 'error' => 'domain_mismatch'));
            exit;
        }

        $prefs = $target->get_prefs();
        $raw   = isset($prefs['twofactor_gauthenticator']) ? $prefs['twofactor_gauthenticator'] : array();
        $data  = $this->_decode2FA($raw);

        if ($action === 'activate') {
            // So permite ativar remotamente se o usuario ja tiver um secret
            // configurado anteriormente (evita bloquear o login do usuario).
            if (empty($data['secret'])) {
                echo json_encode(array('success' => false, 'error' => 'no_secret'));
                exit;
            }
            $data['activate'] = true;
        } else {
            $data['activate'] = false;
        }

        $prefs['twofactor_gauthenticator'] = $this->_encode2FA($data);
        $ok = $target->save_prefs($prefs);

        rcube::write_log('admin2fa_domain', sprintf(
            'admin %s %s 2FA para usuario #%d (%s) [dominio %s]',
            $this->rc->user->data['username'],
            $action === 'activate' ? 'ATIVOU' : 'DESATIVOU',
            $target->ID,
            $target->data['username'],
            $this->domain
        ));

        echo json_encode(array('success' => (bool) $ok, 'active' => (bool) $data['activate']));
        exit;
    }

    // =========================== helpers privados ===========================

    /**
     * Verifica se o usuario logado esta na lista de admins do config.
     * $config['admin2fa_domain_admins'] = array('admin@dominio.com', 'suporte@.*\.dominio2\.com');
     * Cada item e tratado como uma regex ancorada (mesmo estilo usado pelo
     * proprio twofactor_gauthenticator em 'users_allowed_2FA').
     */
    private function _isDomainAdmin()
    {
        $admins   = $this->rc->config->get('admin2fa_domain_admins');
        $username = $this->rc->user->data['username'] ?? null;

        if (!$username || !is_array($admins)) {
            return false;
        }

        foreach ($admins as $pattern) {
            if (@preg_match('/^' . $pattern . '$/i', $username)) {
                return true;
            }
        }

        return false;
    }

    private function _userDomain($username)
    {
        $pos = strrpos((string) $username, '@');

        return $pos === false ? '' : substr($username, $pos + 1);
    }

    /**
     * Lista (user_id, username) dos usuarios do dominio do admin logado,
     * paginado (self::PAGE_SIZE por pagina).
     *
     * NOTA: rcube_db::set_limit() e protegido nesta versao do Roundcube,
     * entao montamos o LIMIT/OFFSET manualmente na query. Isso e seguro
     * porque $limit e $offset sao sempre inteiros (nunca vem direto do
     * usuario sem passar por (int) antes).
     */
    private function _domainUsers($page = 1)
    {
        $db     = $this->rc->db;
        $limit  = (int) self::PAGE_SIZE;
        $offset = (max(1, (int) $page) - 1) * $limit;

        $sql = 'SELECT user_id, username FROM ' . $db->table_name('users')
             . ' WHERE ' . $db->ilike('username', '%@' . $this->domain)
             . ' ORDER BY username ASC'
             . ' LIMIT ' . $limit . ' OFFSET ' . $offset;

        $result = $db->query($sql);

        $rows = array();
        while ($row = $db->fetch_assoc($result)) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Conta o total de usuarios do dominio, para calcular o total de paginas.
     */
    private function _domainUsersCount()
    {
        $db  = $this->rc->db;
        $sql = 'SELECT COUNT(*) AS cnt FROM ' . $db->table_name('users')
             . ' WHERE ' . $db->ilike('username', '%@' . $this->domain);

        $result = $db->query($sql);
        $row    = $db->fetch_assoc($result);

        return $row ? (int) $row['cnt'] : 0;
    }

    /**
     * Retorna o status 2FA de um usuario, usando rcube_user::get_prefs()
     * (a mesma API usada pelo core e pelo proprio twofactor_gauthenticator),
     * em vez de tentar decodificar a coluna preferences na mao.
     */
    private function _get2FAStatus($user_id)
    {
        $user  = new rcube_user((int) $user_id);
        $prefs = $user->get_prefs();
        $raw   = isset($prefs['twofactor_gauthenticator']) ? $prefs['twofactor_gauthenticator'] : array();
        $data  = $this->_decode2FA($raw);

        return array(
            'active'     => !empty($data['activate']),
            'has_secret' => !empty($data['secret']),
        );
    }

    /**
     * Reproduz twofactor_gauthenticator::__get2FAconfig(): se o valor salvo
     * ja e um array, usa direto; senao (quando twofactor_pref_encrypt = true)
     * descriptografa com $rcmail->decrypt() e decodifica o JSON.
     */
    private function _decode2FA($raw)
    {
        if (is_array($raw)) {
            return $raw;
        }

        if ($raw && $this->rc->config->get('twofactor_pref_encrypt')) {
            $decrypted = $this->rc->decrypt($raw);
            $cdata     = json_decode($decrypted, true);

            return is_array($cdata) ? $cdata : array();
        }

        return array();
    }

    /**
     * Espelha a criptografia opcional usada por twofactor_gauthenticator ao
     * salvar, para manter compatibilidade se twofactor_pref_encrypt = true.
     */
    private function _encode2FA($data)
    {
        if ($this->rc->config->get('twofactor_pref_encrypt')) {
            return $this->rc->encrypt(json_encode($data));
        }

        return $data;
    }
}
