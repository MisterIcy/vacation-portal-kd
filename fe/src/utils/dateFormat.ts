type DateInput = string | null | undefined;

const CALENDAR_DATE = /^(\d{4})-(\d{2})-(\d{2})$/;

/**
 * Parses a calendar date ("YYYY-MM-DD") as that day in the viewer's own calendar.
 * `new Date('2026-07-01')` would instead mean midnight UTC, which is still 30 June
 * west of UTC. Returns null for impossible dates such as 2026-02-31.
 */
function parseCalendarDate(value: string): Date | null {
  const match = CALENDAR_DATE.exec(value);
  if (!match) {
    return null;
  }

  const [year, month, day] = [Number(match[1]), Number(match[2]), Number(match[3])];
  const date = new Date(year, month - 1, day);

  const isRealDay = date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day;
  return isRealDay ? date : null;
}

/** Calendar dates are parsed without a time zone, instants (ISO-8601 with offset) as a moment in time. */
function parse(value: string): Date | null {
  if (CALENDAR_DATE.test(value)) {
    return parseCalendarDate(value);
  }

  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? null : date;
}

/**
 * Formats a vacation date ("YYYY-MM-DD", shown as is in every time zone) or an
 * instant such as a creation time (shown as the viewer's local date).
 */
export function formatDate(dateInput: DateInput): string {
  if (!dateInput) {
    return 'N/A';
  }

  const date = parse(dateInput);
  return date ? date.toLocaleDateString() : 'Invalid date';
}

/** Formats an instant as the viewer's local date and time. A calendar date has no time, so only the date is shown. */
export function formatDateTime(dateInput: DateInput): string {
  if (!dateInput) {
    return 'N/A';
  }

  const date = parse(dateInput);
  if (!date) {
    return 'Invalid date';
  }

  return CALENDAR_DATE.test(dateInput) ? date.toLocaleDateString() : date.toLocaleString();
}
