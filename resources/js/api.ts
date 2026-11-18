import type { Scan } from '@/types/models';

export class ApiError extends Error {
    constructor(
        /** The HTTP status, or 0 when the server couldn't be reached. */
        public readonly status: number,
        message: string,
    ) {
        super(message);
        this.name = 'ApiError';
    }
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}

/** Sends a request to the JSON API and returns the `data` of the response. */
async function request<T>(method: 'GET' | 'POST', path: string): Promise<T> {
    let response: Response;

    try {
        response = await fetch(`/api${path}`, {
            method,
            headers: { Accept: 'application/json' },
        });
    } catch {
        throw new ApiError(0, 'Could not reach the server. Check your connection and try again.');
    }

    const body: unknown = await response.json().catch(() => null);

    if (!response.ok) {
        const message =
            isRecord(body) && typeof body.message === 'string' && body.message !== ''
                ? body.message
                : `The request failed (HTTP ${response.status}).`;

        throw new ApiError(response.status, message);
    }

    if (!isRecord(body) || !('data' in body)) {
        throw new ApiError(response.status, 'The server sent an unexpected response.');
    }

    return body.data as T;
}

export const api = {
    runScan: () => request<Scan>('POST', '/scans'),
    listScans: () => request<Scan[]>('GET', '/scans'),
    getScan: (id: number) => request<Scan>('GET', `/scans/${id}`),
    getLatestScan: () => request<Scan>('GET', '/scans/latest'),
};

export function errorMessage(error: unknown): string {
    return error instanceof ApiError ? error.message : 'Something went wrong. Please try again.';
}
