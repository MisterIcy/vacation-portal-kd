/** Builds unsigned JWT-shaped strings for tests. The frontend only reads the payload, never verifies it. */

const base64Url = (value: unknown): string => {
  const bytes = new TextEncoder().encode(JSON.stringify(value));
  const binary = Array.from(bytes, (byte) => String.fromCharCode(byte)).join('');

  return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
};

export const makeToken = (payload: Record<string, unknown>): string =>
  [base64Url({ alg: 'HS256', typ: 'JWT' }), base64Url(payload), 'signature'].join('.');

const nowInSeconds = () => Math.floor(Date.now() / 1000);

/** A token that expires `seconds` from now (an hour by default). */
export const tokenExpiringIn = (seconds = 3600, payload: Record<string, unknown> = {}): string =>
  makeToken({ exp: nowInSeconds() + seconds, ...payload });

export const expiredToken = (): string => tokenExpiringIn(-60);
