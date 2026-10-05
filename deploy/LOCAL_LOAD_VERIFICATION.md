# Disposable local load verification

This is a development-machine smoke/load probe, not production capacity or a
worker-sizing recommendation. It runs actual authenticated PHP HTTP requests,
Socket.IO, current auth/tenant rules, MySQL8 persistence and transactional jobs.
Windows PHP's built-in server has one worker. The Node server and synthetic
clients share one process, so reported RSS/event-loop lag include the driver.
It does not execute a notification worker or contact Firebase.

Use only a dedicated loopback MySQL8 with the disposable test-schema account
described in `website/tests/MySQL/README.md`. Never pass an application database,
production credentials, tunnel or existing schema. The PHP parent creates a
new random namespace, runs fresh migrations, inserts synthetic approved members
and live hashed bearer tokens, and drops only its successfully created schema.
One thousand fixture users permit higher stages if earlier stages pass.

From `website`, set the existing explicit MySQL test variables privately, plus:

```powershell
$env:CI_ENVIRONMENT = 'testing'
$env:NODE_ENV = 'test'
$env:FCM_MOCK = 'true'
$env:KARTAR_LOCAL_LOAD_ENABLE = '1'
$env:KARTAR_LOCAL_NODE_BINARY = 'C:\path\to\node.exe'
php tests/_support/local_load_fixture.php
```

The owned TEMP directory contains logs/result JSON; synthetic bearer material is
removed by the parent's finally block. Database credentials stay in inherited
environment, never arguments/results. Do not print or commit credentials.
The HTTP bootstrap skips application `.env`, replaces both DB groups before any
connection, independently verifies MySQL8/loopback, uses its owned public/writable
paths and denies application cURL. Node starts from the empty owned TEMP directory
and receives explicit test DB host/port/name/credentials/internal URL/secret.
Fetch permits only the two allocated `127.0.0.1` HTTP origins and rejects redirects.
Both services bind loopback. Actual auth uses the unchanged PHP filters. The
`X-RateLimit-Test: local-load` header enables existing testing mutation quotas;
no rate-limit bypass is sent. Node's normal quotas and queue cap remain intact.

Stages are25,50,100,250,500,1000. At most four authentications run together.
Each completed stage executes two concurrent HTTP rounds and one private chat
per client plus the identical UUID retry. Capture HTTP/chat p50/p95/p99, HTTP RPS
and max in-flight, authenticated sockets, numeric pool gauges, RSS and lag.
Pool gauges are samples after work, not queue peak measurements. Sender fanout
is checked; no exactly-once recipient/device delivery or durable reconnect
recovery is claimed. Foreign-tenant HTTP must403; two inventory decisions must
200/409 and final stock+approved must1. Final chat rows=unique ACKs=chat jobs with
zero duplicate logical persistence. Windows HTTP serial execution does not
replace the separate real MySQL concurrent transaction tests.

Stop at>=1% errors/rejections, any security/data invariant failure, RSS512MiB or
lag p99>1s. Stages500+ require at least1GiB free memory. Quota rejections are
recorded separately from unexpected errors and still stop escalation. In
particular, the existing auth60/IP/minute quota bounds a same-loopback burst;
do not clear its state, spoof forwarding headers or increase quotas for this test.
Higher stages may remain unexecuted. Mark the stopped stage explicitly.

Finally disconnect owned clients, close the Node pool/listener, stop only the
owned PHP child, and drop the owned schema. Retain TEMP logs; a forcibly killed
parent may leave an owned random test schema requiring ownership inspection.
Never perform recursive runtime/upload cleanup or touch another service.

Deployment remains a separate manual gate requiring multiworker staging,
provider/device QA, sustained workload, proxy policy and infrastructure metrics.
