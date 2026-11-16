import type { ReactElement } from 'react';
import { render, type RenderOptions } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import { ThemeProvider } from '@emotion/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { vi } from 'vitest';
import { theme } from '@styles/theme';
import { AuthContext } from '@context/AuthContext';
import type { User } from '@domains/auth/types';

interface CustomRenderOptions extends Omit<RenderOptions, 'wrapper'> {
  initialRoute?: string;
  /**
   * Signed-in user for components that read the auth context (e.g. anything
   * wrapped in `Layout`). Pass `null` to render as signed out. When omitted, no
   * auth provider is added, so components that don't use it are unaffected.
   */
  authUser?: User | null;
}

/** A query client for tests: no retries (errors surface immediately) and no cache reuse. */
export const createTestQueryClient = () =>
  new QueryClient({
    defaultOptions: {
      queries: { retry: false, gcTime: 0 },
      mutations: { retry: false },
    },
  });

export const renderWithProviders = (
  ui: ReactElement,
  { initialRoute = '/', authUser, ...renderOptions }: CustomRenderOptions = {}
) => {
  if (initialRoute !== '/') {
    window.history.pushState({}, 'Test page', initialRoute);
  }

  const queryClient = createTestQueryClient();

  const Wrapper = ({ children }: { children: React.ReactNode }) => {
    const content =
      authUser === undefined ? (
        children
      ) : (
        <AuthContext.Provider
          value={{
            user: authUser,
            isAuthenticated: authUser !== null,
            isLoading: false,
            login: vi.fn(),
            logout: vi.fn(),
          }}
        >
          {children}
        </AuthContext.Provider>
      );

    return (
      <BrowserRouter>
        <ThemeProvider theme={theme}>
          <QueryClientProvider client={queryClient}>{content}</QueryClientProvider>
        </ThemeProvider>
      </BrowserRouter>
    );
  };

  return render(ui, { wrapper: Wrapper, ...renderOptions });
};

// eslint-disable-next-line react-refresh/only-export-components
export * from '@testing-library/react';
export { renderWithProviders as render };
