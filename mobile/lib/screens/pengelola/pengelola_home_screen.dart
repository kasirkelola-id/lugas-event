import 'package:flutter/material.dart';
import '../../services/auth_service.dart';
import '../../services/event_service.dart';
import '../../models/user_model.dart';
import '../../models/event_model.dart';
import '../auth/login_screen.dart';
import 'create_event_screen.dart';
import 'event_detail_screen.dart';

class PengelolaHomeScreen extends StatefulWidget {
  const PengelolaHomeScreen({super.key});

  @override
  State<PengelolaHomeScreen> createState() => _PengelolaHomeScreenState();
}

class _PengelolaHomeScreenState extends State<PengelolaHomeScreen> {
  UserModel? _user;
  List<EventModel> _events = [];
  bool _isLoading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final userResult = await AuthService.getMe();
    if (!userResult['success']) {
      if (mounted) _handleError(userResult['message']);
      return;
    }

    final eventsResult = await EventService.getEvents();
    if (!mounted) return;

    if (eventsResult['success']) {
      setState(() {
        _user = userResult['user'];
        _events = eventsResult['events'] as List<EventModel>;
        _isLoading = false;
      });
    } else {
      _handleError(eventsResult['message'] ?? 'Data acara gagal dimuat.');
    }
  }

  void _handleError(String message) {
    setState(() {
      _isLoading = false;
      _errorMessage = message;
    });
    if (message.toLowerCase().contains('sesi') || message.toLowerCase().contains('berakhir')) {
      _logout();
    }
  }

  void _logout() async {
    await AuthService.logout();
    if (!mounted) return;
    Navigator.pushReplacement(
      context,
      MaterialPageRoute(builder: (_) => const LoginScreen()),
    );
  }

  int get _activeEventsCount => _events.where((e) => e.isActive).length;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Beranda Pengelola'),
        actions: [
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: _logout,
            tooltip: 'Logout',
          )
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _loadData,
        child: _buildBody(),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () async {
          final result = await Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const CreateEventScreen()),
          );
          if (result == true) {
            _loadData();
          }
        },
        icon: const Icon(Icons.add),
        label: const Text('Buat Acara'),
      ),
    );
  }

  Widget _buildBody() {
    if (_isLoading && _events.isEmpty && _user == null) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_errorMessage != null && _events.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(_errorMessage!, style: const TextStyle(color: Colors.red)),
            const SizedBox(height: 16),
            ElevatedButton(onPressed: _loadData, child: const Text('Coba Lagi')),
          ],
        ),
      );
    }

    return ListView(
      padding: const EdgeInsets.all(16),
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        if (_user != null) ...[
          Text(
            'Selamat datang,\n${_user!.namaPanggilan}',
            style: Theme.of(context).textTheme.headlineSmall,
          ),
          const SizedBox(height: 24),
          Row(
            children: [
              Expanded(
                child: _buildStatCard('Total Acara', _events.length.toString(), Colors.blue),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: _buildStatCard('Acara Aktif', _activeEventsCount.toString(), Colors.green),
              ),
            ],
          ),
          const SizedBox(height: 32),
        ],
        
        Text('Acara Terbaru', style: Theme.of(context).textTheme.titleLarge),
        const Divider(),
        if (_events.isEmpty && !_isLoading)
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 32),
            child: Center(child: Text('Belum ada acara')),
          )
        else
          ..._events.map((event) => _buildEventCard(event)),
          
        const SizedBox(height: 80), // padding for FAB
      ],
    );
  }

  Widget _buildStatCard(String title, String value, Color color) {
    return Card(
      elevation: 2,
      child: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          children: [
            Text(title, style: const TextStyle(fontSize: 14)),
            const SizedBox(height: 8),
            Text(
              value,
              style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold, color: color),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildEventCard(EventModel event) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: ListTile(
        title: Text(event.namaAcara, style: const TextStyle(fontWeight: FontWeight.bold)),
        subtitle: Text('${event.tanggalAcara}\nJumlah Hadir: ${event.jumlahHadir ?? 0}'),
        isThreeLine: true,
        trailing: Chip(
          label: Text(event.isActive ? 'AKTIF' : 'SELESAI'),
          backgroundColor: event.isActive ? Colors.green.shade100 : Colors.grey.shade200,
          labelStyle: TextStyle(
            color: event.isActive ? Colors.green.shade800 : Colors.grey.shade700,
            fontSize: 12,
            fontWeight: FontWeight.bold,
          ),
        ),
        onTap: () async {
          final result = await Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => EventDetailScreen(eventId: event.id)),
          );
          if (result == true) {
            _loadData();
          }
        },
      ),
    );
  }
}
