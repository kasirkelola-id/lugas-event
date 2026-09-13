import 'package:flutter/foundation.dart';

class ChatPaginationController {
  List<Map<String, dynamic>> privateContacts = [];
  final int contactsPageSize = 50;
  int contactsOffset = 0;
  bool hasMoreContacts = true;
  bool isLoadingMoreContacts = false;
  bool isLoadingPrivate = true;

  void handleRefresh(List<Map<String, dynamic>> freshContacts) {
    final List<Map<String, dynamic>> uniqueContacts = [];
    final Set<int> seenIds = {};
    for (var c in freshContacts) {
      if (c['contact_id'] != null && !seenIds.contains(c['contact_id'])) {
        seenIds.add(c['contact_id']);
        uniqueContacts.add(c);
      }
    }

    privateContacts = uniqueContacts;
    contactsOffset = uniqueContacts.length;
    hasMoreContacts = (freshContacts.length >= contactsPageSize);
    isLoadingPrivate = false;
    isLoadingMoreContacts = false;
  }

  bool canLoadMore() {
    return !isLoadingPrivate && !isLoadingMoreContacts && hasMoreContacts;
  }

  void startLoadMore() {
    isLoadingMoreContacts = true;
  }

  void abortLoadMore() {
    isLoadingMoreContacts = false;
  }

  void handleLoadMore(List<Map<String, dynamic>> moreContacts) {
    if (moreContacts.isEmpty) {
      hasMoreContacts = false;
      isLoadingMoreContacts = false;
      return;
    }

    final Set<int> existingIds = privateContacts
        .map((c) => c['contact_id'] as int)
        .toSet();

    final List<Map<String, dynamic>> newContacts = [];
    for (var c in moreContacts) {
      if (c['contact_id'] != null && !existingIds.contains(c['contact_id'])) {
        existingIds.add(c['contact_id']);
        newContacts.add(c);
      }
    }

    privateContacts.addAll(newContacts);
    contactsOffset = privateContacts.length;
    hasMoreContacts = (moreContacts.length >= contactsPageSize);
    isLoadingMoreContacts = false;
  }
}
