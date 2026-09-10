import 'package:flutter/material.dart';
import '../../../core/theme/app_theme.dart';
import '../../shared/user_pengumuman_screen.dart';
import '../../voting/voting_list_screen.dart';
import '../../undian/wheel_list_screen.dart';

class CommunityActivitySection extends StatelessWidget {
  final Map<String, dynamic>? communityActivity;

  const CommunityActivitySection({
    Key? key,
    required this.communityActivity,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    if (communityActivity == null) return const SizedBox.shrink();

    final announcementData = communityActivity!['announcement'];
    final votingData = communityActivity!['voting'];
    final wheelData = communityActivity!['wheel'];

    final hasAnnouncement = announcementData != null && announcementData['item'] != null;
    final hasVoting = votingData != null && votingData['item'] != null;
    final hasWheel = wheelData != null && wheelData['item'] != null;

    if (!hasAnnouncement && !hasVoting && !hasWheel) {
      return const SizedBox.shrink();
    }

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16.0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'Aktivitas Komunitas',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.bold,
              color: AppTheme.textPrimary,
            ),
          ),
          const SizedBox(height: 12),
          if (hasAnnouncement) _buildAnnouncementCard(context, announcementData),
          if (hasVoting) _buildVotingCard(context, votingData),
          if (hasWheel) _buildWheelCard(context, wheelData),
        ],
      ),
    );
  }

  Widget _buildAnnouncementCard(BuildContext context, Map<String, dynamic> data) {
    final item = data['item'];
    final additionalCount = data['additional_count'] ?? 0;

    return _buildCard(
      context: context,
      icon: Icons.campaign_rounded,
      iconColor: AppTheme.warning,
      title: 'Pengumuman Terbaru',
      subtitle: item['title'] ?? '',
      description: item['preview'] != null ? '${item['preview']}...' : '',
      additionalCount: additionalCount,
      onTap: () {
        Navigator.push(context, MaterialPageRoute(builder: (_) => const UserPengumumanScreen()));
      },
    );
  }

  Widget _buildVotingCard(BuildContext context, Map<String, dynamic> data) {
    final item = data['item'];
    final additionalCount = data['additional_count'] ?? 0;

    return _buildCard(
      context: context,
      icon: Icons.how_to_vote_rounded,
      iconColor: AppTheme.primary,
      title: 'Voting Aktif',
      subtitle: item['title'] ?? '',
      additionalCount: additionalCount,
      onTap: () {
        Navigator.push(context, MaterialPageRoute(builder: (_) => const VotingListScreen()));
      },
    );
  }

  Widget _buildWheelCard(BuildContext context, Map<String, dynamic> data) {
    final item = data['item'];
    final additionalCount = data['additional_count'] ?? 0;
    
    // Status can be 'active' or 'result_available' based on our backend logic.
    final status = item['status'];
    final subtitle = item['title'] ?? 'Undian';
    final desc = status == 'result_available' ? 'Pemenang baru saja dipilih' : 'Undian sedang berlangsung';

    return _buildCard(
      context: context,
      icon: Icons.casino_rounded,
      iconColor: AppTheme.secondary,
      title: 'Undian Aktif',
      subtitle: subtitle,
      description: desc,
      additionalCount: additionalCount,
      onTap: () {
        Navigator.push(context, MaterialPageRoute(builder: (_) => const WheelListScreen()));
      },
    );
  }

  Widget _buildCard({
    required BuildContext context,
    required IconData icon,
    required Color iconColor,
    required String title,
    required String subtitle,
    String? description,
    required int additionalCount,
    required VoidCallback onTap,
  }) {
    return Card(
      elevation: 0,
      margin: const EdgeInsets.only(bottom: 12),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(color: Colors.grey.shade300),
      ),
      color: AppTheme.surface,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(16.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: iconColor.withOpacity(0.1),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Icon(icon, color: iconColor, size: 24),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          title,
                          style: const TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w500,
                            color: AppTheme.textSecondary,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          subtitle,
                          style: const TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.bold,
                            color: AppTheme.textPrimary,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                        if (description != null && description.isNotEmpty) ...[
                          const SizedBox(height: 4),
                          Text(
                            description,
                            style: const TextStyle(
                              fontSize: 13,
                              color: AppTheme.textSecondary,
                            ),
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ],
                      ],
                    ),
                  ),
                  const Icon(
                    Icons.chevron_right_rounded,
                    color: AppTheme.textSecondary,
                  ),
                ],
              ),
              if (additionalCount > 0) ...[
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 12),
                  decoration: BoxDecoration(
                    color: AppTheme.background,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(
                        '+$additionalCount $title lainnya',
                        style: const TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                          color: AppTheme.textSecondary,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
