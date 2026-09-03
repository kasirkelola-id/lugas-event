import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
import '../../models/voting_model.dart';
import '../../models/voting_option_model.dart';
import '../../models/user_model.dart';
import '../../services/auth_service.dart';
import '../../services/voting_service.dart';
import '../widgets/common/custom_button.dart';

class VotingDetailScreen extends StatefulWidget {
  final int votingId;

  const VotingDetailScreen({Key? key, required this.votingId}) : super(key: key);

  @override
  _VotingDetailScreenState createState() => _VotingDetailScreenState();
}

class _VotingDetailScreenState extends State<VotingDetailScreen> {
  bool _isLoading = true;
  bool _isSubmitting = false;
  Voting? _voting;
  UserModel? _currentUser;
  int? _selectedOptionId;

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

    await _fetchDetail();
  }

  Future<void> _fetchDetail() async {
    final result = await VotingService.getVotingDetail(widget.votingId);
    if (mounted) {
      setState(() {
        if (result['success']) {
          _voting = result['voting'];
        } else {
          ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(result['message'])));
        }
        _isLoading = false;
      });
    }
  }

  Future<void> _submitVote() async {
    if (_selectedOptionId == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Silakan pilih salah satu opsi')));
      return;
    }

    final confirm = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Konfirmasi Pilihan'),
        content: const Text('Pilihan Anda tidak dapat diubah setelah dikirim.\nLanjutkan?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Batal')),
          TextButton(onPressed: () => Navigator.pop(context, true), child: const Text('Kirim', style: TextStyle(color: AppTheme.primary))),
        ],
      ),
    );

    if (confirm != true) return;

    setState(() => _isSubmitting = true);
    final result = await VotingService.submitVote(widget.votingId, _selectedOptionId!);
    setState(() => _isSubmitting = false);

    if (result['success']) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(result['message'])));
      _fetchDetail(); // reload to show results
    } else {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(result['message'], style: const TextStyle(color: Colors.white)), backgroundColor: Colors.red));
    }
  }

  Future<void> _closeVoting() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Tutup Voting'),
        content: const Text('Apakah Anda yakin ingin menutup voting ini? Anggota tidak akan bisa memilih lagi.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Batal')),
          TextButton(onPressed: () => Navigator.pop(context, true), child: const Text('Tutup', style: TextStyle(color: Colors.red))),
        ],
      ),
    );

    if (confirm != true) return;

    setState(() => _isSubmitting = true);
    final result = await VotingService.changeStatus(widget.votingId, 'closed');
    setState(() => _isSubmitting = false);

    if (result['success']) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(result['message'])));
      _fetchDetail();
    } else {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(result['message'])));
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return Scaffold(
        appBar: AppBar(title: const Text('Detail Voting'), backgroundColor: AppTheme.primary),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    if (_voting == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Detail Voting'), backgroundColor: AppTheme.primary),
        body: const Center(child: Text('Voting tidak ditemukan')),
      );
    }

    final bool isKetuaOrAdmin = _currentUser?.roleLevel == 'ketua' || _currentUser?.roleLevel == 'superadmin';
    final bool showResults = _voting!.hasVoted || _voting!.status == 'closed';
    final bool canVote = !_voting!.hasVoted && _voting!.status == 'active';

    return Scaffold(
      appBar: AppBar(
        title: const Text('Detail Voting', style: TextStyle(color: Colors.white)),
        backgroundColor: AppTheme.primary,
        iconTheme: const IconThemeData(color: Colors.white),
        actions: [
          if (isKetuaOrAdmin && _voting!.status == 'active')
            IconButton(
              icon: const Icon(Icons.block),
              tooltip: 'Tutup Voting',
              onPressed: _closeVoting,
            ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              decoration: BoxDecoration(
                color: _voting!.status == 'active' ? Colors.green.shade100 : Colors.red.shade100,
                borderRadius: BorderRadius.circular(20),
              ),
              child: Text(
                _voting!.status == 'active' ? 'AKTIF' : 'DITUTUP',
                style: TextStyle(
                  color: _voting!.status == 'active' ? Colors.green.shade800 : Colors.red.shade800,
                  fontWeight: FontWeight.bold,
                  fontSize: 12,
                ),
              ),
            ),
            const SizedBox(height: 12),
            Text(
              _voting!.title,
              style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            if (_voting!.description != null) ...[
              Text(
                _voting!.description!,
                style: const TextStyle(fontSize: 15, color: Colors.black87),
              ),
              const SizedBox(height: 16),
            ],
            const Divider(),
            const SizedBox(height: 8),
            
            Text(
              showResults ? 'Hasil Pemilihan:' : 'Silakan Pilih:',
              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 16),
            
            if (_voting!.options != null)
              ..._voting!.options!.map((option) {
                if (showResults) {
                  // Tampilkan Bar Hasil
                  return _buildResultBar(option);
                } else {
                  // Tampilkan Radio Button
                  return RadioListTile<int>(
                    title: Text(option.optionName),
                    value: option.id,
                    groupValue: _selectedOptionId,
                    activeColor: AppTheme.primary,
                    contentPadding: EdgeInsets.zero,
                    onChanged: (val) {
                      setState(() {
                        _selectedOptionId = val;
                      });
                    },
                  );
                }
              }).toList(),
              
            const SizedBox(height: 24),
            
            if (showResults)
              Center(
                child: Text(
                  'Total Suara: ${_voting!.totalVotes}',
                  style: TextStyle(color: Colors.grey.shade600, fontWeight: FontWeight.bold),
                ),
              ),

            if (canVote)
              SizedBox(
                width: double.infinity,
                child: CustomButton(
                  text: 'Kirim Suara',
                  onPressed: _isSubmitting ? null : _submitVote,
                  isLoading: _isSubmitting,
                ),
              ),
              
            if (!canVote && _voting!.status == 'active')
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.blue.shade50,
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: Colors.blue.shade200),
                ),
                child: const Row(
                  children: [
                    Icon(Icons.info_outline, color: Colors.blue),
                    SizedBox(width: 8),
                    Expanded(child: Text('Anda sudah memberikan suara pada voting ini. Menunggu voting ditutup oleh pengelola.', style: TextStyle(color: Colors.blue))),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildResultBar(VotingOption option) {
    final bool isMyChoice = _voting!.votedOptionId == option.id;
    final double pct = option.percentage ?? 0.0;
    
    bool isWinner = false;
    if (_voting!.status == 'closed' && _voting!.options != null) {
      int maxVotes = 0;
      for (var opt in _voting!.options!) {
        if ((opt.voteCount ?? 0) > maxVotes) {
          maxVotes = opt.voteCount ?? 0;
        }
      }
      if (maxVotes > 0 && (option.voteCount ?? 0) == maxVotes) {
        isWinner = true;
      }
    }
    
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Expanded(
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        option.optionName,
                        style: TextStyle(
                          fontSize: 16, 
                          fontWeight: isMyChoice || isWinner ? FontWeight.bold : FontWeight.normal,
                          color: isWinner ? Colors.green.shade700 : (isMyChoice ? AppTheme.primary : Colors.black87),
                        ),
                      ),
                    ),
                    if (isMyChoice)
                      const Padding(
                        padding: EdgeInsets.only(left: 4),
                        child: Icon(Icons.check_circle, size: 16, color: AppTheme.primary),
                      ),
                    if (isWinner)
                      Container(
                        margin: const EdgeInsets.only(left: 8),
                        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                        decoration: BoxDecoration(
                          color: Colors.green.shade100,
                          borderRadius: BorderRadius.circular(4),
                          border: Border.all(color: Colors.green.shade300),
                        ),
                        child: const Text('PEMENANG', style: TextStyle(fontSize: 10, color: Colors.green, fontWeight: FontWeight.bold)),
                      )
                  ],
                ),
              ),
              Text(
                '${pct.toStringAsFixed(1)}% (${option.voteCount})',
                style: const TextStyle(fontWeight: FontWeight.bold),
              ),
            ],
          ),
          const SizedBox(height: 8),
          LinearProgressIndicator(
            value: pct / 100,
            backgroundColor: Colors.grey.shade200,
            color: isWinner ? Colors.green.shade500 : (isMyChoice ? AppTheme.primary : Colors.grey.shade400),
            minHeight: 10,
            borderRadius: BorderRadius.circular(10),
          ),
        ],
      ),
    );
  }
}
