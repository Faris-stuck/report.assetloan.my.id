# WAHA service — waha.assetloan.my.id

This directory contains the production deployment definition for WAHA (WhatsApp HTTP API) used by the LAPORIN project.

## Architecture

```text
Internet
  |
  | HTTPS
  v
waha.assetloan.my.id
  |
  | reverse proxy
  v
127.0.0.1:3001
  |
  v
laporin-waha :3000
  |
  +-- persistent volume: laporin-waha-sessions
```

The WAHA container is intentionally bound to `127.0.0.1` so port 3001 is not exposed directly to the Internet. Put Nginx/Caddy/Traefik in front of it and terminate TLS for `waha.assetloan.my.id`.

## Server setup

1. Create the deployment directory on the server.
2. Copy `.env.example` to `.env` and replace every `CHANGE_ME` value with strong random secrets.
3. Start the service with:

```bash
docker compose --env-file .env up -d
```

4. Verify the container:

```bash
docker compose --env-file .env ps
docker compose --env-file .env logs --tail=100 waha
```

5. Configure the reverse proxy for `waha.assetloan.my.id` to forward HTTPS traffic to `http://127.0.0.1:3001`.

## Laravel integration

The optional WAHA webhook should point to a dedicated Laravel endpoint, for example:

```text
https://report.assetloan.my.id/api/waha/webhook
```

Keep the WAHA API key and webhook HMAC key out of Git. Store them in the server `.env` and the Laravel production secret store/environment.

## Important

- Do not expose port 3000 or 3001 publicly.
- Do not commit `infra/waha/.env`.
- Keep WAHA API authentication enabled.
- Use HTTPS for the public hostname.
- The WhatsApp session volume is persistent so a container restart does not intentionally discard the session state.
