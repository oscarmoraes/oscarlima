<?php
/**
 * Plugin Name: Oscar Lima Article Generator
 * Description: Gerador de Artigos Automatizado SEO/GEO com painel nativo em abas, nuvem de tags automática, palavra-chave foco, Schema JSON-LD, Open Graph/Twitter Cards nativos e índice dinâmico (TOC) nos posts.
 * Version: 2.0.0
 * Author: Oscar Lima
 * License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OscarLima_Article_Generator {
    
    // Nomes das opções de armazenamento no banco de dados
    private static $option_api_key = 'oscarlima_openai_api_key';
    private static $option_model = 'oscarlima_openai_model';
    private static $option_cron_active = 'oscarlima_cron_active';
    private static $option_default_status = 'oscarlima_default_post_status';
    
    private static $option_pexels_key = 'oscarlima_pexels_api_key';
    private static $option_pixabay_key = 'oscarlima_pixabay_api_key';
    private static $option_image_provider = 'oscarlima_image_provider';
    
    private static $option_title_min = 'oscarlima_seo_title_min';
    private static $option_title_max = 'oscarlima_seo_title_max';
    private static $option_desc_min = 'oscarlima_seo_desc_min';
    private static $option_desc_max = 'oscarlima_seo_desc_max';
    private static $option_auto_tags = 'oscarlima_auto_tags';

    private static $option_toc_active = 'oscarlima_toc_active';
    private static $option_toc_title = 'oscarlima_toc_title';

    // Imagens abstratas reutilizáveis (fonte única, sem duplicação)
    private static $abstract_images = array(
        'https://images.unsplash.com/photo-1620712943543-bcc4688e7485?auto=format&fit=crop&w=%s&q=80',
        'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=%s&q=80',
        'https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?auto=format&fit=crop&w=%s&q=80',
        'https://images.unsplash.com/photo-1639762681485-074b7f938ba0?auto=format&fit=crop&w=%s&q=80',
        'https://images.unsplash.com/photo-1507668077129-56e32842fceb?auto=format&fit=crop&w=%s&q=80',
        'https://images.unsplash.com/photo-1677442135136-760c813028c0?auto=format&fit=crop&w=%s&q=80',
        'https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=%s&q=80'
    );

    // Mapeamento de termos de busca em inglês como fallback por categoria
    private static $category_search_fallbacks = array(
        'Inteligência Artificial'    => 'artificial intelligence, technology, neural network',
        'Clima e Energia Limpa'      => 'solar panels, renewable energy, green technology',
        'Saúde e Biotecnologia'      => 'biotechnology, medical laboratory, science research',
        'Robótica e Automação'       => 'robotics, automation, industrial robot',
        'Exploração Espacial'        => 'space exploration, galaxy space, astronaut',
        'Economia Digital e Fintech' => 'digital economy, mobile payment technology, stock market fintech',
        'Segurança e Privacidade'    => 'cybersecurity, internet security, computer hacking'
    );

    // Configuração das 7 subcategorias estratégicas e respectivos feeds RSS expandidos
    private static $feed_config = array(
        'Inteligência Artificial' => array(
            'https://techcrunch.com/category/artificial-intelligence/feed/',
            'https://venturebeat.com/category/ai/feed/',
            'https://www.marktechpost.com/feed/',
            'https://www.technologyreview.com/feed/',
            'https://research.google/blog/rss/'
        ),
        'Clima e Energia Limpa' => array(
            'https://techcrunch.com/category/climate/feed/',
            'https://spectrum.ieee.org/feeds/topic/energy.rss',
            'https://www.scientificamerican.com/feed/'
        ),
        'Saúde e Biotecnologia' => array(
            'https://techcrunch.com/category/biotech/feed/',
            'https://spectrum.ieee.org/feeds/topic/biomedical.rss',
            'https://www.wired.com/feed/category/science/latest/rss',
            'https://endpts.com/feed/',
            'https://www.fiercebiotech.com/rss/biotech'
        ),
        'Robótica e Automação' => array(
            'https://techcrunch.com/category/robotics/feed/',
            'https://spectrum.ieee.org/feeds/topic/robotics.rss',
            'https://venturebeat.com/category/automation/feed/',
            'https://feeds.arstechnica.com/arstechnica/index/'
        ),
        'Exploração Espacial' => array(
            'https://techcrunch.com/category/space/feed/',
            'https://spectrum.ieee.org/feeds/topic/aerospace.rss',
            'https://www.space.com/feeds/all',
            'https://www.scientificamerican.com/feed/'
        ),
        'Economia Digital e Fintech' => array(
            'https://techcrunch.com/category/fintech/feed/',
            'https://venturebeat.com/category/business/feed/',
            'https://www.wired.com/feed/category/business/latest/rss',
            'https://www.zdnet.com/news/rss.xml'
        ),
        'Segurança e Privacidade' => array(
            'https://techcrunch.com/category/security/feed/',
            'https://venturebeat.com/category/security/feed/',
            'https://www.wired.com/feed/category/security/latest/rss',
            'https://www.bleepingcomputer.com/feed/',
            'https://krebsonsecurity.com/feed/'
        )
    );

    /**
     * Inicializa o plugin registrando ações, ganchos e filtros
     */
    public static function init() {
        add_action( 'oscarlima_daily_article_cron', array( __CLASS__, 'generate_daily_article' ) );
        add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );

        // Filtro nativo para injetar o Índice (TOC) no post
        add_filter( 'the_content', array( __CLASS__, 'inject_table_of_contents' ) );

        // Injeta Schema JSON-LD e Meta tags Open Graph/Twitter nativamente
        add_action( 'wp_head', array( __CLASS__, 'inject_seo_metadata' ) );

        // Gerenciamento dinâmico do Cron conforme configurações do usuário
        if ( get_option( self::$option_cron_active, '1' ) === '1' ) {
            if ( ! wp_next_scheduled( 'oscarlima_daily_article_cron' ) ) {
                wp_schedule_event( time(), 'daily', 'oscarlima_daily_article_cron' );
            }
        } else {
            $timestamp = wp_next_scheduled( 'oscarlima_daily_article_cron' );
            if ( $timestamp ) {
                wp_unschedule_event( $timestamp, 'oscarlima_daily_article_cron' );
            }
        }
    }

    /**
     * Adiciona o menu do Gerador no Painel Admin do WordPress
     */
    public static function add_admin_menu() {
        add_menu_page(
            'Gerador de Artigos',
            'Gerador de Artigos',
            'manage_options',
            'oscarlima-generator',
            array( __CLASS__, 'render_admin_page' ),
            'dashicons-admin-post',
            6
        );
    }

    private static function get_api_key() {
        return get_option( self::$option_api_key, '' );
    }

    // =========================================================================
    // MÉTODOS AUXILIARES REUTILIZÁVEIS (eliminam duplicação entre gerar/regerar)
    // =========================================================================

    /**
     * Retorna uma URL de imagem abstrata aleatória no tamanho solicitado
     */
    private static function get_random_image_url( $width = '800' ) {
        $template = self::$abstract_images[ array_rand( self::$abstract_images ) ];
        return sprintf( $template, $width );
    }

    /**
     * Extrai o conteúdo de uma tag com ou sem fechamento de forma tolerante a falhas
     */
    private static function extract_tag_content( $string, $tag ) {
        $tag = strtoupper( $tag );
        // 1. Tenta correspondência exata com tag de fechamento
        if ( preg_match( '/\[' . $tag . '\](.*?)\[\/' . $tag . '\]/is', $string, $matches ) ) {
            return trim( $matches[1] );
        }
        // 2. Fallback: Captura até a próxima abertura de tag [OUTRA_TAG] ou fim da string
        if ( preg_match( '/\[' . $tag . '\](.*?)(?=\[[A-Z_]+\]|$)/is', $string, $matches ) ) {
            return trim( $matches[1] );
        }
        return '';
    }

    /**
     * Parser unificado da resposta da IA — extrai título, resumo, foco, tags, FAQ e conteúdo
     */
    private static function parse_ai_response( $raw ) {
        $result = array(
            'title'         => '',
            'excerpt'       => '',
            'focus_keyword' => '',
            'tags'          => array(),
            'faq'           => '',
            'content'       => ''
        );

        // Padroniza acentuações comuns que a IA possa gerar por engano nas tags
        $raw = str_ireplace(
            array( '[TÍTULO]', '[/TÍTULO]', '[CONTEÚDO]', '[/CONTEÚDO]', '[RESUMO/]', '[FAQ/]' ),
            array( '[TITULO]', '[/TITULO]', '[CONTEUDO]', '[/CONTEUDO]', '[/RESUMO]', '[/FAQ]' ),
            $raw
        );

        $result['title']         = wp_strip_all_tags( self::extract_tag_content( $raw, 'TITULO' ) );
        $result['excerpt']       = wp_strip_all_tags( self::extract_tag_content( $raw, 'RESUMO' ) );
        $result['focus_keyword'] = wp_strip_all_tags( self::extract_tag_content( $raw, 'PALAVRA_FOCO' ) );

        $tags_raw                = wp_strip_all_tags( self::extract_tag_content( $raw, 'PALAVRAS_CHAVE' ) );
        $result['tags']          = ! empty( $tags_raw ) ? array_map( 'trim', explode( ',', $tags_raw ) ) : array();

        $result['faq']           = self::extract_tag_content( $raw, 'FAQ' );
        $result['content']       = self::extract_tag_content( $raw, 'CONTEUDO' );

        // Limpeza final do título (remove tags que possam ter vazado)
        $result['title'] = self::clean_title( $result['title'] );

        return $result;
    }

    /**
     * Remove qualquer tag de delimitação da IA que tenha vazado para o título
     */
    private static function clean_title( $title ) {
        $title = preg_replace( '/\[PALAVRA_FOCO\].*?(\[\/PALAVRA_FOCO\]|$)/is', '', $title );
        $title = preg_replace( '/\[PALAVRAS_CHAVE\].*?(\[\/PALAVRAS_CHAVE\]|$)/is', '', $title );
        $title = str_replace(
            array( '[PALAVRA_FOCO]', '[/PALAVRA_FOCO]', '[PALAVRAS_CHAVE]', '[/PALAVRAS_CHAVE]', '[TITULO]', '[/TITULO]', '[FAQ]', '[/FAQ]', '[RESUMO]', '[/RESUMO]', '[CONTEUDO]', '[/CONTEUDO]' ),
            '',
            $title
        );
        return wp_strip_all_tags( trim( $title ) );
    }

    /**
     * Injeta imagem secundária inline após o primeiro H2 do conteúdo
     */
    private static function inject_secondary_image( $content, $title ) {
        $image_url = self::get_random_image_url( '800' );
        $img_tag = "\n<p style='text-align:center;'><img src='" . esc_url( $image_url ) . "' alt='" . esc_attr( $title ) . "' loading='lazy' width='800' height='450' decoding='async' style='border-radius:12px; max-width:100%; height:auto; margin:25px 0; box-shadow:0 4px 15px rgba(0,0,0,0.15);' /></p>\n";
        
        $pos = strpos( $content, '</h2>' );
        if ( $pos !== false ) {
            return substr_replace( $content, '</h2>' . $img_tag, $pos, 5 );
        }
        return $img_tag . $content;
    }

    /**
     * Extrai pares pergunta/resposta do HTML de FAQ e salva como post_meta estruturado
     */
    private static function save_faq_metadata( $post_id, $faq_html ) {
        if ( empty( $faq_html ) ) {
            delete_post_meta( $post_id, '_oscarlima_faq_data' );
            return;
        }
        preg_match_all( '/<h3[^>]*>(.*?)<\/h3>\s*<p[^>]*>(.*?)<\/p>/is', $faq_html, $pairs, PREG_SET_ORDER );
        $faq_data = array();
        foreach ( $pairs as $pair ) {
            $faq_data[] = array(
                'question' => wp_strip_all_tags( trim( $pair[1] ) ),
                'answer'   => wp_strip_all_tags( trim( $pair[2] ) )
            );
        }
        if ( ! empty( $faq_data ) ) {
            update_post_meta( $post_id, '_oscarlima_faq_data', $faq_data );
        } else {
            delete_post_meta( $post_id, '_oscarlima_faq_data' );
        }
    }

    /**
     * Salva palavra-chave foco e tags via post_meta nativo do WordPress
     */
    private static function save_seo_metadata( $post_id, $focus_keyword, $tags ) {
        if ( ! empty( $focus_keyword ) ) {
            update_post_meta( $post_id, '_oscarlima_focus_keyword', sanitize_text_field( $focus_keyword ) );
        }
        if ( get_option( self::$option_auto_tags, '1' ) === '1' && ! empty( $tags ) ) {
            wp_set_post_tags( $post_id, $tags, false );
        }
    }

    /**
     * Monta o conteúdo final concatenando FAQ e imagem secundária
     */
    private static function assemble_final_content( $content, $faq, $title ) {
        // Anexa FAQ como seção final
        if ( ! empty( $faq ) ) {
            $content .= "\n\n<h2 class='oscarlima-faq-title'>Perguntas Frequentes (FAQ)</h2>\n" . $faq;
        }
        // Injeta imagem inline após primeiro H2
        $content = self::inject_secondary_image( $content, $title );
        return $content;
    }

    // =========================================================================
    // TOC (ÍNDICE DE CONTEÚDO)
    // =========================================================================

    /**
     * Injeta a Caixa de Índice (TOC) de forma dinâmica no topo do post
     */
    public static function inject_table_of_contents( $content ) {
        if ( ! is_single() || ! is_main_query() || get_post_type() !== 'post' ) {
            return $content;
        }

        if ( get_option( self::$option_toc_active, '1' ) !== '1' ) {
            return $content;
        }

        // Busca todas as tags H2 no conteúdo
        preg_match_all( '/<h2[^>]*>(.*?)<\/h2>/i', $content, $matches, PREG_OFFSET_CAPTURE );

        if ( empty( $matches[0] ) || count( $matches[0] ) < 2 ) {
            return $content; // Requer pelo menos 2 seções para fazer sentido criar o índice
        }

        $toc_title = get_option( self::$option_toc_title, 'Índice de Conteúdo' );

        // Estilos modernos neon/glassmorphic para combinar com o layout Astra-OscarLima
        $toc_html = '<div class="oscarlima-toc" style="background: rgba(13, 13, 25, 0.75); border: 1px solid rgba(0, 210, 255, 0.25); border-radius: 12px; padding: 20px; margin: 25px 0; backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); box-shadow: 0 8px 32px rgba(0,0,0,0.3);">';
        $toc_html .= '<h4 style="margin-top:0; margin-bottom:15px; color:#00d2ff; font-size:16px; font-weight:600; font-family:\'Outfit\',sans-serif; display:flex; align-items:center; gap:8px;"><span class="dashicons dashicons-list-view" style="margin-top:2px; color:#00d2ff;"></span> ' . esc_html( $toc_title ) . '</h4>';
        $toc_html .= '<ul style="list-style:none; padding-left:0; margin:0; display:grid; grid-template-columns:1fr; gap:10px;">';

        $new_content = '';
        $last_offset = 0;
        $index = 1;

        foreach ( $matches[0] as $match_index => $match_data ) {
            $full_h2 = $match_data[0];
            $offset = $match_data[1];
            $h2_text = strip_tags( $matches[1][$match_index][0] );

            // Remove strings de TL;DR ou tags especiais do texto do link
            $h2_text_clean = str_replace( array( 'Resumo Rápido (TL;DR)', 'TL;DR' ), 'Resumo Rápido', $h2_text );
            
            // Cria um ID amigável de âncora
            $h2_id = 'secao-' . $index;

            $toc_html .= '<li style="margin:0; padding:0; line-height: 1.4;"><a href="#' . $h2_id . '" style="color:#d1d1e0; text-decoration:none; font-size:14px; font-weight:500; transition: all 0.2s;" onmouseover="this.style.color=\'#00d2ff\'; this.style.paddingLeft=\'4px\';" onmouseout="this.style.color=\'#d1d1e0\'; this.style.paddingLeft=\'0px\';"><span style="color:#a855f7; font-weight:bold; margin-right:8px;">' . $index . '.</span> ' . esc_html( $h2_text_clean ) . '</a></li>';

            // Injeta o ID dinâmico no H2 preservando atributos existentes
            if ( preg_match( '/^<h2(\s|>)/i', $full_h2 ) ) {
                $replacement_h2 = '<h2 id="' . $h2_id . '"' . substr( $full_h2, 3 );
            } else {
                $replacement_h2 = '<h2 id="' . $h2_id . '">' . strip_tags( $full_h2 ) . '</h2>';
            }

            $new_content .= substr( $content, $last_offset, $offset - $last_offset ) . $replacement_h2;
            $last_offset = $offset + strlen( $full_h2 );

            $index++;
        }

        $toc_html .= '</ul></div>';
        $new_content .= substr( $content, $last_offset );

        // Injeta a caixa do Índice logo antes da primeira tag H2
        $pos_inject = strpos( $new_content, 'id="secao-1"' );
        if ( $pos_inject !== false ) {
            $start_tag = strrpos( substr( $new_content, 0, $pos_inject ), '<h2' );
            if ( $start_tag !== false ) {
                $new_content = substr_replace( $new_content, $toc_html . "\n", $start_tag, 0 );
            } else {
                $new_content = $toc_html . $new_content;
            }
        } else {
            $new_content = $toc_html . $new_content;
        }

        return $new_content;
    }

    // =========================================================================
    // SEO NATIVO (Schema JSON-LD + Open Graph + Twitter Cards)
    // =========================================================================

    /**
     * Injeta Schema JSON-LD (BlogPosting + FAQPage) e metadados Open Graph/Twitter Cards no head
     * Totalmente nativo — sem dependência de plugins de terceiros
     */
    public static function inject_seo_metadata() {
        if ( ! is_single() || get_post_type() !== 'post' ) {
            return;
        }
        global $post;
        if ( ! $post ) {
            return;
        }

        $title   = get_the_title( $post->ID );
        $excerpt = get_the_excerpt( $post->ID );
        $url     = get_permalink( $post->ID );
        $image   = get_the_post_thumbnail_url( $post->ID, 'full' );

        // Schema.org BlogPosting
        $schema = array(
            '@context'         => 'https://schema.org',
            '@type'            => 'BlogPosting',
            'headline'         => $title,
            'description'      => $excerpt,
            'author'           => array(
                '@type' => 'Person',
                'name'  => 'Oscar Lima'
            ),
            'publisher'        => array(
                '@type' => 'Organization',
                'name'  => 'Oscar Lima',
                'logo'  => array(
                    '@type' => 'ImageObject',
                    'url'   => esc_url( home_url( '/favicon.ico' ) )
                )
            ),
            'datePublished'    => get_the_date( 'c', $post->ID ),
            'dateModified'     => get_the_modified_date( 'c', $post->ID ),
            'mainEntityOfPage' => $url,
            'image'            => $image
        );

        echo "\n<!-- Oscar Lima SEO Schema -->\n";
        echo '<script type="application/ld+json">' . json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>';

        // FAQ Schema se dados do FAQ existirem
        $faq_data = get_post_meta( $post->ID, '_oscarlima_faq_data', true );
        if ( ! empty( $faq_data ) && is_array( $faq_data ) ) {
            $faq_schema = array(
                '@context'   => 'https://schema.org',
                '@type'      => 'FAQPage',
                'mainEntity' => array()
            );
            foreach ( $faq_data as $faq ) {
                $faq_schema['mainEntity'][] = array(
                    '@type'          => 'Question',
                    'name'           => $faq['question'],
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text'  => $faq['answer']
                    )
                );
            }
            echo "\n<!-- Oscar Lima FAQ Schema -->\n";
            echo '<script type="application/ld+json">' . json_encode( $faq_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>';
        }

        // Open Graph + Twitter Cards — sempre injetados nativamente
        $title_esc   = esc_attr( $title );
        $url_esc     = esc_url( $url );
        $excerpt_esc = esc_attr( $excerpt );
        $image_esc   = esc_url( $image );

        echo "\n<!-- Oscar Lima Social Meta Tags -->\n";
        echo '<meta property="og:type" content="article" />' . "\n";
        echo '<meta property="og:title" content="' . $title_esc . '" />' . "\n";
        echo '<meta property="og:description" content="' . $excerpt_esc . '" />' . "\n";
        echo '<meta property="og:url" content="' . $url_esc . '" />' . "\n";
        if ( $image ) {
            echo '<meta property="og:image" content="' . $image_esc . '" />' . "\n";
        }
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        echo '<meta name="twitter:title" content="' . $title_esc . '" />' . "\n";
        echo '<meta name="twitter:description" content="' . $excerpt_esc . '" />' . "\n";
        if ( $image ) {
            echo '<meta name="twitter:image" content="' . $image_esc . '" />' . "\n";
        }

        // Meta keyword nativa (focus keyword salva via post_meta)
        $focus_kw = get_post_meta( $post->ID, '_oscarlima_focus_keyword', true );
        if ( ! empty( $focus_kw ) ) {
            echo '<meta name="keywords" content="' . esc_attr( $focus_kw ) . '" />' . "\n";
        }
    }

    // =========================================================================
    // PAINEL ADMINISTRATIVO
    // =========================================================================

    /**
     * Renderiza a página de controle em Abas Nativas
     */
    public static function render_admin_page() {
        // Define a aba ativa (padrão: general)
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';

        $message = '';
        $message_type = 'info';

        // Processamento de formulários por aba
        if ( isset( $_POST['oscarlima_save_settings'] ) ) {
            check_admin_referer( 'oscarlima_save_settings_nonce', 'oscarlima_nonce' );

            if ( $active_tab === 'general' ) {
                update_option( self::$option_api_key, sanitize_text_field( $_POST['openai_api_key'] ) );
                update_option( self::$option_model, sanitize_text_field( $_POST['openai_model'] ) );
                update_option( self::$option_pexels_key, sanitize_text_field( $_POST['pexels_api_key'] ) );
                update_option( self::$option_pixabay_key, sanitize_text_field( $_POST['pixabay_api_key'] ) );
                update_option( self::$option_image_provider, sanitize_text_field( $_POST['image_provider'] ) );
                update_option( self::$option_cron_active, isset( $_POST['cron_active'] ) ? '1' : '0' );
                update_option( self::$option_default_status, sanitize_text_field( $_POST['default_post_status'] ) );
                
                // Força re-registro do Cron imediatamente
                self::init();

                $message = "Configurações Gerais salvas com sucesso!";
                $message_type = "success";
            } 
            elseif ( $active_tab === 'seo' ) {
                update_option( self::$option_title_min, intval( $_POST['seo_title_min'] ) );
                update_option( self::$option_title_max, intval( $_POST['seo_title_max'] ) );
                update_option( self::$option_desc_min, intval( $_POST['seo_desc_min'] ) );
                update_option( self::$option_desc_max, intval( $_POST['seo_desc_max'] ) );
                update_option( self::$option_auto_tags, isset( $_POST['auto_tags'] ) ? '1' : '0' );

                $message = "Configurações de SEO salvas com sucesso!";
                $message_type = "success";
            } 
            elseif ( $active_tab === 'toc' ) {
                update_option( self::$option_toc_active, isset( $_POST['toc_active'] ) ? '1' : '0' );
                update_option( self::$option_toc_title, sanitize_text_field( $_POST['toc_title'] ) );

                $message = "Configurações do Índice (TOC) salvas com sucesso!";
                $message_type = "success";
            }
        }

        // Ações de Geração Manual
        if ( isset( $_POST['oscarlima_generate_action'] ) && check_admin_referer( 'oscarlima_generate_nonce', 'oscarlima_nonce' ) ) {
            if ( empty( self::get_api_key() ) ) {
                $message = "Chave API OpenAI não configurada na aba Geral.";
                $message_type = 'error';
            } else {
                if ( function_exists( 'set_time_limit' ) ) {
                    @set_time_limit( 180 );
                }

                $cat_to_generate = sanitize_text_field( $_POST['oscarlima_category'] );
                if ( $cat_to_generate === 'auto' ) {
                    $cat_to_generate = null;
                }

                $result = self::generate_custom_article( $cat_to_generate, 'draft' );

                if ( $result['success'] ) {
                    $edit_link = get_edit_post_link( $result['post_id'] );
                    $message = "<strong>Artigo gerado com sucesso!</strong><br>";
                    $message .= "Título: " . esc_html( $result['title'] ) . " (Categoria: " . esc_html( $result['category'] ) . ")<br>";
                    $message .= "<a href='" . esc_url( $edit_link ) . "' class='button button-primary' style='margin-top:8px;'>Editar Rascunho</a>";
                    $message_type = 'success';
                } else {
                    $message = "Erro ao gerar: " . esc_html( $result['message'] );
                    $message_type = 'error';
                }
            }
        }

        // Ações de Regeração
        if ( isset( $_POST['oscarlima_regenerate_action'] ) && check_admin_referer( 'oscarlima_regenerate_nonce', 'oscarlima_nonce' ) ) {
            if ( empty( self::get_api_key() ) ) {
                $message = "Configure a chave API na aba Geral antes de regerar.";
                $message_type = 'error';
            } else {
                if ( function_exists( 'set_time_limit' ) ) {
                    @set_time_limit( 180 );
                }

                $post_id_to_regen = intval( $_POST['post_id'] );
                $result = self::regenerate_draft_article( $post_id_to_regen );

                if ( $result['success'] ) {
                    $edit_link = get_edit_post_link( $result['post_id'] );
                    $message = "<strong>Artigo regerado com sucesso!</strong><br>";
                    $message .= "Título Novo: " . esc_html( $result['title'] ) . " (Categoria: " . esc_html( $result['category'] ) . ")<br>";
                    $message .= "<a href='" . esc_url( $edit_link ) . "' class='button button-primary' style='margin-top:8px;'>Editar Rascunho</a>";
                    $message_type = 'success';
                } else {
                    $message = "Erro ao regerar rascunho: " . esc_html( $result['message'] );
                    $message_type = 'error';
                }
            }
        }

        // Ações de Importação do YouTube
        if ( isset( $_POST['oscarlima_youtube_import_action'] ) && check_admin_referer( 'oscarlima_youtube_import_nonce', 'oscarlima_nonce' ) ) {
            if ( empty( self::get_api_key() ) ) {
                $message = "Chave API OpenAI não configurada na aba Geral.";
                $message_type = 'error';
            } else {
                $json_input = isset( $_POST['youtube_json'] ) ? trim( $_POST['youtube_json'] ) : '';
                $cat_id = isset( $_POST['youtube_category'] ) ? intval( $_POST['youtube_category'] ) : 0;
                
                $data = json_decode( stripslashes( $json_input ), true );
                if ( ! $data || ! isset( $data['videoId'] ) || ! isset( $data['transcript'] ) ) {
                    $message = "Formato de JSON inválido. Certifique-se de copiar os dados corretamente do extrator.";
                    $message_type = 'error';
                } else {
                    if ( function_exists( 'set_time_limit' ) ) {
                        @set_time_limit( 180 );
                    }
                    $result = self::generate_article_from_youtube( $data, $cat_id, 'draft' );
                    if ( $result['success'] ) {
                        $edit_link = get_edit_post_link( $result['post_id'] );
                        $message = "<strong>Artigo gerado a partir do YouTube com sucesso!</strong><br>";
                        $message .= "Título: " . esc_html( $result['title'] ) . "<br>";
                        $message .= "<a href='" . esc_url( $edit_link ) . "' class='button button-primary' style='margin-top:8px;'>Editar Rascunho</a>";
                        $message_type = 'success';
                    } else {
                        $message = "Erro ao gerar: " . esc_html( $result['message'] );
                        $message_type = 'error';
                    }
                }
            }
        }

        // Ações de Importação de Projeto
        if ( isset( $_POST['oscarlima_project_import_action'] ) && check_admin_referer( 'oscarlima_project_import_nonce', 'oscarlima_nonce' ) ) {
            if ( empty( self::get_api_key() ) ) {
                $message = "Chave API OpenAI não configurada na aba Geral.";
                $message_type = 'error';
            } else {
                $project_name = isset( $_POST['project_name'] ) ? sanitize_text_field( $_POST['project_name'] ) : '';
                $project_summary = isset( $_POST['project_summary'] ) ? sanitize_textarea_field( $_POST['project_summary'] ) : '';
                $project_prompt = isset( $_POST['project_prompt'] ) ? sanitize_textarea_field( $_POST['project_prompt'] ) : '';
                $project_impl_plan = isset( $_POST['project_impl_plan'] ) ? sanitize_textarea_field( $_POST['project_impl_plan'] ) : '';
                $cat_id = isset( $_POST['project_category'] ) ? intval( $_POST['project_category'] ) : 0;
                
                if ( empty( $project_name ) || empty( $project_summary ) || empty( $project_prompt ) || empty( $project_impl_plan ) ) {
                    $message = "O título do projeto, o resumo, o prompt e o plano de implementação são obrigatórios.";
                    $message_type = 'error';
                } else {
                    if ( function_exists( 'set_time_limit' ) ) {
                        @set_time_limit( 180 );
                    }
                    $result = self::generate_article_from_project( $project_name, $project_summary, $project_prompt, $project_impl_plan, $cat_id, 'draft' );
                    if ( $result['success'] ) {
                        $edit_link = get_edit_post_link( $result['post_id'] );
                        $message = "<strong>Artigo de projeto gerado com sucesso!</strong><br>";
                        $message .= "Título: " . esc_html( $result['title'] ) . "<br>";
                        $message .= "<a href='" . esc_url( $edit_link ) . "' class='button button-primary' style='margin-top:8px;'>Editar Rascunho</a>";
                        $message_type = 'success';
                    } else {
                        $message = "Erro ao gerar: " . esc_html( $result['message'] );
                        $message_type = 'error';
                    }
                }
            }
        }

        // Ações de Regeração de Imagem
        if ( isset( $_POST['oscarlima_regenerate_image_action'] ) && check_admin_referer( 'oscarlima_regenerate_image_nonce', 'oscarlima_nonce' ) ) {
            $post_id_to_regen = intval( $_POST['post_id'] );
            if ( function_exists( 'set_time_limit' ) ) {
                @set_time_limit( 120 );
            }
            $result = self::regenerate_post_images( $post_id_to_regen );
            if ( $result['success'] ) {
                $message = "<strong>Imagens do artigo atualizadas com sucesso!</strong>";
                $message_type = 'success';
            } else {
                $message = "Erro ao atualizar imagens: " . esc_html( $result['message'] );
                $message_type = 'error';
            }
        }
        ?>
        <style>
            .oscarlima-card {
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 8px;
                padding: 20px;
                margin-top: 20px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
                box-sizing: border-box;
                width: 100% !important;
                max-width: 100% !important;
            }
        </style>
        <div class="wrap">
            <h1 style="margin-bottom: 15px;">Gerador de Artigos Automatizado AI 🚀</h1>

            <?php if ( ! empty( $message ) ) : ?>
                <div class="notice notice-<?php echo esc_attr( $message_type ); ?> is-dismissible" style="padding: 12px; margin-bottom: 20px;">
                     <p style="font-size: 14px; margin: 0;"><?php echo $message; ?></p>
                </div>
            <?php endif; ?>

            <!-- Navegação de Abas Nativas do WordPress -->
            <nav class="nav-tab-wrapper" style="margin-bottom: 20px;">
                <a href="?page=oscarlima-generator&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">Configurações Gerais</a>
                <a href="?page=oscarlima-generator&tab=seo" class="nav-tab <?php echo $active_tab === 'seo' ? 'nav-tab-active' : ''; ?>">Parâmetros de SEO</a>
                <a href="?page=oscarlima-generator&tab=toc" class="nav-tab <?php echo $active_tab === 'toc' ? 'nav-tab-active' : ''; ?>">Índice (TOC)</a>
                <a href="?page=oscarlima-generator&tab=generation" class="nav-tab <?php echo $active_tab === 'generation' ? 'nav-tab-active' : ''; ?>">Geração & Rascunhos</a>
                <a href="?page=oscarlima-generator&tab=youtube-import" class="nav-tab <?php echo $active_tab === 'youtube-import' ? 'nav-tab-active' : ''; ?>">Importar YouTube</a>
                <a href="?page=oscarlima-generator&tab=project-import" class="nav-tab <?php echo $active_tab === 'project-import' ? 'nav-tab-active' : ''; ?>">Gerar de Projeto</a>
            </nav>

            <div class="tab-content">
                
                <!-- TAB 1: Configurações Gerais -->
                <?php if ( $active_tab === 'general' ) : ?>
                    <div class="oscarlima-card" style="max-width: 600px; padding: 20px; border-radius: 8px;">
                        <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">Configurações Gerais</h2>
                        <form method="post">
                            <?php wp_nonce_field( 'oscarlima_save_settings_nonce', 'oscarlima_nonce' ); ?>
                            
                            <table class="form-table" role="presentation">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="openai_api_key">Chave API OpenAI:</label></th>
                                        <td>
                                            <input type="password" id="openai_api_key" name="openai_api_key" value="<?php echo esc_attr( self::get_api_key() ); ?>" class="regular-text" placeholder="sk-proj-..." required />
                                            <p class="description">Salva de forma segura na tabela <code>wp_options</code>.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="openai_model">Modelo GPT a Utilizar:</label></th>
                                        <td>
                                            <?php $selected_model = get_option( self::$option_model, 'gpt-4o-mini' ); ?>
                                            <select id="openai_model" name="openai_model">
                                                <option value="gpt-4o-mini" <?php selected( $selected_model, 'gpt-4o-mini' ); ?>>gpt-4o-mini (Mais rápido e econômico)</option>
                                                <option value="gpt-4o" <?php selected( $selected_model, 'gpt-4o' ); ?>>gpt-4o (Máxima precisão e complexidade)</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="image_provider">Provedor de Imagem Destacada:</label></th>
                                        <td>
                                            <?php $selected_provider = get_option( self::$option_image_provider, 'unsplash' ); ?>
                                            <select id="image_provider" name="image_provider">
                                                <option value="unsplash" <?php selected( $selected_provider, 'unsplash' ); ?>>Unsplash (Lista Abstrata Padrão — Sem Chave)</option>
                                                <option value="pexels" <?php selected( $selected_provider, 'pexels' ); ?>>Pexels API (Dinâmica por Palavra-Chave Foco)</option>
                                                <option value="pixabay" <?php selected( $selected_provider, 'pixabay' ); ?>>Pixabay API (Dinâmica por Palavra-Chave Foco)</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="pexels_api_key">Chave API Pexels:</label></th>
                                        <td>
                                            <input type="password" id="pexels_api_key" name="pexels_api_key" value="<?php echo esc_attr( get_option( self::$option_pexels_key, '' ) ); ?>" class="regular-text" placeholder="Chave da API do Pexels" />
                                            <p class="description">Necessária somente se Pexels for selecionado.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="pixabay_api_key">Chave API Pixabay:</label></th>
                                        <td>
                                            <input type="password" id="pixabay_api_key" name="pixabay_api_key" value="<?php echo esc_attr( get_option( self::$option_pixabay_key, '' ) ); ?>" class="regular-text" placeholder="Chave da API do Pixabay" />
                                            <p class="description">Necessária somente se Pixabay for selecionado.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Execução Diária Automatizada:</th>
                                        <td>
                                            <label for="cron_active">
                                                <input type="checkbox" id="cron_active" name="cron_active" value="1" <?php checked( get_option( self::$option_cron_active, '1' ), '1' ); ?> />
                                                Ativar WP-Cron diário para buscar notícias e postar rascunhos inéditos automaticamente
                                            </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="default_post_status">Status do Post Gerado pelo Cron:</label></th>
                                        <td>
                                            <?php $selected_status = get_option( self::$option_default_status, 'draft' ); ?>
                                            <select id="default_post_status" name="default_post_status">
                                                <option value="draft" <?php selected( $selected_status, 'draft' ); ?>>Rascunho (Draft)</option>
                                                <option value="publish" <?php selected( $selected_status, 'publish' ); ?>>Publicado (Publish)</option>
                                            </select>
                                            <p class="description">Define se os posts gerados automaticamente pelo Cron serão criados diretamente como publicados ou salvos como rascunhos.</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <p class="submit">
                                <button type="submit" name="oscarlima_save_settings" class="button button-primary">Salvar Geral</button>
                            </p>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- TAB 2: Configurações de SEO -->
                <?php if ( $active_tab === 'seo' ) : ?>
                    <div class="oscarlima-card" style="max-width: 600px; padding: 20px; border-radius: 8px;">
                        <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">Parâmetros de SEO Nativo</h2>
                        <form method="post">
                            <?php wp_nonce_field( 'oscarlima_save_settings_nonce', 'oscarlima_nonce' ); ?>
                            
                            <table class="form-table" role="presentation">
                                <tbody>
                                    <tr>
                                        <th scope="row">Tamanho de Título SEO (Caracteres):</th>
                                        <td>
                                            <input type="number" name="seo_title_min" value="<?php echo esc_attr( get_option( self::$option_title_min, 35 ) ); ?>" style="width: 60px;" min="10" /> a 
                                            <input type="number" name="seo_title_max" value="<?php echo esc_attr( get_option( self::$option_title_max, 45 ) ); ?>" style="width: 60px;" min="10" />
                                            <p class="description">Valores recomendados: 35 a 45 (deixa espaço para o título do site sem ultrapassar 60 caracteres no Google).</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Tamanho de Meta Descrição (Caracteres):</th>
                                        <td>
                                            <input type="number" name="seo_desc_min" value="<?php echo esc_attr( get_option( self::$option_desc_min, 120 ) ); ?>" style="width: 60px;" min="50" /> a 
                                            <input type="number" name="seo_desc_max" value="<?php echo esc_attr( get_option( self::$option_desc_max, 140 ) ); ?>" style="width: 60px;" min="50" />
                                            <p class="description">Meta descrição salva no campo nativo <code>post_excerpt</code>. Recomendado: 120 a 140.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Nuvem de Tags (Tag Cloud):</th>
                                        <td>
                                            <label for="auto_tags">
                                                <input type="checkbox" id="auto_tags" name="auto_tags" value="1" <?php checked( get_option( self::$option_auto_tags, '1' ), '1' ); ?> />
                                                Ativar criação e vinculação automática de tags identificadas pela IA para alimentar a Tag Cloud nativa do blog
                                            </label>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <p class="submit">
                                <button type="submit" name="oscarlima_save_settings" class="button button-primary">Salvar Parâmetros SEO</button>
                            </p>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- TAB 3: Índice (TOC) -->
                <?php if ( $active_tab === 'toc' ) : ?>
                    <div class="oscarlima-card" style="max-width: 600px; padding: 20px; border-radius: 8px;">
                        <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">Configurações de Índice (Table of Contents)</h2>
                        <form method="post">
                            <?php wp_nonce_field( 'oscarlima_save_settings_nonce', 'oscarlima_nonce' ); ?>
                            
                            <table class="form-table" role="presentation">
                                <tbody>
                                    <tr>
                                        <th scope="row">Ativar Índice de Conteúdo:</th>
                                        <td>
                                            <label for="toc_active">
                                                <input type="checkbox" id="toc_active" name="toc_active" value="1" <?php checked( get_option( self::$option_toc_active, '1' ), '1' ); ?> />
                                                Gerar dinamicamente e exibir a caixa de índice neon/glassmorphism no topo dos posts (somente posts com 2 ou mais títulos H2)
                                            </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="toc_title">Título da Caixa do Índice:</label></th>
                                        <td>
                                            <input type="text" id="toc_title" name="toc_title" value="<?php echo esc_attr( get_option( self::$option_toc_title, 'Índice de Conteúdo' ) ); ?>" class="regular-text" required />
                                            <p class="description">Exemplo: "Índice de Conteúdo", "Neste Artigo", "Tópicos Abordados".</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <p class="submit">
                                <button type="submit" name="oscarlima_save_settings" class="button button-primary">Salvar Índice</button>
                            </p>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- TAB 4: Geração & Rascunhos -->
                <?php if ( $active_tab === 'generation' ) : ?>
                    <div style="display: flex; flex-direction: column; gap: 24px; max-width: 100%;">
                        
                        <!-- Coluna Geração Manual -->
                        <div style="width: 100%;">
                            <div class="oscarlima-card" style="padding: 24px; border-radius: 8px; background:#fff; margin-bottom: 0; display: flex; gap: 30px; flex-wrap: wrap; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                <!-- Lado Esquerdo: Ação Geral -->
                                <div style="flex: 1 1 280px; min-width: 250px; display: flex; flex-direction: column; justify-content: space-between; gap: 15px;">
                                    <div>
                                        <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px; font-size: 18px; font-weight: 600; color: #1d2327;">Geração Manual RSS</h3>
                                        <p style="color: #646970; font-size: 13px; line-height: 1.5; margin-top: 10px;">Gera um novo artigo como Rascunho buscando a última notícia inédita das fontes RSS configuradas.</p>
                                    </div>
                                    <form method="post" style="margin: 0;">
                                        <?php wp_nonce_field( 'oscarlima_generate_nonce', 'oscarlima_nonce' ); ?>
                                        <input type="hidden" name="oscarlima_generate_action" value="1">
                                        <input type="hidden" name="oscarlima_category" value="auto">
                                        <button type="submit" class="button button-hero button-primary" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; height: 50px; font-size: 14px; font-weight: 600; border-radius: 6px; box-shadow: 0 2px 4px rgba(34,113,177,0.25);">
                                            <span class="dashicons dashicons-update" style="margin-top: 2px;"></span>
                                            Gerar Próximo Inédito Geral
                                        </button>
                                    </form>
                                </div>

                                <!-- Lado Direito: Grid de Categorias -->
                                <div style="flex: 2 2 480px; min-width: 320px; border-top: 1px solid #eee; padding-top: 20px; border-left: none; padding-left: 0;">
                                    <h4 style="margin-top:0; margin-bottom: 15px; font-weight: 600; font-size: 14px; color: #1d2327;">Forçar Geração por Categoria Estratégica:</h4>
                                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 12px;">
                                        <?php 
                                        $category_icons = array(
                                            'Inteligência Artificial'    => 'cpu',
                                            'Clima e Energia Limpa'      => 'lightbulb',
                                            'Saúde e Biotecnologia'      => 'heart',
                                            'Robótica e Automação'       => 'admin-tools',
                                            'Exploração Espacial'        => 'cloud',
                                            'Economia Digital e Fintech' => 'chart-area',
                                            'Segurança e Privacidade'    => 'shield'
                                        );
                                        foreach ( self::$feed_config as $category => $feeds ) : 
                                            $icon = isset($category_icons[$category]) ? $category_icons[$category] : 'admin-post';
                                        ?>
                                            <form method="post" style="margin:0;">
                                                <?php wp_nonce_field( 'oscarlima_generate_nonce', 'oscarlima_nonce' ); ?>
                                                <input type="hidden" name="oscarlima_generate_action" value="1">
                                                <input type="hidden" name="oscarlima_category" value="<?php echo esc_attr( $category ); ?>">
                                                <button type="submit" class="button" style="width: 100%; height: 85px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; border: 1px solid #c3c4c7; border-radius: 8px; background: #f6f7f7; padding: 10px; cursor: pointer; transition: all 0.2s ease-in-out; box-sizing: border-box;" onmouseover="this.style.borderColor='#2271b1'; this.style.background='#f0f6fc'; this.style.transform='translateY(-2px)';" onmouseout="this.style.borderColor='#c3c4c7'; this.style.background='#f6f7f7'; this.style.transform='none';">
                                                    <span class="dashicons dashicons-<?php echo $icon; ?>" style="font-size: 20px; width: 20px; height: 20px; color: #2271b1; margin: 0;"></span>
                                                    <span style="font-size: 11px; font-weight: 600; color: #2c3338; text-align: center; line-height: 1.25; white-space: normal;"><?php echo esc_html( $category ); ?></span>
                                                </button>
                                            </form>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Adiciona bordas e espaçamentos responsivos via CSS inline simples -->
                            <style>
                                @media (min-width: 782px) {
                                    div.oscarlima-card div:nth-child(2) {
                                        border-left: 1px solid #eee !important;
                                        padding-left: 30px !important;
                                        border-top: none !important;
                                        padding-top: 0 !important;
                                    }
                                }
                            </style>
                        </div>

                        <!-- Coluna Lista de Artigos -->
                        <div style="width: 100%;">
                            <div class="oscarlima-card" style="padding: 20px; border-radius: 8px; background:#fff;">
                                <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">Artigos Disponíveis para Edição / Regeração</h3>
                                <p>Todos os rascunhos e artigos publicados. Você pode regerá-los via IA para otimizar.</p>

                                <?php
                                $posts_list = get_posts( array(
                                    'post_type'   => 'post',
                                    'post_status' => array( 'draft', 'publish' ),
                                    'numberposts' => -1,
                                    'orderby'     => 'date',
                                    'order'       => 'DESC'
                                ) );

                                if ( empty( $posts_list ) ) {
                                    echo "<p style='color:#777; font-style:italic; margin-top:15px;'>Nenhum artigo encontrado.</p>";
                                } else {
                                    ?>
                                    <table class="wp-list-table widefat fixed striped table-view-list" style="border:0; box-shadow:none;">
                                        <thead>
                                            <tr>
                                                <th style="font-weight:600; padding:8px; width:45%;">Título</th>
                                                <th style="font-weight:600; padding:8px; width:20%;">Palavra-Chave Foco</th>
                                                <th style="font-weight:600; padding:8px; width:15%;">Status</th>
                                                <th style="font-weight:600; padding:8px; text-align:right;">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ( $posts_list as $d ) : ?>
                                                <?php $kw = get_post_meta( $d->ID, '_oscarlima_focus_keyword', true ); ?>
                                                <?php $status_label = ($d->post_status === 'publish') ? '<span style="color: #2e7d32; font-weight: 600;">Publicado</span>' : '<span style="color: #666; font-style: italic;">Rascunho</span>'; ?>
                                                <tr>
                                                    <td style="padding:10px 8px; font-weight:500; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                                        <?php echo esc_html( $d->post_title ); ?>
                                                    </td>
                                                    <td style="padding:10px 8px; font-style:italic; color:#666;">
                                                        <?php echo $kw ? esc_html( $kw ) : 'Não definida'; ?>
                                                    </td>
                                                    <td style="padding:10px 8px; font-size: 13px;">
                                                        <?php echo $status_label; ?>
                                                    </td>
                                                    <td style="padding:6px 8px; text-align:right; display:flex; gap:6px; justify-content:flex-end; align-items:center;">
                                                        <a href="<?php echo esc_url( get_edit_post_link( $d->ID ) ); ?>" class="button button-small">Editar</a>
                                                        <form method="post" style="margin:0;" onsubmit="return confirm('Regerar reescreverá o artigo e recriará tags e palavras-chave. Confirmar?');">
                                                            <?php wp_nonce_field( 'oscarlima_regenerate_nonce', 'oscarlima_nonce' ); ?>
                                                            <input type="hidden" name="oscarlima_regenerate_action" value="1">
                                                            <input type="hidden" name="post_id" value="<?php echo esc_attr( $d->ID ); ?>">
                                                            <button type="submit" class="button button-small" style="color: #2271b1; border-color: #2271b1;">Regerar</button>
                                                        </form>
                                                        <form method="post" style="margin:0;" onsubmit="return confirm('Isso mudará a imagem destacada e a imagem interna do artigo com base no provedor de imagem configurado. Confirmar?');">
                                                            <?php wp_nonce_field( 'oscarlima_regenerate_image_nonce', 'oscarlima_nonce' ); ?>
                                                            <input type="hidden" name="oscarlima_regenerate_image_action" value="1">
                                                            <input type="hidden" name="post_id" value="<?php echo esc_attr( $d->ID ); ?>">
                                                            <button type="submit" class="button button-small" style="color: #a855f7; border-color: #a855f7;">Regerar Imagens</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php
                                }
                                ?>
                            </div>
                        </div>

                    </div>
                <?php endif; ?>

                <!-- TAB 5: Importar YouTube -->
                <?php if ( $active_tab === 'youtube-import' ) : ?>
                    <div class="oscarlima-card" style="max-width: 800px; padding: 20px; border-radius: 8px;">
                        <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">Importar Dados do YouTube</h2>
                        <p class="description" style="font-size: 14px; margin-bottom: 20px;">
                            Utilize o extrator local rodando em <strong><a href="http://localhost:3000" target="_blank">http://localhost:3000</a></strong> para buscar vídeos e extrair a transcrição. Copie os dados JSON gerados e cole-os abaixo para gerar o artigo otimizado.
                        </p>
                        
                        <form method="post">
                            <?php wp_nonce_field( 'oscarlima_youtube_import_nonce', 'oscarlima_nonce' ); ?>
                            <input type="hidden" name="oscarlima_youtube_import_action" value="1">
                            
                            <table class="form-table" role="presentation">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="youtube_category">Categoria do Artigo:</label></th>
                                        <td>
                                            <select id="youtube_category" name="youtube_category">
                                                <option value="0">-- Selecione a Categoria --</option>
                                                <?php
                                                $categories = get_categories( array( 'hide_empty' => 0 ) );
                                                foreach ( $categories as $cat ) {
                                                    echo '<option value="' . esc_attr( $cat->term_id ) . '">' . esc_html( $cat->name ) . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="youtube_json">JSON de Dados do Extrator:</label></th>
                                        <td>
                                            <textarea id="youtube_json" name="youtube_json" rows="12" class="large-text code" style="font-family:monospace; font-size:12px;" placeholder='Cole aqui o JSON gerado no localhost:3000...' required></textarea>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <p class="submit">
                                <button type="submit" class="button button-primary button-hero">Gerar Artigo Otimizado</button>
                            </p>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- TAB 6: Gerar de Projeto -->
                <?php if ( $active_tab === 'project-import' ) : ?>
                    <div class="oscarlima-card" style="max-width: 800px; padding: 20px; border-radius: 8px;">
                        <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">Gerar Artigo a partir de Resumo do Projeto (MVP / Gemini)</h2>
                        <p class="description" style="font-size: 14px; margin-bottom: 20px;">
                            Insira os detalhes do projeto/MVP desenvolvido e o relato de experiência com o Gemini para criar um artigo técnico rico, didático e de alto valor de SEO.
                        </p>
                        
                        <form method="post">
                            <?php wp_nonce_field( 'oscarlima_project_import_nonce', 'oscarlima_nonce' ); ?>
                            <input type="hidden" name="oscarlima_project_import_action" value="1">
                            
                            <table class="form-table" role="presentation">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="project_name">Nome do Projeto / Título Base:</label></th>
                                        <td>
                                            <input type="text" id="project_name" name="project_name" class="regular-text" style="width: 100%;" placeholder="Ex: Gerador de Posts para Instagram com IA" required />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="project_category">Categoria do Artigo:</label></th>
                                        <td>
                                            <select id="project_category" name="project_category">
                                                <option value="0">-- Selecione a Categoria --</option>
                                                <?php
                                                $categories = get_categories( array( 'hide_empty' => 0 ) );
                                                foreach ( $categories as $cat ) {
                                                    echo '<option value="' . esc_attr( $cat->term_id ) . '">' . esc_html( $cat->name ) . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="project_summary">Resumo do Projeto & Relato com Gemini:</label></th>
                                        <td>
                                            <textarea id="project_summary" name="project_summary" rows="12" class="large-text" placeholder="Descreva os objetivos do projeto, como foi a arquitetura desenvolvida, os desafios encontrados e como a experiência com o Gemini (e Inteligência Artificial) ajudou no desenvolvimento do MVP..." required></textarea>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="project_prompt">Prompt Utilizado para o MVP:</label></th>
                                        <td>
                                            <textarea id="project_prompt" name="project_prompt" rows="8" class="large-text" placeholder="Cole aqui o prompt original que você enviou para a IA para iniciar a construção do MVP..." required></textarea>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="project_impl_plan">Plano de Implementação Gerado:</label></th>
                                        <td>
                                            <textarea id="project_impl_plan" name="project_impl_plan" rows="8" class="large-text" placeholder="Cole aqui o plano de implementação ou instruções que a IA te deu para programar o MVP..." required></textarea>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <p class="submit">
                                <button type="submit" class="button button-primary button-hero">Gerar Artigo de Projeto</button>
                            </p>
                        </form>
                    </div>
                <?php endif; ?>

            </div>
        </div>
        <?php
    }

    // =========================================================================
    // GERAÇÃO E REGERAÇÃO DE ARTIGOS
    // =========================================================================

    /**
     * Executa a rotina automática diária
     */
    public static function generate_daily_article() {
        if ( empty( self::get_api_key() ) ) {
            error_log( 'OscarLima Cron: Chave API OpenAI não configurada. Abortando geração automática.' );
            return false;
        }
        $status = get_option( self::$option_default_status, 'draft' );
        $result = self::generate_custom_article( null, $status );
        return isset( $result['post_id'] ) ? $result['post_id'] : false;
    }

    /**
     * Gera um artigo novo a partir de um tópico inédito dos feeds RSS
     */
    public static function generate_custom_article( $category = null, $status = 'draft' ) {
        $topic = $category
            ? self::find_next_inedit_topic_for_category( $category )
            : self::find_next_inedit_topic();
        
        if ( ! $topic ) {
            error_log( 'OscarLima: Nenhum tópico inédito encontrado.' );
            return array( 'success' => false, 'message' => 'Nenhum tópico inédito encontrado nas fontes RSS.' );
        }

        $ai_content_raw = self::generate_content_via_ai( $topic );
        if ( ! $ai_content_raw ) {
            error_log( 'OscarLima: Falha ao gerar conteúdo via OpenAI.' );
            return array( 'success' => false, 'message' => 'Falha ao gerar o conteúdo via API OpenAI.' );
        }

        // Parser unificado
        $parsed = self::parse_ai_response( $ai_content_raw );

        if ( empty( $parsed['title'] ) || empty( $parsed['content'] ) ) {
            return array( 'success' => false, 'message' => 'Erro de parsing no título/conteúdo.' );
        }

        // Monta conteúdo final (FAQ + imagem inline)
        $final_content = self::assemble_final_content( $parsed['content'], $parsed['faq'], $parsed['title'] );

        $post_id = wp_insert_post( array(
            'post_title'   => $parsed['title'],
            'post_content' => $final_content,
            'post_excerpt' => $parsed['excerpt'],
            'post_status'  => $status,
            'post_author'  => 1,
            'post_type'    => 'post',
        ) );

        if ( ! $post_id || is_wp_error( $post_id ) ) {
            return array( 'success' => false, 'message' => 'Falha ao salvar a postagem.' );
        }

        // Metadados RSS para rastreabilidade e regeração futura
        update_post_meta( $post_id, '_oscarlima_rss_link', esc_url_raw( $topic['link'] ) );
        update_post_meta( $post_id, '_oscarlima_rss_title', sanitize_text_field( $topic['title'] ) );
        update_post_meta( $post_id, '_oscarlima_rss_desc', sanitize_text_field( $topic['description'] ) );

        // SEO nativo: FAQ schema, palavra-chave foco e tags
        self::save_faq_metadata( $post_id, $parsed['faq'] );
        self::save_seo_metadata( $post_id, $parsed['focus_keyword'], $parsed['tags'] );

        // Categoria do WordPress
        $cat_name = $topic['category'];
        $cat_id = get_cat_ID( $cat_name );
        if ( ! $cat_id ) {
            $term = wp_insert_term( $cat_name, 'category' );
            if ( ! is_wp_error( $term ) && isset( $term['term_id'] ) ) {
                $cat_id = $term['term_id'];
            }
        }
        if ( $cat_id ) {
            wp_set_post_categories( $post_id, array( $cat_id ) );
        }
        
        // Imagem destacada via DALL-E com fallback Unsplash
        self::sideload_featured_image( $post_id, $parsed['title'] );

        return array( 'success' => true, 'post_id' => $post_id, 'title' => $parsed['title'], 'category' => $cat_name );
    }

    /**
     * Regera um artigo existente preservando o ID do post
     */
    public static function regenerate_draft_article( $post_id ) {
        $rss_link  = get_post_meta( $post_id, '_oscarlima_rss_link', true );
        $rss_title = get_post_meta( $post_id, '_oscarlima_rss_title', true );
        $rss_desc  = get_post_meta( $post_id, '_oscarlima_rss_desc', true );
        $categories = wp_get_post_categories( $post_id, array( 'fields' => 'names' ) );
        $category = ! empty( $categories ) ? $categories[0] : 'Inteligência Artificial';

        if ( empty( $rss_title ) ) {
            $post = get_post( $post_id );
            $rss_title = $post ? $post->post_title : '';
        }

        // Se for um post sem RSS (criado manualmente)
        if ( empty( $rss_link ) ) {
            $rss_link = get_permalink( $post_id );
        }
        if ( empty( $rss_desc ) ) {
            $rss_desc = 'Artigo original criado no blog. Otimizar e expandir conteúdo com Inteligência Artificial.';
        }

        $topic = array(
            'title'       => $rss_title,
            'description' => $rss_desc,
            'link'        => $rss_link,
            'category'    => $category
        );

        $ai_content_raw = self::generate_content_via_ai( $topic );
        if ( ! $ai_content_raw ) {
            return array( 'success' => false, 'message' => 'Falha ao reescrever conteúdo.' );
        }

        // Parser unificado (mesmo método da geração)
        $parsed = self::parse_ai_response( $ai_content_raw );

        if ( empty( $parsed['title'] ) || empty( $parsed['content'] ) ) {
            return array( 'success' => false, 'message' => 'Erro de parsing na regeração.' );
        }

        // Monta conteúdo final (FAQ + imagem inline)
        $final_content = self::assemble_final_content( $parsed['content'], $parsed['faq'], $parsed['title'] );

        $updated_id = wp_update_post( array(
            'ID'           => $post_id,
            'post_title'   => $parsed['title'],
            'post_content' => $final_content,
            'post_excerpt' => $parsed['excerpt'],
        ) );

        if ( ! $updated_id || is_wp_error( $updated_id ) ) {
            return array( 'success' => false, 'message' => 'Erro ao atualizar o post no BD.' );
        }

        // SEO nativo: FAQ schema, palavra-chave foco e tags
        self::save_faq_metadata( $updated_id, $parsed['faq'] );
        self::save_seo_metadata( $updated_id, $parsed['focus_keyword'], $parsed['tags'] );

        // Imagem destacada via DALL-E com fallback Unsplash
        self::sideload_featured_image( $updated_id, $parsed['title'] );

        return array( 'success' => true, 'post_id' => $updated_id, 'title' => $parsed['title'], 'category' => $category );
    }

    // =========================================================================
    // BUSCA DE TÓPICOS NOS FEEDS RSS
    // =========================================================================

    private static function find_next_inedit_topic_for_category( $category ) {
        if ( ! isset( self::$feed_config[$category] ) ) {
            return null;
        }
        foreach ( self::$feed_config[$category] as $feed_url ) {
            $topics = self::fetch_topics_from_feed( $feed_url );
            if ( empty( $topics ) ) {
                continue;
            }
            foreach ( $topics as $topic ) {
                if ( ! self::does_post_exist( $topic['title'], $topic['link'] ) ) {
                    return array(
                        'title'       => $topic['title'],
                        'description' => $topic['description'],
                        'link'        => $topic['link'],
                        'category'    => $category
                    );
                }
            }
        }
        return null;
    }

    private static function find_next_inedit_topic() {
        foreach ( self::$feed_config as $category => $feeds ) {
            foreach ( $feeds as $feed_url ) {
                $topics = self::fetch_topics_from_feed( $feed_url );
                if ( empty( $topics ) ) {
                    continue;
                }
                foreach ( $topics as $topic ) {
                    if ( ! self::does_post_exist( $topic['title'], $topic['link'] ) ) {
                        return array(
                            'title'       => $topic['title'],
                            'description' => $topic['description'],
                            'link'        => $topic['link'],
                            'category'    => $category
                        );
                    }
                }
            }
        }
        return null;
    }

    private static function fetch_topics_from_feed( $feed_url ) {
        $response = wp_remote_get( $feed_url, array(
            'timeout'    => 15,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ) );
        if ( is_wp_error( $response ) ) {
            return array();
        }
        $body = wp_remote_retrieve_body( $response );
        if ( empty( $body ) ) {
            return array();
        }
        libxml_use_internal_errors( true );
        $xml = simplexml_load_string( $body );
        if ( ! $xml || ! isset( $xml->channel->item ) ) {
            return array();
        }
        $topics = array();
        foreach ( $xml->channel->item as $item ) {
            $topics[] = array(
                'title'       => (string) $item->title,
                'description' => (string) $item->description,
                'link'        => (string) $item->link
            );
        }
        return $topics;
    }

    private static function does_post_exist( $title, $link ) {
        $meta_query = new WP_Query( array(
            'post_type'      => 'post',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'meta_query'     => array(
                array(
                    'key'     => '_oscarlima_rss_link',
                    'value'   => esc_url_raw( $link ),
                    'compare' => '='
                )
            ),
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ) );
        if ( $meta_query->have_posts() ) {
            return true;
        }
        $title_query = new WP_Query( array(
            'post_type'      => 'post',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'title'          => wp_strip_all_tags( $title ),
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ) );
        if ( $title_query->have_posts() ) {
            return true;
        }
        return false;
    }

    // =========================================================================
    // IMAGEM DESTACADA (DALL-E + FALLBACK UNSPLASH)
    // =========================================================================

    public static function sideload_featured_image( $post_id, $post_title ) {
        $provider = get_option( self::$option_image_provider, 'unsplash' );
        $focus_keyword = get_post_meta( $post_id, '_oscarlima_focus_keyword', true );
        if ( empty( $focus_keyword ) ) {
            $focus_keyword = $post_title;
        }

        $selected_url = '';

        if ( $provider !== 'unsplash' ) {
            // Obtém a categoria do post para o fallback de busca
            $categories = wp_get_post_categories( $post_id, array( 'fields' => 'names' ) );
            $category = ! empty( $categories ) ? $categories[0] : 'Inteligência Artificial';
            
            // Gera termos de busca otimizados em inglês via IA
            $search_query = self::generate_image_search_query_via_ai( $post_title, $focus_keyword, $category );

            if ( $provider === 'pexels' ) {
                $selected_url = self::get_image_url_from_pexels( $search_query );
            } elseif ( $provider === 'pixabay' ) {
                $selected_url = self::get_image_url_from_pixabay( $search_query );
            }
        }

        // Fallback para lista Unsplash se o provedor for unsplash ou se a chamada da API falhar
        if ( empty( $selected_url ) ) {
            $selected_url = self::get_random_image_url( '1200' );
            $slug = sanitize_title( $post_title );
            $selected_url .= '&' . $slug . '.jpg';
        }

        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/media.php' );

        add_filter( 'https_ssl_verify', '__return_false' );
        add_filter( 'https_local_ssl_verify', '__return_false' );

        // Prefiltro para renomear o arquivo baixado para SEO
        $seo_filename = sanitize_title( $post_title ) . '-' . wp_generate_password( 4, false ) . '.jpg';
        $rename_filter = function( $file ) use ( $seo_filename ) {
            $file['name'] = $seo_filename;
            return $file;
        };
        add_filter( 'wp_handle_sideload_prefilter', $rename_filter );

        $attachment_id = media_sideload_image( $selected_url, $post_id, $post_title, 'id' );

        remove_filter( 'wp_handle_sideload_prefilter', $rename_filter );
        remove_filter( 'https_ssl_verify', '__return_false' );
        remove_filter( 'https_local_ssl_verify', '__return_false' );

        if ( ! is_wp_error( $attachment_id ) ) {
            // Otimiza metadados do anexo de imagem para SEO
            wp_update_post( array(
                'ID'           => $attachment_id,
                'post_title'   => $post_title,
                'post_excerpt' => $focus_keyword,
                'post_content' => $post_title
            ) );
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', $focus_keyword );

            set_post_thumbnail( $post_id, $attachment_id );
            return true;
        }
        return false;
    }

    private static function get_image_url_from_pexels( $keyword ) {
        $api_key = get_option( self::$option_pexels_key, '' );
        if ( empty( $api_key ) ) {
            return '';
        }

        // Consulta API do Pexels buscando as primeiras 5 fotos para obter variedade aleatória
        $url = 'https://api.pexels.com/v1/search?query=' . urlencode( $keyword ) . '&per_page=5';
        $response = wp_remote_get( $url, array(
            'timeout' => 15,
            'headers' => array(
                'Authorization' => $api_key
            )
        ) );

        if ( is_wp_error( $response ) ) {
            return '';
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! empty( $data['photos'] ) && is_array( $data['photos'] ) ) {
            $index = array_rand( $data['photos'] );
            return $data['photos'][$index]['src']['large2x'];
        }

        return '';
    }

    private static function get_image_url_from_pixabay( $keyword ) {
        $api_key = get_option( self::$option_pixabay_key, '' );
        if ( empty( $api_key ) ) {
            return '';
        }

        // Consulta API do Pixabay buscando as primeiras 5 fotos
        $url = 'https://pixabay.com/api/?key=' . urlencode( $api_key ) . '&q=' . urlencode( $keyword ) . '&image_type=photo&per_page=5';
        $response = wp_remote_get( $url, array(
            'timeout' => 15
        ) );

        if ( is_wp_error( $response ) ) {
            return '';
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! empty( $data['hits'] ) && is_array( $data['hits'] ) ) {
            $index = array_rand( $data['hits'] );
            return $data['hits'][$index]['largeImageURL'];
        }

        return '';
    }

    public static function generate_image_search_query_via_ai( $title, $focus_keyword, $category ) {
        $api_key = self::get_api_key();
        if ( empty( $api_key ) ) {
            return self::get_category_fallback_keyword( $category );
        }

        $prompt = "Com base no titulo do artigo e na palavra-chave foco fornecidos, sugira exatamente de 2 a 3 palavras-chave de busca em ingles para encontrar a melhor fotografia conceitual correspondente em bancos de imagens (como Pexels/Pixabay).
As palavras-chave devem ser termos simples em ingles, focados em objetos ou conceitos visuais fotografaveis, sem nomes de marcas ou termos obscuros.
Retorne APENAS os termos em ingles separados por virgula, sem explicacoes, sem introducao e sem pontuacao final.

Titulo: " . $title . "
Palavra-Chave Foco: " . $focus_keyword . "
Categoria: " . $category . "
Resposta esperada (exemplo): smartphone, hand, screen";

        $payload = array(
            'model'       => 'gpt-4o-mini',
            'messages'    => array(
                array( 'role' => 'system', 'content' => 'Voce e um assistente especializado em SEO que retorna apenas palavras-chave de busca de imagem em ingles separadas por virgulas.' ),
                array( 'role' => 'user', 'content' => $prompt )
            ),
            'temperature' => 0.5,
            'max_tokens'  => 50
        );

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
            'timeout' => 15,
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ),
            'body' => json_encode( $payload )
        ) );

        if ( is_wp_error( $response ) ) {
            return self::get_category_fallback_keyword( $category );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body );

        if ( isset( $data->choices[0]->message->content ) ) {
            $keywords = trim( $data->choices[0]->message->content );
            $keywords = str_replace( array( '"', "'", '.', '[', ']' ), '', $keywords );
            if ( ! empty( $keywords ) ) {
                return $keywords;
            }
        }

        return self::get_category_fallback_keyword( $category );
    }

    private static function get_category_fallback_keyword( $category ) {
        if ( isset( self::$category_search_fallbacks[$category] ) ) {
            return self::$category_search_fallbacks[$category];
        }
        return 'technology, modern';
    }

    public static function regenerate_post_images( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return array( 'success' => false, 'message' => 'Post não encontrado.' );
        }

        $focus_keyword = get_post_meta( $post_id, '_oscarlima_focus_keyword', true );
        if ( empty( $focus_keyword ) ) {
            $focus_keyword = $post->post_title;
        }

        $provider = get_option( self::$option_image_provider, 'unsplash' );
        $featured_url = '';
        $inline_url = '';

        if ( $provider !== 'unsplash' ) {
            $categories = wp_get_post_categories( $post_id, array( 'fields' => 'names' ) );
            $category = ! empty( $categories ) ? $categories[0] : 'Inteligência Artificial';
            $search_query = self::generate_image_search_query_via_ai( $post->post_title, $focus_keyword, $category );

            if ( $provider === 'pexels' ) {
                $api_key = get_option( self::$option_pexels_key, '' );
                if ( ! empty( $api_key ) ) {
                    $url = 'https://api.pexels.com/v1/search?query=' . urlencode( $search_query ) . '&per_page=5';
                    $response = wp_remote_get( $url, array(
                        'timeout' => 15,
                        'headers' => array( 'Authorization' => $api_key )
                    ) );
                    if ( ! is_wp_error( $response ) ) {
                        $body = wp_remote_retrieve_body( $response );
                        $data = json_decode( $body, true );
                        if ( ! empty( $data['photos'] ) && count( $data['photos'] ) >= 2 ) {
                            $featured_url = $data['photos'][0]['src']['large2x'];
                            $inline_url = $data['photos'][1]['src']['large2x'];
                        } elseif ( ! empty( $data['photos'] ) ) {
                            $featured_url = $data['photos'][0]['src']['large2x'];
                            $inline_url = $data['photos'][0]['src']['large2x'];
                        }
                    }
                }
            } elseif ( $provider === 'pixabay' ) {
                $api_key = get_option( self::$option_pixabay_key, '' );
                if ( ! empty( $api_key ) ) {
                    $url = 'https://pixabay.com/api/?key=' . urlencode( $api_key ) . '&q=' . urlencode( $search_query ) . '&image_type=photo&per_page=5';
                    $response = wp_remote_get( $url, array( 'timeout' => 15 ) );
                    if ( ! is_wp_error( $response ) ) {
                        $body = wp_remote_retrieve_body( $response );
                        $data = json_decode( $body, true );
                        if ( ! empty( $data['hits'] ) && count( $data['hits'] ) >= 2 ) {
                            $featured_url = $data['hits'][0]['largeImageURL'];
                            $inline_url = $data['hits'][1]['largeImageURL'];
                        } elseif ( ! empty( $data['hits'] ) ) {
                            $featured_url = $data['hits'][0]['largeImageURL'];
                            $inline_url = $data['hits'][0]['largeImageURL'];
                        }
                    }
                }
            }
        }

        // Fallbacks se nada foi recuperado pelas APIs
        if ( empty( $featured_url ) ) {
            $featured_url = self::get_random_image_url( '1200' );
        }
        if ( empty( $inline_url ) ) {
            $inline_url = self::get_random_image_url( '800' );
        }

        // Sideload da nova imagem destacada
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/media.php' );

        add_filter( 'https_ssl_verify', '__return_false' );
        add_filter( 'https_local_ssl_verify', '__return_false' );

        // Apaga anexo da imagem destacada anterior para não poluir a biblioteca
        $old_thumb_id = get_post_thumbnail_id( $post_id );
        if ( $old_thumb_id ) {
            wp_delete_attachment( $old_thumb_id, true );
        }

        // Prefiltro para renomear o arquivo baixado para SEO
        $seo_filename = sanitize_title( $post->post_title ) . '-' . wp_generate_password( 4, false ) . '.jpg';
        $rename_filter = function( $file ) use ( $seo_filename ) {
            $file['name'] = $seo_filename;
            return $file;
        };
        add_filter( 'wp_handle_sideload_prefilter', $rename_filter );

        $attachment_id = media_sideload_image( $featured_url, $post_id, $post->post_title, 'id' );

        remove_filter( 'wp_handle_sideload_prefilter', $rename_filter );

        if ( ! is_wp_error( $attachment_id ) ) {
            // Otimiza metadados do anexo de imagem para SEO
            wp_update_post( array(
                'ID'           => $attachment_id,
                'post_title'   => $post->post_title,
                'post_excerpt' => $focus_keyword,
                'post_content' => $post->post_title
            ) );
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', $focus_keyword );

            set_post_thumbnail( $post_id, $attachment_id );
        }

        // Substituição no conteúdo do post
        $content = $post->post_content;
        
        // Verifica se há alguma imagem inline já injetada
        if ( preg_match( '/<img[^>]+>/i', $content, $img_matches ) ) {
            $old_img_tag = $img_matches[0];
            $new_img_tag = "<img src='" . esc_url( $inline_url ) . "' alt='" . esc_attr( $focus_keyword ) . "' title='" . esc_attr( $post->post_title ) . "' loading='lazy' width='800' height='450' decoding='async' style='border-radius:12px; max-width:100%; height:auto; margin:25px 0; box-shadow:0 4px 15px rgba(0,0,0,0.15);' />";
            $content = str_replace( $old_img_tag, $new_img_tag, $content );
        } else {
            // Se não houver imagem, injeta uma nova e substitui a URL dela
            $content = self::inject_secondary_image( $content, $post->post_title );
            if ( preg_match( '/<img[^>]+>/i', $content, $img_matches ) ) {
                $old_img_tag = $img_matches[0];
                $new_img_tag = "<img src='" . esc_url( $inline_url ) . "' alt='" . esc_attr( $focus_keyword ) . "' title='" . esc_attr( $post->post_title ) . "' loading='lazy' width='800' height='450' decoding='async' style='border-radius:12px; max-width:100%; height:auto; margin:25px 0; box-shadow:0 4px 15px rgba(0,0,0,0.15);' />";
                $content = str_replace( $old_img_tag, $new_img_tag, $content );
            }
        }

        wp_update_post( array(
            'ID'           => $post_id,
            'post_content' => $content
        ) );

        remove_filter( 'https_ssl_verify', '__return_false' );
        remove_filter( 'https_local_ssl_verify', '__return_false' );

        return array( 'success' => true );
    }

    // =========================================================================
    // GERAÇÃO DE CONTEÚDO VIA OPENAI (PROMPT MASTER)
    // =========================================================================

    /**
     * Conecta à OpenAI e redige o conteúdo aplicando todas as diretrizes de SEO nativo
     */
    public static function generate_content_via_ai( $topic ) {
        // Mapeamento dinâmico de todas as fontes RSS para o nome amigável
        $source_map = array(
            'techcrunch.com'         => 'TechCrunch',
            'venturebeat.com'        => 'VentureBeat',
            'marktechpost.com'       => 'MarkTechPost',
            'technologyreview.com'   => 'MIT Technology Review',
            'arstechnica.com'        => 'Ars Technica',
            'wired.com'              => 'WIRED',
            'zdnet.com'              => 'ZDNET',
            'bleepingcomputer.com'   => 'BleepingComputer',
            'krebsonsecurity.com'    => 'Krebs on Security',
            'space.com'              => 'Space.com',
            'scientificamerican.com' => 'Scientific American',
            'spectrum.ieee.org'      => 'IEEE Spectrum',
            'endpts.com'             => 'Endpoints News',
            'fiercebiotech.com'      => 'Fierce Biotech',
            'research.google'        => 'Google Research'
        );
        $source_name = 'Fonte Original';
        foreach ( $source_map as $domain => $name ) {
            if ( ! empty( $topic['link'] ) && strpos( $topic['link'], $domain ) !== false ) {
                $source_name = $name;
                break;
            }
        }
        $is_external = ( ! empty( $topic['link'] ) && strpos( $topic['link'], home_url() ) === false && strpos( $topic['link'], 'localhost' ) === false );

        // Obtém o link e título do último post publicado de forma nativa
        $recent_posts = get_posts( array(
            'numberposts' => 1,
            'post_status' => 'publish'
        ) );
        $last_post_link = '';
        $last_post_title = '';
        if ( ! empty( $recent_posts ) ) {
            $last_post_link = esc_url( get_permalink( $recent_posts[0]->ID ) );
            $last_post_title = wp_strip_all_tags( $recent_posts[0]->post_title );
        }

        // Obtém os títulos dos últimos 3 posts para deduplicação semântica
        $last_3_posts = get_posts( array(
            'numberposts' => 3,
            'post_status' => 'any'
        ) );
        $recent_titles = array();
        foreach ( $last_3_posts as $lp ) {
            $recent_titles[] = $lp->post_title;
        }
        $recent_titles_str = implode( ' | ', $recent_titles );

        // Configurações personalizadas carregadas das opções salvas pelo usuário
        $title_min = intval( get_option( self::$option_title_min, 35 ) );
        $title_max = intval( get_option( self::$option_title_max, 45 ) );
        $desc_min = intval( get_option( self::$option_desc_min, 120 ) );
        $desc_max = intval( get_option( self::$option_desc_max, 140 ) );
        $ai_model = get_option( self::$option_model, 'gpt-4o-mini' );

        $prompt = "Você é um gerador de artigos de tecnologia e ciência avançado. Escreva um artigo técnico, didático e jornalístico extremamente abrangente, aprofundado e completo em Português do Brasil com NO MÍNIMO 2000 PALAVRAS sobre o seguinte tema:\n\n";
        $prompt .= "Título Original da Notícia (Inglês): " . $topic['title'] . "\n";
        $prompt .= "Resumo/Descrição Original: " . $topic['description'] . "\n";
        $prompt .= "Link da Fonte Original: " . $topic['link'] . "\n";
        $prompt .= "Subcategoria do Artigo: " . $topic['category'] . "\n\n";
        $prompt .= "Você DEVE responder utilizando exatamente as tags customizadas abaixo para delimitar o título, a palavra-chave foco, as palavras-chave secundárias, o resumo de SEO (meta descrição), o FAQ e o conteúdo:\n";
        
        $prompt .= "[TITULO]\n";
        $prompt .= "Crie um título em português do Brasil com alto apelo de clique (CTR) e sentimento forte. Siga as regras rígidas de SEO:\n";
        $prompt .= "1. Comprimento estrito de " . $title_min . " a " . $title_max . " caracteres (incluindo espaços). NUNCA ultrapasse " . $title_max . " caracteres de forma alguma (para que haja margem segura ao anexar o título do site)!\n";
        $prompt .= "2. A palavra-chave foco (focus keyword) gerada abaixo DEVE estar no início do título (preferencialmente nos primeiros 40 caracteres).\n";
        $prompt .= "3. Use números ou colchetes visuais quando for útil e contextual para aumentar o CTR (ex: '7 Formas', '[Guia 2026]', '[Exclusivo]').\n";
        $prompt .= "4. Quantidade de palavras: Use no máximo 6 a 8 palavras no total.\n";
        $prompt .= "5. Equilíbrio de Palavras (Use obrigatoriamente pelo menos uma de cada grupo abaixo de forma contextual):\n";
        $prompt .= "   - Palavras Comuns: como, por que, seu, sua, o, a, de, para, com.\n";
        $prompt .= "   - Palavras Incomuns: novo, mais, melhor, dicas, grátis, passo a passo, segredos.\n";
        $prompt .= "   - Palavras Emocionais: incrível, impressionante, maravilhoso, segredo, perigo, sucesso, fácil, rápido, chocante.\n";
        $prompt .= "   - Palavras Poderosas: revolução, definitivo, hoje, revelado, urgente, exclusivo, único, transformar.\n";
        $prompt .= "6. Evite repetições: Você está proibido de gerar títulos parecidos ou de usar termos muito próximos dos títulos dos últimos 3 posts que foram: [ " . $recent_titles_str . " ]. Faça algo totalmente inédito.\n";
        $prompt .= "[/TITULO]\n\n";

        $prompt .= "[PALAVRA_FOCO]\n";
        $prompt .= "Escreva aqui uma única palavra ou termo curto (máximo de 3 palavras) que será a Palavra-Chave Foco (focus keyword) do artigo para otimização nos buscadores (exemplo: 'Nova Siri' ou 'Segurança Digital').\n";
        $prompt .= "[/PALAVRA_FOCO]\n\n";

        $prompt .= "[PALAVRAS_CHAVE]\n";
        $prompt .= "Escreva de 5 a 8 palavras-chave separadas apenas por vírgulas para usarmos como Tags da nuvem de tags do WordPress.\n";
        $prompt .= "[/PALAVRAS_CHAVE]\n\n";

        $prompt .= "[RESUMO]\n";
        $prompt .= "Escreva aqui um resumo executivo do artigo em português que servirá como meta descrição para os buscadores. Siga as regras de SEO:\n";
        $prompt .= "1. Comprimento estrito de " . $desc_min . " a " . $desc_max . " caracteres (incluindo espaços). Não escreva menos que " . $desc_min . " e nem mais que " . $desc_max . " caracteres de forma alguma.\n";
        $prompt .= "2. Use voz ativa e resuma de forma direta o valor principal do artigo.\n";
        $prompt .= "[/RESUMO]\n\n";

        $prompt .= "[FAQ]\n";
        $prompt .= "Gere uma seção de Perguntas Frequentes (FAQ) com 3 a 5 perguntas e respostas curtas e objetivas, otimizadas para capturar featured snippets e responder à intenção de busca do usuário (People Also Ask). Utilize a seguinte formatação HTML exata:\n";
        $prompt .= "<h3>Pergunta...?</h3>\n";
        $prompt .= "<p>Resposta curta, direta e com voz ativa.</p>\n";
        $prompt .= "[/FAQ]\n\n";

        $prompt .= "[CONTEUDO]\n";
        $prompt .= "Escreva aqui o conteúdo completo do artigo em formato HTML semântico puro.\n";
        $prompt .= "[/CONTEUDO]\n\n";

        $prompt .= "Instruções Cruciais para o Tom e Conteúdo (HTML):\n";
        $prompt .= "1. O tom do artigo DEVE ser super didático, simples, amigável e empolgante, de forma que um jovem de 12 anos consiga ler e entender perfeitamente o assunto. Use analogias lúdicas e divertidas do dia a dia (videogames, celular, futebol, escola, etc.) para explicar termos complexos. Use vocabulário simples (nível 6ª série do ensino fundamental).\n";
        $prompt .= "2. O artigo DEVE conter no mínimo 2000 palavras em português do Brasil dentro da tag [CONTEUDO]. Desenvolva cada seção detalhadamente com múltiplos parágrafos longos, explicações passo a passo, dados ou estatísticas reais quando disponíveis, e discussões profundas. NÃO resuma o conteúdo.\n";
        $prompt .= "3. No HTML, NÃO use tags <html> ou <body>, e NÃO inclua títulos H1 no início do artigo. Use tags H2 para seções principais e H3 para subseções.\n";
        if ( $is_external ) {
            $prompt .= "4. Incorpore no corpo do texto (preferencialmente na Introdução) uma menção clara e um link de saída absoluto apontando para a fonte original da notícia. Exemplo: 'Ficamos sabendo de tudo isso por meio de uma matéria bem legal do <a href=\"" . esc_url( $topic['link'] ) . "\" target=\"_blank\" rel=\"noopener\">" . $source_name . "</a>...'\n";
        } else {
            $prompt .= "4. Este é um artigo de opinião ou tópico livre interno. Você está proibido de incluir link de saída ou referência a fontes externas na introdução. Escreva a introdução focando diretamente no tema do título.\n";
        }
        $prompt .= "5. Incorpore obrigatoriamente no corpo do texto pelo menos dois links internos absolutos:\n";
        $prompt .= "   - Um link dinâmico absoluto apontando para a nossa página inicial institucional: `<a href=\"" . esc_url( home_url('/') ) . "\">serviços de desenvolvimento do Oscar</a>`.\n";
        if ( ! empty( $last_post_link ) ) {
            $prompt .= "   - Um link dinâmico absoluto apontando de forma natural e contextual para o último post publicado: `<a href=\"" . $last_post_link . "\">" . $last_post_title . "</a>`.\n";
        }
        $prompt .= "6. Legibilidade e Flesch Reading Ease adaptado para PT-BR (CRÍTICO):\n";
        $prompt .= "   - Tamanho das Frases: Escreva frases curtas. Cada frase deve ter em média de 15 a 18 palavras. Evite frases excessivamente longas e complexas.\n";
        $prompt .= "   - Flesch Reading Ease: Use vocabulário do dia a dia simples. O objetivo é alcançar uma pontuação Flesch de 60-70 (Fácil Leitura em Português).\n";
        $prompt .= "   - Palavras de Transição: Pelo menos 30% a 35% das frases do texto devem conter termos de transição/conectivos (ex: portanto, além disso, contudo, consequentemente, por exemplo, então, assim, todavia, entretanto, mas, porém, desse modo, com isso, além do mais).\n";
        $prompt .= "   - Variedade: Não inicie três frases consecutivas com a mesma palavra.\n";
        $prompt .= "7. Distribuição de Subtítulos (Subheading distribution): Cada seção principal H2 com mais de 200 palavras deve ser subdividida por títulos menores `<h3>` contextuais para melhorar a leitura, garantindo que nenhum bloco de texto tenha mais de 250 palavras sem um subtítulo.\n";
        $prompt .= "8. Estruture o artigo em 6 seções principais (H2). Você está PROIBIDO de usar títulos genéricos (como Introdução, Fundamentos, Guia Prático, SEO/GEO, Limitações, Conclusão). Crie H2s criativos e contextuais específicos do tema real da notícia.\n";
        $prompt .= "9. Certifique-se de usar formatação limpa (listas ordenadas e não ordenadas, parágrafos bem espaçados).";

        $payload = array(
            'model'       => $ai_model,
            'messages'    => array(
                array( 'role' => 'system', 'content' => 'Você é um professor e divulgador científico jovem, divertido e empolgante. Seu objetivo é explicar tecnologias e ciências avançadas de forma simples, amigável e clara para um jovem de 12 anos entender perfeitamente, usando períodos curtos (15-18 palavras por frase), vocabulário simplificado e analogias lúdicas, sem perder a riqueza de detalhes e a extensão do texto de 2000 palavras. Você sempre delimita o título com [TITULO]...[/TITULO], a palavra de foco com [PALAVRA_FOCO]...[/PALAVRA_FOCO], as tags com [PALAVRAS_CHAVE]...[/PALAVRAS_CHAVE], o resumo com [RESUMO]...[/RESUMO], o FAQ com [FAQ]...[/FAQ] e o conteúdo com [CONTEUDO]...[/CONTEUDO].' ),
                array( 'role' => 'user', 'content' => $prompt )
            ),
            'temperature' => 0.7,
            'max_tokens'  => 8000
        );

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
            'timeout' => 180,
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . self::get_api_key()
            ),
            'body' => json_encode( $payload )
        ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body );

        if ( isset( $data->choices[0]->message->content ) ) {
            return $data->choices[0]->message->content;
        }

        return false;
    }

    // =========================================================================
    // IMPORTAÇÃO E GERAÇÃO VIA YOUTUBE
    // =========================================================================

    /**
     * Gera um artigo novo a partir do JSON/dados extraídos de um vídeo do YouTube
     */
    public static function generate_article_from_youtube( $data, $category_id, $status = 'draft' ) {
        $ai_content_raw = self::generate_youtube_content_via_ai( $data );
        if ( ! $ai_content_raw ) {
            error_log( 'OscarLima YouTube: Falha ao gerar conteúdo via OpenAI.' );
            return array( 'success' => false, 'message' => 'Falha ao gerar o conteúdo via API OpenAI.' );
        }

        // Parser unificado (reutiliza o parser de tags do RSS)
        $parsed = self::parse_ai_response( $ai_content_raw );

        if ( empty( $parsed['title'] ) || empty( $parsed['content'] ) ) {
            return array( 'success' => false, 'message' => 'Erro de parsing no título/conteúdo do artigo gerado.' );
        }

        // Prepara o iframe para embutir o player do YouTube no início do artigo
        $video_embed = "\n<p style='text-align:center;'><iframe width='560' height='315' src='https://www.youtube.com/embed/" . esc_attr( $data['videoId'] ) . "' title='YouTube video player' frameborder='0' allow='accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share' allowfullscreen style='border-radius:12px; max-width:100%; box-shadow:0 4px 15px rgba(0,0,0,0.15); margin-bottom:25px;'></iframe></p>\n";

        // Monta conteúdo final (FAQ + imagem inline + player no início)
        $final_content = $video_embed . self::assemble_final_content( $parsed['content'], $parsed['faq'], $parsed['title'] );

        $post_id = wp_insert_post( array(
            'post_title'   => $parsed['title'],
            'post_content' => $final_content,
            'post_excerpt' => $parsed['excerpt'],
            'post_status'  => $status,
            'post_author'  => 1,
            'post_type'    => 'post',
        ) );

        if ( ! $post_id || is_wp_error( $post_id ) ) {
            return array( 'success' => false, 'message' => 'Falha ao salvar a postagem.' );
        }

        // Metadados do YouTube para rastreabilidade
        update_post_meta( $post_id, '_oscarlima_rss_link', esc_url_raw( $data['url'] ) );
        update_post_meta( $post_id, '_oscarlima_rss_title', sanitize_text_field( $data['title'] ) );
        update_post_meta( $post_id, '_oscarlima_rss_desc', sanitize_text_field( $data['description'] ) );
        update_post_meta( $post_id, '_oscarlima_youtube_id', sanitize_text_field( $data['videoId'] ) );

        // SEO nativo: FAQ schema, palavra-chave foco e tags
        self::save_faq_metadata( $post_id, $parsed['faq'] );
        self::save_seo_metadata( $post_id, $parsed['focus_keyword'], $parsed['tags'] );

        // Associa à categoria selecionada
        if ( ! empty( $category_id ) ) {
            wp_set_post_categories( $post_id, array( intval( $category_id ) ) );
        }

        // Faz o sideload da thumbnail do vídeo como imagem destacada do post
        self::sideload_youtube_featured_image( $post_id, $data['thumbnail'], $parsed['title'] );

        return array( 'success' => true, 'post_id' => $post_id, 'title' => $parsed['title'] );
    }

    /**
     * Gera o conteúdo do artigo via OpenAI com base na transcrição do YouTube
     */
    public static function generate_youtube_content_via_ai( $data ) {
        // Obtém o link e título do último post publicado de forma nativa para links internos
        $recent_posts = get_posts( array(
            'numberposts' => 1,
            'post_status' => 'publish'
        ) );
        $last_post_link = '';
        $last_post_title = '';
        if ( ! empty( $recent_posts ) ) {
            $last_post_link = esc_url( get_permalink( $recent_posts[0]->ID ) );
            $last_post_title = wp_strip_all_tags( $recent_posts[0]->post_title );
        }

        // Obtém os títulos dos últimos 3 posts para deduplicação semântica no título
        $last_3_posts = get_posts( array(
            'numberposts' => 3,
            'post_status' => 'any'
        ) );
        $recent_titles = array();
        foreach ( $last_3_posts as $lp ) {
            $recent_titles[] = $lp->post_title;
        }
        $recent_titles_str = implode( ' | ', $recent_titles );

        // Configurações personalizadas carregadas das opções
        $title_min = intval( get_option( self::$option_title_min, 35 ) );
        $title_max = intval( get_option( self::$option_title_max, 45 ) );
        $desc_min = intval( get_option( self::$option_desc_min, 120 ) );
        $desc_max = intval( get_option( self::$option_desc_max, 140 ) );
        $ai_model = get_option( self::$option_model, 'gpt-4o-mini' );

        $prompt = "Você é um gerador de artigos de tecnologia e ciência avançado. Escreva um artigo técnico, didático e jornalístico extremamente abrangente, aprofundado e completo em Português do Brasil com NO MÍNIMO 2000 PALAVRAS a partir da transcrição/legenda de um vídeo do YouTube:\n\n";
        $prompt .= "Título Original do Vídeo: " . $data['title'] . "\n";
        $prompt .= "Canal Autor do Vídeo: " . $data['channel'] . "\n";
        $prompt .= "Link do Vídeo Original (Fonte): " . $data['url'] . "\n";
        $prompt .= "Transcrição/Legenda Completa do Vídeo:\n" . $data['transcript'] . "\n\n";
        
        $prompt .= "Você DEVE responder utilizando exatamente as tags customizadas abaixo para delimitar o título, a palavra-chave foco, as palavras-chave secundárias, o resumo de SEO (meta descrição), o FAQ e o conteúdo:\n";
        $prompt .= "[TITULO]\n";
        $prompt .= "Crie um título em português do Brasil com alto apelo de clique (CTR) e sentimento forte. Siga as regras rígidas de SEO:\n";
        $prompt .= "1. Comprimento estrito de " . $title_min . " a " . $title_max . " caracteres (incluindo espaços). NUNCA ultrapasse " . $title_max . " caracteres de forma alguma.\n";
        $prompt .= "2. A palavra-chave foco (focus keyword) gerada abaixo DEVE estar no início do título (preferencialmente nos primeiros 40 caracteres).\n";
        $prompt .= "3. Use números ou colchetes visuais quando for útil e contextual para aumentar o CTR (ex: '7 Formas', '[Guia 2026]', '[Exclusivo]').\n";
        $prompt .= "4. Quantidade de palavras: Use no máximo 6 a 8 palavras no total.\n";
        $prompt .= "5. Equilíbrio de Palavras (Use pelo menos uma de cada grupo: Comuns, Incomuns, Emocionais, Poderosas).\n";
        $prompt .= "6. Evite repetições: Você está proibido de gerar títulos parecidos com: [ " . $recent_titles_str . " ].\n";
        $prompt .= "[/TITULO]\n\n";

        $prompt .= "[PALAVRA_FOCO]\n";
        $prompt .= "Escreva aqui uma única palavra ou termo curto (máximo de 3 palavras) que será a Palavra-Chave Foco (focus keyword) do artigo.\n";
        $prompt .= "[/PALAVRA_FOCO]\n\n";

        $prompt .= "[PALAVRAS_CHAVE]\n";
        $prompt .= "Escreva de 5 a 8 palavras-chave separadas apenas por vírgulas para usarmos como Tags do post.\n";
        $prompt .= "[/PALAVRAS_CHAVE]\n\n";

        $prompt .= "[RESUMO]\n";
        $prompt .= "Escreva aqui um resumo executivo do artigo em português que servirá como meta descrição para os buscadores. Siga as regras de SEO:\n";
        $prompt .= "1. Comprimento estrito de " . $desc_min . " a " . $desc_max . " caracteres (incluindo espaços).\n";
        $prompt .= "2. Use voz ativa e resuma de forma direta o valor principal do artigo.\n";
        $prompt .= "[/RESUMO]\n\n";

        $prompt .= "[FAQ]\n";
        $prompt .= "Gere uma seção de Perguntas Frequentes (FAQ) com 3 a 5 perguntas e respostas curtas e objetivas. Utilize a seguinte formatação HTML exata:\n";
        $prompt .= "<h3>Pergunta...?</h3>\n";
        $prompt .= "<p>Resposta curta, direta e com voz ativa.</p>\n";
        $prompt .= "[/FAQ]\n\n";

        $prompt .= "[CONTEUDO]\n";
        $prompt .= "Escreva aqui o conteúdo completo do artigo em formato HTML semântico puro.\n";
        $prompt .= "[/CONTEUDO]\n\n";

        $prompt .= "Instruções Cruciais para o Tom e Conteúdo (HTML):\n";
        $prompt .= "1. O tom do artigo DEVE ser super didático, simples, amigável e empolgante, de forma que um jovem de 12 anos consiga ler e entender perfeitamente o assunto. Use analogias lúdicas e divertidas do dia a dia. Use vocabulário simples.\n";
        $prompt .= "2. O artigo DEVE conter no mínimo 2000 palavras em português do Brasil dentro da tag [CONTEUDO]. Desenvolva cada seção detalhadamente com múltiplos parágrafos longos, explicações passo a passo, dados ou estatísticas reais quando disponíveis, e discussões profundas. NÃO resuma o conteúdo.\n";
        $prompt .= "3. No HTML, NÃO use tags <html> ou <body>, e NÃO inclua títulos H1 no início do artigo. Use tags H2 para seções principais e H3 para subseções.\n";
        $prompt .= "4. Incorpore no corpo do texto (preferencialmente na Introdução) uma menção clara e um link de saída absoluto apontando para o vídeo original como fonte. Exemplo: 'Ficamos sabendo de tudo isso por meio de um vídeo muito interessante do canal <a href=\"" . esc_url( $data['url'] ) . "\" target=\"_blank\" rel=\"noopener\">" . esc_html( $data['channel'] ) . "</a> no YouTube...'\n";
        $prompt .= "5. Incorpore obrigatoriamente no corpo do texto pelo menos dois links internos absolutos:\n";
        $prompt .= "   - Um link dinâmico absoluto apontando para a nossa página inicial institucional: `<a href=\"" . esc_url( home_url('/') ) . "\">serviços de desenvolvimento do Oscar</a>`.\n";
        if ( ! empty( $last_post_link ) ) {
            $prompt .= "   - Um link dinâmico absoluto apontando de forma natural e contextual para o último post publicado: `<a href=\"" . $last_post_link . "\">" . $last_post_title . "</a>`.\n";
        }
        $prompt .= "6. Legibilidade e Flesch Reading Ease adaptado para PT-BR (CRÍTICO):\n";
        $prompt .= "   - Tamanho das Frases: Escreva frases curtas. Cada frase deve ter em média de 15 a 18 palavras. Evite frases excessivamente longas e complexas.\n";
        $prompt .= "   - Flesch Reading Ease: Use vocabulário do dia a dia simples. O objetivo é alcançar uma pontuação Flesch de 60-70 (Fácil Leitura em Português).\n";
        $prompt .= "   - Palavras de Transição: Pelo menos 30% a 35% das frases do texto devem conter termos de transição/conectivos (ex: portanto, além disso, contudo, consequentemente, por exemplo, então, assim, todavia, entretanto, mas, porém, desse modo, com isso, além do mais).\n";
        $prompt .= "   - Variedade: Não inicie três frases consecutivas com a mesma palavra.\n";
        $prompt .= "7. Distribuição de Subtítulos (Subheading distribution): Cada seção principal H2 com mais de 200 palavras deve ser subdividida por títulos menores `<h3>` contextuais para melhorar a leitura, garantindo que nenhum bloco de texto tenha mais de 250 palavras sem um subtítulo.\n";
        $prompt .= "8. Estruture o artigo em 6 seções principais (H2). Você está PROIBIDO de usar títulos genéricos. Crie H2s criativos e contextuais específicos do tema real do vídeo.\n";
        $prompt .= "9. Certifique-se de usar formatação limpa (listas ordenadas e não ordenadas, parágrafos bem espaçados).";

        $payload = array(
            'model'       => $ai_model,
            'messages'    => array(
                array( 'role' => 'system', 'content' => 'Você é um professor e divulgador científico jovem, divertido e empolgante. Seu objetivo é explicar tecnologias e ciências avançadas de forma simples, amigável e clara para um jovem de 12 anos entender perfeitamente, usando períodos curtos (15-18 palavras por frase), vocabulário simplificado e analogias lúdicas, sem perder a riqueza de detalhes e a extensão do texto de 2000 palavras. Você sempre delimita o título com [TITULO]...[/TITULO], a palavra de foco com [PALAVRA_FOCO]...[/PALAVRA_FOCO], as tags com [PALAVRAS_CHAVE]...[/PALAVRAS_CHAVE], o resumo com [RESUMO]...[/RESUMO], o FAQ com [FAQ]...[/FAQ] e o conteúdo com [CONTEUDO]...[/CONTEUDO].' ),
                array( 'role' => 'user', 'content' => $prompt )
            ),
            'temperature' => 0.7,
            'max_tokens'  => 8000
        );

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
            'timeout' => 180,
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . self::get_api_key()
            ),
            'body' => json_encode( $payload )
        ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body );

        if ( isset( $data->choices[0]->message->content ) ) {
            return $data->choices[0]->message->content;
        }

        return false;
    }

    /**
     * Baixa a imagem da thumbnail do YouTube e anexa como imagem destacada do post
     */
    public static function sideload_youtube_featured_image( $post_id, $thumbnail_url, $post_title ) {
        if ( empty( $thumbnail_url ) ) {
            $youtube_id = get_post_meta( $post_id, '_oscarlima_youtube_id', true );
            if ( $youtube_id ) {
                $thumbnail_url = "https://img.youtube.com/vi/" . $youtube_id . "/maxresdefault.jpg";
            }
        }

        $focus_keyword = get_post_meta( $post_id, '_oscarlima_focus_keyword', true );
        if ( empty( $focus_keyword ) ) {
            $focus_keyword = $post_title;
        }

        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/media.php' );

        add_filter( 'https_ssl_verify', '__return_false' );
        add_filter( 'https_local_ssl_verify', '__return_false' );

        // Prefiltro para renomear o arquivo baixado para SEO
        $seo_filename = sanitize_title( $post_title ) . '-' . wp_generate_password( 4, false ) . '.jpg';
        $rename_filter = function( $file ) use ( $seo_filename ) {
            $file['name'] = $seo_filename;
            return $file;
        };
        add_filter( 'wp_handle_sideload_prefilter', $rename_filter );

        // Executa o download e anexa à biblioteca de mídia do WordPress
        $attachment_id = media_sideload_image( $thumbnail_url, $post_id, $post_title, 'id' );

        // Fallback caso maxresdefault.jpg retorne 404
        if ( is_wp_error( $attachment_id ) ) {
            $youtube_id = get_post_meta( $post_id, '_oscarlima_youtube_id', true );
            if ( $youtube_id ) {
                $fallback_url = "https://img.youtube.com/vi/" . $youtube_id . "/hqdefault.jpg";
                $attachment_id = media_sideload_image( $fallback_url, $post_id, $post_title, 'id' );
            }
        }

        remove_filter( 'wp_handle_sideload_prefilter', $rename_filter );
        remove_filter( 'https_ssl_verify', '__return_false' );
        remove_filter( 'https_local_ssl_verify', '__return_false' );

        if ( ! is_wp_error( $attachment_id ) ) {
            // Otimiza metadados do anexo de imagem para SEO
            wp_update_post( array(
                'ID'           => $attachment_id,
                'post_title'   => $post_title,
                'post_excerpt' => $focus_keyword,
                'post_content' => $post_title
            ) );
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', $focus_keyword );

            set_post_thumbnail( $post_id, $attachment_id );
            return true;
        }

        return false;
    }

    /**
     * Gera um artigo a partir de um resumo de projeto
     */
    public static function generate_article_from_project( $project_name, $project_summary, $project_prompt, $project_impl_plan, $category_id, $status = 'draft' ) {
        // Resolve a categoria
        $cat_name = 'Inteligência Artificial';
        if ( ! empty( $category_id ) ) {
            $cat_obj = get_category( $category_id );
            if ( $cat_obj && ! is_wp_error( $cat_obj ) ) {
                $cat_name = $cat_obj->name;
            }
        }

        $ai_content_raw = self::generate_project_content_via_ai( $project_name, $project_summary, $project_prompt, $project_impl_plan, $cat_name );
        if ( ! $ai_content_raw ) {
            error_log( 'OscarLima Project: Falha ao gerar conteúdo via OpenAI.' );
            return array( 'success' => false, 'message' => 'Falha ao gerar o conteúdo via API OpenAI.' );
        }

        // Parser unificado (reutiliza o parser de tags do RSS/YouTube)
        $parsed = self::parse_ai_response( $ai_content_raw );

        if ( empty( $parsed['title'] ) || empty( $parsed['content'] ) ) {
            return array( 'success' => false, 'message' => 'Erro de parsing no título/conteúdo do artigo gerado.' );
        }

        // Monta conteúdo final (FAQ + imagem inline)
        $final_content = self::assemble_final_content( $parsed['content'], $parsed['faq'], $parsed['title'] );

        $post_id = wp_insert_post( array(
            'post_title'   => $parsed['title'],
            'post_content' => $final_content,
            'post_excerpt' => $parsed['excerpt'],
            'post_status'  => $status,
            'post_author'  => 1,
            'post_type'    => 'post',
        ) );

        if ( ! $post_id || is_wp_error( $post_id ) ) {
            return array( 'success' => false, 'message' => 'Falha ao salvar a postagem.' );
        }

        // Metadados do projeto para rastreabilidade
        update_post_meta( $post_id, '_oscarlima_rss_link', esc_url_raw( home_url( '/blog/' ) ) );
        update_post_meta( $post_id, '_oscarlima_rss_title', sanitize_text_field( $project_name ) );
        update_post_meta( $post_id, '_oscarlima_rss_desc', sanitize_text_field( $project_summary ) );

        // SEO nativo: FAQ schema, palavra-chave foco e tags
        self::save_faq_metadata( $post_id, $parsed['faq'] );
        self::save_seo_metadata( $post_id, $parsed['focus_keyword'], $parsed['tags'] );

        // Associa à categoria selecionada
        if ( ! empty( $category_id ) ) {
            wp_set_post_categories( $post_id, array( intval( $category_id ) ) );
        }

        // Faz o sideload da imagem destacada do post (usando o título para busca)
        self::sideload_featured_image( $post_id, $parsed['title'] );

        return array( 'success' => true, 'post_id' => $post_id, 'title' => $parsed['title'] );
    }

    /**
     * Conecta à OpenAI e gera o artigo com base no resumo do projeto
     */
    public static function generate_project_content_via_ai( $project_name, $project_summary, $project_prompt, $project_impl_plan, $category ) {
        // Obtém o link e título do último post publicado de forma nativa para links internos
        $recent_posts = get_posts( array(
            'numberposts' => 1,
            'post_status' => 'publish'
        ) );
        $last_post_link = '';
        $last_post_title = '';
        if ( ! empty( $recent_posts ) ) {
            $last_post_link = esc_url( get_permalink( $recent_posts[0]->ID ) );
            $last_post_title = wp_strip_all_tags( $recent_posts[0]->post_title );
        }

        // Obtém os títulos dos últimos 3 posts para deduplicação semântica no título
        $last_3_posts = get_posts( array(
            'numberposts' => 3,
            'post_status' => 'any'
        ) );
        $recent_titles = array();
        foreach ( $last_3_posts as $lp ) {
            $recent_titles[] = $lp->post_title;
        }
        $recent_titles_str = implode( ' | ', $recent_titles );

        // Configurações personalizadas
        $title_min = intval( get_option( self::$option_title_min, 35 ) );
        $title_max = intval( get_option( self::$option_title_max, 45 ) );
        $desc_min = intval( get_option( self::$option_desc_min, 120 ) );
        $desc_max = intval( get_option( self::$option_desc_max, 140 ) );
        $ai_model = get_option( self::$option_model, 'gpt-4o-mini' );

        $prompt = "Você é um gerador de artigos de tecnologia e ciência avançado. Escreva um artigo técnico, didático e jornalístico extremamente abrangente, aprofundado e completo em Português do Brasil com NO MÍNIMO 2000 PALAVRAS sobre o desenvolvimento de um projeto de tecnologia e a experiência do programador com a Inteligência Artificial (Gemini) no desenvolvimento do MVP:\n\n";
        $prompt .= "Nome do Projeto / MVP: " . $project_name . "\n";
        $prompt .= "Resumo do Projeto, Arquitetura e Relato da Experiência com o Gemini:\n" . $project_summary . "\n";
        $prompt .= "Subcategoria do Artigo: " . $category . "\n\n";

        $prompt .= "Informações Adicionais Importantes para Incluir no Artigo:\n";
        $prompt .= "- Prompt Original do Projeto:\n" . $project_prompt . "\n\n";
        $prompt .= "- Plano de Implementação Original do Projeto:\n" . $project_impl_plan . "\n\n";
        
        $prompt .= "Você DEVE responder utilizando exatamente as tags customizadas abaixo para delimitar o título, a palavra-chave foco, as palavras-chave secundárias, o resumo de SEO (meta descrição), o FAQ e o conteúdo:\n";
        $prompt .= "[TITULO]\n";
        $prompt .= "Crie um título em português do Brasil com alto apelo de clique (CTR) e sentimento forte. Siga as regras rígidas de SEO:\n";
        $prompt .= "1. Comprimento estrito de " . $title_min . " a " . $title_max . " caracteres (incluindo espaços). NUNCA ultrapasse " . $title_max . " caracteres de forma alguma.\n";
        $prompt .= "2. A palavra-chave foco (focus keyword) gerada abaixo DEVE estar no início do título (preferencialmente nos primeiros 40 caracteres).\n";
        $prompt .= "3. Use números ou colchetes visuais quando for útil e contextual (ex: '[Guia MVP]', '[Experiência Real]').\n";
        $prompt .= "4. Quantidade de palavras: Use no máximo 6 a 8 palavras no total.\n";
        $prompt .= "5. Evite repetições: Você está proibido de gerar títulos parecidos com: [ " . $recent_titles_str . " ].\n";
        $prompt .= "[/TITULO]\n\n";

        $prompt .= "[PALAVRA_FOCO]\n";
        $prompt .= "Escreva aqui uma única palavra ou termo curto (máximo de 3 palavras) que será a Palavra-Chave Foco (focus keyword) do artigo.\n";
        $prompt .= "[/PALAVRA_FOCO]\n\n";

        $prompt .= "[PALAVRAS_CHAVE]\n";
        $prompt .= "Escreva de 5 a 8 palavras-chave separadas apenas por vírgulas para usarmos como Tags do post.\n";
        $prompt .= "[/PALAVRAS_CHAVE]\n\n";

        $prompt .= "[RESUMO]\n";
        $prompt .= "Escreva aqui um resumo executivo do artigo em português que servirá como meta descrição para os buscadores. Siga as regras de SEO:\n";
        $prompt .= "1. Comprimento estrito de " . $desc_min . " a " . $desc_max . " caracteres (incluindo espaços).\n";
        $prompt .= "2. Use voz ativa e resuma de forma direta o valor principal do artigo.\n";
        $prompt .= "[/RESUMO]\n\n";

        $prompt .= "[FAQ]\n";
        $prompt .= "Gere uma seção de Perguntas Frequentes (FAQ) com 3 a 5 perguntas e respostas curtas e objetivas sobre o projeto e o aprendizado obtido. Utilize a seguinte formatação HTML exata:\n";
        $prompt .= "<h3>Pergunta...?</h3>\n";
        $prompt .= "<p>Resposta curta, direta e com voz ativa.</p>\n";
        $prompt .= "[/FAQ]\n\n";

        $prompt .= "[CONTEUDO]\n";
        $prompt .= "Escreva aqui o conteúdo completo do artigo em formato HTML semântico puro.\n";
        $prompt .= "[/CONTEUDO]\n\n";

        $prompt .= "Instruções Cruciais para o Tom e Conteúdo (HTML):\n";
        $prompt .= "1. O tom do artigo DEVE ser super didático, simples, amigável e empolgante, de forma que um jovem de 12 anos consiga ler e entender perfeitamente o assunto. Use analogias lúdicas e divertidas do dia a dia. Explique termos como MVP, APIs e Gemini com analogias simples.\n";
        $prompt .= "2. O artigo DEVE conter no mínimo 2000 palavras em português do Brasil dentro da tag [CONTEUDO]. Conte a história do projeto, a ideia do MVP, a stack utilizada, e inclua obrigatoriamente as seguintes seções estruturadas:\n";
        $prompt .= "   - Uma seção exibindo o Prompt original com a introdução exata: 'Aqui está o prompt utilizado para criar o projeto' seguida de um bloco <pre> com o prompt. Substitua as variáveis que o leitor precisa para 'colocar a cara dele' (como dimensões fixas, paletas de cores, caminhos e handles de usuário) por variáveis genéricas explicativas em colchetes (ex: [LARGURA_X_ALTURA], [SUAS_CORES_HEX], [SUAS_FONTES], [PORTA_SERVIDOR], [URL_FOTO_PERFIL_PADRAO], [SEU_NOME], [SEU_HANDLE_REDE_SOCIAL] e [MODELO_IA]).\n";
        $prompt .= "   - Uma seção exibindo o Plano de Implementação original com a introdução exata: 'Aqui está o plano de implementação' contendo as diretrizes de desenvolvimento estruturadas.\n";
        $prompt .= "   - Uma seção de comparação com a introdução exata: 'Compare com o seu resultado obtido e verifique o comportamento', ensinando o leitor a rodar testes básicos e depurar seu código local.\n";
        $prompt .= "   - REGRA RÍGIDA DE ESCAPAMENTO DE HTML: Para evitar quebrar o painel e o site WordPress, toda tag HTML ou código de template que estiver contido em blocos <pre><code> ou similares DEVE ser devidamente escapado substituindo os colchetes angulares por entidades HTML (substituir < por &lt; e > por &gt;). NUNCA insira tags HTML brutas ou elementos funcionais reais como inputs e botões dentro de tags <pre>.\n";
        $prompt .= "   - Stack de Código Atualizada: Ao exibir códigos Node.js nos exemplos, use a SDK oficial da OpenAI (versão v4 com new OpenAI() e chamadas chat.completions.create) e o modelo moderno gpt-4o-mini. É proibido usar pacotes de terceiros obsoletos (como openai-api) ou modelos descontinuados como text-davinci-003.\n";
        $prompt .= "3. No HTML, NÃO use tags <html> ou <body>, e NÃO inclua títulos H1 no início do artigo. Use tags H2 para seções principais e H3 para subseções.\n";
        $prompt .= "4. Este é um artigo de experiência própria do blog. Você está proibido de incluir link de saída ou referência a fontes externas concorrentes. Foque na experiência e no relato técnico real.\n";
        $prompt .= "5. Incorpore obrigatoriamente no corpo do texto pelo menos dois links internos absolutos:\n";
        $prompt .= "   - Um link dinâmico absoluto apontando para a nossa página inicial institucional: `<a href=\"" . esc_url( home_url('/') ) . "\">serviços de desenvolvimento do Oscar</a>`.\n";
        if ( ! empty( $last_post_link ) ) {
            $prompt .= "   - Um link dinâmico absoluto apontando de forma natural e contextual para o último post publicado: `<a href=\"" . $last_post_link . "\">" . $last_post_title . "</a>`.\n";
        }
        $prompt .= "6. Legibilidade e Flesch Reading Ease adaptado para PT-BR (CRÍTICO):\n";
        $prompt .= "   - Tamanho das Frases: Escreva frases curtas. Cada frase deve ter em média de 15 a 18 palavras. Evite frases excessivamente longas e complexas.\n";
        $prompt .= "   - Flesch Reading Ease: Use vocabulário do dia a dia simples. O objetivo é alcançar uma pontuação Flesch de 60-70.\n";
        $prompt .= "   - Palavras de Transição: Pelo menos 30% a 35% das frases do texto devem conter termos de transição/conectivos.\n";
        $prompt .= "   - Variedade: Não inicie três frases consecutivas com a mesma palavra.\n";
        $prompt .= "7. Distribuição de Subtítulos: Cada seção principal H2 com mais de 200 palavras deve ser subdividida por títulos menores `<h3>` contextuais para melhorar a leitura.\n";
        $prompt .= "8. Estruture o artigo em 6 seções principais (H2). Crie H2s criativos e específicos sobre a jornada, o MVP e o Gemini.\n";
        $prompt .= "9. Certifique-se de usar formatação limpa (listas ordenadas e não ordenadas, parágrafos bem espaçados).";

        $payload = array(
            'model'       => $ai_model,
            'messages'    => array(
                array( 'role' => 'system', 'content' => 'Você é um professor e divulgador científico jovem, divertido e empolgante. Seu objetivo é explicar tecnologias e desenvolvimento de software de forma simples, amigável e clara para um jovem de 12 anos entender perfeitamente, usando períodos curtos (15-18 palavras por frase), vocabulário simplificado e analogias lúdicas, sem perder a riqueza de detalhes e a extensão do texto de 2000 palavras. Você sempre delimita o título com [TITULO]...[/TITULO], a palavra de foco com [PALAVRA_FOCO]...[/PALAVRA_FOCO], as tags com [PALAVRAS_CHAVE]...[/PALAVRAS_CHAVE], o resumo com [RESUMO]...[/RESUMO], o FAQ com [FAQ]...[/FAQ] e o conteúdo com [CONTEUDO]...[/CONTEUDO].' ),
                array( 'role' => 'user', 'content' => $prompt )
            ),
            'temperature' => 0.7,
            'max_tokens'  => 8000
        );

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
            'timeout' => 180,
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . self::get_api_key()
            ),
            'body' => json_encode( $payload )
        ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body );

        if ( isset( $data->choices[0]->message->content ) ) {
            return $data->choices[0]->message->content;
        }

        return false;
    }
}

OscarLima_Article_Generator::init();
