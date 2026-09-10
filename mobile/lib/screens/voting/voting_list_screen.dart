import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
import '../../models/voting_model.dart';
import '../../models/user_model.dart';
import '../../services/auth_service.dart';
import '../../services/voting_service.dart';
import 'voting_detail_screen.dart';
import 'create_voting_screen.dart';
import '../widgets/animations/fade_in_slide.dart';
import 'package:intl/intl.dart';
import 'package:mobile/screens/widgets/common/custom_loading_indicator.dart';
import '../widgets/app_drawer.dart';

class VotingListScreen extends StatefulWidget {
  final bool fromDrawer;
  const VotingListScreen({Key? key, this.fromDrawer = false}) : super(key: key);

  @override
  _VotingListScreenState createState() => _VotingListScreenState();
}

class _VotingListScreenState extends State<VotingListScreen> {
  bool _isLoading = true;
  List<Voting> _votings = [];
  UserModel? _currentUser;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() => _isLoading = true);

    final userResult = await AuthService.getMe();
    if (userResult['success']) {
      _currentUser = userResult['user'];
    }

    await _fetchVotings();
  }

  Future<void> _fetchVotings() async {
    final result = await VotingService.getVotings();
    if (mounted) {
      setState(() {
        if (result['success']) {
          _votings = result['votings'];
        }
        _isLoading = false;
      });
    }
  }

  List<Voting> _getVotingsByStatus(String status) {
    if (status == 'active') {
      return _votings.where((v) => v.status == 'active').toList();
    } else if (status == 'scheduled') {
      return _votings.where((v) => v.status == 'scheduled').toList();
    } else {
      return _votings.where((v) => v.status == 'ended').toList();
    }
  }

  @override
  Widget build(BuildContext context) {
    final bool isKetuaOrAdmin =
        _currentUser?.roleLevel == 'ketua' ||
        _currentUser?.roleLevel == 'superadmin';

    return DefaultTabController(
      length: 3,
      child: Scaffold(
        drawer: (widget.fromDrawer && _currentUser != null) ? AppDrawer(user: _currentUser!) : null,
        appBar: AppBar(
          title: const Text(
            'Voting & Pemilu',
            style: TextStyle(color: Colors.white),
          ),
          backgroundColor: AppTheme.primary,
          iconTheme: const IconThemeData(color: Colors.white),
          bottom: const TabBar(
            indicatorColor: Colors.white,
            indicatorWeight: 3,
            labelColor: Colors.white,
            unselectedLabelColor: Colors.white70,
            labelStyle: TextStyle(
              fontWeight: FontWeight.bold,
              fontSize: 14,
            ),
            tabs: [
              Tab(text: 'Berlangsung'),
              Tab(text: 'Belum Dimulai'),
              Tab(text: 'Selesai'),
            ],
          ),
        ),
        body: _isLoading
            ? const Center(child: CustomLoadingIndicator())
            : TabBarView(
                children: [
                  _buildVotingList(_getVotingsByStatus('active')),
                  _buildVotingList(_getVotingsByStatus('scheduled')),
                  _buildVotingList(_getVotingsByStatus('ended')),
                ],
              ),
        floatingActionButton: isKetuaOrAdmin
            ? FloatingActionButton(
                onPressed: () async {
                  final result = await Navigator.push(
                    context,
                    MaterialPageRoute(
                        builder: (_) => const CreateVotingScreen()),
                  );
                  if (result == true) {
                    _fetchVotings();
                  }
                },
                backgroundColor: AppTheme.primary,
                child: const Icon(Icons.add, color: Colors.white),
              )
            : null,
      ),
    );
  }

  Widget _buildVotingList(List<Voting> list) {
    if (list.isEmpty) {
      return RefreshIndicator(
        onRefresh: _fetchVotings,
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          child: SizedBox(
            height: MediaQuery.of(context).size.height * 0.6,
            child: const Center(
              child: Text(
                "Tidak ada data voting.",
                style: TextStyle(color: AppTheme.textSecondary),
              ),
            ),
          ),
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _fetchVotings,
      child: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: list.length,
        itemBuilder: (context, index) {
          final voting = list[index];
          final dateFormat = DateFormat('dd MMM yyyy');

          Color statusColor;
          String statusText;

          if (voting.status == 'scheduled') {
            statusColor = Colors.orange;
            statusText = 'Belum Mulai';
          } else if (voting.status == 'active') {
            statusColor = Colors.green;
            statusText = 'Aktif';
          } else {
            statusColor = Colors.red;
            statusText = 'Selesai';
          }

          int optionsCount = voting.options?.length ?? 0;

          return FadeInSlide(
            delay: 0.1 * index,
            child: Card(
              margin: const EdgeInsets.only(bottom: 16),
              elevation: 2,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(16),
              ),
              shadowColor: Colors.black12,
              child: InkWell(
                borderRadius: BorderRadius.circular(16),
                onTap: () async {
                  await Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => VotingDetailScreen(votingId: voting.id),
                    ),
                  );
                  _fetchVotings();
                },
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Expanded(
                            child: Text(
                              voting.title,
                              style: const TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.bold,
                                color: AppTheme.textPrimary,
                              ),
                            ),
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 10,
                              vertical: 4,
                            ),
                            decoration: BoxDecoration(
                              color: statusColor.withOpacity(0.1),
                              borderRadius: BorderRadius.circular(20),
                            ),
                            child: Text(
                              statusText,
                              style: TextStyle(
                                color: statusColor,
                                fontSize: 12,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      if (voting.description != null &&
                          voting.description!.isNotEmpty)
                        Text(
                          voting.description!,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(color: Colors.grey.shade600),
                        ),
                      const SizedBox(height: 16),
                      const Divider(),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Row(
                            children: [
                              Icon(
                                Icons.format_list_bulleted,
                                size: 16,
                                color: Colors.grey.shade600,
                              ),
                              const SizedBox(width: 4),
                              Text(
                                '$optionsCount Opsi',
                                style: TextStyle(
                                  color: Colors.grey.shade600,
                                  fontSize: 13,
                                ),
                              ),
                            ],
                          ),
                          Text(
                            dateFormat.format(voting.waktuMulai ?? voting.createdAt),
                            style: TextStyle(
                              color: Colors.grey.shade500,
                              fontSize: 12,
                            ),
                          ),
                        ],
                      ),
                      if (voting.hasVoted)
                        Padding(
                          padding: const EdgeInsets.only(top: 8),
                          child: Row(
                            children: [
                              const Icon(
                                Icons.check_circle,
                                size: 16,
                                color: Colors.blue,
                              ),
                              const SizedBox(width: 4),
                              const Text(
                                'Anda sudah memilih',
                                style: TextStyle(
                                  color: Colors.blue,
                                  fontSize: 12,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ],
                          ),
                        ),
                    ],
                  ),
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}
