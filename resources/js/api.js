
/*
|------------------------------------------------------------------------------
| TRYBE — shared API helper
|------------------------------------------------------------------------------
|
| OWNER: Member 1 (Nowmi). Do not fork this file. If you need something it
| does not do, ask — one shared helper is the point.
|
| Every call from the browser to /api/v1/... goes through here. It handles
| three things you would otherwise have to remember on every single fetch:
|
|   1. Accept: application/json
|      Without this Laravel returns an HTML error page instead of JSON, and
|      your JSON.parse() blows up with a confusing "Unexpected token <".
|
|   2. The CSRF token
|      Laravel rejects POST/PATCH/DELETE without it (419 error). We read it
|      from the XSRF-TOKEN cookie Laravel already set and send it back.
|
|   3. The session cookie
|      This is how the API knows who you are. No tokens to store.
|
| ---------------------------------------------------------------------------
| HOW TO USE IT FROM A BLADE PAGE
| ---------------------------------------------------------------------------
|
|   <script>
|   document.addEventListener('DOMContentLoaded', async () => {
|       try {
|           const res = await api.get('/api/v1/participants/7/reliability');
|           console.log(res.data.reliability_score);
|       } catch (e) {
|           console.error(e.message);
|       }
|   });
|   </script>
|
| The DOMContentLoaded wrapper is REQUIRED. This file is loaded as a module,
| which the browser defers until after the HTML is parsed. A bare inline
| script would run first and find `api` undefined.
|
*/

/** Read a single cookie by name. Returns null if it isn't set. */
function readCookie(name) {
    const parts = document.cookie.split('; ');

    for (const part of parts) {
        const eq = part.indexOf('=');

        if (eq > -1 && part.slice(0, eq) === name) {
            return decodeURIComponent(part.slice(eq + 1));
        }
    }

    return null;
}

/**
 * A failed request. Carries the HTTP status and, for 422 validation
 * failures, Laravel's field-by-field errors object.
 */
export class ApiError extends Error {
    constructor(message, status, errors = null) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.errors = errors;
    }
}

/** The one function every helper below calls. */
async function request(method, url, body = undefined) {
    const headers = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }

    // Laravel sets XSRF-TOKEN as a cookie; it wants it back as a header.
    const csrf = readCookie('XSRF-TOKEN');

    if (csrf) {
        headers['X-XSRF-TOKEN'] = csrf;
    }

    let response;

    try {
        response = await fetch(url, {
            method,
            headers,
            credentials: 'same-origin',
            body: body === undefined ? undefined : JSON.stringify(body),
        });
    } catch (networkFailure) {
        // fetch() only rejects when the request never completed at all —
        // no internet, server down, DNS failure. A 404 or 500 is a normal
        // resolved response and is handled below.
        throw new ApiError('Could not reach the server. Check your connection.', 0);
    }

    // 204 No Content has an empty body, so there is nothing to parse.
    if (response.status === 204) {
        return null;
    }

    let payload = null;

    try {
        payload = await response.json();
    } catch (notJson) {
        if (response.ok) {
            throw new ApiError('The server sent a reply we could not read.', response.status);
        }
    }

    if (response.ok) {
        return payload;
    }

    // ---- Everything below here is an error response -------------------

    if (response.status === 401) {
        throw new ApiError('Your session has expired. Please log in again.', 401);
    }

    if (response.status === 419) {
        throw new ApiError('Your session has expired. Please refresh the page.', 419);
    }

    if (response.status === 403) {
        throw new ApiError(payload?.message || 'You do not have permission to do that.', 403);
    }

    if (response.status === 422) {
        throw new ApiError(
            payload?.message || 'Please check the form and try again.',
            422,
            payload?.errors || null
        );
    }

    throw new ApiError(
        payload?.message || 'Something went wrong. Please try again.',
        response.status
    );
}

export const api = {
    get:    (url)       => request('GET', url),
    post:   (url, body) => request('POST', url, body ?? {}),
    put:    (url, body) => request('PUT', url, body ?? {}),
    patch:  (url, body) => request('PATCH', url, body ?? {}),
    delete: (url)       => request('DELETE', url),
};

// Exposed globally so Blade pages can use `api.get(...)` inside a plain
// <script> tag without needing their own module setup.
window.api = api;
window.ApiError = ApiError;
