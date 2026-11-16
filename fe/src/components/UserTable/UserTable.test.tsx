import { describe, it, expect, vi, beforeEach } from 'vitest';
import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { UserTable } from './UserTable';
import { render } from '@tests/test-utils';
import type { User } from '@domains/users/types';

const buildUser = (overrides: Partial<User> = {}): User => ({
  id: 2,
  name: 'Jane Doe',
  email: 'jane@example.com',
  employee_code: '1000002',
  role: 'employee',
  created_at: '2026-01-15T10:00:00+00:00',
  updated_at: '2026-01-15T10:00:00+00:00',
  ...overrides,
});

describe('UserTable', () => {
  const onDelete = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows name, email, code and role of every user', () => {
    render(
      <UserTable
        users={[
          buildUser(),
          buildUser({ id: 3, name: 'Max Manager', email: 'max@example.com', employee_code: '1000003', role: 'manager' }),
        ]}
        onDelete={onDelete}
      />
    );

    expect(screen.getByText('Jane Doe')).toBeInTheDocument();
    expect(screen.getByText('jane@example.com')).toBeInTheDocument();
    expect(screen.getByText('1000002')).toBeInTheDocument();
    expect(screen.getByText('Employee')).toBeInTheDocument();
    expect(screen.getByText('Max Manager')).toBeInTheDocument();
    expect(screen.getByText('Manager')).toBeInTheDocument();
  });

  it('offers edit and delete for each user, named so they can be told apart', () => {
    render(<UserTable users={[buildUser(), buildUser({ id: 3, name: 'John Smith' })]} onDelete={onDelete} />);

    expect(screen.getByRole('link', { name: 'Edit Jane Doe' })).toHaveAttribute('href', '/users/2/edit');
    expect(screen.getByRole('link', { name: 'Edit John Smith' })).toHaveAttribute('href', '/users/3/edit');
    expect(screen.getByRole('button', { name: 'Delete Jane Doe' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Delete John Smith' })).toBeInTheDocument();
  });

  it('asks to delete the user that was clicked', async () => {
    const user = userEvent.setup();
    render(<UserTable users={[buildUser(), buildUser({ id: 3, name: 'John Smith' })]} onDelete={onDelete} />);

    await user.click(screen.getByRole('button', { name: 'Delete John Smith' }));

    expect(onDelete).toHaveBeenCalledWith(3, 'John Smith');
  });

  it('disables deleting while one is in progress', () => {
    render(<UserTable users={[buildUser()]} isBusy onDelete={onDelete} />);

    expect(screen.getByRole('button', { name: 'Delete Jane Doe' })).toBeDisabled();
  });

  it('shows loading and empty states', () => {
    const { rerender } = render(<UserTable users={undefined} isLoading onDelete={onDelete} />);
    expect(screen.getByText('Loading users...')).toBeInTheDocument();

    rerender(<UserTable users={[]} onDelete={onDelete} />);
    expect(screen.getByText('No users found.')).toBeInTheDocument();
  });
});
