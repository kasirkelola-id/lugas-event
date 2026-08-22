class ParticipantModel {
  final int participantId;
  final int userId;
  final String namaLengkap;
  final String namaPanggilan;
  final String whatsapp;
  final String roleLevel;

  ParticipantModel({
    required this.participantId,
    required this.userId,
    required this.namaLengkap,
    required this.namaPanggilan,
    required this.whatsapp,
    required this.roleLevel,
  });

  factory ParticipantModel.fromJson(Map<String, dynamic> json) {
    return ParticipantModel(
      participantId: json['participant_id'] is int ? json['participant_id'] : int.tryParse(json['participant_id'].toString()) ?? 0,
      userId: json['user_id'] is int ? json['user_id'] : int.tryParse(json['user_id'].toString()) ?? 0,
      namaLengkap: json['nama_lengkap'] ?? '',
      namaPanggilan: json['nama_panggilan'] ?? '',
      whatsapp: json['whatsapp'] ?? '',
      roleLevel: json['role_level'] ?? '',
    );
  }
}
