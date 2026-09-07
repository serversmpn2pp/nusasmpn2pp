import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/goods_catalog/application/goods_catalog_controller.dart';
import 'package:nusa/features/goods_catalog/domain/goods_catalog.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class GoodsCatalogDetailView extends ConsumerStatefulWidget {
  const GoodsCatalogDetailView({required this.goodsId, super.key});
  final int goodsId;

  @override
  ConsumerState<GoodsCatalogDetailView> createState() =>
      _GoodsCatalogDetailViewState();
}

class _GoodsCatalogDetailViewState
    extends ConsumerState<GoodsCatalogDetailView> {
  late Future<GoodsCatalogDetail> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<GoodsCatalogDetail> _load() =>
      ref.read(goodsCatalogActionsProvider).detail(widget.goodsId);

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Detail Katalog'),
      actions: [
        IconButton(
          tooltip: 'Perbarui',
          onPressed: () => setState(() => _future = _load()),
          icon: const Icon(Icons.refresh_rounded),
        ),
      ],
    ),
    body: SafeArea(
      top: false,
      child: FutureBuilder<GoodsCatalogDetail>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return _DetailError(
              message: goodsCatalogMessage(snapshot.error!),
              onRetry: () => setState(() => _future = _load()),
            );
          }
          return _DetailContent(detail: snapshot.requireData);
        },
      ),
    ),
  );
}

class _DetailContent extends StatelessWidget {
  const _DetailContent({required this.detail});
  final GoodsCatalogDetail detail;

  @override
  Widget build(BuildContext context) {
    final item = detail.item;
    return ListView(
      key: const Key('goods-catalog-detail'),
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
      children: [
        _GoodsHero(item: item),
        const SizedBox(height: 12),
        _InfoCard(item: item),
        if (item.description?.trim().isNotEmpty ?? false) ...[
          const SizedBox(height: 12),
          _SectionCard(
            icon: Icons.notes_rounded,
            title: 'Deskripsi',
            child: Text(
              item.description!.trim(),
              style: const TextStyle(fontSize: 12, height: 1.5),
            ),
          ),
        ],
        const SizedBox(height: 12),
        _SectionCard(
          icon: Icons.location_on_outlined,
          title: 'Ketersediaan per Lokasi',
          child: item.locations.isEmpty
              ? const _EmptyLine('Belum ada lokasi penyimpanan tercatat.')
              : Column(
                  children: [
                    for (var index = 0; index < item.locations.length; index++)
                      _LocationRow(
                        location: item.locations[index],
                        unit: item.unit,
                        showDivider: index < item.locations.length - 1,
                      ),
                  ],
                ),
        ),
        if (item.units.isNotEmpty) ...[
          const SizedBox(height: 12),
          _SectionCard(
            icon: Icons.qr_code_2_rounded,
            title: 'Unit Aset (${item.units.length})',
            child: Column(
              children: [
                for (var index = 0; index < item.units.length; index++)
                  _UnitRow(
                    unit: item.units[index],
                    showDivider: index < item.units.length - 1,
                  ),
              ],
            ),
          ),
        ],
        if (item.activeBorrowers.isNotEmpty) ...[
          const SizedBox(height: 12),
          _SectionCard(
            icon: Icons.person_search_rounded,
            title: 'Peminjam Aktif (${item.activeBorrowers.length})',
            child: Column(
              children: [
                for (
                  var index = 0;
                  index < item.activeBorrowers.length;
                  index++
                )
                  _BorrowerRow(
                    borrower: item.activeBorrowers[index],
                    showDivider: index < item.activeBorrowers.length - 1,
                  ),
              ],
            ),
          ),
        ],
        if (detail.access.canRequest) ...[
          const SizedBox(height: 18),
          NusaPrimaryButton(
            key: const Key('goods-catalog-request'),
            label: item.serviceType == 'peminjaman'
                ? 'Ajukan Peminjaman'
                : 'Ajukan Permintaan',
            onPressed: () =>
                context.push('/pengajuan-saya/tambah?barang_id=${item.id}'),
          ),
          const SizedBox(height: 6),
          const Text(
            'Barang ini akan langsung dipilih pada formulir pengajuan.',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 10, color: NusaColors.textSecondary),
          ),
        ],
      ],
    );
  }
}

class _GoodsHero extends StatelessWidget {
  const _GoodsHero({required this.item});
  final GoodsCatalogItem item;

