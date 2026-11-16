import { describe, it, expect } from 'vitest';
import { AxiosError, type AxiosResponse } from 'axios';
import { getApiErrorMessage, DEFAULT_ERROR_MESSAGE } from './apiError';

const httpError = (status: number, data: unknown) =>
  new AxiosError('Request failed', 'ERR_BAD_REQUEST', undefined, undefined, {
    status,
    data,
  } as AxiosResponse);

describe('getApiErrorMessage', () => {
  it("returns the API's reason phrase", () => {
    const error = httpError(400, { status_code: 400, reason_phrase: "Email 'a@example.com' is already in use." });

    expect(getApiErrorMessage(error)).toBe("Email 'a@example.com' is already in use.");
  });

  it('prefers the API message over the fallback', () => {
    const error = httpError(401, { status_code: 401, reason_phrase: 'Invalid credentials' });

    expect(getApiErrorMessage(error, 'Login failed')).toBe('Invalid credentials');
  });

  it('uses the fallback when the response has no usable message', () => {
    expect(getApiErrorMessage(httpError(500, ''), 'Could not save')).toBe('Could not save');
    expect(getApiErrorMessage(httpError(500, null), 'Could not save')).toBe('Could not save');
    expect(getApiErrorMessage(httpError(500, { reason_phrase: '  ' }), 'Could not save')).toBe('Could not save');
    expect(getApiErrorMessage(httpError(500, { reason_phrase: 42 }), 'Could not save')).toBe('Could not save');
  });

  it('explains when the server cannot be reached', () => {
    const networkError = new AxiosError('Network Error', 'ERR_NETWORK');

    expect(getApiErrorMessage(networkError)).toMatch(/cannot reach the server/i);
  });

  it('falls back for errors that did not come from the API', () => {
    expect(getApiErrorMessage(new Error('boom'))).toBe(DEFAULT_ERROR_MESSAGE);
    expect(getApiErrorMessage('boom', 'Custom')).toBe('Custom');
    expect(getApiErrorMessage(null)).toBe(DEFAULT_ERROR_MESSAGE);
  });
});
