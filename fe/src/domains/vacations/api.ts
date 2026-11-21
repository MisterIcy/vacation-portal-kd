import api from '@api/axios';
import type { Vacation, CreateVacationRequest, VacationStatusFilter } from './types';

/**
 * Without a status the server answers by role: a manager gets the requests waiting for a
 * decision, an employee gets all of their own.
 */
export const getVacations = async (status?: VacationStatusFilter): Promise<Vacation[]> => {
  const { data } = await api.get<Vacation[]>('/vacations', status ? { params: { status } } : undefined);
  return data;
};

export const createVacation = async (vacation: CreateVacationRequest): Promise<Vacation> => {
  const { data } = await api.post<Vacation>('/vacations', vacation);
  return data;
};

export const deleteVacation = async (id: number): Promise<void> => {
  await api.delete(`/vacations/${id}`);
};

export const approveVacation = async (id: number): Promise<Vacation> => {
  const { data } = await api.put<Vacation>(`/vacations/${id}/approve`);
  return data;
};

export const rejectVacation = async (id: number): Promise<Vacation> => {
  const { data } = await api.put<Vacation>(`/vacations/${id}/reject`);
  return data;
};
