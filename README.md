# Telegram Bot Webhook Setup Using Cloudflare Tunnel 

This guide provides step-by-step instructions on setting up a Telegram bot webhook using Cloudflare Tunnel. By following these steps, you can securely expose your local server to the internet without the need for a static IP address or domain name.

## Prerequisites

Before you begin, ensure you have the following:

- A Cloudflare account.
- Cloudflare Tunnel installed on your server.
- Telegram bot token (you can create a bot using the BotFather).

## Step-by-Step Guide

### 1. Update and Install Dependencies

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y curl lsb-release php libapache2-mod-php php-{bcmath,bz2,intl,gd,mbstring,mysql,zip,cli,fpm,opcache,xml,curl,intl,xsl,soap,json,apcu,imap,xmlrpc}
```

### 2. Install Cloudflared

```bash
curl -L https://pkg.cloudflare.com/cloudflare-main.gpg | sudo tee /usr/share/keyrings/cloudflare-archive-keyring.gpg >/dev/null
echo "deb [signed-by=/usr/share/keyrings/cloudflare-archive-keyring.gpg] https://pkg.cloudflare.com/cloudflared $(lsb_release -cs) main" | sudo tee  /etc/apt/sources.list.d/cloudflared.list
sudo apt update
sudo apt install -y cloudflared
```

### 3. Log in and Create a Tunnel

```bash
cloudflared tunnel login
cloudflared tunnel create mytunnel
```

### 4. Route the Tunnel to a Domain Name

```bash
cloudflared tunnel route dns mytunnel gems-tpc.opensio.co.in
```

Replace `example.com` with your own domain name.

### 5. Configure Cloudflared

Create a YAML configuration file:

```bash
sudo nano ~/.cloudflared/config.yml
```

Replace `XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX` with your tunnel ID and `XXXXXXXXX.json` with your credentials file.

```yaml
tunnel: XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
credentials-file: /home/mahesh/.cloudflared/XXXXXXXXX.json

ingress:
  - hostname: gems-tpc.opensio.co.in
    service: http://localhost:3000
  - service: http_status:404
```

### 6. Install Cloudflared Service

```bash
sudo cloudflared --config ~/.cloudflared/config.yml service install
sudo systemctl enable cloudflared
sudo systemctl start cloudflared
```

### 7. Run the Tunnel

```bash
cloudflared tunnel run mytunnel
```

### 8. Set Up the Webhook with Telegram

Register the webhook URL with Telegram using the hostname you configured. Replace `<YourBotToken>` with your Telegram bot token and `example.com` with your domain name.

```bash
curl -F "url=https://gems-tpc.opensio.co.in/bot.php" https://api.telegram.org/bot<YourBotToken>/setWebhook
```

## Conclusion

Following these steps, you've successfully set up a Telegram bot webhook using Cloudflare Tunnel. Your local server is now accessible on the internet securely.

For more information, refer to the [Cloudflare Tunnel documentation](https://developers.cloudflare.com/cloudflare-one/connections/connect-apps).

---

Feel free to customize and add more details as needed for your specific project.
