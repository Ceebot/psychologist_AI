const createNewChat = () => {
    fetch('/chat/new', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.href = `/chat/${data.chatId}`;
            }
        })
        .catch(err => console.error(err));
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
                headers: { 'Content-Type': 'application/json' }
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
        createNewChat,

        init() {
            this.loadMessages();
        },

        loadMessages() {
            fetch(`/chat/${chatId}/messages`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.messages = data.messages;
                        this.$nextTick(() => this.scrollToBottom());
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

            fetch(`/chat/${chatId}/send`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: messageToSend })
            })
                .then(res => res.json())
                .then(data => {
                    this.loading = false;

                    if (data.success) {
                        this.messages.push(data.userMessage);
                        this.messages.push(data.assistantMessage);
                        
                        if (data.newTitle) {
                            this.updateChatTitle(data.newTitle);
                        }
                        
                        this.$nextTick(() => this.scrollToBottom());
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

        scrollToBottom() {
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
        }
    }));
});

