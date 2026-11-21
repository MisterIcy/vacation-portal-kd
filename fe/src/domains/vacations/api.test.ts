import { describe, it, expect, vi, beforeEach } from 'vitest';
import api from '@api/axios';
import { getVacations, createVacation, deleteVacation, approveVacation, rejectVacation } from './api';
import { vacationKeys } from './queryKeys';

vi.mock('@api/axios', () => ({
  default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}));

describe('vacations api', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('getVacations', () => {
    it('asks for the default list when no status is given, and gives back the requests', async () => {
      vi.mocked(api.get).mockResolvedValue({ data: [{ id: 1 }] });

      await expect(getVacations()).resolves.toEqual([{ id: 1 }]);

      expect(api.get).toHaveBeenCalledWith('/vacations', undefined);
    });

    it.each(['pending', 'approved', 'rejected', 'all'] as const)('asks for the %s status when given', async (status) => {
      vi.mocked(api.get).mockResolvedValue({ data: [] });

      await getVacations(status);

      expect(api.get).toHaveBeenCalledWith('/vacations', { params: { status } });
    });
  });

  it('creates a request', async () => {
    const body = { from: '2030-07-01', to: '2030-07-03', reason: 'Holiday' };
    vi.mocked(api.post).mockResolvedValue({ data: { id: 5, ...body } });

    await expect(createVacation(body)).resolves.toMatchObject({ id: 5 });

    expect(api.post).toHaveBeenCalledWith('/vacations', body);
  });

  it('cancels a request', async () => {
    vi.mocked(api.delete).mockResolvedValue({});

    await expect(deleteVacation(5)).resolves.toBeUndefined();

    expect(api.delete).toHaveBeenCalledWith('/vacations/5');
  });

  it('approves a request', async () => {
    vi.mocked(api.put).mockResolvedValue({ data: { id: 5, status: 'approved' } });

    await expect(approveVacation(5)).resolves.toMatchObject({ status: 'approved' });

    expect(api.put).toHaveBeenCalledWith('/vacations/5/approve');
  });

  it('rejects a request', async () => {
    vi.mocked(api.put).mockResolvedValue({ data: { id: 5, status: 'rejected' } });

    await expect(rejectVacation(5)).resolves.toMatchObject({ status: 'rejected' });

    expect(api.put).toHaveBeenCalledWith('/vacations/5/reject');
  });
});

describe('vacationKeys', () => {
  it('keeps every list under the same root, so one invalidation refreshes them all', () => {
    expect(vacationKeys.list().slice(0, 1)).toEqual(vacationKeys.all);
    expect(vacationKeys.list('pending').slice(0, 1)).toEqual(vacationKeys.all);
  });

  it('tells the lists apart', () => {
    const keys = [undefined, 'pending', 'approved', 'rejected', 'all'].map((status) =>
      JSON.stringify(vacationKeys.list(status as Parameters<typeof vacationKeys.list>[0])),
    );

    expect(new Set(keys).size).toBe(keys.length);
  });
});
