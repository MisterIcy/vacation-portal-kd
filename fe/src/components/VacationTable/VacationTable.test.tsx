import { describe, it, expect, vi, beforeEach } from 'vitest';
import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { VacationTable } from './VacationTable';
import { render } from '@tests/test-utils';
import type { Vacation } from '@domains/vacations/types';

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

const handlers = {
  onDelete: vi.fn(),
  onApprove: vi.fn(),
  onReject: vi.fn(),
};

describe('VacationTable', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('columns', () => {
    it('shows submitted date, requested dates, reason, total days and status for an employee', () => {
      render(<VacationTable vacations={[buildVacation()]} isManager={false} {...handlers} />);

      const headers = screen.getAllByRole('columnheader').map((header) => header.textContent);
      expect(headers).toEqual(['Date submitted', 'Dates requested', 'Reason', 'Total days', 'Status', 'Actions']);

      const row = screen.getAllByRole('row')[1];
      expect(within(row).getByText('Summer holiday')).toBeInTheDocument();
      expect(within(row).getByText('10')).toBeInTheDocument();
      expect(within(row).getByText('Pending')).toBeInTheDocument();
      expect(within(row).getByText(/–/)).toHaveTextContent(
        `${new Date(2026, 6, 1).toLocaleDateString()} – ${new Date(2026, 6, 10).toLocaleDateString()}`
      );
    });

    it('adds the employee column for managers', () => {
      render(<VacationTable vacations={[buildVacation()]} isManager {...handlers} />);

      expect(screen.getByRole('columnheader', { name: 'Employee' })).toBeInTheDocument();
      expect(screen.getByText('Employee User')).toBeInTheDocument();
    });

    it('shows the status as text for every status', () => {
      render(
        <VacationTable
          vacations={[
            buildVacation({ id: 1, status: 'pending' }),
            buildVacation({ id: 2, status: 'approved' }),
            buildVacation({ id: 3, status: 'rejected' }),
          ]}
          isManager={false}
          {...handlers}
        />
      );

      expect(screen.getByText('Pending')).toBeInTheDocument();
      expect(screen.getByText('Approved')).toBeInTheDocument();
      expect(screen.getByText('Rejected')).toBeInTheDocument();
    });
  });

  describe('actions for an employee', () => {
    it('can delete a pending request only', async () => {
      const user = userEvent.setup();
      render(
        <VacationTable
          vacations={[
            buildVacation({ id: 1, status: 'pending' }),
            buildVacation({ id: 2, status: 'approved' }),
            buildVacation({ id: 3, status: 'rejected' }),
          ]}
          isManager={false}
          {...handlers}
        />
      );

      const deleteButtons = screen.getAllByRole('button', { name: 'Delete' });
      expect(deleteButtons).toHaveLength(1);
      expect(screen.queryByRole('button', { name: 'Approve' })).not.toBeInTheDocument();
      expect(screen.queryByRole('button', { name: 'Reject' })).not.toBeInTheDocument();

      await user.click(deleteButtons[0]);
      expect(handlers.onDelete).toHaveBeenCalledWith(1);
    });
  });

  describe('actions for a manager', () => {
    it('can approve or reject a pending request but not delete it', async () => {
      const user = userEvent.setup();
      render(<VacationTable vacations={[buildVacation({ id: 7 })]} isManager {...handlers} />);

      expect(screen.queryByRole('button', { name: 'Delete' })).not.toBeInTheDocument();

      await user.click(screen.getByRole('button', { name: 'Approve' }));
      expect(handlers.onApprove).toHaveBeenCalledWith(7);

      await user.click(screen.getByRole('button', { name: 'Reject' }));
      expect(handlers.onReject).toHaveBeenCalledWith(7);
    });

    it('has no actions on a request that was already decided', () => {
      render(<VacationTable vacations={[buildVacation({ status: 'approved' })]} isManager {...handlers} />);

      expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });
  });

  it('disables the actions while one is being processed', () => {
    render(<VacationTable vacations={[buildVacation()]} isManager isBusy {...handlers} />);

    expect(screen.getByRole('button', { name: 'Approve' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Reject' })).toBeDisabled();
  });

  describe('states', () => {
    it('shows a loading message', () => {
      render(<VacationTable vacations={undefined} isLoading isManager={false} {...handlers} />);

      expect(screen.getByText('Loading vacations...')).toBeInTheDocument();
    });

    it('shows the empty message', () => {
      render(<VacationTable vacations={[]} isManager={false} emptyMessage="Nothing here yet." {...handlers} />);

      expect(screen.getByText('Nothing here yet.')).toBeInTheDocument();
      expect(screen.queryByRole('table')).not.toBeInTheDocument();
    });
  });
});
