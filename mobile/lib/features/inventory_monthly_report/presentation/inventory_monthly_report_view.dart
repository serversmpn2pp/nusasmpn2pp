import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/inventory_monthly_report/application/inventory_monthly_report_controller.dart';
import 'package:nusa/features/inventory_monthly_report/application/inventory_monthly_report_document_service.dart';
import 'package:nusa/features/inventory_monthly_report/domain/inventory_monthly_report.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class InventoryMonthlyReportView extends ConsumerStatefulWidget {
  const InventoryMonthlyReportView({super.key});

  @override
  ConsumerState<InventoryMonthlyReportView> createState() =>
      _InventoryMonthlyReportViewState();
}

class _InventoryMonthlyReportViewState
    extends ConsumerState<InventoryMonthlyReportView> {
  bool _exporting = false;

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(inventoryMonthlyReportControllerProvider);
    final report = state.value;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Laporan Inventaris'),
        actions: [
          PopupMenuButton<_DocumentAction>(
            key: const Key('inventory-report-document-menu'),
            enabled: report != null && !_exporting,
            tooltip: 'Cetak atau bagikan laporan',
            onSelected: (action) => _document(action, report!),
            itemBuilder: (_) => const [
              PopupMenuItem(
                value: _DocumentAction.print,
                child: ListTile(
                  leading: Icon(Icons.print_outlined),
                  title: Text('Cetak laporan'),
                  contentPadding: EdgeInsets.zero,
                ),
              ),
              PopupMenuItem(
                value: _DocumentAction.share,
                child: ListTile(
                  leading: Icon(Icons.share_outlined),
                  title: Text('Bagikan PDF'),
                  contentPadding: EdgeInsets.zero,
                ),
              ),
            ],
            icon: _exporting
                ? const SizedBox.square(
                    dimension: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.picture_as_pdf_outlined),
          ),
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading || _exporting
                ? null
                : ref
                      .read(inventoryMonthlyReportControllerProvider.notifier)
                      .refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: state.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, _) => _ErrorState(
            message: _message(error),
            onRetry: ref
                .read(inventoryMonthlyReportControllerProvider.notifier)
                .refresh,
          ),
          data: _content,
        ),
      ),
    );
  }

  Widget _content(InventoryMonthlyReport report) => RefreshIndicator(
    onRefresh: ref
        .read(inventoryMonthlyReportControllerProvider.notifier)
        .refresh,
    child: ListView(
      key: const Key('inventory-monthly-report-scroll'),
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
      children: [
        _ReportHero(report: report),
        const SizedBox(height: 11),
        OutlinedButton.icon(
          key: const Key('inventory-report-filter'),
          onPressed: () => _filter(report),
          icon: const Icon(Icons.tune_rounded),
          label: Text(
            '${report.periodLabel} · ${report.locationLabel}',
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ),
        if (report.unrecordedStock.isNotEmpty) ...[
          const SizedBox(height: 11),
          _UnrecordedWarning(report: report),
        ],
        const SizedBox(height: 11),
        _SignatoriesCard(items: report.signatories),
        const SizedBox(height: 11),
        _ReportSection(
          sectionKey: const Key('inventory-report-services'),
          icon: Icons.badge_outlined,
          title: 'Layanan Barang Pegawai',
          subtitle:
              '${report.employeeServiceSummary.services} layanan pada ${report.periodLabel}',
          initiallyExpanded: true,
          action: IconButton(
            tooltip: 'Buka rekap peminjaman',
            onPressed: () => context.push(
              '/rekap-peminjaman-barang?jenis_peminjam=pegawai&status_pemantauan=semua&tanggal_mulai=${report.startDate}&tanggal_selesai=${report.endDate}',
            ),
            icon: const Icon(Icons.open_in_new_rounded, size: 19),
          ),
          child: Column(
            children: [
              _ServiceSummary(summary: report.employeeServiceSummary),
              const SizedBox(height: 10),
              if (report.employeeServices.isEmpty)
                const _EmptyLine(
                  'Belum ada layanan barang kepada pegawai pada periode ini.',
                )
              else
                for (
                  var index = 0;
                  index < report.employeeServices.length;
                  index++
                )
                  _ServiceCard(
                    item: report.employeeServices[index],
                    showDivider: index < report.employeeServices.length - 1,
                  ),
            ],
          ),
        ),
        const SizedBox(height: 11),
        _ReportSection(
          sectionKey: const Key('inventory-report-stock'),
          icon: Icons.inventory_2_outlined,
          title: 'Rekap Stok Bulanan',
          subtitle: '${report.stockRows.length} baris saldo stok',
          initiallyExpanded: true,
          action: IconButton(
            tooltip: 'Buka mutasi stok',
            onPressed: () => context.push(
              '/mutasi-stok?tanggal_mulai=${report.startDate}&tanggal_selesai=${report.endDate}',
            ),
            icon: const Icon(Icons.open_in_new_rounded, size: 19),
          ),
          child: report.stockRows.isEmpty
              ? const _EmptyLine(
                  'Belum ada saldo stok pada pilihan laporan ini.',
                )
              : Column(
                  children: [
                    for (
                      var index = 0;
                      index < report.stockRows.length;
                      index++
                    )
                      _StockCard(
                        item: report.stockRows[index],
                        showDivider: index < report.stockRows.length - 1,
                      ),
                  ],
                ),
        ),
        const SizedBox(height: 11),
        _ReportSection(
          sectionKey: const Key('inventory-report-assets'),
          icon: Icons.devices_other_outlined,
          title: 'Snapshot Unit Aset',
          subtitle:
              '${report.summary.assetUnits} unit aktif · ${report.summary.unitsNeedingAttention} perhatian',
          action: IconButton(
            tooltip: 'Buka unit aset',
            onPressed: () => context.push('/unit-aset'),
            icon: const Icon(Icons.open_in_new_rounded, size: 19),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Wrap(
                spacing: 7,
                runSpacing: 7,
                children: report.unitDistribution
                    .map((item) => _UnitStatus(item: item))
                    .toList(),
              ),
              const SizedBox(height: 10),
              if (report.unitsNeedingAttention.isEmpty)
                const _EmptyLine('Tidak ada unit aset yang perlu perhatian.')
              else
                for (
                  var index = 0;
                  index < report.unitsNeedingAttention.length;
                  index++
                )
                  _UnitAttentionCard(
                    item: report.unitsNeedingAttention[index],
                    showDivider:
                        index < report.unitsNeedingAttention.length - 1,
                  ),
            ],
          ),
        ),
        const SizedBox(height: 11),
        _ReportSection(
          sectionKey: const Key('inventory-report-movements'),
          icon: Icons.swap_vert_rounded,
          title: 'Rincian Mutasi Periode',
          subtitle: report.movements.length > 30
              ? '30 dari ${report.movements.length} transaksi terbaru'
              : '${report.movements.length} transaksi',
          child: report.movements.isEmpty
              ? const _EmptyLine('Belum ada mutasi stok pada periode terpilih.')
              : Column(
                  children: [
                    for (
                      var index = 0;
                      index < report.movements.take(30).length;
                      index++
                    )
                      _MovementCard(
                        item: report.movements[index],
                        showDivider:
                            index < report.movements.take(30).length - 1,
                      ),
                  ],
                ),
        ),
      ],
    ),
  );

  Future<void> _filter(InventoryMonthlyReport report) async {
    final result = await showModalBottomSheet<InventoryReportFilter>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _FilterSheet(report: report),
    );
    if (result == null || !mounted) return;
    await ref
        .read(inventoryMonthlyReportControllerProvider.notifier)
        .apply(result);
  }

  Future<void> _document(
    _DocumentAction action,
    InventoryMonthlyReport report,
  ) async {
    setState(() => _exporting = true);
    try {
      final service = ref.read(inventoryMonthlyReportDocumentServiceProvider);
      if (action == _DocumentAction.print) {
        await service.printReport(report);
      } else {
        await service.shareReport(report);
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('PDF belum dapat diproses: ${_message(error)}'),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _exporting = false);
    }
  }
}

