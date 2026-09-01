import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../../core/theme/app_theme.dart';
import '../../models/user_model.dart';
import '../../services/setting_service.dart';
import '../widgets/app_drawer.dart';

class AdminPengaturanScreen extends StatefulWidget {
  final UserModel? user;

  const AdminPengaturanScreen({super.key, this.user});

  @override
  State<AdminPengaturanScreen> createState() => _AdminPengaturanScreenState();
}

class _AdminPengaturanScreenState extends State<AdminPengaturanScreen> {
  final _formKey = GlobalKey<FormState>();
  final _kasBackdateController = TextEditingController();
  
  bool _isLoading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _loadSettings();
  }

  Future<void> _loadSettings() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final result = await SettingService.getSettings();
    if (!mounted) return;

    if (result['success']) {
      final data = result['data'] as Map<String, dynamic>;
      setState(() {
        _kasBackdateController.text = data['kas_backdate_limit']?.toString() ?? '30';
        _isLoading = false;
      });
    } else {
      setState(() {
        _errorMessage = result['message'] ?? 'Gagal memuat pengaturan';
        _isLoading = false;
      });
    }
  }

  Future<void> _saveSettings() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() { _isLoading = true; });

    final result = await SettingService.updateSettings({
      'kas_backdate_limit': _kasBackdateController.text,
    });

    if (!mounted) return;

    setState(() { _isLoading = false; });

    if (result['success']) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Pengaturan berhasil disimpan.')),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(result['message'])),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Pengaturan Sistem'),
        backgroundColor: AppTheme.surface,
        elevation: 0,
      ),
      drawer: widget.user != null ? AppDrawer(user: widget.user!) : null,
      backgroundColor: AppTheme.background,
      body: _isLoading 
        ? const Center(child: CircularProgressIndicator(color: AppTheme.primary))
        : _errorMessage != null 
          ? Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Icon(Icons.error_outline, size: 64, color: AppTheme.error),
                  const SizedBox(height: 16),
                  Text(_errorMessage!, style: const TextStyle(color: AppTheme.error)),
                  const SizedBox(height: 16),
                  ElevatedButton(onPressed: _loadSettings, child: const Text('Coba Lagi')),
                ],
              ),
            )
          : Form(
              key: _formKey,
              child: ListView(
                padding: const EdgeInsets.all(20),
                children: [
                  const Text('Kas Warga', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppTheme.primary)),
                  const SizedBox(height: 8),
                  const Text('Atur berapa hari maksimal seorang bendahara dapat menginput data secara backdate (tanggal lampau).', style: TextStyle(color: AppTheme.textSecondary, fontSize: 14)),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _kasBackdateController,
                    decoration: const InputDecoration(
                      labelText: 'Batas Maksimal Backdate Kas (Hari)',
                      border: OutlineInputBorder(),
                      suffixText: 'Hari',
                    ),
                    keyboardType: TextInputType.number,
                    inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                    validator: (v) {
                      if (v == null || v.isEmpty) return 'Harus diisi';
                      if (int.tryParse(v) == null) return 'Hanya angka';
                      return null;
                    },
                  ),
                  const SizedBox(height: 32),
                  ElevatedButton(
                    onPressed: _saveSettings,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppTheme.primary,
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                    ),
                    child: const Text('Simpan Pengaturan', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.white)),
                  ),
                ],
              ),
            ),
    );
  }
}