  @override
  Widget build(BuildContext context) {
    final color = item.available
        ? NusaColors.success
        : Theme.of(context).colorScheme.error;
    return Container(
      padding: const EdgeInsets.all(18),
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
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 52,
            height: 52,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: .12),
              borderRadius: BorderRadius.circular(15),
            ),
            child: Icon(
              item.isAsset
                  ? Icons.devices_other_rounded
                  : Icons.inventory_2_rounded,
              color: NusaColors.accent,
              size: 29,
            ),
          ),
          const SizedBox(width: 13),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.name,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  '${item.code} · ${item.category}',
                  style: const TextStyle(
                    color: Color(0xFFCADBED),
                    fontSize: 11,
                  ),
                ),
                const SizedBox(height: 10),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 9,
                    vertical: 5,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    item.available
                        ? '${item.availableLabel} tersedia'
                        : 'Tidak tersedia',
                    style: TextStyle(
                      color: color,
                      fontSize: 10,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _InfoCard extends StatelessWidget {
  const _InfoCard({required this.item});
  final GoodsCatalogItem item;

  @override
  Widget build(BuildContext context) => _SectionCard(
    icon: Icons.info_outline_rounded,
    title: 'Informasi Barang',
    child: Column(
      children: [
        _InfoRow(label: 'Jenis', value: item.typeLabel),
        _InfoRow(label: 'Pengelolaan', value: item.managementTypeLabel),
        _InfoRow(
          label: 'Layanan',
          value: item.serviceType == 'peminjaman' ? 'Peminjaman' : 'Permintaan',
        ),
        if (item.isAsset)
          _InfoRow(
            label: 'Unit aset',
            value: '${item.unitCount} total · ${item.borrowedCount} dipinjam',
            last: true,
          )
        else
          _InfoRow(
            label: 'Stok tersedia',
            value: item.availableLabel,
            last: true,
          ),
      ],
    ),
  );
}

class _SectionCard extends StatelessWidget {
  const _SectionCard({
    required this.icon,
    required this.title,
    required this.child,
  });
  final IconData icon;
  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) => Card(
    margin: EdgeInsets.zero,
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, size: 19, color: NusaColors.primary),
              const SizedBox(width: 7),
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 11),
          child,
        ],
      ),
    ),
  );
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value, this.last = false});
  final String label;
  final String value;
  final bool last;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(vertical: 8),
    decoration: BoxDecoration(
      border: last
          ? null
          : const Border(bottom: BorderSide(color: NusaColors.outline)),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Text(
            label,
            style: const TextStyle(
              fontSize: 11,
              color: NusaColors.textSecondary,
            ),
          ),
        ),
        const SizedBox(width: 12),
        Flexible(
          child: Text(
            value,
            textAlign: TextAlign.right,
            style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700),
          ),
        ),
      ],
    ),
  );
}

class _LocationRow extends StatelessWidget {
  const _LocationRow({
    required this.location,
    required this.unit,
    required this.showDivider,
  });
  final GoodsCatalogLocation location;
  final String unit;
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
      children: [
        const Icon(
          Icons.place_outlined,
          size: 17,
          color: NusaColors.textSecondary,
        ),
        const SizedBox(width: 7),
        Expanded(
          child: Text(
            location.name,
            style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700),
          ),
        ),
        Text(
          '${goodsCatalogQuantity(location.available)} $unit tersedia',
          style: const TextStyle(
            color: NusaColors.success,
            fontSize: 10,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    ),
  );
}

class _UnitRow extends StatelessWidget {
  const _UnitRow({required this.unit, required this.showDivider});
  final GoodsCatalogUnit unit;
  final bool showDivider;

  @override
  Widget build(BuildContext context) {
    final available = unit.status == 'tersedia';
    final color = available ? NusaColors.success : const Color(0xFFD88B00);
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
          Icon(Icons.qr_code_rounded, size: 18, color: color),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  unit.inventoryCode,
                  style: const TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  '${unit.location} · ${unit.condition}',
                  style: const TextStyle(
                    fontSize: 10,
                    color: NusaColors.textSecondary,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Text(
            unit.statusLabel,
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

class _BorrowerRow extends StatelessWidget {
  const _BorrowerRow({required this.borrower, required this.showDivider});
  final GoodsCatalogBorrower borrower;
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
        const CircleAvatar(
          radius: 17,
          backgroundColor: NusaColors.surfaceBlue,
          child: Icon(Icons.person_outline_rounded, size: 18),
        ),
        const SizedBox(width: 9),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                borrower.name,
                style: const TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                '${borrower.unit} · ${borrower.inventoryCode}',
                style: const TextStyle(
                  fontSize: 10,
                  color: NusaColors.textSecondary,
                ),
              ),
              if (borrower.plannedReturnLabel?.isNotEmpty ?? false)
                Text(
                  'Rencana kembali ${borrower.plannedReturnLabel}',
                  style: const TextStyle(
                    fontSize: 10,
                    color: Color(0xFFD88B00),
                  ),
                ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _EmptyLine extends StatelessWidget {
  const _EmptyLine(this.message);
  final String message;

  @override
  Widget build(BuildContext context) => Text(
    message,
    style: const TextStyle(fontSize: 11, color: NusaColors.textSecondary),
  );
}

class _DetailError extends StatelessWidget {
  const _DetailError({required this.message, required this.onRetry});
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
