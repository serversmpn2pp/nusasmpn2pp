import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/facility_dashboard/application/facility_dashboard_controller.dart';
import 'package:nusa/features/facility_dashboard/domain/facility_dashboard.dart';

class FacilityDashboardView extends ConsumerWidget {
  const FacilityDashboardView({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(facilityDashboardControllerProvider);
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Dashboard Sarpras'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading
                ? null
                : ref
                      .read(facilityDashboardControllerProvider.notifier)
                      .refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: state.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _ErrorState(
            message: error is AppException
                ? error.message
                : 'Dashboard sarana prasarana belum dapat dimuat.',
            onRetry: ref
                .read(facilityDashboardControllerProvider.notifier)
                .refresh,
          ),
          data: (data) => RefreshIndicator(
            onRefresh: ref
                .read(facilityDashboardControllerProvider.notifier)
                .refresh,
            child: ListView(
              key: const PageStorageKey<String>('facility-dashboard-scroll'),
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
              children: [
                _HeroCard(data: data),
                const SizedBox(height: 16),
                const _SectionTitle(
                  title: 'Ringkasan Inventaris',
                  subtitle: 'Kondisi data sarpras aktif saat ini',
                ),
                const SizedBox(height: 10),
                _SummaryGrid(summary: data.summary),
                const SizedBox(height: 18),
                const _SectionTitle(
                  title: 'Menu Sarpras',
                  subtitle: 'Pintasan ditampilkan sesuai hak akses akun',
                ),
                const SizedBox(height: 10),
                _FacilityToolGrid(items: data.tools),
                const SizedBox(height: 18),
                _AttentionOverview(summary: data.summary),
                const SizedBox(height: 18),
                const _SectionTitle(
                  title: 'Perhatian Stok',
                  subtitle: 'Stok habis, menipis, dan saldo yang belum dicatat',
                ),
                const SizedBox(height: 10),
                _StockSection(data: data),
                const SizedBox(height: 18),
                const _SectionTitle(
                  title: 'Distribusi Unit Aset',
                  subtitle: 'Sebaran unit berdasarkan status terakhir',
                ),
                const SizedBox(height: 10),
                _UnitDistributionCard(items: data.unitDistribution),
                const SizedBox(height: 18),
                const _SectionTitle(
                  title: 'Peminjaman Terlambat',
                  subtitle: 'Peminjaman aktif yang melewati rencana kembali',
                ),
                const SizedBox(height: 10),
                if (data.overdueLoans.isEmpty)
                  const _EmptyCard(
                    icon: Icons.task_alt_rounded,
                    title: 'Tidak ada peminjaman terlambat',
                    message: 'Seluruh peminjaman aktif masih sesuai jadwal.',
                    success: true,
                  )
                else
                  ...data.overdueLoans.map(
                    (loan) => Padding(
                      padding: const EdgeInsets.only(bottom: 9),
                      child: _OverdueLoanCard(loan: loan),
                    ),
                  ),
                const SizedBox(height: 9),
                const _SectionTitle(
                  title: 'Unit Perlu Perhatian',
                  subtitle: 'Aset rusak, dalam perbaikan, atau hilang',
                ),
                const SizedBox(height: 10),
                if (data.unitAttention.isEmpty)
                  const _EmptyCard(
                    icon: Icons.verified_rounded,
                    title: 'Semua unit dalam kondisi baik',
                    message: 'Tidak ada unit aktif yang memerlukan perhatian.',
                    success: true,
                  )
                else
                  ...data.unitAttention.map(
                    (unit) => Padding(
                      padding: const EdgeInsets.only(bottom: 9),
                      child: _UnitAttentionCard(unit: unit),
                    ),
                  ),
                const SizedBox(height: 9),
                const _SectionTitle(
                  title: 'Aktivitas Terbaru',
                  subtitle: 'Barang datang, mutasi, pinjam, dan kembali',
                ),
                const SizedBox(height: 10),
                _RecentActivityCard(items: data.recentActivities),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _FacilityToolGrid extends StatelessWidget {
  const _FacilityToolGrid({required this.items});

  final List<FacilityTool> items;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) {
      return const _EmptyCard(
        icon: Icons.lock_outline_rounded,
        title: 'Belum ada menu Sarpras',
        message: 'Hak akses akun belum mencakup menu operasional Sarpras.',
      );
    }

    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth < 350 ? 2 : 3;
        final width = (constraints.maxWidth - ((columns - 1) * 9)) / columns;
        return Wrap(
          spacing: 9,
          runSpacing: 9,
          children: items
              .map(
                (item) => _FacilityToolCard(
                  width: width,
                  item: item,
                  onTap: () {
                    if (item.isAvailable) {
                      context.push(item.route!);
                      return;
                    }
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(
                        content: Text(
                          '${item.label} akan dilanjutkan sebagai modul native berikutnya.',
                        ),
                      ),
                    );
                  },
                ),
              )
              .toList(),
        );
      },
    );
  }
}

class _FacilityToolCard extends StatelessWidget {
  const _FacilityToolCard({
    required this.width,
    required this.item,
    required this.onTap,
  });

