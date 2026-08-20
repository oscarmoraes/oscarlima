<?php
/**
 * Astra Child Theme functions and definitions
 * Organizado de forma Orientada a Objetos para máxima legibilidade e manutenção.
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Astra Child
 * @since 1.0.0
 */

// Evita acesso direto ao arquivo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe principal do Tema Filho Oscar Lima
 */
class OscarLima_Blog_Theme {

    /**
     * Instância única da classe (Singleton)
     */
    private static $instance = null;

    /**
     * Retorna a instância única da classe
     */
    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor da classe - Registra todos os hooks do WordPress e Astra
     */
    private function __construct() {
        // Inicializa as dependências e scripts do tema
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 15 );
        add_action( 'wp_head', array( $this, 'inject_google_fonts' ), 5 );
        add_filter( 'style_loader_tag', array( $this, 'defer_non_critical_styles' ), 10, 4 );
        add_action( 'wp_body_open', array( $this, 'inject_background_blobs' ) );
        add_filter( 'upload_mimes', array( $this, 'allow_svg_uploads' ) );
        
        // Configurações de layout para forçar a sidebar ativa
        add_filter( 'astra_page_layout', array( $this, 'force_sidebar_layout' ) );
        add_filter( 'astra_get_content_layout', array( $this, 'force_content_layout' ) );
        
        // Gatilhos de conversão em largura total e Chatbot
        add_action( 'astra_sidebars_before', array( $this, 'render_sticky_sidebar' ) );
        add_action( 'astra_single_header_bottom', array( $this, 'inject_single_post_header_meta' ) );
        add_action( 'astra_content_after', array( $this, 'inject_bottom_fullwidth_sections' ) );
        add_action( 'wp_footer', array( $this, 'inject_chatbot_ui' ) );

        // Desativação global de comentários e pings
        add_filter( 'comments_open', '__return_false', 20, 2 );
        add_filter( 'pings_open', '__return_false', 20, 2 );
        add_action( 'init', array( $this, 'disable_comments_support' ) );

        // Conexão do blog com a página principal (Header, Logo e Site Title)
        add_action( 'after_setup_theme', array( $this, 'setup_primary_nav_menu' ) );
        add_filter( 'get_custom_logo', array( $this, 'redirect_custom_logo_url' ) );
        add_filter( 'astra_site_title_href', array( $this, 'redirect_site_title_url' ) );

        // Imagem em Destaque Padrão / Fallback (Welcome Banner)
        add_filter( 'has_post_thumbnail', array( $this, 'fallback_has_post_thumbnail' ), 10, 3 );
        add_filter( 'post_thumbnail_html', array( $this, 'fallback_post_thumbnail_html' ), 10, 5 );
        add_filter( 'post_thumbnail_url', array( $this, 'fallback_post_thumbnail_url' ), 10, 3 );

        // Cabeçalho e Rodapé Custom Scripts
        add_action( 'wp_head', array( $this, 'inject_header_scripts' ), 20 );
        add_action( 'wp_footer', array( $this, 'inject_footer_scripts' ), 99 );

