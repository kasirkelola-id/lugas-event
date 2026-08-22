class AnnouncementModel {
  final int id;
  final String judul;
  final String isi;
  final int dibuatOleh;
  final String pembuat;
  final String targetRole;
  final int statusAktif;
  final String createdAt;

  AnnouncementModel({
    required this.id,
    required this.judul,
    required this.isi,
    required this.dibuatOleh,
    required this.pembuat,
    required this.targetRole,
    required this.statusAktif,
    required this.createdAt,
  });

  factory AnnouncementModel.fromJson(Map<String, dynamic> json) {
    return AnnouncementModel(
      id: json['id'] is int ? json['id'] : int.tryParse(json['id'].toString()) ?? 0,
      judul: json['judul'] ?? '',
      isi: json['isi'] ?? '',
      dibuatOleh: json['dibuat_oleh'] is int ? json['dibuat_oleh'] : int.tryParse(json['dibuat_oleh'].toString()) ?? 0,
      pembuat: json['pembuat'] ?? 'Admin',
      targetRole: json['target_role'] ?? 'semua',
      statusAktif: json['status_aktif'] is int ? json['status_aktif'] : int.tryParse(json['status_aktif'].toString()) ?? 0,
      createdAt: json['created_at'] ?? '',
    );
  }
}
