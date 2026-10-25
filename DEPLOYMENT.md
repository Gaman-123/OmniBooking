# 🐳 Omni Booking — Docker + AWS Free Tier Deployment Guide

> **Stack:** PHP 8.3-FPM · Nginx · MySQL 8 · C++17 Engine · Docker · GitHub Actions · AWS EC2 + RDS  
> **Cost Year 1:** $0 (AWS Free Tier)  
> **Cost Year 2+:** ~$6/mo (migrate to DigitalOcean, same Docker image)

---

## 🗺️ Architecture

```
  GitHub (push to main)
         │
         ▼
  GitHub Actions CI/CD
  ┌─────────────────────────────────────────┐
  │ 1. docker build (compiles C++ inside)   │
  │ 2. Test: channel_engine --status        │
  │ 3. SSH into EC2 → docker pull & restart │
  └─────────────────────────────────────────┘
         │
         ▼
  ┌─────────────────────────────────────────────────────────────────────┐
  │                    AWS EC2 t2.micro  (FREE TIER)                    │
  │                                                                     │
  │  ┌──────────────────┐    ┌──────────────────────────────────────┐   │
  │  │  Nginx (:80/443) │───▶│  PHP 8.3-FPM + channel_engine (C++) │   │
  │  └──────────────────┘    └──────────────────────────────────────┘   │
  └──────────────────────────────┬──────────────────────────────────────┘
                                 │
                                 ▼
              ┌──────────────────────────────────────┐
              │    AWS RDS db.t3.micro  (FREE TIER)   │
              │    MySQL 8 · omni_booking database    │
              └──────────────────────────────────────┘
                                 │
                                 ▼
                    Cloudflare CDN + DNS (FREE)
                    SSL · WAF · Global Edge Cache
```

---

## 💰 Cost Breakdown

| Resource | Free Tier Allowance | After 12 Months |
|---|---|---|
| EC2 t2.micro | 750 hrs/mo × 12 months | ~$8/mo or move to DO $6 |
| RDS db.t3.micro | 750 hrs/mo × 12 months | ~$15/mo or self-host |
| ECR image storage | 500 MB free | ~$0.10/GB |
| Cloudflare CDN + DNS | Forever free | Forever free |
| SSL Certificate | Free (via Cloudflare) | Free |
| **Total Year 1** | **$0 🎉** | — |
| **Total Year 2+** | — | **$6/mo (DO migration)** |

---

## 📂 Files to Create

```
Omni booking/
├── Dockerfile                          ← Multi-stage build (PHP + C++ compiler)
├── docker-compose.yml                  ← Local dev (PHP + MySQL together)
├── deployment/
│   └── nginx.conf                      ← Nginx server block
└── .github/
    └── workflows/
        └── deploy.yml                  ← GitHub Actions CI/CD pipeline
```

---

## 🐳 Dockerfile

Create `Dockerfile` at the project root:

```dockerfile
# ─────────────────────────────────────────────────────
# Stage 1: Build the C++ Algorithmic Engine
# ─────────────────────────────────────────────────────
FROM gcc:13-bookworm AS cpp-builder

WORKDIR /build
COPY cpp/ ./cpp/

RUN mkdir -p bin && g++ -std=c++14 -O3 -Icpp/include \
    cpp/src/dynamic_pricing.cpp \
    cpp/src/inventory_allocator.cpp \
    cpp/src/dag_scheduler.cpp \
    cpp/src/rate_parity_auditor.cpp \
    cpp/src/main.cpp \
    -o bin/channel_engine

# ─────────────────────────────────────────────────────
# Stage 2: PHP + Nginx Production Image
# ─────────────────────────────────────────────────────
FROM php:8.3-fpm-bookworm

# Install Nginx + PHP extensions + MySQL client
RUN apt-get update && apt-get install -y \
    nginx \
    libpq-dev \
    default-mysql-client \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/omni-booking

# Copy application source
COPY . .

# Copy compiled C++ binary from Stage 1
COPY --from=cpp-builder /build/bin/channel_engine ./bin/channel_engine
RUN chmod +x ./bin/channel_engine

# Install PHP dependencies (production only)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy Nginx config
COPY deployment/nginx.conf /etc/nginx/sites-available/default

# Set permissions
RUN chown -R www-data:www-data /var/www/omni-booking \
    && chmod -R 755 /var/www/omni-booking/public

# Expose HTTP port
EXPOSE 80

# Start PHP-FPM + Nginx
CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]
```

---

## ⚙️ Nginx Config

Create `deployment/nginx.conf`:

