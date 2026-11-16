import { describe, it, expect, vi, beforeEach } from 'vitest';
import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Routes, Route } from 'react-router-dom';
import { AxiosError, type AxiosResponse } from 'axios';
import { UserForm } from './UserForm';
import { render } from '@tests/test-utils';
import { getUser, updateUser } from '@domains/users/api';
import type { User } from '@domains/users/types';
import type { User as AuthUser } from '@domains/auth/types';

vi.mock('@domains/users/api', () => ({
  getUser: vi.fn(),
  createUser: vi.fn(),
  updateUser: vi.fn(),
}));

const manager: AuthUser = { id: 1, name: 'Manager User', email: 'manager@example.com', role: 'manager' };

const existingUser: User = {
  id: 5,
  name: 'Jane Doe',
  email: 'jane@example.com',
  employee_code: '1000005',
  role: 'employee',
  created_at: '2026-01-15 10:00:00',
  updated_at: '2026-01-15 10:00:00',
};

const renderEditForm = () =>
  render(
    <Routes>
      <Route path="/users/:id/edit" element={<UserForm />} />
    </Routes>,
    { initialRoute: '/users/5/edit', authUser: manager }
  );

describe('UserForm (create)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('asks for a password of at least 8 characters', () => {
    render(<UserForm />, { authUser: manager });

    const password = screen.getByLabelText(/^password/i);
    expect(password).toBeRequired();
    expect(password).toHaveAttribute('minLength', '8');
  });

  it('asks for an employee code of exactly 7 digits', () => {
    render(<UserForm />, { authUser: manager });

    const code = screen.getByLabelText(/employee code/i);
    expect(code).toBeRequired();
    expect(code).toHaveAttribute('pattern', '[0-9]{7}');
    expect(code).toHaveAttribute('maxLength', '7');
    expect(code).toHaveAttribute('inputMode', 'numeric');
  });

  // Whether the browser then blocks a short password is browser behaviour that jsdom does not
  // reproduce (it ignores minLength), so that is covered by the end-to-end tests instead.
});

describe('UserForm (edit)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(getUser).mockResolvedValue(existingUser);
    vi.mocked(updateUser).mockResolvedValue(existingUser);
  });

  it('keeps the new password optional but still at least 8 characters when one is typed', async () => {
    renderEditForm();

    const password = await screen.findByLabelText(/^password/i);
    expect(password).not.toBeRequired();
    expect(password).toHaveAttribute('minLength', '8');
  });

  it('does not send a password when the field is left blank', async () => {
    const user = userEvent.setup();
    renderEditForm();

    const name = await screen.findByLabelText(/^name/i);
    await user.type(name, ' Jr');
    await user.click(screen.getByRole('button', { name: 'Update User' }));

    await waitFor(() => expect(updateUser).toHaveBeenCalledTimes(1));
    expect(updateUser).toHaveBeenCalledWith(5, { email: 'jane@example.com', name: 'Jane Doe Jr' });
  });

  it('lets the manager clear a field instead of snapping back to the old value', async () => {
    const user = userEvent.setup();
    renderEditForm();

    const name = await screen.findByLabelText(/^name/i);
    expect(name).toHaveValue('Jane Doe');

    await user.clear(name);

    expect(name).toHaveValue('');
  });

  it('does not submit an emptied required field', async () => {
    const user = userEvent.setup();
    renderEditForm();

    await user.clear(await screen.findByLabelText(/^name/i));
    await user.click(screen.getByRole('button', { name: 'Update User' }));

    expect(updateUser).not.toHaveBeenCalled();
  });

  it("shows the server's message when the update is rejected", async () => {
    vi.mocked(updateUser).mockRejectedValue(
      new AxiosError('Request failed', 'ERR_BAD_REQUEST', undefined, undefined, {
        status: 400,
        data: { status_code: 400, reason_phrase: "Email 'taken@example.com' is already in use." },
      } as AxiosResponse)
    );
    const user = userEvent.setup();
    renderEditForm();

    await screen.findByLabelText(/^name/i);
    await user.click(screen.getByRole('button', { name: 'Update User' }));

    expect(await screen.findByRole('alert')).toHaveTextContent("Email 'taken@example.com' is already in use.");
  });

  it('sends the new password when one is entered', async () => {
    const user = userEvent.setup();
    renderEditForm();

    await screen.findByLabelText(/^name/i);
    await user.type(screen.getByLabelText(/^password/i), 'new-secret-123');
    await user.click(screen.getByRole('button', { name: 'Update User' }));

    await waitFor(() => expect(updateUser).toHaveBeenCalledTimes(1));
    expect(updateUser).toHaveBeenCalledWith(5, {
      email: 'jane@example.com',
      name: 'Jane Doe',
      password: 'new-secret-123',
    });
  });
});
