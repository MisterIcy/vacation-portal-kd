import { isoInDays } from '../../support/dates';

describe('Manager account › Managing users', () => {
  // Every test that needs a user makes its own and removes it afterwards, so the seeded accounts are never touched.
  let created;

  beforeEach(() => {
    created = [];
  });

  afterEach(() => {
    created.forEach((user) => cy.deleteUserViaApi(user));
  });

  const makeUser = (overrides) =>
    cy.createUserViaApi(overrides).then((user) => {
      created.push(user);

      return user;
    });

  const rowOf = (text) => cy.contains('tr', text);

  const fillUserForm = ({ name, email, employee_code, password, role }) => {
    cy.get('#name').clear().type(name);
    cy.get('#email').clear().type(email);
    if (employee_code !== undefined) cy.get('#employeeCode').clear().type(employee_code);
    if (password !== undefined) cy.get('#password').clear().type(password);
    if (role !== undefined) cy.get('#role').select(role);
  };

  const canLogIn = (user, password = user.password) =>
    cy.request({ method: 'POST', url: '/api/auth/login', body: { email: user.email, password }, failOnStatusCode: false });

  describe('the list of users', () => {
    it('MGR-USR-01 shows every user with name and email', () => {
      makeUser().then((user) => {
        cy.loginByToken('manager', '/users');

        rowOf(user.email).within(() => {
          cy.contains(user.name).should('be.visible');
        });
        cy.contains('tr', 'manager@example.com').should('be.visible');
        cy.contains('tr', 'employee@example.com').should('be.visible');
      });
    });

    it('MGR-USR-02 offers update and delete on each row', () => {
      makeUser().then((user) => {
        cy.loginByToken('manager', '/users');

        rowOf(user.email).within(() => {
          cy.get(`a[aria-label="Edit ${user.name}"]`).should('be.visible');
          cy.get(`button[aria-label="Delete ${user.name}"]`).should('be.visible');
        });
      });
    });
  });

  describe('creating a user', () => {
    it('MGR-CRT-01 adds the user to the list, and they can sign in', () => {
      const n = Cypress._.random(1000000, 9999999);
      const user = { name: `QA Created ${n}`, email: `qa.created.${n}@example.com`, employee_code: String(n), password: 'Password123' };
      cy.intercept('POST', '/api/users').as('createUser');
      cy.loginByToken('manager', '/users/new');

      fillUserForm(user);
      cy.contains('button', 'Create User').click();

      cy.wait('@createUser').then(({ response }) => {
        expect(response.statusCode).to.eq(201);
        created.push({ id: response.body.id });
      });
      cy.location('pathname').should('eq', '/users');
      rowOf(user.email).should('contain.text', user.name);
      canLogIn(user).its('status').should('eq', 200);
    });

    it('MGR-CRT-06 can make a manager, who then lands on the manager home', () => {
      const n = Cypress._.random(1000000, 9999999);
      const user = { name: `QA Boss ${n}`, email: `qa.boss.${n}@example.com`, employee_code: String(n), password: 'Password123', role: 'manager' };
      cy.intercept('POST', '/api/users').as('createUser');
      cy.loginByToken('manager', '/users/new');

      fillUserForm(user);
      cy.contains('button', 'Create User').click();
      cy.wait('@createUser').then(({ response }) => created.push({ id: response.body.id }));

      cy.apiToken(user).then((token) => {
        expect(JSON.parse(atob(token.split('.')[1])).role).to.eq('manager');
      });
    });

    it('MGR-CRT-02 an employee code that is not exactly seven digits is stopped by the form', () => {
      cy.intercept('POST', '/api/users').as('createUser');
      cy.loginByToken('manager', '/users/new');

      fillUserForm({ name: 'QA Short Code', email: 'qa.short@example.com', employee_code: '123456', password: 'Password123' });
      cy.contains('button', 'Create User').click();

      cy.get('#employeeCode').then(($input) => expect($input[0].validity.patternMismatch).to.be.true);
      cy.location('pathname').should('eq', '/users/new');
      cy.get('@createUser.all').should('have.length', 0);
    });

    // The form's minLength is only enforced for typing by a person, so the boundary is checked where the rule lives.
    it('MGR-CRT-03 accepts a password of eight characters and refuses seven', () => {
      const n = Cypress._.random(1000000, 9999999);
      const user = { name: `QA Boundary ${n}`, email: `qa.boundary.${n}@example.com`, employee_code: String(n) };

      cy.apiAs('manager', { method: 'POST', url: '/api/users', body: { ...user, password: '1234567' } }).then((response) => {
        expect(response.status).to.eq(400);
        expect(response.body.reason_phrase).to.contain('8 characters');
      });
      cy.apiAs('manager', { method: 'POST', url: '/api/users', body: { ...user, password: '12345678' } }).then((response) => {
        expect(response.status).to.eq(201);
        created.push({ id: response.body.id });
      });
    });

    it('MGR-CRT-05 an email that is already in use is refused and the form says why', () => {
      makeUser().then((existing) => {
        cy.loginByToken('manager', '/users/new');

        fillUserForm({
          name: 'QA Duplicate',
          email: existing.email,
          employee_code: String(Cypress._.random(1000000, 9999999)),
          password: 'Password123',
        });
        cy.contains('button', 'Create User').click();

        cy.get('[role="alert"]').should('contain.text', 'already in use');
        cy.location('pathname').should('eq', '/users/new');
      });
    });
  });

  describe('updating a user', () => {
    it('MGR-UPD-01 a changed name is saved and shown in the list', () => {
      makeUser().then((user) => {
        cy.loginByToken('manager', '/users');
        rowOf(user.email).find(`a[aria-label="Edit ${user.name}"]`).click();

        cy.get('#name').clear().type('Renamed By QA');
        cy.contains('button', 'Update User').click();

        cy.location('pathname').should('eq', '/users');
        rowOf(user.email).should('contain.text', 'Renamed By QA');
      });
    });

    it('MGR-UPD-04 saving without a password keeps the one the user already has', () => {
      makeUser().then((user) => {
        cy.loginByToken('manager', '/users');
        rowOf(user.email).find(`a[aria-label="Edit ${user.name}"]`).click();

        cy.get('#name').clear().type('Same Password Still');
        cy.contains('button', 'Update User').click();
        cy.location('pathname').should('eq', '/users');

        canLogIn(user).its('status').should('eq', 200);
      });
    });

    it('MGR-UPD-03 a new password replaces the old one', () => {
      makeUser().then((user) => {
        cy.loginByToken('manager', '/users');
        rowOf(user.email).find(`a[aria-label="Edit ${user.name}"]`).click();

        cy.get('#password').type('BrandNewPass456');
        cy.contains('button', 'Update User').click();
        cy.location('pathname').should('eq', '/users');

        canLogIn(user).its('status').should('eq', 401);
        canLogIn(user, 'BrandNewPass456').its('status').should('eq', 200);
      });
    });

    it('MGR-UPD-02 a changed email works for signing in and the old one no longer does', () => {
      makeUser().then((user) => {
        const newEmail = `moved.${user.email}`;
        cy.loginByToken('manager', '/users');
        rowOf(user.email).find(`a[aria-label="Edit ${user.name}"]`).click();

        cy.get('#email').clear().type(newEmail);
        cy.contains('button', 'Update User').click();
        cy.location('pathname').should('eq', '/users');

        canLogIn({ email: newEmail }, user.password).its('status').should('eq', 200);
        canLogIn(user).its('status').should('eq', 401);
      });
    });

    it('MGR-UPD-06 the employee code cannot be edited', () => {
      makeUser().then((user) => {
        cy.loginByToken('manager', '/users');
        rowOf(user.email).find(`a[aria-label="Edit ${user.name}"]`).click();

        cy.get('#employeeCode').should('not.exist');
      });
    });
  });

  describe('deleting a user', () => {
    it('MGR-DEL-01 removes the user from the list, and they can no longer sign in', () => {
      makeUser().then((user) => {
        cy.loginByToken('manager', '/users');

        cy.acceptConfirm();
        rowOf(user.email).find(`button[aria-label="Delete ${user.name}"]`).click();

        cy.contains('tr', user.email).should('not.exist');
        canLogIn(user).its('status').should('eq', 401);
      });
    });

    it('MGR-DEL-03 does nothing when the confirmation is declined', () => {
      makeUser().then((user) => {
        cy.loginByToken('manager', '/users');

        cy.declineConfirm();
        rowOf(user.email).find(`button[aria-label="Delete ${user.name}"]`).click();

        cy.contains('tr', user.email).should('be.visible');
        canLogIn(user).its('status').should('eq', 200);
      });
    });

    it('MGR-DEL-02 takes the user\'s vacation requests with them', () => {
      makeUser().then((user) => {
        cy.createVacationViaApi(user, { from: isoInDays(40), to: isoInDays(42), reason: 'Gone with the user' });
        cy.loginByToken('manager', '/users');

        cy.acceptConfirm();
        rowOf(user.email).find(`button[aria-label="Delete ${user.name}"]`).click();
        cy.contains('tr', user.email).should('not.exist');

        cy.apiAs('manager', { method: 'GET', url: '/api/vacations?status=all' }).then(({ body }) => {
          expect(body.filter((vacation) => vacation.user_id === user.id)).to.have.length(0);
        });
      });
    });

    it('MGR-DEL-04 a manager cannot delete their own account', () => {
      cy.loginByToken('manager', '/users');

      cy.acceptConfirm();
      cy.contains('tr', 'manager@example.com').find('button[aria-label^="Delete"]').click();

      cy.get('[role="alert"]').should('contain.text', 'cannot delete your own account');
      cy.contains('tr', 'manager@example.com').should('be.visible');
    });
  });
});
