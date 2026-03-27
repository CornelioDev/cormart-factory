export type Role = 'super_admin' | 'operator' | 'member' | 'company_user';

interface Credentials {
  email: string;
  password: string;
}

const credentials: Record<Role, Credentials> = {
  super_admin: { email: 'admin@test.com', password: 'password' },
  operator: { email: 'operador@test.com', password: 'password' },
  member: { email: 'miembro@test.com', password: 'password' },
  company_user: { email: 'cormart@test.com', password: 'password' },
};

export function getCredentials(baseURL: string | undefined, role: Role): Credentials {
  return credentials[role];
}
