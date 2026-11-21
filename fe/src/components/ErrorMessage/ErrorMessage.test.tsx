import { describe, it, expect } from 'vitest';
import { screen } from '@testing-library/react';
import { ErrorMessage } from './ErrorMessage';
import { render } from '@tests/test-utils';

describe('ErrorMessage', () => {
  it('announces the message as an alert', () => {
    render(<ErrorMessage message="Email is already in use." />);

    expect(screen.getByRole('alert')).toHaveTextContent('Email is already in use.');
  });

  it('renders nothing without a message', () => {
    const { container } = render(<ErrorMessage message={null} />);

    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    expect(container).toBeEmptyDOMElement();
  });

  it('passes a class name through so views can adjust the layout', () => {
    render(<ErrorMessage message="Oops" className="login__error-message" />);

    expect(screen.getByRole('alert')).toHaveClass('login__error-message');
  });
});
