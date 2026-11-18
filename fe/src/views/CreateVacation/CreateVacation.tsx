/** @jsxImportSource @emotion/react */
import { useState, type FormEvent } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate, Link } from 'react-router-dom';
import { useTheme } from '@emotion/react';
import type { Theme } from '@styles/theme';
import { createVacation } from '@domains/vacations/api';
import { vacationKeys } from '@domains/vacations/queryKeys';
import { Layout } from '@components/Layout';
import { ErrorMessage } from '@components/ErrorMessage';
import { getApiErrorMessage } from '@utils/apiError';
import { todayUtc, addDays, countDays, MAX_VACATION_DAYS } from '@utils/calendarDate';
import { createVacationStyles } from './CreateVacation.styles';

/** The reason is stored in a column of this size. */
const MAX_REASON_LENGTH = 255;

export const CreateVacation = () => {
  const theme = useTheme() as Theme;
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');
  const [reason, setReason] = useState('');
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  // Guidance only: the server applies the same rules and has the last word.
  const today = todayUtc();
  const totalDays = countDays(startDate, endDate);

  const mutation = useMutation({
    mutationFn: createVacation,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: vacationKeys.all });
      navigate('/vacations');
    },
  });

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
    mutation.mutate({ from: startDate, to: endDate, reason });
  };

  return (
    <Layout>
      <div css={createVacationStyles(theme)}>
        <h2 className="create-vacation__title">Request Vacation</h2>

        <div className="create-vacation__card">
          <form className="create-vacation__form" onSubmit={handleSubmit}>
            <div className="create-vacation__input-group">
              <label className="create-vacation__label" htmlFor="startDate">Start Date</label>
              <input
                className="create-vacation__input"
                id="startDate"
                type="date"
                value={startDate}
                min={today}
                onChange={(e) => setStartDate(e.target.value)}
                required
              />
            </div>

            <div className="create-vacation__input-group">
              <label className="create-vacation__label" htmlFor="endDate">End Date</label>
              <input
                className="create-vacation__input"
                id="endDate"
                type="date"
                value={endDate}
                min={startDate || today}
                max={startDate ? addDays(startDate, MAX_VACATION_DAYS - 1) : undefined}
                onChange={(e) => setEndDate(e.target.value)}
                required
              />
            </div>

            {totalDays !== null && (
              <p className="create-vacation__total-days" aria-live="polite">
                Total days: {totalDays}
              </p>
            )}

            <div className="create-vacation__input-group">
              <label className="create-vacation__label" htmlFor="reason">Reason</label>
              <textarea
                className="create-vacation__textarea"
                id="reason"
                value={reason}
                maxLength={MAX_REASON_LENGTH}
                onChange={(e) => setReason(e.target.value)}
                required
                placeholder="Please provide a reason for your vacation request"
              />
              <div className="create-vacation__counter">
                {reason.length} / {MAX_REASON_LENGTH}
              </div>
            </div>

            {mutation.isError && (
              <ErrorMessage
                className="create-vacation__error-message"
                message={getApiErrorMessage(mutation.error, 'Failed to create vacation request. Please try again.')}
              />
            )}

            <div className="create-vacation__button-group">
              <Link
                to="/vacations"
                className="create-vacation__button create-vacation__button--cancel"
              >
                Cancel
              </Link>
              <button
                className="create-vacation__button create-vacation__button--submit"
                type="submit"
                disabled={mutation.isPending}
              >
                {mutation.isPending ? 'Submitting...' : 'Submit Request'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Layout>
  );
};
