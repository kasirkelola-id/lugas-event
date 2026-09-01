class KasModel {
  final int id;
  final String jenis;
  final int nominal;
  final String keterangan;
  final String tanggal;
  final int dibuatOleh;
  final String? pembuat;
  final String createdAt;

  KasModel({
    required this.id,
    required this.jenis,
    required this.nominal,
    required this.keterangan,
    required this.tanggal,
    required this.dibuatOleh,
    this.pembuat,
    required this.createdAt,
  });

  bool get isPemasukan => jenis == 'pemasukan';

  factory KasModel.fromJson(Map<String, dynamic> json) {
    return KasModel(
      id: json['id'] != null ? (json['id'] is int ? json['id'] : int.tryParse(json['id'].toString()) ?? 0) : 0,
      jenis: json['jenis'] ?? '',
      nominal: json['nominal'] != null ? (json['nominal'] is int ? json['nominal'] : int.tryParse(json['nominal'].toString()) ?? 0) : 0,
      keterangan: json['keterangan'] ?? '',
      tanggal: json['tanggal'] ?? '',
      dibuatOleh: json['dibuat_oleh'] != null ? (json['dibuat_oleh'] is int ? json['dibuat_oleh'] : int.tryParse(json['dibuat_oleh'].toString()) ?? 0) : 0,
      pembuat: json['pembuat'],
      createdAt: json['created_at'] ?? '',
    );
  }
}
