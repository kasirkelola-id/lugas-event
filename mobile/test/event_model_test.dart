import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/models/event_model.dart';

void main() {
  group('EventModel', () {
    test('fromJson parses correctly', () {
      final json = {
        'id': 1,
        'nama_acara': 'Acara 1',
        'tanggal_acara': '2026-08-25',
        'kode_qr': 'QR123',
        'status_aktif': 1,
        'dibuat_oleh': 2,
        'jumlah_hadir': 5,
        'created_at': '2026-08-20 10:00:00',
      };

      final event = EventModel.fromJson(json);

      expect(event.id, 1);
      expect(event.namaAcara, 'Acara 1');
      expect(event.tanggalAcara, '2026-08-25');
      expect(event.kodeQr, 'QR123');
      expect(event.statusAktif, 1);
      expect(event.dibuatOleh, 2);
      expect(event.jumlahHadir, 5);
      expect(event.createdAt, '2026-08-20 10:00:00');
      expect(event.isActive, true);
    });

    test('fromJson parses string integers correctly', () {
      final json = {
        'id': '2',
        'status_aktif': '0',
        'dibuat_oleh': '1',
        'jumlah_hadir': '10',
      };

      final event = EventModel.fromJson(json);

      expect(event.id, 2);
      expect(event.statusAktif, 0);
      expect(event.dibuatOleh, 1);
      expect(event.jumlahHadir, 10);
      expect(event.isActive, false);
    });
  });
}
