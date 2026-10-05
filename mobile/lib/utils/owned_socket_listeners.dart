import 'package:socket_io_client/socket_io_client.dart' as io;

/// Removes only this owner's callbacks and rejects events from a replaced socket.
class OwnedSocketListeners {
  OwnedSocketListeners(this.handlers);
  final Map<String, void Function(dynamic)> handlers;
  final Map<String, void Function(dynamic)> _installed = {};
  io.Socket? _socket;
  io.Socket? get socket => _socket;

  void attach(io.Socket? socket) {
    if (identical(_socket, socket)) return;
    for (final entry in _installed.entries) {
      _socket?.off(entry.key, entry.value);
    }
    _installed.clear();
    _socket = socket;
    if (socket == null) return;
    for (final entry in handlers.entries) {
      void callback(dynamic data) {
        if (identical(_socket, socket)) entry.value(data);
      }

      _installed[entry.key] = callback;
      socket.on(entry.key, callback);
    }
  }

  void dispose() => attach(null);
}
