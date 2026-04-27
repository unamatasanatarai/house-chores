import { ui } from './utils.js';

const API_BASE = '/api';

export const api = {
    async request(endpoint, options = {}) {
        const token = localStorage.getItem('choreloop_user_id');
        
        const defaultHeaders = {
            'Content-Type': 'application/json',
        };

        if (token) {
            defaultHeaders['Authorization'] = `Bearer ${token}`;
        }

        const config = {
            ...options,
            headers: {
                ...defaultHeaders,
                ...options.headers,
            },
        };

        try {
            const response = await fetch(`${API_BASE}${endpoint}`, config);
            
            // Reconnected successfully if we reached here
            ui.hideOfflineOverlay();

            if (response.status === 401) {
                localStorage.removeItem('choreloop_user_id');
                window.dispatchEvent(new CustomEvent('unauthorized'));
                return;
            }

            const result = await response.json();

            if (!response.ok) {
                throw result.error || { message: 'An unexpected error occurred' };
            }

            return result;
        } catch (error) {
            // Network Failure (DNS, Timeout, etc)
            if (error instanceof TypeError || error.name === 'AbortError') {
                ui.showOfflineOverlay();
            }
            
            console.error(`API Error [${endpoint}]:`, error);
            throw error;
        }
    },

    get(endpoint) {
        return this.request(endpoint, { method: 'GET' });
    },

    post(endpoint, data) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data),
        });
    },

    put(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data),
        });
    }
};
