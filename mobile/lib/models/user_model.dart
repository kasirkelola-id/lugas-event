class UserModel {
  final int id;
  final String namaLengkap;
  final String namaPanggilan;
  final String username;
  final String noWhatsapp;
  final int rt;
  final String roleLevel;
  final int statusAktif;
  final bool passwordMustChange;
  final String? profilePhotoUrl;

  UserModel({
    required this.id,
    required this.namaLengkap,
    required this.namaPanggilan,
    required this.username,
    required this.noWhatsapp,
    this.rt = 1,
    required this.roleLevel,
    required this.statusAktif,
    this.passwordMustChange = false,
    this.profilePhotoUrl,
  });

  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      id: json['id'] is int ? json['id'] : int.parse(json['id'].toString()),
      namaLengkap: json['nama_lengkap'] ?? '',
      namaPanggilan: json['nama_panggilan'] ?? '',
      username: json['username'] ?? '',
      noWhatsapp: json['no_whatsapp'] ?? '',
      rt: json['rt'] != null ? (json['rt'] is int ? json['rt'] : int.tryParse(json['rt'].toString()) ?? 1) : 1,
      roleLevel: json['role_level'] ?? '',
      statusAktif: json['status_aktif'] != null ? (json['status_aktif'] is int ? json['status_aktif'] : int.tryParse(json['status_aktif'].toString()) ?? 1) : 1,
      passwordMustChange: json['password_must_change'] == true || json['password_must_change'] == 1,
      profilePhotoUrl: json['profile_photo_url'],
    );
  }
}