  final double width;
  final FacilityTool item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: width,
    height: 116,
    child: Card(
      margin: EdgeInsets.zero,
      child: InkWell(
        key: Key('facility-tool-${item.code}'),
        onTap: onTap,
        borderRadius: BorderRadius.circular(18),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 10),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: NusaColors.surfaceBlue,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(
                  _facilityToolIcon(item.code),
                  color: NusaColors.primary,
                  size: 22,
                ),
              ),
              const SizedBox(height: 7),
              Text(
                item.label,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: NusaColors.primaryDark,
                  fontSize: 10.5,
                  fontWeight: FontWeight.w800,
                  height: 1.15,
                ),
              ),
              if (!item.isAvailable) ...[
                const SizedBox(height: 3),
                const Text(
                  'Segera hadir',
                  style: TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 8.5,
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    ),
  );
}

class _HeroCard extends StatelessWidget {
  const _HeroCard({required this.data});

  final FacilityDashboard data;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(18),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
      ),
      borderRadius: BorderRadius.circular(20),
      boxShadow: [
        BoxShadow(
          color: NusaColors.primary.withValues(alpha: 0.2),
          blurRadius: 20,
          offset: const Offset(0, 8),
        ),
      ],
    ),
    child: Stack(
      children: [
        Positioned(
          right: -20,
          top: -24,
          child: Icon(
            Icons.warehouse_rounded,
            size: 122,
            color: Colors.white.withValues(alpha: 0.06),
          ),
        ),
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 50,
                  height: 50,
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.13),
                    borderRadius: BorderRadius.circular(15),
                    border: Border.all(
                      color: NusaColors.accent.withValues(alpha: 0.75),
                    ),
                  ),
                  child: const Icon(
                    Icons.inventory_2_rounded,
                    color: NusaColors.accent,
                    size: 28,
                  ),
                ),
                const SizedBox(width: 13),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Sarana Prasarana',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 19,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        data.dateLabel,
                        style: const TextStyle(
                          color: Color(0xFFDCEBFA),
                          fontSize: 11.5,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 15),
            Wrap(
              spacing: 7,
              runSpacing: 7,
              children: [
                if (data.access.canViewGoods)
                  const _AccessChip(label: 'Lihat inventaris'),
                if (data.access.canManageGoods)
                  const _AccessChip(label: 'Kelola barang'),
                if (data.access.canManageLoans)
                  const _AccessChip(label: 'Kelola peminjaman'),
              ],
            ),
          ],
        ),
      ],
    ),
  );
}

class _AccessChip extends StatelessWidget {
  const _AccessChip({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
    decoration: BoxDecoration(
      color: Colors.white.withValues(alpha: 0.11),
      borderRadius: BorderRadius.circular(20),
      border: Border.all(color: Colors.white.withValues(alpha: 0.16)),
    ),
    child: Text(
      label,
      style: const TextStyle(
        color: Colors.white,
        fontSize: 9.5,
        fontWeight: FontWeight.w700,
      ),
    ),
  );
}

class _SummaryGrid extends StatelessWidget {
  const _SummaryGrid({required this.summary});

  final FacilitySummary summary;

  @override
  Widget build(BuildContext context) {
    final items = [
      _Metric('Jenis barang', summary.goodsTypes, Icons.category_rounded),
      _Metric('Unit aset', summary.assetUnits, Icons.qr_code_2_rounded),
      _Metric(
        'Unit tersedia',
        summary.availableUnits,
        Icons.check_circle_rounded,
      ),
      _Metric(
        'Pinjaman aktif',
        summary.activeLoans,
        Icons.assignment_return_rounded,
      ),
    ];
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        mainAxisSpacing: 9,
        crossAxisSpacing: 9,
        mainAxisExtent: 120,
      ),
      itemCount: items.length,
      itemBuilder: (context, index) => _MetricCard(metric: items[index]),
    );
  }
}

class _Metric {
  const _Metric(this.label, this.value, this.icon);
  final String label;
  final int value;
  final IconData icon;
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({required this.metric});

