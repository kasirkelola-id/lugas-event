/// Tracks server-assigned chat IDs so history and realtime events can be safely
/// reconciled without treating equal text or timestamps as duplicates.
class ChatIdDeduplicator {
  final Set<int> _ids = <int>{};

  bool add(int id) => _ids.add(id);

  void addAll(Iterable<int> ids) => _ids.addAll(ids);

  bool contains(int id) => _ids.contains(id);
}
