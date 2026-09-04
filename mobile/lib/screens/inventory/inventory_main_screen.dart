import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
import '../../models/inventory_model.dart';
import '../../models/inventory_loan_model.dart';
import '../../models/user_model.dart';
import '../../services/auth_service.dart';
import '../../services/inventory_service.dart';
import 'create_inventory_screen.dart';
import 'loan_request_screen.dart';
import 'package:intl/intl.dart';
import 'package:mobile/screens/widgets/common/custom_loading_indicator.dart';

class InventoryMainScreen extends StatefulWidget {
  const InventoryMainScreen({Key? key}) : super(key: key);

  @override
  _InventoryMainScreenState createState() => _InventoryMainScreenState();
}

class _InventoryMainScreenState extends State<InventoryMainScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  
  bool _isLoading = true;
  List<Inventory> _inventories = [];
  List<InventoryLoan> _loans = [];
  UserModel? _currentUser;

  // Filters
  final TextEditingController _searchController = TextEditingController();
  String _inventoryFilter = 'Semua'; // Semua, Tersedia, Habis
  String _loanFilter = 'Aktif'; // Semua, Aktif, Riwayat

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    _tabController.addListener(() => setState(() {}));
    _searchController.addListener(() => setState(() {}));
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() => _isLoading = true);
    final userResult = await AuthService.getMe();
    if (userResult['success']) {
      _currentUser = userResult['user'];
    }
    await Future.wait([
      _fetchInventories(),
      _fetchLoans(),
    ]);
    if (mounted) {
      setState(() => _isLoading = false);
    }
  }

  Future<void> _fetchInventories() async {
    final result = await InventoryService.getInventories();
    if (result['success']) {
      _inventories = result['inventories'];
    }
  }

  Future<void> _fetchLoans() async {
    final result = await InventoryService.getLoans();
    if (result['success']) {
      _loans = result['loans'];
    }
  }

  Future<void> _refresh() async {
    await Future.wait([
      _fetchInventories(),
      _fetchLoans(),
    ]);
    if (mounted) setState(() {});
  }

  @override
  void dispose() {
    _tabController.dispose();
    _searchController.dispose();
    super.dispose();
  }

  List<Inventory> get _filteredInventories {
    List<Inventory> list = _inventories.where((item) {
      bool matchesSearch = item.name.toLowerCase().contains(_searchController.text.toLowerCase());
      bool matchesFilter = true;
      if (_inventoryFilter == 'Tersedia') matchesFilter = item.availableQuantity > 0;
      if (_inventoryFilter == 'Habis') matchesFilter = item.availableQuantity == 0;
      return matchesSearch && matchesFilter;
    }).toList();
    
    list.sort((a, b) {
      // Available first
      int aAvail = a.availableQuantity > 0 ? 1 : 0;
      int bAvail = b.availableQuantity > 0 ? 1 : 0;
      if (aAvail != bAvail) {
        return bAvail.compareTo(aAvail);
      }
      return a.name.compareTo(b.name);
    });
    return list;
  }

  List<InventoryLoan> get _filteredLoans {
    List<InventoryLoan> list = _loans.where((item) {
      if (_loanFilter == 'Semua') return true;
      if (_loanFilter == 'Aktif') return item.status == 'pending' || item.status == 'approved';
      if (_loanFilter == 'Riwayat') return item.status == 'returned' || item.status == 'rejected';
      return true;
    }).toList();

    final bool isKetuaOrAdmin = _currentUser?.roleLevel == 'ketua' || _currentUser?.roleLevel == 'superadmin' || _currentUser?.roleLevel == 'admin';

    list.sort((a, b) {
      if (isKetuaOrAdmin) {
        // Pending first for admin
        int aPending = a.status == 'pending' ? 1 : 0;
        int bPending = b.status == 'pending' ? 1 : 0;
        if (aPending != bPending) return bPending.compareTo(aPending);
      }
      // Newest first
      return b.borrowDate.compareTo(a.borrowDate);
    });
    return list;
  }

  @override
  Widget build(BuildContext context) {
    final bool isKetuaOrAdmin = _currentUser?.roleLevel == 'ketua' || _currentUser?.roleLevel == 'superadmin' || _currentUser?.roleLevel == 'admin';

    return Scaffold(
      appBar: AppBar(
        title: const Text('Inventaris Barang', style: TextStyle(color: Colors.white)),
        backgroundColor: AppTheme.primary,
        iconTheme: const IconThemeData(color: Colors.white),
        bottom: TabBar(
          controller: _tabController,
          labelColor: Colors.white,
          unselectedLabelColor: Colors.white70,
          indicatorColor: Colors.white,
          tabs: const [
            Tab(text: 'Daftar Barang'),
            Tab(text: 'Peminjaman'),
          ],
        ),
      ),
      body: _isLoading
          ? const Center(child: CustomLoadingIndicator())
          : TabBarView(
              controller: _tabController,
              children: [
                _buildInventoryTab(),
                _buildLoanTab(isKetuaOrAdmin),
              ],
            ),
      floatingActionButton: (_tabController.index == 0 && isKetuaOrAdmin)
          ? FloatingActionButton(
              onPressed: () async {
                final result = await Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const CreateInventoryScreen()),
                );
                if (result == true) {
                  setState(() => _isLoading = true);
                  await _refresh();
                  setState(() => _isLoading = false);
                }
              },
              backgroundColor: AppTheme.primary,
              child: const Icon(Icons.add, color: Colors.white),
            )
          : null,
    );
  }

  Widget _buildInventoryTab() {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(16.0),
          child: Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _searchController,
                  decoration: InputDecoration(
                    hintText: 'Cari barang...',
                    prefixIcon: const Icon(Icons.search),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              DropdownButton<String>(
                value: _inventoryFilter,
                items: ['Semua', 'Tersedia', 'Habis'].map((e) => DropdownMenuItem(value: e, child: Text(e))).toList(),
                onChanged: (val) {
                  if (val != null) setState(() => _inventoryFilter = val);
                },
              ),
            ],
          ),
        ),
        Expanded(
          child: _filteredInventories.isEmpty
              ? const Center(child: Text('Belum ada barang di inventaris'))
              : RefreshIndicator(
                  onRefresh: _refresh,
                  child: ListView.builder(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                    itemCount: _filteredInventories.length,
                    itemBuilder: (context, index) {
                      final item = _filteredInventories[index];
                      final isAvailable = item.availableQuantity > 0;
                      
                      return Card(
                        elevation: 2,
                        margin: const EdgeInsets.only(bottom: 12),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        child: ListTile(
                          leading: CircleAvatar(
                            backgroundColor: AppTheme.primary.withValues(alpha: 0.1),
                            child: const Icon(Icons.inventory_2, color: AppTheme.primary),
                          ),
                          title: Text(item.name, style: const TextStyle(fontWeight: FontWeight.bold)),
                          subtitle: Text('Total: ${item.totalQuantity} | Kondisi: ${item.condition ?? '-'}'),
                          trailing: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Text(isAvailable ? 'Tersedia' : 'Stok Habis', style: TextStyle(fontSize: 10, color: isAvailable ? Colors.grey.shade600 : Colors.red)),
                              Text(
                                '${item.availableQuantity}',
                                style: TextStyle(
                                  fontWeight: FontWeight.bold,
                                  fontSize: 18,
                                  color: isAvailable ? Colors.green : Colors.red,
                                ),
                              ),
                            ],
                          ),
                          onTap: () async {
                            final result = await Navigator.push(
                              context,
                              MaterialPageRoute(builder: (_) => LoanRequestScreen(inventory: item)),
                            );
                            if (result == true) {
                              setState(() => _isLoading = true);
                              await _refresh();
                              setState(() => _isLoading = false);
                              _tabController.animateTo(1);
                            }
                          },
                        ),
                      );
                    },
                  ),
                ),
        ),
      ],
    );
  }

  Widget _buildLoanTab(bool isKetuaOrAdmin) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 8.0),
          child: Row(
            children: ['Semua', 'Aktif', 'Riwayat'].map((type) {
              final isSelected = _loanFilter == type;
              return Padding(
                padding: const EdgeInsets.only(right: 8.0),
                child: FilterChip(
                  label: Text(type),
                  selected: isSelected,
                  onSelected: (val) {
                    setState(() => _loanFilter = type);
                  },
                  selectedColor: AppTheme.primary.withValues(alpha: 0.2),
                  checkmarkColor: AppTheme.primary,
                ),
              );
            }).toList(),
          ),
        ),
        Expanded(
          child: _filteredLoans.isEmpty
              ? const Center(child: Text('Tidak ada pengajuan yang sesuai.'))
              : RefreshIndicator(
                  onRefresh: _refresh,
                  child: ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: _filteredLoans.length,
                    itemBuilder: (context, index) {
                      final loan = _filteredLoans[index];
                      final format = DateFormat('dd MMM yyyy');
                      
                      Color statusColor = Colors.grey;
                      String statusText = 'Pending';
                      if (loan.status == 'approved') {
                        statusColor = Colors.blue;
                        statusText = 'Disetujui';
                      } else if (loan.status == 'returned') {
                        statusColor = Colors.green;
                        statusText = 'Dikembalikan';
                      } else if (loan.status == 'rejected') {
                        statusColor = Colors.red;
                        statusText = 'Ditolak';
                      }

                      return Card(
                        elevation: 2,
                        margin: const EdgeInsets.only(bottom: 12),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        child: Padding(
                          padding: const EdgeInsets.all(16),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  Expanded(
                                    child: Text(
                                      loan.inventoryName ?? 'Barang',
                                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                                    ),
                                  ),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                    decoration: BoxDecoration(
                                      color: statusColor.withValues(alpha: 0.1),
                                      borderRadius: BorderRadius.circular(12),
                                    ),
                                    child: Text(
                                      statusText,
                                      style: TextStyle(color: statusColor, fontWeight: FontWeight.bold, fontSize: 12),
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 8),
                              Text('Peminjam: ${loan.userName}'),
                              Text('Jumlah: ${loan.quantity}'),
                              Text('Tanggal: ${format.format(loan.borrowDate)} s.d ${format.format(loan.returnDate)}'),
                              
                              if (isKetuaOrAdmin && (loan.status == 'pending' || loan.status == 'approved')) ...[
                                const Divider(height: 24),
                                Row(
                                  mainAxisAlignment: MainAxisAlignment.end,
                                  children: [
                                    if (loan.status == 'pending') ...[
                                      TextButton(
                                        onPressed: () => _confirmUpdateLoanStatus(loan.id, 'rejected', 'Tolak Peminjaman', 'Tolak peminjaman ${loan.quantity} ${loan.inventoryName} untuk ${loan.userName}?'),
                                        child: const Text('Tolak', style: TextStyle(color: Colors.red)),
                                      ),
                                      ElevatedButton(
                                        onPressed: () => _confirmUpdateLoanStatus(loan.id, 'approved', 'Setujui Peminjaman', 'Setujui peminjaman ${loan.quantity} ${loan.inventoryName} untuk ${loan.userName}?'),
                                        style: ElevatedButton.styleFrom(backgroundColor: AppTheme.primary),
                                        child: const Text('Setujui', style: TextStyle(color: Colors.white)),
                                      ),
                                    ],
                                    if (loan.status == 'approved') ...[
                                      ElevatedButton(
                                        onPressed: () => _confirmUpdateLoanStatus(loan.id, 'returned', 'Tandai Dikembalikan', 'Tandai barang sudah dikembalikan? Stok akan dikembalikan otomatis.'),
                                        style: ElevatedButton.styleFrom(backgroundColor: Colors.green),
                                        child: const Text('Dikembalikan', style: TextStyle(color: Colors.white)),
                                      ),
                                    ],
                                  ],
                                )
                              ],
                            ],
                          ),
                        ),
                      );
                    },
                  ),
                ),
        ),
      ],
    );
  }

  Future<void> _confirmUpdateLoanStatus(int loanId, String status, String title, String message) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(title),
        content: Text(message),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Batal')),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, true), 
            style: ElevatedButton.styleFrom(backgroundColor: AppTheme.primary),
            child: const Text('Ya', style: TextStyle(color: Colors.white)),
          ),
        ],
      )
    );

    if (confirm == true) {
      _updateLoanStatus(loanId, status);
    }
  }

  Future<void> _updateLoanStatus(int loanId, String status) async {
    setState(() => _isLoading = true);
    final result = await InventoryService.changeLoanStatus(loanId, status);
    if (result['success']) {
      await _refresh();
    } else {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(result['message'])));
      }
    }
    setState(() => _isLoading = false);
  }
}
