class ChatRoom {
  final int id;
  final int karangTarunaId;
  final String name;
  final String type;
  final int? createdBy;
  final DateTime? createdAt;
  final String? lastMessage;
  final DateTime? lastMessageAt;

  ChatRoom({
    required this.id,
    required this.karangTarunaId,
    required this.name,
    required this.type,
    this.createdBy,
    this.createdAt,
    this.lastMessage,
    this.lastMessageAt,
  });

  factory ChatRoom.fromJson(Map<String, dynamic> json) {
    return ChatRoom(
      id: int.parse(json['id'].toString()),
      karangTarunaId: int.parse(json['karang_taruna_id'].toString()),
      name: json['name'],
      type: json['type'],
      createdBy: json['created_by'] != null ? int.parse(json['created_by'].toString()) : null,
      createdAt: json['created_at'] != null ? DateTime.parse(json['created_at']) : null,
      lastMessage: json['last_message'],
      lastMessageAt: json['last_message_at'] != null ? DateTime.parse(json['last_message_at']) : null,
    );
  }
}
