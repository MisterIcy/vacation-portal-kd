/** @jsxImportSource @emotion/react */
import { useTheme } from '@emotion/react';
import type { Theme } from '@styles/theme';
import { errorMessageStyles } from './ErrorMessage.styles';

interface ErrorMessageProps {
  /** Nothing is rendered when there is no message. */
  message?: string | null;
  /** Lets a view keep its own look (spacing, alignment) on top of the default. */
  className?: string;
}

/** Announces a failure to assistive technology and shows it where the user is looking. */
export const ErrorMessage = ({ message, className }: ErrorMessageProps) => {
  const theme = useTheme() as Theme;

  if (!message) {
    return null;
  }

  return (
    <div role="alert" css={errorMessageStyles(theme)} className={className}>
      {message}
    </div>
  );
};
