import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:mobile/screens/widgets/common/collection_pager.dart';
import 'package:mobile/services/event_service.dart';
import 'package:mobile/services/attendance_service.dart';
import 'package:mobile/services/announcement_service.dart';
import 'package:mobile/services/kas_service.dart';
import 'package:mobile/services/inventory_service.dart';
import 'package:mobile/services/voting_service.dart';
import 'package:mobile/services/wheel_service.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  setUp(
    () => SharedPreferences.setMockInitialValues({
      'auth_token': 'synthetic',
      'karang_taruna_id': 101,
      'nama_organisasi': 'Synthetic',
    }),
  );

  test(
    'collection services request one bounded page and retain metadata and old data keys',
    () async {
      final pagination = {
        'page': 2,
        'limit': 20,
        'total': 45,
        'total_pages': 3,
        'has_more': true,
      };
      final requests = <String>[];
      final client = MockClient((request) async {
        expect(request.url.queryParameters['page'], '2');
        expect(request.url.queryParameters['limit'], '20');
        expect(request.headers['X-Karang-Taruna-ID'], '101');
        requests.add(request.url.path);
        return http.Response(
          jsonEncode({
            'status': true,
            'success': true,
            'data': request.url.path.endsWith('/kas')
                ? {'saldo': 123, 'transaksi': []}
                : [],
            'pagination': pagination,
          }),
          200,
        );
      });
      addTearDown(client.close);
      await http.runWithClient(() async {
        final results = [
          await EventService.getEvents(page: 2, limit: 20),
          await AttendanceService.getMyHistory(page: 2, limit: 20),
          await AnnouncementService.getAnnouncements(page: 2, limit: 20),
          await KasService.getKasData(page: 2, limit: 20),
          await InventoryService.getInventories(page: 2, limit: 20),
          await InventoryService.getLoans(page: 2, limit: 20),
          await VotingService.getVotings(page: 2, limit: 20),
          await WheelService.getSessions(page: 2, limit: 20),
        ];
        final keys = [
          'events',
          'history',
          'data',
          'transaksi',
          'inventories',
          'loans',
          'votings',
          'sessions',
        ];
        for (var i = 0; i < results.length; i++) {
          expect(results[i]['success'], true);
          expect(results[i][keys[i]], isEmpty);
          expect(results[i]['pagination'], pagination);
        }
        expect(results[3]['saldo'], 123);
      }, () => client);
      expect(requests.length, 8);
    },
  );

  testWidgets('pager bounds navigation and disables it while loading', (
    tester,
  ) async {
    final selected = <int>[];
    Future<void> render(int page, bool loading) => tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          bottomNavigationBar: CollectionPager(
            pagination: {'page': page, 'total_pages': 3},
            loading: loading,
            onPage: selected.add,
          ),
        ),
      ),
    );
    await render(1, false);
    await tester.tap(find.byTooltip('Sebelumnya'));
    expect(selected, isEmpty);
    await tester.tap(find.byTooltip('Berikutnya'));
    expect(selected, [2]);
    await render(3, true);
    await tester.tap(find.byTooltip('Sebelumnya'));
    await tester.tap(find.byTooltip('Berikutnya'));
    expect(selected, [2]);
    await render(3, false);
    await tester.tap(find.byTooltip('Sebelumnya'));
    expect(selected, [2, 2]);
  });
}
