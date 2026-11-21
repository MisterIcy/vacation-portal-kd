import { css } from '@emotion/react';
import type { Theme } from '@styles/theme';

export const statusFilterStyles = (theme: Theme) => css`
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-bottom: 1rem;

  .status-filter__button {
    padding: 0.5rem 1rem;
    border: 1px solid ${theme.colors.border.main};
    border-radius: 999px;
    background: ${theme.colors.background.default};
    color: ${theme.colors.text.primary};
    font-size: 0.875rem;
    cursor: pointer;

    &:hover {
      background: ${theme.colors.background.hover};
    }

    &:focus-visible {
      outline: 2px solid ${theme.colors.primary.main};
      outline-offset: 2px;
    }

    /* The selected state is carried by weight and fill, not by colour alone. */
    &[aria-pressed='true'] {
      background: ${theme.colors.primary.main};
      border-color: ${theme.colors.primary.main};
      color: ${theme.colors.primary.contrast};
      font-weight: 600;
    }
  }
`;