enum _DocumentAction { print, share }

class _ReportHero extends StatelessWidget {
  const _ReportHero({required this.report});
  final InventoryMonthlyReport report;

  @override
  Widget build(BuildContext context) => Container(
    key: const Key('inventory-report-summary'),
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(20),
      boxShadow: [
        BoxShadow(
          color: NusaColors.primary.withValues(alpha: .16),
          blurRadius: 16,
          offset: const Offset(0, 7),
        ),
      ],
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: .12),
                borderRadius: BorderRadius.circular(13),
              ),
              child: const Icon(
                Icons.analytics_rounded,
                color: NusaColors.accent,
              ),
            ),
            const SizedBox(width: 11),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    report.periodLabel,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 17,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  Text(
                    report.locationLabel,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: Color(0xFFCADBED),
                      fontSize: 10,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
        const SizedBox(height: 14),
        Row(
          children: [
            _HeroStat(value: report.summary.stockRows, label: 'Baris stok'),
            _HeroStat(value: report.summary.movements, label: 'Mutasi'),
            _HeroStat(
              value: report.summary.stockNeedsAttention,
              label: 'Stok perhatian',
            ),
            _HeroStat(value: report.summary.assetUnits, label: 'Unit aset'),
            _HeroStat(
              value: report.summary.unitsNeedingAttention,
              label: 'Unit perhatian',
            ),
          ],
        ),
        const SizedBox(height: 9),
        Text(
          '${report.summary.acquiredUnits} unit diperoleh pada periode ini · '
          '${report.summary.outOfStock} stok habis',
          style: const TextStyle(color: Color(0xFFCADBED), fontSize: 9),
        ),
      ],
    ),
  );
}

