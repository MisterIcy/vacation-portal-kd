import { useQuery } from '@tanstack/react-query';
import { getVacations } from '@domains/vacations/api';
import { vacationKeys } from '@domains/vacations/queryKeys';

/** How often a manager who leaves the page open learns about new requests. */
export const PENDING_REFETCH_INTERVAL_MS = 60_000;

/**
 * How many requests are waiting for the manager's decision. This is how managers are told about
 * new requests (email or push would need a mail/queue service; see the production notes).
 * It shares its query with the dashboard, so the two never disagree. Nothing is shown for zero
 * or when the count cannot be loaded: the badge is a hint, not a place for errors.
 */
export const PendingRequestsBadge = () => {
  const { data } = useQuery({
    queryKey: vacationKeys.list('pending'),
    queryFn: () => getVacations('pending'),
    refetchInterval: PENDING_REFETCH_INTERVAL_MS,
    refetchOnWindowFocus: true,
  });

  const count = data?.length ?? 0;
  if (count === 0) {
    return null;
  }

  return (
    <span className="navigation__badge">
      <span aria-hidden="true">{count}</span>
      <span className="navigation__visually-hidden">
        {count === 1 ? '1 pending request' : `${count} pending requests`}
      </span>
    </span>
  );
};
