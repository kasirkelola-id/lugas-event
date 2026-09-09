import sys

with open('mobile/lib/screens/anggota/attendance_geofence_screen.dart', 'r', encoding='utf-8') as f:
    code = f.read()

# Replace build method to use SliverAppBar
build_idx = code.find('  @override\n  Widget build(BuildContext context) {')
build_end = code.find('  Widget _buildEmptyState() {')

new_build = '''  @override
  Widget build(BuildContext context) {
    return Scaffold(
      drawer: _currentUser != null ? AppDrawer(user: _currentUser!) : null,
      backgroundColor: AppTheme.background,
      body: _isLoading
          ? const Center(child: CustomLoadingIndicator(color: AppTheme.primary))
          : RefreshIndicator(
              onRefresh: _initLocationAndData,
              color: AppTheme.primary,
              child: CustomScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                slivers: [
                  SliverAppBar(
                    title: const Text(
                      'Absensi Lokasi',
                      style: TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    backgroundColor: AppTheme.primary,
                    iconTheme: const IconThemeData(color: Colors.white),
                    pinned: true,
                    floating: true,
                    elevation: 0,
                    expandedHeight: 180,
                    flexibleSpace: FlexibleSpaceBar(
                      background: Container(
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                            colors: [
                              AppTheme.primary,
                              AppTheme.primary.withOpacity(0.8),
                            ],
                          ),
                        ),
                        child: SafeArea(
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.end,
                            children: [
                              const Icon(Icons.location_on, color: Colors.white, size: 48),
                              const SizedBox(height: 8),
                              const Text(
                                'Catat Kehadiran Anda',
                                style: TextStyle(color: Colors.white, fontSize: 16),
                              ),
                              const SizedBox(height: 24),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
                  SliverToBoxAdapter(
                    child: Padding(
                      padding: const EdgeInsets.all(24.0),
                      child: Column(
                        children: [
                          FadeInSlide(delay: 0.1, child: _buildLocationStatusCard()),
                          const SizedBox(height: 32),
                          if (_nearbyEvents.isEmpty && _errorMessage.isEmpty)
                            FadeInSlide(delay: 0.2, child: _buildEmptyState())
                          else
                            ..._nearbyEvents.asMap().entries.map((entry) => FadeInSlide(
                              delay: 0.2 + (0.1 * entry.key), 
                              child: _buildEventCard(entry.value),
                            )),
                          if (_errorMessage.isNotEmpty)
                            Padding(
                              padding: const EdgeInsets.only(top: 24),
                              child: ElevatedButton.icon(
                                onPressed: _initLocationAndData,
                                icon: const Icon(Icons.refresh),
                                label: const Text('Coba Lagi'),
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: Colors.white,
                                  foregroundColor: AppTheme.primary,
                                  elevation: 2,
                                  padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 24),
                                ),
                              ),
                            ),
                          const SizedBox(height: 40),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
    );
  }

'''

code = code[:build_idx] + new_build + code[build_end:]

with open('mobile/lib/screens/anggota/attendance_geofence_screen.dart', 'w', encoding='utf-8') as f:
    f.write(code)

print("Refactored Geofence Screen.")