        // Painel Administrativo de Opções do Tema
        if ( is_admin() ) {
            add_action( 'admin_menu', array( $this, 'add_theme_options_menu' ) );
            add_action( 'admin_init', array( $this, 'register_theme_settings' ) );
        }
    }

    /**
     * Enfileira folhas de estilo, scripts e FontAwesome
     */
    public function enqueue_assets() {
        // FontAwesome para ícones premium do rodapé e sidebar
        wp_enqueue_style( 
            'fontawesome', 
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', 
            array(), 
            '6.4.0' 
        );

        // Tema Pai (Astra)
        wp_enqueue_style( 
            'astra-parent-theme-css', 
            get_template_directory_uri() . '/style.css', 
            array(), 
            'all' 
        );
        
        // Tema Filho
        wp_enqueue_style( 
            'astra-child-theme-css', 
            get_stylesheet_directory_uri() . '/style.css', 
            array('astra-parent-theme-css'), 
            time(), // Cache-busting automático usando timestamp durante o desenvolvimento
            'all' 
        );
    }

    /**
     * Injeta os blobs coloridos flutuantes de fundo (Design original oscarlima.com.br)
     */
    public function inject_background_blobs() {
        echo '<!-- Background Blobs -->';
        echo '<div class="bg-blobs">';
        echo '  <div class="blob blob-1"></div>';
        echo '  <div class="blob blob-2"></div>';
        echo '</div>';
    }

    /**
     * Permite upload de arquivos SVG nativos
     */
    public function allow_svg_uploads( $mimes ) {
        $mimes['svg'] = 'image/svg+xml';
        return $mimes;
    }

    /**
     * Força o layout de barra lateral direita nos artigos individuais
     */
    public function force_sidebar_layout( $layout ) {
        if ( is_single() ) {
            return 'right-sidebar';
        }
        return $layout;
    }

    /**
     * Força o layout do contêiner para 'content-boxed-layout' nos posts.
     * Importante: Isso impede que o Astra use o modo full-width/stretched e garante 
     * o espaço necessário para renderizar o elemento de sidebar na estrutura HTML do tema.
     */
    public function force_content_layout( $layout ) {
        if ( is_single() ) {
            return 'content-boxed-layout';
        }
        return $layout;
    }

    /**
     * Injeta as seções de Prova Social e Newsletter em largura total abaixo do conteúdo e sidebar
     */
    public function inject_bottom_fullwidth_sections() {
        if ( is_single() ) {
            ?>
            <!-- Gatilhos de Conversão: Seção de Prova Social e Resultados (Workana Testimonial) -->
            <section class="oscarlima-bottom-section oscarlima-bottom-proof">
                <div class="ast-container">
                    <div class="oscarlima-post-proof glass-panel">
                        <span class="cta-badge secondary-badge">PROVA SOCIAL</span>
                        <h4>Garantia de Performance &amp; Tecnologia</h4>
                        
                        <div class="proof-stats-grid">
                            <div class="stat-item">
                                <div class="stat-icon speed-score">100</div>
                                <div class="stat-info">
                                    <strong>Google PageSpeed</strong>
                                    <span>Carregamento instantâneo para máxima retenção de leads</span>
                                </div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-icon"><i class="fa-solid fa-robot"></i></div>
                                <div class="stat-info">
                                    <strong>IA Integrada &amp; Agentes</strong>
                                    <span>Automatização inteligente integrada para alavancar vendas</span>
                                </div>
                            </div>
                        </div>

                        <div class="proof-testimonial">
                            <p class="testimonial-quote">"Oscar é um dos melhores freelancers com quem trabalhei! Conhece muito, é dedicado, rápido e a entrega é superior à contratação."</p>
                            <div class="testimonial-author">
                                <div class="author-avatar">M</div>
                                <div class="author-info">
                                    <strong>Marcelo F.</strong>
                                    <span>Empresário (via Workana)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Gatilhos de Conversão: Newsletter Proeminente -->
            <section class="oscarlima-bottom-section oscarlima-bottom-newsletter">
                <div class="ast-container">
                    <div class="oscarlima-single-newsletter glass-panel">
                        <span class="cta-badge">NEWSLETTER</span>
                        <h3>Domine o Web Design &amp; IA</h3>
                        <p>Receba tutoriais práticos semanais, novidades sobre criação de agentes de IA e estratégias avançadas de conversão para alavancar seus projetos diretamente no seu e-mail.</p>
                        <form class="cta-form" action="#" method="post" onsubmit="alert('Obrigado por se inscrever! Enviaremos novidades em breve.'); return false;">
                            <input type="email" placeholder="Seu melhor e-mail" required />
                            <button type="submit">Inscrever-se</button>
                        </form>
                    </div>
                </div>
            </section>
            <?php
        }
    }

    /**
     * Remove o suporte a comentários dos posts e páginas
     */
    public function disable_comments_support() {
        remove_post_type_support( 'post', 'comments' );
        remove_post_type_support( 'page', 'comments' );
    }

    /**
     * Cria e vincula o menu primário dinamicamente contendo os links da home principal
     */
    public function setup_primary_nav_menu() {
        $menu_name = 'Primary Menu';
        $blog_home = esc_url( home_url( '/' ) );
        $saved_home = get_option( 'oscarlima_last_menu_home_url', '' );

        // Recria apenas se o menu não existir ou o endereço do site mudar (migração)
        if ( ! wp_get_nav_menu_object( $menu_name ) || $saved_home !== $blog_home ) {
            $menu_exists = wp_get_nav_menu_object( $menu_name );
            if ( $menu_exists ) {
                wp_delete_nav_menu( $menu_exists->term_id );
            }

            $menu_id = wp_create_nav_menu( $menu_name );
            $parent_root = str_replace( '/blog/', '/', $blog_home );

            wp_update_nav_menu_item( $menu_id, 0, array(
                'menu-item-title'   => 'Início',
                'menu-item-url'     => $parent_root . '#home',
                'menu-item-status'  => 'publish'
            ) );
            wp_update_nav_menu_item( $menu_id, 0, array(
                'menu-item-title'   => 'Especialidades',
                'menu-item-url'     => $parent_root . '#specialties',
                'menu-item-status'  => 'publish'
            ) );
            wp_update_nav_menu_item( $menu_id, 0, array(
                'menu-item-title'   => 'Performance',
                'menu-item-url'     => $parent_root . '#performance',
                'menu-item-status'  => 'publish'
            ) );
            wp_update_nav_menu_item( $menu_id, 0, array(
                'menu-item-title'   => 'Portfólio',
                'menu-item-url'     => $parent_root . '#portfolio',
                'menu-item-status'  => 'publish'
            ) );
            wp_update_nav_menu_item( $menu_id, 0, array(
                'menu-item-title'   => 'Depoimentos',
                'menu-item-url'     => $parent_root . '#testimonials',
                'menu-item-status'  => 'publish'
            ) );
            wp_update_nav_menu_item( $menu_id, 0, array(
                'menu-item-title'   => 'Blog',
                'menu-item-url'     => $blog_home,
                'menu-item-status'  => 'publish'
            ) );
            wp_update_nav_menu_item( $menu_id, 0, array(
                'menu-item-title'   => 'Contato',
                'menu-item-url'     => $parent_root . '#contact',
                'menu-item-status'  => 'publish'
            ) );

            $locations = get_theme_mod( 'nav_menu_locations' );
            $locations['primary'] = $menu_id;
            set_theme_mod( 'nav_menu_locations', $locations );

            update_option( 'oscarlima_last_menu_home_url', $blog_home );
        }
    }

    /**
     * Redireciona o link do logo customizado do blog para a página principal (root)
     */
    public function redirect_custom_logo_url( $html ) {
        $blog_home = esc_url( home_url( '/' ) ); // e.g. http://localhost/oscarlima/blog/
        $parent_root = str_replace( '/blog/', '/', $blog_home ); // e.g. http://localhost/oscarlima/
        
        // Substitui a URL do link no HTML do logo customizado
        $html = str_replace( 'href="' . $blog_home . '"', 'href="' . $parent_root . '"', $html );
        $html = str_replace( 'href=\'' . $blog_home . '\'', 'href=\'' . $parent_root . '\'', $html );
        
        return $html;
    }

    /**
     * Redireciona o link do título do site do blog para a página principal (root)
     */
    public function redirect_site_title_url( $url ) {
        $blog_home = esc_url( home_url( '/' ) );
        $parent_root = str_replace( '/blog/', '/', $blog_home );
        return $parent_root;
    }

    /**
     * Força has_post_thumbnail a retornar true para posts do blog (para exibir o fallback de boas-vindas)
     */
    public function fallback_has_post_thumbnail( $has_thumbnail, $post, $thumbnail_id ) {
        if ( ! $has_thumbnail ) {
            $post_obj = get_post( $post );
            if ( $post_obj && get_post_type( $post_obj ) === 'post' ) {
                return true;
            }
        }
        return $has_thumbnail;
    }

    /**
     * Injeta a imagem de boas-vindas padrão como HTML da imagem de destaque caso não haja uma cadastrada
     */
    public function fallback_post_thumbnail_html( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
        if ( ! $post_thumbnail_id ) {
            $default_url = get_stylesheet_directory_uri() . '/welcome-banner.png';
            $html = '<img src="' . esc_url( $default_url ) . '" class="attachment-large size-large wp-post-image default-fallback-featured" alt="' . esc_attr( get_the_title( $post_id ) ) . '" itemprop="image" decoding="async" />';
        }
        return $html;
    }

    /**
     * Filtra a URL da imagem de destaque padrão para retornar o caminho da imagem de boas-vindas
     */
    public function fallback_post_thumbnail_url( $thumbnail_url, $post, $size ) {
        if ( ! $thumbnail_url ) {
            $post_obj = get_post( $post );
            if ( $post_obj && get_post_type( $post_obj ) === 'post' ) {
                return get_stylesheet_directory_uri() . '/welcome-banner.png';
            }
        }
        return $thumbnail_url;
    }

    /**
     * Injeta metadados de autoridade e tempo de leitura no topo dos posts individuais
     */
    public function inject_single_post_header_meta() {
        if ( is_single() ) {
            global $post;
            $word_count = str_word_count( strip_tags( $post->post_content ) );
            $reading_time = ceil( $word_count / 200 );
            if ( $reading_time < 1 ) {
                $reading_time = 1;
            }
            $author_name = get_option( 'oscarlima_author_name', 'Oscar Lima Bot' );
            $author_subtitle = get_option( 'oscarlima_author_subtitle', 'Mestre da Preguiça Eficiente' );
            ?>
            <div class="oscarlima-author-meta">
                <div class="meta-author-avatar">OL</div>
                <div class="meta-author-details">
                    <span class="meta-author-by">Escrito por <strong><?php echo esc_html( $author_name ); ?></strong></span>
                    <span class="meta-author-subtitle"><?php echo esc_html( $author_subtitle ); ?></span>
                </div>
                <div class="meta-reading-time">
                    <i class="fa-regular fa-clock" style="margin-right: 5px;"></i>
                    <span>Leitura de <?php echo $reading_time; ?> min</span>
                </div>
            </div>

            <?php if ( has_post_thumbnail() ) : ?>
                <div class="oscarlima-single-featured-image">
                    <?php the_post_thumbnail( 'large', array( 'alt' => get_the_title() ) ); ?>
                </div>
            <?php endif; ?>
            <?php
        }
    }

    /**
     * Renderiza a barra lateral de conversão personalizada (Sticky)
     */
    public function render_sticky_sidebar() {
        $bio_widget = new OscarLima_Bio_Widget();
        $sticky_widgets = array(
            new OscarLima_Hostinger_Widget(),
            new OscarLima_CTA_Widget()
        );
        ?>
        <div class="oscarlima-normal-sidebar" style="margin-bottom: 30px;">
            <?php $bio_widget->render(); ?>
        </div>
        <div class="oscarlima-sticky-sidebar">
            <?php
            foreach ( $sticky_widgets as $widget ) {
                if ( $widget instanceof OscarLima_Renderable ) {
                    $widget->render();
                }
            }
            ?>
        </div>
        <?php
    }

    /**
     * Injeta a interface e a lógica do Chatbot de IA no rodapé
     */
    public function inject_chatbot_ui() {
        ?>
        <!-- Chatbot Floating UI -->
        <button id="chatbot-trigger" class="chatbot-trigger" aria-label="Abrir Chat Inteligente">
            <i class="fa-solid fa-brain" style="font-size: 1.5rem;"></i>
        </button>
     
        <div id="chatbot-panel" class="chatbot-panel glass-panel">
            <div class="chatbot-header">
                <div class="chatbot-user">
                    <div class="chatbot-avatar">OL</div>
                    <div class="chatbot-status">
                        <h4>Oscar Lima (IA)</h4>
                        <p>Online</p>
                    </div>
                </div>
                <button id="chatbot-close" class="chatbot-close" aria-label="Fechar Chat"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div id="chatbot-messages" class="chatbot-messages">
                <div class="chat-bubble bot">
                    Olá! Sou a Inteligência Artificial do Oscar Lima. 
                    Estou aqui para tirar suas dúvidas sobre criação de sites, estimativa de prazos ou otimização de velocidade de forma direta e sem termos técnicos complexos. 
                    O que você deseja construir para o seu negócio?
                </div>
            </div>
            <div class="chatbot-suggestions">
                <button class="suggestion-btn">Quero um orçamento</button>
                <button class="suggestion-btn">Otimizar velocidade</button>
                <button class="suggestion-btn">WordPress ou React?</button>
            </div>
            <div class="chatbot-input-area">
                <input type="text" id="chatbot-input" class="chatbot-input" placeholder="Digite sua mensagem...">
                <button id="chatbot-send" class="chatbot-send" aria-label="Enviar mensagem"><i class="fa-solid fa-paper-plane"></i></button>
            </div>
        </div>

        <!-- Controlador do Chatbot com chamada relativa para a API do Oscar -->
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const chatbotTrigger = document.getElementById('chatbot-trigger');
            const chatbotPanel = document.getElementById('chatbot-panel');
            const chatbotClose = document.getElementById('chatbot-close');
            const chatbotMessages = document.getElementById('chatbot-messages');
            const chatbotInput = document.getElementById('chatbot-input');
            const chatbotSend = document.getElementById('chatbot-send');
            const suggestionBtns = document.querySelectorAll('.suggestion-btn');

            if (chatbotTrigger && chatbotPanel) {
                chatbotTrigger.addEventListener('click', () => {
                    chatbotPanel.classList.toggle('open');
                    if (chatbotPanel.classList.contains('open') && chatbotInput) {
                        chatbotInput.focus();
                    }
                });

                if (chatbotClose) {
                    chatbotClose.addEventListener('click', () => {
                        chatbotPanel.classList.remove('open');
                    });
                }

                window.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && chatbotPanel.classList.contains('open')) {
                        chatbotPanel.classList.remove('open');
                    }
                });

                const appendMessage = (text, sender, isHtml = false) => {
                    if (!chatbotMessages) return;
                    const bubble = document.createElement('div');
                    bubble.classList.add('chat-bubble', sender);
                    if (isHtml) {
                        bubble.innerHTML = text;
                    } else {
                        bubble.textContent = text;
                    }
                    chatbotMessages.appendChild(bubble);
                    chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
                    return bubble;
                };

                const showTypingIndicator = () => {
                    if (!chatbotMessages) return;
                    const typing = document.createElement('div');
                    typing.classList.add('chat-bubble', 'bot', 'chat-typing');
                    typing.id = 'chat-typing-indicator';
                    typing.innerHTML = '<span></span><span></span><span></span>';
                    chatbotMessages.appendChild(typing);
                    chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
                };

                const removeTypingIndicator = () => {
                    const indicator = document.getElementById('chat-typing-indicator');
                    if (indicator) indicator.remove();
                };

                const sendMessageToAI = async (messageText) => {
                    if (!messageText.trim()) return;

                    appendMessage(messageText, 'user');
                    if (chatbotInput) chatbotInput.value = '';
                    showTypingIndicator();

                    try {
                        const response = await fetch('../api/chat.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({ message: messageText })
                        });
                        
                        const data = await response.json();
                        removeTypingIndicator();
                        
                        if (data && data.response) {
                            appendMessage(data.response, 'bot', true);
                        } else {
                            appendMessage('Desculpe, ocorreu um erro de resposta.', 'bot');
                        }
                    } catch (error) {
                        removeTypingIndicator();
                        appendMessage('Desculpe, ocorreu um erro de conexão.', 'bot');
                    }
                };

                if (chatbotSend) {
                    chatbotSend.addEventListener('click', () => {
                        if (chatbotInput) sendMessageToAI(chatbotInput.value);
                    });
                }

                if (chatbotInput) {
                    chatbotInput.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter') {
                            sendMessageToAI(chatbotInput.value);
                        }
                    });
                }

                suggestionBtns.forEach(btn => {
                    btn.addEventListener('click', () => {
                        sendMessageToAI(btn.textContent);
                    });
                });
            }
        });
        </script>
        <?php
    }

    /**
     * Injeta preconnect e tags do Google Fonts para eliminar o bloqueio de renderização do @import
     */
    public function inject_google_fonts() {
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
        echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap">' . "\n";
    }

    /**
     * Carrega folhas de estilo não críticas de forma assíncrona para melhorar a performance de LCP/FCP
     */
    public function defer_non_critical_styles( $tag, $handle, $href, $media ) {
        $non_critical_styles = array(
            'fontawesome',
            'oscarlima-cookie-consent-style',
            'oscarlima-cookie-consent-css'
        );
        
        if ( in_array( $handle, $non_critical_styles ) ) {
            return '<link rel="preload" href="' . esc_url( $href ) . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">' . "\n" . '<noscript><link rel="stylesheet" href="' . esc_url( $href ) . '"></noscript>';
        }
        return $tag;
    }

    /**
     * Injeta scripts e tags personalizadas do usuário no cabeçalho (<head>)
     */
    public function inject_header_scripts() {
        $header_scripts = get_option( 'oscarlima_header_scripts', '' );
        if ( ! empty( $header_scripts ) ) {
            echo "\n<!-- Oscar Lima Custom Header Scripts -->\n";
            echo $header_scripts . "\n";
        }
    }

    /**
     * Injeta scripts e tags personalizadas do usuário no rodapé (antes do </body>)
     */
    public function inject_footer_scripts() {
        $footer_scripts = get_option( 'oscarlima_footer_scripts', '' );
        if ( ! empty( $footer_scripts ) ) {
            echo "\n<!-- Oscar Lima Custom Footer Scripts -->\n";
            echo $footer_scripts . "\n";
        }
    }

    /**
     * Registra o menu administrativo de Opções do Tema Child
     */
    public function add_theme_options_menu() {
        add_theme_page(
            'Opções do Tema Oscar Lima',
            'Opções do Tema',
            'edit_theme_options',
            'oscarlima-theme-options',
            array( $this, 'render_theme_options_page' )
        );
    }

    /**
     * Registra todas as opções e configurações do tema
     */
    public function register_theme_settings() {
        register_setting( 'oscarlima_theme_options', 'oscarlima_header_scripts' );
        register_setting( 'oscarlima_theme_options', 'oscarlima_footer_scripts' );
        register_setting( 'oscarlima_theme_options', 'oscarlima_whatsapp_url' );
        register_setting( 'oscarlima_theme_options', 'oscarlima_contact_email' );
        register_setting( 'oscarlima_theme_options', 'oscarlima_author_name' );
        register_setting( 'oscarlima_theme_options', 'oscarlima_author_title' );
        register_setting( 'oscarlima_theme_options', 'oscarlima_author_subtitle' );
        register_setting( 'oscarlima_theme_options', 'oscarlima_author_bio' );
    }

    /**
     * Renderiza o painel nativo de Opções do Tema
     */
    public function render_theme_options_page() {
        ?>
        <div class="wrap">
            <h1 style="margin-bottom: 20px;">Configurações do Tema Astra Child - Oscar Lima 🎨</h1>
            
            <div class="card" style="max-width: 800px; padding: 20px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); background:#fff;">
                <form method="post" action="options.php">
                    <?php
                    settings_fields( 'oscarlima_theme_options' );
                    ?>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row" style="font-weight: 600; width: 250px;">Scripts no Cabeçalho (&lt;head&gt;):</th>
                                <td>
                                    <textarea name="oscarlima_header_scripts" rows="8" style="font-family:Consolas,monospace; width:100%;" placeholder="<!-- Ex: Google Analytics, Google Adsense, Meta Pixel -->"><?php echo esc_textarea( get_option('oscarlima_header_scripts') ); ?></textarea>
                                    <p class="description">Cole aqui seus scripts como Google Analytics, AdSense Auto Ads, Pixel de Facebook ou tags de verificação. Serão injetados dentro do <code>&lt;head&gt;</code> de todas as páginas.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row" style="font-weight: 600;">Scripts no Rodapé (Footer):</th>
                                <td>
                                    <textarea name="oscarlima_footer_scripts" rows="8" style="font-family:Consolas,monospace; width:100%;" placeholder="<!-- Ex: Scripts de Rastreamento de Conversão, Chatbots extras -->"><?php echo esc_textarea( get_option('oscarlima_footer_scripts') ); ?></textarea>
                                    <p class="description">Cole aqui scripts que devem carregar antes do fechamento de <code>&lt;/body&gt;</code>.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row" style="font-weight: 600;">URL de Contato WhatsApp:</th>
                                <td>
                                    <input type="text" name="oscarlima_whatsapp_url" value="<?php echo esc_url( get_option('oscarlima_whatsapp_url', 'https://wa.me/5521991061796') ); ?>" class="regular-text" style="width: 100%;" />
                                    <p class="description">Link completo do botão do WhatsApp (ex: <code>https://wa.me/5521991061796</code>).</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row" style="font-weight: 600;">E-mail de Contato:</th>
                                <td>
                                    <input type="email" name="oscarlima_contact_email" value="<?php echo esc_attr( get_option('oscarlima_contact_email', 'contato@oscarlima.com.br') ); ?>" class="regular-text" style="width: 100%;" />
                                    <p class="description">E-mail de contato principal do rodapé da barra lateral.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row" style="font-weight: 600;">Nome do Autor (Bio):</th>
                                <td>
                                    <input type="text" name="oscarlima_author_name" value="<?php echo esc_attr( get_option('oscarlima_author_name', 'Oscar Lima Bot') ); ?>" class="regular-text" style="width: 100%;" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row" style="font-weight: 600;">Título do Autor (Sidebar):</th>
                                <td>
                                    <input type="text" name="oscarlima_author_title" value="<?php echo esc_attr( get_option('oscarlima_author_title', 'Desenvolvedor por Curiosidade') ); ?>" class="regular-text" style="width: 100%;" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row" style="font-weight: 600;">Subtítulo do Autor (Post Header):</th>
                                <td>
                                    <input type="text" name="oscarlima_author_subtitle" value="<?php echo esc_attr( get_option('oscarlima_author_subtitle', 'Mestre da Preguiça Eficiente') ); ?>" class="regular-text" style="width: 100%;" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row" style="font-weight: 600;">Descrição da Biografia:</th>
                                <td>
                                    <textarea name="oscarlima_author_bio" rows="5" style="width:100%;"><?php echo esc_textarea( get_option('oscarlima_author_bio', 'Não sou formado em nada, sou 100% autodidata e preguiçoso demais para fazer tarefas repetitivas. Meu primeiro contato com o mundo digital foi no curso do Jonathan Taioba sobre como criar blogs. Anos depois, finalmente tirei esse projeto do papel usando a Inteligência Artificial para automatizar tudo.') ); ?></textarea>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <?php submit_button('Salvar Configurações do Tema'); ?>
                </form>
            </div>
        </div>
        <?php
    }
}

