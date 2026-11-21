export interface User {
  id: number;
  email: string;
  name: string;
  employee_code: string;
  role: 'employee' | 'manager';
  created_at: string;
  updated_at: string;
}

export interface CreateUserRequest {
  email: string;
  password: string;
  name: string;
  employee_code: string;
  role: 'employee' | 'manager';
}

export interface UpdateUserRequest {
  email?: string;
  password?: string;
  name?: string;
}
