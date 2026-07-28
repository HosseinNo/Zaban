#!/usr/bin/env bash
# آماده‌سازی یک سرور تازهٔ اوبونتو ۲۴.۰۴ برای لینگوتاک (سند S.3)
set -euo pipefail

APP_USER="${APP_USER:-lingotalk}"
SSH_PORT="${SSH_PORT:-22}"

echo "── به‌روزرسانی سیستم"
apt-get update -qq && apt-get upgrade -y -qq

echo "── ساعت سرور روی UTC (امضای درگاه و کد یک‌بارمصرف به آن حساس‌اند)"
timedatectl set-timezone UTC
apt-get install -y -qq chrony && systemctl enable --now chrony

echo "── داکر"
if ! command -v docker >/dev/null; then
  curl -fsSL https://get.docker.com | sh
fi
systemctl enable --now docker

echo "── کاربر غیرروت"
id -u "$APP_USER" >/dev/null 2>&1 || adduser --disabled-password --gecos "" "$APP_USER"
usermod -aG docker "$APP_USER"

echo "── فایروال: فقط ۸۰، ۴۴۳ و SSH"
apt-get install -y -qq ufw
ufw --force reset >/dev/null
ufw default deny incoming
ufw default allow outgoing
ufw allow "$SSH_PORT"/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

echo "── سخت‌سازی SSH: فقط کلید، بدون ورود root"
sed -i 's/^#\?PasswordAuthentication.*/PasswordAuthentication no/'   /etc/ssh/sshd_config
sed -i 's/^#\?PermitRootLogin.*/PermitRootLogin prohibit-password/'  /etc/ssh/sshd_config
systemctl reload ssh

echo "── fail2ban"
apt-get install -y -qq fail2ban
systemctl enable --now fail2ban

echo "── محدودکردن لاگ تا دیسک پر نشود"
mkdir -p /etc/docker
cat > /etc/docker/daemon.json <<'JSON'
{ "log-driver": "json-file", "log-opts": { "max-size": "20m", "max-file": "5" } }
JSON
systemctl restart docker

echo
echo "آماده است. قدم بعد:"
echo "  cp infra/.env.example infra/.env  &&  nano infra/.env"
echo "  cd infra && docker compose up -d"
