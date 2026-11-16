export interface Vacation {
  id: number;
  user_id: number;
  user?: {
    id: number;
    name: string;
    email: string;
    role: string;
  } | null;
  from: string;
  to: string;
  /** Inclusive number of days from `from` to `to`. */
  days: number;
  reason: string;
  status: 'pending' | 'approved' | 'rejected';
  authorized_by: number | null;
  created_at: string;
  updated_at: string;
}

export interface CreateVacationRequest {
  from: string;
  to: string;
  reason: string;
}
