function ChannelEngineAjax() {
    this.baseUrl = this.getAjaxUrl();
}

ChannelEngineAjax.prototype.getAjaxUrl = function() {
    var currentUrl = window.location.href;
    var url = currentUrl.split('?')[0] + '?controller=AdminChannelEngine&ajax=1';

    var urlParts = currentUrl.split('token=');
    if (urlParts.length > 1) {
        var token = urlParts[1].split('&')[0];
        url += '&token=' + token;
    }

    console.log('ChannelEngine AJAX URL:', url);

    return url;
};

ChannelEngineAjax.prototype.post = function(data, callback, errorCallback) {
    var self = this;
    var options = {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data || {})
    };

    fetch(this.baseUrl, options)
        .then(function(response) {
            return response.json().then(function(data) {
                return {
                    ok: response.ok,
                    status: response.status,
                    statusText: response.statusText,
                    data: data
                };
            }).catch(function(parseError) {
                throw new Error('Invalid JSON response (HTTP ' + response.status + ')');
            });
        })
        .then(function(result) {
            if (result.ok) {
                console.log('ChannelEngine Response:', result.data);
                if (callback) {
                    callback(result.data);
                }
            } else {
                console.error('ChannelEngine API Error:', result.data);

                var errorMessage = 'Request failed';

                if (result.data && result.data.message) {
                    errorMessage = result.data.message;
                } else if (result.data && result.data.error) {
                    errorMessage = result.data.error;
                } else {
                    errorMessage = 'HTTP ' + result.status + ': ' + result.statusText;
                }

                if (errorCallback) {
                    errorCallback(errorMessage);
                }
            }
        })
        .catch(function(error) {
            console.error('ChannelEngine request failed:', error);

            var errorMessage = error.message || 'Request failed';

            if (error.name === 'TypeError' && error.message.includes('fetch')) {
                errorMessage = 'Network error - please check your connection';
            } else if (error.message.includes('JSON')) {
                errorMessage = 'Server returned invalid response format';
            }

            if (errorCallback) {
                errorCallback(errorMessage);
            }
        });
};

ChannelEngineAjax.prototype.get = function(data, callback, errorCallback) {
    this.makeRequest('GET', data, callback, errorCallback);
};

ChannelEngineAjax.prototype.put = function(data, callback, errorCallback) {
    this.makeRequest('PUT', data, callback, errorCallback);
};

ChannelEngineAjax.prototype.delete = function(data, callback, errorCallback) {
    this.makeRequest('DELETE', data, callback, errorCallback);
};

ChannelEngineAjax.prototype.makeRequest = function(method, data, callback, errorCallback) {
    var self = this;

    var options = {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };

    if (method !== 'GET' || (data && Object.keys(data).length > 0)) {
        options.body = JSON.stringify(data || {});
    }

    fetch(this.baseUrl, options)
        .then(function(response) {
            return response.json().then(function(responseData) {
                return {
                    ok: response.ok,
                    status: response.status,
                    statusText: response.statusText,
                    data: responseData
                };
            }).catch(function(parseError) {
                throw new Error('Invalid JSON response (HTTP ' + response.status + ')');
            });
        })
        .then(function(result) {
            if (result.ok) {
                console.log('ChannelEngine ' + method + ' Response:', result.data);
                if (callback) {
                    callback(result.data);
                }
            } else {
                console.error('ChannelEngine ' + method + ' Error:', result.data);

                var errorMessage = 'Request failed';

                if (result.data && result.data.message) {
                    errorMessage = result.data.message;
                } else if (result.data && result.data.error) {
                    errorMessage = result.data.error;
                } else {
                    errorMessage = 'HTTP ' + result.status + ': ' + result.statusText;
                }

                if (errorCallback) {
                    errorCallback(errorMessage);
                }
            }
        })
        .catch(function(error) {
            console.error('ChannelEngine ' + method + ' request failed:', error);

            var errorMessage = error.message || 'Request failed';

            if (error.name === 'TypeError' && error.message.includes('fetch')) {
                errorMessage = 'Network error - please check your connection';
            } else if (error.message.includes('JSON')) {
                errorMessage = 'Server returned invalid response format';
            }

            if (errorCallback) {
                errorCallback(errorMessage);
            }
        });
};

ChannelEngineAjax.prototype.connect = function(accountName, apiKey, callback, errorCallback) {
    this.post({
        action: 'connect',
        account_name: accountName,
        api_key: apiKey
    }, callback, errorCallback);
};

ChannelEngineAjax.prototype.disconnect = function(callback, errorCallback) {
    this.delete({
        action: 'disconnect'
    }, callback, errorCallback);
};

ChannelEngineAjax.prototype.getStatus = function(callback, errorCallback) {
    this.get({
        action: 'status'
    }, callback, errorCallback);
};

ChannelEngineAjax.prototype.testConnection = function(callback, errorCallback) {
    this.get({
        action: 'test'
    }, callback, errorCallback);
};

ChannelEngineAjax.prototype.updateSettings = function(settings, callback, errorCallback) {
    this.put({
        action: 'update_settings',
        settings: settings
    }, callback, errorCallback);
};