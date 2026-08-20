/**
 * OSCAR LIMA - COOKIE CONSENT BANNER ENGINE (LGPD & GDPR Compliant)
 * Lógica de consentimento unificado para páginas estáticas e WordPress.
 * Gerencia integrações do Google Analytics 4, Tag Manager e Facebook Pixel de forma assíncrona.
 */

(function () {
    // Configurações padrão
    var defaultConfig = {
        active: true,
        privacy_url: "/blog/politica-de-privacidade/",
        ga_id: "",
        gtm_id: "",
        pixel_id: "",
        custom_analytics: "",
        custom_marketing: ""
    };

    var config = Object.assign({}, defaultConfig);
    var cookieName = "ol_cookie_consent";
    var consent = {
        necessary: true,
        analytics: false,
        marketing: false
    };

    // Inicializa o Google Consent Mode padrão (antes de qualquer tag carregar)
    initConsentMode();

    // Carrega as configurações do JSON
    fetchConfig();

    /**
     * Define o Consent Mode Padrão do Google
     */
    function initConsentMode() {
        window.dataLayer = window.dataLayer || [];
        function gtag() { dataLayer.push(arguments); }
        window.gtag = gtag;

        // Se já existe consentimento salvo, carrega os estados salvos
        var savedConsent = getConsentCookie();
        if (savedConsent) {
            consent = savedConsent;
        }

        gtag('consent', 'default', {
            'ad_storage': consent.marketing ? 'granted' : 'denied',
            'ad_user_data': consent.marketing ? 'granted' : 'denied',
            'ad_personalization': consent.marketing ? 'granted' : 'denied',
            'analytics_storage': consent.analytics ? 'granted' : 'denied',
            'wait_for_update': 500
        });
    }

    /**
     * Busca as opções salvas via painel WordPress através do arquivo JSON compartilhado
     */
    function fetchConfig() {
        // Usa timestamp para contornar cache do navegador ao buscar as configurações
        var configUrl = "/js/cookie-config.json?t=" + new Date().getTime();
        
        var xhr = new XMLHttpRequest();
        xhr.open("GET", configUrl, true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        config = Object.assign({}, defaultConfig, response);
                    } catch (e) {
                        console.warn("Falha ao analisar o JSON de configurações de cookies. Usando padrões.");
                    }
                }
                
                // Se o banner não estiver ativo nas configurações globais, apenas ativa as tags já aceitas e encerra
                if (!config.active) {
                    var saved = getConsentCookie();
                    if (saved) {
                        triggerIntegrations(saved);
                    }
                    return;
                }

                // Inicia o fluxo principal
                initConsentFlow();
            }
        };
        xhr.send();
    }

    /**
     * Lógica principal de fluxo e exibição do banner
     */
    function initConsentFlow() {
        var savedConsent = getConsentCookie();
        
        if (savedConsent) {
            consent = savedConsent;
            triggerIntegrations(consent);
            return;
        }

        // Se não houver consentimento salvo, exibe o banner com delay
        setTimeout(function () {
            renderBannerElements();
        }, 1500);
    }

    /**
     * Injeta o HTML do banner e do modal de preferências na página
     */
    function renderBannerElements() {
        // Previne duplicados
        if (document.getElementById("ol-cookie-consent-banner")) return;

        // Estutura do Banner principal
        var bannerHtml = `
            <div id="ol-cookie-consent-banner" class="ol-cookie-banner">
                <div class="ol-cookie-banner-content">
                    <h4><i class="fa-solid fa-cookie-bite"></i> Controle de Privacidade</h4>
                    <p>
                        Nós usamos cookies para analisar o tráfego do site, melhorar o desempenho e personalizar o conteúdo. 
                        Ao aceitar, você concorda com nossa <a href="${config.privacy_url}" target="_blank">Política de Privacidade</a>.
                    </p>
                </div>
                <div class="ol-cookie-banner-actions">
                    <button id="ol-cookie-btn-pref" class="ol-cookie-btn ol-cookie-btn-link">Preferências</button>
                    <button id="ol-cookie-btn-reject" class="ol-cookie-btn ol-cookie-btn-secondary">Recusar</button>
                    <button id="ol-cookie-btn-accept" class="ol-cookie-btn ol-cookie-btn-primary"><i class="fa-solid fa-check"></i> Aceitar Todos</button>
                </div>
            </div>
            
            <div id="ol-cookie-consent-overlay" class="ol-cookie-overlay"></div>
            
            <div id="ol-cookie-consent-modal" class="ol-cookie-modal">
                <div class="ol-cookie-modal-header">
                    <h3>Preferências de Privacidade</h3>
                    <button id="ol-cookie-modal-close" class="ol-cookie-modal-close" aria-label="Fechar"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="ol-cookie-modal-description">
                    Configure abaixo quais tipos de cookies você autoriza serem carregados no seu navegador. Os cookies necessários são essenciais para o funcionamento do site.
                </div>
                <div class="ol-cookie-options-list">
                    <!-- Necessários -->
                    <div class="ol-cookie-option-item">
                        <div class="ol-cookie-option-info">
                            <h5 class="ol-cookie-option-title">Necessários <span class="badge">Obrigatório</span></h5>
                            <p class="ol-cookie-option-desc">Cookies essenciais para o carregamento do layout, navegação básica e segurança do site.</p>
                        </div>
                        <label class="ol-cookie-switch">
                            <input type="checkbox" checked disabled>
                            <span class="ol-cookie-slider"></span>
                        </label>
                    </div>
                    <!-- Estatísticas -->
                    <div class="ol-cookie-option-item">
                        <div class="ol-cookie-option-info">
                            <h5 class="ol-cookie-option-title">Estatísticos / Analíticos</h5>
                            <p class="ol-cookie-option-desc">Cookies que nos permitem analisar o comportamento do usuário e coletar dados de audiência (como o Google Analytics).</p>
                        </div>
                        <label class="ol-cookie-switch">
                            <input type="checkbox" id="ol-cookie-toggle-analytics">
                            <span class="ol-cookie-slider"></span>
                        </label>
                    </div>
                    <!-- Marketing -->
                    <div class="ol-cookie-option-item">
                        <div class="ol-cookie-option-info">
                            <h5 class="ol-cookie-option-title">Marketing & Chatbot</h5>
                            <p class="ol-cookie-option-desc">Cookies para exibição de anúncios personalizados e funcionamento de scripts terceiros (como Facebook Pixel e Chatbot inteligente).</p>
                        </div>
                        <label class="ol-cookie-switch">
                            <input type="checkbox" id="ol-cookie-toggle-marketing">
                            <span class="ol-cookie-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="ol-cookie-modal-actions">
                    <button id="ol-cookie-btn-save-pref" class="ol-cookie-btn ol-cookie-btn-primary">Salvar Preferências</button>
                </div>
            </div>
        `;

        // Insere o HTML no final do body
        var wrapper = document.createElement("div");
        wrapper.id = "ol-cookie-consent-wrapper";
        wrapper.innerHTML = bannerHtml;
        document.body.appendChild(wrapper);

        // Referências de DOM
        var banner = document.getElementById("ol-cookie-consent-banner");
        var overlay = document.getElementById("ol-cookie-consent-overlay");
        var modal = document.getElementById("ol-cookie-consent-modal");

        var btnAccept = document.getElementById("ol-cookie-btn-accept");
        var btnReject = document.getElementById("ol-cookie-btn-reject");
        var btnPref = document.getElementById("ol-cookie-btn-pref");
        var btnCloseModal = document.getElementById("ol-cookie-modal-close");
        var btnSavePref = document.getElementById("ol-cookie-btn-save-pref");

        // Exibe o banner
        setTimeout(function () {
            banner.classList.add("show");
        }, 100);

        // Ações de Aceite total
        btnAccept.addEventListener("click", function () {
            var consentData = { necessary: true, analytics: true, marketing: true };
            saveConsent(consentData);
        });

        // Ações de Rejeição total (Mantém apenas necessários)
        btnReject.addEventListener("click", function () {
            var consentData = { necessary: true, analytics: false, marketing: false };
            saveConsent(consentData);
        });

        // Abre modal de preferências
        btnPref.addEventListener("click", function () {
            document.getElementById("ol-cookie-toggle-analytics").checked = consent.analytics;
            document.getElementById("ol-cookie-toggle-marketing").checked = consent.marketing;
            overlay.classList.add("show");
            modal.classList.add("show");
        });

        // Fecha modal
        btnCloseModal.addEventListener("click", function () {
            overlay.classList.remove("show");
            modal.classList.remove("show");
        });

        overlay.addEventListener("click", function () {
            overlay.classList.remove("show");
            modal.classList.remove("show");
        });

        // Salva preferências granulares
        btnSavePref.addEventListener("click", function () {
            var consentData = {
                necessary: true,
                analytics: document.getElementById("ol-cookie-toggle-analytics").checked,
                marketing: document.getElementById("ol-cookie-toggle-marketing").checked
            };
            saveConsent(consentData);
            overlay.classList.remove("show");
            modal.classList.remove("show");
        });
    }

    /**
     * Salva o consentimento no Cookie e localStorage, fecha o banner e ativa integrações
     */
    function saveConsent(consentData) {
        consent = consentData;
        
        // Define o cookie expirando em 1 ano (365 dias)
        var d = new Date();
        d.setTime(d.getTime() + (365 * 24 * 60 * 60 * 1000));
        var expires = "expires=" + d.toUTCString();
        
        // Configura cookie compartilhado para o domínio principal (incluindo subpastas)
        // Se estiver no localhost, omite o parâmetro domain para funcionar localmente
        var domainString = "";
        if (location.hostname !== "localhost" && location.hostname !== "127.0.0.1") {
            domainString = ";domain=.oscarlima.com.br";
        }
        
        document.cookie = cookieName + "=" + JSON.stringify(consent) + ";" + expires + ";path=/" + domainString + ";SameSite=Lax;Secure";
        
        // Também armazena no localStorage por redundância
        localStorage.setItem(cookieName, JSON.stringify(consent));

        // Esconde o banner
        var banner = document.getElementById("ol-cookie-consent-banner");
        if (banner) {
            banner.classList.remove("show");
            setTimeout(function () {
                var wrapper = document.getElementById("ol-cookie-consent-wrapper");
                if (wrapper) wrapper.remove();
            }, 600);
        }

        // Atualiza o Google Consent Mode
        if (window.gtag) {
            window.gtag('consent', 'update', {
                'analytics_storage': consent.analytics ? 'granted' : 'denied',
                'ad_storage': consent.marketing ? 'granted' : 'denied',
                'ad_user_data': consent.marketing ? 'granted' : 'denied',
                'ad_personalization': consent.marketing ? 'granted' : 'denied'
            });
        }

        // Ativa os scripts de terceiros permitidos
        triggerIntegrations(consent);

        // Despara evento customizado para outros scripts escutarem
        var event = new CustomEvent("olCookieConsentUpdated", { detail: consent });
        window.dispatchEvent(event);
    }

    /**
     * Obtém o cookie de consentimento salvo
     */
    function getConsentCookie() {
        var name = cookieName + "=";
        var decodedCookie = decodeURIComponent(document.cookie);
        var ca = decodedCookie.split(';');
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0) == ' ') {
                c = c.substring(1);
            }
            if (c.indexOf(name) == 0) {
                try {
                    return JSON.parse(c.substring(name.length, c.length));
                } catch (e) {
                    return null;
                }
            }
        }
        
        // Se falhar no cookie, busca no localStorage
        var local = localStorage.getItem(cookieName);
        if (local) {
            try {
                return JSON.parse(local);
            } catch (e) {
                return null;
            }
        }
        
        return null;
    }

    /**
     * Gerencia a injeção e execução das tags de rastreamento com base no consentimento
     */
    function triggerIntegrations(consentData) {
        if (consentData.analytics) {
            // 1. Google Analytics 4 (GA4)
            if (config.ga_id && !window.ga_loaded) {
                window.ga_loaded = true;
                injectScript("https://www.googletagmanager.com/gtag/js?id=" + config.ga_id);
                
                window.dataLayer = window.dataLayer || [];
                function gtag() { dataLayer.push(arguments); }
                gtag('js', new Date());
                gtag('config', config.ga_id);
            }

            // 2. Google Tag Manager (GTM)
            if (config.gtm_id && !window.gtm_loaded) {
                window.gtm_loaded = true;
                (function (w, d, s, l, i) {
                    w[l] = w[l] || []; w[l].push({
                        'gtm.start':
                            new Date().getTime(), event: 'gtm.js'
                    }); var f = d.getElementsByTagName(s)[0],
                        j = d.createElement(s), dl = l != 'dataLayer' ? '&l=' + l : ''; j.async = true; j.src =
                            'https://www.googletagmanager.com/gtm.js?id=' + i + dl; f.parentNode.insertBefore(j, f);
                })(window, document, 'script', 'dataLayer', config.gtm_id);
            }

            // 3. Scripts Analíticos Customizados
            if (config.custom_analytics) {
                injectRawHTML(config.custom_analytics, "custom-analytics-scripts");
            }
        }

        if (consentData.marketing) {
            // 1. Facebook Pixel
            if (config.pixel_id && !window.fb_loaded) {
                window.fb_loaded = true;
                !function (f, b, e, v, n, t, s) {
                    if (f.fbq) return; n = f.fbq = function () {
                        n.callMethod ?
                        n.callMethod.apply(n, arguments) : n.queue.push(arguments)
                    }; if (!f._fbq) f._fbq = n;
                    n.push = n; n.loaded = !0; n.version = '2.0'; n.queue = []; t = b.createElement(e); t.async = !0;
                    t.src = v; s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s)
                }(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
                
                fbq('init', config.pixel_id);
                fbq('track', 'PageView');
            }

            // 2. Scripts de Marketing Customizados
            if (config.custom_marketing) {
                injectRawHTML(config.custom_marketing, "custom-marketing-scripts");
            }
        }
    }

    /**
     * Função auxiliar para injetar tags de script externas
     */
    function injectScript(src) {
        var script = document.createElement("script");
        script.src = src;
        script.async = true;
        document.head.appendChild(script);
    }

    /**
     * Função auxiliar para injetar HTML de tags customizadas com segurança
     */
    function injectRawHTML(html, containerId) {
        if (document.getElementById(containerId)) return;
        
        var container = document.createElement("div");
        container.id = containerId;
        container.style.display = "none";
        container.innerHTML = html;
        document.body.appendChild(container);
        
        // Executa manualmente scripts que foram injetados via innerHTML
        var scripts = container.getElementsByTagName("script");
        for (var i = 0; i < scripts.length; i++) {
            var newScript = document.createElement("script");
            if (scripts[i].src) {
                newScript.src = scripts[i].src;
                newScript.async = true;
            } else {
                newScript.textContent = scripts[i].textContent;
            }
            document.head.appendChild(newScript);
        }
    }
})();
