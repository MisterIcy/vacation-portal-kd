import type { VacationStatusFilter } from './types';

/**
 * Query keys for the vacation lists. Every key starts with `vacations`, so invalidating
 * `vacationKeys.all` refreshes every list (and the pending count) after a change.
 */
export const vacationKeys = {
  all: ['vacations'] as const,
  list: (status?: VacationStatusFilter) => ['vacations', 'list', status ?? 'default'] as const,
};
