import { css } from '@emotion/react';
import type { Theme } from '@styles/theme';

export const userTableStyles = (theme: Theme) => css`
  .user-table__table {
    width: 100%;
    background: ${theme.colors.background.default};
    border-radius: 8px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    overflow: hidden;
    border-collapse: collapse;

    @media (max-width: 768px) {
      display: block;
      box-shadow: none;
      background: transparent;
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }
  }

  .user-table__table-head {
    background-color: ${theme.colors.background.paper};

    @media (max-width: 768px) {
      display: none;
    }
  }

  .user-table__th {
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: ${theme.colors.text.secondary};
    border-bottom: 2px solid ${theme.colors.border.main};
  }

  tbody {
    @media (max-width: 768px) {
      display: block;
    }
  }

  tbody tr {
    @media (max-width: 768px) {
      display: block;
      margin-bottom: 1rem;
      background: ${theme.colors.background.default};
      border-radius: 8px;
      padding: 1rem;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
  }

  .user-table__td {
    padding: 1rem;
    border-bottom: 1px solid ${theme.colors.border.light};

    @media (max-width: 768px) {
      display: flex;
      justify-content: space-between;
      padding: 0.75rem 0;
      border: none;

      &::before {
        content: attr(data-label);
        font-weight: 600;
        color: ${theme.colors.text.secondary};
        flex-shrink: 0;
        margin-right: 1rem;
      }

      &:last-child {
        padding-bottom: 0;
        margin-top: 0.5rem;
        border-top: 1px solid ${theme.colors.border.light};
        padding-top: 0.75rem;
        flex-direction: column;
        gap: 0.5rem;

        &::before {
          content: '';
          display: none;
        }
      }
    }
  }

  .user-table__role-badge {
    display: inline-block;
    padding: 0.25rem 1rem;
    border-radius: 12px;
    font-size: ${theme.fontSize.sm};
    font-weight: 500;
  }

  .user-table__role-badge--manager {
    background-color: ${theme.colors.role.manager.bg};
    color: ${theme.colors.role.manager.text};
  }

  .user-table__role-badge--employee {
    background-color: ${theme.colors.role.employee.bg};
    color: ${theme.colors.role.employee.text};
  }

  .user-table__button {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 4px;
    font-weight: 500;
    font-size: ${theme.fontSize.sm};
    cursor: pointer;
    transition: all 0.2s ease;
    margin-right: 0.5rem;
    text-decoration: none;
    display: inline-block;

    @media (max-width: 768px) {
      margin-right: 0;
      margin-bottom: 0.5rem;
      width: 100%;
      text-align: center;

      &:last-child {
        margin-bottom: 0;
      }
    }
  }

  .user-table__button--edit {
    background-color: ${theme.colors.primary.light};
    color: ${theme.colors.primary.contrast};
  }

  .user-table__button--edit:hover {
    background-color: ${theme.colors.primary.main};
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
  }

  .user-table__button--edit:active {
    background-color: ${theme.colors.primary.main};
    box-shadow: none;
  }

  .user-table__button--delete {
    background-color: ${theme.colors.error.light};
    color: ${theme.colors.error.contrast};
  }

  .user-table__button--delete:hover:not(:disabled) {
    background-color: ${theme.colors.error.main};
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
  }

  .user-table__button--delete:active:not(:disabled) {
    background-color: ${theme.colors.error.main};
    box-shadow: none;
  }

  .user-table__button--delete:disabled {
    background-color: ${theme.colors.background.disabled};
    cursor: not-allowed;
    opacity: 0.6;
  }

  .user-table__empty-state {
    padding: 3rem;
    text-align: center;
    color: ${theme.colors.text.disabled};
  }

  .user-table__loading-state {
    padding: 3rem;
    text-align: center;
    color: ${theme.colors.text.secondary};
  }
`;
