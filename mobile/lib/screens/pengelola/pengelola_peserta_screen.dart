import 'package:flutter/material.dart';
import '../../services/auth_service.dart';
import '../../models/user_model.dart';
import '../widgets/app_drawer.dart';

class PengelolaPesertaScreen extends StatefulWidget {
  const PengelolaPesertaScreen({super.key});

  @override
  State<PengelolaPesertaScreen> createState() => _PengelolaPesertaScreenState();
}

class _PengelolaPesertaScreenState extends State<PengelolaPesertaScreen> {
  UserModel? _user;

  @override
  void initState() {
    super.initState();
    _loadUser();
  }

  Future<void> _loadUser() async {
    final userResult = await AuthService.getMe();
    if (mounted && userResult['success']) {
      setState(() {
        _user = userResult['user'];
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Peserta & Absensi')),
      drawer: _user != null ? AppDrawer(user: _user!) : null,
      body: const Center(
        child: Padding(
          padding: EdgeInsets.all(24.0),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.construction, size: 64, color: Colors.orange),
              SizedBox(height: 16),
              Text('Fitur Peserta selengkapnya akan segera hadir. Saat ini Anda dapat melihat daftar hadir langsung melalui detail acara.', textAlign: TextAlign.center, style: TextStyle(fontSize: 16, color: Colors.grey)),
            ],
          ),
        ),
      ),
    );
  }
}