class _HeroStat extends StatelessWidget {
  const _HeroStat({required this.value, required this.label});
  final int value;
  final String label;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(
      children: [
        Text(
          '$value',
          style: const TextStyle(
            color: Colors.white,
            fontSize: 16,
            fontWeight: FontWeight.w800,
          ),
        ),
        Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(color: Color(0xFFCADBED), fontSize: 8),
        ),
      ],
    ),
  );
}

class _UnrecordedWarning extends StatelessWidget {
  const _UnrecordedWarning({required this.report});
  final InventoryMonthlyReport report;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(13),
    decoration: BoxDecoration(
      color: const Color(0xFFFFF8E1),
      borderRadius: BorderRadius.circular(16),
      border: const Border(
        left: BorderSide(color: NusaColors.accent, width: 4),
      ),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          '${report.summary.unrecordedStock} barang belum memiliki saldo awal.',
          style: const TextStyle(
            color: Color(0xFF713F12),
            fontSize: 11,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 5),
        Text(
          report.unrecordedStock.map((item) => item.name).join(', '),
          style: const TextStyle(color: Color(0xFF713F12), fontSize: 10),
        ),
      ],
    ),
  );
}

class _SignatoriesCard extends StatelessWidget {
  const _SignatoriesCard({required this.items});
  final List<InventoryReportSignatory> items;

  @override
  Widget build(BuildContext context) => Card(
    margin: EdgeInsets.zero,
    child: ExpansionTile(
      key: const Key('inventory-report-signatories'),
      tilePadding: const EdgeInsets.symmetric(horizontal: 14),
      childrenPadding: const EdgeInsets.fromLTRB(14, 0, 14, 13),
      leading: const Icon(Icons.draw_outlined, color: NusaColors.primary),
      title: const Text(
        'Penandatangan Laporan',
        style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800),
      ),
      subtitle: const Text(
        'Mengikuti role akun pegawai aktif',
        style: TextStyle(fontSize: 9),
      ),
      children: [
        for (var index = 0; index < items.length; index++)
          _SignatureRow(
            item: items[index],
            showDivider: index < items.length - 1,
          ),
      ],
    ),
  );
}

