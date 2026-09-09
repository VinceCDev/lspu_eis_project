# Deploy sa Hostinger VPS (Docker) — LSPU EIS

Sunod-sunod na hakbang. Gawin lahat sa **hPanel** at sa **SSH terminal** ng VPS.

---

## 1. Mag-SSH sa VPS

1. hPanel → **VPS** → piliin ang server mo → **Overview**
   - Tandaan ang **IP address** (hal. `193.203.xxx.xxx`)
2. Kaliwang menu → **SSH Access**
   - Kung hindi mo alam ang root password: pindutin **Change SSH password** → maglagay ng bago.
3. Sa laptop mo, buksan ang **PowerShell** at i-type:
   ```powershell
   ssh root@193.203.xxx.xxx
   ```
   - Sagutin ng `yes` ang tanong tungkol sa fingerprint (unang beses lang).
   - Ilagay ang password (hindi lalabas ang mga letra habang nagta-type — normal yan).

Kapag nakapasok ka na, may prompt kang ganito: `root@srv123:~#`

---

## 2. I-check ang OS at Docker

```bash
cat /etc/os-release | head -2
docker --version || echo "WALA PANG DOCKER"
docker compose version || echo "WALA PANG COMPOSE"
```

- Kung **may Docker na** → dumiretso sa Step 4.
- Kung **WALA** → Step 3.

---

## 3. Mag-install ng Docker (kung wala pa)

Para sa Ubuntu/Debian VPS:

```bash
curl -fsSL https://get.docker.com | sh
systemctl enable --now docker
docker run --rm hello-world     # dapat may "Hello from Docker!" na lalabas
```

> Kung hindi Ubuntu/Debian ang VPS (hal. AlmaLinux/CentOS), sabihin mo sa akin ang output ng `cat /etc/os-release`.

---

## 4. Kunin ang project (git clone)

```bash
cd /opt
git clone <URL_NG_REPO_MO> lspu-eis
cd /opt/lspu-eis
```

> Kung private ang repo, gagamit ka ng GitHub **Personal Access Token** bilang password,
> o mag-setup ng deploy key. Sabihin mo lang kung private.

---

## 5. Gawin ang `.env`

```bash
cp .env.docker.example .env
nano .env
```

Palitan ang mga linyang may `CHANGE_ME` at `YOUR_VPS_IP`:

| Linya | Ilagay |
|---|---|
| `APP_URL` | `http://` + IP ng VPS mo (hal. `http://193.203.10.20`) |
| `DB_PASSWORD` | isang matibay na password (walang space) |
| `DB_ROOT_PASSWORD` | **ibang** matibay na password |

Wala nang kailangang baguhin sa `DB_HOST` (`db` na ang tama).

I-save ang nano: `Ctrl+O` → `Enter` → `Ctrl+X`.

---

## 6. I-build at patakbuhin

```bash
docker compose up -d --build
```

Unang build: ~3–8 minuto. Pagkatapos:

```bash
docker compose ps           # dapat "running" / "healthy" lahat
docker compose logs -f app  # panoorin ang bootstrap (migrate, cache). Ctrl+C para lumabas.
```

Sa `app` logs, hanapin ang:
- `Generating APP_KEY...`
- `Migrating: ...` na tapos nang walang error
- `Caching config / routes / views...`

---

## 7. Subukan

Buksan sa browser: **`http://IP-NG-VPS-MO`**

Kung may "500 Server Error":
```bash
docker compose exec app php artisan config:clear
docker compose exec app tail -n 50 storage/logs/laravel.log
```

---

## 8. Gumawa ng unang admin / seed (kung kailangan)

```bash
docker compose exec app php artisan db:seed --force
# o kung may custom command:
docker compose exec app php artisan tinker
```

---

## 9. Firewall (ilantad ang port 80)

```bash
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable
ufw status
```

---

## Pag may update sa code (redeploy)

```bash
cd /opt/lspu-eis
git pull
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose restart app queue scheduler
```

(Ang `app` entrypoint ang bahala sa `config:cache` / `route:cache` / `view:cache`.)

---

## Mamaya: magdagdag ng domain + HTTPS

1. Sa domain registrar mo, gumawa ng **A record**: `@` → IP ng VPS. Hintayin mag-propagate (~30 min).
2. Baguhin sa `.env`: `APP_URL=https://yourdomain.com` → `docker compose restart app`.
3. Idagdag ang Caddy o Nginx-Proxy-Manager para sa auto-SSL, o gamitin ang certbot.
   Sabihin mo lang kapag nandito ka na — bibigyan kita ng exact compose add-on.

---

## Mabilisang cheat-sheet

| Gawin | Command |
|---|---|
| Tingnan status | `docker compose ps` |
| Logs | `docker compose logs -f app` |
| Artisan | `docker compose exec app php artisan <cmd>` |
| MySQL shell | `docker compose exec db mysql -u root -p` |
| Restart lahat | `docker compose restart` |
| Patay lahat | `docker compose down` |
| Patay + burahin DB | `docker compose down -v`  ⚠️ mawawala ang data |