// Inicializa a classe única do Tema Filho
OscarLima_Blog_Theme::get_instance();

/**
 * ==========================================================================
 * Estrutura Orientada a Objetos para Widgets Personalizados da Sidebar
 * ==========================================================================
 */

interface OscarLima_Renderable {
    public function render();
}

abstract class OscarLima_Base_Widget implements OscarLima_Renderable {
    protected $class_name;
    
    public function __construct( $class_name ) {
        $this->class_name = $class_name;
    }
    
    abstract public function render_content();
    
    public function render() {
        echo '<div class="oscarlima-sidebar-widget ' . esc_attr( $this->class_name ) . '">';
        $this->render_content();
        echo '</div>';
    }
}

class OscarLima_Bio_Widget extends OscarLima_Base_Widget {
    public function __construct() {
        parent::__construct( 'oscarlima-bio-card' );
    }
    
    public function render_content() {
        $author_name = get_option( 'oscarlima_author_name', 'Oscar Lima Bot' );
        $author_title = get_option( 'oscarlima_author_title', 'Desenvolvedor por Curiosidade' );
        $author_bio = get_option( 'oscarlima_author_bio', 'Não sou formado em nada, sou 100% autodidata e preguiçoso demais para fazer tarefas repetitivas. Meu primeiro contato com o mundo digital foi no curso do Jonathan Taioba sobre como criar blogs. Anos depois, finalmente tirei esse projeto do papel usando a Inteligência Artificial para automatizar tudo.' );
        $whatsapp_url = get_option( 'oscarlima_whatsapp_url', 'https://wa.me/5521991061796' );
        $contact_email = get_option( 'oscarlima_contact_email', 'contato@oscarlima.com.br' );
        ?>
        <div class="oscarlima-bio-header">
            <div class="bio-avatar">OL</div>
            <div class="bio-name-area">
                <h4><?php echo esc_html( $author_name ); ?></h4>
                <span class="bio-title"><?php echo esc_html( $author_title ); ?></span>
            </div>
        </div>
        <p class="bio-desc"><?php echo esc_html( $author_bio ); ?></p>
        <div class="oscarlima-bio-socials">
            <a href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" aria-label="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
            <a href="mailto:<?php echo esc_attr( $contact_email ); ?>" aria-label="Email"><i class="fa-solid fa-envelope"></i></a>
            <a href="https://linkedin.com" target="_blank" aria-label="LinkedIn"><i class="fa-brands fa-linkedin"></i></a>
            <a href="https://github.com" target="_blank" aria-label="GitHub"><i class="fa-brands fa-github"></i></a>
        </div>
        <?php
    }
}

