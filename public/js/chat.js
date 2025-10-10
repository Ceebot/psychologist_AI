const getCsrfToken = () => document.querySelector('[data-csrf-token]')?.dataset.csrfToken || '';

const createNewChat = () => {
    window.location.href = '/chat/new';
};

document.addEventListener('alpine:init', () => {
    Alpine.data('chatApp', () => ({
        showDeleteModal: false,
        chatToDelete: null,
        createNewChat,

        openDeleteModal(chatId) {
            this.chatToDelete = chatId;
            this.showDeleteModal = true;
        },

        confirmDelete() {
            if (!this.chatToDelete) return;

            fetch(`/chat/${this.chatToDelete}/delete`, {
                method: 'DELETE',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCsrfToken()
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                }
            })
            .catch(err => console.error(err))
            .finally(() => {
                this.showDeleteModal = false;
                this.chatToDelete = null;
            });
        }
    }));

    Alpine.data('chatRoom', (chatId, initialTitle) => ({
        messages: [],
        newMessage: '',
        loading: false,
        error: '',
        chatTitle: initialTitle,
        showLimitWarning: false,
        limitInfo: { remaining: 20, total: 20, resetAt: null },
        createNewChat,

        init() {
            this.loadMessages();
            this.$nextTick(() => {
                this.$refs.messageInput?.focus();
            });
        },

        loadMessages() {
            fetch(`/chat/${chatId}/messages`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.messages = data.messages;
                        // При первой загрузке прокручиваем в самый конец
                        this.$nextTick(() => this.scrollToEnd());
                    }
                })
                .catch(err => console.error(err));
        },

        sendMessage() {
            const message = this.newMessage.trim();
            if (!message || this.loading) return;

            this.loading = true;
            this.error = '';
            const messageToSend = message;
            this.newMessage = '';
            
            // Прокручиваем сразу после очистки поля ввода
            this.$nextTick(() => this.scrollToBottom());

            fetch(`/chat/${chatId}/send`, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCsrfToken()
                },
                body: JSON.stringify({ message: messageToSend })
            })
                .then(res => res.json())
                .then(data => {
                    this.loading = false;

                    if (data.success) {
                        this.messages.push(data.userMessage);
                        this.$nextTick(() => this.scrollToBottom());
                        
                        // Небольшая задержка перед добавлением ответа ассистента
                        setTimeout(() => {
                            this.messages.push(data.assistantMessage);
                            this.$nextTick(() => this.scrollToBottom());
                        }, 100);
                        
                        if (data.newTitle) {
                            this.updateChatTitle(data.newTitle);
                        }
                        
                        // Обновляем информацию о лимите
                        if (data.rateLimit) {
                            this.limitInfo = data.rateLimit;
                            
                            // Показываем предупреждение после 10-го использованного запроса
                            if (data.rateLimit.total - data.rateLimit.remaining === 10) {
                                this.showLimitWarning = true;
                                setTimeout(() => {
                                    this.showLimitWarning = false;
                                }, 10000);
                            }
                        }
                    } else {
                        this.error = data.error || 'Произошла ошибка';
                        this.newMessage = messageToSend;
                    }
                })
                .catch(err => {
                    this.loading = false;
                    this.error = 'Ошибка соединения с сервером';
                    this.newMessage = messageToSend;
                    console.error(err);
                });
        },

        scrollToBottom(smooth = false) {
            const container = this.$refs.messagesContainer;
            if (!container) return;
            
            // Находим все реальные сообщения
            const messages = container.querySelectorAll('.message');
            
            if (messages.length >= 2) {
                // Если есть минимум 2 сообщения, прокручиваем к предпоследнему
                const secondLastMessage = messages[messages.length - 2];
                
                // Используем center чтобы сообщение было хорошо видно
                secondLastMessage.scrollIntoView({
                    behavior: smooth ? 'smooth' : 'auto',
                    block: 'center'
                });
                
                // Дополнительно прокручиваем немного вниз, чтобы видеть начало ответа
                setTimeout(() => {
                    const currentScroll = container.scrollTop;
                    const messageHeight = secondLastMessage.offsetHeight;
                    container.scrollTop = currentScroll + messageHeight * 0.3;
                }, smooth ? 300 : 0);
            } else if (messages.length === 1) {
                // Если только одно сообщение, прокручиваем к нему
                const lastMessage = messages[0];
                lastMessage.scrollIntoView({
                    behavior: smooth ? 'smooth' : 'auto',
                    block: 'start'
                });
            } else {
                // Если нет сообщений, прокручиваем к низу
                container.scrollTop = container.scrollHeight;
            }
        },

        scrollToEnd() {
            // Прокрутка в самый конец контейнера
            const container = this.$refs.messagesContainer;
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        },

        formatTime(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleTimeString('ru-RU', {
                hour: '2-digit',
                minute: '2-digit'
            });
        },

        updateChatTitle(newTitle) {
            const titleElements = document.querySelectorAll('.chat-item-title, .chat-header h3');
            titleElements.forEach(el => {
                el.style.animation = 'titleFadeOut 0.3s ease-out';
                setTimeout(() => {
                    this.chatTitle = newTitle;
                    el.style.animation = 'titleFadeIn 0.3s ease-in';
                }, 300);
            });
        },

        formatResetTime() {
            if (!this.limitInfo.resetAt) return '';
            
            const now = Math.floor(Date.now() / 1000);
            const diff = this.limitInfo.resetAt - now;
            
            if (diff <= 0) return 'скоро';
            
            const hours = Math.floor(diff / 3600);
            const minutes = Math.floor((diff % 3600) / 60);
            
            if (hours > 0) {
                return `${hours} ч ${minutes} мин`;
            }
            return `${minutes} мин`;
        },

        formatMessage(content, role) {
            // Для сообщений пользователя просто экранируем HTML
            if (role === 'user') {
                return this.escapeHtml(content);
            }
            
            // Для ассистента применяем markdown
            let html = this.escapeHtml(content);
            
            // Заголовки ### ## # (с эмодзи)
            html = html.replace(/^### (.+)$/gm, '<h3>$1</h3>');
            html = html.replace(/^## (.+)$/gm, '<h2>$1</h2>');
            html = html.replace(/^# (.+)$/gm, '<h1>$1</h1>');
            
            // Код в обратных кавычках `code`
            html = html.replace(/`([^`]+)`/g, '<code>$1</code>');
            
            // Жирный текст **text**
            html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
            
            // Нумерованные списки (обрабатываем ДО маркированных)
            html = html.replace(/^\d+\.\s+(.+)$/gm, '|||NUM|||$1|||/NUM|||');
            
            // Маркированные списки (* в начале строки)
            html = html.replace(/^\*\s+(.+)$/gm, '|||BULLET|||$1|||/BULLET|||');
            
            // Курсив *text* (только внутри строки, не в начале)
            html = html.replace(/([^\n])\*([^\s*].+?[^\s*])\*/g, '$1<em>$2</em>');
            
            // Заменяем маркеры на теги
            html = html.replace(/\|\|\|NUM\|\|\|/g, '<li class="numbered">');
            html = html.replace(/\|\|\|\/NUM\|\|\|/g, '</li>');
            html = html.replace(/\|\|\|BULLET\|\|\|/g, '<li class="bullet">');
            html = html.replace(/\|\|\|\/BULLET\|\|\|/g, '</li>');
            
            // Оборачиваем последовательные <li> в <ul> и <ol>
            html = html.replace(/(<li class="numbered">.*?<\/li>\n?)+/gs, '<ol>$&</ol>');
            html = html.replace(/(<li class="bullet">.*?<\/li>\n?)+/gs, '<ul>$&</ul>');
            
            // Удаляем классы
            html = html.replace(/class="numbered"/g, '');
            html = html.replace(/class="bullet"/g, '');
            
            // Параграфы
            html = html.replace(/\n\n+/g, '</p><p>');
            html = '<p>' + html + '</p>';
            
            // Убираем пустые параграфы
            html = html.replace(/<p>\s*<\/p>/g, '');
            html = html.replace(/<p>(<[hou])/g, '$1');
            html = html.replace(/(<\/[hou][^>]*>)<\/p>/g, '$1');
            
            return html;
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }));
});

