class EventModel {
  final int id;
  final String namaAcara;
  final String tanggalAcara;
  final String? waktuMulai;
  final String? waktuSelesai;
  final String kodeQr;
  final int statusAktif;
  final String? statusKegiatan;
  final String? userAttendanceStatus;
  final int dibuatOleh;
  final int? jumlahHadir;
  final bool requireGps;
  final double? latitude;
  final double? longitude;
  final int? radius;
  final String createdAt;

  EventModel({
    required this.id,
    required this.namaAcara,
    required this.tanggalAcara,
    this.waktuMulai,
    this.waktuSelesai,
    required this.kodeQr,
    required this.statusAktif,
    this.statusKegiatan,
    this.userAttendanceStatus,
    required this.dibuatOleh,
    this.jumlahHadir,
    this.requireGps = false,
    this.latitude,
    this.longitude,
    this.radius,
    required this.createdAt,
  });

  bool get isActive => statusAktif == 1;

  factory EventModel.fromJson(Map<String, dynamic> json) {
    return EventModel(
      id: json['id'] != null ? (json['id'] is int ? json['id'] : int.tryParse(json['id'].toString()) ?? 0) : 0,
      namaAcara: json['nama_acara'] ?? '',
      tanggalAcara: json['tanggal_acara'] ?? '',
      waktuMulai: json['waktu_mulai'],
      waktuSelesai: json['waktu_selesai'],
      kodeQr: json['kode_qr'] ?? '',
      statusAktif: json['status_aktif'] != null ? (json['status_aktif'] is int ? json['status_aktif'] : int.tryParse(json['status_aktif'].toString()) ?? 0) : 0,
      statusKegiatan: json['status_kegiatan'],
      userAttendanceStatus: json['user_attendance_status'],
      dibuatOleh: json['dibuat_oleh'] != null ? (json['dibuat_oleh'] is int ? json['dibuat_oleh'] : int.tryParse(json['dibuat_oleh'].toString()) ?? 0) : 0,
      jumlahHadir: json['jumlah_hadir'] != null ? (json['jumlah_hadir'] is int ? json['jumlah_hadir'] : int.tryParse(json['jumlah_hadir'].toString())) : null,
      requireGps: json['require_gps'] == 1 || json['require_gps'] == true,
      latitude: json['latitude'] != null ? double.tryParse(json['latitude'].toString()) : null,
      longitude: json['longitude'] != null ? double.tryParse(json['longitude'].toString()) : null,
      radius: json['radius'] != null ? int.tryParse(json['radius'].toString()) : null,
      createdAt: json['created_at'] ?? '',
    );
  }
}
