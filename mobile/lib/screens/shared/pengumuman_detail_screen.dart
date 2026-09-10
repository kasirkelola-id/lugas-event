import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
import '../../models/announcement_model.dart';
import 'package:intl/intl.dart';

class PengumumanDetailScreen extends StatelessWidget {
  final AnnouncementModel announcement;

  const PengumumanDetailScreen({super.key, required this.announcement});

  @override
  Widget build(BuildContext context) {
    final DateTime createdAt = DateTime.tryParse(announcement.createdAt) ?? DateTime.now();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Detail Pengumuman'),
        backgroundColor: AppTheme.surface,
        elevation: 0,
        scrolledUnderElevation: 0,
      ),
      backgroundColor: AppTheme.background,
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: announcement.targetRole == 'semua'
                        ? Colors.blue.shade50
                        : Colors.orange.shade50,
                    borderRadius: AppTheme.radiusSmall,
                    border: Border.all(
                      color: announcement.targetRole == 'semua'
                          ? Colors.blue.shade200
                          : Colors.orange.shade200,
                    ),
                  ),
                  child: Text(
                    announcement.targetRole == 'semua'
                        ? 'UNTUK SEMUA'
                        : (announcement.targetRole == 'pengelola'
                            ? 'UNTUK PENGELOLA'
                            : 'UNTUK ANGGOTA'),
                    style: TextStyle(
                      fontSize: 10,
                      color: announcement.targetRole == 'semua'
                          ? Colors.blue.shade700
                          : Colors.orange.shade700,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
                const Spacer(),
                const Icon(Icons.access_time, size: 14, color: AppTheme.textSecondary),
                const SizedBox(width: 4),
                Text(
                  DateFormat('dd MMM yyyy, HH:mm').format(createdAt),
                  style: const TextStyle(fontSize: 12, color: AppTheme.textSecondary),
                ),
              ],
            ),
            const SizedBox(height: 16),
            Text(
              announcement.judul,
              style: const TextStyle(
                fontSize: 24,
                fontWeight: FontWeight.bold,
                color: AppTheme.primary,
                height: 1.3,
              ),
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Icon(Icons.person_outline, size: 14, color: Colors.grey.shade500),
                const SizedBox(width: 4),
                Text(
                  'Oleh: ${announcement.pembuat}',
                  style: TextStyle(
                    fontSize: 14,
                    color: Colors.grey.shade700,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 24),
            const Divider(color: Colors.black12, height: 1),
            const SizedBox(height: 24),
            Text(
              announcement.isi,
              style: const TextStyle(
                fontSize: 16,
                color: AppTheme.textPrimary,
                height: 1.6,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
