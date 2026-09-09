import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
import '../../services/voting_service.dart';
import '../widgets/common/custom_button.dart';

class CreateVotingScreen extends StatefulWidget {
  const CreateVotingScreen({Key? key}) : super(key: key);

  @override
  _CreateVotingScreenState createState() => _CreateVotingScreenState();
}

class _CreateVotingScreenState extends State<CreateVotingScreen> {
  final TextEditingController _titleController = TextEditingController();
  final TextEditingController _descController = TextEditingController();

  final TextEditingController _tanggalMulaiController = TextEditingController();
  final TextEditingController _jamMulaiController = TextEditingController();
  final TextEditingController _tanggalSelesaiController =
      TextEditingController();
  final TextEditingController _jamSelesaiController = TextEditingController();

  final List<TextEditingController> _optionControllers = [
    TextEditingController(),
    TextEditingController(),
  ];

  bool _isSubmitting = false;

  void _addOption() {
    setState(() {
      _optionControllers.add(TextEditingController());
    });
  }

  void _removeOption(int index) {
    if (_optionControllers.length > 2) {
      setState(() {
        _optionControllers[index].dispose();
        _optionControllers.removeAt(index);
      });
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Minimal harus ada 2 opsi pilihan')),
      );
    }
  }

  Future<void> _pilihTanggal(TextEditingController controller) async {
    final picked = await showDatePicker(
      context: context,
      initialDate: DateTime.now(),
      firstDate: DateTime.now(),
      lastDate: DateTime(2100),
    );
    if (picked != null) {
      setState(() {
        controller.text =
            "${picked.year}-${picked.month.toString().padLeft(2, '0')}-${picked.day.toString().padLeft(2, '0')}";
      });
    }
  }

  Future<void> _pilihJam(TextEditingController controller) async {
    final picked = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.now(),
    );
    if (picked != null) {
      setState(() {
        controller.text =
            "${picked.hour.toString().padLeft(2, '0')}:${picked.minute.toString().padLeft(2, '0')}:00";
      });
    }
  }

  Future<void> _submit() async {
    final title = _titleController.text.trim();
    if (title.isEmpty) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Judul voting wajib diisi')));
      return;
    }

    if (_tanggalMulaiController.text.isEmpty ||
        _jamMulaiController.text.isEmpty ||
        _tanggalSelesaiController.text.isEmpty ||
        _jamSelesaiController.text.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Waktu mulai dan selesai wajib diisi')),
      );
      return;
    }

    final waktuMulaiStr =
        '${_tanggalMulaiController.text} ${_jamMulaiController.text}';
    final waktuSelesaiStr =
        '${_tanggalSelesaiController.text} ${_jamSelesaiController.text}';

    try {
      final start = DateTime.parse(waktuMulaiStr);
      final end = DateTime.parse(waktuSelesaiStr);
      if (start.isAfter(end) || start.isAtSameMomentAs(end)) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Waktu selesai harus lebih besar dari waktu mulai'),
          ),
        );
        return;
      }
    } catch (e) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Format waktu tidak valid')));
      return;
    }

    final List<String> options = [];
    for (var controller in _optionControllers) {
      final text = controller.text.trim();
      if (text.isNotEmpty) {
        options.add(text);
      }
    }

    if (options.length < 2) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Minimal 2 opsi pilihan wajib diisi')),
      );
      return;
    }

    setState(() => _isSubmitting = true);

    final data = {
      'title': title,
      'description': _descController.text.trim(),
      'waktu_mulai': waktuMulaiStr,
      'waktu_selesai': waktuSelesaiStr,
      'options': options,
    };

    final result = await VotingService.createVoting(data);
    setState(() => _isSubmitting = false);

    if (result['success']) {
      if (mounted) {
        Navigator.pop(context, true);
      }
    } else {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(result['message'])));
      }
    }
  }

  @override
  void dispose() {
    _titleController.dispose();
    _descController.dispose();
    _tanggalMulaiController.dispose();
    _jamMulaiController.dispose();
    _tanggalSelesaiController.dispose();
    _jamSelesaiController.dispose();
    for (var c in _optionControllers) {
      c.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text(
          'Buat Voting Baru',
          style: TextStyle(color: Colors.white),
        ),
        backgroundColor: AppTheme.primary,
        iconTheme: const IconThemeData(color: Colors.white),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            TextField(
              controller: _titleController,
              decoration: const InputDecoration(
                labelText: 'Judul Voting / Pertanyaan',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _descController,
              maxLines: 3,
              decoration: const InputDecoration(
                labelText: 'Deskripsi (Opsional)',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 24),
            const Text(
              'Jadwal Voting:',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: TextFormField(
                    controller: _tanggalMulaiController,
                    readOnly: true,
                    decoration: const InputDecoration(
                      labelText: 'Tgl Mulai',
                      border: OutlineInputBorder(),
                    ),
                    onTap: () => _pilihTanggal(_tanggalMulaiController),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: TextFormField(
                    controller: _jamMulaiController,
                    readOnly: true,
                    decoration: const InputDecoration(
                      labelText: 'Jam Mulai',
                      border: OutlineInputBorder(),
                    ),
                    onTap: () => _pilihJam(_jamMulaiController),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: TextFormField(
                    controller: _tanggalSelesaiController,
                    readOnly: true,
                    decoration: const InputDecoration(
                      labelText: 'Tgl Selesai',
                      border: OutlineInputBorder(),
                    ),
                    onTap: () => _pilihTanggal(_tanggalSelesaiController),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: TextFormField(
                    controller: _jamSelesaiController,
                    readOnly: true,
                    decoration: const InputDecoration(
                      labelText: 'Jam Selesai',
                      border: OutlineInputBorder(),
                    ),
                    onTap: () => _pilihJam(_jamSelesaiController),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 24),
            const Text(
              'Opsi Pilihan:',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
            ),
            const SizedBox(height: 8),

            ...List.generate(_optionControllers.length, (index) {
              return Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Row(
                  children: [
                    Expanded(
                      child: TextField(
                        controller: _optionControllers[index],
                        decoration: InputDecoration(
                          labelText: 'Opsi ${index + 1}',
                          border: const OutlineInputBorder(),
                          contentPadding: const EdgeInsets.symmetric(
                            horizontal: 12,
                            vertical: 8,
                          ),
                        ),
                      ),
                    ),
                    if (_optionControllers.length > 2)
                      IconButton(
                        icon: const Icon(
                          Icons.remove_circle,
                          color: Colors.red,
                        ),
                        onPressed: () => _removeOption(index),
                      ),
                  ],
                ),
              );
            }),

            TextButton.icon(
              onPressed: _addOption,
              icon: const Icon(Icons.add),
              label: const Text('Tambah Opsi'),
            ),

            const SizedBox(height: 32),
            SizedBox(
              width: double.infinity,
              height: 48,
              child: CustomButton(
                text: 'Simpan Voting',
                onPressed: _isSubmitting ? null : _submit,
                isLoading: _isSubmitting,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
