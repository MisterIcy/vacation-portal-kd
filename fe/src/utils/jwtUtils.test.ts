import { describe, it, expect, vi, afterEach } from 'vitest';
import { decodeJWT, isTokenExpired, getTokenExpiration } from './jwtUtils';
import { makeToken, tokenExpiringIn, expiredToken } from '@tests/tokens';

describe('jwtUtils', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  describe('decodeJWT', () => {
    it('reads the payload of a token', () => {
      const token = makeToken({ sub: 7, role: 'manager', exp: 2000000000 });

      expect(decodeJWT(token)).toEqual({ sub: 7, role: 'manager', exp: 2000000000 });
    });

    it('reads names with characters outside ASCII', () => {
      const token = makeToken({ name: 'Zoë Müller – 山田' });

      expect(decodeJWT(token)?.name).toBe('Zoë Müller – 山田');
    });

    it('reads the URL-safe alphabet that JWTs use', () => {
      // These characters encode to "+" and "/" in plain base64, which JWTs write as "-" and "_".
      const payload = btoa(String.fromCharCode(...new TextEncoder().encode(JSON.stringify({ x: '???>>>' }))))
        .replace(/\+/g, '-')
        .replace(/\//g, '_');
      expect(payload).toMatch(/[-_]/);

      expect(decodeJWT(`header.${payload}.signature`)).toEqual({ x: '???>>>' });
    });

    it('gives null for something that is not a token', () => {
      vi.spyOn(console, 'error').mockImplementation(() => {});

      expect(decodeJWT('not-a-token')).toBeNull();
      expect(decodeJWT('a.!!!.c')).toBeNull();
      expect(decodeJWT('')).toBeNull();
    });
  });

  describe('isTokenExpired', () => {
    it('is false while the token has time left', () => {
      expect(isTokenExpired(tokenExpiringIn(3600))).toBe(false);
    });

    it('is true once the token has expired', () => {
      expect(isTokenExpired(expiredToken())).toBe(true);
    });

    it('is already true in the last 30 seconds, so a request does not go out with a token about to die', () => {
      expect(isTokenExpired(tokenExpiringIn(20))).toBe(true);
      expect(isTokenExpired(tokenExpiringIn(40))).toBe(false);
    });

    it('is true when the token carries no expiry or cannot be read', () => {
      vi.spyOn(console, 'error').mockImplementation(() => {});

      expect(isTokenExpired(makeToken({ sub: 1 }))).toBe(true);
      expect(isTokenExpired('garbage')).toBe(true);
    });
  });

  describe('getTokenExpiration', () => {
    it('gives the expiry as a date', () => {
      expect(getTokenExpiration(makeToken({ exp: 2000000000 }))).toEqual(new Date(2000000000 * 1000));
    });

    it('gives null when there is no expiry or the token cannot be read', () => {
      vi.spyOn(console, 'error').mockImplementation(() => {});

      expect(getTokenExpiration(makeToken({ sub: 1 }))).toBeNull();
      expect(getTokenExpiration('garbage')).toBeNull();
    });
  });
});
