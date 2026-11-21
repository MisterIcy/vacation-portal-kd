import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { screen, fireEvent, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AxiosError, type AxiosResponse } from 'axios';
import { CreateVacation } from './CreateVacation';
import { render } from '@tests/test-utils';
import { createVacation } from '@domains/vacations/api';
import type { User } from '@domains/auth/types';

vi.mock('@domains/vacations/api', () => ({
  createVacation: vi.fn(),
}));

const employee: User = { id: 2, name: 'Employee User', email: 'employee@example.com', role: 'employee' };

const renderForm = () => render(<CreateVacation />, { authUser: employee });

const startDate = () => screen.getByLabelText('Start Date') as HTMLInputElement;
const endDate = () => screen.getByLabelText('End Date') as HTMLInputElement;
const reason = () => screen.getByLabelText('Reason') as HTMLTextAreaElement;

const pickDates = (from: string, to: string) => {
  fireEvent.change(startDate(), { target: { value: from } });
  fireEvent.change(endDate(), { target: { value: to } });
};

describe('CreateVacation', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    // Only the date is faked, so timers used by the testing library keep working.
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-07-10T12:00:00Z'));
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  describe('guidance before submitting', () => {
    it('does not offer a start date in the past', () => {
      renderForm();

      expect(startDate()).toHaveAttribute('min', '2026-07-10');
    });

    it('does not offer an end date before the start date', () => {
      renderForm();
      expect(endDate()).toHaveAttribute('min', '2026-07-10');

      fireEvent.change(startDate(), { target: { value: '2026-08-03' } });

      expect(endDate()).toHaveAttribute('min', '2026-08-03');
    });

    it('limits the end date to the longest vacation allowed (60 days in total)', () => {
      renderForm();

      fireEvent.change(startDate(), { target: { value: '2026-08-01' } });

      expect(endDate()).toHaveAttribute('max', '2026-09-29');
    });

    it('limits the reason to what can be stored and counts the characters', async () => {
      const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
      renderForm();

      expect(reason()).toHaveAttribute('maxLength', '255');
      expect(screen.getByText('0 / 255')).toBeInTheDocument();

      await user.type(reason(), 'Family trip');

      expect(screen.getByText('11 / 255')).toBeInTheDocument();
    });
  });

  describe('total days', () => {
    it('shows nothing until both dates are chosen', () => {
      renderForm();

      expect(screen.queryByText(/total days/i)).not.toBeInTheDocument();

      fireEvent.change(startDate(), { target: { value: '2026-07-20' } });
      expect(screen.queryByText(/total days/i)).not.toBeInTheDocument();
    });

    it('shows how many days the request covers, counting both ends', () => {
      renderForm();

      pickDates('2026-07-20', '2026-07-24');

      expect(screen.getByText('Total days: 5')).toBeInTheDocument();
    });

    it('counts a single day', () => {
      renderForm();

      pickDates('2026-07-20', '2026-07-20');

      expect(screen.getByText('Total days: 1')).toBeInTheDocument();
    });
  });

  describe('submitting', () => {
    it('sends the chosen dates and reason', async () => {
      vi.mocked(createVacation).mockResolvedValue({} as never);
      const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
      renderForm();

      pickDates('2026-07-20', '2026-07-24');
      await user.type(reason(), 'Family trip');
      await user.click(screen.getByRole('button', { name: 'Submit Request' }));

      await waitFor(() => expect(createVacation).toHaveBeenCalledTimes(1));
      expect(vi.mocked(createVacation).mock.calls[0][0]).toEqual({
        from: '2026-07-20',
        to: '2026-07-24',
        reason: 'Family trip',
      });
    });

    it("shows the server's message when a rule is broken", async () => {
      vi.mocked(createVacation).mockRejectedValue(
        new AxiosError('Request failed', 'ERR_BAD_REQUEST', undefined, undefined, {
          status: 400,
          data: {
            status_code: 400,
            reason_phrase: 'The requested dates overlap with another pending or approved vacation request.',
          },
        } as AxiosResponse)
      );
      const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
      renderForm();

      pickDates('2026-07-20', '2026-07-24');
      await user.type(reason(), 'Family trip');
      await user.click(screen.getByRole('button', { name: 'Submit Request' }));

      expect(await screen.findByRole('alert')).toHaveTextContent(/overlap with another/i);
    });
  });
});
