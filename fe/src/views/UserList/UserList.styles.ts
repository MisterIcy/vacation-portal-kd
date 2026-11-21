import { css } from '@emotion/react';
import type { Theme } from '@styles/theme';

export const userListStyles = (theme: Theme) => css`
  display: flex;
  flex-direction: column;
  gap: 2rem;

  .user-list__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .user-list__title {
    margin: 0;
    color: ${theme.colors.text.primary};
    font-size: 1.75rem;
    font-weight: 600;
  }

  .user-list__add-button {
    padding: 1rem 1.5rem;
    background-color: ${theme.colors.primary.main};
    color: ${theme.colors.primary.contrast};
    text-decoration: none;
    border-radius: 4px;
    font-weight: 600;
    transition: all 0.2s ease;
  }

  .user-list__add-button:hover {
    background-color: ${theme.colors.primary.dark};
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
  }

  .user-list__add-button:active {
    background-color: ${theme.colors.primary.dark};
    box-shadow: none;
  }
`;
