import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/models/user_model.dart';

void main() {
  group('UserModel', () {
    test('fromJson parses correctly', () {
      final json = {
        'id': 1,
        'nama_lengkap': 'Administrator Pengelola',
        'nama_panggilan': 'Admin',
        'username': 'pengelola',
        'no_whatsapp': '081234567890',
        'role_level': 'pengelola',
        'status_aktif': 1,
      };

      final user = UserModel.fromJson(json);

      expect(user.id, 1);
      expect(user.namaLengkap, 'Administrator Pengelola');
      expect(user.namaPanggilan, 'Admin');
      expect(user.username, 'pengelola');
      expect(user.noWhatsapp, '081234567890');
      expect(user.roleLevel, 'pengelola');
      expect(user.statusAktif, 1);
    });

    test('fromJson parses string integers correctly', () {
      final json = {
        'id': '2',
        'nama_lengkap': 'Anggota Test',
        'nama_panggilan': 'Anggota',
        'username': 'anggota',
        'no_whatsapp': '08111111',
        'role_level': 'anggota',
        'status_aktif': '1',
      };

      final user = UserModel.fromJson(json);

      expect(user.id, 2);
      expect(user.statusAktif, 1);
    });
  });
}