```nginx
server {
    listen 80;
    server_name _;
    root /var/www/omni-booking/public;
    index index.php;

    # Gzip compression
    gzip on;
    gzip_types text/plain application/json application/javascript text/css;

    # Static assets — serve directly with long cache
    location ~* \.(css|js|ico|png|jpg|woff2|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # API requests — never cache
    location /api/ {
        add_header Cache-Control "no-store, no-cache";
        try_files $uri $uri/ /index.php?$query_string;
    }

    # All other requests
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM handler
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_read_timeout 60;
    }

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";
}
```

---

## 🧑‍💻 Docker Compose (Local Development)

Create `docker-compose.yml` at project root:

```yaml
version: '3.9'

services:
  # ── PHP Application + C++ Engine ──────────────────
  app:
    build: .
    container_name: omni-booking-app
    ports:
      - "8000:80"
    environment:
      APP_ENV: local
      APP_DEBUG: "true"
      DB_CONNECTION: mysql
      DB_HOST: db
      DB_PORT: 3306
      DB_DATABASE: omni_booking
      DB_USERNAME: root
      DB_PASSWORD: secret
      CPP_ENGINE_PATH: bin/channel_engine
    volumes:
      - .:/var/www/omni-booking          # Live reload source changes
      - /var/www/omni-booking/vendor     # Don't overwrite vendor
    depends_on:
      db:
        condition: service_healthy
    restart: unless-stopped

  # ── MySQL 8 ───────────────────────────────────────
  db:
    image: mysql:8.0
    container_name: omni-booking-db
    environment:
      MYSQL_ROOT_PASSWORD: secret
      MYSQL_DATABASE: omni_booking
    ports:
      - "3307:3306"         # 3307 to avoid conflict with local MySQL
    volumes:
      - mysql_data:/var/lib/mysql
      - ./database/schema_mysql.sql:/docker-entrypoint-initdb.d/01_schema.sql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 5s
      timeout: 5s
      retries: 10

volumes:
  mysql_data:
```

### Local Dev Commands

```bash
# Start everything
docker compose up -d

# Check logs
docker compose logs -f app

# Run migrations
docker compose exec app php artisan migrate

# Seed database
docker compose exec app php artisan db:seed

# Test C++ engine
docker compose exec app php artisan cpp:status

# Simulate a booking
docker compose exec app php artisan booking:simulate

# Stop
docker compose down
```

---

## 🚀 GitHub Actions CI/CD

Create `.github/workflows/deploy.yml`:

