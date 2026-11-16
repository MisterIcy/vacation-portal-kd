import { describe, it, expect, vi, beforeEach } from 'vitest';
import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AxiosError, type AxiosResponse } from 'axios';
import { UserList } from './UserList';
import { render } from '@tests/test-utils';
import { getUsers, deleteUser } from '@domains/users/api';
import type { User } from '@domains/users/types';
import type { User as AuthUser } from '@domains/auth/types';

vi.mock('@domains/users/api', () => ({
  getUsers: vi.fn(),
  deleteUser: vi.fn(),
}));

const manager: AuthUser = { id: 1, name: 'Manager User', email: 'manager@example.com', role: 'manager' };

const buildUser = (overrides: Partial<User> = {}): User => ({
  id: 2,
  name: 'Jane Doe',
  email: 'jane@example.com',
  employee_code: '1000002',
  role: 'employee',
  created_at: '2026-01-15 10:00:00',
  updated_at: '2026-01-15 10:00:00',
  ...overrides,
});

describe('UserList', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows a row with name and email for every user returned by the API', async () => {
    vi.mocked(getUsers).mockResolvedValue([
      buildUser(),
      buildUser({ id: 3, name: 'John Smith', email: 'john@example.com' }),
    ]);

    render(<UserList />, { authUser: manager });

    expect(await screen.findByText('Jane Doe')).toBeInTheDocument();
    expect(screen.getByText('jane@example.com')).toBeInTheDocument();
    expect(screen.getByText('John Smith')).toBeInTheDocument();
    expect(screen.getByText('john@example.com')).toBeInTheDocument();
  });

  it('shows an empty state when there are no users', async () => {
    vi.mocked(getUsers).mockResolvedValue([]);

    render(<UserList />, { authUser: manager });

    expect(await screen.findByText('No users found.')).toBeInTheDocument();
  });

  it('deletes a user only after the manager confirms', async () => {
    vi.mocked(getUsers).mockResolvedValue([buildUser()]);
    vi.mocked(deleteUser).mockResolvedValue(undefined);
    const confirm = vi.spyOn(window, 'confirm');
    const user = userEvent.setup();

    render(<UserList />, { authUser: manager });
    const deleteButton = await screen.findByRole('button', { name: 'Delete Jane Doe' });

    confirm.mockReturnValueOnce(false);
    await user.click(deleteButton);
    expect(deleteUser).not.toHaveBeenCalled();

    confirm.mockReturnValueOnce(true);
    await user.click(deleteButton);
    await waitFor(() => expect(deleteUser).toHaveBeenCalledTimes(1));
    // React Query passes extra context after the variable; only the id matters here.
    expect(vi.mocked(deleteUser).mock.calls[0][0]).toBe(2);

    confirm.mockRestore();
  });

  it("shows the server's message when a delete is refused", async () => {
    vi.mocked(getUsers).mockResolvedValue([buildUser()]);
    vi.mocked(deleteUser).mockRejectedValue(
      new AxiosError('Request failed', 'ERR_BAD_REQUEST', undefined, undefined, {
        status: 400,
        data: { status_code: 400, reason_phrase: 'You cannot delete your own account' },
      } as AxiosResponse)
    );
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    const user = userEvent.setup();

    render(<UserList />, { authUser: manager });
    await user.click(await screen.findByRole('button', { name: 'Delete Jane Doe' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('You cannot delete your own account');
  });
});
