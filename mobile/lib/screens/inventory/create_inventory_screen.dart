import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
import '../../services/inventory_service.dart';
import '../widgets/common/custom_button.dart';

class CreateInventoryScreen extends StatefulWidget {
  const CreateInventoryScreen({Key? key}) : super(key: key);

  @override
  _CreateInventoryScreenState createState() => _CreateInventoryScreenState();
}

class _CreateInventoryScreenState extends State<CreateInventoryScreen> {
  final TextEditingController _nameController = TextEditingController();
  final TextEditingController _qtyController = TextEditingController();
  final TextEditingController _conditionController = TextEditingController(text: 'Baik');
  
  bool _isSubmitting = false;

  Future<void> _submit() async {
    final name = _nameController.text.trim();
    final qtyText = _qtyController.text.trim();
    final condition = _conditionController.text.trim();

    if (name.isEmpty || qtyText.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Nama dan kuantitas wajib diisi')));
      return;
    }

    final qty = int.tryParse(qtyText);
    if (qty == null || qty <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Kuantitas harus berupa angka lebih dari 0')));
      return;
    }

    setState(() => _isSubmitting = true);

    final data = {
      'name': name,
      'total_quantity': qty,
      'condition': condition,
    };

    final result = await InventoryService.createInventory(data);
    setState(() => _isSubmitting = false);

    if (result['success']) {
      if (mounted) Navigator.pop(context, true);
    } else {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(result['message'])));
    }
  }

  @override
  void dispose() {
    _nameController.dispose();
    _qtyController.dispose();
    _conditionController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Tambah Barang', style: TextStyle(color: Colors.white)),
        backgroundColor: AppTheme.primary,
        iconTheme: const IconThemeData(color: Colors.white),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            TextField(
              controller: _nameController,
              decoration: const InputDecoration(
                labelText: 'Nama Barang',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _qtyController,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(
                labelText: 'Kuantitas Total',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _conditionController,
              decoration: const InputDecoration(
                labelText: 'Kondisi (Opsional)',
                border: OutlineInputBorder(),
                hintText: 'Contoh: Baik, Baru, Perlu Perbaikan',
              ),
            ),
            const SizedBox(height: 32),
            SizedBox(
              width: double.infinity,
              height: 48,
              child: CustomButton(
                text: 'Simpan Barang',
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
