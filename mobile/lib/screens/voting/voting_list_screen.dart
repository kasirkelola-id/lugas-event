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

class VotingListScreen extends StatefulWidget {
  const VotingListScreen({Key? key}) : super(key: key);

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

  @override
  Widget build(BuildContext context) {
    final bool isKetuaOrAdmin = _currentUser?.roleLevel == 'ketua' || _currentUser?.roleLevel == 'superadmin';

    return Scaffold(
      appBar: AppBar(
        title: const Text('Voting & Pemilu', style: TextStyle(color: Colors.white)),
        backgroundColor: AppTheme.primary,
        iconTheme: const IconThemeData(color: Colors.white),
      ),
      body: _isLoading
          ? const Center(child: CustomLoadingIndicator())
          : _votings.isEmpty
              ? const Center(child: Text("Belum ada voting saat ini."))
              : RefreshIndicator(
                  onRefresh: _fetchVotings,
                  child: ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: _votings.length,
                    itemBuilder: (context, index) {
                      final voting = _votings[index];
                      final isActive = voting.status == 'active';
                      final dateFormat = DateFormat('dd MMM yyyy, HH:mm');
                      
                      return FadeInSlide(
                        delay: 0.1 * index,
                        child: Card(
                          margin: const EdgeInsets.only(bottom: 16),
                          elevation: 2,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                          shadowColor: Colors.black12,
                          child: InkWell(
                            borderRadius: BorderRadius.circular(16),
                            onTap: () async {
                              await Navigator.push(
                                context,
                                MaterialPageRoute(builder: (_) => VotingDetailScreen(votingId: voting.id)),
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
                                          style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                                        ),
                                      ),
                                      Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                        decoration: BoxDecoration(
                                          color: isActive ? Colors.green.shade100 : Colors.red.shade100,
                                          borderRadius: BorderRadius.circular(20),
                                        ),
                                        child: Text(
                                          isActive ? 'Aktif' : 'Ditutup',
                                          style: TextStyle(
                                            color: isActive ? Colors.green.shade800 : Colors.red.shade800,
                                            fontSize: 12,
                                            fontWeight: FontWeight.bold,
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 8),
                                  if (voting.description != null && voting.description!.isNotEmpty)
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
                                          Icon(Icons.how_to_vote, size: 16, color: Colors.grey.shade600),
                                          const SizedBox(width: 4),
                                          Text(
                                            '${voting.totalVotes ?? 0} Suara',
                                            style: TextStyle(color: Colors.grey.shade600, fontSize: 13),
                                          ),
                                        ],
                                      ),
                                      Text(
                                        dateFormat.format(voting.createdAt),
                                        style: TextStyle(color: Colors.grey.shade500, fontSize: 12),
                                      ),
                                    ],
                                  ),
                                  if (voting.hasVoted)
                                    Padding(
                                      padding: const EdgeInsets.only(top: 8),
                                      child: Row(
                                        children: [
                                          const Icon(Icons.check_circle, size: 16, color: Colors.blue),
                                          const SizedBox(width: 4),
                                          const Text(
                                            'Anda sudah memilih',
                                            style: TextStyle(color: Colors.blue, fontSize: 12, fontWeight: FontWeight.bold),
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
                ),
      floatingActionButton: isKetuaOrAdmin
          ? FloatingActionButton(
              onPressed: () async {
                final result = await Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const CreateVotingScreen()),
                );
                if (result == true) {
                  _fetchVotings();
                }
              },
              backgroundColor: AppTheme.primary,
              child: const Icon(Icons.add, color: Colors.white),
            )
          : null,
    );
  }
}
