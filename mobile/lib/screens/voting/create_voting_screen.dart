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
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Minimal harus ada 2 opsi pilihan')));
    }
  }

  Future<void> _submit() async {
    final title = _titleController.text.trim();
    if (title.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Judul voting wajib diisi')));
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
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Minimal 2 opsi pilihan wajib diisi')));
      return;
    }

    setState(() => _isSubmitting = true);

    final data = {
      'title': title,
      'description': _descController.text.trim(),
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
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(result['message'])));
      }
    }
  }

  @override
  void dispose() {
    _titleController.dispose();
    _descController.dispose();
    for (var c in _optionControllers) {
      c.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Buat Voting Baru', style: TextStyle(color: Colors.white)),
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
            const Text('Opsi Pilihan:', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
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
                          contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                        ),
                      ),
                    ),
                    if (_optionControllers.length > 2)
                      IconButton(
                        icon: const Icon(Icons.remove_circle, color: Colors.red),
                        onPressed: () => _removeOption(index),
                      )
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
