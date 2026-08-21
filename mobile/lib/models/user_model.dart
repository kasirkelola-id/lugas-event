class UserModel {
  final int id;
  final String namaLengkap;
  final String namaPanggilan;
  final String username;
  final String noWhatsapp;
  final String roleLevel;
  final int statusAktif;

  UserModel({
    required this.id,
    required this.namaLengkap,
    required this.namaPanggilan,
    required this.username,
    required this.noWhatsapp,
    required this.roleLevel,
    required this.statusAktif,
  });

  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      id: json['id'] is int ? json['id'] : int.parse(json['id'].toString()),
      namaLengkap: json['nama_lengkap'] ?? '',
      namaPanggilan: json['nama_panggilan'] ?? '',
      username: json['username'] ?? '',
      noWhatsapp: json['no_whatsapp'] ?? '',
      roleLevel: json['role_level'] ?? '',
      statusAktif: json['status_aktif'] is int ? json['status_aktif'] : int.parse(json['status_aktif'].toString()),
    );
  }
}
