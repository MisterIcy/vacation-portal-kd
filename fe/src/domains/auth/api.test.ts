import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import api from '@api/axios';
import { login, logout } from './api';
import { makeToken } from '@tests/tokens';

vi.mock('@api/axios', () => ({
  default: { post: vi.fn() },
}));

describe('auth api', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('login', () => {
    it('sends the credentials and reads who signed in from the token', async () => {
      const token = makeToken({ sub: 2, name: 'Zoë Müller', email: 'zoe@example.com', role: 'employee', exp: 2000000000 });
      vi.mocked(api.post).mockResolvedValue({ data: { token } });

      const result = await login({ email: 'zoe@example.com', password: 'secret123' });

      expect(api.post).toHaveBeenCalledWith('/auth/login', { email: 'zoe@example.com', password: 'secret123' });
      expect(result).toEqual({
        token,
        user: { id: 2, name: 'Zoë Müller', email: 'zoe@example.com', role: 'employee' },
      });
    });

    it('lets a refused login reach the caller', async () => {
      vi.mocked(api.post).mockRejectedValue(new Error('Request failed with status code 401'));

      await expect(login({ email: 'zoe@example.com', password: 'wrong' })).rejects.toThrow('401');
    });
  });

  describe('logout', () => {
    const originalLocation = window.location;

    afterEach(() => {
      Object.defineProperty(window, 'location', { value: originalLocation, configurable: true });
    });

    it('forgets the token and goes to the login page', () => {
      const location = { pathname: '/vacations', href: '/vacations' };
      Object.defineProperty(window, 'location', { value: location, configurable: true });
      localStorage.setItem('token', 'abc.def.ghi');

      logout();

      expect(localStorage.getItem('token')).toBeNull();
      expect(location.href).toBe('/login');
    });
  });
});
