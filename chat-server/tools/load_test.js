const { io } = require('socket.io-client');
const { performance } = require('perf_hooks');

const NUM_CLIENTS = parseInt(process.argv[2]) || parseInt(process.env.NUM_CLIENTS) || 100;
const SERVER_URL = process.argv[3] || process.env.SERVER_URL || 'http://localhost:3000';

let metrics = {
    connections_attempted: 0,
    connections_established: 0,
    auth_attempted: 0,
    auth_success: 0,
    auth_timeout: 0,
    auth_failure: 0,
    messages_attempted: 0,
    messages_db_inserted: 0, // Assume equal to successfully relayed if broadcasted
    events_expected: 0,
    events_received: 0,
    wrong_recipient: 0,
    wrong_tenant: 0,
    wrong_room: 0,
    duplicates: 0,
    missing: 0,
    connection_errors: 0
};

let authLatencies = [];
let msgLatencies = [];

let receivedMsgIds = new Set();
let expectedMsgIds = new Set();

const clients = [];

// Event loop lag monitoring
let lastLoop = performance.now();
let maxLag = 0;
setInterval(() => {
    const lag = performance.now() - lastLoop;
    if (lag > maxLag && lag > 15) maxLag = lag - 15; // Rough lag, baseline 15ms interval
    lastLoop = performance.now();
}, 15);

function getPercentile(arr, p) {
    if (arr.length === 0) return 0;
    arr.sort((a, b) => a - b);
    const index = Math.ceil(arr.length * (p / 100)) - 1;
    return arr[index].toFixed(2);
}

function printResources(label) {
    const mem = process.memoryUsage();
    console.log(`[${label}] RSS: ${(mem.rss/1024/1024).toFixed(2)}MB | HeapUsed: ${(mem.heapUsed/1024/1024).toFixed(2)}MB | Max Lag: ${maxLag.toFixed(2)}ms`);
    maxLag = 0;
}

async function start() {
    console.log(`Starting load test with ${NUM_CLIENTS} clients against ${SERVER_URL}`);
    printResources('BEFORE LOAD');

    for (let i = 1; i <= NUM_CLIENTS; i++) {
        metrics.connections_attempted++;
        const client = io(SERVER_URL, {
            transports: ['websocket'],
            reconnection: false
        });

        const isTenant2 = i > 100; // E.g., clients > 100 are tenant 2
        const userId = i;
        const tenantId = isTenant2 ? 2 : 1;
        const token = `fake_jwt_token_${userId}_${tenantId}`;

        let authStart;
        let isAuthSuccess = false;

        client.on('connect', () => {
            metrics.connections_established++;
            metrics.auth_attempted++;
            authStart = performance.now();
            client.emit('auth', { token: token, tenant_id: tenantId });
            
            setTimeout(() => {
                if (!isAuthSuccess) metrics.auth_timeout++;
            }, 5000);
        });

        client.on('connect_error', (err) => {
            metrics.connection_errors++;
        });

        client.on('auth_success', () => {
            isAuthSuccess = true;
            metrics.auth_success++;
            authLatencies.push(performance.now() - authStart);

            // Start sending messages randomly
            setInterval(() => {
                const targetUserId = Math.floor(Math.random() * Math.min(NUM_CLIENTS, 100)) + 1; // Only tenant 1
                if (targetUserId !== userId) {
                    const msgId = `msg_${userId}_${targetUserId}_${performance.now()}`;
                    const sentAt = performance.now();
                    expectedMsgIds.add(msgId);
                      metrics.events_expected += 2;
                    
                    client.emit('send_message', {
                        type: 'private',
                        receiver_id: targetUserId,
                        message: `Test message ${msgId}`,
                        _meta: { msgId, sentAt, tenantId, targetUserId } // Add meta for tracking
                    });
                    metrics.messages_attempted++;
                }
            }, 5000 + Math.random() * 5000);
        });

        client.on('auth_error', () => {
            isAuthSuccess = true; // prevent timeout count
            metrics.auth_failure++;
        });

        client.on('new_message', (data) => {
            metrics.events_received++;
            
            // Reconstruct meta if sent from sender
            const metaStr = data.message.split('msg_')[1];
            if (metaStr) {
                const msgId = 'msg_' + metaStr;
                const uniqueKey = client.id + '_' + msgId;
                if (receivedMsgIds.has(uniqueKey)) {
                    metrics.duplicates++;
                } else {
                    receivedMsgIds.add(uniqueKey);
                    metrics.messages_db_inserted++; // Count as success
                }
            }
            
            if (data.receiver_id && parseInt(data.receiver_id) !== userId && parseInt(data.sender_id) !== userId) {
                metrics.wrong_recipient++;
            }
            if (data.karang_taruna_id && parseInt(data.karang_taruna_id) !== tenantId) {
                metrics.wrong_tenant++;
            }
            // Room check could go here if we were testing groups in this run
        });

        clients.push(client);
        
        // Stagger connections
        await new Promise(r => setTimeout(r, 10));
    }

    let loopCounter = 0;
    setInterval(() => {
        loopCounter++;
        metrics.missing = metrics.events_expected - receivedMsgIds.size;
        
        console.log(`\n--- METRICS SNAPSHOT ---`);
        console.log(`Conn: Att=${metrics.connections_attempted} Est=${metrics.connections_established} Err=${metrics.connection_errors}`);
        console.log(`Auth: Att=${metrics.auth_attempted} Succ=${metrics.auth_success} Fail=${metrics.auth_failure} Timeout=${metrics.auth_timeout}`);
        console.log(`Msgs: Att=${metrics.messages_attempted} Expected=${metrics.events_expected} Rcvd=${metrics.events_received}`);
        console.log(`Errors: WrongRecip=${metrics.wrong_recipient} WrongTenant=${metrics.wrong_tenant} Duplicates=${metrics.duplicates} Missing=${metrics.missing}`);
        console.log(`Auth Latency (ms): p50=${getPercentile(authLatencies, 50)} p95=${getPercentile(authLatencies, 95)} p99=${getPercentile(authLatencies, 99)}`);
        
        if (loopCounter === 3) { // After 15 seconds, print peak
            printResources('PEAK LOAD');
        } else if (loopCounter === 6) { // After 30 seconds, end
            printResources('AFTER LOAD');
            process.exit(0);
        }
    }, 5000);
}

start();
