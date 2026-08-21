import 'package:flutter/material.dart';
import '../../models/attendance_model.dart';
import '../../models/event_model.dart';
import '../../services/attendance_service.dart';
import '../../services/auth_service.dart';
import '../auth/login_screen.dart';

class AttendanceListScreen extends StatefulWidget {
  final EventModel event;
  const AttendanceListScreen({super.key, required this.event});

  @override
  State<AttendanceListScreen> createState() => _AttendanceListScreenState();
}

class _AttendanceListScreenState extends State<AttendanceListScreen> {
  List<AttendanceModel> _attendees = [];
  bool _isLoading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _loadAttendees();
  }

  Future<void> _loadAttendees() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final result = await AttendanceService.getEventAttendance(widget.event.id);
    if (!mounted) return;

    if (result['success']) {
      setState(() {
        _attendees = result['attendees'] as List<AttendanceModel>;
        _isLoading = false;
      });
    } else {
      setState(() {
        _isLoading = false;
        _errorMessage = (result['message'].toString().toLowerCase().contains('sesi') || 
                         result['message'].toString().toLowerCase().contains('berakhir'))
            ? result['message']
            : 'Daftar hadir gagal dimuat.';
      });
      if (result['statusCode'] == 401) {
        await AuthService.logout();
        if (!mounted) return;
        Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => const LoginScreen()));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Daftar Hadir')),
      body: RefreshIndicator(
        onRefresh: _loadAttendees,
        child: _buildBody(),
      ),
    );
  }

  Widget _buildBody() {
    if (_isLoading && _attendees.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_errorMessage != null && _attendees.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(_errorMessage!, style: const TextStyle(color: Colors.red)),
            const SizedBox(height: 16),
            ElevatedButton(onPressed: _loadAttendees, child: const Text('Coba Lagi')),
          ],
        ),
      );
    }

    return ListView(
      padding: const EdgeInsets.all(16),
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        Text(widget.event.namaAcara, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold)),
        const SizedBox(height: 16),
        if (_attendees.isEmpty)
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 32.0),
            child: Center(child: Text('Belum ada peserta yang hadir.')),
          )
        else
          ..._attendees.asMap().entries.map((entry) {
            final index = entry.key;
            final item = entry.value;
            return Card(
              margin: const EdgeInsets.only(bottom: 8),
              child: ListTile(
                leading: CircleAvatar(
                  backgroundColor: Colors.blue.shade100,
                  child: Text('${index + 1}'),
                ),
                title: Text(item.namaPanggilan.isNotEmpty ? item.namaPanggilan : 'Peserta', style: const TextStyle(fontWeight: FontWeight.bold)),
                subtitle: Text('${item.namaLengkap}\n${item.waktuAbsen}'),
                isThreeLine: true,
              ),
            );
          }),
      ],
    );
  }
}
