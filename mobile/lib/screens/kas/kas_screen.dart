import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../core/theme/app_theme.dart';
import '../../models/user_model.dart';
import '../../models/kas_model.dart';
import '../../services/kas_service.dart';
import '../widgets/app_drawer.dart';
import '../widgets/common/custom_button.dart';
import '../widgets/common/empty_state.dart';
import '../widgets/common/feedback_dialogs.dart';
import 'add_kas_screen.dart';

class KasScreen extends StatefulWidget {
  final UserModel? user;

  const KasScreen({super.key, this.user});

  @override
  State<KasScreen> createState() => _KasScreenState();
}

class _KasScreenState extends State<KasScreen> {
  int _saldo = 0;
  List<KasModel> _transaksi = [];
  bool _isLoading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final result = await KasService.getKasData();
    if (!mounted) return;

    if (result['success']) {
      setState(() {
        _saldo = result['saldo'] ?? 0;
        _transaksi = result['transaksi'] as List<KasModel>;
        _isLoading = false;
      });
    } else {
      setState(() {
        _errorMessage = result['message'] ?? 'Data gagal dimuat';
        _isLoading = false;
      });
    }
  }

  void _deleteTransaksi(int id) async {
    final confirm = await FeedbackDialogs.showConfirmation(
      context: context,
      title: 'Hapus Transaksi?',
      content: 'Apakah Anda yakin ingin menghapus data kas ini? Saldo akan dihitung ulang secara otomatis.',
      isDestructive: true,
    );

    if (confirm != true) return;

    setState(() { _isLoading = true; });
    final result = await KasService.deleteTransaksi(id);
    if (!mounted) return;

    if (result['success']) {
      FeedbackDialogs.showSnackbar(context, 'Transaksi dihapus.');
      _loadData();
    } else {
      setState(() { _isLoading = false; });
      FeedbackDialogs.showSnackbar(context, result['message'], isError: true);
    }
  }

  String _formatCurrency(int amount) {
    return NumberFormat.currency(locale: 'id_ID', symbol: 'Rp ', decimalDigits: 0).format(amount);
  }

  @override
  Widget build(BuildContext context) {
    final bool canManage = widget.user != null && 
        ['admin', 'ketua', 'bendahara'].contains(widget.user!.roleLevel);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Keuangan Kas'),
        backgroundColor: AppTheme.surface,
        elevation: 0,
        scrolledUnderElevation: 0,
      ),
      drawer: widget.user != null ? AppDrawer(user: widget.user!) : null,
      backgroundColor: AppTheme.background,
      body: RefreshIndicator(
        onRefresh: _loadData,
        color: AppTheme.primary,
        child: _buildBody(canManage),
      ),
      floatingActionButton: canManage ? FloatingActionButton.extended(
        onPressed: () async {
          final result = await Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const AddKasScreen()),
          );
          if (result == true) {
            _loadData();
          }
        },
        icon: const Icon(Icons.add, color: Colors.white),
        label: const Text('Catat Transaksi', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600)),
        backgroundColor: AppTheme.primary,
        elevation: 4,
      ) : null,
    );
  }

  Widget _buildBody(bool canManage) {
    if (_isLoading && _transaksi.isEmpty) {
      return const Center(child: CircularProgressIndicator(color: AppTheme.primary));
    }

    if (_errorMessage != null && _transaksi.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(color: AppTheme.error.withValues(alpha: 0.1), shape: BoxShape.circle),
              child: const Icon(Icons.error_outline, size: 64, color: AppTheme.error),
            ),
            const SizedBox(height: 24),
            Text(_errorMessage!, style: const TextStyle(color: AppTheme.textPrimary, fontWeight: FontWeight.bold, fontSize: 16)),
            const SizedBox(height: 24),
            CustomButton(
              text: 'Coba Lagi',
              onPressed: _loadData,
              isFullWidth: false,
              icon: Icons.refresh,
            ),
          ],
        ),
      );
    }

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        _buildSaldoCard(),
        const SizedBox(height: 24),
        const Text(
          'Riwayat Transaksi',
          style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
        ),
        const SizedBox(height: 12),
        if (_transaksi.isEmpty)
          const EmptyStateWidget(
            icon: Icons.account_balance_wallet_outlined,
            title: 'Belum Ada Transaksi',
            subtitle: 'Daftar transaksi kas Anda akan muncul di sini.',
          )
        else
          ..._transaksi.map((t) => _buildTransaksiCard(t, canManage)).toList(),
        const SizedBox(height: 80), // space for FAB
      ],
    );
  }

  Widget _buildSaldoCard() {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [AppTheme.primary, AppTheme.primary.withValues(alpha: 0.8)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: AppTheme.primary.withValues(alpha: 0.3),
            blurRadius: 10,
            offset: const Offset(0, 5),
          ),
        ],
      ),
      child: Column(
        children: [
          const Text('Total Saldo Kas', style: TextStyle(color: Colors.white70, fontSize: 16)),
          const SizedBox(height: 8),
          Text(
            _formatCurrency(_saldo),
            style: const TextStyle(color: Colors.white, fontSize: 32, fontWeight: FontWeight.bold),
          ),
        ],
      ),
    );
  }

  Widget _buildTransaksiCard(KasModel t, bool canManage) {
    final bool isPemasukan = t.isPemasukan;
    final color = isPemasukan ? AppTheme.success : AppTheme.error;
    final icon = isPemasukan ? Icons.arrow_downward : Icons.arrow_upward;
    final sign = isPemasukan ? '+' : '-';

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      elevation: 1,
      child: ListTile(
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        leading: CircleAvatar(
          backgroundColor: color.withValues(alpha: 0.1),
          child: Icon(icon, color: color),
        ),
        title: Text(t.keterangan, style: const TextStyle(fontWeight: FontWeight.bold)),
        subtitle: Padding(
          padding: const EdgeInsets.only(top: 4.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(t.tanggal, style: const TextStyle(fontSize: 12)),
              if (t.pembuat != null)
                Text('Oleh: ${t.pembuat}', style: const TextStyle(fontSize: 12, fontStyle: FontStyle.italic)),
            ],
          ),
        ),
        trailing: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              '$sign${_formatCurrency(t.nominal)}',
              style: TextStyle(color: color, fontWeight: FontWeight.bold, fontSize: 14),
            ),
            if (canManage) ...[
              const SizedBox(width: 8),
              IconButton(
                icon: const Icon(Icons.delete, color: AppTheme.error, size: 20),
                onPressed: () => _deleteTransaksi(t.id),
                padding: EdgeInsets.zero,
                constraints: const BoxConstraints(),
              ),
            ]
          ],
        ),
      ),
    );
  }
}
