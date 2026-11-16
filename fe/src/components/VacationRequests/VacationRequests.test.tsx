import { describe, it, expect, vi, beforeEach } from 'vitest';
import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AxiosError, type AxiosResponse } from 'axios';
import { VacationRequests } from './VacationRequests';
import { render } from '@tests/test-utils';
import { getVacations, deleteVacation, approveVacation } from '@domains/vacations/api';
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

const buildVacation = (overrides: Partial<Vacation> = {}): Vacation => ({
  id: 1,
  user_id: 2,
  user: { id: 2, name: 'Employee User', email: 'employee@example.com', role: 'employee' },
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

const apiError = (reason: string) =>
  new AxiosError('Request failed', 'ERR_BAD_REQUEST', undefined, undefined, {
    status: 400,
    data: { status_code: 400, reason_phrase: reason },
  } as AxiosResponse);

describe('VacationRequests', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("lists the employee's requests with the wireframe's columns", async () => {
    vi.mocked(getVacations).mockResolvedValue([buildVacation()]);

    render(<VacationRequests />, { authUser: employee });

    expect(await screen.findByText('Summer holiday')).toBeInTheDocument();
    expect(screen.getByRole('columnheader', { name: 'Total days' })).toBeInTheDocument();
    expect(screen.queryByRole('columnheader', { name: 'Employee' })).not.toBeInTheDocument();
  });

  it('shows the given message when there are no requests', async () => {
    vi.mocked(getVacations).mockResolvedValue([]);

    render(<VacationRequests emptyMessage="Nothing requested yet." />, { authUser: employee });

    expect(await screen.findByText('Nothing requested yet.')).toBeInTheDocument();
  });

  it('shows the requests waiting for a decision to a manager, with the employee', async () => {
    vi.mocked(getVacations).mockResolvedValue([buildVacation()]);

    render(<VacationRequests />, { authUser: manager });

    expect(await screen.findByRole('columnheader', { name: 'Employee' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Approve' })).toBeInTheDocument();
  });

  it('deletes a request only after the employee confirms', async () => {
    vi.mocked(getVacations).mockResolvedValue([buildVacation({ id: 9 })]);
    vi.mocked(deleteVacation).mockResolvedValue(undefined);
    const confirm = vi.spyOn(window, 'confirm');
    const user = userEvent.setup();

    render(<VacationRequests />, { authUser: employee });
    const button = await screen.findByRole('button', { name: 'Delete' });

    confirm.mockReturnValueOnce(false);
    await user.click(button);
    expect(deleteVacation).not.toHaveBeenCalled();

    confirm.mockReturnValueOnce(true);
    await user.click(button);
    await vi.waitFor(() => expect(deleteVacation).toHaveBeenCalledTimes(1));
    expect(vi.mocked(deleteVacation).mock.calls[0][0]).toBe(9);

    confirm.mockRestore();
  });

  it("shows the server's message when a decision is refused", async () => {
    vi.mocked(getVacations).mockResolvedValue([buildVacation()]);
    vi.mocked(approveVacation).mockRejectedValue(apiError('Only pending vacations can be updated.'));
    const user = userEvent.setup();

    render(<VacationRequests />, { authUser: manager });
    await user.click(await screen.findByRole('button', { name: 'Approve' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Only pending vacations can be updated.');
  });

  it('says so when the requests cannot be loaded', async () => {
    vi.mocked(getVacations).mockRejectedValue(new AxiosError('Network Error', 'ERR_NETWORK'));

    render(<VacationRequests />, { authUser: employee });

    expect(await screen.findByRole('alert')).toHaveTextContent(/cannot reach the server/i);
  });
});
