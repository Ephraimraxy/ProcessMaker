# 🚀 ProcessMaker VPS Deployment Guide

This guide will help you deploy ProcessMaker to a cloud VPS (Virtual Private Server) like DigitalOcean, Vultr, or Linode.

## Prerequisites

1. **A Domain Name**: You need a domain (e.g., `mybpm.com` or `pm.mycompany.com`).
2. **A VPS Account**: DigitalOcean, Vultr, Linode, or Hetzner.

---

## Step 1: Create a VPS

1. Log in to your VPS provider.
2. Create a new server (Droplet/Instance) with:
   - **OS**: Ubuntu 22.04 (LTS) x64
   - **Size**: Minimum 4GB RAM / 2 vCPUs (Recommended: 8GB RAM for heavy use)
   - **Region**: Closest to you or your users.
3. Add your SSH Key or create a Password.
4. Create the server and copy its **Public IP Address**.

## Step 2: Configure DNS

1. Go to your domain registrar (Namecheap, GoDaddy, Cloudflare, etc.).
2. Create an **A Record**:
   - **Host/Name**: `@` (or `pm` if using a subdomain like `pm.yoursite.com`)
   - **Value/Target**: Your VPS IP Address (e.g., `123.45.67.89`)
   - **TTL**: Automatic or 3600

> **Wait 5-10 minutes** for DNS to propagate.

## Step 3: Deploy on VPS

1. **SSH into your server**:
   ```bash
   ssh root@your-vps-ip
   ```

2. **Run the One-Line Setup**:
   Copy and paste this entire block into your terminal:

   ```bash
   # Clone the specific repository (Replace URL with your repo if different)
   git clone https://github.com/ProcessMaker/processmaker.git /var/www/processmaker
   
   cd /var/www/processmaker
   
   # Make setup script executable
   chmod +x deploy/setup-vps.sh
   
   # Run setup
   ./deploy/setup-vps.sh
   ```

3. **Follow the prompts**:
   - Enter your Domain: `pm.yoursite.com`
   - Enter your Email: `you@example.com` (for SSL notifications)

## Step 4: Access ProcessMaker

Once the script finishes (it takes ~5-10 minutes):

1. Open your browser to `https://pm.yoursite.com`
2. Log in with:
   - **User**: `admin`
   - **Password**: `admin`

> **Note**: Change the admin password immediately after logging in!

---

## Troubleshooting

- **Check Status**: `docker compose -f docker-compose.prod.yml ps`
- **View Logs**: `docker compose -f docker-compose.prod.yml logs -f`
- **Restart Services**: `docker compose -f docker-compose.prod.yml restart`

## Maintenance

- **Backup Database**:
  ```bash
  docker compose -f docker-compose.prod.yml exec db mysqldump -u processmaker -p"${DB_PASSWORD}" processmaker > backup.sql
  ```
