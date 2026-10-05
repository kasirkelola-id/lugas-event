# Server protection candidate

This repository serves `website/index.php` from the **website directory**, not
`website/public`. Its app, vendor, tests, tools and writable directories are
therefore siblings of the front controller. Apache `.htaccess` files do not
protect an Nginx deployment. SEC-19 remains production verification required.

Run `python deploy/verify_nginx_local.py --nginx /path/to/local/nginx` with a
portable local Nginx distribution containing `conf/mime.types` and
`conf/fastcgi_params`. It creates a new TEMP fixture, binds a random loopback
port, tests the actual shared locations, gracefully stops only its owned server
and retains evidence. It does not read the application's environment, uploads,
credentials or database. TLS/FPM/socket routing still requires staging checks.

The Nginx example permits execution of the fixed `/index.php` only, denies
sensitive paths and public internal routes, and isolates uploads to GET/HEAD
static PNG/JPEG/WebP files in the two existing image directories. Other upload
extensions and script PATH_INFO are denied. Existing public images remain
public; this is not a private-document access policy. Assets have their own
static type allowlist. Review any additional legitimate static paths before
rollout. All other URLs reach the front controller.

Image handlers now bound dimensions/pixels, re-encode to random PNG filenames
and fail without moving originals if decoding/encoding is unavailable. Install
and verify the configured GD/ImageMagick encoder; do not restore the raw-file
fallback. Only the service account running PHP should write upload directories;
Nginx needs read access. Uploads must not contain symlinks to private files.
Align PHP `upload_max_filesize` (at least5M) and `post_max_size` (at least6M)
with the candidate6MiB body cap; application logo/profile limits remain2/5MiB.

Before enabling changes on staging, review complete `nginx -T`, run `nginx -t`,
set the real TLS hostname/certificates and PHP-FPM loopback listener, and proxy
`/socket.io/` to the loopback Node listener. Provision a private matching internal
service secret. Node must not be exposed directly on a public port. PHP/Node
trusted proxies and real client-IP rate-limit behavior require a topology check;
do not trust arbitrary forwarded headers. Keep CI_ENVIRONMENT=production and
CI_DEBUG disabled. The example access log omits URLs/query strings, headers and
bodies; application logs provide safe correlations. Critical Nginx error logs
can still contain request context and require restricted access/retention.

On **staging only**, capture effective config and status/body evidence for:

- `.env`, `.git/config`, app/private config, vendor, writable, tests, tools,
  composer files, backup/SQL files and public `/api/internal/*`: 404/403 with
  no content or directory listing, including encoded/case/path-info variants.
- A synthetic harmless `.php`, `.phtml`, `.phar`, double-extension and dotted
  file in uploads: denied; no PHP execution. Never use a production upload.
- A legitimate synthetic PNG and normal API/browser route: expected behavior.
- Browser CSRF, bearer authentication, tenant isolation, Socket.IO TLS handshake,
  revoked-token lease, file write/read permissions, upload replacement and
  maximum-body failures: all pass.

Record deployed SHA/config fingerprint and remove only the synthetic fixtures.
No production installation, server probe or service restart is authorized by
the local remediation task. A localhost config/HTTP fixture run is source/local
evidence and does not close deployed SEC-19 or PERF-11.

Primary documentation: [location selection](https://nginx.org/en/docs/http/ngx_http_core_module.html#location),
[WebSocket proxying](https://nginx.org/en/docs/http/websocket.html),
[FastCGI parameters](https://nginx.org/en/docs/http/ngx_http_fastcgi_module.html#fastcgi_param).


Android update links are restricted by `website/app/Config/UpdatePolicy.php` to HTTPS and exact reviewed distribution hostnames (default `kartar.kelolakasir.id`). Authentication components, fragments, IP/localhost hosts and non-443 ports are rejected. Add another operator-controlled distribution hostname only through reviewed configuration; no wildcard/subdomain suffix trust. Existing untrusted stored links disable the public update prompt without deleting historical settings. This is an origin policy, not APK signature/digest verification or a redirect-chain guarantee; review redirect behavior and distribution identity before rollout.
