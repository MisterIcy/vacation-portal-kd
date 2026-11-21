/** @jsxImportSource @emotion/react */
import { Link } from 'react-router-dom';
import { useTheme } from '@emotion/react';
import type { Theme } from '@styles/theme';
import type { User } from '@domains/users/types';
import { formatDate } from '@utils/dateFormat';
import { userTableStyles } from './UserTable.styles';

interface UserTableProps {
  /** Undefined while the first load is in progress. */
  users: User[] | undefined;
  isLoading?: boolean;
  /** Disables deleting while a delete is being processed. */
  isBusy?: boolean;
  emptyMessage?: string;
  onDelete: (id: number, name: string) => void;
}

const roleLabel = (role: User['role']) => role.charAt(0).toUpperCase() + role.slice(1);

export const UserTable = ({
  users,
  isLoading = false,
  isBusy = false,
  emptyMessage = 'No users found.',
  onDelete,
}: UserTableProps) => {
  const theme = useTheme() as Theme;

  if (isLoading || users === undefined) {
    return (
      <div css={userTableStyles(theme)}>
        <div className="user-table__loading-state">Loading users...</div>
      </div>
    );
  }

  if (users.length === 0) {
    return (
      <div css={userTableStyles(theme)}>
        <div className="user-table__empty-state">{emptyMessage}</div>
      </div>
    );
  }

  return (
    <div css={userTableStyles(theme)}>
      <table className="user-table__table">
        <thead className="user-table__table-head">
          <tr>
            <th className="user-table__th">Name</th>
            <th className="user-table__th">Email</th>
            <th className="user-table__th">Employee Code</th>
            <th className="user-table__th">Role</th>
            <th className="user-table__th">Created</th>
            <th className="user-table__th">Actions</th>
          </tr>
        </thead>
        <tbody>
          {users.map((user) => (
            <tr key={user.id}>
              <td className="user-table__td" data-label="Name">{user.name}</td>
              <td className="user-table__td" data-label="Email">{user.email}</td>
              <td className="user-table__td" data-label="Employee Code">{user.employee_code}</td>
              <td className="user-table__td" data-label="Role">
                <span className={`user-table__role-badge user-table__role-badge--${user.role}`}>
                  {roleLabel(user.role)}
                </span>
              </td>
              <td className="user-table__td" data-label="Created">{formatDate(user.created_at)}</td>
              <td className="user-table__td">
                <Link
                  to={`/users/${user.id}/edit`}
                  className="user-table__button user-table__button--edit"
                  aria-label={`Edit ${user.name}`}
                >
                  Edit
                </Link>
                <button
                  className="user-table__button user-table__button--delete"
                  onClick={() => onDelete(user.id, user.name)}
                  disabled={isBusy}
                  aria-label={`Delete ${user.name}`}
                >
                  Delete
                </button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
};
