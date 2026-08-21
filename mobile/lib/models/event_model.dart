class EventModel {
  final int id;
  final String namaAcara;
  final String tanggalAcara;
  final String kodeQr;
  final int statusAktif;
  final int dibuatOleh;
  final int? jumlahHadir;
  final String createdAt;

  EventModel({
    required this.id,
    required this.namaAcara,
    required this.tanggalAcara,
    required this.kodeQr,
    required this.statusAktif,
    required this.dibuatOleh,
    this.jumlahHadir,
    required this.createdAt,
  });

  bool get isActive => statusAktif == 1;

  factory EventModel.fromJson(Map<String, dynamic> json) {
    return EventModel(
      id: json['id'] is int ? json['id'] : int.parse(json['id'].toString()),
      namaAcara: json['nama_acara'] ?? '',
      tanggalAcara: json['tanggal_acara'] ?? '',
      kodeQr: json['kode_qr'] ?? '',
      statusAktif: json['status_aktif'] is int ? json['status_aktif'] : int.parse(json['status_aktif'].toString()),
      dibuatOleh: json['dibuat_oleh'] is int ? json['dibuat_oleh'] : int.parse(json['dibuat_oleh'].toString()),
      jumlahHadir: json['jumlah_hadir'] != null ? (json['jumlah_hadir'] is int ? json['jumlah_hadir'] : int.parse(json['jumlah_hadir'].toString())) : null,
      createdAt: json['created_at'] ?? '',
    );
  }
}
