# Status Fitur Aplikasi Lugas

Dokumen ini melacak status fitur untuk aplikasi Lugas (baik versi Website maupun Mobile).

*Terakhir diperbarui: 22 Oktober 2026*

## 📱 Mobile App (Flutter)

### Auth & User
- [x] Login (`login_screen.dart`, `auth_storage.dart`)
- [x] PIN tenant (`pin_screen.dart`)
- [x] Logout (`auth_service.dart`)
- [x] Force change password (`force_change_password_screen.dart`)
- [x] Profile Service (`profile_service.dart`)
- [ ] Register (screen belum dibuat)

### Events & Participants
- [x] Participant Service (`participant_service.dart`)
- [x] Event Service (`event_service.dart`)
- [x] Menampilkan daftar event
- [x] Detail event
- [x] Create & Edit event (pengelola)
- [x] Peserta event (`participant_list_screen.dart`)

### Absensi
- [x] Check-in & Check-out (`attendance_service.dart`)
- [x] Geofencing (`attendance_geofence_screen.dart`)

### Kas
- [x] Daftar kas & summary (`kas_screen.dart`)
- [x] Tambah kas (`add_kas_screen.dart`)

### Chat
- [x] Chat list & rooms (`chat_list_screen.dart`)
- [x] Chat room (`chat_room_screen.dart`)
- [x] Buat group chat (`create_group_screen.dart`)
- [x] Group info (`group_info_screen.dart`)
- [x] Private chat

### Fitur Lainnya
- [x] Voting (`voting_list_screen.dart`, `voting_detail_screen.dart`)
- [x] Undian / Wheel (`wheel_list_screen.dart`, `wheel_session_screen.dart`)
- [x] Inventory (`inventory_main_screen.dart`)
- [x] Pengumuman (`user_pengumuman_screen.dart`)
- [x] Dashboard (`dashboard_service.dart`)
- [x] Laporan (`admin_laporan_screen.dart`)
- [x] Pengaturan (`admin_pengaturan_screen.dart`)
- [x] Notifikasi Push (FCM via `notification_service.dart`)
- [x] QR Code Scanner (`mobile_scanner`)
- [x] Thermal Printer (`blue_thermal_printer`)
- [x] Map / Geolokasi (`flutter_map`, `geolocator`)

## 🌐 Website (Backend/Admin - CodeIgniter 4/PHP)

### Auth & User
- [x] Login Admin (Superadmin Panel)
- [x] Manajemen User (via Superadmin)
- [x] Approval anggota (`MembershipController.php`)

### Events API
- [x] Event Controller API (`EventController.php`)
- [x] Routing (`Routes.php`)
- [x] CRUD Event
- [x] Verifikasi Participant

### Fitur Backend Lainnya
- [x] Chat API (`ChatController.php`)
- [x] Voting API (`VotingController.php`)
- [x] Undian/Wheel API (`WheelController.php`)
- [x] Kas API (`KasController.php`)
- [x] Inventory API (`InventoryController.php`)
- [x] Absensi API (`AbsensiController.php`)
- [x] Pengumuman API (`AnnouncementController.php`)
- [x] Dashboard API (`DashboardController.php`)
- [x] Profil API (`ProfileController.php`)
- [x] Laporan API (`ReportController.php`)
- [x] Pengaturan API (`SettingController.php`)
- [x] User Management API (`UserController.php`)
- [x] Internal API (`InternalApiController.php`)
- [x] App Version API (`AppVersionController.php`)
- [x] Multi-tenant (Karang Taruna)
- [x] Push Notification (FCM via Google Auth)

---

*Catatan: Centang `[x]` jika sudah selesai, dan `[ ]` jika belum. Silakan sesuaikan atau tambahkan fitur yang kurang.*
