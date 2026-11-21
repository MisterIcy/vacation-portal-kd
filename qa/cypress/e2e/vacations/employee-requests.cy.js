import { isoInDays } from '../../support/dates';

describe('Employee account › Vacation requests', () => {
  // A new employee for every test: a clean list, and no overlap with anything another test requested.
  let employee;

  beforeEach(() => {
    cy.createUserViaApi().then((user) => {
      employee = user;
    });
  });

  afterEach(() => {
    cy.deleteUserViaApi(employee);
  });

  const requestRow = (reason) => cy.contains('tr', reason);

  describe('the list of my requests', () => {
    it('EMP-LST-05 invites a new employee to make a first request', () => {
      cy.loginByToken(employee, '/dashboard');

      cy.contains('You have not requested any vacation yet').should('be.visible');
      cy.contains('a', 'Request New Vacation').should('be.visible');
    });

    it('EMP-LST-02..04 shows when it was submitted, the dates asked for, the days and the status', () => {
      cy.createVacationViaApi(employee, { from: isoInDays(30), to: isoInDays(34), reason: 'Listed with all details' });
      cy.loginByToken(employee, '/vacations');

      cy.get('thead').within(() => {
        ['Date submitted', 'Dates requested', 'Reason', 'Total days', 'Status'].forEach((heading) =>
          cy.contains('th', heading).should('be.visible'),
        );
      });
      requestRow('Listed with all details').within(() => {
        cy.get('[data-label="Total days"]').should('have.text', '5');
        cy.get('[data-label="Status"]').should('have.text', 'Pending');
        cy.get('[data-label="Date submitted"]').invoke('text').should('match', /\d/);
        cy.get('[data-label="Dates requested"]').invoke('text').should('contain', '–');
      });
    });

    it('EMP-LST-01 shows only my own requests', () => {
      cy.createVacationViaApi(employee, { from: isoInDays(30), to: isoInDays(31), reason: 'Mine alone' });
      // The seeded employee is someone else here. A random far-away week keeps reruns from overlapping.
      const week = 200 + Cypress._.random(0, 100);
      cy.createVacationViaApi('employee', { from: isoInDays(week), to: isoInDays(week + 1), reason: 'Seeded employee, not mine' }).then(
        (other) => {
          cy.loginByToken(employee, '/vacations');

          requestRow('Mine alone').should('be.visible');
          cy.contains('Seeded employee, not mine').should('not.exist');

          cy.apiAs('employee', { method: 'DELETE', url: `/api/vacations/${other.id}` });
        },
      );
    });
  });

  describe('making a request', () => {
    it('EMP-CRT-01 submits a date range and a reason, and it appears as pending', () => {
      cy.intercept('POST', '/api/vacations').as('createVacation');
      cy.loginByToken(employee, '/vacations/new');

      cy.get('#startDate').type(isoInDays(30));
      cy.get('#endDate').type(isoInDays(33));
      cy.contains('Total days: 4').should('be.visible');
      cy.get('#reason').type('Created through the form');
      cy.contains('button', /submit|request/i).click();

      cy.wait('@createVacation').its('response.statusCode').should('eq', 201);
      cy.location('pathname').should('eq', '/vacations');
      requestRow('Created through the form').within(() => {
        cy.get('[data-label="Status"]').should('have.text', 'Pending');
        cy.get('[data-label="Total days"]').should('have.text', '4');
      });
    });

    it('EMP-CRT-03 a single day is a valid request', () => {
      cy.loginByToken(employee, '/vacations/new');

      cy.get('#startDate').type(isoInDays(30));
      cy.get('#endDate').type(isoInDays(30));
      cy.get('#reason').type('Just the one day');
      cy.contains('button', /submit|request/i).click();

      requestRow('Just the one day').find('[data-label="Total days"]').should('have.text', '1');
    });

    it('EMP-CRT-02 an end date before the start date is stopped by the form', () => {
      cy.intercept('POST', '/api/vacations').as('createVacation');
      cy.loginByToken(employee, '/vacations/new');

      cy.get('#startDate').type(isoInDays(35));
      cy.get('#endDate').type(isoInDays(30));
      cy.get('#reason').type('Backwards');
      cy.contains('button', /submit|request/i).click();

      cy.get('#endDate').then(($input) => expect($input[0].validity.rangeUnderflow).to.be.true);
      cy.location('pathname').should('eq', '/vacations/new');
      cy.get('@createVacation.all').should('have.length', 0);
    });

    it('EMP-CRT-05 a reason is required', () => {
      cy.intercept('POST', '/api/vacations').as('createVacation');
      cy.loginByToken(employee, '/vacations/new');

      cy.get('#startDate').type(isoInDays(30));
      cy.get('#endDate').type(isoInDays(31));
      cy.contains('button', /submit|request/i).click();

      cy.get('#reason').then(($input) => expect($input[0].validity.valueMissing).to.be.true);
      cy.get('@createVacation.all').should('have.length', 0);
    });

    it('EMP-CRT-08 dates that overlap an earlier request are refused and the form says why', () => {
      cy.createVacationViaApi(employee, { from: isoInDays(30), to: isoInDays(35), reason: 'First one' });
      cy.loginByToken(employee, '/vacations/new');

      cy.get('#startDate').type(isoInDays(33));
      cy.get('#endDate').type(isoInDays(38));
      cy.get('#reason').type('Overlapping one');
      cy.contains('button', /submit|request/i).click();

      cy.get('[role="alert"]').should('contain.text', 'overlap');
      cy.location('pathname').should('eq', '/vacations/new');
    });

    it('EMP-CRT-10 clicking submit twice quickly makes one request', () => {
      cy.intercept('POST', '/api/vacations').as('createVacation');
      cy.loginByToken(employee, '/vacations/new');

      cy.get('#startDate').type(isoInDays(30));
      cy.get('#endDate').type(isoInDays(31));
      cy.get('#reason').type('Double click');
      cy.contains('button', /submit|request/i).dblclick();

      cy.wait('@createVacation');
      cy.apiAs(employee, { method: 'GET', url: '/api/vacations' }).then(({ body }) => {
        expect(body.filter((vacation) => vacation.reason === 'Double click')).to.have.length(1);
      });
    });
  });

  describe('cancelling a request', () => {
    it('EMP-DEL-01 removes a pending request after confirmation', () => {
      cy.createVacationViaApi(employee, { from: isoInDays(30), to: isoInDays(31), reason: 'To be cancelled' });
      cy.loginByToken(employee, '/vacations');

      cy.acceptConfirm();
      requestRow('To be cancelled').contains('button', 'Delete').click();

      cy.contains('To be cancelled').should('not.exist');
    });

    it('EMP-DEL-04 keeps the request when the confirmation is declined', () => {
      cy.createVacationViaApi(employee, { from: isoInDays(30), to: isoInDays(31), reason: 'Not cancelled' });
      cy.loginByToken(employee, '/vacations');

      cy.declineConfirm();
      requestRow('Not cancelled').contains('button', 'Delete').click();

      requestRow('Not cancelled').should('be.visible');
    });

    it('EMP-DEL-02 a decided request cannot be deleted', () => {
      cy.createVacationViaApi(employee, { from: isoInDays(30), to: isoInDays(31), reason: 'Already approved' }).then((vacation) => {
        cy.decideViaApi(vacation.id, 'approve');
        cy.loginByToken(employee, '/vacations');

        requestRow('Already approved').within(() => {
          cy.get('[data-label="Status"]').should('have.text', 'Approved');
          cy.contains('button', 'Delete').should('not.exist');
        });
      });
    });
  });
});
