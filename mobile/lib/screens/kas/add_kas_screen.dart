import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';
import '../../core/theme/app_theme.dart';
import '../../services/kas_service.dart';
import '../../services/setting_service.dart';
import '../../services/auth_service.dart';
import '../auth/login_screen.dart';
import '../widgets/common/custom_button.dart';
import '../widgets/common/custom_text_field.dart';
import '../widgets/common/feedback_dialogs.dart';
import 'package:mobile/screens/widgets/common/custom_loading_indicator.dart';

class AddKasScreen extends StatefulWidget {
  const AddKasScreen({super.key});

  @override
  State<AddKasScreen> createState() => _AddKasScreenState();
}

class _AddKasScreenState extends State<AddKasScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nominalController = TextEditingController();
  final _keteranganController = TextEditingController();
  final _tanggalController = TextEditingController();
  
  String _jenis = 'pemasukan';
  bool _isLoading = false;
  int _limitDays = 30; // default
  bool _isInitLoading = true;

  @override
  void initState() {
    super.initState();
    _tanggalController.text = DateFormat('yyyy-MM-dd').format(DateTime.now());
    _loadSettings();
  }

  Future<void> _loadSettings() async {
    final result = await SettingService.getSettings();
    if (!mounted) return;
    
    if (result['success']) {
      final data = result['data'] as Map<String, dynamic>;
      final limit = int.tryParse(data['kas_backdate_limit']?.toString() ?? '30') ?? 30;
      setState(() {
        _limitDays = limit;
        _isInitLoading = false;
      });
    } else {
      setState(() { _isInitLoading = false; });
    }
  }

  void _pilihTanggal() async {
    final DateTime today = DateTime.now();
    final DateTime firstDate = today.subtract(Duration(days: _limitDays));
    
    final picked = await showDatePicker(
      context: context,
      initialDate: today,
      firstDate: firstDate,
      lastDate: today,
      helpText: 'Pilih Tanggal Transaksi',
      fieldLabelText: 'Tanggal',
      errorFormatText: 'Format tanggal salah',
      errorInvalidText: 'Tanggal melebihi batas backdate ($_limitDays hari)',
    );
    if (picked != null) {
      setState(() {
        _tanggalController.text = DateFormat('yyyy-MM-dd').format(picked);
      });
    }
  }

  void _submit() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _isLoading = true;
    });

    // Remove any non-digit character (e.g., formatting)
    final nominalRaw = _nominalController.text.replaceAll(RegExp(r'[^0-9]'), '');
    final nominal = int.tryParse(nominalRaw) ?? 0;

    final result = await KasService.createTransaksi(
      jenis: _jenis,
      nominal: nominal,
      keterangan: _keteranganController.text,
      tanggal: _tanggalController.text,
    );

    setState(() {
      _isLoading = false;
    });

    if (!mounted) return;

    if (result['success']) {
      FeedbackDialogs.showSnackbar(context, 'Transaksi berhasil dicatat.');
      Navigator.pop(context, true); // return true to refresh
    } else {
      if (result['statusCode'] == 401) {
        await AuthService.logout();
        if (!mounted) return;
        Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => const LoginScreen()));
      } else {
        FeedbackDialogs.showSnackbar(context, result['message'], isError: true);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Catat Transaksi'),
        backgroundColor: AppTheme.surface,
        elevation: 0,
      ),
      backgroundColor: AppTheme.background,
      body: _isInitLoading 
        ? const Center(child: CustomLoadingIndicator(color: AppTheme.primary))
        : Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            const Text('Jenis Transaksi', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: RadioListTile<String>(
                    title: const Text('Pemasukan', style: TextStyle(color: AppTheme.success)),
                    value: 'pemasukan',
                    groupValue: _jenis,
                    activeColor: AppTheme.success,
                    contentPadding: EdgeInsets.zero,
                    onChanged: (val) {
                      setState(() { _jenis = val!; });
                    },
                  ),
                ),
                Expanded(
                  child: RadioListTile<String>(
                    title: const Text('Pengeluaran', style: TextStyle(color: AppTheme.error)),
                    value: 'pengeluaran',
                    groupValue: _jenis,
                    activeColor: AppTheme.error,
                    contentPadding: EdgeInsets.zero,
                    onChanged: (val) {
                      setState(() { _jenis = val!; });
                    },
                  ),
                ),
              ],
            ),
            const SizedBox(height: 24),
            CustomTextField(
              controller: _nominalController,
              label: 'Nominal (Rp)',
              prefixText: 'Rp ',
              keyboardType: TextInputType.number,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              validator: (v) => v!.isEmpty ? 'Nominal harus diisi' : null,
              readOnly: _isLoading,
            ),
            CustomTextField(
              controller: _keteranganController,
              label: 'Keterangan',
              hint: 'Cth: Iuran kas bulan Juli Bpk Andi',
              maxLines: 2,
              validator: (v) => v!.isEmpty ? 'Keterangan harus diisi' : null,
              readOnly: _isLoading,
            ),
            CustomTextField(
              controller: _tanggalController,
              label: 'Tanggal Transaksi',
              suffixIcon: const Icon(Icons.calendar_today),
              readOnly: true,
              onTap: _isLoading ? null : _pilihTanggal,
              validator: (v) => v!.isEmpty ? 'Tanggal harus diisi' : null,
            ),
            Padding(
              padding: const EdgeInsets.only(left: 4.0, bottom: 24.0),
              child: Text('Batas maksimal mundur (backdate): $_limitDays hari', style: const TextStyle(fontSize: 12, color: Colors.grey)),
            ),
            CustomButton(
              text: 'Simpan Transaksi',
              onPressed: _submit,
              isLoading: _isLoading,
            ),
          ],
        ),
      ),
    );
  }
}
