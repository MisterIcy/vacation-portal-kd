import { css } from '@emotion/react';
import type { Theme } from '@styles/theme';

export const errorMessageStyles = (theme: Theme) => css`
  color: ${theme.colors.error.main};
  font-size: ${theme.fontSize.sm};
  background-color: ${theme.colors.grey[50]};
  padding: 0.75rem 1rem;
  border-radius: 4px;
`;
