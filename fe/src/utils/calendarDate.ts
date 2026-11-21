/**
 * Helpers for calendar dates written as "YYYY-MM-DD", the format of <input type="date"> and
 * of the API. They work on whole days in UTC, so daylight saving changes cannot add or
 * remove a day.
 */

/** The longest vacation the server accepts, in total days (both ends counted). */
export const MAX_VACATION_DAYS = 60;

const MS_PER_DAY = 24 * 60 * 60 * 1000;

const toUtcMilliseconds = (isoDate: string): number => {
  const [year, month, day] = isoDate.split('-').map(Number);
  return Date.UTC(year, month - 1, day);
};

const fromUtcMilliseconds = (milliseconds: number): string => new Date(milliseconds).toISOString().slice(0, 10);

/**
 * Today's date in UTC. The server decides whether a vacation starts in the past by the UTC
 * date, so the form uses the same day instead of the browser's local one.
 */
export const todayUtc = (): string => fromUtcMilliseconds(Date.now());

export const addDays = (isoDate: string, days: number): string =>
  fromUtcMilliseconds(toUtcMilliseconds(isoDate) + days * MS_PER_DAY);

/**
 * Total days from the first to the last day, counting both. Null while either date is
 * missing or the range is reversed, so the caller has nothing to show yet.
 */
export const countDays = (from: string, to: string): number | null => {
  if (!from || !to) {
    return null;
  }

  const difference = Math.round((toUtcMilliseconds(to) - toUtcMilliseconds(from)) / MS_PER_DAY);
  return difference < 0 ? null : difference + 1;
};
