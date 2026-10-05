import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/utils/owned_socket_listeners.dart';
import 'chat_tenant_isolation_test.dart' show MemorySocket;

void main() {
  test(
    'replacement/disposal removes only owned listeners and preserves another screen',
    () {
      final first = MemorySocket();
      final second = MemorySocket();
      var ownEvents = 0;
      var otherEvents = 0;
      first.on('wheel_closed', (_) => otherEvents++);
      final owner = OwnedSocketListeners({'wheel_closed': (_) => ownEvents++});
      owner.attach(first);
      owner.attach(first);
      first.receive('wheel_closed', {});
      expect(ownEvents, 1);
      owner.attach(second);
      first.receive('wheel_closed', {});
      second.receive('wheel_closed', {});
      expect(ownEvents, 2);
      expect(otherEvents, 2);
      owner.dispose();
      second.receive('wheel_closed', {});
      first.receive('wheel_closed', {});
      expect(ownEvents, 2);
      expect(otherEvents, 3);
      first.dispose();
      second.dispose();
    },
  );
}
