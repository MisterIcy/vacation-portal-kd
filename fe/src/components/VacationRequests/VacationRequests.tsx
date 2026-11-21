import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getVacations, deleteVacation, approveVacation, rejectVacation } from '@domains/vacations/api';
import { vacationKeys } from '@domains/vacations/queryKeys';
import type { VacationStatusFilter } from '@domains/vacations/types';
import { StatusFilter } from '@components/StatusFilter';
import { VacationTable } from '@components/VacationTable';
import { ErrorMessage } from '@components/ErrorMessage';
import { useAuth } from '@hooks/useAuth';
import { getApiErrorMessage } from '@utils/apiError';

interface VacationRequestsProps {
  emptyMessage?: string;
}

const managerEmptyMessage = (filter: VacationStatusFilter) =>
  filter === 'all' ? 'No vacation requests found.' : `No ${filter} vacation requests.`;

/**
 * The vacation requests visible to the signed-in user: an employee's own requests
 * (which they can cancel while pending) or, for a manager, the requests waiting
 * for a decision, which they can narrow to approved, rejected or all. Shared by the home page and the list page so both behave the same.
 */
export const VacationRequests = ({ emptyMessage }: VacationRequestsProps) => {
  const { user, isAuthenticated } = useAuth();
  const queryClient = useQueryClient();
  const isManager = user?.role === 'manager';

  // Only managers choose a filter; an employee always gets their own requests, whatever their status.
  const [filter, setFilter] = useState<VacationStatusFilter>('pending');
  const status = isManager ? filter : undefined;

  const { data: vacations, isLoading, isError, error } = useQuery({
    queryKey: vacationKeys.list(status),
    queryFn: () => getVacations(status),
    enabled: isAuthenticated,
  });

  const refresh = () => queryClient.invalidateQueries({ queryKey: vacationKeys.all });
  const deleteMutation = useMutation({ mutationFn: deleteVacation, onSuccess: refresh });
  const approveMutation = useMutation({ mutationFn: approveVacation, onSuccess: refresh });
  const rejectMutation = useMutation({ mutationFn: rejectVacation, onSuccess: refresh });

  const mutations = [deleteMutation, approveMutation, rejectMutation];
  const actionError = mutations.find((mutation) => mutation.isError)?.error;
  const errorMessage = isError
    ? getApiErrorMessage(error, 'Could not load the vacation requests. Please try again.')
    : actionError
      ? getApiErrorMessage(actionError, 'The action could not be completed. Please try again.')
      : null;

  const handleDelete = (id: number) => {
    if (window.confirm('Are you sure you want to delete this vacation request?')) {
      deleteMutation.mutate(id);
    }
  };

  return (
    <>
      {isManager && <StatusFilter value={filter} onChange={setFilter} />}
      <ErrorMessage message={errorMessage} />
      <VacationTable
        vacations={vacations}
        isLoading={isLoading}
        isManager={isManager}
        isBusy={mutations.some((mutation) => mutation.isPending)}
        emptyMessage={isManager ? managerEmptyMessage(filter) : emptyMessage}
        onDelete={handleDelete}
        onApprove={(id) => approveMutation.mutate(id)}
        onReject={(id) => rejectMutation.mutate(id)}
      />
    </>
  );
};
