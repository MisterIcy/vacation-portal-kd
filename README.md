# Vacation Portal

Please review the [Assignment](Assignment.md) document for the detailed business requirements of this assignment.

## Getting Started

1. **Environment Setup**
   - Copy the example environment file to create your own configuration:
     ```sh
     cp .env.example .env
     ```
   - Edit `.env` and update the values as needed for your local setup.
   - For a standard Docker setup, copying `.env.example` as-is is enough.
2. **Project Lifecycle Commands**
  - Use the provided `run` script to manage the project:
    - **Build the project (install dependencies, migrate, seed):**
      ```sh
      ./run build
      ```
    - **Start the project:**
      ```sh
      ./run start
      ```
    - **Stop and remove containers and volumes:**
      ```sh
      ./run teardown
      ```
    - **Run backend checks:**
      ```sh
      ./run check-be
      ```
    - **Run frontend checks:**
      ```sh
      ./run check-fe
      ```
    - **Run QA / Cypress checks:**
      ```sh
      ./run check-qa
      ```

You will need to first execute the `./run build` command before starting the project for the first time. Then you can
use the `./run start` command to start the project. The `./run teardown` command can be used to stop and remove all
containers and volumes (clean-up). Finally, while the project is running, you can use the `./run check-xx` command to
run the automated checks.

3. **You can now visit the URL**: `http://localhost:5173` and access the project.

The following test users can be used to log in:

| Role     | Email                  | Password         | Name          |
|----------|------------------------|------------------|---------------|
| Manager  | manager@example.com    | managerpass123   | Manager User  |
| Employee | employee@example.com   | employeepass123  | Employee User |

4. **Manually testing the API**
   - Example login request:
     ```sh
     curl -v -X POST http://localhost:8080/auth/login \
       -H "Content-Type: application/json" \
       -d '{"email": "manager@example.com", "password": "managerpass123"}'
     ```
   - Alternatively you can use the `./test_routes.sh` script inside the `be` folder to test all the API routes.

Refer to the `run` script for more details on each command.

## Troubleshooting

- **Login returns a 500 right after `./run build`.** Almost always a MySQL
  init race, not a bug in your code: the db container can report healthy
  before its entrypoint finishes creating the app database and user, so the
  backend starts against a half-initialized database (look for
  `Access denied for user 'vacation'@'...'` in the `vacation_be` logs).
  Fix by rebuilding from a clean state:
  ```sh
  ./run teardown && ./run build
  ```
  The same `Access denied` error appears if you change `DB_USERNAME` or
  `DB_PASSWORD` in `.env` after the database was first created: MySQL only
  applies those values when it initialises an empty volume. Run
  `./run teardown` (which deletes the database volume) before building again
  whenever you change them.

### Runbooks (local environment)

Short, step-by-step procedures for situations that come up while working on
the project. Commands assume a POSIX shell (Git Bash on Windows).

**Reset to a clean state** (all data is deleted and the two test users are
re-seeded)

1. `./run teardown` removes the containers and the database volume.
2. `./run build` rebuilds the images, runs the migrations and the seed, then
   stops the stack.
3. `./run start` starts it again.
4. Check: <http://localhost:5173> loads and `manager@example.com` can log in.

**Back up the database**

```sh
docker exec vacation_db sh -c 'mysqldump --single-transaction -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' > backup.sql
```

The dump contains personal data and password hashes. Keep it out of git and
out of shared or synced folders.

**Restore the database from a backup** (replaces the current data)

1. Make sure the stack is running (`./run start`).
2. Load the dump:
   ```sh
   docker exec -i vacation_db sh -c 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' < backup.sql
   ```
3. Check: log in and confirm the users and vacation requests you expect. A
   backup that has never been restored has not been proven to work, so try
   this once.

**The stack won't start because a port is in use**

The project uses ports 5173 (UI), 8080 (API) and 3306 (MySQL). Stop whatever
holds the port (a local MySQL is the usual cause), or run `./run teardown` if
an earlier copy of this stack is still running, then `./run start`.

