import 'package:flutter/material.dart';
import '../../services/event_service.dart';
import '../../services/auth_service.dart';
import '../auth/login_screen.dart';
import 'package:intl/intl.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import '../shared/map_picker_screen.dart';
import 'package:latlong2/latlong.dart';
import '../../core/theme/app_theme.dart';
import 'package:mobile/screens/widgets/common/custom_loading_indicator.dart';

class CreateEventScreen extends StatefulWidget {
  const CreateEventScreen({super.key});

  @override
  State<CreateEventScreen> createState() => _CreateEventScreenState();
}

class _CreateEventScreenState extends State<CreateEventScreen> {
  final _formKey = GlobalKey<FormState>();
  final _namaController = TextEditingController();
  final _tanggalController = TextEditingController();
  bool _isLoading = false;
  
  bool _requireGps = false;
  LatLng? _selectedLocation;
  double _radius = 50.0;
  final MapController _mapController = MapController();

  void _pilihTanggal() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: DateTime.now(),
      firstDate: DateTime.now(),
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

    if (_requireGps && _selectedLocation == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Pilih lokasi acara pada peta.')),
      );
      setState(() {
        _isLoading = false;
      });
      return;
    }

    final result = await EventService.createEvent(
      nama: _namaController.text,
      tanggal: _tanggalController.text,
      requireGps: _requireGps,
      latitude: _selectedLocation?.latitude,
      longitude: _selectedLocation?.longitude,
      radius: _radius.toInt(),
    );

    setState(() {
      _isLoading = false;
    });

    if (!mounted) return;

    if (result['success']) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Acara berhasil dibuat.')),
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
      appBar: AppBar(title: const Text('Buat Acara')),
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
            const SizedBox(height: 16),
            SwitchListTile(
              title: const Text('Batasi Presensi dengan GPS'),
              subtitle: const Text('Anggota hanya bisa absen di sekitar lokasi acara'),
              value: _requireGps,
              activeColor: AppTheme.primary,
              onChanged: _isLoading ? null : (val) {
                setState(() {
                  _requireGps = val;
                  if (val && _selectedLocation == null) {
                    _selectedLocation = const LatLng(-6.200000, 106.816666); // Default Jakarta
                  }
                });
              },
            ),
            if (_requireGps) ...[
              const SizedBox(height: 16),
              const Text('Lokasi Acara', style: TextStyle(fontWeight: FontWeight.bold)),
              const SizedBox(height: 8),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  border: Border.all(color: Colors.grey.shade300),
                  borderRadius: BorderRadius.circular(8),
                  color: Colors.grey.shade50,
                ),
                child: Column(
                  children: [
                    if (_selectedLocation != null) ...[
                      const Icon(Icons.location_on, color: AppTheme.error, size: 48),
                      const SizedBox(height: 8),
                      Text(
                        'Lat: ${_selectedLocation!.latitude.toStringAsFixed(6)}\nLng: ${_selectedLocation!.longitude.toStringAsFixed(6)}',
                        textAlign: TextAlign.center,
                        style: const TextStyle(fontWeight: FontWeight.bold),
                      ),
                      const SizedBox(height: 16),
                    ] else ...[
                      const Icon(Icons.map_outlined, color: Colors.grey, size: 48),
                      const SizedBox(height: 8),
                      const Text('Lokasi belum dipilih', style: TextStyle(color: Colors.grey)),
                      const SizedBox(height: 16),
                    ],
                    ElevatedButton.icon(
                      onPressed: _isLoading ? null : () async {
                        final result = await Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => MapPickerScreen(initialLocation: _selectedLocation),
                          ),
                        );
                        if (result != null && result is LatLng) {
                          setState(() {
                            _selectedLocation = result;
                          });
                        }
                      },
                      icon: const Icon(Icons.open_in_full, size: 16),
                      label: const Text('Buka Peta Interaktif'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppTheme.secondary,
                        foregroundColor: Colors.white,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 8),
              const Text('Ketuk peta untuk memindahkan lokasi acara.', style: TextStyle(fontSize: 12, color: Colors.grey)),
              const SizedBox(height: 16),
              Row(
                children: [
                  const Text('Radius (meter):', style: TextStyle(fontWeight: FontWeight.bold)),
                  Expanded(
                    child: Slider(
                      value: _radius,
                      min: 10,
                      max: 1000,
                      divisions: 99,
                      label: '${_radius.toInt()} m',
                      activeColor: AppTheme.primary,
                      onChanged: _isLoading ? null : (val) {
                        setState(() {
                          _radius = val;
                        });
                      },
                    ),
                  ),
                  Text('${_radius.toInt()} m', style: const TextStyle(fontWeight: FontWeight.bold)),
                ],
              ),
            ],
            const SizedBox(height: 24),
            ElevatedButton(
              onPressed: _isLoading ? null : _submit,
              child: _isLoading ? const CustomLoadingIndicator() : const Text('Buat Acara'),
            ),
          ],
        ),
      ),
    );
  }
}
