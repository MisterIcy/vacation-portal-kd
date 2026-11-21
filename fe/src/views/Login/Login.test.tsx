import { describe, it, expect, vi, beforeEach } from 'vitest';
import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AxiosError, type AxiosResponse } from 'axios';
import { LoginPage } from './Login';
import { render } from '@tests/test-utils';
import { login } from '@domains/auth/api';

vi.mock('@domains/auth/api', () => ({
  login: vi.fn(),
}));

const mockNavigate = vi.fn();
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return { ...actual, useNavigate: () => mockNavigate };
});

const signIn = async (email: string, password: string) => {
  const user = userEvent.setup();
  await user.type(screen.getByLabelText('Email'), email);
  await user.type(screen.getByLabelText('Password'), password);
  await user.click(screen.getByRole('button', { name: 'Login' }));
};

describe('LoginPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("shows the server's message when the credentials are wrong", async () => {
    vi.mocked(login).mockRejectedValue(
      new AxiosError('Request failed', 'ERR_BAD_REQUEST', undefined, undefined, {
        status: 401,
        data: { status_code: 401, reason_phrase: 'Invalid credentials' },
      } as AxiosResponse)
    );
    render(<LoginPage />, { authUser: null });

    await signIn('manager@example.com', 'wrong-password');

    expect(await screen.findByRole('alert')).toHaveTextContent('Invalid credentials');
    expect(mockNavigate).not.toHaveBeenCalled();
  });

  it('says so when the server cannot be reached', async () => {
    vi.mocked(login).mockRejectedValue(new AxiosError('Network Error', 'ERR_NETWORK'));
    render(<LoginPage />, { authUser: null });

    await signIn('manager@example.com', 'managerpass123');

    expect(await screen.findByRole('alert')).toHaveTextContent(/cannot reach the server/i);
  });

  it('sends a manager to the manager dashboard and an employee to the employee dashboard', async () => {
    vi.mocked(login).mockResolvedValueOnce({
      token: 't',
      user: { id: 1, name: 'Manager', email: 'manager@example.com', role: 'manager' },
    });
    render(<LoginPage />, { authUser: null });
    await signIn('manager@example.com', 'managerpass123');

    await vi.waitFor(() => expect(mockNavigate).toHaveBeenCalledWith('/dashboard/manager'));
  });
});
