import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/screens/chat/chat_pagination_controller.dart';

List<Map<String, dynamic>> mockResponse(int startId, int count) {
  return List.generate(count, (index) => {
    'contact_id': startId - index,
    'contact_name': 'User ${startId - index}',
  });
}

void main() {
  group('Private Contacts Pagination Core Logic', () {
    test('SCENARIO A - 49 CONTACTS (Backend returns 49)', () {
      final state = ChatPaginationController();
      state.handleRefresh(mockResponse(100, 49));

      expect(state.privateContacts.length, 49);
      expect(state.hasMoreContacts, false);
      expect(state.contactsOffset, 49);
    });

    test('SCENARIO B - EXACTLY 50', () {
      final state = ChatPaginationController();
      state.handleRefresh(mockResponse(100, 50));

      expect(state.privateContacts.length, 50);
      expect(state.hasMoreContacts, true);
      expect(state.contactsOffset, 50);

      // Next request returns 0
      state.handleLoadMore([]);

      expect(state.privateContacts.length, 50);
      expect(state.hasMoreContacts, false);
      expect(state.contactsOffset, 50);
    });

    test('SCENARIO C - 51 CONTACTS', () {
      final state = ChatPaginationController();
      state.handleRefresh(mockResponse(100, 50));
      state.handleLoadMore(mockResponse(50, 1));

      expect(state.privateContacts.length, 51);
      expect(state.hasMoreContacts, false);
      expect(state.contactsOffset, 51);

      // Ensure IDs are unique and correct
      final ids = state.privateContacts.map((c) => c['contact_id'] as int).toSet();
      expect(ids.length, 51);
    });

    test('SCENARIO D - 100 CONTACTS', () {
      final state = ChatPaginationController();
      state.handleRefresh(mockResponse(100, 50));
      state.handleLoadMore(mockResponse(50, 50));

      expect(state.privateContacts.length, 100);
      expect(state.hasMoreContacts, true);
      expect(state.contactsOffset, 100);

      state.handleLoadMore([]);
      expect(state.privateContacts.length, 100);
      expect(state.hasMoreContacts, false);
    });

    test('SCENARIO E - 101 CONTACTS', () {
      final state = ChatPaginationController();
      state.handleRefresh(mockResponse(101, 50));
      state.handleLoadMore(mockResponse(51, 50));
      state.handleLoadMore(mockResponse(1, 1));

      expect(state.privateContacts.length, 101);
      expect(state.hasMoreContacts, false);
      expect(state.contactsOffset, 101);
      expect(state.privateContacts.last['contact_id'], 1);
    });

    test('SCENARIO F - OVERLAPPING PAGES (Duplicate contact_id)', () {
      final state = ChatPaginationController();
      final page1 = mockResponse(100, 50); // 100 to 51
      state.handleRefresh(page1);

      final page2 = mockResponse(52, 10); // Overlaps 52 and 51
      state.handleLoadMore(page2);

      expect(state.privateContacts.length, 58); // 50 + 8 unique new
      expect(state.contactsOffset, 58);
      // It was a short page (10 < 50)
      expect(state.hasMoreContacts, false);

      final duplicateCount = state.privateContacts.length -
          state.privateContacts.map((e) => e['contact_id']).toSet().length;
      expect(duplicateCount, 0, reason: 'Must not contain duplicate rows');
    });

    test('SCENARIO G - MANUAL REFRESH (Reset strategy)', () {
      final state = ChatPaginationController();
      state.handleRefresh(mockResponse(100, 50));
      state.handleLoadMore(mockResponse(50, 50));

      expect(state.privateContacts.length, 100);

      // Manual refresh
      state.handleRefresh(mockResponse(100, 50));

      expect(state.privateContacts.length, 50);
      expect(state.contactsOffset, 50);
      expect(state.hasMoreContacts, true);
    });

    test('SCENARIO H - EXISTING CONTACT REALTIME (Reset strategy)', () {
      final state = ChatPaginationController();
      state.handleRefresh([
        {'contact_id': 2},
        {'contact_id': 1},
      ]);

      // Realtime message from contact 1, backend bumps it to top
      state.handleRefresh([
        {'contact_id': 1},
        {'contact_id': 2},
      ]);

      expect(state.privateContacts.length, 2);
      expect(state.privateContacts.first['contact_id'], 1);
    });

    test('SCENARIO I - FIRST-EVER REALTIME CONTACT', () {
      final state = ChatPaginationController();
      state.handleRefresh([]);

      state.handleRefresh([
        {'contact_id': 1},
      ]);

      expect(state.privateContacts.length, 1);
      expect(state.privateContacts.first['contact_id'], 1);
    });

    test('SCENARIO J - EXPIRED CONTACT', () {
      final state = ChatPaginationController();
      state.handleRefresh([
        {'contact_id': 2},
        {'contact_id': 1}, // this will expire
      ]);

      state.handleRefresh([
        {'contact_id': 2},
      ]); // 1 expired and backend didn't return it

      expect(state.privateContacts.length, 1);
      expect(state.privateContacts.first['contact_id'], 2);
    });

    test('SCENARIO K - LOAD MORE ERROR', () {
      final state = ChatPaginationController();
      state.handleRefresh(mockResponse(100, 50));

      expect(state.privateContacts.length, 50);
      expect(state.hasMoreContacts, true);

      // Simulate network error (caller catches error and calls nothing, or we just abort)
      state.abortLoadMore();

      expect(state.privateContacts.length, 50);
      expect(state.hasMoreContacts, true);
      expect(state.contactsOffset, 50);
    });

    test('SCENARIO L - CONCURRENT SCROLL EVENTS', () {
      final state = ChatPaginationController();
      state.handleRefresh(mockResponse(100, 50));

      bool req1 = false;
      if (state.canLoadMore()) {
        state.startLoadMore();
        req1 = true;
      }

      bool req2 = false;
      if (state.canLoadMore()) {
        state.startLoadMore();
        req2 = true;
      }

      expect(req1, true);
      expect(req2, false);
      expect(state.isLoadingMoreContacts, true);

      // Resolve first request
      state.handleLoadMore(mockResponse(50, 50));
      expect(state.isLoadingMoreContacts, false);
      expect(state.privateContacts.length, 100);
    });
  });
}
