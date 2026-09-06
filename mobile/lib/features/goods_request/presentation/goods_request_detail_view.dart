import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/goods_request/application/goods_request_controller.dart';
import 'package:nusa/features/goods_request/domain/goods_request.dart';
import 'package:nusa/features/goods_request/presentation/goods_request_view.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class GoodsRequestDetailView extends ConsumerStatefulWidget {
  const GoodsRequestDetailView({required this.requestId, super.key});
  final int requestId;
  @override
  ConsumerState<GoodsRequestDetailView> createState() =>
      _GoodsRequestDetailViewState();
}

class _GoodsRequestDetailViewState
    extends ConsumerState<GoodsRequestDetailView> {
  late Future<GoodsRequestDetail> _future;
  bool _processing = false;
  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<GoodsRequestDetail> _load() =>
      ref.read(goodsRequestActionsProvider).detail(widget.requestId);

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Periksa Pengajuan'),
      actions: [
        IconButton(
          tooltip: 'Perbarui',
          onPressed: _processing
              ? null
              : () => setState(() => _future = _load()),
          icon: const Icon(Icons.refresh_rounded),
        ),
      ],
    ),
    body: SafeArea(
      top: false,
      child: FutureBuilder<GoodsRequestDetail>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return _DetailError(
              message: goodsRequestMessage(snapshot.error!),
              onRetry: () => setState(() => _future = _load()),
            );
          }
          return _content(snapshot.requireData);
        },
      ),
    ),
  );

  Widget _content(GoodsRequestDetail detail) {
    final item = detail.request;
    final color = requestStatusColor(context, item.status);
    return Stack(
      children: [
        RefreshIndicator(
          onRefresh: () async {
            final value = await _load();
            if (mounted) setState(() => _future = Future.value(value));
          },
          child: ListView(
            key: const Key('goods-request-detail-scroll'),
            physics: const AlwaysScrollableScrollPhysics(),
            padding: EdgeInsets.fromLTRB(
              16,
              8,
              16,
              detail.canFulfill || detail.canReject ? 94 : 24,
            ),
            children: [
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: [NusaColors.primary, NusaColors.primaryDark],
                  ),
                  borderRadius: BorderRadius.circular(18),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.number,
                      style: const TextStyle(
                        color: NusaColors.accent,
                        fontSize: 12,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      item.employeeName,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 20,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    Text(
                      '${item.employeeType}${item.nip?.isNotEmpty == true ? ' · NIP ${item.nip}' : ''}',
                      style: const TextStyle(
                        color: Color(0xFFCADBED),
                        fontSize: 11,
                      ),
                    ),
                    const SizedBox(height: 12),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        _Pill(
                          icon: item.type == 'peminjaman'
                              ? Icons.devices_other_outlined
                              : Icons.inventory_2_outlined,
                          label: item.typeLabel,
                        ),
                        _Pill(
                          icon: Icons.flag_outlined,
                          label: item.statusLabel,
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              _SectionCard(
                title: 'Rincian Barang',
                children: [
                  _InfoRow(label: 'Barang', value: item.goodsName),
                  _InfoRow(label: 'Kode', value: item.goodsCode),
                  _InfoRow(label: 'Kategori', value: item.category ?? '-'),
                  _InfoRow(
                    label: 'Jumlah',
                    value: '${goodsRequestNumber(item.quantity)} ${item.unit}',
                  ),
                  _InfoRow(
                    label: 'Status',
                    value: item.statusLabel,
                    color: color,
                  ),
                ],
              ),
              const SizedBox(height: 12),
              _SectionCard(
                title: 'Waktu & Kebutuhan',
                children: [
                  _InfoRow(
                    label: 'Tanggal pengajuan',
                    value: item.submissionDateLabel,
                  ),
                  _InfoRow(
                    label: 'Tanggal dibutuhkan',
                    value: item.requiredDateLabel,
                  ),
                  if (item.type == 'peminjaman')
                    _InfoRow(
                      label: 'Rencana kembali',
                      value: item.plannedReturnLabel ?? '-',
                    ),
                  _InfoRow(
                    label: 'Tujuan penggunaan',
                    value: item.purpose ?? '-',
                  ),
                ],
              ),
              if (!item.pending) ...[
                const SizedBox(height: 12),
                _SectionCard(
                  title: 'Hasil Pemeriksaan',
                  children: [
                    _InfoRow(
                      label: 'Diproses oleh',
                      value: item.processedBy ?? 'Sistem',
                    ),
                    _InfoRow(
                      label: 'Waktu proses',
                      value: item.processedAtLabel ?? '-',
                    ),
                    _InfoRow(
                      label: 'Catatan petugas',
                      value: item.officerNotes ?? '-',
                    ),
                  ],
                ),
              ],
              if (item.loanId != null) ...[
                const SizedBox(height: 12),
                OutlinedButton.icon(
                  key: const Key('open-request-loan'),
                  onPressed: () =>
                      context.push('/peminjaman-barang/${item.loanId}'),
                  icon: const Icon(Icons.receipt_long_outlined),
                  label: Text('Lihat transaksi ${item.loanNumber ?? ''}'),
                ),
              ],
            ],
          ),
        ),
        if (detail.canFulfill || detail.canReject)
          Positioned(
            left: 16,
            right: 16,
            bottom: 16,
            child: Row(
              children: [
                if (detail.canReject)
                  Expanded(
                    child: OutlinedButton.icon(
                      key: const Key('reject-goods-request'),
                      onPressed: _processing ? null : () => _reject(detail),
                      icon: const Icon(Icons.close_rounded),
                      label: const Text('Tolak'),
                    ),
                  ),
                if (detail.canReject && detail.canFulfill)
                  const SizedBox(width: 10),
                if (detail.canFulfill)
                  Expanded(
                    flex: 2,
                    child: FilledButton.icon(
                      key: const Key('fulfill-goods-request'),
                      onPressed: _processing ? null : () => _fulfill(detail),
                      icon: const Icon(Icons.check_rounded),
                      label: const Text('Penuhi & Serahkan'),
                    ),
                  ),
              ],
            ),
          ),
      ],
    );
  }

  Future<void> _fulfill(GoodsRequestDetail detail) async {
    final value = await showModalBottomSheet<GoodsRequestFulfillValue>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _FulfillSheet(detail: detail),
    );
    if (value == null || !mounted) return;
    await _run(
      () => ref
          .read(goodsRequestActionsProvider)
          .fulfill(widget.requestId, value),
      'Pengajuan dipenuhi dan transaksi barang dicatat.',
    );
  }

  Future<void> _reject(GoodsRequestDetail detail) async {
    final reason = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => const _RejectSheet(),
    );
    if (reason == null || !mounted) return;
    await _run(
      () => ref
          .read(goodsRequestActionsProvider)
          .reject(widget.requestId, reason),
      'Pengajuan barang berhasil ditolak.',
    );
  }

  Future<void> _run(
    Future<GoodsRequestDetail> Function() action,
    String success,
  ) async {
    setState(() => _processing = true);
    try {
      final value = await action();
      ref.invalidate(goodsRequestControllerProvider);
      if (mounted) {
        setState(() => _future = Future.value(value));
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(success)));
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(goodsRequestMessage(error))));
      }
    } finally {
      if (mounted) setState(() => _processing = false);
    }
  }
}

