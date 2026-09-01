import 'package:flutter/material.dart';
import '../../models/event_model.dart';
import '../../services/event_service.dart';
import '../../services/auth_service.dart';
import '../auth/login_screen.dart';
import 'package:intl/intl.dart';

class EditEventScreen extends StatefulWidget {
  final EventModel event;
  const EditEventScreen({super.key, required this.event});

  @override
  State<EditEventScreen> createState() => _EditEventScreenState();
}

class _EditEventScreenState extends State<EditEventScreen> {
  final _formKey = GlobalKey<FormState>();
  late TextEditingController _namaController;
  late TextEditingController _tanggalController;
  bool _isLoading = false;

  @override
  void initState() {
    super.initState();
    _namaController = TextEditingController(text: widget.event.namaAcara);
    _tanggalController = TextEditingController(text: widget.event.tanggalAcara);
  }

  void _pilihTanggal() async {
    final initialDate = DateTime.tryParse(widget.event.tanggalAcara) ?? DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: initialDate,
      firstDate: DateTime(2000),
      lastDate: DateTime(2100),
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

    final result = await EventService.updateEvent(
      id: widget.event.id,
      nama: _namaController.text,
      tanggal: _tanggalController.text,
    );

    setState(() {
      _isLoading = false;
    });

    if (!mounted) return;

    if (result['success']) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Acara berhasil diperbarui.')),
      );
      Navigator.pop(context, true); // return true to refresh
    } else {
      if (result['statusCode'] == 401) {
        await AuthService.logout();
        if (!mounted) return;
        Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => const LoginScreen()));
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(result['message'])),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Edit Acara')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            TextFormField(
              controller: _namaController,
              decoration: const InputDecoration(labelText: 'Nama Acara', border: OutlineInputBorder()),
              enabled: !_isLoading,
              validator: (v) => v!.isEmpty ? 'Nama acara harus diisi' : null,
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _tanggalController,
              decoration: const InputDecoration(
                labelText: 'Tanggal Acara (YYYY-MM-DD)',
                border: OutlineInputBorder(),
                suffixIcon: Icon(Icons.calendar_today),
              ),
              readOnly: true,
              onTap: _isLoading ? null : _pilihTanggal,
              validator: (v) => v!.isEmpty ? 'Tanggal acara harus diisi' : null,
            ),
            const SizedBox(height: 24),
            ElevatedButton(
              onPressed: _isLoading ? null : _submit,
              child: _isLoading ? const CircularProgressIndicator() : const Text('Simpan Perubahan'),
            ),
          ],
        ),
      ),
    );
  }
}
