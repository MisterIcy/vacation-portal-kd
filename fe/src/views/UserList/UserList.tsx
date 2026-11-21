/** @jsxImportSource @emotion/react */
import { Link } from 'react-router-dom';
import { useTheme } from '@emotion/react';
import type { Theme } from '@styles/theme';
import { Layout } from '@components/Layout';
import { UserDirectory } from '@components/UserDirectory';
import { userListStyles } from './UserList.styles';

export const UserList = () => {
  const theme = useTheme() as Theme;

  return (
    <Layout>
      <div css={userListStyles(theme)}>
        <div className="user-list__header">
          <h2 className="user-list__title">Manage Users</h2>
          <Link to="/users/new" className="user-list__add-button">
            Add New User
          </Link>
        </div>

        <UserDirectory />
      </div>
    </Layout>
  );
};
