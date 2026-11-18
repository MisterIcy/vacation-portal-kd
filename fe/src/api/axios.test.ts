import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { AxiosError, type AxiosResponse, type InternalAxiosRequestConfig } from 'axios';
import api from './axios';

type Seen = { authorization?: unknown };

/** Replaces the network: the request goes through the interceptors, then ends here. */
const answerWith = (status: number, seen: Seen = {}) => {
  api.defaults.adapter = async (config: InternalAxiosRequestConfig) => {
    seen.authorization = config.headers.Authorization;
    const response = { data: {}, status, statusText: '', headers: {}, config } as AxiosResponse;

    if (status >= 400) {
      throw new AxiosError('Request failed', 'ERR_BAD_REQUEST', config, null, response);
    }

    return response;
  };

  return seen;
};

describe('api client', () => {
  const originalAdapter = api.defaults.adapter;
  const originalLocation = window.location;

  beforeEach(() => {
    localStorage.clear();
    delete api.defaults.headers.common['Authorization'];
  });

  afterEach(() => {
    api.defaults.adapter = originalAdapter;
    vi.restoreAllMocks();
    Object.defineProperty(window, 'location', { value: originalLocation, configurable: true });
  });

  const stubLocation = (pathname: string) => {
    const location = { pathname, href: pathname };
    Object.defineProperty(window, 'location', { value: location, configurable: true });

    return location;
  };

  describe('requests', () => {
    it('carry the stored token', async () => {
      localStorage.setItem('token', 'abc.def.ghi');
      const seen = answerWith(200);

      await api.get('/vacations');

      expect(seen.authorization).toBe('Bearer abc.def.ghi');
    });

    it('carry no authorization when nobody is signed in', async () => {
      const seen = answerWith(200);

      await api.get('/vacations');

      expect(seen.authorization).toBeUndefined();
    });
  });

  describe('a 401 answer', () => {
    it('ends the session and sends the user to the login page', async () => {
      const location = stubLocation('/vacations');
      localStorage.setItem('token', 'abc.def.ghi');
      localStorage.setItem('user', '{"id":1}');
      api.defaults.headers.common['Authorization'] = 'Bearer abc.def.ghi';
      answerWith(401);

      await expect(api.get('/vacations')).rejects.toMatchObject({ response: { status: 401 } });

      expect(localStorage.getItem('token')).toBeNull();
      expect(localStorage.getItem('user')).toBeNull();
      expect(api.defaults.headers.common['Authorization']).toBeUndefined();
      expect(location.href).toBe('/login');
    });

    it('does not redirect when the user is already on the login page', async () => {
      const location = stubLocation('/login');
      answerWith(401);

      await expect(api.post('/auth/login', {})).rejects.toBeDefined();

      expect(location.href).toBe('/login');
    });
  });

  describe.each([[400], [403], [404], [409], [500]])('a %i answer', (status) => {
    it('is passed on to the caller and does not end the session', async () => {
      const location = stubLocation('/vacations');
      localStorage.setItem('token', 'abc.def.ghi');
      answerWith(status);

      await expect(api.get('/vacations')).rejects.toMatchObject({ response: { status } });

      expect(localStorage.getItem('token')).toBe('abc.def.ghi');
      expect(location.href).toBe('/vacations');
    });
  });

  it('passes a network failure on to the caller', async () => {
    api.defaults.adapter = async () => {
      throw new AxiosError('Network Error', 'ERR_NETWORK');
    };

    await expect(api.get('/vacations')).rejects.toMatchObject({ code: 'ERR_NETWORK' });
  });
});
