import { describe, it, expect, vi } from 'vitest';
import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { StatusFilter } from './StatusFilter';
import { render } from '@tests/test-utils';

describe('StatusFilter', () => {
  it('offers every status and marks the selected one', () => {
    render(<StatusFilter value="approved" onChange={vi.fn()} />);

    expect(screen.getByRole('group', { name: 'Filter by status' })).toBeInTheDocument();
    expect(screen.getAllByRole('button').map((button) => button.textContent)).toEqual([
      'Pending',
      'Approved',
      'Rejected',
      'All',
    ]);
    expect(screen.getByRole('button', { name: 'Approved' })).toHaveAttribute('aria-pressed', 'true');
    expect(screen.getByRole('button', { name: 'Pending' })).toHaveAttribute('aria-pressed', 'false');
  });

  it('reports the status that was chosen', async () => {
    const onChange = vi.fn();
    const user = userEvent.setup();

    render(<StatusFilter value="pending" onChange={onChange} />);
    await user.click(screen.getByRole('button', { name: 'Rejected' }));

    expect(onChange).toHaveBeenCalledWith('rejected');
  });
});