class OscarLima_Hostinger_Widget extends OscarLima_Base_Widget {
    public function __construct() {
        parent::__construct( 'oscarlima-hostinger-widget' );
    }
    
    public function render_content() {
        ?>
        <span class="widget-badge hostinger-badge">MAIS POPULAR</span>
        <div class="hostinger-header" style="margin-top: 10px;">
            <h4>Agentes Starter</h4>
            <p class="widget-subtitle">Expanda seu negócio com Agentes de IA</p>
        </div>
        
        <div class="hostinger-price-box" style="margin: 15px 0 !important; display: flex; flex-direction: column; gap: 4px;">
            <div style="display: flex; align-items: baseline; gap: 8px;">
                <span class="price-original" style="text-decoration: line-through; color: var(--color-text-muted); font-size: 0.95rem;">R$ 51,99</span>
                <span class="price-value"><small>R$</small>33,59<small>/mês</small></span>
            </div>
            <div class="hostinger-discount-badge" style="background: rgba(16, 185, 129, 0.1); color: var(--color-success); font-size: 0.75rem; font-weight: 700; padding: 4px 8px; border-radius: 4px; display: inline-block; border: 1px solid rgba(16, 185, 129, 0.2); width: fit-content; margin-top: 4px;">
                Cupom: <strong style="color: #10b981;">OscarLimaSite</strong> (20% OFF EXTRA)
            </div>
        </div>

        <ul class="oscarlima-service-list hostinger-features">
            <li><i class="fa-solid fa-check text-purple"></i> 1.000 créditos / mês</li>
            <li><i class="fa-solid fa-check text-purple"></i> 7 agentes de IA especializados</li>
            <li><i class="fa-solid fa-check text-purple"></i> 100+ habilidades prontas</li>
            <li><i class="fa-solid fa-check text-purple"></i> Conexão com 1.000+ aplicativos</li>
        </ul>

        <a href="https://www.hostinger.com/br/cart?product=hagents%3Astarter&period=12&referral_type=cart_link&REFERRALCODE=OscarLimaSite&referral_id=019e70d1-cf5d-712a-a786-a8445448547d" class="cta-hostinger-btn" target="_blank">
            Quero Agentes de IA
        </a>
        <?php
    }
}