**The API returns 5xx errors**

1. `docker logs vacation_be --tail 50` shows the web server output.
2. `docker compose -p vacation-portal ps` shows whether `vacation_db` is up
   and healthy.
3. If the logs mention `Access denied` or an unknown database, follow the
   MySQL init race entry above.

## Delivery and operations (proposed design)

The project runs locally with Docker Compose. Nothing is deployed, so this
section describes how it would be operated as an internal tool and the
runbooks that would back it. It is a design, not an implemented pipeline.

**Release flow:** every change goes through a pull request with the automated
checks (`composer check:all`, `npm run validate`, the test suites, and the
Cypress journeys). Merged changes are built into one image per service, tagged
with the git commit, and promoted from staging to production after approval.

**Runbooks the production setup would need**

| Situation | Procedure in short |
|---|---|
| Deploy | Run database migrations first (backward compatible with the running version), then roll out the new images, wait for the health check, then run a smoke test. |
| Roll back | Redeploy the previous image tag. Because migrations only add things, the database needs no change. |
| Restore the database | Restore the latest snapshot to a new instance, or to a point in time, check the data, then switch the application to it. Practise this regularly. |
| Rotate `JWT_SECRET` | Changing the secret signs everyone out (tokens last one hour). To avoid that, the application would accept two keys during the switch. |
| Offboard a user | A manager deletes the account, which also deletes their vacation requests. With the current design an existing login token keeps working until it expires. Checking the user in the database on each request closes that gap. |
| Add the first manager | The seed creates the initial manager. Further managers are created by an existing manager through the user form. |
| API errors | Check the health endpoint, the application logs and the database connection, in that order. |

**Configuration:** the application checks its settings when it starts and
refuses to run if one is missing, naming the setting (never its value). A
`.env` file is optional, so a deployment can provide the variables directly.
A `JWT_SECRET` that is the placeholder from `.env.example`, or shorter than 32
bytes, only logs a warning while developing, but stops the application when
`APP_ENV=production` is set. Generate a real one with `openssl rand -base64 48`.

**Decision notifications:** when a manager approves or rejects a request, the
employee is notified by email. No mail provider is used: each email is written
as its own `.eml` file, by default in `<system temp dir>/vacation-portal/notifications`
(inside the container: `docker exec vacation_be ls /tmp/vacation-portal/notifications`).
Set `NOTIFICATIONS_DIR` to use another directory and `MAIL_FROM` to change the
sender. The `PUT /vacations/{id}/approve` and `/reject` calls accept an optional
JSON body `{"message": "..."}` (up to 500 characters) that is included in the
email. A failed write is retried up to three times and then logged; it never
changes the manager's response, because the decision is already stored. In
production the same events would feed an outbox and a real provider.

**Database users:** the application and the migrations should not share one
account. The migrations need to change the schema; the application only needs
to read and write rows, so that a flaw such as a SQL injection could not drop
or alter tables. Migrations therefore use `DB_MIGRATION_USERNAME` and
`DB_MIGRATION_PASSWORD` when they are set, and fall back to `DB_USERNAME` and
`DB_PASSWORD` when they are not, so the setup described above keeps working
unchanged. In production, create two users: one with schema privileges for the
migration step of the deployment, and one with only `SELECT`, `INSERT`,
`UPDATE` and `DELETE` on the application's schema for the running service.

**Observability:** logs written to standard output, an uptime check on a health
endpoint, an alert on the rate of 5xx responses, and daily database backups
with point-in-time recovery.

## Repository layout

| Folder | Purpose | More info |
|--------|---------|-----------|
| `be/` | REST API, database migrations, and seeds | [be/README.md](be/README.md) |
| `fe/` | React UI served at http://localhost:5173 | [fe/README.md](fe/README.md) |
| `qa/` | Cypress E2E tests | [qa/README.md](qa/README.md) |