class _FulfillSheet extends StatefulWidget {
  const _FulfillSheet({required this.detail});
  final GoodsRequestDetail detail;
  @override
  State<_FulfillSheet> createState() => _FulfillSheetState();
}

class _FulfillSheetState extends State<_FulfillSheet> {
  final _notes = TextEditingController();
  final Set<int> _unitIds = {};
  int? _locationId;
  String? _error;
  @override
  void dispose() {
    _notes.dispose();
    super.dispose();
  }

  bool get _asset => widget.detail.request.managementType == 'aset_individual';

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.fromLTRB(
      16,
      12,
      16,
      MediaQuery.viewInsetsOf(context).bottom + 18,
    ),
    child: SingleChildScrollView(
      key: const Key('goods-request-fulfill-scroll'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'Penuhi & Serahkan Barang',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 5),
          Text(
            _asset
                ? 'Pilih tepat ${widget.detail.requiredUnits} unit aset.'
                : 'Pilih lokasi asal dengan stok yang mencukupi.',
            style: const TextStyle(
              fontSize: 12,
              color: NusaColors.textSecondary,
            ),
          ),
          const SizedBox(height: 16),
          if (_asset)
            if (widget.detail.units.isEmpty)
              const _AvailabilityWarning(
                text: 'Tidak ada unit aset yang tersedia.',
              )
            else
              ...widget.detail.units.map(
                (unit) => Card(
                  margin: const EdgeInsets.only(bottom: 8),
                  child: CheckboxListTile(
                    key: Key('goods-request-unit-${unit.id}'),
                    value: _unitIds.contains(unit.id),
                    onChanged: (checked) => setState(() {
                      checked == true
                          ? _unitIds.add(unit.id)
                          : _unitIds.remove(unit.id);
                      _error = null;
                    }),
                    title: Text(
                      unit.code,
                      style: const TextStyle(fontWeight: FontWeight.w700),
                    ),
                    subtitle: Text(
                      '${unit.officialNumber ?? 'Nomor aset belum diisi'} · ${unit.location} · ${unit.condition}',
                    ),
                    controlAffinity: ListTileControlAffinity.leading,
                  ),
                ),
              )
          else if (widget.detail.stocks.isEmpty)
            const _AvailabilityWarning(
              text: 'Tidak ada saldo stok yang tersedia.',
            )
          else
            NusaDropdownField<int>(
              fieldKey: const Key('goods-request-stock-location'),
              value: _locationId,
              options: widget.detail.stocks
                  .map(
                    (stock) => NusaDropdownOption<int>(
                      value: stock.locationId,
                      label:
                          '${stock.location} · ${goodsRequestNumber(stock.quantity)} ${stock.unit}',
                      enabled: stock.quantity >= widget.detail.request.quantity,
                    ),
                  )
                  .toList(),
              decoration: const InputDecoration(
                labelText: 'Lokasi asal stok',
                hintText: 'Pilih lokasi',
              ),
              onChanged: (value) => setState(() {
                _locationId = value;
                _error = null;
              }),
            ),
          const SizedBox(height: 14),
          TextField(
            key: const Key('goods-request-officer-notes'),
            controller: _notes,
            maxLines: 3,
            maxLength: 1000,
            decoration: const InputDecoration(
              labelText: 'Catatan petugas',
              hintText: 'Opsional',
            ),
          ),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Text(
                _error!,
                style: TextStyle(
                  color: Theme.of(context).colorScheme.error,
                  fontSize: 12,
                ),
              ),
            ),
          FilledButton.icon(
            key: const Key('save-goods-request-fulfill'),
            onPressed: _submit,
            icon: const Icon(Icons.check_rounded),
            label: const Text('Penuhi dan Catat Transaksi'),
          ),
        ],
      ),
    ),
  );

  void _submit() {
    if (_asset && _unitIds.length != widget.detail.requiredUnits) {
      setState(
        () => _error = 'Pilih tepat ${widget.detail.requiredUnits} unit aset.',
      );
      return;
    }
    if (!_asset && _locationId == null) {
      setState(() => _error = 'Pilih lokasi asal stok.');
      return;
    }
    Navigator.pop(
      context,
      GoodsRequestFulfillValue(
        unitIds: _unitIds.toList(),
        locationId: _locationId,
        notes: _notes.text,
      ),
    );
  }
}

