class WheelSessionModel {
  final int id;
  final int karangTarunaId;
  final int createdByUserId;
  final String title;
  final String sourceType;
  final int spinDurationSeconds;
  final int removeWinnerAfterSpin;
  final String status;
  final int itemCount;
  final DateTime? createdAt;
  final int creatorId;

  WheelSessionModel({
    required this.id,
    required this.karangTarunaId,
    required this.createdByUserId,
    required this.title,
    required this.sourceType,
    required this.spinDurationSeconds,
    required this.removeWinnerAfterSpin,
    required this.status,
    this.itemCount = 0,
    this.createdAt,
    required this.creatorId,
  });

  factory WheelSessionModel.fromJson(Map<String, dynamic> json) {
    return WheelSessionModel(
      id: json['id'] is int
          ? json['id']
          : int.tryParse(json['id'].toString()) ?? 0,
      karangTarunaId: json['karang_taruna_id'] is int
          ? json['karang_taruna_id']
          : int.tryParse(json['karang_taruna_id'].toString()) ?? 0,
      createdByUserId: json['created_by_user_id'] is int
          ? json['created_by_user_id']
          : int.tryParse(json['created_by_user_id'].toString()) ?? 0,
      title: json['title'] ?? '',
      sourceType: json['source_type'] ?? 'members',
      spinDurationSeconds: json['spin_duration_seconds'] is int
          ? json['spin_duration_seconds']
          : int.tryParse(json['spin_duration_seconds'].toString()) ?? 10,
      removeWinnerAfterSpin: json['remove_winner_after_spin'] is int
          ? json['remove_winner_after_spin']
          : int.tryParse(json['remove_winner_after_spin'].toString()) ?? 0,
      status: json['status'] ?? 'active',
      itemCount: json['item_count'] is int
          ? json['item_count']
          : int.tryParse(json['item_count']?.toString() ?? '0') ?? 0,
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'])
          : null,
      creatorId: json['creator_id'] is int
          ? json['creator_id']
          : int.tryParse(json['creator_id']?.toString() ?? '0') ?? 0,
    );
  }
}

class WheelItemModel {
  final int id;
  final int sessionId;
  final int? memberUserId;
  final String labelSnapshot;
  final bool isActive;

  WheelItemModel({
    required this.id,
    required this.sessionId,
    this.memberUserId,
    required this.labelSnapshot,
    required this.isActive,
  });

  factory WheelItemModel.fromJson(Map<String, dynamic> json) {
    return WheelItemModel(
      id: json['id'] is int
          ? json['id']
          : int.tryParse(json['id'].toString()) ?? 0,
      sessionId: json['session_id'] is int
          ? json['session_id']
          : int.tryParse(json['session_id'].toString()) ?? 0,
      memberUserId: json['member_user_id'] != null
          ? (json['member_user_id'] is int
                ? json['member_user_id']
                : int.tryParse(json['member_user_id'].toString()))
          : null,
      labelSnapshot: json['label_snapshot'] ?? '',
      isActive: (json['is_active']?.toString() ?? '1') == '1',
    );
  }
}

class WheelResultModel {
  final int id;
  final int sessionId;
  final int wheelItemId;
  final String resultLabelSnapshot;
  final int spinSequence;
  final DateTime startedAt;
  final int durationSeconds;

  WheelResultModel({
    required this.id,
    required this.sessionId,
    required this.wheelItemId,
    required this.resultLabelSnapshot,
    required this.spinSequence,
    required this.startedAt,
    required this.durationSeconds,
  });

  factory WheelResultModel.fromJson(Map<String, dynamic> json) {
    return WheelResultModel(
      id: json['id'] is int
          ? json['id']
          : int.tryParse(json['id'].toString()) ?? 0,
      sessionId: json['session_id'] is int
          ? json['session_id']
          : int.tryParse(json['session_id'].toString()) ?? 0,
      wheelItemId: json['wheel_item_id'] is int
          ? json['wheel_item_id']
          : int.tryParse(json['wheel_item_id'].toString()) ?? 0,
      resultLabelSnapshot: json['result_label_snapshot'] ?? '',
      spinSequence: json['spin_sequence'] is int
          ? json['spin_sequence']
          : int.tryParse(json['spin_sequence'].toString()) ?? 1,
      startedAt: DateTime.tryParse(json['started_at'] ?? '') ?? DateTime.now(),
      durationSeconds: json['duration_seconds'] is int
          ? json['duration_seconds']
          : int.tryParse(json['duration_seconds'].toString()) ?? 10,
    );
  }
}
