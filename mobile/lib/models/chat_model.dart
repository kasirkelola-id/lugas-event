class Chat {
  final int id;
  final int karangTarunaId;
  final String type;
  final int senderId;
  final int? receiverId;
  final String message;
  final DateTime createdAt;
  final String? namaLengkap;
  final String? roleLevel;

  Chat({
    required this.id,
    required this.karangTarunaId,
    required this.type,
    required this.senderId,
    this.receiverId,
    required this.message,
    required this.createdAt,
    this.namaLengkap,
    this.roleLevel,
  });

  factory Chat.fromJson(Map<String, dynamic> json) {
    return Chat(
      id: int.parse(json['id'].toString()),
      karangTarunaId: int.parse(json['karang_taruna_id'].toString()),
      type: json['type'],
      senderId: int.parse(json['sender_id'].toString()),
      receiverId: json['receiver_id'] != null ? int.parse(json['receiver_id'].toString()) : null,
      message: json['message'],
      createdAt: DateTime.parse(json['created_at']),
      namaLengkap: json['nama_lengkap'],
      roleLevel: json['role_level'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'karang_taruna_id': karangTarunaId,
      'type': type,
      'sender_id': senderId,
      'receiver_id': receiverId,
      'message': message,
      'created_at': createdAt.toIso8601String(),
      'nama_lengkap': namaLengkap,
      'role_level': roleLevel,
    };
  }
}
