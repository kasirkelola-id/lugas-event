import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/models/attendance_model.dart';

void main() {
  group('AttendanceModel', () {
    test('fromJson parses correctly', () {
      final json = {
        'absensi_id': 1,
        'event_id': 2,
        'user_id': 3,
        'nama_acara': 'Acara Test',
        'tanggal_acara': '2026-08-25',
        'status_event': 1,
        'waktu_absen': '2026-08-25 10:00:00',
      };

      final attendance = AttendanceModel.fromJson(json);

      expect(attendance.absensiId, 1);
      expect(attendance.eventId, 2);
      expect(attendance.namaAcara, 'Acara Test');
      expect(attendance.tanggalAcara, '2026-08-25');
      expect(attendance.statusEvent, 1);
      expect(attendance.waktuAbsen, '2026-08-25 10:00:00');
      expect(attendance.isActive, true);
    });

    test('fromJson parses string integers correctly', () {
      final json = {
        'absensi_id': '10',
        'event_id': '20',
        'user_id': '30',
        'status_event': '0',
      };

      final attendance = AttendanceModel.fromJson(json);

      expect(attendance.absensiId, 10);
      expect(attendance.eventId, 20);
      expect(attendance.statusEvent, 0);
      expect(attendance.isActive, false);
    });
  });
}
