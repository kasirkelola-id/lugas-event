class DashboardSummary {
  final UpcomingEventSummary? upcomingEvent;
  final LatestAnnouncementSummary? latestAnnouncement;
  final ActiveVotingSummary? activeVoting;
  final MyActiveLoanSummary? myActiveLoan;
  final ManagementMetrics? management;

  DashboardSummary({
    this.upcomingEvent,
    this.latestAnnouncement,
    this.activeVoting,
    this.myActiveLoan,
    this.management,
  });

  factory DashboardSummary.fromJson(Map<String, dynamic> json) {
    return DashboardSummary(
      upcomingEvent: json['upcoming_event'] != null ? UpcomingEventSummary.fromJson(json['upcoming_event']) : null,
      latestAnnouncement: json['latest_announcement'] != null ? LatestAnnouncementSummary.fromJson(json['latest_announcement']) : null,
      activeVoting: json['active_voting'] != null ? ActiveVotingSummary.fromJson(json['active_voting']) : null,
      myActiveLoan: json['my_active_loan'] != null ? MyActiveLoanSummary.fromJson(json['my_active_loan']) : null,
      management: json['management'] != null ? ManagementMetrics.fromJson(json['management']) : null,
    );
  }
}

class UpcomingEventSummary {
  final int id;
  final String title;
  final String date;
  final String time;
  final dynamic status;

  UpcomingEventSummary({
    required this.id,
    required this.title,
    required this.date,
    required this.time,
    this.status,
  });

  factory UpcomingEventSummary.fromJson(Map<String, dynamic> json) {
    return UpcomingEventSummary(
      id: int.parse(json['id'].toString()),
      title: json['title'],
      date: json['date'],
      time: json['time'],
      status: json['status'],
    );
  }
}

class LatestAnnouncementSummary {
  final int id;
  final String title;
  final String preview;
  final String date;

  LatestAnnouncementSummary({
    required this.id,
    required this.title,
    required this.preview,
    required this.date,
  });

  factory LatestAnnouncementSummary.fromJson(Map<String, dynamic> json) {
    return LatestAnnouncementSummary(
      id: int.parse(json['id'].toString()),
      title: json['title'],
      preview: json['preview'],
      date: json['date'],
    );
  }
}

class ActiveVotingSummary {
  final int id;
  final String title;
  final String status;

  ActiveVotingSummary({
    required this.id,
    required this.title,
    required this.status,
  });

  factory ActiveVotingSummary.fromJson(Map<String, dynamic> json) {
    return ActiveVotingSummary(
      id: int.parse(json['id'].toString()),
      title: json['title'],
      status: json['status'],
    );
  }
}

class MyActiveLoanSummary {
  final int id;
  final String inventoryName;
  final int quantity;
  final String status;
  final String borrowDate;
  final String returnDate;

  MyActiveLoanSummary({
    required this.id,
    required this.inventoryName,
    required this.quantity,
    required this.status,
    required this.borrowDate,
    required this.returnDate,
  });

  factory MyActiveLoanSummary.fromJson(Map<String, dynamic> json) {
    return MyActiveLoanSummary(
      id: int.parse(json['id'].toString()),
      inventoryName: json['inventory_name'],
      quantity: int.parse(json['quantity'].toString()),
      status: json['status'],
      borrowDate: json['borrow_date'],
      returnDate: json['return_date'],
    );
  }
}

class ManagementMetrics {
  final int pendingLoans;
  final int activeMembers;
  final int outOfStock;

  ManagementMetrics({
    required this.pendingLoans,
    required this.activeMembers,
    required this.outOfStock,
  });

  factory ManagementMetrics.fromJson(Map<String, dynamic> json) {
    return ManagementMetrics(
      pendingLoans: int.parse(json['pending_loans'].toString()),
      activeMembers: int.parse(json['active_members'].toString()),
      outOfStock: int.parse(json['out_of_stock'].toString()),
    );
  }
}
