---
domain: auth
purpose: enterprise-security
version: 1.0
updated: 2024-01-15
owner: security-team
status: stable
---

Security Level: Enterprise

Target: provide guidance and basic app-level protections. True enterprise-grade DDoS and WAF protections require edge infrastructure (CDN, WAF, load balancers) and are out-of-band from application code. This document lists the recommended controls and minimal in-app measures implemented in this repository.

1. In-app protections added
- `App\Http\Middleware\SecurityHeaders`: adds strong security headers (CSP, HSTS, COOP, etc.).
- `App\Http\Middleware\EnterpriseSecurity`: global middleware implementing:
  - conservative per-IP rate limiting and short-term burst protection
  - heuristic SQL injection payload detection (basic patterns)
  - blocking of suspicious User-Agents
  - logging of suspicious activity to application logs

2. Recommended edge/infrastructure controls (Enterprise)
- Use a reputable CDN / DDoS protection provider (Cloudflare, AWS Shield + CloudFront, Azure Front Door). Enable:
  - Always-on DDoS mitigation
  - Geo-blocking and IP reputation filtering
  - Rate limiting rules per endpoint
  - WAF managed rulesets (OWASP Core Rule Set) with tuned exclusions
- Use a Web Application Firewall (WAF) — Cloudflare WAF, AWS WAF, or a managed WAF appliance.
- Configure TLS only (no TLS 1.0/1.1), strong cipher suites, and HSTS preloading when appropriate.
- Place the application behind a reverse proxy; trust `CF-Connecting-IP` and `X-Forwarded-For` headers only from that proxy.
- Use autoscaling + load balancer to absorb volumetric traffic; pair with rate limiting and CDN throttle.
- Implement network ACLs and allowlist access to internal services and database servers.

3. Brute-force and authentication hardening
- Use route-specific throttles for authentication endpoints (login, password reset) with low limits.
- Use progressive delays or exponential backoff on repeated failed logins.
- Enforce multi-factor authentication for privileged roles.
- Store and monitor failed authentication attempts; consider integrating with SIEM and alerting.

4. SQL injection and database hardening
- Use parameterized queries / Eloquent (already in use) and never concatenate user input into raw queries.
- Use a database user with minimal privileges (no superuser) for the application connection.
- Enable database auditing and slow query logging; monitor for suspicious queries.

5. Monitoring, logging, and incident response
- Centralize logs (ELK, Datadog, Papertrail) and set alerts for spikes in 4xx/5xx, traffic volume, or SQL error rates.
- Implement health checks that exclude from rate-limiting where appropriate.
- Maintain an incident runbook for DDoS and intrusion events; include contact list for CDN/WAF support.

6. Next steps to improve protections (actionable)
- Configure WAF rules and enable OWASP CRS.
- Tune RateLimiter rules in `bootstrap/app.php` per-route and per-role.
- Add IP blocklist/allowlist management (e.g., store in Redis or DB and consult in `EnterpriseSecurity`).
- Integrate with Cloudflare (or chosen provider) and test mitigation with a controlled traffic generator.

Notes
- The in-app middleware is a complement, not a replacement, for edge protections. For real Enterprise SLAs and anti-DDoS capabilities, deploy a managed service (Cloudflare/AWS/Azure) and configure network-level controls.
