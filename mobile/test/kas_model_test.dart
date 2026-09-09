import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/models/kas_model.dart';

void main() {
  group('KasModel Tests', () {
    test('fromJson parses correctly with valid data', () {
      final json = {
        'id': 1,
        'jenis': 'pemasukan',
        'nominal': 50000,
        'keterangan': 'Iuran bulanan',
        'tanggal': '2026-09-09',
        'dibuat_oleh': 2,
        'pembuat': 'Budi',
        'created_at': '2026-09-09 10:00:00',
      };

      final kas = KasModel.fromJson(json);

      expect(kas.id, 1);
      expect(kas.jenis, 'pemasukan');
      expect(kas.nominal, 50000);
      expect(kas.jumlah, 50000);
      expect(kas.keterangan, 'Iuran bulanan');
      expect(kas.tanggal.year, 2026);
      expect(kas.tanggal.month, 9);
      expect(kas.tanggal.day, 9);
      expect(kas.dibuatOleh, 2);
      expect(kas.pembuat, 'Budi');
      expect(kas.namaPencatat, 'Budi');
      expect(kas.isPemasukan, true);
    });

    test('fromJson parses string integers correctly', () {
      final json = {
        'id': '10',
        'jenis': 'pengeluaran',
        'nominal': '25000',
        'keterangan': 'Beli sapu',
        'tanggal': '2026-09-10T12:00:00Z',
        'dibuat_oleh': '3',
        'pembuat': null,
        'created_at': '2026-09-10 12:00:00',
      };

      final kas = KasModel.fromJson(json);

      expect(kas.id, 10);
      expect(kas.jenis, 'pengeluaran');
      expect(kas.nominal, 25000);
      expect(kas.jumlah, 25000);
      expect(kas.tanggal.year, 2026);
      expect(kas.dibuatOleh, 3);
      expect(kas.pembuat, null);
      expect(kas.namaPencatat, null);
      expect(kas.isPemasukan, false);
    });
  });
}
