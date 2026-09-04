import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import '../../core/theme/app_theme.dart';
import '../widgets/common/custom_button.dart';
import 'package:geolocator/geolocator.dart';
import 'package:mobile/screens/widgets/common/custom_loading_indicator.dart';

class MapPickerScreen extends StatefulWidget {
  final LatLng? initialLocation;

  const MapPickerScreen({super.key, this.initialLocation});

  @override
  State<MapPickerScreen> createState() => _MapPickerScreenState();
}

class _MapPickerScreenState extends State<MapPickerScreen> {
  late LatLng _selectedLocation;
  final MapController _mapController = MapController();
  bool _isLoadingLocation = false;

  @override
  void initState() {
    super.initState();
    _selectedLocation = widget.initialLocation ?? const LatLng(-6.200000, 106.816666);
  }

  Future<void> _getCurrentLocation() async {
    setState(() => _isLoadingLocation = true);
    try {
      bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) throw Exception('GPS tidak aktif');

      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
        if (permission == LocationPermission.denied) throw Exception('Izin ditolak');
      }
      if (permission == LocationPermission.deniedForever) throw Exception('Izin ditolak permanen');

      final position = await Geolocator.getCurrentPosition(desiredAccuracy: LocationAccuracy.high);
      final newLocation = LatLng(position.latitude, position.longitude);
      
      setState(() {
        _selectedLocation = newLocation;
      });
      _mapController.move(newLocation, 16.0);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    } finally {
      if (mounted) {
        setState(() => _isLoadingLocation = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Pilih Lokasi Acara'),
        actions: [
          IconButton(
            icon: _isLoadingLocation 
                ? const SizedBox(width: 20, height: 20, child: CustomLoadingIndicator(size: 24, ))
                : const Icon(Icons.my_location),
            onPressed: _isLoadingLocation ? null : _getCurrentLocation,
            tooltip: 'Gunakan Lokasi Saat Ini',
          ),
        ],
      ),
      body: Stack(
        children: [
          FlutterMap(
            mapController: _mapController,
            options: MapOptions(
              initialCenter: _selectedLocation,
              initialZoom: 15.0,
              onPositionChanged: (position, hasGesture) {
                if (hasGesture && position.center != null) {
                  setState(() {
                    _selectedLocation = position.center!;
                  });
                }
              },
            ),
            children: [
              TileLayer(
                urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                userAgentPackageName: 'id.lugas.mobile',
              ),
            ],
          ),
          // Center Marker (Pin)
          const Center(
            child: Padding(
              padding: EdgeInsets.only(bottom: 40.0), // Offset to point to exact center
              child: Icon(Icons.location_on, size: 48, color: AppTheme.error),
            ),
          ),
          // Info Box
          Positioned(
            top: 16,
            left: 16,
            right: 16,
            child: Container(
              padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 16),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.9),
                borderRadius: BorderRadius.circular(8),
                boxShadow: const [BoxShadow(color: Colors.black12, blurRadius: 4)],
              ),
              child: Text(
                'Geser peta untuk menentukan titik koordinat.\nLat: ${_selectedLocation.latitude.toStringAsFixed(6)}, Lng: ${_selectedLocation.longitude.toStringAsFixed(6)}',
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
              ),
            ),
          ),
          // Button Confirm
          Positioned(
            bottom: 32,
            left: 24,
            right: 24,
            child: CustomButton(
              text: 'Pilih Lokasi Ini',
              onPressed: () {
                Navigator.pop(context, _selectedLocation);
              },
            ),
          )
        ],
      ),
    );
  }
}
