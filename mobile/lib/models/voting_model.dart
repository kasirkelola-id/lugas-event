import 'voting_option_model.dart';

class Voting {
  final int id;
  final int karangTarunaId;
  final String title;
  final String? description;
  final String status; // active, closed
  final int createdBy;
  final DateTime createdAt;
  final bool hasVoted;
  final int? totalVotes;
  final int? votedOptionId;
  final List<VotingOption>? options;

  Voting({
    required this.id,
    required this.karangTarunaId,
    required this.title,
    this.description,
    required this.status,
    required this.createdBy,
    required this.createdAt,
    this.hasVoted = false,
    this.totalVotes,
    this.votedOptionId,
    this.options,
  });

  factory Voting.fromJson(Map<String, dynamic> json) {
    return Voting(
      id: int.parse(json['id'].toString()),
      karangTarunaId: int.parse(json['karang_taruna_id'].toString()),
      title: json['title'],
      description: json['description'],
      status: json['status'] ?? 'active',
      createdBy: int.parse(json['created_by'].toString()),
      createdAt: DateTime.parse(json['created_at']),
      hasVoted: json['has_voted'] ?? false,
      totalVotes: json['total_votes'] != null ? int.parse(json['total_votes'].toString()) : null,
      votedOptionId: json['voted_option_id'] != null ? int.parse(json['voted_option_id'].toString()) : null,
      options: json['options'] != null
          ? (json['options'] as List).map((i) => VotingOption.fromJson(i)).toList()
          : null,
    );
  }
}
