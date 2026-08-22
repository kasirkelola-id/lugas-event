import 'package:flutter/material.dart';
import '../../services/auth_service.dart';
import '../../models/user_model.dart';
import '../widgets/app_drawer.dart';

class PengelolaAcaraScreen extends StatefulWidget {
  const PengelolaAcaraScreen({super.key});

  @override
  State<PengelolaAcaraScreen> createState() => _PengelolaAcaraScreenState();
}

class _PengelolaAcaraScreenState extends State<PengelolaAcaraScreen> {
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
      appBar: AppBar(title: const Text('Daftar Acara')),
      drawer: _user != null ? AppDrawer(user: _user!) : null,
      body: const Center(child: Text('Fitur Daftar Acara selengkapnya akan segera hadir.')),
    );
  }
}
