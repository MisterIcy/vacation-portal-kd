import { isoInDays } from '../../support/dates';

describe('Manager account › Deciding on vacation requests', () => {
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

  const submit = (reason) => cy.createVacationViaApi(employee, { from: isoInDays(30), to: isoInDays(34), reason });

  it('MGR-REQ-01 a new request shows up for the manager with who asked, the dates and the reason', () => {
    submit('Waiting for a decision');
    cy.loginByToken('manager', '/vacations');

    requestRow('Waiting for a decision').within(() => {
      cy.get('[data-label="Employee"]').should('have.text', employee.name);
      cy.get('[data-label="Total days"]').should('have.text', '5');
      cy.get('[data-label="Status"]').should('have.text', 'Pending');
      cy.contains('button', 'Approve').should('be.visible');
      cy.contains('button', 'Reject').should('be.visible');
    });
  });

  it('X-01 approving moves the request out of the pending list, and the employee sees it approved', () => {
    submit('To be approved');
    cy.intercept('PUT', '/api/vacations/*/approve').as('approve');
    cy.loginByToken('manager', '/vacations');

    requestRow('To be approved').contains('button', 'Approve').click();

    cy.wait('@approve').its('response.statusCode').should('eq', 200);
    cy.contains('To be approved').should('not.exist');

    cy.contains('button', 'Approved').click();
    requestRow('To be approved').find('[data-label="Status"]').should('have.text', 'Approved');

    cy.loginByToken(employee, '/vacations');
    requestRow('To be approved').find('[data-label="Status"]').should('have.text', 'Approved');
  });

  it('X-01 rejecting moves the request out of the pending list, and the employee sees it rejected', () => {
    submit('To be rejected');
    cy.intercept('PUT', '/api/vacations/*/reject').as('reject');
    cy.loginByToken('manager', '/vacations');

    requestRow('To be rejected').contains('button', 'Reject').click();

    cy.wait('@reject').its('response.statusCode').should('eq', 200);
    cy.contains('To be rejected').should('not.exist');

    cy.contains('button', 'Rejected').click();
    requestRow('To be rejected').find('[data-label="Status"]').should('have.text', 'Rejected');

    cy.loginByToken(employee, '/vacations');
    requestRow('To be rejected').find('[data-label="Status"]').should('have.text', 'Rejected');
  });

  it('MGR-REQ-04 a request someone else decided meanwhile is not decided twice, and the manager is told', () => {
    submit('Decided in another tab').then((vacation) => {
      cy.loginByToken('manager', '/vacations');
      requestRow('Decided in another tab').should('be.visible');

      // Another manager (or tab) rejects it while this page still shows it as pending.
      cy.decideViaApi(vacation.id, 'reject');
      requestRow('Decided in another tab').contains('button', 'Approve').click();

      cy.get('[role="alert"]').should('contain.text', 'Only pending vacations can be updated');
      cy.apiAs(employee, { method: 'GET', url: '/api/vacations' }).then(({ body }) => {
        expect(body.find((v) => v.id === vacation.id).status).to.eq('rejected');
      });
    });
  });

  it('MGR-REQ-06 records which manager decided', () => {
    submit('Who decided').then((vacation) => {
      cy.decideViaApi(vacation.id, 'approve').then(({ body }) => {
        cy.apiAs('manager', { method: 'GET', url: '/api/users' }).then(({ body: users }) => {
          const manager = users.find((user) => user.email === 'manager@example.com');

          expect(body.authorized_by).to.eq(manager.id);
        });
      });
    });
  });

  it('the history can be shown by status, and the pending filter is the default', () => {
    submit('History check').then((vacation) => {
      cy.decideViaApi(vacation.id, 'approve');
      cy.loginByToken('manager', '/vacations');

      cy.contains('button', 'Pending').should('have.attr', 'aria-pressed', 'true');
      cy.contains('History check').should('not.exist');

      cy.contains('button', 'All').click();
      requestRow('History check').should('be.visible');
      cy.contains('button', 'Rejected').click();
      cy.contains('History check').should('not.exist');
    });
  });

  it('MGR-NOTIFY the navigation counts the requests waiting for a decision', () => {
    submit('Counted in the badge');
    cy.loginByToken('manager', '/dashboard/manager');

    cy.contains('a', /Vacation Requests/).should('contain.text', 'pending request');
  });
});
