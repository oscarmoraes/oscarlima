document.addEventListener('DOMContentLoaded', () => {
    // 1. Scroll Progress & Header Class
    const header = document.querySelector('header');
    const scrollProgress = document.getElementById('scroll-progress');
    
    window.addEventListener('scroll', () => {
        const winScroll = document.documentElement.scrollTop || document.body.scrollTop;
        const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
        const scrolled = (winScroll / height) * 100;
        
        if (scrollProgress) scrollProgress.style.width = scrolled + '%';
        
        if (header) {
            if (winScroll > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        }
    });

    // 2. Accessible Mobile Menu Toggle with Body Scroll Lock
    const hamburger = document.querySelector('.hamburger');
    const navMenu = document.querySelector('.nav-menu');
    const navLinks = document.querySelectorAll('.nav-link');

    if (hamburger && navMenu) {
        const toggleMenu = (open) => {
            const isOpen = typeof open === 'boolean' ? open : !navMenu.classList.contains('active');
            hamburger.classList.toggle('active', isOpen);
            navMenu.classList.toggle('active', isOpen);
            hamburger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            document.body.style.overflow = isOpen ? 'hidden' : '';
        };

        hamburger.addEventListener('click', () => toggleMenu());

        navLinks.forEach(link => {
            link.addEventListener('click', () => toggleMenu(false));
        });

        // Close on Escape key for keyboard accessibility
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && navMenu.classList.contains('active')) {
                toggleMenu(false);
            }
        });
    }

    // 3. 3D Monumental Particle Engine is driven by app.js

    // 4. Typewriter Effect (Robust & Safe Version with Entity Decoding)
    const typewriter = document.querySelector('.typewriter-text');
    if (typewriter) {
        let words = ["Criação de Sites", "WordPress Sob Medida", "Landing Pages", "Front-end Moderno", "SEO e Presença Digital"];
        const attrWords = typewriter.getAttribute('data-words');
        if (attrWords) {
            try {
                // Decodifica entidades HTML como &quot; caso existam na string antes do parse JSON
                const decodedWords = attrWords.replace(/&quot;/g, '"');
                words = JSON.parse(decodedWords);
            } catch (e) {
                console.error("Erro ao analisar data-words do Typewriter, usando fallback:", e);
            }
        }

        let wordIndex = 0;
        let charIndex = 0;
        let isDeleting = false;
        let typingDelay = 120;

        function type() {
            if (!words || words.length === 0) return;
            const currentWord = words[wordIndex];
            if (!currentWord) return;

            if (isDeleting) {
                typewriter.textContent = currentWord.substring(0, charIndex);
                charIndex--;
            } else {
                typewriter.textContent = currentWord.substring(0, charIndex);
                charIndex++;
            }

            if (!isDeleting && charIndex > currentWord.length) {
                isDeleting = true;
                typingDelay = 2000; // Pausa no final da digitação
            } else if (isDeleting && charIndex < 0) {
                isDeleting = false;
                wordIndex = (wordIndex + 1) % words.length;
                charIndex = 0;
                typingDelay = 500; // Pausa antes de digitar a próxima palavra
            } else {
                typingDelay = isDeleting ? 40 : 80;
            }

            setTimeout(type, typingDelay);
        }
        
        setTimeout(type, 1000);
    }

    // 5. Scroll Reveal Animation
    const revealElements = document.querySelectorAll('.reveal');
    const revealOnScroll = () => {
        revealElements.forEach(element => {
            const elementTop = element.getBoundingClientRect().top;
            const elementVisible = 120;
            if (elementTop < window.innerHeight - elementVisible) {
                element.classList.add('active');
            }
        });
    };
    window.addEventListener('scroll', revealOnScroll);
    revealOnScroll(); // Disparar uma vez no carregamento

    // 6. Metric Counters Animation (Intersection Observer Version)
    const statsSection = document.querySelector('.stats-banner');
    const statNumbers = document.querySelectorAll('.stat-number');
    
    if (statsSection && statNumbers.length > 0) {
        const observerOptions = {
            root: null,
            rootMargin: '0px',
            threshold: 0.15 // Dispara quando 15% do banner estiver visível
        };

        const observer = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    statNumbers.forEach(stat => {
                        const target = parseInt(stat.getAttribute('data-target'), 10);
                        const suffix = stat.getAttribute('data-suffix') || '';
                        let current = 0;
                        const duration = 1500; // 1.5s
                        const increment = target / (duration / 16); // ~60fps
                        
                        const updateCounter = () => {
                            current += increment;
                            if (current >= target) {
                                stat.textContent = target + suffix;
                            } else {
                                stat.textContent = Math.floor(current) + suffix;
                                requestAnimationFrame(updateCounter);
                            }
                        };
                        updateCounter();
                    });
                    // Desativa a observação para rodar a animação apenas uma vez
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        observer.observe(statsSection);
    }

    // 7. Portfolio Filtering
    const filterButtons = document.querySelectorAll('.filter-btn');
    const projectCards = document.querySelectorAll('.project-card');

    filterButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            filterButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const filterValue = btn.getAttribute('data-filter');

            projectCards.forEach(card => {
                const category = card.getAttribute('data-category');
                if (filterValue === 'all' || category === filterValue) {
                    card.style.display = 'flex';
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0) scale(1)';
                    }, 50);
                } else {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(10px) scale(0.95)';
                    setTimeout(() => {
                        card.style.display = 'none';
                    }, 300);
                }
            });
        });
    });

    // 8. FAQ Accordion
    const faqItems = document.querySelectorAll('.faq-item');
    faqItems.forEach(item => {
        const question = item.querySelector('.faq-question');
        if (question) {
            question.addEventListener('click', () => {
                const isActive = item.classList.contains('active');
                faqItems.forEach(i => i.classList.remove('active'));
                
                if (!isActive) {
                    item.classList.add('active');
                }
            });
        }
    });

    // 9. Interactive AI Chatbot Logic (Safe Version)
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
                const response = await fetch('api/chat.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ message: messageText })
                });

                const data = await response.json();
                removeTypingIndicator();

                if (data.error) {
                    appendMessage("Desculpe, ocorreu um erro de conexão com meu servidor. " + data.error, 'bot');
                } else if (data.choices && data.choices[0] && data.choices[0].message) {
                    const botReply = data.choices[0].message.content;
                    
                    let formattedReply = botReply.replace(/\n/g, '<br>');
                    const waLink = `https://wa.me/5521991061796?text=${encodeURIComponent('Oi Oscar! Minha conversa com seu chat de IA sugeriu: ' + botReply.substring(0, 120) + '...')}`;
                    formattedReply += `<br><br><a href="${waLink}" target="_blank" class="chat-whatsapp-cta"><i class="fab fa-whatsapp"></i> Fechar valor no WhatsApp</a>`;
                    
                    appendMessage(formattedReply, 'bot', true);
                } else {
                    appendMessage("Desculpe, não consegui obter uma resposta adequada. Tente novamente.", 'bot');
                }
            } catch (err) {
                removeTypingIndicator();
                appendMessage("Erro ao tentar conectar ao servidor de IA. Por favor, tente falar diretamente pelo WhatsApp.", 'bot');
                console.error(err);
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

    // 10. Contact Form Submitting (Safe Version)
    const contactForm = document.getElementById('contact-form');
    if (contactForm) {
        contactForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const nameEl = document.getElementById('form-name');
            const emailEl = document.getElementById('form-email');
            const serviceEl = document.getElementById('form-service');
            const messageEl = document.getElementById('form-message');

            const name = nameEl ? nameEl.value : '';
            const email = emailEl ? emailEl.value : '';
            const service = serviceEl ? serviceEl.value : '';
            const message = messageEl ? messageEl.value : '';

            // Form message for WhatsApp
            const text = `Olá Oscar! Meu nome é ${name} (${email}). Estou interessado em: ${service}. Mensagem: ${message}`;
            const waUrl = `https://wa.me/5521991061796?text=${encodeURIComponent(text)}`;
            
            // Redirect to WhatsApp
            window.open(waUrl, '_blank');
        });
    }
});