class _SignatureRow extends StatelessWidget {
  const _SignatureRow({required this.item, required this.showDivider});
  final InventoryReportSignatory item;
  final bool showDivider;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(vertical: 8),
    decoration: BoxDecoration(
      border: showDivider
          ? const Border(bottom: BorderSide(color: NusaColors.outline))
          : null,
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 3,
          height: 34,
          decoration: BoxDecoration(
            color: NusaColors.accent,
            borderRadius: BorderRadius.circular(3),
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                item.position,
                style: const TextStyle(
                  fontSize: 9,
                  color: NusaColors.textSecondary,
                ),
              ),
              Text(
                item.name,
                style: const TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w800,
                ),
              ),
              Text(
                'NIP ${item.nip ?? '-'}',
                style: const TextStyle(
                  fontSize: 9,
                  color: NusaColors.textSecondary,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _ReportSection extends StatelessWidget {
  const _ReportSection({
    required this.sectionKey,
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.child,
    this.action,
    this.initiallyExpanded = false,
  });
  final Key sectionKey;
  final IconData icon;
  final String title;
  final String subtitle;
  final Widget child;
  final Widget? action;
  final bool initiallyExpanded;

  @override
  Widget build(BuildContext context) => Card(
    key: sectionKey,
    margin: EdgeInsets.zero,
    child: ExpansionTile(
      initiallyExpanded: initiallyExpanded,
      tilePadding: const EdgeInsets.only(left: 14, right: 6),
      childrenPadding: const EdgeInsets.fromLTRB(14, 0, 14, 14),
      leading: Icon(icon, color: NusaColors.primary),
      title: Text(
        title,
        style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w800),
      ),
      subtitle: Text(subtitle, style: const TextStyle(fontSize: 9)),
      children: [
        if (action != null)
          Align(alignment: Alignment.centerRight, child: action),
        child,
      ],
    ),
  );
}

class _ServiceSummary extends StatelessWidget {
  const _ServiceSummary({required this.summary});
  final InventoryEmployeeServiceSummary summary;

  @override
  Widget build(BuildContext context) => Wrap(
    spacing: 7,
    runSpacing: 7,
    children: [
      _SmallStat(label: 'Total', value: summary.services),
      _SmallStat(label: 'Pegawai', value: summary.employees),
      _SmallStat(label: 'Aset', value: summary.assetLoans),
      _SmallStat(label: 'Habis pakai', value: summary.consumables),
      _SmallStat(label: 'Aktif', value: summary.activeLoans),
    ],
  );
}

class _SmallStat extends StatelessWidget {
  const _SmallStat({required this.label, required this.value});
  final String label;
  final int value;

  @override
  Widget build(BuildContext context) => Container(
    width: 87,
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 8),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(11),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          '$value',
          style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w800),
        ),
        Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(fontSize: 8, color: NusaColors.textSecondary),
        ),
      ],
    ),
  );
}

class _ServiceCard extends StatelessWidget {
  const _ServiceCard({required this.item, required this.showDivider});
  final InventoryEmployeeService item;
  final bool showDivider;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(vertical: 10),
    decoration: BoxDecoration(
      border: showDivider
          ? const Border(bottom: BorderSide(color: NusaColors.outline))
          : null,
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    item.employee.name,
                    style: const TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  Text(
                    '${item.number} · ${item.dateLabel}',
                    style: const TextStyle(
                      fontSize: 9,
                      color: NusaColors.textSecondary,
                    ),
                  ),
                ],
              ),
            ),
            _Badge(label: item.statusLabel, color: _statusColor(item.status)),
          ],
        ),
        const SizedBox(height: 7),
        for (final goods in item.items)
          Padding(
            padding: const EdgeInsets.only(bottom: 3),
            child: Text(
              '${goods.goods} · ${inventoryReportNumber(goods.quantity)} ${goods.unit}'
              '${goods.inventoryCode == null ? '' : ' · ${goods.inventoryCode}'}',
              style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w600),
            ),
          ),
        Text(
          'Sumber: ${item.source}',
          style: const TextStyle(fontSize: 9, color: NusaColors.textSecondary),
        ),
      ],
    ),
  );
}

class _StockCard extends StatelessWidget {
  const _StockCard({required this.item, required this.showDivider});
  final InventoryStockRecap item;
  final bool showDivider;

