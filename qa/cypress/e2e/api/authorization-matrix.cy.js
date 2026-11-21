import { isoInDays } from '../../support/dates';

/**
 * Who may call what, checked at the API where the rules are enforced, not through the UI that hides the buttons.
 * Role is the row, endpoint is the column; ids that do not exist are fine where the role check comes first.
 */
describe('API › Authorization matrix', () => {
  const body = { from: isoInDays(30), to: isoInDays(31), reason: 'Matrix probe' };
  const newUser = { name: 'Probe', email: 'probe@example.com', employee_code: '7654321', password: 'Password123' };

  const endpoints = [
    // [label, method, url, body]
    ['list users', 'GET', '/api/users'],
    ['read a user', 'GET', '/api/users/1'],
    ['create a user', 'POST', '/api/users', newUser],
    ['update a user', 'PUT', '/api/users/1', { name: 'Changed' }],
    ['delete a user', 'DELETE', '/api/users/1'],
    ['list vacations', 'GET', '/api/vacations'],
    ['request a vacation', 'POST', '/api/vacations', body],
    ['cancel a vacation', 'DELETE', '/api/vacations/1'],
    ['approve a vacation', 'PUT', '/api/vacations/1/approve'],
    ['reject a vacation', 'PUT', '/api/vacations/1/reject'],
    ['renew a token', 'POST', '/api/auth/renew'],
  ];

  describe('nobody signed in', () => {
    endpoints.forEach(([label, method, url, payload]) => {
      it(`401 to ${label}`, () => {
        cy.apiAs(null, { method, url, body: payload }).then((response) => {
          expect(response.status).to.eq(401);
          expect(response.body).to.have.all.keys('status_code', 'reason_phrase');
        });
      });
    });

    it('401 to a token that is not one', () => {
      cy.request({ url: '/api/vacations', headers: { Authorization: 'Bearer not.a.token' }, failOnStatusCode: false })
        .its('status')
        .should('eq', 401);
    });

    it('401 to a request whose authorization is not a bearer token', () => {
      cy.request({ url: '/api/vacations', headers: { Authorization: 'Basic abc' }, failOnStatusCode: false })
        .its('status')
        .should('eq', 401);
    });
  });

  describe('an employee', () => {
    // Everything about users, and deciding, is for managers only.
    [
      ['list users', 'GET', '/api/users'],
      ['read a user', 'GET', '/api/users/1'],
      ['create a user', 'POST', '/api/users', newUser],
      ['update a user', 'PUT', '/api/users/1', { name: 'Changed' }],
      ['delete a user', 'DELETE', '/api/users/1'],
      ['approve a vacation', 'PUT', '/api/vacations/1/approve'],
      ['reject a vacation', 'PUT', '/api/vacations/1/reject'],
    ].forEach(([label, method, url, payload]) => {
      it(`403 to ${label}, and no data in the answer`, () => {
        cy.apiAs('employee', { method, url, body: payload }).then((response) => {
          expect(response.status).to.eq(403);
          expect(response.body).to.have.all.keys('status_code', 'reason_phrase');
        });
      });
    });

    it('can list their own vacations and renew their token', () => {
      cy.apiAs('employee', { method: 'GET', url: '/api/vacations' }).its('status').should('eq', 200);
      cy.apiAs('employee', { method: 'POST', url: '/api/auth/renew' }).its('status').should('eq', 200);
    });
  });

  describe('a manager', () => {
    // Managers decide on requests; they do not make or cancel them.
    [
      ['request a vacation', 'POST', '/api/vacations', body],
      ['cancel a vacation', 'DELETE', '/api/vacations/1'],
    ].forEach(([label, method, url, payload]) => {
      it(`403 to ${label}`, () => {
        cy.apiAs('manager', { method, url, body: payload }).its('status').should('eq', 403);
      });
    });

    it('can list users and vacations', () => {
      cy.apiAs('manager', { method: 'GET', url: '/api/users' }).its('status').should('eq', 200);
      cy.apiAs('manager', { method: 'GET', url: '/api/vacations' }).its('status').should('eq', 200);
    });
  });

  describe('somebody else\'s data', () => {
    let owner;
    let other;
    let vacation;

    beforeEach(() => {
      cy.createUserViaApi().then((user) => {
        owner = user;
        cy.createVacationViaApi(owner, { from: isoInDays(30), to: isoInDays(32), reason: 'Owner only' }).then((created) => {
          vacation = created;
        });
      });
      cy.createUserViaApi().then((user) => {
        other = user;
      });
    });

    afterEach(() => {
      cy.deleteUserViaApi(owner);
      cy.deleteUserViaApi(other);
    });

    it('EMP-LST-SEC an employee\'s list holds only their own requests', () => {
      cy.apiAs(other, { method: 'GET', url: '/api/vacations?status=all' }).then(({ body: list }) => {
        expect(list.map((v) => v.reason)).to.not.include('Owner only');
      });
    });

    it('EMP-DEL-03 cancelling another employee\'s request is a 404, the same as for one that does not exist', () => {
      cy.apiAs(other, { method: 'DELETE', url: `/api/vacations/${vacation.id}` }).its('status').should('eq', 404);
      cy.apiAs(other, { method: 'DELETE', url: '/api/vacations/999999999' }).its('status').should('eq', 404);
      cy.apiAs(owner, { method: 'GET', url: '/api/vacations' }).its('body').should('have.length', 1);
    });
  });

  describe('sessions that no longer match the account', () => {
    it('X-02 the token of a deleted user stops working at once', () => {
      cy.createUserViaApi().then((user) => {
        cy.apiToken(user).then((token) => {
          cy.deleteUserViaApi(user);

          cy.request({ url: '/api/vacations', headers: { Authorization: `Bearer ${token}` }, failOnStatusCode: false })
            .its('status')
            .should('eq', 401);
        });
      });
    });

    it('X-03 a demoted manager loses manager rights with the token they already hold', () => {
      cy.createUserViaApi({ role: 'manager' }).then((user) => {
        cy.apiToken(user).then((token) => {
          const asThem = { url: '/api/users', headers: { Authorization: `Bearer ${token}` }, failOnStatusCode: false };
          cy.request(asThem).its('status').should('eq', 200);

          cy.apiAs('manager', { method: 'PUT', url: `/api/users/${user.id}`, body: { role: 'employee' } });

          cy.request(asThem).its('status').should('eq', 403);
          cy.deleteUserViaApi(user);
        });
      });
    });
  });

  describe('what the API says back', () => {
    it('X-05 never includes a password, hashed or not', () => {
      cy.apiAs('manager', { method: 'GET', url: '/api/users' }).then(({ body: users }) => {
        users.forEach((user) => expect(Object.keys(user).join()).to.not.match(/pass/i));
        expect(JSON.stringify(users)).to.not.contain('$argon2');
      });
    });

    it('X-08 a body larger than a few kilobytes is refused with 413 before the application reads it', () => {
      const huge = { email: 'a@b.c', password: 'a'.repeat(20000) };

      cy.request({ method: 'POST', url: '/api/auth/login', body: huge, failOnStatusCode: false }).its('status').should('eq', 413);
      cy.apiAs('manager', { method: 'POST', url: '/api/users', body: { name: 'x'.repeat(20000) } }).its('status').should('eq', 413);
      // A normal request is unaffected.
      cy.apiAs('employee', { method: 'GET', url: '/api/vacations' }).its('status').should('eq', 200);
    });

    it('X-04 an error carries a status and a phrase, and nothing about the server inside', () => {
      cy.apiAs('manager', { method: 'GET', url: '/api/users/999999999' }).then((response) => {
        expect(response.status).to.eq(404);
        expect(response.body).to.have.all.keys('status_code', 'reason_phrase');
        expect(JSON.stringify(response.body)).to.not.match(/\.php|Stack trace|PDO|SQLSTATE/i);
      });
    });

    it('answers security headers on every response', () => {
      cy.apiAs(null, { method: 'GET', url: '/api/users' }).then(({ headers }) => {
        expect(headers['x-content-type-options']).to.eq('nosniff');
        expect(headers).to.have.property('x-frame-options');
      });
    });
  });

  describe('the rules of a vacation request, at the API', () => {
    let employee;

    beforeEach(() => {
      cy.createUserViaApi().then((user) => {
        employee = user;
      });
    });

    afterEach(() => {
      cy.deleteUserViaApi(employee);
    });

    const create = (payload) => cy.apiAs(employee, { method: 'POST', url: '/api/vacations', body: payload });

    it('EMP-CRT-04 accepts sixty days and refuses sixty-one', () => {
      create({ from: isoInDays(30), to: isoInDays(89), reason: 'Sixty' }).its('status').should('eq', 201);
      create({ from: isoInDays(100), to: isoInDays(160), reason: 'Sixty one' }).its('status').should('eq', 400);
    });

    it('EMP-CRT-06 accepts a 255 character reason and refuses 256 with a clear message, not a server error', () => {
      create({ from: isoInDays(30), to: isoInDays(31), reason: 'x'.repeat(255) }).its('status').should('eq', 201);
      create({ from: isoInDays(40), to: isoInDays(41), reason: 'x'.repeat(256) }).then((response) => {
        expect(response.status).to.eq(400);
        expect(response.body.reason_phrase).to.contain('255');
      });
    });

    it('EMP-CRT-11 accepts a reason on several lines and refuses one with a control character', () => {
      create({ from: isoInDays(30), to: isoInDays(31), reason: 'First line\nSecond line' }).its('status').should('eq', 201);
      create({ from: isoInDays(40), to: isoInDays(41), reason: 'Trip\u0000' }).then((response) => {
        expect(response.status).to.eq(400);
        expect(response.body.reason_phrase).to.contain('control characters');
      });
    });

    it('MGR-CRT-07 a name must be one line, when creating and when updating a user', () => {
      const n = Cypress._.random(1000000, 9999999);
      const user = { name: 'Line\r\nBreak', email: `qa.line.${n}@example.com`, employee_code: String(n), password: 'Password123' };
      cy.apiAs('manager', { method: 'POST', url: '/api/users', body: user }).its('status').should('eq', 400);
      cy.apiAs('manager', { method: 'PUT', url: `/api/users/${employee.id}`, body: { name: 'Line\nBreak' } }).its('status').should('eq', 400);
    });

    it('EMP-CRT-07 refuses a start date in the past', () => {
      create({ from: isoInDays(-5), to: isoInDays(-3), reason: 'Past' }).its('status').should('eq', 400);
    });

    it('EMP-CRT-08 refuses dates that overlap a pending request, and allows the day after it ends', () => {
      create({ from: isoInDays(30), to: isoInDays(35), reason: 'Existing' }).its('status').should('eq', 201);
      create({ from: isoInDays(35), to: isoInDays(36), reason: 'Shares the last day' }).its('status').should('eq', 409);
      create({ from: isoInDays(36), to: isoInDays(37), reason: 'Starts after' }).its('status').should('eq', 201);
    });

    [
      ['text', 'abc'],
      ['a relative date', '+1 year'],
      ['a day that does not exist', '2030-02-30'],
      ['the wrong format', '01/07/2030'],
    ].forEach(([label, value]) => {
      it(`EMP-CRT-09 refuses ${label} as a date with a 400`, () => {
        create({ from: value, to: isoInDays(31), reason: 'Bad date' }).its('status').should('eq', 400);
      });
    });

    it('refuses a missing field and a body that is not JSON, with a 400', () => {
      create({ from: isoInDays(30), reason: 'No end' }).its('status').should('eq', 400);
      cy.apiAs(employee, { method: 'POST', url: '/api/vacations', body: '{not json', headers: { 'Content-Type': 'application/json' } })
        .its('status')
        .should('eq', 400);
    });

    it('MGR-REQ-08 a user promoted to manager cannot decide on their own pending request', () => {
      create({ from: isoInDays(30), to: isoInDays(31), reason: 'Asked before being promoted' }).then(({ body: vacation }) => {
        cy.apiAs('manager', { method: 'PUT', url: `/api/users/${employee.id}`, body: { role: 'manager' } }).its('status').should('eq', 200);

        ['approve', 'reject'].forEach((decision) => {
          cy.apiAs(employee, { method: 'PUT', url: `/api/vacations/${vacation.id}/${decision}` }).then((response) => {
            expect(response.status).to.eq(403);
            expect(response.body.reason_phrase).to.contain('your own');
          });
        });

        cy.apiAs('manager', { method: 'GET', url: '/api/vacations?status=pending' }).then(({ body: pending }) => {
          expect(pending.map((v) => v.id)).to.include(vacation.id);
        });
        // Someone else still can.
        cy.decideViaApi(vacation.id, 'approve').its('status').should('eq', 200);
      });
    });

    it('MGR-REQ-04 a request can be decided once; the second decision is a 409 and changes nothing', () => {
      create({ from: isoInDays(30), to: isoInDays(31), reason: 'Decide once' }).then(({ body: vacation }) => {
        cy.decideViaApi(vacation.id, 'approve').its('status').should('eq', 200);
        cy.decideViaApi(vacation.id, 'reject').its('status').should('eq', 409);
        cy.apiAs(employee, { method: 'GET', url: '/api/vacations' }).then(({ body: list }) => {
          expect(list[0].status).to.eq('approved');
        });
      });
    });

    it('EMP-DEL-02 a decided request cannot be cancelled: 409', () => {
      create({ from: isoInDays(30), to: isoInDays(31), reason: 'Keep me' }).then(({ body: vacation }) => {
        cy.decideViaApi(vacation.id, 'approve');

        cy.apiAs(employee, { method: 'DELETE', url: `/api/vacations/${vacation.id}` }).its('status').should('eq', 409);
      });
    });

    it('deciding on a request that does not exist is a 404', () => {
      cy.decideViaApi(999999999, 'approve').its('status').should('eq', 404);
    });

    it('listing with a status it does not know is a 400', () => {
      cy.apiAs('manager', { method: 'GET', url: '/api/vacations?status=bogus' }).its('status').should('eq', 400);
    });
  });
});