class OscarLima_CTA_Widget extends OscarLima_Base_Widget {
    public function __construct() {
        parent::__construct( 'oscarlima-cta-widget' );
    }
    
    public function render_content() {
        $whatsapp_url = get_option( 'oscarlima_whatsapp_url', 'https://wa.me/5521991061796' );
        ?>
        <span class="widget-badge">SOLUÇÕES PREMIUM</span>
        <h4>Projetos e Consultoria</h4>
        <p class="widget-subtitle">Acelere seu negócio com soluções sob medida:</p>
        <ul class="oscarlima-service-list">
            <li><i class="fa-solid fa-circle-check"></i> Landing Pages Ultra Rápidas</li>
            <li><i class="fa-solid fa-circle-check"></i> Integração de Agentes de IA</li>
            <li><i class="fa-solid fa-circle-check"></i> Automação de Processos</li>
            <li><i class="fa-solid fa-circle-check"></i> SEO &amp; Performance Extrema</li>
        </ul>
        <a href="<?php echo esc_url( $whatsapp_url ); ?>" class="cta-whatsapp-btn" target="_blank">
            <svg class="whatsapp-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" width="16" height="16" fill="currentColor" style="margin-right: 8px; vertical-align: -2px;"><path d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-69.8 18.3L72 359.2l-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z"/></svg>
            Fale comigo
        </a>
        <?php
    }
}


