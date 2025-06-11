class ChannelEngineAjax {

    constructor() {
        this.baseUrl = window.location.origin + '/admin/ajax/channelengine';
    }

    async get(url, headers = {}) {
        return this.request(url, {
            method: 'GET',
            headers: headers
        });
    }

    async post(url, data = {}, headers = {}) {
        return this.request(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                ...headers
            },
            body: JSON.stringify(data)
        });
    }

    async put(url, data = {}, headers = {}) {
        return this.request(url, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                ...headers
            },
            body: JSON.stringify(data)
        });
    }

    async delete(url, headers = {}) {
        return this.request(url, {
            method: 'DELETE',
            headers: headers
        });
    }

    async request(url, options = {}) {
        try {
            const response = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    ...options.headers
                },
                ...options
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            return await response.json();
        } catch (error) {
            console.error('AJAX request failed:', error);
            throw error;
        }
    }
}