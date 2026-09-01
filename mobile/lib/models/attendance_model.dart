class AttendanceModel {
  final int absensiId;
  final int eventId;
  final String waktuAbsen;
  final String? waktuCheckout;
  final int? durasi;
  final String namaAcara;
  final String tanggalAcara;
  final int statusEvent;
  final String namaLengkap;
  final String namaPanggilan;

  AttendanceModel({
    required this.absensiId,
    required this.eventId,
    required this.waktuAbsen,
    this.waktuCheckout,
    this.durasi,
    required this.namaAcara,
    required this.tanggalAcara,
    required this.statusEvent,
    this.namaLengkap = '',
    this.namaPanggilan = '',
  });

  bool get isActive => statusEvent == 1;

  factory AttendanceModel.fromJson(Map<String, dynamic> json) {
    return AttendanceModel(
      absensiId: json['absensi_id'] != null ? (json['absensi_id'] is int ? json['absensi_id'] : int.parse(json['absensi_id'].toString())) : 0,
      eventId: json['event_id'] != null ? (json['event_id'] is int ? json['event_id'] : int.parse(json['event_id'].toString())) : 0,
      waktuAbsen: json['waktu_absen'] ?? '',
      waktuCheckout: json['waktu_checkout'],
      durasi: json['durasi'] != null ? (json['durasi'] is int ? json['durasi'] : int.tryParse(json['durasi'].toString())) : null,
      namaAcara: json['nama_acara'] ?? '',
      tanggalAcara: json['tanggal_acara'] ?? '',
      statusEvent: json['status_event'] != null ? (json['status_event'] is int ? json['status_event'] : int.parse(json['status_event'].toString())) : 0,
      namaLengkap: json['nama_lengkap'] ?? '',
      namaPanggilan: json['nama_panggilan'] ?? '',
    );
  }
}
