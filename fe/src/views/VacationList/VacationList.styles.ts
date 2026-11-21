import { css } from '@emotion/react';
import type { Theme } from '@styles/theme';

export const vacationListStyles = (theme: Theme) => css`
  display: flex;
  flex-direction: column;
  gap: 2rem;

  .vacation-list__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .vacation-list__title {
    margin: 0;
    color: ${theme.colors.text.primary};
    font-size: 1.75rem;
    font-weight: 600;
  }
`;
