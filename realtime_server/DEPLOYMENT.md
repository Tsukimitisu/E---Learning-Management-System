# ELMS Realtime Deployment (No Page Reload)

This project uses Socket.IO for push notifications. To keep realtime always-on online, deploy the realtime server as a managed background service and proxy it through your main HTTPS domain.

## 1) Start with PM2

From `realtime_server/`:

```bash
npm install
pm2 start ecosystem.config.cjs
pm2 save
pm2 startup
```

Check status:

```bash
pm2 status
pm2 logs elms-realtime
curl http://127.0.0.1:3000/healthz
```

## 2) Configure PHP app env

Set these environment variables for your PHP runtime:

```text
ELMS_BASE_URL=https://your-domain.com/elms_system/
ELMS_REALTIME_ENABLED=true
ELMS_REALTIME_SERVER_URL=https://your-domain.com/realtime
ELMS_REALTIME_SOCKET_PATH=/realtime/socket.io
ELMS_REALTIME_BROADCAST_URL=http://127.0.0.1:3000/api/broadcast
```

Notes:
- `ELMS_REALTIME_SERVER_URL` is used by browser clients.
- `ELMS_REALTIME_BROADCAST_URL` is used by PHP backend when pushing events.
- Keep broadcast URL internal (`127.0.0.1`) when Node and PHP are on the same host.

## 3) Nginx reverse proxy

```nginx
location /realtime/socket.io/ {
    proxy_pass http://127.0.0.1:3000/socket.io/;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_read_timeout 120s;
}

location /realtime/api/ {
    proxy_pass http://127.0.0.1:3000/api/;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

## 4) Apache reverse proxy

Enable modules: `proxy`, `proxy_http`, `proxy_wstunnel`, `rewrite`.

```apache
ProxyPreserveHost On

ProxyPass        "/realtime/socket.io/"  "ws://127.0.0.1:3000/socket.io/"
ProxyPassReverse "/realtime/socket.io/"  "ws://127.0.0.1:3000/socket.io/"

ProxyPass        "/realtime/api/"        "http://127.0.0.1:3000/api/"
ProxyPassReverse "/realtime/api/"        "http://127.0.0.1:3000/api/"
```

## 5) Firewall and TLS

- Expose only your web server ports (`80`/`443`) publicly.
- Keep Node port `3000` private.
- Use HTTPS so Socket.IO uses WSS in browser.

## 6) Verify end-to-end

1. Open two browsers: teacher and student.
2. Update a grade or upload a material from teacher side.
3. Student should receive toast/badge instantly without page reload.
4. Restart Node process (`pm2 restart elms-realtime`) and verify auto-reconnect still resumes events.
