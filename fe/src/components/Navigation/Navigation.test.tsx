import { describe, it, expect, vi, beforeEach } from 'vitest';
import { screen, waitFor, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Navigation } from './Navigation';
import { PENDING_REFETCH_INTERVAL_MS } from './PendingRequestsBadge';
import { render } from '@tests/test-utils';
import { getVacations } from '@domains/vacations/api';
import type { Vacation } from '@domains/vacations/types';
import type { User } from '@domains/auth/types';

vi.mock('@domains/vacations/api', () => ({
  getVacations: vi.fn(),
}));

const pending = (count: number) => Array.from({ length: count }, (_, i) => ({ id: i + 1 }) as Vacation);

describe('Navigation', () => {
  const mockOnLogout = vi.fn();

  beforeEach(() => {
    vi.mocked(getVacations).mockResolvedValue([]);
  });

  const employeeUser: User = {
    id: 1,
    name: 'John Doe',
    email: 'john@example.com',
    role: 'employee',
  };

  const managerUser: User = {
    id: 2,
    name: 'Jane Manager',
    email: 'jane@example.com',
    role: 'manager',
  };

  afterEach(() => {
    vi.clearAllMocks();
  });

  describe('Employee Navigation', () => {
    it('should render employee navigation links', () => {
      render(<Navigation user={employeeUser} onLogout={mockOnLogout} />);

      expect(screen.getByText('Dashboard')).toBeInTheDocument();
      expect(screen.getByText('My Vacations')).toBeInTheDocument();
      expect(screen.getByText('Request Vacation')).toBeInTheDocument();
      expect(screen.getByText('John Doe')).toBeInTheDocument();
      expect(screen.getByText('Logout')).toBeInTheDocument();
    });

    it('should not render manager-only links for employees', () => {
      render(<Navigation user={employeeUser} onLogout={mockOnLogout} />);

      expect(screen.queryByText('Vacation Requests')).not.toBeInTheDocument();
      expect(screen.queryByText('Manage Users')).not.toBeInTheDocument();
    });

    it('should have correct href for employee links', () => {
      render(<Navigation user={employeeUser} onLogout={mockOnLogout} />);

      const myVacationsLink = screen.getByText('My Vacations').closest('a');
      const requestVacationLink = screen.getByText('Request Vacation').closest('a');

      expect(myVacationsLink).toHaveAttribute('href', '/vacations');
      expect(requestVacationLink).toHaveAttribute('href', '/vacations/new');
    });
  });

  describe('Manager Navigation', () => {
    it('should render manager navigation links', () => {
      render(<Navigation user={managerUser} onLogout={mockOnLogout} />);

      expect(screen.getByText('Dashboard')).toBeInTheDocument();
      expect(screen.getByText('Vacation Requests')).toBeInTheDocument();
      expect(screen.getByText('Manage Users')).toBeInTheDocument();
      expect(screen.getByText('Jane Manager')).toBeInTheDocument();
      expect(screen.getByText('Logout')).toBeInTheDocument();
    });

    it('should not render employee-only links for managers', () => {
      render(<Navigation user={managerUser} onLogout={mockOnLogout} />);

      expect(screen.queryByText('My Vacations')).not.toBeInTheDocument();
      expect(screen.queryByText('Request Vacation')).not.toBeInTheDocument();
    });

    it('should have correct href for manager links', () => {
      render(<Navigation user={managerUser} onLogout={mockOnLogout} />);

      const dashboardLink = screen.getByText('Dashboard').closest('a');
      const vacationRequestsLink = screen.getByText('Vacation Requests').closest('a');
      const manageUsersLink = screen.getByText('Manage Users').closest('a');

      expect(dashboardLink).toHaveAttribute('href', '/dashboard/manager');
      expect(vacationRequestsLink).toHaveAttribute('href', '/vacations');
      expect(manageUsersLink).toHaveAttribute('href', '/users');
    });
  });

  describe('Pending requests badge', () => {
    it('tells a manager how many requests wait for a decision', async () => {
      vi.mocked(getVacations).mockResolvedValue(pending(3));

      render(<Navigation user={managerUser} onLogout={mockOnLogout} />);

      const link = await screen.findByRole('link', { name: /3 pending requests/ });
      expect(link).toHaveAttribute('href', '/vacations');
      expect(vi.mocked(getVacations).mock.calls[0][0]).toBe('pending');
    });

    it('uses the singular for one request', async () => {
      vi.mocked(getVacations).mockResolvedValue(pending(1));

      render(<Navigation user={managerUser} onLogout={mockOnLogout} />);

      expect(await screen.findByRole('link', { name: /1 pending request$/ })).toBeInTheDocument();
    });

    it('shows nothing when no request is waiting', async () => {
      render(<Navigation user={managerUser} onLogout={mockOnLogout} />);

      await waitFor(() => expect(getVacations).toHaveBeenCalled());
      expect(screen.getByRole('link', { name: 'Vacation Requests' })).toBeInTheDocument();
    });

    it('shows nothing, and no error, when the count cannot be loaded', async () => {
      vi.mocked(getVacations).mockRejectedValue(new Error('Network Error'));

      render(<Navigation user={managerUser} onLogout={mockOnLogout} />);

      await waitFor(() => expect(getVacations).toHaveBeenCalled());
      expect(screen.getByRole('link', { name: 'Vacation Requests' })).toBeInTheDocument();
      expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    });

    it('is not shown to employees, who do not even ask for the count', () => {
      render(<Navigation user={employeeUser} onLogout={mockOnLogout} />);

      expect(screen.queryByText(/pending request/)).not.toBeInTheDocument();
      expect(getVacations).not.toHaveBeenCalled();
    });

    it('asks again every minute so new requests show up on an open page', async () => {
      vi.useFakeTimers({ shouldAdvanceTime: true });
      try {
        vi.mocked(getVacations).mockResolvedValue(pending(1));
        render(<Navigation user={managerUser} onLogout={mockOnLogout} />);
        await screen.findByRole('link', { name: /1 pending request$/ });

        vi.mocked(getVacations).mockResolvedValue(pending(2));
        await act(async () => {
          await vi.advanceTimersByTimeAsync(PENDING_REFETCH_INTERVAL_MS);
        });

        expect(await screen.findByRole('link', { name: /2 pending requests$/ })).toBeInTheDocument();
      } finally {
        vi.useRealTimers();
      }
    });
  });

  describe('Logout Functionality', () => {
    it('should call onLogout when logout button is clicked', async () => {
      const user = userEvent.setup();
      render(<Navigation user={employeeUser} onLogout={mockOnLogout} />);

      const logoutButton = screen.getByText('Logout');
      await user.click(logoutButton);

      expect(mockOnLogout).toHaveBeenCalledTimes(1);
    });
  });

  describe('Mobile Menu', () => {
    it('should render hamburger menu button', () => {
      render(<Navigation user={employeeUser} onLogout={mockOnLogout} />);

      const menuButton = screen.getByLabelText('Toggle menu');
      expect(menuButton).toBeInTheDocument();
    });

    it('should toggle mobile menu state when hamburger is clicked', async () => {
      const user = userEvent.setup();
      render(<Navigation user={employeeUser} onLogout={mockOnLogout} />);

      const menuButton = screen.getByLabelText('Toggle menu');

      // Click to open
      await user.click(menuButton);

      // Click to close
      await user.click(menuButton);

      // Button should still be in the document
      expect(menuButton).toBeInTheDocument();
    });

    it('should close mobile menu when a navigation link is clicked', async () => {
      const user = userEvent.setup();
      render(<Navigation user={employeeUser} onLogout={mockOnLogout} />);

      const menuButton = screen.getByLabelText('Toggle menu');
      await user.click(menuButton);

      const myVacationsLink = screen.getByText('My Vacations');
      await user.click(myVacationsLink);

      // Menu should still be functional after clicking a link
      expect(menuButton).toBeInTheDocument();
    });

    it('should close mobile menu when logout is clicked', async () => {
      const user = userEvent.setup();
      render(<Navigation user={employeeUser} onLogout={mockOnLogout} />);

      const menuButton = screen.getByLabelText('Toggle menu');
      await user.click(menuButton);

      const logoutButton = screen.getByText('Logout');
      await user.click(logoutButton);

      await waitFor(() => {
        expect(mockOnLogout).toHaveBeenCalled();
      });
    });
  });

  describe('Null User Handling', () => {
    it('should render only user info section when user is null', () => {
      render(<Navigation user={null} onLogout={mockOnLogout} />);

      expect(screen.queryByText('My Vacations')).not.toBeInTheDocument();
      expect(screen.queryByText('Dashboard')).not.toBeInTheDocument();
      expect(screen.getByText('Logout')).toBeInTheDocument();
    });
  });
});
