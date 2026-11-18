import { describe, it, expect, vi, beforeEach } from 'vitest';
import api from '@api/axios';
import { getUsers, getUser, createUser, updateUser, deleteUser } from './api';

vi.mock('@api/axios', () => ({
  default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}));

describe('users api', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('lists the users', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: [{ id: 1 }, { id: 2 }] });

    await expect(getUsers()).resolves.toEqual([{ id: 1 }, { id: 2 }]);

    expect(api.get).toHaveBeenCalledWith('/users');
  });

  it('fetches one user', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { id: 3 } });

    await expect(getUser(3)).resolves.toEqual({ id: 3 });

    expect(api.get).toHaveBeenCalledWith('/users/3');
  });

  it('creates a user', async () => {
    const body = { name: 'Jane', email: 'jane@example.com', password: 'secret123', employee_code: '1000002', role: 'employee' as const };
    vi.mocked(api.post).mockResolvedValue({ data: { id: 9 } });

    await expect(createUser(body)).resolves.toEqual({ id: 9 });

    expect(api.post).toHaveBeenCalledWith('/users', body);
  });

  it('updates a user', async () => {
    vi.mocked(api.put).mockResolvedValue({ data: { id: 9, name: 'Jane Doe' } });

    await expect(updateUser(9, { name: 'Jane Doe' })).resolves.toMatchObject({ name: 'Jane Doe' });

    expect(api.put).toHaveBeenCalledWith('/users/9', { name: 'Jane Doe' });
  });

  it('deletes a user', async () => {
    vi.mocked(api.delete).mockResolvedValue({});

    await expect(deleteUser(9)).resolves.toBeUndefined();

    expect(api.delete).toHaveBeenCalledWith('/users/9');
  });
});
