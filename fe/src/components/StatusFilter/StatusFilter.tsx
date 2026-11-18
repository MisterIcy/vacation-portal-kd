/** @jsxImportSource @emotion/react */
import { useTheme } from '@emotion/react';
import type { Theme } from '@styles/theme';
import type { VacationStatusFilter } from '@domains/vacations/types';
import { statusFilterStyles } from './StatusFilter.styles';

const OPTIONS: { value: VacationStatusFilter; label: string }[] = [
  { value: 'pending', label: 'Pending' },
  { value: 'approved', label: 'Approved' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'all', label: 'All' },
];

interface StatusFilterProps {
  value: VacationStatusFilter;
  onChange: (value: VacationStatusFilter) => void;
}

/** Narrows the request list by status. A group of toggle buttons, so it works with plain Tab and Enter. */
export const StatusFilter = ({ value, onChange }: StatusFilterProps) => {
  const theme = useTheme() as Theme;

  return (
    <div role="group" aria-label="Filter by status" css={statusFilterStyles(theme)}>
      {OPTIONS.map((option) => (
        <button
          key={option.value}
          type="button"
          className="status-filter__button"
          aria-pressed={value === option.value}
          onClick={() => onChange(option.value)}
        >
          {option.label}
        </button>
      ))}
    </div>
  );
};
