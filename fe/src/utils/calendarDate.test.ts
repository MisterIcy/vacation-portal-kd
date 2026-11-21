import { describe, it, expect, afterEach, vi } from 'vitest';
import { todayUtc, addDays, countDays, MAX_VACATION_DAYS } from './calendarDate';

afterEach(() => {
  vi.useRealTimers();
});

describe('todayUtc', () => {
  it("is today's date in UTC, the day the server compares against", () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-07-10T23:30:00Z'));

    expect(todayUtc()).toBe('2026-07-10');
  });

  it('rolls over at UTC midnight', () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-07-11T00:00:01Z'));

    expect(todayUtc()).toBe('2026-07-11');
  });
});

describe('addDays', () => {
  it.each([
    ['2026-07-01', 0, '2026-07-01'],
    ['2026-07-01', 9, '2026-07-10'],
    ['2026-01-31', 1, '2026-02-01'],
    ['2026-12-31', 1, '2027-01-01'],
    ['2028-02-28', 1, '2028-02-29'],
    ['2026-03-01', -1, '2026-02-28'],
  ])('%s + %i days = %s', (start, days, expected) => {
    expect(addDays(start, days)).toBe(expected);
  });
});

describe('countDays', () => {
  it.each([
    ['2026-07-01', '2026-07-01', 1],
    ['2026-07-01', '2026-07-10', 10],
    ['2026-01-30', '2026-02-02', 4],
    ['2028-02-28', '2028-03-01', 3],
  ])('%s to %s is %i days, counting both ends', (from, to, expected) => {
    expect(countDays(from, to)).toBe(expected);
  });

  it('is null until both dates are filled in', () => {
    expect(countDays('', '2026-07-10')).toBeNull();
    expect(countDays('2026-07-01', '')).toBeNull();
  });

  it('is null for a reversed range', () => {
    expect(countDays('2026-07-10', '2026-07-01')).toBeNull();
  });

  it('is not affected by daylight saving changes', () => {
    // The clocks go forward in Europe on 29 March 2026.
    expect(countDays('2026-03-28', '2026-03-30')).toBe(3);
  });
});

it('uses the same maximum as the server', () => {
  expect(MAX_VACATION_DAYS).toBe(60);
});