  final _Metric metric;

  @override
  Widget build(BuildContext context) => Card(
    margin: EdgeInsets.zero,
    child: Padding(
      padding: const EdgeInsets.all(13),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: NusaColors.surfaceBlue,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(metric.icon, color: NusaColors.primary, size: 22),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${metric.value}',
                  style: const TextStyle(
                    color: NusaColors.primary,
                    fontSize: 21,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                Text(
                  metric.label,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 10.5,
                    height: 1.2,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    ),
  );
}

class _AttentionOverview extends StatelessWidget {
  const _AttentionOverview({required this.summary});

  final FacilitySummary summary;

  @override
  Widget build(BuildContext context) {
    final items = [
      (
        'Terlambat',
        summary.overdueLoans,
        Icons.timer_off_rounded,
        Colors.red.shade700,
      ),
      (
        'Jatuh tempo',
        summary.dueSoon,
        Icons.event_rounded,
        const Color(0xFFB57900),
      ),
      (
        'Stok menipis',
        summary.lowStock,
        Icons.trending_down_rounded,
        const Color(0xFFF97316),
      ),
      (
        'Stok habis',
        summary.outOfStock,
        Icons.remove_shopping_cart_rounded,
        Colors.red.shade700,
      ),
      (
        'Unit bermasalah',
        summary.unitsNeedingAttention,
        Icons.build_circle_rounded,
        const Color(0xFFF97316),
      ),
      (
        'Belum dicatat',
        summary.unrecordedStock,
        Icons.playlist_add_rounded,
        NusaColors.primary,
      ),
    ];
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: NusaColors.outline),
      ),
      child: Wrap(
        spacing: 8,
        runSpacing: 8,
        children: items
            .map(
              (item) => _AttentionChip(
                label: item.$1,
                value: item.$2,
                icon: item.$3,
                color: item.$4,
              ),
            )
            .toList(),
      ),
    );
  }
}

class _AttentionChip extends StatelessWidget {
  const _AttentionChip({
    required this.label,
    required this.value,
    required this.icon,
    required this.color,
  });

  final String label;
  final int value;
  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 7),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.08),
      borderRadius: BorderRadius.circular(12),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, color: color, size: 15),
        const SizedBox(width: 5),
        Text(
          '$label  ',
          style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.w600),
        ),
        Text(
          '$value',
          style: TextStyle(
            color: color,
            fontSize: 11,
            fontWeight: FontWeight.w900,
          ),
        ),
      ],
    ),
  );
}

class _StockSection extends StatelessWidget {
  const _StockSection({required this.data});

  final FacilityDashboard data;