```yaml
name: 🐳 Build · Test · Deploy — Omni Booking

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

env:
  DOCKER_IMAGE: omni-booking

jobs:
  # ────────────────────────────────────────────────────
  # JOB 1: Build Docker image and verify C++ engine
  # ────────────────────────────────────────────────────
  build:
    name: 🔨 Build & Verify
    runs-on: ubuntu-latest

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Set up Docker Buildx
        uses: docker/setup-buildx-action@v3

      - name: Build Docker image
        run: |
          docker build \
            --tag ${{ env.DOCKER_IMAGE }}:${{ github.sha }} \
            --tag ${{ env.DOCKER_IMAGE }}:latest \
            --cache-from ${{ env.DOCKER_IMAGE }}:latest \
            .

      - name: Verify C++ engine compiles and runs
        run: |
          docker run --rm ${{ env.DOCKER_IMAGE }}:${{ github.sha }} \
            /var/www/omni-booking/bin/channel_engine --status

      - name: Run C++ benchmark inside container
        run: |
          docker run --rm ${{ env.DOCKER_IMAGE }}:${{ github.sha }} \
            /var/www/omni-booking/bin/channel_engine --benchmark

      - name: Save image as artifact (for deploy job)
        run: docker save ${{ env.DOCKER_IMAGE }}:${{ github.sha }} | gzip > image.tar.gz

      - name: Upload image artifact
        uses: actions/upload-artifact@v4
        with:
          name: docker-image
          path: image.tar.gz
          retention-days: 1

  # ────────────────────────────────────────────────────
  # JOB 2: Deploy to AWS EC2 (only on push to main)
  # ────────────────────────────────────────────────────
  deploy:
    name: 🚀 Deploy to AWS EC2
    runs-on: ubuntu-latest
    needs: build
    if: github.ref == 'refs/heads/main' && github.event_name == 'push'

    steps:
      - name: Download image artifact
        uses: actions/download-artifact@v4
        with:
          name: docker-image

      - name: Copy image to EC2 via SCP
        uses: appleboy/scp-action@v0.1.7
        with:
          host: ${{ secrets.AWS_EC2_IP }}
          username: ec2-user
          key: ${{ secrets.AWS_SSH_KEY }}
          source: image.tar.gz
          target: /home/ec2-user/

      - name: Load image and restart container on EC2
        uses: appleboy/ssh-action@v1.0.0
        with:
          host: ${{ secrets.AWS_EC2_IP }}
          username: ec2-user
          key: ${{ secrets.AWS_SSH_KEY }}
          script: |
            set -e

            echo "📦 Loading new Docker image..."
            docker load < /home/ec2-user/image.tar.gz
            rm -f /home/ec2-user/image.tar.gz

            echo "🛑 Stopping old container..."
            docker stop omni-booking 2>/dev/null || true
            docker rm   omni-booking 2>/dev/null || true

            echo "🚀 Starting new container..."
            docker run -d \
              --name omni-booking \
              --restart unless-stopped \
              -p 80:80 \
              -e APP_ENV=production \
              -e APP_DEBUG=false \
              -e APP_URL=https://${{ secrets.APP_DOMAIN }} \
              -e DB_CONNECTION=mysql \
              -e DB_HOST=${{ secrets.RDS_HOST }} \
              -e DB_PORT=3306 \
              -e DB_DATABASE=omni_booking \
              -e DB_USERNAME=admin \
              -e DB_PASSWORD=${{ secrets.DB_PASSWORD }} \
              -e CPP_ENGINE_PATH=bin/channel_engine \
              omni-booking:${{ github.sha }}

            echo "⏳ Waiting for container to be ready..."
            sleep 5

            echo "🗄️ Running database migrations..."
            docker exec omni-booking php artisan migrate --force

            echo "✅ Health check..."
            curl -sf http://localhost/api/health | grep '"status":"healthy"' \
              && echo "🟢 Health check passed!" \
              || (echo "🔴 Health check FAILED" && exit 1)

            echo "🧹 Cleaning up old images..."
            docker image prune -f

  # ────────────────────────────────────────────────────
  # JOB 3: Post-deploy notification (optional)
  # ────────────────────────────────────────────────────
  notify:
    name: 📣 Notify
    runs-on: ubuntu-latest
    needs: deploy
    if: always()

    steps:
      - name: Deployment status
        run: |
          if [ "${{ needs.deploy.result }}" == "success" ]; then
            echo "✅ Deployment SUCCEEDED — commit ${{ github.sha }}"
          else
            echo "❌ Deployment FAILED — check logs"
            exit 1
          fi
```

---

## 🔑 GitHub Repository Secrets

Go to: **GitHub → Repo → Settings → Secrets and Variables → Actions → New repository secret**

| Secret Name | Value |
|---|---|
| `AWS_EC2_IP` | Your EC2 public IP (e.g. `13.233.x.x`) |
| `AWS_SSH_KEY` | Contents of your `.pem` private key file |
| `RDS_HOST` | RDS endpoint (e.g. `omni-booking.xxxx.ap-south-1.rds.amazonaws.com`) |
| `DB_PASSWORD` | Your RDS MySQL root password |
| `APP_DOMAIN` | Your domain (e.g. `omnibooking.yourdomain.com`) |

---

## ☁️ AWS Free Tier — One-Time Setup

### Step 1 — Create AWS Account

