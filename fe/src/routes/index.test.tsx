import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { screen } from '@testing-library/react';
import { AppRoutes } from './index';
import { render } from '@tests/test-utils';
import type { User } from '@domains/auth/types';

// The views are tested on their own. Here each one is a marker, so what is checked is who gets to see which.
vi.mock('@views/Login', () => ({ LoginPage: () => <div>Login page</div> }));
vi.mock('@views/EmployeeDashboard', () => ({ EmployeeDashboard: () => <div>Employee dashboard</div> }));
vi.mock('@views/ManagerDashboard', () => ({ ManagerDashboard: () => <div>Manager dashboard</div> }));
vi.mock('@views/VacationList', () => ({ VacationList: () => <div>Vacation list</div> }));
vi.mock('@views/CreateVacation', () => ({ CreateVacation: () => <div>Create vacation</div> }));
vi.mock('@views/UserList', () => ({ UserList: () => <div>User list</div> }));
vi.mock('@views/UserForm', () => ({ UserForm: () => <div>User form</div> }));

const employee: User = { id: 2, name: 'Employee User', email: 'employee@example.com', role: 'employee' };
const manager: User = { id: 1, name: 'Manager User', email: 'manager@example.com', role: 'manager' };

const visit = (path: string, authUser: User | null) => {
  window.history.pushState({}, '', path);

  return render(<AppRoutes />, { authUser });
};

describe('AppRoutes', () => {
  beforeEach(() => {
    vi.spyOn(console, 'log').mockImplementation(() => {});
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  describe('someone who is not signed in', () => {
    it.each(['/', '/dashboard', '/dashboard/manager', '/vacations', '/vacations/new', '/users', '/users/new', '/users/1/edit'])(
      'is sent to the login page from %s',
      (path) => {
        visit(path, null);

        expect(screen.getByText('Login page')).toBeInTheDocument();
        expect(window.location.pathname).toBe('/login');
      },
    );

    it('can open the login page', () => {
      visit('/login', null);

      expect(screen.getByText('Login page')).toBeInTheDocument();
    });
  });

  describe('an employee', () => {
    it.each([
      ['/', 'Employee dashboard'],
      ['/dashboard', 'Employee dashboard'],
      ['/vacations', 'Vacation list'],
      ['/vacations/new', 'Create vacation'],
    ])('can open %s', (path, expected) => {
      visit(path, employee);

      expect(screen.getByText(expected)).toBeInTheDocument();
    });

    it.each(['/dashboard/manager', '/users', '/users/new', '/users/1/edit'])(
      'is sent back to their own dashboard from the manager page %s',
      (path) => {
        visit(path, employee);

        expect(screen.getByText('Employee dashboard')).toBeInTheDocument();
        expect(window.location.pathname).toBe('/dashboard');
      },
    );
  });

  describe('a manager', () => {
    it.each([
      ['/', 'Manager dashboard'],
      ['/dashboard/manager', 'Manager dashboard'],
      ['/vacations', 'Vacation list'],
      ['/users', 'User list'],
      ['/users/new', 'User form'],
      ['/users/1/edit', 'User form'],
    ])('can open %s', (path, expected) => {
      visit(path, manager);

      expect(screen.getByText(expected)).toBeInTheDocument();
    });

    it.each(['/dashboard', '/vacations/new'])('is sent back to their own dashboard from the employee page %s', (path) => {
      visit(path, manager);

      expect(screen.getByText('Manager dashboard')).toBeInTheDocument();
      expect(window.location.pathname).toBe('/dashboard/manager');
    });
  });

  it('says so when the page does not exist, to anyone', () => {
    visit('/no/such/page', null);

    expect(screen.getByRole('heading', { name: 'Page Not Found' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Go to Home' })).toHaveAttribute('href', '/');
  });
});
