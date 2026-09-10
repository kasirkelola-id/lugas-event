import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
import '../../services/wheel_service.dart';
import '../widgets/common/app_dialog.dart';
import '../widgets/common/custom_button.dart';
import '../widgets/common/custom_text_field.dart';

class CreateWheelScreen extends StatefulWidget {
  const CreateWheelScreen({super.key});

  @override
  State<CreateWheelScreen> createState() => _CreateWheelScreenState();
}

class _CreateWheelScreenState extends State<CreateWheelScreen> {
  final _titleController = TextEditingController();
  final _itemsController =
      TextEditingController(); // For custom items, comma separated

  String _sourceType = 'members'; // 'members' or 'custom'
  int _spinDuration = 10;
  bool _removeWinner = false;
  int _dashboardDurationMinutes = 60; // 1 jam default
  final _customDurationController = TextEditingController();

  void _submit() async {
    final title = _titleController.text.trim();
    if (title.isEmpty) {
      AppDialog.showResult(
        context: context,
        title: 'Validasi Gagal',
        content: 'Judul undian tidak boleh kosong',
        type: DialogType.error,
      );
      return;
    }

    List<dynamic> items = [];
    if (_sourceType == 'custom') {
      final text = _itemsController.text;
      items = text
          .split(',')
          .map((e) => e.trim())
          .where((e) => e.isNotEmpty)
          .toList();
      if (items.length < 2) {
        AppDialog.showResult(
          context: context,
          title: 'Validasi Gagal',
          content: 'Kandidat manual minimal 2 (pisahkan dengan koma)',
          type: DialogType.error,
        );
        return;
      }
    }

    AppDialog.showLoading(context, message: 'Menyimpan...');

    int finalMinutes = _dashboardDurationMinutes;
    if (finalMinutes == -1) {
      final customHours = int.tryParse(_customDurationController.text) ?? 1;
      finalMinutes = customHours * 60;
    }
    final untilDate = DateTime.now().add(Duration(minutes: finalMinutes));
    final dashboardUntil = "${untilDate.year.toString().padLeft(4, '0')}-${untilDate.month.toString().padLeft(2, '0')}-${untilDate.day.toString().padLeft(2, '0')} ${untilDate.hour.toString().padLeft(2, '0')}:${untilDate.minute.toString().padLeft(2, '0')}:${untilDate.second.toString().padLeft(2, '0')}";

    final result = await WheelService.createSession(
      title: title,
      sourceType: _sourceType,
      spinDurationSeconds: _spinDuration,
      removeWinnerAfterSpin: _removeWinner,
      items: items,
      dashboardUntil: dashboardUntil,
    );

    if (!mounted) return;
    Navigator.pop(context); // close loading

    if (result['success']) {
      Navigator.pop(context, true);
    } else {
      AppDialog.showResult(
        context: context,
        title: 'Gagal',
        content: result['message'],
        type: DialogType.error,
      );
    }
  }

  @override
  void dispose() {
    _titleController.dispose();
    _itemsController.dispose();
    _customDurationController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.background,
      appBar: AppBar(
        title: const Text('Buat Undian Baru'),
        backgroundColor: AppTheme.surface,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            CustomTextField(
              controller: _titleController,
              label: 'Judul Undian',
              hint: 'Contoh: Undian Doorprize Acara 17an',
            ),
            const SizedBox(height: 20),
            const Text(
              'Sumber Kandidat',
              style: TextStyle(
                fontWeight: FontWeight.bold,
                color: AppTheme.textSecondary,
              ),
            ),
            const SizedBox(height: 8),
            Container(
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: AppTheme.radiusSmall,
                border: Border.all(color: Colors.black12),
              ),
              child: Column(
                children: [
                  RadioListTile<String>(
                    title: const Text('Anggota Aktif Organisasi'),
                    value: 'members',
                    groupValue: _sourceType,
                    onChanged: (val) {
                      setState(() {
                        _sourceType = val!;
                      });
                    },
                    activeColor: AppTheme.primary,
                  ),
                  const Divider(height: 1),
                  RadioListTile<String>(
                    title: const Text('Input Manual'),
                    value: 'custom',
                    groupValue: _sourceType,
                    onChanged: (val) {
                      setState(() {
                        _sourceType = val!;
                      });
                    },
                    activeColor: AppTheme.primary,
                  ),
                ],
              ),
            ),
            if (_sourceType == 'custom') ...[
              const SizedBox(height: 16),
              CustomTextField(
                controller: _itemsController,
                label: 'Daftar Kandidat',
                hint: 'Pisahkan dengan koma (Andi, Budi, Citra)',
                maxLines: 3,
              ),
            ],
            const SizedBox(height: 20),
            const Text(
              'Pengaturan Putaran',
              style: TextStyle(
                fontWeight: FontWeight.bold,
                color: AppTheme.textSecondary,
              ),
            ),
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: AppTheme.radiusSmall,
                border: Border.all(color: Colors.black12),
              ),
              child: Column(
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('Durasi Putaran (detik)'),
                      Row(
                        children: [
                          IconButton(
                            icon: const Icon(Icons.remove_circle_outline),
                            onPressed: _spinDuration > 10
                                ? () => setState(() => _spinDuration -= 5)
                                : null,
                          ),
                          Text(
                            '$_spinDuration',
                            style: const TextStyle(
                              fontWeight: FontWeight.bold,
                              fontSize: 16,
                            ),
                          ),
                          IconButton(
                            icon: const Icon(Icons.add_circle_outline),
                            onPressed: _spinDuration < 30
                                ? () => setState(() => _spinDuration += 5)
                                : null,
                          ),
                        ],
                      ),
                    ],
                  ),
                  const Divider(height: 24),
                  SwitchListTile(
                    title: const Text(
                      'Hapus Pemenang Setelah Putaran',
                      style: TextStyle(fontSize: 14),
                    ),
                    subtitle: const Text(
                      'Kandidat yang sudah menang tidak akan terpilih lagi di putaran berikutnya',
                      style: TextStyle(fontSize: 12),
                    ),
                    value: _removeWinner,
                    contentPadding: EdgeInsets.zero,
                    activeColor: AppTheme.primary,
                    onChanged: (val) => setState(() => _removeWinner = val),
                  ),
                  const Divider(height: 24),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('Tampilkan di Dashboard selama'),
                      DropdownButton<int>(
                        value: _dashboardDurationMinutes,
                        items: const [
                          DropdownMenuItem(value: 30, child: Text('30 Menit')),
                          DropdownMenuItem(value: 60, child: Text('1 Jam')),
                          DropdownMenuItem(value: 180, child: Text('3 Jam')),
                          DropdownMenuItem(value: -1, child: Text('Custom (Jam)')),
                        ],
                        onChanged: (val) {
                          if (val != null) setState(() => _dashboardDurationMinutes = val);
                        },
                      ),
                    ],
                  ),
                  if (_dashboardDurationMinutes == -1) ...[
                    const SizedBox(height: 8),
                    CustomTextField(
                      controller: _customDurationController,
                      label: 'Jumlah Jam',
                      keyboardType: TextInputType.number,
                      validator: (value) {
                        if (value == null || value.isEmpty) return 'Wajib diisi';
                        return null;
                      },
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 40),
            CustomButton(text: 'Simpan & Buat', onPressed: _submit),
          ],
        ),
      ),
    );
  }
}
