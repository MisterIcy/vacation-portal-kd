describe('Authentication › Logging in and out', () => {
  beforeEach(() => {
    cy.intercept('POST', '/api/auth/login').as('login');
  });

  const signIn = (email, password) => {
    cy.visit('/login');
    cy.get('#email').type(email);
    cy.get('#password').type(password);
    cy.get('button[type="submit"]').click();
  };

  it('AUTH-01 a manager signs in and lands on the manager home', () => {
    cy.fixture('users').then(({ manager }) => {
      signIn(manager.email, manager.password);

      cy.wait('@login').its('response.statusCode').should('eq', 200);
      cy.location('pathname').should('eq', '/dashboard/manager');
      cy.contains('h2', 'Manager Dashboard').should('be.visible');
      cy.contains(manager.name).should('be.visible');
    });
  });

  it('AUTH-02 an employee signs in and lands on the employee home', () => {
    cy.fixture('users').then(({ employee }) => {
      signIn(employee.email, employee.password);

      cy.wait('@login').its('response.statusCode').should('eq', 200);
      cy.location('pathname').should('eq', '/dashboard');
      cy.contains(employee.name).should('be.visible');
    });
  });

  it('AUTH-03 a wrong password is refused without saying which part was wrong', () => {
    cy.fixture('users').then(({ employee }) => {
      signIn(employee.email, 'not-the-password');

      cy.wait('@login').its('response.statusCode').should('eq', 401);
      cy.get('[role="alert"]').should('contain.text', 'Invalid credentials');
      cy.location('pathname').should('eq', '/login');
      cy.window().its('localStorage.token').should('not.exist');
    });
  });

  it('AUTH-03 an unknown email gets exactly the same answer as a wrong password', () => {
    signIn('nobody@example.com', 'whatever123');

    cy.wait('@login').its('response.statusCode').should('eq', 401);
    cy.get('[role="alert"]').should('contain.text', 'Invalid credentials');
  });

  it('AUTH-04 empty fields are stopped by the form before anything is sent', () => {
    cy.visit('/login');
    cy.get('button[type="submit"]').click();

    cy.get('#email').then(($email) => expect($email[0].validity.valueMissing).to.be.true);
    cy.location('pathname').should('eq', '/login');
    cy.get('@login.all').should('have.length', 0);
  });

  it('AUTH-05 signing out clears the session and protected pages need a new login', () => {
    cy.loginByToken('employee', '/dashboard');

    cy.contains('button', 'Logout').click();

    cy.location('pathname').should('eq', '/login');
    cy.window().its('localStorage.token').should('not.exist');
    cy.visit('/vacations');
    cy.location('pathname').should('eq', '/login');
  });

  it('AUTH-06 opening a protected page while signed out leads to the login page', () => {
    cy.visit('/users');

    cy.location('pathname').should('eq', '/login');
  });

  it('AUTH-07 an employee who opens a manager page is sent to their own home', () => {
    cy.loginByToken('employee', '/users');

    cy.location('pathname').should('eq', '/dashboard');
    cy.contains('Manage Users').should('not.exist');
  });

  it('AUTH-07 a manager who opens the request form is sent to their own home', () => {
    cy.loginByToken('manager', '/vacations/new');

    cy.location('pathname').should('eq', '/dashboard/manager');
  });

  it('AUTH-08 a token that is not a real token does not keep anyone signed in', () => {
    cy.visit('/login', {
      onBeforeLoad(win) {
        win.localStorage.setItem('token', 'not.a.token');
        win.localStorage.setItem('user', JSON.stringify({ id: 1, name: 'X', email: 'x@example.com', role: 'manager' }));
      },
    });
    cy.visit('/users');

    cy.location('pathname').should('eq', '/login');
    cy.window().its('localStorage.token').should('not.exist');
  });
});
