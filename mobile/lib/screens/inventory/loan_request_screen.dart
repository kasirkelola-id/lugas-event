import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
import '../../models/inventory_model.dart';
import '../../services/inventory_service.dart';
import '../widgets/common/custom_button.dart';
import 'package:intl/intl.dart';

class LoanRequestScreen extends StatefulWidget {
  final Inventory inventory;

  const LoanRequestScreen({Key? key, required this.inventory}) : super(key: key);

  @override
  _LoanRequestScreenState createState() => _LoanRequestScreenState();
}

class _LoanRequestScreenState extends State<LoanRequestScreen> {
  final TextEditingController _qtyController = TextEditingController(text: '1');
  DateTime? _borrowDate;
  DateTime? _returnDate;
  
  bool _isSubmitting = false;

  Future<void> _selectDate(BuildContext context, bool isBorrow) async {
    final initialDate = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: initialDate,
      firstDate: initialDate,
      lastDate: DateTime.now().add(const Duration(days: 365)),
    );

    if (picked != null) {
      setState(() {
        if (isBorrow) {
          _borrowDate = picked;
        } else {
          _returnDate = picked;
        }
      });
    }
  }

  Future<void> _submit() async {
    final qtyText = _qtyController.text.trim();
    final qty = int.tryParse(qtyText);

    if (qty == null || qty <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Kuantitas tidak valid, masukkan angka bulat positif')));
      return;
    }
    if (qty > widget.inventory.availableQuantity) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Maksimal peminjaman: ${widget.inventory.availableQuantity}')));
      return;
    }
    if (_borrowDate == null || _returnDate == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Pilih tanggal pinjam dan tanggal kembali')));
      return;
    }
    if (_returnDate!.isBefore(_borrowDate!)) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Tanggal kembali tidak boleh mendahului tanggal pinjam')));
      return;
    }

    setState(() => _isSubmitting = true);

    final format = DateFormat('yyyy-MM-dd');
    final data = {
      'inventory_id': widget.inventory.id,
      'quantity': qty,
      'borrow_date': format.format(_borrowDate!),
      'return_date': format.format(_returnDate!),
    };

    final result = await InventoryService.requestLoan(data);
    setState(() => _isSubmitting = false);

    if (result['success']) {
      if (mounted) Navigator.pop(context, true);
    } else {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(result['message'])));
    }
  }

  @override
  void dispose() {
    _qtyController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final format = DateFormat('dd MMM yyyy');
    final isAvailable = widget.inventory.availableQuantity > 0;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Detail & Pengajuan', style: TextStyle(color: Colors.white)),
        backgroundColor: AppTheme.primary,
        iconTheme: const IconThemeData(color: Colors.white),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: AppTheme.surface,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: Colors.grey.shade200),
                boxShadow: AppTheme.shadowSoft,
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  CircleAvatar(
                    radius: 40,
                    backgroundColor: AppTheme.primary.withValues(alpha: 0.1),
                    child: const Icon(Icons.inventory_2, color: AppTheme.primary, size: 40),
                  ),
                  const SizedBox(height: 16),
                  Text(
                    widget.inventory.name, 
                    style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 12),
                  const Divider(),
                  const SizedBox(height: 12),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceAround,
                    children: [
                      Column(
                        children: [
                          const Text('Total Stok', style: TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                          const SizedBox(height: 4),
                          Text('${widget.inventory.totalQuantity}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                        ],
                      ),
                      Column(
                        children: [
                          const Text('Tersedia', style: TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                          const SizedBox(height: 4),
                          Text(
                            '${widget.inventory.availableQuantity}', 
                            style: TextStyle(
                              fontWeight: FontWeight.bold, 
                              fontSize: 16,
                              color: isAvailable ? Colors.green : Colors.red,
                            ),
                          ),
                        ],
                      ),
                      Column(
                        children: [
                          const Text('Kondisi', style: TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                          const SizedBox(height: 4),
                          Text(widget.inventory.condition ?? '-', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                        ],
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 32),
            if (isAvailable) ...[
              const Text('Formulir Pengajuan', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
              const SizedBox(height: 16),
              TextField(
                controller: _qtyController,
                keyboardType: TextInputType.number,
                decoration: InputDecoration(
                  labelText: 'Jumlah yang dipinjam',
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                  prefixIcon: const Icon(Icons.numbers),
                ),
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: InkWell(
                      onTap: () => _selectDate(context, true),
                      child: InputDecorator(
                        decoration: InputDecoration(
                          labelText: 'Tanggal Pinjam',
                          border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                          prefixIcon: const Icon(Icons.calendar_today, size: 18),
                        ),
                        child: Text(_borrowDate != null ? format.format(_borrowDate!) : 'Pilih Tanggal', style: const TextStyle(fontSize: 14)),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: InkWell(
                      onTap: () => _selectDate(context, false),
                      child: InputDecorator(
                        decoration: InputDecoration(
                          labelText: 'Tanggal Kembali',
                          border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                          prefixIcon: const Icon(Icons.calendar_month, size: 18),
                        ),
                        child: Text(_returnDate != null ? format.format(_returnDate!) : 'Pilih Tanggal', style: const TextStyle(fontSize: 14)),
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 32),
              SizedBox(
                width: double.infinity,
                height: 52,
                child: CustomButton(
                  text: 'Ajukan Peminjaman',
                  onPressed: _isSubmitting ? null : _submit,
                  isLoading: _isSubmitting,
                ),
              ),
            ] else ...[
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.red.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: Colors.red.withValues(alpha: 0.3)),
                ),
                child: const Row(
                  children: [
                    Icon(Icons.info_outline, color: Colors.red),
                    SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        'Barang ini sedang habis dipinjam, sehingga Anda tidak dapat mengajukan peminjaman saat ini.',
                        style: TextStyle(color: Colors.red),
                      ),
                    ),
                  ],
                ),
              ),
            ]
          ],
        ),
      ),
    );
  }
}
