<?php
/**
 * Plugin Name: Oscar Lima Cookie Consent & Integrations
 * Plugin URI: https://oscarlima.com.br
 * Description: Plugin customizado de consentimento de cookies em conformidade com a LGPD e centralizador de integrações (GA4, GTM, Facebook Pixel) para o site híbrido.
 * Version: 1.0.0
 * Author: Oscar Lima
 * Author URI: https://oscarlima.com.br
 * License: GPL2
 * Text Domain: oscarlima-cookie-consent
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Evita acesso direto ao arquivo
}

class OscarLima_Cookie_Consent {

    private static $instance = null;

    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Enfileira os scripts no front-end do WordPress
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );

        // Adiciona o menu administrativo
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

        // Registra as configurações do plugin
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        
        // Define um aviso caso o JSON não consiga ser escrito (problema de permissão)
        add_action( 'admin_notices', array( $this, 'display_permission_notice' ) );
    }

    /**
     * Enfileira os arquivos de estilo e script da raiz no blog WordPress
     */
    public function enqueue_assets() {
        // Enfileira o estilo compartilhando o arquivo da raiz
        wp_enqueue_style(
            'oscarlima-cookie-consent-style',
            '/css/cookie-consent.css',
            array(),
            '1.0.0',
            'all'
        );

        // Enfileira o script compartilhando o arquivo da raiz
        wp_enqueue_script(
            'oscarlima-cookie-consent-script',
            '/js/cookie-consent.js',
            array(),
            '1.0.0',
            true // Carrega no footer
        );
    }

    /**
     * Adiciona a página de opções no menu administrativo
     */
    public function add_admin_menu() {
        add_options_page(
            __( 'Consentimento de Cookies e Integrações', 'oscarlima-cookie-consent' ),
            __( 'Cookies & Integrações', 'oscarlima-cookie-consent' ),
            'manage_options',
            'oscarlima-cookie-consent',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Registra as configurações e opções do plugin
     */
    public function register_settings() {
        register_setting(
            'oscarlima_cookie_consent_group',
            'oscarlima_cookie_consent_options',
            array( $this, 'sanitize_and_write_json' )
        );
    }

    /**
     * Valida os dados antes de salvar no DB e escreve o arquivo JSON na raiz
     */
    public function sanitize_and_write_json( $input ) {
        $output = array();

        $output['active'] = isset( $input['active'] ) ? (bool) $input['active'] : false;
        $output['privacy_url'] = isset( $input['privacy_url'] ) ? esc_url_raw( $input['privacy_url'] ) : '/blog/politica-de-privacidade/';
        $output['ga_id'] = isset( $input['ga_id'] ) ? sanitize_text_field( $input['ga_id'] ) : '';
        $output['gtm_id'] = isset( $input['gtm_id'] ) ? sanitize_text_field( $input['gtm_id'] ) : '';
        $output['pixel_id'] = isset( $input['pixel_id'] ) ? sanitize_text_field( $input['pixel_id'] ) : '';
        
        // Mantém scripts customizados intactos para suportar HTML/JS completo (com sanitização leve de escape)
        $output['custom_analytics'] = isset( $input['custom_analytics'] ) ? trim( $input['custom_analytics'] ) : '';
        $output['custom_marketing'] = isset( $input['custom_marketing'] ) ? trim( $input['custom_marketing'] ) : '';

        // Tenta escrever o arquivo JSON na pasta raiz do site (e:/xampp/htdocs/oscarlima/js/)
        $json_dir = ABSPATH . '../js';
        $json_file = $json_dir . '/cookie-config.json';

        // Garante que o diretório exista
        if ( ! file_exists( $json_dir ) ) {
            wp_mkdir_p( $json_dir );
        }

        // Codifica os dados em formato JSON formatado
        $json_data = json_encode( $output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

        // Grava no arquivo
        $write_result = @file_put_contents( $json_file, $json_data );

        if ( $write_result === false ) {
            // Se falhar, define um transiente para mostrar aviso de erro administrativo
            set_transient( 'oscarlima_cc_write_error', true, 45 );
        } else {
            delete_transient( 'oscarlima_cc_write_error' );
        }

        return $output;
    }

    /**
     * Exibe um aviso se houver falhas ao salvar o arquivo JSON
     */
    public function display_permission_notice() {
        if ( get_transient( 'oscarlima_cc_write_error' ) ) {
            $json_dir = ABSPATH . '../js';
            ?>
            <div class="notice notice-error is-dismissible">
                <p>
                    <strong><?php _e( 'Erro do Plugin Oscar Lima Cookie Consent:', 'oscarlima-cookie-consent' ); ?></strong>
                    <?php echo sprintf( __( 'Não foi possível escrever o arquivo JSON de configuração em <code>%s/cookie-config.json</code>. Verifique as permissões de gravação da pasta.', 'oscarlima-cookie-consent' ), esc_html( $json_dir ) ); ?>
                </p>
            </div>
            <?php
            delete_transient( 'oscarlima_cc_write_error' );
        }
    }

    /**
     * Renderiza a página administrativa de configurações
     */
    public function render_settings_page() {
        // Obtém opções salvas ou define valores padrão
        $options = get_option( 'oscarlima_cookie_consent_options', array(
            'active' => true,
            'privacy_url' => '/blog/politica-de-privacidade/',
            'ga_id' => '',
            'gtm_id' => '',
            'pixel_id' => '',
            'custom_analytics' => '',
            'custom_marketing' => ''
        ) );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <p><?php _e( 'Gerencie as configurações do aviso de consentimento de cookies da LGPD e os IDs de integração. O plugin salvará as opções localmente e atualizará o arquivo JSON compartilhado com o site estático na raiz.', 'oscarlima-cookie-consent' ); ?></p>
            
            <form action="options.php" method="post" style="max-width: 800px; background: #fff; padding: 24px; border: 1px solid #ccd0d4; border-radius: 8px; margin-top: 20px;">
                <?php
                settings_fields( 'oscarlima_cookie_consent_group' );
                ?>
                
                <h2 style="border-bottom: 1px solid #ccd0d4; padding-bottom: 8px; margin-top: 0;"><?php _e( 'Configurações do Banner', 'oscarlima-cookie-consent' ); ?></h2>
                
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php _e( 'Status do Banner', 'oscarlima-cookie-consent' ); ?></th>
                        <td>
                            <label for="active">
                                <input type="checkbox" id="active" name="oscarlima_cookie_consent_options[active]" value="1" <?php checked( isset( $options['active'] ) && $options['active'], true ); ?>>
                                <?php _e( 'Ativar o Banner de Consentimento de Cookies no site e blog', 'oscarlima-cookie-consent' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="privacy_url"><?php _e( 'URL da Política de Privacidade', 'oscarlima-cookie-consent' ); ?></label></th>
                        <td>
                            <input type="text" id="privacy_url" name="oscarlima_cookie_consent_options[privacy_url]" value="<?php echo esc_attr( $options['privacy_url'] ); ?>" class="large-text" placeholder="/blog/politica-de-privacidade/">
                            <p class="description"><?php _e( 'Caminho ou URL completo para a página de políticas de privacidade do seu site.', 'oscarlima-cookie-consent' ); ?></p>
                        </td>
                    </tr>
                </table>

                <h2 style="border-bottom: 1px solid #ccd0d4; padding-bottom: 8px; margin-top: 30px;"><?php _e( 'IDs de Rastreamento (Injetados após consentimento)', 'oscarlima-cookie-consent' ); ?></h2>
                
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="ga_id"><?php _e( 'Google Analytics 4 (GA4 ID)', 'oscarlima-cookie-consent' ); ?></label></th>
                        <td>
                            <input type="text" id="ga_id" name="oscarlima_cookie_consent_options[ga_id]" value="<?php echo esc_attr( isset( $options['ga_id'] ) ? $options['ga_id'] : '' ); ?>" class="regular-text" placeholder="G-XXXXXXXXXX">
                            <p class="description"><?php _e( 'ID de medição do Google Analytics (carregado na categoria Analíticos).', 'oscarlima-cookie-consent' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gtm_id"><?php _e( 'Google Tag Manager (GTM ID)', 'oscarlima-cookie-consent' ); ?></label></th>
                        <td>
                            <input type="text" id="gtm_id" name="oscarlima_cookie_consent_options[gtm_id]" value="<?php echo esc_attr( isset( $options['gtm_id'] ) ? $options['gtm_id'] : '' ); ?>" class="regular-text" placeholder="GTM-XXXXXXX">
                            <p class="description"><?php _e( 'ID do Google Tag Manager (carregado na categoria Analíticos).', 'oscarlima-cookie-consent' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="pixel_id"><?php _e( 'Facebook Pixel ID', 'oscarlima-cookie-consent' ); ?></label></th>
                        <td>
                            <input type="text" id="pixel_id" name="oscarlima_cookie_consent_options[pixel_id]" value="<?php echo esc_attr( isset( $options['pixel_id'] ) ? $options['pixel_id'] : '' ); ?>" class="regular-text" placeholder="XXXXXXXXXXXXXXXX">
                            <p class="description"><?php _e( 'ID do pixel do Facebook (carregado na categoria Marketing).', 'oscarlima-cookie-consent' ); ?></p>
                        </td>
                    </tr>
                </table>

                <h2 style="border-bottom: 1px solid #ccd0d4; padding-bottom: 8px; margin-top: 30px;"><?php _e( 'Outros Scripts Personalizados (Opcional)', 'oscarlima-cookie-consent' ); ?></h2>
                <p class="description" style="color: #c33; font-weight: bold;"><?php _e( 'Atenção: Não inclua as tags &lt;script&gt; e &lt;/script&gt; se for apenas código JS, ou insira as tags HTML correspondentes.', 'oscarlima-cookie-consent' ); ?></p>
                
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="custom_analytics"><?php _e( 'Scripts de Análise Extras', 'oscarlima-cookie-consent' ); ?></label></th>
                        <td>
                            <textarea id="custom_analytics" name="oscarlima_cookie_consent_options[custom_analytics]" class="large-text code" rows="5" placeholder="<!-- Outras ferramentas de analytics -->"><?php echo esc_textarea( isset( $options['custom_analytics'] ) ? $options['custom_analytics'] : '' ); ?></textarea>
                            <p class="description"><?php _e( 'Scripts adicionais carregados se o usuário consentir com os cookies Analíticos.', 'oscarlima-cookie-consent' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="custom_marketing"><?php _e( 'Scripts de Marketing/Chatbot Extras', 'oscarlima-cookie-consent' ); ?></label></th>
                        <td>
                            <textarea id="custom_marketing" name="oscarlima_cookie_consent_options[custom_marketing]" class="large-text code" rows="5" placeholder="<!-- Outros pixels de anúncios ou chatbot -->"><?php echo esc_textarea( isset( $options['custom_marketing'] ) ? $options['custom_marketing'] : '' ); ?></textarea>
                            <p class="description"><?php _e( 'Scripts adicionais carregados se o usuário consentir com os cookies de Marketing.', 'oscarlima-cookie-consent' ); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button( __( 'Salvar Configurações e Atualizar JSON', 'oscarlima-cookie-consent' ) ); ?>
            </form>
        </div>
        <?php
    }
}

// Inicializa a classe principal do plugin
OscarLima_Cookie_Consent::get_instance();
