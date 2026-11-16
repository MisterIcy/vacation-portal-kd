import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getVacations, deleteVacation, approveVacation, rejectVacation } from '@domains/vacations/api';
import { VacationTable } from '@components/VacationTable';
import { ErrorMessage } from '@components/ErrorMessage';
import { useAuth } from '@hooks/useAuth';
import { getApiErrorMessage } from '@utils/apiError';

interface VacationRequestsProps {
  emptyMessage?: string;
}

/**
 * The vacation requests visible to the signed-in user: an employee's own requests
 * (which they can cancel while pending) or, for a manager, the requests waiting
 * for a decision. Shared by the home page and the list page so both behave the same.
 */
export const VacationRequests = ({ emptyMessage }: VacationRequestsProps) => {
  const { user, isAuthenticated } = useAuth();
  const queryClient = useQueryClient();
  const isManager = user?.role === 'manager';

  const { data: vacations, isLoading, isError, error } = useQuery({
    queryKey: ['vacations'],
    queryFn: getVacations,
    enabled: isAuthenticated,
  });

  const refresh = () => queryClient.invalidateQueries({ queryKey: ['vacations'] });
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
      <ErrorMessage message={errorMessage} />
      <VacationTable
        vacations={vacations}
        isLoading={isLoading}
        isManager={isManager}
        isBusy={mutations.some((mutation) => mutation.isPending)}
        emptyMessage={emptyMessage}
        onDelete={handleDelete}
        onApprove={(id) => approveMutation.mutate(id)}
        onReject={(id) => rejectMutation.mutate(id)}
      />
    </>
  );
};
