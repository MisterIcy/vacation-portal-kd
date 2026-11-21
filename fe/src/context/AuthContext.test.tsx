import type { ReactNode } from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { AuthProvider } from './AuthContext';
import { useAuth } from '@hooks/useAuth';
import api from '@api/axios';
import type { User } from '@domains/auth/types';
import { expiredToken, tokenExpiringIn } from '@tests/tokens';

const employee: User = { id: 2, name: 'Employee User', email: 'employee@example.com', role: 'employee' };

const wrapper = ({ children }: { children: ReactNode }) => <AuthProvider>{children}</AuthProvider>;
const renderAuth = () => renderHook(() => useAuth(), { wrapper });

const store = (token: string, user: unknown = employee) => {
  localStorage.setItem('token', token);
  localStorage.setItem('user', typeof user === 'string' ? user : JSON.stringify(user));
};

describe('AuthProvider', () => {
  beforeEach(() => {
    localStorage.clear();
    delete api.defaults.headers.common['Authorization'];
  });

  describe('starting up', () => {
    it('has nobody signed in when nothing is stored', () => {
      const { result } = renderAuth();

      expect(result.current.user).toBeNull();
      expect(result.current.isAuthenticated).toBe(false);
    });

    it('picks up a stored session and sends its token with every request', () => {
      const token = tokenExpiringIn(3600);
      store(token);

      const { result } = renderAuth();

      expect(result.current.user).toEqual(employee);
      expect(result.current.isAuthenticated).toBe(true);
      expect(api.defaults.headers.common['Authorization']).toBe(`Bearer ${token}`);
    });

    it('forgets a stored session whose token has expired', () => {
      store(expiredToken());

      const { result } = renderAuth();

      expect(result.current.isAuthenticated).toBe(false);
      expect(localStorage.getItem('token')).toBeNull();
      expect(localStorage.getItem('user')).toBeNull();
      expect(api.defaults.headers.common['Authorization']).toBeUndefined();
    });

    it.each([
      ['unreadable JSON', '{not json'],
      ['the text "undefined"', 'undefined'],
      ['the text "null"', 'null'],
    ])('does not sign anyone in when the stored user is %s', (_name, storedUser) => {
      store(tokenExpiringIn(3600), storedUser);

      const { result } = renderAuth();

      expect(result.current.user).toBeNull();
      expect(result.current.isAuthenticated).toBe(false);
    });

    it('does not sign anyone in when there is a token but no stored user', () => {
      localStorage.setItem('token', tokenExpiringIn(3600));

      expect(renderAuth().result.current.isAuthenticated).toBe(false);
    });
  });

  describe('login and logout', () => {
    it('signs a user in and keeps the session for the next visit', () => {
      const token = tokenExpiringIn(3600);
      const { result } = renderAuth();

      act(() => result.current.login(token, employee));

      expect(result.current.user).toEqual(employee);
      expect(result.current.isAuthenticated).toBe(true);
      expect(localStorage.getItem('token')).toBe(token);
      expect(JSON.parse(localStorage.getItem('user') ?? 'null')).toEqual(employee);
      expect(api.defaults.headers.common['Authorization']).toBe(`Bearer ${token}`);
    });

    it('signs the user out and leaves nothing behind', () => {
      store(tokenExpiringIn(3600));
      const { result } = renderAuth();

      act(() => result.current.logout());

      expect(result.current.user).toBeNull();
      expect(result.current.isAuthenticated).toBe(false);
      expect(localStorage.getItem('token')).toBeNull();
      expect(localStorage.getItem('user')).toBeNull();
      expect(api.defaults.headers.common['Authorization']).toBeUndefined();
    });
  });

  describe('a session that runs out while the page is open', () => {
    const originalLocation = window.location;

    beforeEach(() => {
      vi.useFakeTimers();
    });

    afterEach(() => {
      vi.useRealTimers();
      vi.restoreAllMocks();
      Object.defineProperty(window, 'location', { value: originalLocation, configurable: true });
    });

    const stubLocation = (pathname: string) => {
      const location = { pathname, href: pathname };
      Object.defineProperty(window, 'location', { value: location, configurable: true });

      return location;
    };

    it('signs the user out and sends them to the login page', () => {
      const location = stubLocation('/vacations');
      store(tokenExpiringIn(100));
      const { result } = renderAuth();
      expect(result.current.isAuthenticated).toBe(true);

      act(() => {
        vi.advanceTimersByTime(120_000);
      });

      expect(result.current.isAuthenticated).toBe(false);
      expect(localStorage.getItem('token')).toBeNull();
      expect(location.href).toBe('/login');
    });

    it('does not redirect when the user is already on the login page', () => {
      const location = stubLocation('/login');
      store(tokenExpiringIn(100));
      renderAuth();

      act(() => {
        vi.advanceTimersByTime(120_000);
      });

      expect(location.href).toBe('/login');
      expect(localStorage.getItem('token')).toBeNull();
    });

    it('leaves a session that is still good alone', () => {
      const location = stubLocation('/vacations');
      store(tokenExpiringIn(3600));
      const { result } = renderAuth();

      act(() => {
        vi.advanceTimersByTime(120_000);
      });

      expect(result.current.isAuthenticated).toBe(true);
      expect(location.href).toBe('/vacations');
    });
  });
});

describe('useAuth', () => {
  it('refuses to be used outside the provider', () => {
    vi.spyOn(console, 'error').mockImplementation(() => {});

    expect(() => renderHook(() => useAuth())).toThrow('useAuth must be used within an AuthProvider');

    vi.restoreAllMocks();
  });
});
