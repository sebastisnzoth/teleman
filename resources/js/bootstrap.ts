import axios from 'axios';
// Set up axios defaults
window.axios = axios;

/**
 * Check if the current user is authenticated via Inertia page props.
 * Used by interceptors to avoid redirecting guest users on public pages.
 */
function isUserAuthenticated(): boolean {
    try {
        // Dynamic import to avoid circular dependency issues at module level
        const { router } = require('@inertiajs/react');
        return !!(router.page?.props as any)?.auth?.user;
    } catch {
        return false;
    }
}

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;

// Function to get CSRF token from meta tag or cookie
function getCsrfToken(): string | null {
    // First try meta tag
    const metaToken = document.head.querySelector('meta[name="csrf-token"]');
    if (metaToken) {
        return metaToken.getAttribute('content');
    }
    
    // Fallback to XSRF-TOKEN cookie
    const cookies = document.cookie.split(';');
    for (const cookie of cookies) {
        const [name, value] = cookie.trim().split('=');
        if (name === 'XSRF-TOKEN') {
            return decodeURIComponent(value);
        }
    }
    
    return null;
}

// Set CSRF token on initial load
const token = getCsrfToken();
if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
} else {
    console.error('CSRF token not found: https://laravel.com/docs/csrf#csrf-x-csrf-token');
}

// Add request interceptor to ensure CSRF token is always fresh
window.axios.interceptors.request.use(
    (config) => {
        const freshToken = getCsrfToken();
        if (freshToken) {
            config.headers['X-CSRF-TOKEN'] = freshToken;
        }
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// Add response interceptor to handle 419 CSRF token mismatch errors
window.axios.interceptors.response.use(
    (response) => response,
    async (error) => {
        const originalRequest = error.config;

        // Handle 419 CSRF Token Mismatch
        if (error.response?.status === 419 && !originalRequest._retry) {
            originalRequest._retry = true;

            try {
                // Get a fresh CSRF token by making a request to the sanctum/csrf-cookie endpoint
                await window.axios.get('/sanctum/csrf-cookie');
                
                // Update the token
                const freshToken = getCsrfToken();
                if (freshToken) {
                    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = freshToken;
                    originalRequest.headers['X-CSRF-TOKEN'] = freshToken;
                }

                // Retry the original request
                return window.axios(originalRequest);
            } catch (tokenError) {
                console.error('Failed to refresh CSRF token:', tokenError);

                // Only redirect to login if user was authenticated (session expired)
                // Guest users on public pages should not be redirected
                if (isUserAuthenticated() && !window.location.pathname.includes('/login')) {
                    window.location.href = '/login';
                }

                return Promise.reject(tokenError);
            }
        }

        // Handle 401 Unauthorized (session expired)
        // Only redirect if user was authenticated — guest users on public pages should not be redirected
        if (error.response?.status === 401 && isUserAuthenticated() && !window.location.pathname.includes('/login')) {
            window.location.href = '/login';
        }

        return Promise.reject(error);
    }
);

