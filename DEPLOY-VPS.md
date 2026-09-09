# Deploy to Hostinger VPS (Docker) — LSPU EIS

Step-by-step. Do everything in **hPanel** and in the VPS **SSH / Browser terminal**.

VPS in use: `srv1847558.hstgr.cloud` — IP `72.62.240.65` — Ubuntu, KVM 4.

---

## 1. Open a terminal on the VPS

**Option A — Browser terminal (easiest, no setup):**

1. hPanel → **VPS** → row `srv1847558...` → **Manage**
2. Left menu → **Browser terminal**
3. Log in as `root` (use the root password the VPS owner set; if you don't have it,
   ask the owner, or use **Change root password** in the VPS panel).

**Option B — SSH from your PC:**

```powershell
ssh root@72.62.240.65
```
Answer `yes` to the fingerprint prompt (first time only), then enter the password
(characters won't show while typing — that's normal).

You're in when the prompt looks like `root@srv1847558:~#`

---

## 2. Check the OS and whether Docker is installed

```bash
cat /etc/os-release | grep PRETTY_NAME
docker --version || echo "NO DOCKER YET"
docker compose version || echo "NO COMPOSE YET"
```

- **Docker already there** → skip to Step 4.
- **Not there** → Step 3.

---

## 3. Install Docker (only if missing)

For Ubuntu/Debian:

```bash
curl -fsSL https://get.docker.com | sh
systemctl enable --now docker
docker run --rm hello-world     # should print "Hello from Docker!"
```

---

## 4. Get the project (git clone)

```bash
cd /opt
git clone https://github.com/VinceCDev/lspu_eis_project.git lspu-eis
cd /opt/lspu-eis
```

> If the repo is **private**, git will ask for a username and password. Use your
> GitHub username and a **Personal Access Token** (GitHub → Settings → Developer
> settings → Personal access tokens) as the password. Or make the repo public.

---

## 5. Create the `.env`

```bash
cp .env.docker.example .env
nano .env
```

Change the lines marked `CHANGE_ME` and `YOUR_VPS_IP`:

| Line | Set to |
|---|---|
| `APP_URL` | `http://72.62.240.65` |
| `DB_PASSWORD` | a strong password (no spaces) |
| `DB_ROOT_PASSWORD` | a **different** strong password |

Leave `DB_HOST=db` as is — that's correct for the Docker network.

Save in nano: `Ctrl+O` → `Enter` → `Ctrl+X`.

---

## 6. Build and start

```bash
docker compose up -d --build
```

First build takes ~3–8 minutes. Then:

```bash
docker compose ps           # all services should be "running" / "healthy"
docker compose logs -f app  # watch the bootstrap (migrate, cache). Ctrl+C to exit.
```

In the `app` logs, look for:
- `Generating APP_KEY...`
- `Migrating: ...` finishing with no errors
- `Caching config / routes / views...`

---

## 7. Test it

Open in a browser: **`http://72.62.240.65`**

If you get a "500 Server Error":
```bash
docker compose exec app php artisan config:clear
docker compose exec app tail -n 50 storage/logs/laravel.log
```

---

## 8. Seed data / first admin (if needed)

```bash
docker compose exec app php artisan db:seed --force
# or, for a custom command:
docker compose exec app php artisan tinker
```

---

## 9. Firewall (expose port 80)

```bash
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable
ufw status
```

---

## Redeploying after a code change

```bash
cd /opt/lspu-eis
git pull
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose restart app queue scheduler
```

(The `app` entrypoint handles `config:cache` / `route:cache` / `view:cache`.)

---

## Later: add a domain + HTTPS

1. At your domain registrar, add an **A record**: `@` → `72.62.240.65`. Wait for
   propagation (~30 min).
2. In `.env`, set `APP_URL=https://yourdomain.com` → `docker compose restart app`.
3. Add Caddy or Nginx Proxy Manager for automatic SSL, or use certbot.
   Ask when you reach this point and I'll give you the exact compose add-on.

---

## Quick cheat-sheet

| Do this | Command |
|---|---|
| Show status | `docker compose ps` |
| Logs | `docker compose logs -f app` |
| Run artisan | `docker compose exec app php artisan <cmd>` |
| MySQL shell | `docker compose exec db mysql -u root -p` |
| Restart all | `docker compose restart` |
| Stop all | `docker compose down` |
| Stop + wipe DB | `docker compose down -v`  ⚠️ deletes all data |
