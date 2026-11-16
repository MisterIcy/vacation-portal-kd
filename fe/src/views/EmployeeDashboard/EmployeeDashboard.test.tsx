import { describe, it, expect, vi, beforeEach } from 'vitest';
import { screen } from '@testing-library/react';
import { EmployeeDashboard } from './EmployeeDashboard';
import { render } from '@tests/test-utils';
import { getVacations } from '@domains/vacations/api';
import type { Vacation } from '@domains/vacations/types';
import type { User } from '@domains/auth/types';

vi.mock('@domains/vacations/api', () => ({
  getVacations: vi.fn(),
  deleteVacation: vi.fn(),
  approveVacation: vi.fn(),
  rejectVacation: vi.fn(),
}));

const employee: User = { id: 2, name: 'Employee User', email: 'employee@example.com', role: 'employee' };

const buildVacation = (overrides: Partial<Vacation>): Vacation => ({
  id: 1,
  user_id: 2,
  user: null,
  from: '2026-07-01',
  to: '2026-07-10',
  days: 10,
  reason: 'Summer holiday',
  status: 'pending',
  authorized_by: null,
  created_at: '2026-06-01T09:00:00+00:00',
  updated_at: '2026-06-01T09:00:00+00:00',
  ...overrides,
});

describe('EmployeeDashboard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('lands on the home page with the submitted requests and a way to create a new one', async () => {
    vi.mocked(getVacations).mockResolvedValue([
      buildVacation({ id: 1, reason: 'Summer holiday', status: 'approved' }),
      buildVacation({ id: 2, reason: 'Long weekend', status: 'pending', days: 3 }),
    ]);

    render(<EmployeeDashboard />, { authUser: employee });

    expect(await screen.findByText('Summer holiday')).toBeInTheDocument();
    expect(screen.getByText('Long weekend')).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'My vacation requests' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Request New Vacation' })).toHaveAttribute('href', '/vacations/new');
  });

  it('invites a new employee to submit a first request', async () => {
    vi.mocked(getVacations).mockResolvedValue([]);

    render(<EmployeeDashboard />, { authUser: employee });

    expect(await screen.findByText(/have not requested any vacation yet/i)).toBeInTheDocument();
  });
});