  @override
  Widget build(BuildContext context) {
    final color = _stockColor(item.status, context);
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 10),
      decoration: BoxDecoration(
        border: showDivider
            ? const Border(bottom: BorderSide(color: NusaColors.outline))
            : null,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.goods.name,
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    Text(
                      '${item.goods.code} · ${item.location.name} · ${item.movementCount} mutasi',
                      style: const TextStyle(
                        fontSize: 9,
                        color: NusaColors.textSecondary,
                      ),
                    ),
                  ],
                ),
              ),
              _Badge(label: item.statusLabel, color: color),
            ],
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              _Quantity(
                label: 'Awal',
                value: item.opening,
                unit: item.goods.unit,
              ),
              _Quantity(
                label: 'Masuk',
                value: item.incoming,
                unit: item.goods.unit,
              ),
              _Quantity(
                label: 'Keluar',
                value: item.outgoing,
                unit: item.goods.unit,
              ),
              _Quantity(
                label: 'Akhir',
                value: item.closing,
                unit: item.goods.unit,
                color: color,
              ),
            ],
          ),
          if (item.adjustment != 0) ...[
            const SizedBox(height: 5),
            Text(
              'Penyesuaian ${item.adjustment > 0 ? '+' : ''}${inventoryReportNumber(item.adjustment)} ${item.goods.unit}',
              style: const TextStyle(
                fontSize: 9,
                color: NusaColors.textSecondary,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _Quantity extends StatelessWidget {
  const _Quantity({
    required this.label,
    required this.value,
    required this.unit,
    this.color = NusaColors.textPrimary,
  });
  final String label;
  final double value;
  final String unit;
  final Color color;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: const TextStyle(fontSize: 8, color: NusaColors.textSecondary),
        ),
        Text(
          '${inventoryReportNumber(value)} $unit',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            color: color,
            fontSize: 9,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    ),
  );
}

class _UnitStatus extends StatelessWidget {
  const _UnitStatus({required this.item});
  final InventoryUnitDistribution item;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 7),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(11),
    ),
    child: Text(
      '${item.label} ${item.count}',
      style: const TextStyle(fontSize: 9, fontWeight: FontWeight.w700),
    ),
  );
}

