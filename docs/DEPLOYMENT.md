# Deploying CapsTrack

The website goes on one Azure virtual machine (a rented Linux computer). The
AI goes on a RunPod machine with a graphics card, switched on only while it
is needed. The files in `deploy/` are the ready-made settings this guide
refers to.

Anything with a password, a payment or a sign-in is yours to type. Never
paste secrets into a chat.

---

## 1. Create the Azure machine

1. Activate Azure for Students (GitHub Student Pack > Microsoft Azure).
2. Azure portal > **Virtual machines > Create**.
   - Image: **Ubuntu Server 24.04 LTS**
   - Size: **B2s** (2 CPUs, 4 GB). B1s (1 GB) is free for 12 months but
     too small for MySQL plus the site build.
   - Authentication: **SSH public key**. Download the key file it gives you.
   - Inbound ports: **SSH (22), HTTP (80), HTTPS (443)**.
3. After it is created: the machine's **Overview > DNS name > Configure**,
   pick a label. Your address becomes
   `<label>.<region>.cloudapp.azure.com`. That is your `<your-domain>` below.

Log in from your laptop:

```bash
ssh -i path/to/key.pem azureuser@<your-domain>
```

## 2. Install what the site needs (on the Azure machine)

```bash
sudo apt update && sudo apt upgrade -y
# Ubuntu ships PHP 8.3; composer.lock needs 8.4.
sudo add-apt-repository -y ppa:ondrej/php && sudo apt update
sudo apt install -y nginx mysql-server supervisor git unzip certbot python3-certbot-nginx \
  php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip \
  php8.4-gd php8.4-intl php8.4-bcmath php8.4-gmp
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo bash - && sudo apt install -y nodejs
```

## 3. Database

```bash
sudo mysql -e "CREATE DATABASE capstrack CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'capstrack'@'localhost' IDENTIFIED BY '<strong-password>';
GRANT ALL ON capstrack.* TO 'capstrack'@'localhost';"
```

Bring the data over from the laptop. Make a **fresh** export (the old
`capstrackdb.sql` is from 17 September). On the laptop, in the project folder:

```powershell
# Laptop, PowerShell (WAMP's MySQL). --result-file, not >, because
# PowerShell's > writes UTF-16 and the import then fails.
& "C:\wamp64\bin\mysql\mysql8.4.7\bin\mysqldump.exe" -u root --default-character-set=utf8mb4 --set-gtid-purged=OFF --result-file=capstrack-export.sql capstrackdb
scp -i path/to/key.pem capstrack-export.sql azureuser@<your-domain>:~
```

Then on the Azure machine:

```bash
mysql -u capstrack -p capstrack < ~/capstrack-export.sql && rm ~/capstrack-export.sql
```

MySQL on your own machine keeps the tables as they are (MyISAM). Only
Azure's separate "Azure Database for MySQL" service would need them
converted, and this guide does not use it.

## 4. The code

The GitHub repository is private, so the server needs permission to read it:

```bash
ssh-keygen -t ed25519 -f ~/.ssh/github -N ""
cat ~/.ssh/github.pub
```

Add that line on GitHub: repository > **Settings > Deploy keys > Add**
(read-only). Then:

```bash
printf 'Host github.com\n  IdentityFile ~/.ssh/github\n' >> ~/.ssh/config
sudo mkdir -p /var/www/capstrack && sudo chown azureuser:www-data /var/www/capstrack
git clone -b capstrack_with_gdrive git@github.com:M21thony/capstrack.git /var/www/capstrack
cd /var/www/capstrack
cp deploy/env.production.example .env   # then fill it in: nano .env
composer install --no-dev --optimize-autoloader
php artisan key:generate
npm ci && npm run build
```

Uploaded files (student papers, PDFs) are **not** in git. Copy them from
the laptop:

```bash
scp -i path/to/key.pem -r storage/app azureuser@<your-domain>:/var/www/capstrack/storage/
```

Then on the server:

```bash
sudo usermod -aG www-data azureuser   # log out and back in after this
sudo chown azureuser:www-data .env && sudo chmod 640 .env   # www-data must read it
sudo chown -R azureuser:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod g+s {} +
php artisan migrate --force
php artisan storage:link
php artisan optimize
```

## 5. Web server, background worker, daily reminders

```bash
sudo cp deploy/nginx.conf /etc/nginx/sites-available/capstrack
sudo ln -s /etc/nginx/sites-available/capstrack /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default
sudo cp deploy/php.ini /etc/php/8.4/fpm/conf.d/99-capstrack.ini

# nginx ships no type for .mjs, so the PDF viewer's worker goes out as
# octet-stream and the browser refuses to start it: every scan in the CaPSA's
# check window then says it cannot be shown. Add it to the shared table - a
# types block in the site file would replace the table, not extend it.
sudo sed -i 's|^\(\s*application/javascript\s*\)js;|js mjs;|' /etc/nginx/mime.types

sudo systemctl restart php8.4-fpm nginx

sudo cp deploy/capstrack-worker.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update

# The four daily reminders
echo '* * * * * www-data cd /var/www/capstrack && php artisan schedule:run >> /dev/null 2>&1' | sudo tee /etc/cron.d/capstrack
```

## 6. HTTPS (required)

Google sign-in and push notifications refuse plain `http://` anywhere
except localhost.

```bash
sudo certbot --nginx -d <your-domain>
```

## 7. Google

Google Cloud Console > APIs & Services > Credentials > your OAuth client:

- **Authorized JavaScript origins:** `https://<your-domain>`
- **Authorized redirect URIs**, both of them:
  - `https://<your-domain>/login/google/callback`
  - `https://<your-domain>/admin/gdrive/callback`

Drive connect fails silently if only the login one is added.

---

## 8. The AI on RunPod

1. runpod.io > **Pods > Deploy**. Pick a cheap GPU (RTX A4000 / 3090 / 4090
   class is plenty for a 3B model).
2. Template: search **Ollama**. Under the pod settings:
   - Expose HTTP port **11434**
   - Environment variables: `OLLAMA_HOST=0.0.0.0` and
     `OLLAMA_NUM_PARALLEL=4` (how many checks it answers at once)
   - Volume: 20 GB, so the models survive stopping the pod.
3. Open the pod's web terminal and pull the public search model:

   ```bash
   ollama pull nomic-embed-text
   ```

4. The two fine-tuned models are only on your laptop. On the laptop, find
   each one's model file:

   ```bash
   ollama show --modelfile capstrack-duplication
   ollama show --modelfile capstrack-duplication-v3
   ```

   The `FROM` line of each names a file under `.ollama\models\blobs`. Save
   each output as a `Modelfile`, send the blob plus the Modelfile to the pod
   (`runpodctl send <file>` on the laptop, `runpodctl receive <code>` on the
   pod), change `FROM` to the received file's path, and on the pod run:

   ```bash
   ollama create capstrack-duplication -f Modelfile
   ollama create capstrack-duplication-v3 -f Modelfile-v3
   ```

5. The pod's address is `https://<pod-id>-11434.proxy.runpod.net`. Put it in
   the server's `.env` as `OLLAMA_URL=...`, then
   `php artisan optimize` so the site reads the new setting.

**Stop the pod when you are not testing.** It bills by the hour while
running. The address is also public: anyone who learns it can use your AI
while the pod runs.

To go back to the laptop, empty `OLLAMA_URL` and fill in the
`SSH_TUNNEL_*` settings instead.

---

## Updating the site later

```bash
cd /var/www/capstrack && bash deploy/deploy.sh
```

It takes the site offline for the minute it needs, pulls the latest code,
rebuilds, runs new migrations, and restarts the background worker.
