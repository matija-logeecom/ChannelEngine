var ChannelEngine = {

    ajax: null,
    modal: null,

    init: function() {
        this.ajax = new ChannelEngineAjax();
        this.createModal();
        this.bindEvents();
    },

    bindEvents: function() {
        var self = this;

        document.addEventListener('click', function(event) {
            if (event.target === self.modal) {
                self.closeModal();
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && self.modal && self.modal.classList.contains('show')) {
                self.closeModal();
            }
        });
    },

    /**
     * Initialize modal (it's already in the DOM)
     */
    createModal: function() {
        this.modal = document.getElementById('channelengine-modal');

        if (!this.modal) {
            console.error('Modal not found in DOM');
            // Fallback: create a simple modal
            this.createFallbackModal();
        }
    },

    /**
     * Handle connect button click
     */
    handleConnect: function() {
        this.openModal();
    },

    /**
     * Open modal
     */
    openModal: function() {
        if (this.modal) {
            this.modal.classList.add('show');
        }
    },

    /**
     * Close modal
     */
    closeModal: function() {
        if (this.modal) {
            this.modal.classList.remove('show');
            document.getElementById('account_name').value = '';
            document.getElementById('api_key').value = '';
        }
    },

    /**
     * Handle login
     */
    handleLogin: function() {
        var accountName = document.getElementById('account_name').value;
        var apiKey = document.getElementById('api_key').value;
        var connectBtn = this.modal.querySelector('.channelengine-btn-primary');

        if (!accountName || !apiKey) {
            alert('Please fill in all fields');
            return;
        }

        // Show loading
        connectBtn.textContent = 'Connecting...';
        connectBtn.disabled = true;

        // Make request
        this.ajax.post('/connect', {
            account_name: accountName,
            api_key: apiKey
        })
            .then(response => {
                if (response.success) {
                    alert('Connected successfully!');
                    this.closeModal();
                } else {
                    alert('Connection failed: ' + (response.message || 'Unknown error'));
                }
            })
            .catch(error => {
                alert('Connection failed: ' + error.message);
            })
            .finally(() => {
                connectBtn.textContent = 'Connect';
                connectBtn.disabled = false;
            });
    }
};

// Initialize
ChannelEngine.init();