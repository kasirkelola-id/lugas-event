# Chat delivery and rollout

Apply the reviewed forward chat idempotency migration in an isolated restore
before staging either the PHP or Node change. Both servers require the column
and tenant/sender/client-message unique index. Never edit historical migrations.
Production migration execution remains operator work after schema review.

New clients generate a UUID v4 for each logical send. A lost Socket.IO ACK retries
REST with that same UUID. A retry returns the previously stored row; reuse with a
different message/destination fails. Authorization is checked before replay.
The server ACK confirms a canonical persisted row, including its real timestamp.
Legacy clients may omit the UUID and retain their previous non-idempotent behavior.

Flutter keeps at most 50 unconfirmed IDs in memory, scoped to the current bearer
and tenant. Reconnect and an explicit same-message retry retain the ID; success
retires it. Failed confirmation restores the draft. App process termination loses
this in-memory retry state; durable offline chat drafts are not implemented.
History and rendering deduplicate by server row ID. This is idempotent persistence,
not exactly-once device delivery. The unique record lives only as long as its chat
row: after retention deletes the row, a very old replay can create a new one.

At the phase 11 checkpoint, broadcast and notification remain best-effort after
persistence. PHP notification failure does not turn a saved message into a failed
domain response. Node may crash between commit and broadcast/notification; REST
does not yet supply realtime fanout to other sockets. Durable notification work
and reconciliation are tracked separately; do not treat this checkpoint alone as
a closed delivery finding. Staging must exercise the combined PHP/Node/mobile
rollout, reconnect, lost ACK, tenant switching, and provider failure paths.
