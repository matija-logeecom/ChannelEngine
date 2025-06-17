/**
 * Simple ChannelEngine Admin Interface
 */
var ChannelEngine = {
    ajax: null,
    modal: null,

    init: function() {
        console.log('ChannelEngine: Initializing...');

        this.ajax = new ChannelEngineAjax();
        this.findModal();
        this.bindEvents();

        console.log('ChannelEngine: Initialization complete');
    },

    findModal: function() {
        this.modal = document.getElementById('channelengine-modal');

        if (this.modal) {
            console.log('ChannelEngine: Modal found');
        } else {
            console.error('ChannelEngine: Modal NOT found');
            var elements = document.querySelectorAll('[id*="channelengine"]');
            console.log('Elements with channelengine in ID:', elements);
        }
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

    handleConnect: function() {
        console.log('ChannelEngine: handleConnect called');

        if (!this.modal) {
            console.error('ChannelEngine: No modal found, cannot open');
            alert('Error: Modal not found. Please refresh the page.');
            return;
        }

        this.openModal();
    },

    openModal: function() {
        console.log('ChannelEngine: Opening modal');

        if (this.modal) {
            this.modal.classList.add('show');

            var accountInput = document.getElementById('account_name');
            if (accountInput) {
                accountInput.focus();
            }
        }
    },

    closeModal: function() {
        console.log('ChannelEngine: Closing modal');

        if (this.modal) {
            this.modal.classList.remove('show');
            this.clearForm();
        }
    },

    clearForm: function() {
        var accountInput = document.getElementById('account_name');
        var apiKeyInput = document.getElementById('api_key');

        if (accountInput) accountInput.value = '';
        if (apiKeyInput) apiKeyInput.value = '';
    },

    handleLogin: function() {
        console.log('ChannelEngine: handleLogin called');

        var accountInput = document.getElementById('account_name');
        var apiKeyInput = document.getElementById('api_key');
        var connectBtn = this.modal ? this.modal.querySelector('.channelengine-btn-primary') : null;

        if (!accountInput || !apiKeyInput) {
            alert('Form inputs not found');
            return;
        }

        var accountName = accountInput.value.trim();
        var apiKey = apiKeyInput.value.trim();

        if (!accountName || !apiKey) {
            alert('Please fill in all fields');
            return;
        }

        if (connectBtn) {
            connectBtn.textContent = 'Connecting...';
            connectBtn.disabled = true;
        }

        var self = this;

        this.ajax.connect(accountName, apiKey,
            function(response) {
                if (response && response.success) {
                    alert('Connected successfully!');
                    self.closeModal();

                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    alert('Connection failed: ' + (response.message || 'Unknown error'));
                }

                if (connectBtn) {
                    connectBtn.textContent = 'Connect';
                    connectBtn.disabled = false;
                }
            },
            function(error) {
                alert('Connection failed: ' + error);

                if (connectBtn) {
                    connectBtn.textContent = 'Connect';
                    connectBtn.disabled = false;
                }
            }
        );
    },

    handleSync: function() {
        console.log('ChannelEngine: handleSync called');

        var syncButton = document.querySelector('.sync-button');
        if (syncButton) {
            syncButton.textContent = 'Synchronizing...';
            syncButton.disabled = true;
        }

        var self = this;

        this.ajax.sync(
            function(response) {
                if (response && response.success) {
                    alert('Synchronization completed successfully!');
                    // Optionally reload the page to refresh sync status
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    alert('Synchronization failed: ' + (response.message || 'Unknown error'));
                }

                if (syncButton) {
                    syncButton.textContent = 'Synchronize';
                    syncButton.disabled = false;
                }
            },
            function(error) {
                alert('Synchronization failed: ' + error);

                if (syncButton) {
                    syncButton.textContent = 'Synchronize';
                    syncButton.disabled = false;
                }
            }
        );
    },

    handleDisconnect: function() {
        if (!confirm('Are you sure you want to disconnect from ChannelEngine?')) {
            return;
        }

        var self = this;

        this.ajax.disconnect(
            function(response) {
                if (response && response.success) {
                    alert('Disconnected successfully!');
                    window.location.reload();
                } else {
                    alert('Disconnect failed: ' + (response.message || 'Unknown error'));
                }
            },
            function(error) {
                alert('Disconnect failed: ' + error);
            }
        );
    },

    updateSyncStatus: function() {
        var self = this;

        this.ajax.getSyncStatus(
            function(response) {
                if (response && response.success && response.data) {
                    self.displaySyncStatus(response.data);
                }
            },
            function(error) {
                console.error('Failed to get sync status:', error);
            }
        );
    },

    displaySyncStatus: function(statusData) {
        var statusElements = {
            done: document.querySelector('.status-done'),
            progress: document.querySelector('.status-progress'),
            error: document.querySelector('.status-error')
        };

        // Reset all statuses
        Object.values(statusElements).forEach(function(element) {
            if (element) {
                element.style.fontWeight = 'normal';
                element.style.textDecoration = 'none';
            }
        });

        // Highlight current status
        var currentStatus = statusData.status || 'done';
        var currentElement = statusElements[currentStatus === 'in_progress' ? 'progress' : currentStatus];

        if (currentElement) {
            currentElement.style.fontWeight = 'bold';
            currentElement.style.textDecoration = 'underline';
        }
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        ChannelEngine.init();

        // Update sync status on sync page load
        if (document.querySelector('.sync-status-container')) {
            ChannelEngine.updateSyncStatus();
        }
    });
} else {
    ChannelEngine.init();

    // Update sync status on sync page load
    if (document.querySelector('.sync-status-container')) {
        ChannelEngine.updateSyncStatus();
    }
}