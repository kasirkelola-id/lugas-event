class VotingOption {
  final int id;
  final int votingId;
  final String optionName;
  final int? voteCount;
  final double? percentage;

  VotingOption({
    required this.id,
    required this.votingId,
    required this.optionName,
    this.voteCount,
    this.percentage,
  });

  factory VotingOption.fromJson(Map<String, dynamic> json) {
    return VotingOption(
      id: int.parse(json['id'].toString()),
      votingId: int.parse(json['voting_id'].toString()),
      optionName: json['option_name'],
      voteCount: json['vote_count'] != null ? int.parse(json['vote_count'].toString()) : null,
      percentage: json['percentage'] != null ? double.parse(json['percentage'].toString()) : null,
    );
  }
}
