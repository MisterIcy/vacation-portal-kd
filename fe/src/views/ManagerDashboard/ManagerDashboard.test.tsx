import { describe, it, expect, vi, beforeEach } from 'vitest';
import { screen, waitFor } from '@testing-library/react';
import { ManagerDashboard } from './ManagerDashboard';
import { render } from '@tests/test-utils';
import { getUsers } from '@domains/users/api';
import { getVacations } from '@domains/vacations/api';
import type { Vacation } from '@domains/vacations/types';
import type { User } from '@domains/users/types';
import type { User as AuthUser } from '@domains/auth/types';

vi.mock('@domains/users/api', () => ({
  getUsers: vi.fn(),
  deleteUser: vi.fn(),
}));
vi.mock('@domains/vacations/api', () => ({
  getVacations: vi.fn(),
}));

const manager: AuthUser = { id: 1, name: 'Manager User', email: 'manager@example.com', role: 'manager' };

const buildUser = (overrides: Partial<User>): User => ({
  id: 2,
  name: 'Jane Doe',
  email: 'jane@example.com',
  employee_code: '1000002',
  role: 'employee',
  created_at: '2026-01-15T10:00:00+00:00',
  updated_at: '2026-01-15T10:00:00+00:00',
  ...overrides,
});

describe('ManagerDashboard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(getVacations).mockResolvedValue([]);
  });

  it('lands on the home page with the registered users and a way to create a new one', async () => {
    vi.mocked(getUsers).mockResolvedValue([
      buildUser({ id: 1, name: 'Manager User', email: 'manager@example.com', role: 'manager' }),
      buildUser({ id: 2, name: 'Jane Doe', email: 'jane@example.com' }),
    ]);

    render(<ManagerDashboard />, { authUser: manager });

    expect(await screen.findByText('jane@example.com')).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Users' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Create user' })).toHaveAttribute('href', '/users/new');
    expect(screen.getByRole('link', { name: 'Edit Jane Doe' })).toBeInTheDocument();
  });

  it('counts the pending requests the server returns', async () => {
    vi.mocked(getUsers).mockResolvedValue([buildUser({})]);
    vi.mocked(getVacations).mockResolvedValue([
      { id: 1 } as Vacation,
      { id: 2 } as Vacation,
      { id: 3 } as Vacation,
    ]);

    render(<ManagerDashboard />, { authUser: manager });

    const card = (await screen.findByText('Pending Requests')).parentElement as HTMLElement;
    await waitFor(() => expect(card).toHaveTextContent('3'));
    // The navigation badge reads the same list, so the two always agree.
    expect(await screen.findByRole('link', { name: /3 pending requests/ })).toBeInTheDocument();
    expect(vi.mocked(getVacations).mock.calls[0][0]).toBe('pending');
  });

  it('keeps the summary and the link to the pending requests', async () => {
    vi.mocked(getUsers).mockResolvedValue([buildUser({})]);

    render(<ManagerDashboard />, { authUser: manager });

    expect(await screen.findByText('Total Users')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'View Requests' })).toHaveAttribute('href', '/vacations');
  });
});
