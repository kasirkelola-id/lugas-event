// No external auth, notification, or database I/O. All listeners are loopback.
const http = require('http');
const ioc = require('socket.io-client');
jest.mock('mysql2/promise', () => ({ createPool: jest.fn(() => ({
  execute: jest.fn(), end: jest.fn().mockResolvedValue()
})) }));
Object.assign(process.env, {
  DB_HOST:'127.0.0.1',DB_USER:'test',DB_PASSWORD:'',DB_NAME:'test',
  INTERNAL_API_SECRET:'transport-test-secret',
  INTERNAL_API_URL:'http://127.0.0.1/api/internal/socket-auth'
});
global.fetch = jest.fn();
const {server,io,pool,privateUserRoom,abuse} = require('../server');
let clients, sequence;
const pause = () => new Promise(resolve => setTimeout(resolve,30));
function once(emitter,name) {
  return new Promise((resolve,reject) => {
    const timer = setTimeout(() => reject(new Error(`Missing ${name}`)),2000);
    emitter.once(name,value => {clearTimeout(timer);resolve(value);});
  });
}
async function connect(user,tenant,mode) {
  const client = ioc(`http://127.0.0.1:${server.address().port}`, {
    autoConnect:false,reconnection:false,
    transports:mode === 'websocket' ? ['websocket'] : ['polling','websocket'],
    upgrade:mode === 'upgrade'
  });
  clients.push(client);
  client.on('connect',() => client.emit('auth',{token:String(user),tenant_id:tenant}));
  const authenticated = once(client,'auth_success');
  client.connect();
  const upgraded = mode === 'upgrade' ? once(client.io.engine,'upgrade') : null;
  if (mode !== 'websocket') expect(client.io.engine.transport.name).toBe('polling');
  await authenticated;
  if (upgraded) await upgraded;
  expect(client.io.engine.transport.name).toBe(mode === 'polling' ? 'polling' : 'websocket');
  return client;
}
beforeAll(done => {server.listen(0,'127.0.0.1',done);});
afterAll(async () => {io.close();server.close();await pool.end();});
beforeEach(() => {
  abuse.clear();
  clients=[];sequence=0;jest.clearAllMocks();
  global.fetch.mockImplementation(async (url,options) => {
    expect(url).toMatch(/^http:\/\/127\.0\.0\.1\/api\/internal\/(socket-auth|chat-notification)$/);
    if (url.endsWith('chat-notification')) return {ok:true};
    return {ok:true,json:async () => ({status:true,data:{
      user_id:Number(options.headers.Authorization.replace('Bearer ','')),
      karang_taruna_id:Number(options.headers['X-Karang-Taruna-ID']),
      permissions:['chat.read','chat.send']
    }})};
  });
  pool.execute.mockImplementation(async (sql,params) => {
    if (sql.includes('organization_members')) return [[{user_id:params[0]}]];
    if (sql.includes('INSERT INTO chats')) return [{insertId:++sequence}];
    if (sql.includes('DATE_FORMAT')) return [[{created_at_iso:'2026-10-05T00:00:00Z'}]];
    if (sql.includes('chat_rooms')) return [[{id:7,type:'default',karang_taruna_id:101}]];
    throw new Error('Unexpected SQL');
  });
});
afterEach(async () => {clients.forEach(client => client.disconnect());await pause();});

test('HTTP polling handshake advertises a valid Engine.IO v4 websocket upgrade', async () => {
  const response = await new Promise((resolve,reject) => {
    http.get({host:'127.0.0.1',port:server.address().port,
      path:'/socket.io/?EIO=4&transport=polling'},res => {
      let body='';res.on('data',chunk => {body+=chunk;});
      res.on('end',() => resolve({status:res.statusCode,body}));
    }).on('error',reject);
  });
  expect(response.status).toBe(200);
  expect(response.body[0]).toBe('0');
  const handshake = JSON.parse(response.body.slice(1));
  expect(handshake.sid).toEqual(expect.any(String));
  expect(handshake.upgrades).toContain('websocket');
  expect(handshake.maxPayload).toBe(1e6);
});
test.each(['polling','upgrade','websocket'])('%s preserves auth, private multi-device tenant isolation, group and wheel',async mode => {
  const sender = await connect(10,101,mode);
  const receiver = await connect(20,101,mode);
  const receiverDevice = await connect(20,101,mode);
  const otherTenant = await connect(20,102,mode);
  const counts = [0,0,0,0];
  [sender,receiver,receiverDevice,otherTenant].forEach((client,index) => client.on('new_message',() => counts[index]++));
  const delivered = once(receiver,'new_message');
  sender.emit('send_message',{type:'private',receiver_id:20,message:'Synthetic',sender_id:999,karang_taruna_id:102});
  const payload = await delivered;await pause();
  expect(payload).toMatchObject({sender_id:10,karang_taruna_id:101});
  expect(counts).toEqual([1,1,1,0]);
  expect(io.sockets.sockets.get(receiver.id).rooms.has(privateUserRoom(101,20))).toBe(true);
  for (const client of [sender,receiver,receiverDevice]) {
    const joined = once(client,'room_joined');client.emit('join_room',{room_id:7});await joined;
  }
  const group = once(receiver,'new_message');
  sender.emit('send_message',{type:'group',chat_room_id:7,message:'Synthetic group'});
  expect((await group).type).toBe('group');await pause();
  expect(counts).toEqual([2,2,2,0]);
  for (const client of [receiver,otherTenant]) {
    const joined = once(client,'wheel_joined');client.emit('join_wheel',{session_id:7});await joined;
  }
  let wrongWheel=0;otherTenant.on('wheel_closed',() => wrongWheel++);
  const wheel = once(receiver,'wheel_closed');
  io.to('wheel_session_101_7').emit('wheel_closed',{session_id:7});
  expect(await wheel).toEqual({session_id:7});await pause();expect(wrongWheel).toBe(0);
});
test('driver failure logs no message body, token, secret or SQL payload',async () => {
  const sender = await connect(10,101,'websocket');
  const log = jest.spyOn(console,'error').mockImplementation(() => {});
  try {
    pool.execute.mockRejectedValueOnce(Object.assign(new Error('SENSITIVE_SENTINEL'),{
      sql:'SENSITIVE_SENTINEL',parameters:['SENSITIVE_SENTINEL']
    }));
    const denied = once(sender,'error');
    sender.emit('send_message',{type:'private',receiver_id:20,message:'SENSITIVE_SENTINEL'});
    expect((await denied).message).toBe('Failed to send message');
    expect(log).toHaveBeenCalledWith('Error saving message');
    expect(JSON.stringify(log.mock.calls)).not.toContain('SENSITIVE_SENTINEL');
  } finally {log.mockRestore();}
});
test('installed and locked Engine.IO server versions are outside the affected range',() => {
  const patched = version => {
    const [major,minor,patch] = version.split('.').map(Number);
    return major>6 || (major===6 && (minor>6 || (minor===6 && patch>=10)));
  };
  expect(patched(require('../node_modules/engine.io/package.json').version)).toBe(true);
  const lock = require('../package-lock.json');
  const engines = Object.entries(lock.packages).filter(([name]) => name.endsWith('node_modules/engine.io'));
  expect(engines.length).toBeGreaterThan(0);
  for (const [,value] of engines) expect(patched(value.version)).toBe(true);
});