  @override
  Widget build(BuildContext context) {
    if (data.stockAttention.isEmpty && data.unrecordedStock.isEmpty) {
      return const _EmptyCard(
        icon: Icons.inventory_rounded,
        title: 'Stok dalam kondisi aman',
        message: 'Tidak ada stok yang perlu segera ditindaklanjuti.',
        success: true,
      );
    }
    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          children: [
            ...data.stockAttention.indexed.map(
              (entry) => _StockRow(
                stock: entry.$2,
                divider:
                    entry.$1 < data.stockAttention.length - 1 ||
                    data.unrecordedStock.isNotEmpty,
              ),
            ),
            ...data.unrecordedStock.indexed.map(
              (entry) => _UnrecordedStockRow(
                stock: entry.$2,
                divider: entry.$1 < data.unrecordedStock.length - 1,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _StockRow extends StatelessWidget {
  const _StockRow({required this.stock, required this.divider});

  final FacilityStock stock;
  final bool divider;

  @override
  Widget build(BuildContext context) {
    final color = stock.status == 'habis'
        ? Colors.red.shade700
        : const Color(0xFFF97316);
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 7),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _RoundIcon(icon: Icons.inventory_2_outlined, color: color),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      stock.name,
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      '${stock.code ?? '-'} · ${stock.location}',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10.5,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    '${_number(stock.amount)} ${stock.unit}',
                    style: TextStyle(color: color, fontWeight: FontWeight.w900),
                  ),
                  Text(
                    'min. ${_number(stock.minimum)}',
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 9.5,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
        if (divider) const Divider(height: 1),
      ],
    );
  }
}

class _UnrecordedStockRow extends StatelessWidget {
  const _UnrecordedStockRow({required this.stock, required this.divider});

  final FacilityUnrecordedStock stock;
  final bool divider;

  @override
  Widget build(BuildContext context) => Column(
    children: [
      Padding(
        padding: const EdgeInsets.symmetric(vertical: 9),
        child: Row(
          children: [
            const _RoundIcon(
              icon: Icons.playlist_add_rounded,
              color: NusaColors.primary,
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    stock.name,
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    '${stock.code ?? '-'} · saldo ${stock.unit} belum dicatat',
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 10.5,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
      if (divider) const Divider(height: 1),
    ],
  );
}

class _UnitDistributionCard extends StatelessWidget {
  const _UnitDistributionCard({required this.items});

  final List<FacilityUnitDistribution> items;

  @override
  Widget build(BuildContext context) {
    final maximum = items.fold<int>(
      1,
      (value, item) => item.count > value ? item.count : value,
    );
    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.all(15),
        child: Column(
          children: items.isEmpty
              ? const [Text('Belum ada data unit aset.')]
              : items
                    .map(
                      (item) => Padding(
                        padding: const EdgeInsets.only(bottom: 11),
                        child: Column(
                          children: [
                            Row(
                              children: [
                                Expanded(
                                  child: Text(
                                    item.label,
                                    style: const TextStyle(
                                      fontSize: 11.5,
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                ),
                                Text(
                                  '${item.count}',
                                  style: const TextStyle(
                                    fontSize: 11.5,
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 6),
                            ClipRRect(
                              borderRadius: BorderRadius.circular(10),
                              child: LinearProgressIndicator(
                                minHeight: 7,
                                value: item.count / maximum,
                                color: _hexColor(item.color),
                                backgroundColor: NusaColors.outline.withValues(
                                  alpha: 0.55,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    )
                    .toList(),
        ),
      ),
    );
  }
}

class _OverdueLoanCard extends StatelessWidget {
  const _OverdueLoanCard({required this.loan});

  final FacilityOverdueLoan loan;

  @override
  Widget build(BuildContext context) => Card(
    margin: EdgeInsets.zero,
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _RoundIcon(icon: Icons.timer_off_rounded, color: Colors.red.shade700),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Text(
                        loan.borrower,
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                    ),
                    _StatusPill(
                      label: '${loan.overdueDays} hari',
                      color: Colors.red.shade700,
                    ),
                  ],
                ),
                const SizedBox(height: 3),
                Text(
                  '${loan.identity} · ${loan.number}',
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 10.5,
                  ),
                ),
                const SizedBox(height: 7),
                Text(
                  loan.items.isEmpty
                      ? 'Rincian barang tidak tersedia'
                      : loan.items.join(', '),
                  maxLines: 3,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontSize: 11, height: 1.35),
                ),
                const SizedBox(height: 5),
                Text(
                  'Rencana kembali ${loan.dueDateLabel ?? '-'}',
                  style: TextStyle(
                    color: Colors.red.shade700,
                    fontSize: 10.5,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    ),
  );
}

class _UnitAttentionCard extends StatelessWidget {
  const _UnitAttentionCard({required this.unit});

  final FacilityUnitAttention unit;

  @override
  Widget build(BuildContext context) {
    final color = unit.tone == 'bahaya'
        ? Colors.red.shade700
        : const Color(0xFFF97316);
    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _RoundIcon(icon: Icons.build_rounded, color: color),
            const SizedBox(width: 11),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    unit.goods,
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    '${unit.inventoryCode} · ${unit.location}',
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 10.5,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 6,
                    runSpacing: 6,
                    children: [
                      _StatusPill(label: unit.statusLabel, color: color),
                      _StatusPill(label: unit.conditionLabel, color: color),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _RecentActivityCard extends StatelessWidget {
  const _RecentActivityCard({required this.items});

  final List<FacilityActivity> items;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) {
      return const _EmptyCard(
        icon: Icons.history_rounded,
        title: 'Belum ada aktivitas',
        message: 'Transaksi sarpras terbaru akan tampil di sini.',
      );
    }
    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          children: items.indexed.map((entry) {
            final item = entry.$2;
            final color = _toneColor(item.tone);
            return Column(
              children: [
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 7),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _RoundIcon(icon: _activityIcon(item.type), color: color),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              item.type,
                              style: TextStyle(
                                color: color,
                                fontSize: 10,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              item.title,
                              style: const TextStyle(
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            if (item.description.isNotEmpty) ...[
                              const SizedBox(height: 2),
                              Text(
                                item.description,
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  color: NusaColors.textSecondary,
                                  fontSize: 10.5,
                                ),
                              ),
                            ],
                            if (item.time != null) ...[
                              const SizedBox(height: 3),
                              Text(
                                _dateTime(item.time!),
                                style: const TextStyle(
                                  color: NusaColors.textSecondary,
                                  fontSize: 9.5,
                                ),
                              ),
                            ],
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                if (entry.$1 < items.length - 1) const Divider(height: 1),
              ],
            );
          }).toList(),
        ),
      ),
    );
  }
}

class _RoundIcon extends StatelessWidget {
  const _RoundIcon({required this.icon, required this.color});
  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    width: 38,
    height: 38,
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.09),
      borderRadius: BorderRadius.circular(11),
    ),
    child: Icon(icon, color: color, size: 20),
  );
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.label, required this.color});
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.09),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(
      label,
      style: TextStyle(
        color: color,
        fontSize: 9.5,
        fontWeight: FontWeight.w800,
      ),
    ),
  );
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.title, required this.subtitle});
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        title,
        style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w900),
      ),
      const SizedBox(height: 2),
      Text(
        subtitle,
        style: const TextStyle(color: NusaColors.textSecondary, fontSize: 11),
      ),
    ],
  );
}

