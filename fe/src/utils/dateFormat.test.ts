import { describe, it, expect, afterEach, vi } from 'vitest';
import { formatDate, formatDateTime } from './dateFormat';

const useTimeZone = (timeZone: string) => {
  vi.stubEnv('TZ', timeZone);
};

afterEach(() => {
  vi.unstubAllEnvs();
});

describe('formatDate', () => {
  describe('calendar dates (YYYY-MM-DD)', () => {
    // A vacation's first and last day have no time zone: 1 July is 1 July everywhere.
    it.each(['America/Los_Angeles', 'UTC', 'Europe/Athens', 'Pacific/Auckland'])(
      'shows the same day in %s',
      (timeZone) => {
        useTimeZone(timeZone);

        expect(formatDate('2026-07-01')).toBe(new Date(2026, 6, 1).toLocaleDateString());
      }
    );

    it('is not shifted back a day west of UTC (parsing as UTC would show 30 June)', () => {
      useTimeZone('America/Los_Angeles');

      expect(formatDate('2026-07-01')).not.toBe(new Date('2026-07-01').toLocaleDateString());
    });

    it('handles month and year boundaries', () => {
      useTimeZone('America/Los_Angeles');

      expect(formatDate('2027-01-01')).toBe(new Date(2027, 0, 1).toLocaleDateString());
      expect(formatDate('2028-02-29')).toBe(new Date(2028, 1, 29).toLocaleDateString());
    });
  });

  describe('instants (ISO-8601 with offset)', () => {
    it('shows the viewer\'s local date for the moment in time', () => {
      useTimeZone('America/Los_Angeles');

      // 02:00 UTC on 16 Nov is still the evening of 15 Nov in Los Angeles.
      expect(formatDate('2026-11-16T02:00:00+00:00')).toBe(new Date(2026, 10, 15).toLocaleDateString());
    });
  });

  describe('invalid input', () => {
    it('returns N/A for missing values', () => {
      expect(formatDate(null)).toBe('N/A');
      expect(formatDate(undefined)).toBe('N/A');
      expect(formatDate('')).toBe('N/A');
    });

    it('returns a placeholder for unparseable values', () => {
      expect(formatDate('not a date')).toBe('Invalid date');
      expect(formatDate('2026-02-31')).toBe('Invalid date');
    });
  });
});

describe('formatDateTime', () => {
  it('shows date and time of an instant in the viewer\'s time zone', () => {
    useTimeZone('UTC');

    expect(formatDateTime('2026-11-16T11:56:15+00:00')).toBe(new Date('2026-11-16T11:56:15Z').toLocaleString());
  });

  it('returns N/A for missing values', () => {
    expect(formatDateTime(null)).toBe('N/A');
  });
});
