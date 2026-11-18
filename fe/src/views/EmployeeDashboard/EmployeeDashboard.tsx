/** @jsxImportSource @emotion/react */
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { useTheme } from '@emotion/react';
import { getVacations } from '@domains/vacations/api';
import { vacationKeys } from '@domains/vacations/queryKeys';
import { Layout } from '@components/Layout';
import { VacationRequests } from '@components/VacationRequests';
import type { Theme } from '@styles/theme';
import { employeeDashboardStyles } from './EmployeeDashboard.styles';

export const EmployeeDashboard = () => {
  const theme = useTheme() as Theme;
  const { data: vacations, isLoading } = useQuery({
    queryKey: vacationKeys.list(),
    queryFn: () => getVacations(),
  });

  const pendingCount = vacations?.filter(v => v.status === 'pending').length || 0;
  const approvedCount = vacations?.filter(v => v.status === 'approved').length || 0;
  const rejectedCount = vacations?.filter(v => v.status === 'rejected').length || 0;

  return (
    <Layout>
      <div css={employeeDashboardStyles(theme)}>
        <div className="employee-dashboard__welcome">
          <h2 className="employee-dashboard__title">
            Welcome to Your Dashboard
          </h2>
          <p className="employee-dashboard__description">
            Manage your vacation requests, view their status, and request new time off.
          </p>
          <Link to="/vacations/new" className="employee-dashboard__action-button">
            Request New Vacation
          </Link>
        </div>

        {!isLoading && (
          <div className="employee-dashboard__stats">
            <div className="employee-dashboard__stat-card">
              <div className="employee-dashboard__stat-label">
                Pending Requests
              </div>
              <div className="employee-dashboard__stat-value">
                {pendingCount}
              </div>
            </div>
            <div className="employee-dashboard__stat-card">
              <div className="employee-dashboard__stat-label">
                Approved Requests
              </div>
              <div className="employee-dashboard__stat-value">
                {approvedCount}
              </div>
            </div>
            <div className="employee-dashboard__stat-card">
              <div className="employee-dashboard__stat-label">
                Rejected Requests
              </div>
              <div className="employee-dashboard__stat-value">
                {rejectedCount}
              </div>
            </div>
            <div className="employee-dashboard__stat-card">
              <div className="employee-dashboard__stat-label">
                Total Requests
              </div>
              <div className="employee-dashboard__stat-value">
                {vacations?.length || 0}
              </div>
            </div>
          </div>
        )}

        <section className="employee-dashboard__requests" aria-labelledby="my-requests-title">
          <h3 id="my-requests-title" className="employee-dashboard__section-title">
            My vacation requests
          </h3>
          <VacationRequests emptyMessage="You have not requested any vacation yet. Use “Request New Vacation” to submit one." />
        </section>
      </div>
    </Layout>
  );
};
