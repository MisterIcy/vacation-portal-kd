/** @jsxImportSource @emotion/react */
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { useTheme } from '@emotion/react';
import { getVacations } from '@domains/vacations/api';
import { vacationKeys } from '@domains/vacations/queryKeys';
import { getUsers } from '@domains/users/api';
import { Layout } from '@components/Layout';
import { UserDirectory } from '@components/UserDirectory';
import type { Theme } from '@styles/theme';
import { managerDashboardStyles } from './ManagerDashboard.styles';

export const ManagerDashboard = () => {
  const theme = useTheme() as Theme;
  const { data: pendingVacations } = useQuery({
    queryKey: vacationKeys.list('pending'),
    queryFn: () => getVacations('pending'),
  });

  const { data: users } = useQuery({
    queryKey: ['users'],
    queryFn: getUsers,
  });

  const pendingCount = pendingVacations?.length ?? 0;
  const totalUsers = users?.length || 0;
  const employeeCount = users?.filter(u => u.role === 'employee').length || 0;

  return (
    <Layout>
      <div css={managerDashboardStyles(theme)}>
        <div className="manager-dashboard__welcome">
          <h2 className="manager-dashboard__title">Manager Dashboard</h2>
          <p className="manager-dashboard__description">
            Manage vacation requests, users, and oversee team operations.
          </p>
        </div>

        <div className="manager-dashboard__stats">
          <div className="manager-dashboard__stat-card">
            <div className="manager-dashboard__stat-label">Pending Requests</div>
            <div className="manager-dashboard__stat-value">{pendingCount}</div>
          </div>
          <div className="manager-dashboard__stat-card">
            <div className="manager-dashboard__stat-label">Total Users</div>
            <div className="manager-dashboard__stat-value">{totalUsers}</div>
          </div>
          <div className="manager-dashboard__stat-card">
            <div className="manager-dashboard__stat-label">Employees</div>
            <div className="manager-dashboard__stat-value">{employeeCount}</div>
          </div>
        </div>

        <div className="manager-dashboard__actions">
          <div className="manager-dashboard__action-card">
            <h3 className="manager-dashboard__action-title">Vacation Requests</h3>
            <p className="manager-dashboard__action-description">
              Review and approve or reject pending vacation requests from your team.
            </p>
            <Link to="/vacations" className="manager-dashboard__action-button">
              View Requests
            </Link>
          </div>
        </div>

        <section className="manager-dashboard__users" aria-labelledby="users-title">
          <div className="manager-dashboard__section-header">
            <h3 id="users-title" className="manager-dashboard__section-title">Users</h3>
            <Link to="/users/new" className="manager-dashboard__action-button">
              Create user
            </Link>
          </div>
          <UserDirectory />
        </section>
      </div>
    </Layout>
  );
};
