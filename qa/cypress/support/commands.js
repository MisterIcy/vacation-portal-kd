/**
 * Log in using seeded credentials from cypress/fixtures/users.json, through the login form.
 * Use it only in tests that are about logging in; everywhere else use cy.loginByToken, which is faster.
 *
 * @example cy.loginAs('employee')
 */
Cypress.Commands.add('loginAs', (role) => {
  cy.fixture('users').then((users) => {
    const user = users[role];

    cy.visit('/login');
    cy.get('#email').clear().type(user.email);
    cy.get('#password').clear().type(user.password);
    cy.get('button[type="submit"]').click();
    // Waits for post-login redirect
    cy.url().should('include', '/dashboard');
  });
});

/**
 * Stub window.confirm to accept the next dialog(s).
 * Call immediately before the action that triggers confirm (e.g. Delete click).
 *
 * @example cy.acceptConfirm(); cy.contains('button', 'Delete').click();
 */
Cypress.Commands.add('acceptConfirm', () => {
  cy.on('window:confirm', () => true);
});

/** Refuses the next confirm dialog, so a test can check that nothing was deleted. */
Cypress.Commands.add('declineConfirm', () => {
  cy.on('window:confirm', () => false);
});

// ---- Working through the API: fast, and every test owns its data -------------------------------

/** "manager" or "employee" (the seeded accounts), or an object with email and password. */
const credentialsOf = (who) =>
  typeof who === 'string' ? cy.fixture('users').then((users) => users[who]) : cy.wrap(who);

/** Signs in through the API and yields the token. */
Cypress.Commands.add('apiToken', (who) =>
  credentialsOf(who).then(({ email, password }) =>
    cy.request('POST', '/api/auth/login', { email, password }).its('body.token'),
  ),
);

/**
 * Sends a request as someone (or as nobody, with who = null) and yields the response whatever its status.
 *
 * @example cy.apiAs('employee', { method: 'GET', url: '/api/users' }).its('status').should('eq', 403)
 */
Cypress.Commands.add('apiAs', (who, options) => {
  const send = (headers) => cy.request({ failOnStatusCode: false, ...options, headers });

  return who === null ? send({}) : cy.apiToken(who).then((token) => send({ Authorization: `Bearer ${token}` }));
});

/**
 * Opens the app already signed in, without going through the login form.
 * It stores what AuthContext restores on load: the token and the user from its claims.
 */
Cypress.Commands.add('loginByToken', (who, path = '/') =>
  cy.apiToken(who).then((token) => {
    const claims = JSON.parse(atob(token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/')));
    const user = { id: claims.sub, email: claims.email, name: claims.name, role: claims.role };

    cy.visit(path, {
      onBeforeLoad(win) {
        win.localStorage.setItem('token', token);
        win.localStorage.setItem('user', JSON.stringify(user));
      },
    });
  }),
);

/** Creates a user as the seeded manager and yields it, with its id and the password it was given. */
Cypress.Commands.add('createUserViaApi', (overrides = {}) => {
  const n = Cypress._.random(1000000, 9999999);
  const user = {
    name: `QA User ${n}`,
    email: `qa.${n}@example.com`,
    employee_code: String(n),
    password: 'Password123',
    role: 'employee',
    ...overrides,
  };

  return cy.apiAs('manager', { method: 'POST', url: '/api/users', body: user }).then((response) => {
    expect(response.status, 'user created').to.eq(201);

    return { ...user, id: response.body.id };
  });
});

/** Removes a user and, with it, everything of theirs. Cheap, so tests clean up after themselves. */
Cypress.Commands.add('deleteUserViaApi', (user) =>
  cy.apiAs('manager', { method: 'DELETE', url: `/api/users/${user.id}` }),
);

/** Submits a vacation request as `who` and yields it. */
Cypress.Commands.add('createVacationViaApi', (who, { from, to, reason }) =>
  cy.apiAs(who, { method: 'POST', url: '/api/vacations', body: { from, to, reason } }).then((response) => {
    expect(response.status, 'vacation request created').to.eq(201);

    return response.body;
  }),
);

/** Decides a request as the seeded manager ("approve" or "reject") and yields the response. */
Cypress.Commands.add('decideViaApi', (vacationId, decision) =>
  cy.apiAs('manager', { method: 'PUT', url: `/api/vacations/${vacationId}/${decision}` }),
);
