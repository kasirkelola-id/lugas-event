class ReportModel {
  final int totalAcara;
  final int acaraAktif;
  final int acaraSelesai;
  final int totalPeserta;
  final int totalHadir;
  final int totalBelumHadir;
  final double persentaseKehadiran;

  ReportModel({
    required this.totalAcara,
    required this.acaraAktif,
    required this.acaraSelesai,
    required this.totalPeserta,
    required this.totalHadir,
    required this.totalBelumHadir,
    required this.persentaseKehadiran,
  });

  factory ReportModel.fromJson(Map<String, dynamic> json) {
    return ReportModel(
      totalAcara: json['total_acara'] is int ? json['total_acara'] : int.tryParse(json['total_acara'].toString()) ?? 0,
      acaraAktif: json['acara_aktif'] is int ? json['acara_aktif'] : int.tryParse(json['acara_aktif'].toString()) ?? 0,
      acaraSelesai: json['acara_selesai'] is int ? json['acara_selesai'] : int.tryParse(json['acara_selesai'].toString()) ?? 0,
      totalPeserta: json['total_peserta'] is int ? json['total_peserta'] : int.tryParse(json['total_peserta'].toString()) ?? 0,
      totalHadir: json['total_hadir'] is int ? json['total_hadir'] : int.tryParse(json['total_hadir'].toString()) ?? 0,
      totalBelumHadir: json['total_belum_hadir'] is int ? json['total_belum_hadir'] : int.tryParse(json['total_belum_hadir'].toString()) ?? 0,
      persentaseKehadiran: json['persentase_kehadiran'] is double ? json['persentase_kehadiran'] : double.tryParse(json['persentase_kehadiran'].toString()) ?? 0.0,
    );
  }
}
