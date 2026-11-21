/** @jsxImportSource @emotion/react */
import { useTheme } from '@emotion/react';
import type { Theme } from '@styles/theme';
import { Layout } from '@components/Layout';
import { VacationRequests } from '@components/VacationRequests';
import { useAuth } from '@hooks/useAuth';
import { vacationListStyles } from './VacationList.styles';

export const VacationList = () => {
  const theme = useTheme() as Theme;
  const { user } = useAuth();

  return (
    <Layout>
      <div css={vacationListStyles(theme)}>
        <div className="vacation-list__header">
          <h2 className="vacation-list__title">
            {user?.role === 'manager' ? 'Vacation Requests' : 'My Vacation Requests'}
          </h2>
        </div>

        <VacationRequests />
      </div>
    </Layout>
  );
};
