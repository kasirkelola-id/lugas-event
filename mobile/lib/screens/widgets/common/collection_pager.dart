import 'package:flutter/material.dart';

class CollectionPager extends StatelessWidget {
  final Map<String, dynamic>? pagination;
  final bool loading;
  final ValueChanged<int> onPage;
  const CollectionPager({
    super.key,
    required this.pagination,
    required this.loading,
    required this.onPage,
  });

  @override
  Widget build(BuildContext context) {
    final page = pagination?['page'] as int? ?? 1;
    final pages = pagination?['total_pages'] as int? ?? 1;
    if (pages <= 1) return const SizedBox.shrink();
    return SafeArea(
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          IconButton(
            tooltip: 'Sebelumnya',
            onPressed: loading || page <= 1 ? null : () => onPage(page - 1),
            icon: const Icon(Icons.chevron_left),
          ),
          Text('Halaman $page / $pages'),
          IconButton(
            tooltip: 'Berikutnya',
            onPressed: loading || page >= pages ? null : () => onPage(page + 1),
            icon: const Icon(Icons.chevron_right),
          ),
        ],
      ),
    );
  }
}
