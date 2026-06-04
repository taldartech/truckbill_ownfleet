import axios from 'axios';

const api = axios.create({
    baseURL: '/',
    withCredentials: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

let cachedCsrfToken = null;
let refreshPromise = null;

function metaCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function syncMetaToken(token) {
    const meta = document.querySelector('meta[name="csrf-token"]');

    if (meta) {
        meta.setAttribute('content', token);
    }
}

export function currentCsrfToken() {
    return cachedCsrfToken || metaCsrfToken();
}

function expireCookie(name, path = '/') {
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';
    const hostParts = window.location.hostname.split('.');

    document.cookie = `${name}=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=${path}${secure}`;

    if (hostParts.length > 1) {
        const domain = hostParts.slice(-2).join('.');

        document.cookie = `${name}=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=${path};domain=.${domain}${secure}`;
    }
}

export function clearClientAuthState() {
    cachedCsrfToken = null;
    refreshPromise = null;

    if (typeof window !== 'undefined') {
        window.__AUTH_USER__ = null;
        window.__LOGIN_FLASH__ = {
            email: '',
            error: '',
            status: '',
            remember: false,
        };
    }

    try {
        sessionStorage.clear();
    } catch {
        // ignore
    }

    try {
        localStorage.clear();
    } catch {
        // ignore
    }

    document.cookie.split(';').forEach((part) => {
        const name = part.split('=')[0]?.trim();

        if (!name) {
            return;
        }

        if (name === 'XSRF-TOKEN' || name.startsWith('remember_web_')) {
            expireCookie(name);
        }
    });
}

export async function refreshCsrfToken() {
    if (!refreshPromise) {
        refreshPromise = axios
            .get('/app/csrf-token', {
                baseURL: '/',
                withCredentials: true,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
            .then((response) => {
                cachedCsrfToken = response.data?.token ?? '';

                if (cachedCsrfToken) {
                    syncMetaToken(cachedCsrfToken);
                }

                return cachedCsrfToken || metaCsrfToken();
            })
            .catch(() => metaCsrfToken())
            .finally(() => {
                refreshPromise = null;
            });
    }

    return refreshPromise;
}

function applyCsrfHeaders(config) {
    const token = currentCsrfToken();

    if (!token) {
        return config;
    }

    config.headers = config.headers ?? {};
    config.headers['X-CSRF-TOKEN'] = token;

    return config;
}

function appendTokenToData(config) {
    const token = currentCsrfToken();

    if (!token) {
        return config;
    }

    if (config.data instanceof FormData) {
        config.data.set('_token', token);
    } else if (typeof config.data === 'object' && config.data !== null) {
        config.data = { ...config.data, _token: token };
    }

    return config;
}

function isMutatingMethod(method) {
    return ['post', 'put', 'patch', 'delete'].includes((method ?? 'get').toLowerCase());
}

function requiresCsrfToken(url = '') {
    return url === '/login' || url === '/logout';
}

api.interceptors.request.use(async (config) => {
    if (!isMutatingMethod(config.method)) {
        return config;
    }

    const url = config.url ?? '';

    if (!requiresCsrfToken(url)) {
        return config;
    }

    await refreshCsrfToken();
    applyCsrfHeaders(config);
    appendTokenToData(config);

    return config;
});

api.interceptors.response.use(
    (response) => response,
    async (error) => {
        const original = error.config;

        if (error.response?.status === 419 && original && !original._csrfRetry) {
            original._csrfRetry = true;
            cachedCsrfToken = null;
            await refreshCsrfToken();

            if (original.headers) {
                delete original.headers['X-CSRF-TOKEN'];
                delete original.headers['X-XSRF-TOKEN'];
            }

            applyCsrfHeaders(original);
            appendTokenToData(original);

            return api.request(original);
        }

        return Promise.reject(error);
    },
);

/**
 * Submit login as a classic HTML form POST so the browser persists the session
 * cookie on redirect. JSON/XHR login can drop the session cookie in some setups
 * while the remember-me cookie still works.
 */
export async function login(email, password, remember = false) {
    const token = (await refreshCsrfToken()) || metaCsrfToken();

    if (!token) {
        throw new Error('Missing CSRF token');
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/login';
    form.style.display = 'none';

    const fields = {
        _token: token,
        email,
        password,
    };

    if (remember) {
        fields.remember = '1';
    }

    Object.entries(fields).forEach(([name, value]) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
}

export async function fetchCurrentUser() {
    const { data } = await api.get('/app/user');

    if (!data?.user) {
        throw new Error('Unauthenticated');
    }

    return data.user;
}

export async function logout() {
    try {
        const { data } = await api.post('/logout', { _token: currentCsrfToken() });

        return data;
    } finally {
        clearClientAuthState();
    }
}

export default api;