class _EmptyCard extends StatelessWidget {
  const _EmptyCard({
    required this.icon,
    required this.title,
    required this.message,
    this.success = false,
  });

  final IconData icon;
  final String title;
  final String message;
  final bool success;

  @override
  Widget build(BuildContext context) {
    final color = success ? NusaColors.success : NusaColors.textSecondary;
    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          children: [
            Icon(icon, size: 34, color: color),
            const SizedBox(height: 8),
            Text(
              title,
              textAlign: TextAlign.center,
              style: const TextStyle(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 3),
            Text(
              message,
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 11,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});
  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(
            Icons.cloud_off_rounded,
            size: 48,
            color: NusaColors.textSecondary,
          ),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 14),
          FilledButton.tonal(
            onPressed: onRetry,
            child: const Text('Coba Lagi'),
          ),
        ],
      ),
    ),
  );
}

Color _toneColor(String tone) => switch (tone) {
  'berhasil' => NusaColors.success,
  'bahaya' => Colors.red.shade700,
  'peringatan' => const Color(0xFFB57900),
  _ => NusaColors.primary,
};

Color _hexColor(String value) {
  final normalized = value.replaceFirst('#', '');
  final parsed = int.tryParse(normalized, radix: 16);
  return parsed == null ? const Color(0xFF94A3B8) : Color(0xFF000000 | parsed);
}

IconData _activityIcon(String type) {
  final lower = type.toLowerCase();
  if (lower.contains('kembali')) return Icons.assignment_return_rounded;
  if (lower.contains('pinjam')) return Icons.outbox_rounded;
  if (lower.contains('mutasi')) return Icons.swap_horiz_rounded;
  return Icons.move_to_inbox_rounded;
}

IconData _facilityToolIcon(String code) => switch (code) {
  'katalog-barang' => Icons.storefront_rounded,
  'pengajuan-saya' => Icons.shopping_cart_checkout_rounded,
  'inventaris-barang' => Icons.inventory_2_rounded,
  'unit-aset' => Icons.qr_code_2_rounded,
  'label-inventaris' => Icons.qr_code_scanner_rounded,
  'barang-datang' => Icons.move_to_inbox_rounded,
  'saldo-stok' => Icons.stacked_bar_chart_rounded,
  'mutasi-stok' => Icons.swap_horiz_rounded,
  'peminjaman-barang' => Icons.outbox_rounded,
  'pengajuan-barang' => Icons.assignment_turned_in_rounded,
  'pengembalian-barang' => Icons.assignment_return_rounded,
  'rekap-peminjaman' => Icons.summarize_rounded,
  'laporan-inventaris' => Icons.assessment_rounded,
  'kategori-barang' => Icons.category_rounded,
  'satuan-barang' => Icons.straighten_rounded,
  'lokasi-barang' => Icons.location_on_rounded,
  'sumber-perolehan' => Icons.account_balance_wallet_rounded,
  'pengaturan-inventaris' => Icons.settings_rounded,
  _ => Icons.inventory_rounded,
};

String _number(double value) => value == value.roundToDouble()
    ? value.toInt().toString()
    : value
          .toStringAsFixed(2)
          .replaceAll(RegExp(r'0+$'), '')
          .replaceAll(RegExp(r'\.$'), '');

String _dateTime(DateTime value) {
  const months = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Agu',
    'Sep',
    'Okt',
    'Nov',
    'Des',
  ];
  final hour = value.hour.toString().padLeft(2, '0');
  final minute = value.minute.toString().padLeft(2, '0');
  return '${value.day} ${months[value.month - 1]} ${value.year}, $hour.$minute WIB';
}
