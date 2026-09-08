import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
import '../../services/auth_service.dart';
import '../../services/event_service.dart';
import '../../models/user_model.dart';
import '../../models/event_model.dart';
import '../widgets/app_drawer.dart';
import 'create_event_screen.dart';
import 'event_detail_screen.dart';
import 'package:mobile/screens/widgets/common/custom_loading_indicator.dart';
import '../widgets/animations/fade_in_slide.dart';

class PengelolaAcaraScreen extends StatefulWidget {
  const PengelolaAcaraScreen({super.key});

  @override
  State<PengelolaAcaraScreen> createState() => _PengelolaAcaraScreenState();
}

class _PengelolaAcaraScreenState extends State<PengelolaAcaraScreen> {
  UserModel? _user;
  List<EventModel> _events = [];
  bool _isLoading = true;
  String _searchQuery = '';
  String _sortBy = 'Tanggal Acara (Terdekat)';
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
  }

  bool _isEventActive(EventModel e) {
    if (e.statusKegiatan != null) {
      return e.statusKegiatan == 'berlangsung' || e.statusKegiatan == 'akan_datang';
    }
    return e.isActive;
  }

  List<EventModel> _getFilteredAndSorted(bool active) {
    var result = _events.where((e) {
      bool matchesStatus = _isEventActive(e) == active;
      bool matchesSearch = _searchQuery.isEmpty || e.namaAcara.toLowerCase().contains(_searchQuery.toLowerCase());
      return matchesStatus && matchesSearch;
    }).toList();

    result.sort((a, b) {
      if (_sortBy == 'Nama Acara') {
        return a.namaAcara.compareTo(b.namaAcara);
      } else if (_sortBy == 'Tanggal Dibuat') {
        return b.createdAt.compareTo(a.createdAt);
      }
      // Default: Tanggal Acara (Terdekat)
      return a.tanggalAcara.compareTo(b.tanggalAcara);
    });

    return result;
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        drawer: _user != null ? AppDrawer(user: _user!) : null,
        backgroundColor: AppTheme.background,
        body: RefreshIndicator(
          onRefresh: _loadData,
          color: AppTheme.primary,
          child: NestedScrollView(
            headerSliverBuilder: (context, innerBoxIsScrolled) {
              return [
                SliverAppBar(
                  title: const Text('Kelola Acara', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                  backgroundColor: AppTheme.primary,
                  iconTheme: const IconThemeData(color: Colors.white),
                  pinned: true,
                  floating: true,
                  elevation: 0,
                  flexibleSpace: Container(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                        colors: [AppTheme.primary, AppTheme.primary.withOpacity(0.8)],
                      ),
                    ),
                  ),
                  bottom: PreferredSize(
                    preferredSize: const Size.fromHeight(130),
                    child: Column(
                      children: [
                        // Floating Search Bar
                        Container(
                          margin: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
                          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(20),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withOpacity(0.1),
                                blurRadius: 10,
                                offset: const Offset(0, 5),
                              ),
                            ],
                          ),
                          child: Row(
                            children: [
                              const Icon(Icons.search, color: Colors.grey),
                              const SizedBox(width: 8),
                              Expanded(
                                child: TextField(
                                  decoration: const InputDecoration(
                                    hintText: 'Cari acara...',
                                    border: InputBorder.none,
                                    isDense: true,
                                    contentPadding: EdgeInsets.symmetric(vertical: 12),
                                  ),
                                  onChanged: (value) => setState(() => _searchQuery = value),
                                ),
                              ),
                              Container(
                                height: 24,
                                width: 1,
                                color: Colors.grey.shade300,
                                margin: const EdgeInsets.symmetric(horizontal: 8),
                              ),
                              DropdownButtonHideUnderline(
                                child: DropdownButton<String>(
                                  value: _sortBy,
                                  icon: const Icon(Icons.tune, color: AppTheme.primary, size: 20),
                                  alignment: AlignmentDirectional.centerEnd,
                                  onChanged: (String? newValue) {
                                    if (newValue != null) {
                                      setState(() => _sortBy = newValue);
                                    }
                                  },
                                  items: <String>[
                                    'Tanggal Acara (Terdekat)',
                                    'Tanggal Dibuat',
                                    'Nama Acara',
                                  ].map<DropdownMenuItem<String>>((String value) {
                                    return DropdownMenuItem<String>(
                                      value: value,
                                      child: Text(
                                        value == 'Tanggal Acara (Terdekat)' ? 'Terdekat' : value == 'Tanggal Dibuat' ? 'Terbaru' : 'Nama',
                                        style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppTheme.primary),
                                      ),
                                    );
                                  }).toList(),
                                ),
                              ),
                            ],
                          ),
                        ),
                        // Tab Bar
                        TabBar(
                          indicatorColor: Colors.white,
                          indicatorWeight: 3,
                          labelColor: Colors.white,
                          unselectedLabelColor: Colors.white.withOpacity(0.6),
                          labelStyle: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                          tabs: const [
                            Tab(text: 'Aktif'),
                            Tab(text: 'Selesai'),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              ];
            },
            body: TabBarView(
              children: [
                _buildEventList(true),
                _buildEventList(false),
              ],
            ),
          ),
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
          icon: const Icon(Icons.add, color: Colors.white),
          label: const Text(
            'Buat Acara Baru',
            style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600),
          ),
          backgroundColor: AppTheme.primary,
          elevation: 4,
        ),
      ),
    );
  }

  Widget _buildEventList(bool active) {
    if (_isLoading && _events.isEmpty) {
      return const Center(
        child: CustomLoadingIndicator(color: AppTheme.primary),
      );
    }

    if (_errorMessage != null && _events.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 64, color: AppTheme.error),
            const SizedBox(height: 16),
            Text(_errorMessage!, style: const TextStyle(color: AppTheme.error)),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: _loadData,
              child: const Text('Coba Lagi'),
            ),
          ],
        ),
      );
    }

    final filtered = _getFilteredAndSorted(active);

    if (filtered.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.event_busy, size: 80, color: Colors.grey.shade300),
            const SizedBox(height: 16),
            Text(
              active ? 'Belum Ada Acara Aktif' : 'Belum Ada Acara Selesai',
              style: const TextStyle(
                color: AppTheme.textPrimary,
                fontSize: 18,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              active ? 'Acara yang sedang berjalan akan tampil di sini.' : 'Riwayat acara akan tampil di sini.',
              style: const TextStyle(color: AppTheme.textSecondary, fontSize: 14),
            ),
          ],
        ),
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.only(
        top: 20,
        left: 20,
        right: 20,
        bottom: 100, // Extra space for FAB
      ),
      physics: const AlwaysScrollableScrollPhysics(),
      itemCount: filtered.length,
      itemBuilder: (context, index) {
        final event = filtered[index];
        return FadeInSlide(
          delay: 0.1 * (index % 10), // Prevent too much delay for large lists
          child: _buildEventCard(event),
        );
      },
    );
  }

  Widget _buildEventCard(EventModel event) {
    Color statusColor = _getStatusColor(event.statusKegiatan ?? (event.isActive ? 'berlangsung' : 'selesai'));
    
    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: AppTheme.radiusLarge,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.06),
            blurRadius: 15,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: AppTheme.radiusLarge,
        child: Container(
          decoration: BoxDecoration(
            border: Border(
              left: BorderSide(color: statusColor, width: 6),
            ),
          ),
          child: InkWell(
            onTap: () async {
              final result = await Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => EventDetailScreen(eventId: event.id),
                ),
              );
              if (result == true) {
                _loadData();
              }
            },
            child: Padding(
              padding: const EdgeInsets.all(20.0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Expanded(
                        child: Text(
                          event.namaAcara,
                          style: const TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.bold,
                            color: AppTheme.textPrimary,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 12,
                          vertical: 6,
                        ),
                        decoration: BoxDecoration(
                          color: statusColor.withOpacity(0.1),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: Text(
                          _getStatusText(
                            event.statusKegiatan ?? (event.isActive ? 'berlangsung' : 'selesai'),
                          ),
                          style: TextStyle(
                            color: statusColor,
                            fontSize: 10,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 0.5,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: Colors.grey.shade50,
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: const Icon(Icons.calendar_month, size: 20, color: AppTheme.primary),
                      ),
                      const SizedBox(width: 12),
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('Waktu Pelaksanaan', style: TextStyle(fontSize: 11, color: AppTheme.textSecondary)),
                          const SizedBox(height: 2),
                          Text(
                            event.tanggalAcara,
                            style: const TextStyle(
                              color: AppTheme.textPrimary,
                              fontWeight: FontWeight.w600,
                              fontSize: 14,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 12),
                    child: Divider(height: 1, color: Colors.black12),
                  ),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Row(
                        children: [
                          const Icon(Icons.people_alt_outlined, size: 16, color: AppTheme.textSecondary),
                          const SizedBox(width: 6),
                          Text(
                            'Kehadiran: ${event.jumlahHadir ?? 0} Orang',
                            style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13, fontWeight: FontWeight.w500),
                          ),
                        ],
                      ),
                      const Icon(Icons.arrow_forward_ios, size: 14, color: Colors.grey),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Color _getStatusColor(String status) {
    switch (status) {
      case 'berlangsung':
        return AppTheme.success;
      case 'akan_datang':
        return AppTheme.info;
      case 'selesai':
      default:
        return AppTheme.textSecondary;
    }
  }

  String _getStatusText(String status) {
    switch (status) {
      case 'berlangsung':
        return 'SEDANG BERLANGSUNG';
      case 'akan_datang':
        return 'AKAN DATANG';
      case 'selesai':
      default:
        return 'SELESAI';
    }
  }
}
