class AttendanceModel {
  final int absensiId;
  final int eventId;
  final String waktuAbsen;
  final String namaAcara;
  final String tanggalAcara;
  final int statusEvent;

  AttendanceModel({
    required this.absensiId,
    required this.eventId,
    required this.waktuAbsen,
    required this.namaAcara,
    required this.tanggalAcara,
    required this.statusEvent,
  });

  bool get isActive => statusEvent == 1;

  factory AttendanceModel.fromJson(Map<String, dynamic> json) {
    return AttendanceModel(
      absensiId: json['absensi_id'] is int ? json['absensi_id'] : int.parse(json['absensi_id'].toString()),
      eventId: json['event_id'] is int ? json['event_id'] : int.parse(json['event_id'].toString()),
      waktuAbsen: json['waktu_absen'] ?? '',
      namaAcara: json['nama_acara'] ?? '',
      tanggalAcara: json['tanggal_acara'] ?? '',
      statusEvent: json['status_event'] != null ? (json['status_event'] is int ? json['status_event'] : int.parse(json['status_event'].toString())) : 0,
    );
  }
}
