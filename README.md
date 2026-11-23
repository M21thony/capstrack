# CapsTrack

CapsTrack is the capstone tracking system of the College of Computer Studies, CSPC.
Students, experts (capstone subject advisers, capstone adviser, consultant), the CaPSA/TSA and the admin use it for teams,
document submissions, title duplication checks, defense schedules, grading and the
capstone archive.

It runs with Docker. Everything it needs is in this repository except the `.env` file,
which is sent separately.

## What the server needs

- Docker and Docker Compose
- Git
- About 10 GB of free disk for the images and the AI models, plus room for uploaded files
- One open port for the site (80 by default)

## Starting it

1. Clone the repository and go into the folder.

   ```bash
   git clone <repository address> capstrack
   cd capstrack
   ```

2. Put the `.env` file in this folder. The name must be exactly `.env`.

3. Open `.env` and set these for your server:

   | Setting | What to put |
   |---|---|
   | `APP_URL` | The address people will open, for example `https://capstrack.cspc.edu.ph` |
   | `GOOGLE_REDIRECT_URI` | `APP_URL` followed by `/login/google/callback` |
   | `APP_PORT` | The port on the server. Leave it out to use 80 |

   The passwords in the file (`DB_PASSWORD`, `DB_ROOT_PASSWORD`, `SILO_ACCESS_KEY_ID`,
   `SILO_SECRET_ACCESS_KEY`) can be changed to your own before the first start. After the
   first start they are already stored in the database and storage containers, so do not
   change them later.

4. Build and start.

   ```bash
   docker compose up -d --build
   ```

5. Download the two AI models. This is done once. They are about 3.6 GB together.

   ```bash
   docker compose exec capstrack-ollama ollama pull qwen3.5:4b-q4_K_M
   ```

   ```bash
   docker compose exec capstrack-ollama ollama pull nomic-embed-text
   ```

6. Open the address in a browser. The sign-in page should show.

The first start creates the database tables by itself. Nothing has to be imported.

## The containers

| Container | What it is |
|---|---|
| `capstrack-system` | The site itself: nginx, PHP 8.4, the queue worker and the scheduler |
| `capstrack-db` | MySQL 8.4 |
| `capstrack-ollama` | The AI used for the title duplication check and document checks |
| `capstrack-storage` | Storage for uploaded files (S3 compatible) |

Only `capstrack-system` is reachable from outside. The other three are reached only by it.

The site container serves plain HTTP. HTTPS is expected to come from the server or proxy
in front of it.

## Accounts on first start

Sign-in is by Google only, there are no passwords. These accounts exist after the first start:

| Role | Google account |
|---|---|
| Admin | `ccs@cspc.edu.ph` |
| Admin | `markanthonybalilla@gmail.com` |
| CaPSA/TSA | `hellscreamgrom9@gmail.com` |
| Expert | `markanthonybalilla21@gmail.com` |
| Student | `mabalilla@my.cspc.edu.ph` |

Other accounts are added from inside the system by the admin and the CaPSA/TSA.

## Needed outside the server

Google sign-in and Google Drive need a Google Cloud project.
Make an OAuth client (Web application) and add two Authorized redirect URIs:

- `APP_URL/login/google/callback` for signing in
- `APP_URL/admin/gdrive/callback` for connecting Google Drive

Turn on the Google Drive API and Google Picker API in the same project and
make an API key. Put the client ID, client secret and API key in `.env`.

The sign-in page also needs reCAPTCHA v3 keys for the server's domain
(google.com/recaptcha/admin).

Nobody can sign in until both are set.

## Updating

```bash
git pull
```

```bash
docker compose up -d --build
```

Database changes are applied by themselves on start. Data is kept.

### On a laptop that also has XAMPP or WAMP

There the `.env` file belongs to XAMPP, so Docker's settings are kept in `.env.docker`
(Docker's database passwords, `APP_PORT=8080` and `APP_URL=http://localhost:8080`).
Always name that file when starting or updating:

```bash
docker compose --env-file .env.docker up -d --build
```

Without it the system starts with XAMPP's database settings and keeps restarting
("Access denied for user 'root'"). Running the command above again fixes it. Data is kept.
The system then opens at http://localhost:8080.

## Backup

The data lives in Docker volumes: `db-data` (database), `storage-data` (uploaded files),
`app-storage` (logs and cache) and `ollama-models` (the AI models, can be downloaded again).

Database:

```bash
docker compose exec capstrack-db sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' > capstrack.sql
```

Uploaded files, where `capstrack` is the name of the folder the repository is in:

```bash
docker run --rm -v capstrack_storage-data:/data -v "$PWD":/backup alpine tar czf /backup/capstrack-files.tar.gz -C /data .
```

## Checking and stopping

```bash
docker compose ps
```

```bash
docker compose logs -f capstrack-system
```

```bash
docker compose down
```

`docker compose down` stops the containers and keeps the data. Do not add `-v`, that deletes the data.