1. Go to [aws.amazon.com/free](https://aws.amazon.com/free)
2. Sign up — credit card needed but **not charged** for free tier usage
3. Select region: **ap-south-1** (Mumbai) for lowest latency from India

### Step 2 — Launch EC2 Instance

```
AWS Console → EC2 → Launch Instance

  Name:           omni-booking-server
  AMI:            Amazon Linux 2023 (free tier eligible)
  Instance type:  t2.micro  ← FREE TIER
  Key pair:       Create new → download .pem file → save securely
  Storage:        30 GB SSD (gp2) ← free tier limit
  Security group:
    - SSH  (22)  → My IP only
    - HTTP (80)  → Anywhere (0.0.0.0/0)
    - HTTPS(443) → Anywhere (0.0.0.0/0)
```

### Step 3 — Install Docker on EC2

```bash
ssh -i your-key.pem ec2-user@YOUR_EC2_IP

# Install Docker
sudo yum update -y
sudo yum install -y docker
sudo systemctl enable docker
sudo systemctl start docker
sudo usermod -aG docker ec2-user

# Re-login to apply group change
exit
ssh -i your-key.pem ec2-user@YOUR_EC2_IP

# Verify
docker --version
```

### Step 4 — Create RDS MySQL Instance

```
AWS Console → RDS → Create database

  Method:         Standard create
  Engine:         MySQL 8.0
  Template:       Free tier  ← IMPORTANT
  DB name:        omni_booking
  Username:       admin
  Password:       (strong password → save in GitHub Secrets)
  Instance:       db.t3.micro  ← FREE TIER
  Storage:        20 GB SSD (gp2)
  VPC:            Same VPC as your EC2
  Public access:  No  (EC2 reaches it privately)
  Security group: Allow 3306 from EC2 security group only
```

### Step 5 — First Deploy (Manual)

```bash
# SSH into EC2
ssh -i your-key.pem ec2-user@YOUR_EC2_IP

# Build and run for the first time
docker run -d \
  --name omni-booking \
  --restart unless-stopped \
  -p 80:80 \
  -e APP_ENV=production \
  -e APP_DEBUG=false \
  -e DB_CONNECTION=mysql \
  -e DB_HOST=YOUR_RDS_ENDPOINT \
  -e DB_DATABASE=omni_booking \
  -e DB_USERNAME=admin \
  -e DB_PASSWORD=YOUR_PASSWORD \
  -e CPP_ENGINE_PATH=bin/channel_engine \
  omni-booking:latest

# Initialize database
docker exec omni-booking php artisan migrate
docker exec omni-booking php artisan db:seed

# Verify
curl http://localhost/api/health
curl http://localhost/api/cpp/status
```

---

## 🌐 Cloudflare DNS + SSL (Free)

```
1. Sign up at cloudflare.com (free)
2. Add your domain → update nameservers at registrar
3. Add DNS record:
      Type: A
      Name: @  (or subdomain like 'booking')
      Value: YOUR_EC2_IP
      Proxy: ✅ ON (orange cloud = CDN + SSL active)
4. SSL/TLS → Mode: Full (Strict)
5. Speed → Optimization → Auto Minify: ✅ JS, CSS, HTML
6. Caching → Configuration → Browser TTL: 4 hours
```

Your app is now live at `https://yourdomain.com` with:
- ✅ Free SSL certificate
- ✅ DDoS protection
- ✅ Global CDN caching
- ✅ API routes bypass cache automatically

---

## 🔁 Day-to-Day Workflow After Setup

```bash
# Make code changes locally
git add .
git commit -m "feat: your change"
git push origin main

# GitHub Actions automatically:
# 1. Builds Docker image (compiles C++ inside)
# 2. Tests: channel_engine --status, --benchmark
# 3. SCPs image to EC2
# 4. Stops old container, starts new one
# 5. Runs php artisan migrate
# 6. Health checks /api/health
# Done ✅ — usually takes 3–5 minutes total
```

---

## 🐛 Troubleshooting

| Problem | Fix |
|---|---|
| Health check fails after deploy | `docker logs omni-booking` to see PHP errors |
| MySQL connection refused | Check RDS Security Group allows EC2 IP on port 3306 |
| C++ engine offline | `docker exec omni-booking php artisan cpp:status` |
| Nginx 502 Bad Gateway | `docker exec omni-booking ps aux` — check php-fpm running |
| GitHub Actions SSH fails | Verify `AWS_EC2_IP` and `AWS_SSH_KEY` secrets are correct |
| Free tier charge | Set AWS Billing Alert at $1 — you'll never hit it |

---

## ✅ Pre-Launch Checklist

- [ ] `Dockerfile` builds successfully: `docker build -t omni-booking .`
- [ ] `docker-compose up` works locally at `http://localhost:8000`
- [ ] `/api/health` returns `"driver": "mysql"` locally
- [ ] `/api/cpp/status` returns `"status": "online"`
- [ ] `booking:simulate` completes with `"status": "confirmed"`
- [ ] EC2 t2.micro launched in `ap-south-1`
- [ ] Docker installed on EC2: `docker --version`
- [ ] RDS MySQL created (Free Tier template selected)
- [ ] RDS security group allows EC2 on port 3306
- [ ] All 5 GitHub secrets set correctly
- [ ] First manual deploy succeeded on EC2
- [ ] GitHub Actions workflow passes on `main` push
- [ ] Cloudflare DNS pointing to EC2 IP (proxy ON)
- [ ] `https://yourdomain.com/api/health` returns 200 OK
- [ ] AWS Billing Alert set at $1 threshold

---

> **Total setup time:** ~45 minutes (one time)  
> **Ongoing deploys:** `git push` → auto in 3–5 min  
> **Year 1 cost:** **$0** 🎉
