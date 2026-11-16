/** @jsxImportSource @emotion/react */
import { useTheme } from '@emotion/react';
import type { Theme } from '@styles/theme';
import type { Vacation } from '@domains/vacations/types';
import { formatDate } from '@utils/dateFormat';
import { vacationTableStyles } from './VacationTable.styles';

interface VacationTableProps {
  /** Undefined while the first load is in progress. */
  vacations: Vacation[] | undefined;
  isLoading?: boolean;
  /** Managers see who asked and can decide; employees can cancel their own pending requests. */
  isManager: boolean;
  /** Disables the actions while one of them is being processed. */
  isBusy?: boolean;
  emptyMessage?: string;
  onDelete: (id: number) => void;
  onApprove: (id: number) => void;
  onReject: (id: number) => void;
}

const statusLabel = (status: Vacation['status']) => status.charAt(0).toUpperCase() + status.slice(1);

export const VacationTable = ({
  vacations,
  isLoading = false,
  isManager,
  isBusy = false,
  emptyMessage = 'No vacation requests found.',
  onDelete,
  onApprove,
  onReject,
}: VacationTableProps) => {
  const theme = useTheme() as Theme;

  if (isLoading || vacations === undefined) {
    return (
      <div css={vacationTableStyles(theme)}>
        <div className="vacation-table__loading-state">Loading vacations...</div>
      </div>
    );
  }

  if (vacations.length === 0) {
    return (
      <div css={vacationTableStyles(theme)}>
        <div className="vacation-table__empty-state">{emptyMessage}</div>
      </div>
    );
  }

  return (
    <div css={vacationTableStyles(theme)}>
      <table className="vacation-table__table">
        <thead className="vacation-table__table-head">
          <tr>
            {isManager && <th className="vacation-table__th">Employee</th>}
            <th className="vacation-table__th">Date submitted</th>
            <th className="vacation-table__th">Dates requested</th>
            <th className="vacation-table__th">Reason</th>
            <th className="vacation-table__th">Total days</th>
            <th className="vacation-table__th">Status</th>
            <th className="vacation-table__th">Actions</th>
          </tr>
        </thead>
        <tbody>
          {vacations.map((vacation) => (
            <tr key={vacation.id}>
              {isManager && (
                <td className="vacation-table__td" data-label="Employee">
                  {vacation.user?.name || 'Unknown'}
                </td>
              )}
              <td className="vacation-table__td" data-label="Date submitted">
                {formatDate(vacation.created_at)}
              </td>
              <td className="vacation-table__td" data-label="Dates requested">
                {formatDate(vacation.from)} – {formatDate(vacation.to)}
              </td>
              <td className="vacation-table__td" data-label="Reason">
                {vacation.reason}
              </td>
              <td className="vacation-table__td" data-label="Total days">
                {vacation.days}
              </td>
              <td className="vacation-table__td" data-label="Status">
                <span className={`vacation-table__status-badge vacation-table__status-badge--${vacation.status}`}>
                  {statusLabel(vacation.status)}
                </span>
              </td>
              <td className="vacation-table__td">
                {vacation.status === 'pending' && isManager && (
                  <>
                    <button
                      className="vacation-table__button vacation-table__button--approve"
                      onClick={() => onApprove(vacation.id)}
                      disabled={isBusy}
                    >
                      Approve
                    </button>
                    <button
                      className="vacation-table__button vacation-table__button--reject"
                      onClick={() => onReject(vacation.id)}
                      disabled={isBusy}
                    >
                      Reject
                    </button>
                  </>
                )}
                {vacation.status === 'pending' && !isManager && (
                  <button
                    className="vacation-table__button vacation-table__button--delete"
                    onClick={() => onDelete(vacation.id)}
                    disabled={isBusy}
                  >
                    Delete
                  </button>
                )}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
};