class _UnitAttentionCard extends StatelessWidget {
  const _UnitAttentionCard({required this.item, required this.showDivider});
  final InventoryUnitAttention item;
  final bool showDivider;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(vertical: 9),
    decoration: BoxDecoration(
      border: showDivider
          ? const Border(bottom: BorderSide(color: NusaColors.outline))
          : null,
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Icon(
          Icons.build_circle_outlined,
          color: Color(0xFFD88B00),
          size: 22,
        ),
        const SizedBox(width: 8),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                item.goods,
                style: const TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w800,
                ),
              ),
              Text(
                '${item.inventoryCode} · ${item.location}',
                style: const TextStyle(
                  fontSize: 9,
                  color: NusaColors.textSecondary,
                ),
              ),
              Text(
                '${item.conditionLabel} · ${item.statusLabel}',
                style: const TextStyle(fontSize: 9, color: Color(0xFFD88B00)),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _MovementCard extends StatelessWidget {
  const _MovementCard({required this.item, required this.showDivider});
  final InventoryReportMovement item;
  final bool showDivider;

  @override
  Widget build(BuildContext context) {
    final color = item.change > 0
        ? NusaColors.success
        : const Color(0xFFD88B00);
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 9),
      decoration: BoxDecoration(
        border: showDivider
            ? const Border(bottom: BorderSide(color: NusaColors.outline))
            : null,
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            item.change > 0
                ? Icons.south_west_rounded
                : Icons.north_east_rounded,
            size: 20,
            color: color,
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.goods,
                  style: const TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                Text(
                  '${item.dateLabel} · ${item.location}',
                  style: const TextStyle(
                    fontSize: 9,
                    color: NusaColors.textSecondary,
                  ),
                ),
                Text(
                  '${item.categoryLabel} · ${item.reference ?? 'Tanpa referensi'}',
                  style: const TextStyle(
                    fontSize: 9,
                    color: NusaColors.textSecondary,
                  ),
                ),
              ],
            ),
          ),
          Text(
            '${item.change > 0 ? '+' : ''}${inventoryReportNumber(item.change)} ${item.unit}',
            style: TextStyle(
              color: color,
              fontSize: 10,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

class _Badge extends StatelessWidget {
  const _Badge({required this.label, required this.color});
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
    decoration: BoxDecoration(
      color: color.withValues(alpha: .1),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(
      label,
      style: TextStyle(color: color, fontSize: 8, fontWeight: FontWeight.w800),
    ),
  );
}

class _FilterSheet extends StatefulWidget {
  const _FilterSheet({required this.report});
  final InventoryMonthlyReport report;

  @override
  State<_FilterSheet> createState() => _FilterSheetState();
}

class _FilterSheetState extends State<_FilterSheet> {
  late int _month;
  late int _year;
  late int _locationId;

  @override
  void initState() {
    super.initState();
    final parts = widget.report.period.split('-');
    final now = DateTime.now();
    _year = parts.isNotEmpty ? int.tryParse(parts.first) ?? now.year : now.year;
    _month = parts.length > 1 ? int.tryParse(parts[1]) ?? now.month : now.month;
    _locationId = widget.report.locationId ?? 0;
  }

  @override
  Widget build(BuildContext context) {
    final currentYear = DateTime.now().year;
    return Padding(
      padding: EdgeInsets.fromLTRB(
        16,
        12,
        16,
        MediaQuery.viewInsetsOf(context).bottom + 18,
      ),
      child: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text(
              'Filter Laporan',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: NusaDropdownField<int>(
                    fieldKey: const Key('inventory-report-month'),
                    value: _month,
                    options: [
                      for (var index = 0; index < _months.length; index++)
                        NusaDropdownOption(
                          value: index + 1,
                          label: _months[index],
                        ),
                    ],
                    decoration: const InputDecoration(labelText: 'Bulan'),
                    onChanged: (value) =>
                        setState(() => _month = value ?? _month),
                  ),
                ),
                const SizedBox(width: 9),
                Expanded(
                  child: NusaDropdownField<int>(
                    fieldKey: const Key('inventory-report-year'),
                    value: _year,
                    options: [
                      for (var year = 2000; year <= currentYear + 1; year++)
                        NusaDropdownOption(value: year, label: '$year'),
                    ],
                    decoration: const InputDecoration(labelText: 'Tahun'),
                    onChanged: (value) =>
                        setState(() => _year = value ?? _year),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            NusaDropdownField<int>(
              fieldKey: const Key('inventory-report-location'),
              value: _locationId,
              options: [
                const NusaDropdownOption(value: 0, label: 'Semua lokasi'),
                ...widget.report.locations.map(
                  (item) =>
                      NusaDropdownOption(value: item.id, label: item.label),
                ),
              ],
              decoration: const InputDecoration(labelText: 'Lokasi barang'),
              onChanged: (value) => setState(() => _locationId = value ?? 0),
            ),
            const SizedBox(height: 16),
            FilledButton.icon(
              key: const Key('apply-inventory-report-filter'),
              onPressed: () => Navigator.pop(
                context,
                InventoryReportFilter(
                  period:
                      '${_year.toString().padLeft(4, '0')}-${_month.toString().padLeft(2, '0')}',
                  locationId: _locationId == 0 ? null : _locationId,
                ),
              ),
              icon: const Icon(Icons.check_rounded),
              label: const Text('Terapkan Filter'),
            ),
          ],
        ),
      ),
    );
  }
}

class _EmptyLine extends StatelessWidget {
  const _EmptyLine(this.message);
  final String message;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 8),
    child: Text(
      message,
      textAlign: TextAlign.center,
      style: const TextStyle(fontSize: 10, color: NusaColors.textSecondary),
    ),
  );
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});
  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.cloud_off_rounded, size: 48),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 14),
          FilledButton.tonalIcon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Coba lagi'),
          ),
        ],
      ),
    ),
  );
}

Color _stockColor(String status, BuildContext context) => switch (status) {
  'habis' => Theme.of(context).colorScheme.error,
  'menipis' => const Color(0xFFD88B00),
  _ => NusaColors.success,
};
Color _statusColor(String status) => switch (status) {
  'selesai' => NusaColors.success,
  _ => const Color(0xFFD88B00),
};
String _message(Object error) => error is AppException
    ? error.message
    : 'Laporan inventaris belum dapat dimuat.';

const _months = [
  'Januari',
  'Februari',
  'Maret',
  'April',
  'Mei',
  'Juni',
  'Juli',
  'Agustus',
  'September',
  'Oktober',
  'November',
  'Desember',
];