class _RejectSheet extends StatefulWidget {
  const _RejectSheet();
  @override
  State<_RejectSheet> createState() => _RejectSheetState();
}

class _RejectSheetState extends State<_RejectSheet> {
  final _reason = TextEditingController();
  String? _error;
  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.fromLTRB(
      16,
      12,
      16,
      MediaQuery.viewInsetsOf(context).bottom + 18,
    ),
    child: Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Text(
          'Tolak Pengajuan',
          style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 5),
        const Text(
          'Tuliskan alasan yang jelas untuk pemohon.',
          style: TextStyle(fontSize: 12, color: NusaColors.textSecondary),
        ),
        const SizedBox(height: 16),
        TextField(
          key: const Key('goods-request-reject-reason'),
          controller: _reason,
          minLines: 3,
          maxLines: 5,
          maxLength: 1000,
          decoration: InputDecoration(
            labelText: 'Alasan penolakan',
            errorText: _error,
          ),
        ),
        const SizedBox(height: 8),
        FilledButton.icon(
          key: const Key('save-goods-request-reject'),
          style: FilledButton.styleFrom(
            backgroundColor: Theme.of(context).colorScheme.error,
          ),
          onPressed: () {
            final value = _reason.text.trim();
            if (value.length < 5) {
              setState(() => _error = 'Alasan minimal 5 karakter.');
            } else {
              Navigator.pop(context, value);
            }
          },
          icon: const Icon(Icons.close_rounded),
          label: const Text('Tolak Pengajuan'),
        ),
      ],
    ),
  );
}

class _SectionCard extends StatelessWidget {
  const _SectionCard({required this.title, required this.children});
  final String title;
  final List<Widget> children;
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 10),
          ...children,
        ],
      ),
    ),
  );
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value, this.color});
  final String label;
  final String value;
  final Color? color;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 5),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 118,
          child: Text(
            label,
            style: const TextStyle(
              fontSize: 11,
              color: NusaColors.textSecondary,
            ),
          ),
        ),
        Expanded(
          child: Text(
            value,
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: color,
            ),
          ),
        ),
      ],
    ),
  );
}

class _Pill extends StatelessWidget {
  const _Pill({required this.icon, required this.label});
  final IconData icon;
  final String label;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
    decoration: BoxDecoration(
      color: Colors.white.withValues(alpha: .12),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 14, color: Colors.white),
        const SizedBox(width: 5),
        Text(
          label,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 10,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    ),
  );
}

class _AvailabilityWarning extends StatelessWidget {
  const _AvailabilityWarning({required this.text});
  final String text;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: const Color(0xFFFFF6DB),
      borderRadius: BorderRadius.circular(12),
    ),
    child: Text(
      text,
      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
    ),
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
