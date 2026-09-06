import 'package:flutter/material.dart';
import 'package:nusa/features/goods_loan_recap/domain/goods_loan_recap.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class GoodsLoanRecapFilterSheet extends StatefulWidget {
  const GoodsLoanRecapFilterSheet({required this.page, super.key});
  final GoodsLoanRecapPage page;

  @override
  State<GoodsLoanRecapFilterSheet> createState() =>
      _GoodsLoanRecapFilterSheetState();
}

class _GoodsLoanRecapFilterSheetState extends State<GoodsLoanRecapFilterSheet> {
  late String _monitoringStatus;
  late String _borrowerType;
  late String _borrower;
  late int _goodsId;
  DateTime? _start;
  DateTime? _end;

  List<GoodsLoanRecapBorrower> get _borrowers => widget.page.borrowers
      .where((item) => _borrowerType == 'semua' || item.type == _borrowerType)
      .toList(growable: false);

  @override
  void initState() {
    super.initState();
    final filter = widget.page.filter;
    _monitoringStatus = filter.monitoringStatus;
    _borrowerType = filter.borrowerType;
    _borrower = filter.borrower;
    _goodsId = filter.goodsId ?? 0;
    _start = filter.startDate;
    _end = filter.endDate;
  }

  @override
  Widget build(BuildContext context) => SafeArea(
    child: SizedBox(
      height: (MediaQuery.sizeOf(context).height * .92).clamp(560, 880),
      child: Column(
        children: [
          const SizedBox(height: 10),
          Container(
            width: 42,
            height: 4,
            decoration: BoxDecoration(
              color: Theme.of(context).dividerColor,
              borderRadius: BorderRadius.circular(4),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 8, 8),
            child: Row(
              children: [
                const Expanded(
                  child: Text(
                    'Filter Rekap Peminjaman',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
                  ),
                ),
                IconButton(
                  tooltip: 'Tutup',
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: ListView(
              key: const Key('goods-loan-recap-filter-scroll'),
              padding: const EdgeInsets.all(16),
              children: [
                NusaDropdownField<String>(
                  fieldKey: const Key('goods-loan-recap-monitoring-filter'),
                  value: _monitoringStatus,
                  options: widget.page.monitoringStatuses
                      .map(
                        (item) => NusaDropdownOption(
                          value: item.value,
                          label: item.label,
                        ),
                      )
                      .toList(),
                  decoration: const InputDecoration(
                    labelText: 'Pemantauan',
                    prefixIcon: Icon(Icons.monitor_heart_outlined),
                  ),
                  onChanged: (value) =>
                      setState(() => _monitoringStatus = value ?? 'aktif'),
                ),
                const SizedBox(height: 12),
                NusaDropdownField<String>(
                  fieldKey: const Key('goods-loan-recap-borrower-type-filter'),
                  value: _borrowerType,
                  options: widget.page.borrowerTypes
                      .map(
                        (item) => NusaDropdownOption(
                          value: item.value,
                          label: item.label,
                        ),
                      )
                      .toList(),
                  decoration: const InputDecoration(
                    labelText: 'Jenis peminjam',
                    prefixIcon: Icon(Icons.groups_outlined),
                  ),
                  onChanged: (value) {
                    final next = value ?? 'semua';
                    setState(() {
                      _borrowerType = next;
                      if (!_borrowers.any((item) => item.value == _borrower)) {
                        _borrower = '';
                      }
                    });
                  },
                ),
                const SizedBox(height: 12),
                NusaDropdownField<String>(
                  fieldKey: const Key('goods-loan-recap-borrower-filter'),
                  value: _borrower,
                  options: [
                    const NusaDropdownOption(
                      value: '',
                      label: 'Semua peminjam',
                    ),
                    ..._borrowers.map(
                      (item) => NusaDropdownOption(
                        value: item.value,
                        label: item.label,
                      ),
                    ),
                  ],
                  decoration: const InputDecoration(
                    labelText: 'Riwayat peminjam',
                    prefixIcon: Icon(Icons.person_search_outlined),
                  ),
                  onChanged: (value) => setState(() => _borrower = value ?? ''),
                ),
                const SizedBox(height: 12),
                NusaDropdownField<int>(
                  fieldKey: const Key('goods-loan-recap-goods-filter'),
                  value: _goodsId,
                  options: [
                    const NusaDropdownOption(value: 0, label: 'Semua barang'),
                    ...widget.page.goods.map(
                      (item) =>
                          NusaDropdownOption(value: item.id, label: item.label),
                    ),
                  ],
                  decoration: const InputDecoration(
                    labelText: 'Barang',
                    prefixIcon: Icon(Icons.inventory_2_outlined),
                  ),
                  onChanged: (value) => setState(() => _goodsId = value ?? 0),
                ),
                const SizedBox(height: 12),
                OutlinedButton.icon(
                  key: const Key('goods-loan-recap-date-filter'),
                  onPressed: _pickRange,
                  icon: const Icon(Icons.date_range_outlined),
                  label: Text(
                    _start == null || _end == null
                        ? 'Semua tanggal peminjaman'
                        : '${_date(_start!)} – ${_date(_end!)}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                if (_start != null || _end != null)
                  TextButton(
                    onPressed: () => setState(() {
                      _start = null;
                      _end = null;
                    }),
                    child: const Text('Hapus rentang tanggal'),
                  ),
                const SizedBox(height: 16),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        key: const Key('reset-goods-loan-recap-filter'),
                        onPressed: () => Navigator.pop(
                          context,
                          const GoodsLoanRecapFilter(),
                        ),
                        child: const Text('Reset'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: FilledButton.icon(
                        key: const Key('apply-goods-loan-recap-filter'),
                        onPressed: _apply,
                        icon: const Icon(Icons.check_rounded),
                        label: const Text('Terapkan'),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    ),
  );

  void _apply() => Navigator.pop(
    context,
    GoodsLoanRecapFilter(
      query: widget.page.filter.query,
      monitoringStatus: _monitoringStatus,
      borrowerType: _borrowerType,
      borrower: _borrower,
      goodsId: _goodsId == 0 ? null : _goodsId,
      startDate: _start,
      endDate: _end,
    ),
  );

  Future<void> _pickRange() async {
    final result = await showDateRangePicker(
      context: context,
      firstDate: DateTime(2000),
      lastDate: DateTime.now().add(const Duration(days: 3650)),
      initialDateRange: _start != null && _end != null
          ? DateTimeRange(start: _start!, end: _end!)
          : null,
    );
    if (result != null) {
      setState(() {
        _start = result.start;
        _end = result.end;
      });
    }
  }
}

String _date(DateTime value) =>
    '${value.day.toString().padLeft(2, '0')}/${value.month.toString().padLeft(2, '0')}/${value.year}';
