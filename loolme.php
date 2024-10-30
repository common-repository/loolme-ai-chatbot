<?php
/**
 * Plugin Name:       LooLMe AI Chatbot
 * Description:       LooLMe is a chatbot system powered by AI. This chatbot uses data from WordPress posts and pages as its AI knowledge base.
 * Version:           1.0.6
 * Author:            kunimasa noda
 * Author URI:        https://www.pm9.com
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

if (!defined('ABSPATH')) exit;

add_action('init', 'LooLMe::init');
add_action('wp_footer', 'loolme_wp_footer');

class LooLMe
{
    const VERSION                 = '1.0.6';
    const PLUGIN_ID               = 'loolme';
    const CREDENTIAL_ACTION       = self::PLUGIN_ID . '-nonce-action';
    const CREDENTIAL_NAME         = self::PLUGIN_ID . '-nonce-key';
    const PLUGIN_DB_PREFIX        = self::PLUGIN_ID . '_';
    const MENU_PARENT_SLUG        = self::PLUGIN_ID;
    const MENU_DEMO_CONFIG_SLUG   = self::PLUGIN_ID . '-demo-config';
    const MENU_PROD_CONFIG_SLUG   = self::PLUGIN_ID . '-prod-config';
    const ERROR_TRANSIENT_KEY     = self::PLUGIN_ID . '_error';
    const COMPLETE_TRANSIENT_KEY  = self::PLUGIN_ID . '_complete';

    static function init()
    {
        return new self();
    }

    function __construct()
    {
        if (is_admin() && is_user_logged_in()) {
            add_action('admin_menu', [$this, 'set_plugin_menu']);
            add_action('admin_menu', [$this, 'set_plugin_menu_about']);
            add_action('admin_menu', [$this, 'set_plugin_sub_menu_demo']);
            add_action('admin_menu', [$this, 'set_plugin_sub_menu_prod']);
            add_action('admin_init', [$this, 'save_demo_config']);
            add_action('admin_init', [$this, 'save_prod_config']);
            add_action('admin_notices', [$this, 'admin_notices']);
        }
    }

    function set_plugin_menu()
    {
        add_menu_page(
            'LooLMe Plugin',
            'LooLMe Plugin',
            'manage_options',
            self::MENU_PARENT_SLUG,
            [$this, 'show_about_plugin'],
            WP_PLUGIN_URL.'/loolme-ai-chatbot/loolme20.png',
            3
        );
    }

    function set_plugin_menu_about()
    {
        add_submenu_page(
            self::MENU_PARENT_SLUG,
            'About LooLMe',
            'About LooLMe',
            'manage_options',
            self::MENU_PARENT_SLUG,
            [$this, 'show_about_plugin'],
        );
    }

    function set_plugin_sub_menu_demo()
    {
        add_submenu_page(
            self::MENU_PARENT_SLUG,
            'デモ機能設定',
            'デモ機能設定',
            'manage_options',
            self::MENU_DEMO_CONFIG_SLUG,
            [$this, 'show_demo_config_form']
        );
    }

    function set_plugin_sub_menu_prod()
    {
        add_submenu_page(
            self::MENU_PARENT_SLUG,
            '正規版機能設定',
            '正規版機能設定',
            'manage_options',
            self::MENU_PROD_CONFIG_SLUG,
            [$this, 'show_prod_config_form']
        );
    }

    function show_about_plugin()
    {
        include_once 'style.css';
        include_once 'about.html';
    }

    function show_demo_config_form()
    {
        $knowledge_url_list = get_option(self::PLUGIN_DB_PREFIX . '_knowledge_url_list');
        $helpdesk_name = get_option(self::PLUGIN_DB_PREFIX . '_helpdesk_name');
        $character_settings = get_option(self::PLUGIN_DB_PREFIX . '_character_settings');
        $self_introduction = get_option(self::PLUGIN_DB_PREFIX . '_self_introduction');
        $intro_message = get_option(self::PLUGIN_DB_PREFIX . '_intro_message');
        $require_login = get_option(self::PLUGIN_DB_PREFIX . '_require_login');

        $err_knowledge_url_list = get_option(self::PLUGIN_DB_PREFIX . '_err_knowledge_url_list');
        $err_helpdesk_name = get_option(self::PLUGIN_DB_PREFIX . '_err_helpdesk_name');
        $err_character_settings = get_option(self::PLUGIN_DB_PREFIX . '_err_character_settings');
        $err_self_introduction = get_option(self::PLUGIN_DB_PREFIX . '_err_self_introduction');
        $err_intro_message = get_option(self::PLUGIN_DB_PREFIX . '_err_intro_message');

        include_once 'style.css';
        include_once 'form_demo.html';
    }

    function validate_url($url)
    {
        foreach(array('post', 'page') as $post_type) {
            $args = array(
                'post_type' => $post_type,
                'posts_per_page' => -1
            );
            $news_query = new WP_Query($args);
            if ($news_query->have_posts()) {
                while ($news_query->have_posts()) {
                    $news_query->the_post();
                    if (get_permalink() == $url) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    function get_contents($knowledge_url_list)
    {
        $tmp = explode("\n", $knowledge_url_list);
        $url_list = array();
        foreach($tmp as $url) {
            $url_list[] = esc_url_raw($url);
        }

        $contents_list = array();

        foreach(array('post', 'page') as $post_type) {
            $args = array(
                'post_type' => $post_type
            );
            $news_query = new WP_Query($args);
            if ($news_query->have_posts()) {
                while ($news_query->have_posts()) {
                    $news_query->the_post();
                    if (in_array(get_permalink(), $url_list)) {
                        $title = get_the_title();
                        $link = get_permalink();
                        $contents = "<div>\n";
                        $contents .= "<h1>$title</h1>";
                        $contents .= "<a href='$link'>$title</a>";
                        $contents .= '<br>';
                        $contents .= '<br>';
                        $contents .= get_the_content();
                        $contents .= "\n</div>";
                        $contents_list[] = $contents;
                    }
                }
            }
        }
        return join("\n\n", $contents_list);
    }

    function trim_and_escape($str)
    {
        $str = str_replace("\\\"", '”', $str);
        $str = str_replace("\\'", '’', $str);
        $str = str_replace("\\", '￥', $str);
        $str = str_replace('"', '”', $str);
        $str = str_replace("'", '’', $str);
        $str = str_replace('<', '＜', $str);
        $str = str_replace('>', '＞', $str);
        $str = trim($str);
        return $str;
    }

    function validate_demo($knowledge_url_list, $helpdesk_name, $character_settings, $self_introduction, $intro_message, $require_login)
    {
        update_option(self::PLUGIN_DB_PREFIX .  '_err_knowledge_url_list', '');
        update_option(self::PLUGIN_DB_PREFIX .  '_err_helpdesk_name', '');
        update_option(self::PLUGIN_DB_PREFIX .  '_err_character_settings',''); 
        update_option(self::PLUGIN_DB_PREFIX .  '_err_self_introduction', '');
        update_option(self::PLUGIN_DB_PREFIX .  '_err_intro_message', '');
        $ret_error = false;

        if (! $knowledge_url_list) {
            update_option(self::PLUGIN_DB_PREFIX .  '_err_knowledge_url_list', '* 入力必須です。');
            $ret_error = true;
        }

        $list = explode("\n", $knowledge_url_list);
        $url_count = 0;
        foreach($list as $url) {
            $url = esc_url_raw($url);
            if (! self::validate_url($url)) {
                update_option(self::PLUGIN_DB_PREFIX .  '_err_knowledge_url_list', '* 投稿ページもしくは固定ページが存在しません。 ' . $url);
                $ret_error = true;
            } else {
                $url_count++;
            }
        }
        if ($url_count > 100) {
            update_option(self::PLUGIN_DB_PREFIX .  '_err_knowledge_url_list', '* 登録可能なURLは100件以内です。。 ' . $url);
            $ret_error = true;
        }

        if (! $helpdesk_name) {
            update_option(self::PLUGIN_DB_PREFIX .  '_err_helpdesk_name', '* 入力必須です。');
            $ret_error = true;
        }

        if (! $character_settings) {
            update_option(self::PLUGIN_DB_PREFIX .  '_err_character_settings', '* 入力必須です。');
            $ret_error = true;
        }

        if (! $self_introduction) {
            update_option(self::PLUGIN_DB_PREFIX .  '_err_self_introduction', '* 入力必須です。');
            $ret_error = true;
        }

        if (! $intro_message) {
            update_option(self::PLUGIN_DB_PREFIX .  '_err_intro_message', '* 入力必須です。');
            $ret_error = true;
        }

        return $ret_error;
    }

    function save_demo($knowledge_url_list, $helpdesk_name, $character_settings, $self_introduction, $intro_message, $require_login)
    {
        $email = get_bloginfo('admin_email');
        $company_name = get_bloginfo('name');
        $company_url = home_url();
        $time = time();

        $knowledge = self::get_contents($knowledge_url_list);

        $data = array(
            'email' => $email,
            'company_name' => $company_name,
            'company_url' => $company_url,
            'knowledge' => $knowledge,
            'helpdesk_name' => $helpdesk_name,
            'character_settings' => $character_settings,
            'self_introduction' => $self_introduction,
            'intro_message' => $intro_message,
            'time' => $time,
        );
        $data = http_build_query($data, '', '&');
        $options = array(
            'method'=> 'POST',
            'headers'=> array('Content-Type: application/x-www-form-urlencoded'),
            'body' => $data
        );
        $url = 'http://www.loolme.ai:8075/reg1st';
        $json = wp_remote_post($url, $options);
        if ($json && $json['response'] && $json['response']['code'] == 200) {
            $resp = json_decode($json['body'], true);
            if ($resp['status'] == 'true') {
                $company_id = $resp['company_id'] ?? '';
                $company_id = self::trim_and_escape($company_id);
                update_option(self::PLUGIN_DB_PREFIX .  '_company_id', $company_id);
                set_transient(self::ERROR_TRANSIENT_KEY, null, 5);
                $completed_text = '知識データの生成を開始します。しばらくお待ちください。知識データの生成が完了したら自動的にAIチャットボットが表示されます。';
                set_transient(self::COMPLETE_TRANSIENT_KEY, $completed_text, 5);
            } else {
                $message = $resp['message'] ?? '';
                $message = self::trim_and_escape($message);
                set_transient(self::ERROR_TRANSIENT_KEY, $message, 5);
                set_transient(self::COMPLETE_TRANSIENT_KEY, null, 5);
            }
        }
    }

    function save_demo_config()
    {
        if (isset($_POST[self::CREDENTIAL_NAME]) && wp_verify_nonce(wp_strip_all_tags(wp_unslash($_POST[self::CREDENTIAL_NAME])), self::CREDENTIAL_ACTION)) {
            if (isset($_POST['t']) && $_POST['t'] == 'demo') {
                $knowledge_url_list = self::trim_and_escape(wp_strip_all_tags(wp_unslash($_POST['knowledge_url_list'] ?? '')));
                $helpdesk_name = self::trim_and_escape(wp_strip_all_tags(wp_unslash($_POST['helpdesk_name'] ?? '')));
                $character_settings = self::trim_and_escape(wp_strip_all_tags(wp_unslash($_POST['character_settings'] ?? '')));
                $self_introduction = self::trim_and_escape(wp_strip_all_tags(wp_unslash($_POST['self_introduction'] ?? '')));
                $intro_message = self::trim_and_escape(wp_strip_all_tags(wp_unslash($_POST['intro_message'] ?? '')));
                $require_login = isset($_POST['require_login']) ? "1" : "";

                update_option(self::PLUGIN_DB_PREFIX .  '_knowledge_url_list', $knowledge_url_list);
                update_option(self::PLUGIN_DB_PREFIX .  '_helpdesk_name', $helpdesk_name);
                update_option(self::PLUGIN_DB_PREFIX .  '_character_settings', $character_settings);
                update_option(self::PLUGIN_DB_PREFIX .  '_self_introduction', $self_introduction);
                update_option(self::PLUGIN_DB_PREFIX .  '_intro_message', $intro_message);
                update_option(self::PLUGIN_DB_PREFIX .  '_require_login', $require_login);

                if (self::validate_demo($knowledge_url_list, $helpdesk_name, $character_settings, $self_introduction, $intro_message, $require_login)) {
                    update_option(self::PLUGIN_DB_PREFIX .  '_demo_valid', '');

                    $error_text = '入力エラーがあります。';
                    set_transient(self::ERROR_TRANSIENT_KEY, $error_text, 5);
                    set_transient(self::COMPLETE_TRANSIENT_KEY, null, 5);
                } else {
                    self::save_demo($knowledge_url_list, $helpdesk_name, $character_settings, $self_introduction, $intro_message, $require_login);
                    update_option(self::PLUGIN_DB_PREFIX .  '_demo_valid', 'true');
                }
            }
        }
    }

    function show_prod_config_form()
    {
        $data_id = get_option(self::PLUGIN_DB_PREFIX . '_data_id');
        $data_logo = get_option(self::PLUGIN_DB_PREFIX . '_data_logo');
        $require_login = get_option(self::PLUGIN_DB_PREFIX . '_require_login');

        include_once 'style.css';
        include_once 'form_prod.html';
    }

    function validate_prod($data_id, $data_logo)
    {
        update_option(self::PLUGIN_DB_PREFIX .  '_err_data_id', '');
        update_option(self::PLUGIN_DB_PREFIX .  '_err_data_logo', '');
        $ret_error = false;

        if (! $data_id && ! $data_logo) {
            return false;
        }

        if (! $data_id) {
            update_option(self::PLUGIN_DB_PREFIX .  '_err_data_id', '* 入力必須です。');
            $ret_error = true;
        }

        if (! $data_logo) {
            update_option(self::PLUGIN_DB_PREFIX .  '_err_data_logo', '* 入力必須です。');
            $ret_error = true;
        }

        return $ret_error;
    }

    function save_prod($data_id, $data_logo)
    {
    }

    function save_prod_config()
    {
        if (isset($_POST[self::CREDENTIAL_NAME]) && wp_verify_nonce(wp_strip_all_tags(wp_unslash($_POST[self::CREDENTIAL_NAME])), self::CREDENTIAL_ACTION)) {
            if (isset($_POST['t']) && $_POST['t'] == 'prod') {
                $data_id = self::trim_and_escape(wp_strip_all_tags(wp_unslash($_POST['data_id'] ?? '')));
                $data_logo = self::trim_and_escape(wp_strip_all_tags(wp_unslash($_POST['data_logo'] ?? '')));
                $require_login = isset($_POST['require_login']) ? "1" : "";

                update_option(self::PLUGIN_DB_PREFIX .  '_data_id', $data_id);
                update_option(self::PLUGIN_DB_PREFIX .  '_data_logo', $data_logo);
                update_option(self::PLUGIN_DB_PREFIX .  '_require_login', $require_login);

                if (self::validate_prod($data_id, $data_logo)) {
                    update_option(self::PLUGIN_DB_PREFIX .  '_prod_valid', '');

                    $error_text = '入力エラーがあります。';
                    set_transient(self::ERROR_TRANSIENT_KEY, $error_text, 5);
                    set_transient(self::COMPLETE_TRANSIENT_KEY, null, 5);
                } else {
                    self::save_prod($data_id, $data_logo);
                    if ($data_id) {
                        update_option(self::PLUGIN_DB_PREFIX .  '_prod_valid', 'true');
                    } else {
                        update_option(self::PLUGIN_DB_PREFIX .  '_prod_valid', '');
                    }

                    set_transient(self::ERROR_TRANSIENT_KEY, null, 5);
                    $completed_text = '設定を保存しました。';
                    set_transient(self::COMPLETE_TRANSIENT_KEY, $completed_text, 5);
                }
            }
        }
    }

    public function admin_notices()
    {
        global $pagenow;
        if ( $pagenow != 'admin.php' ) {
            return;
        }

        if ($notice = get_transient(self::ERROR_TRANSIENT_KEY)) {
        ?>
        <div id='message' class='notice notice-error is-dismissible'>
            <p><?php echo esc_html($notice) ?></p>
        </div>
        <?php
        }

        if ($notice = get_transient(self::COMPLETE_TRANSIENT_KEY)) {
        ?>
        <div id='message' class='notice notice-success is-dismissible'>
            <p><?php echo esc_html($notice) ?></p>
        </div>
        <?php
        }
    }
}

function loolme_add_data_attribute($tag, $handle) {


    $data_id = get_option('loolme__data_id');
    $data_logo = get_option('loolme__data_logo');
    $data_company_id = get_option('loolme__company_id');
    $require_login = get_option('loolme__require_login');

    if ($require_login) {
        if (! is_user_logged_in()) {
            return $tag;
        }
    }

    if ('loolme__prod_valid' === $handle) {
        return str_replace(' src', " id='pm9-chatbot'  data-id='$data_id'  data-logo='$data_logo' src", $tag);
    }

    if ('loolme__demo_valid' === $handle) {
        return str_replace(' src', " id='pm9-chatbot'  data-id='$data_company_id'  data-logo='default/chatbot' src", $tag);
    }

    return $tag;
}

function loolme_wp_footer()
{
    if (get_option('loolme__prod_valid')) {
        wp_enqueue_script('loolme__prod_valid', 'https://www.loolme.ai:8080/static/js/chatbot.js', array(), '1.0.0', true);
    } else if (get_option('loolme__demo_valid')) {
        wp_enqueue_script('loolme__demo_valid', 'https://www.loolme.ai:8080/static/js/chatbot.js', array(), '1.0.0', true);
    }
}

add_filter('script_loader_tag', 'loolme_add_data_attribute', 10, 2);
