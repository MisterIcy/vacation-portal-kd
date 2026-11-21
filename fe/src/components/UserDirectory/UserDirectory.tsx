import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getUsers, deleteUser } from '@domains/users/api';
import { UserTable } from '@components/UserTable';
import { ErrorMessage } from '@components/ErrorMessage';
import { useAuth } from '@hooks/useAuth';
import { getApiErrorMessage } from '@utils/apiError';

/**
 * Every registered user, with edit and delete (after confirmation). Shared by the
 * manager's home page and the user management page so both behave the same.
 */
export const UserDirectory = () => {
  const { isAuthenticated } = useAuth();
  const queryClient = useQueryClient();

  const { data: users, isLoading, isError, error } = useQuery({
    queryKey: ['users'],
    queryFn: getUsers,
    enabled: isAuthenticated,
  });

  const deleteMutation = useMutation({
    mutationFn: deleteUser,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['users'] }),
  });

  const errorMessage = isError
    ? getApiErrorMessage(error, 'Could not load the users. Please try again.')
    : deleteMutation.isError
      ? getApiErrorMessage(deleteMutation.error, 'Could not delete the user. Please try again.')
      : null;

  const handleDelete = (id: number, name: string) => {
    if (window.confirm(`Are you sure you want to delete user "${name}"?`)) {
      deleteMutation.mutate(id);
    }
  };

  return (
    <>
      <ErrorMessage message={errorMessage} />
      <UserTable
        users={users}
        isLoading={isLoading}
        isBusy={deleteMutation.isPending}
        onDelete={handleDelete}
      />
    </>
  );
};
