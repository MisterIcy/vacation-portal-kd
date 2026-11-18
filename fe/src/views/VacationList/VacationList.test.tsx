import { describe, it, expect, vi, beforeEach } from 'vitest';
import { screen } from '@testing-library/react';
import { VacationList } from './VacationList';
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
const manager: User = { id: 1, name: 'Manager User', email: 'manager@example.com', role: 'manager' };

const vacation: Vacation = {
  id: 1,
  user_id: 2,
  user: { id: 2, name: 'Employee User', email: 'employee@example.com', role: 'employee' },
  from: '2030-07-01',
  to: '2030-07-10',
  days: 10,
  reason: 'Summer holiday',
  status: 'pending',
  authorized_by: null,
  created_at: '2030-06-01T09:00:00+00:00',
  updated_at: '2030-06-01T09:00:00+00:00',
};

describe('VacationList', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(getVacations).mockResolvedValue([vacation]);
  });

  it("titles an employee's page as their own requests and lists them", async () => {
    render(<VacationList />, { authUser: employee });

    expect(screen.getByRole('heading', { name: 'My Vacation Requests' })).toBeInTheDocument();
    expect(await screen.findByText('Summer holiday')).toBeInTheDocument();
  });

  it("titles a manager's page as the requests to decide and offers the status filter", async () => {
    render(<VacationList />, { authUser: manager });

    expect(screen.getByRole('heading', { name: 'Vacation Requests' })).toBeInTheDocument();
    expect(await screen.findByText('Summer holiday')).toBeInTheDocument();
    expect(screen.getByRole('group', { name: 'Filter by status' })).toBeInTheDocument();
  });
});
