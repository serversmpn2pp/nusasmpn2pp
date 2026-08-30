import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/late_point_setting/domain/late_point_setting.dart';

class LatePointSettingFormSheet extends StatefulWidget {
  const LatePointSettingFormSheet({required this.setting, super.key});

  final LatePointSetting setting;

  @override
  State<LatePointSettingFormSheet> createState() =>
      _LatePointSettingFormSheetState();
}

class _LatePointSettingFormSheetState extends State<LatePointSettingFormSheet> {
  late final List<_EditableLateRange> _ranges;
  late bool _active;
  String? _error;

  @override
  void initState() {
    super.initState();
    _active = widget.setting.automaticActive;
    _ranges = widget.setting.ranges
        .map(_EditableLateRange.fromDomain)
        .toList(growable: true);
    if (_ranges.isEmpty) {
      _ranges.addAll([
        _EditableLateRange(start: 1, end: 10, points: 0),
        _EditableLateRange(start: 11, points: 15),
      ]);
    }
  }

  @override
  void dispose() {
    for (final range in _ranges) {
      range.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedPadding(
    duration: const Duration(milliseconds: 160),
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: SizedBox(
      height: (MediaQuery.sizeOf(context).height * 0.92).clamp(580.0, 820.0),
      child: Column(
        children: [
          const SizedBox(height: 10),
          Container(
            width: 42,
            height: 4,
            decoration: BoxDecoration(
              color: NusaColors.outline,
              borderRadius: BorderRadius.circular(4),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 13, 8, 9),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Atur Poin Keterlambatan',
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      Text(
                        'Tahun ${widget.setting.academicYear.name}',
                        style: const TextStyle(
                          color: NusaColors.textSecondary,
                          fontSize: 11,
                        ),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  key: const Key('close-late-point-form'),
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: ListView(
              key: const Key('late-point-form-scroll'),
              padding: const EdgeInsets.all(16),
              children: [
                SwitchListTile.adaptive(
                  key: const Key('late-point-form-active'),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 12),
                  shape: RoundedRectangleBorder(
                    side: const BorderSide(color: NusaColors.outline),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  title: const Text('Otomatisasi aktif'),
                  subtitle: const Text(
                    'Gunakan rentang ini saat memproses rekap presensi.',
                  ),
                  value: _active,
                  onChanged: (value) => setState(() => _active = value),
                ),
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.all(11),
                  decoration: BoxDecoration(
                    color: NusaColors.accent.withValues(alpha: 0.13),
                    borderRadius: BorderRadius.circular(13),
                  ),
                  child: const Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Icon(Icons.info_outline_rounded, size: 18),
                      SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          'Gunakan 0 poin sebagai toleransi. Rentang harus menyambung dan rentang terakhir tanpa batas akhir.',
                          style: TextStyle(fontSize: 11.5, height: 1.35),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                for (var index = 0; index < _ranges.length; index++) ...[
                  _RangeEditorCard(
                    index: index,
                    range: _ranges[index],
                    canRemove: _ranges.length > 1,
                    isLast: index == _ranges.length - 1,
                    onRemove: () => _removeRange(index),
                  ),
                  const SizedBox(height: 10),
                ],
                OutlinedButton.icon(
                  key: const Key('add-late-point-range'),
                  onPressed: _ranges.length >= 20 ? null : _addRange,
                  icon: const Icon(Icons.add_rounded),
                  label: const Text('Tambah rentang'),
                ),
                if (_error != null) ...[
                  const SizedBox(height: 10),
                  Text(
                    _error!,
                    key: const Key('late-point-form-error'),
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                      fontSize: 12,
                    ),
                  ),
                ],
                const SizedBox(height: 8),
                const Text(
                  'Laporan yang telah terbentuk tetap menyimpan menit keterlambatan dan total poin lamanya ketika rentang ini diperbarui.',
                  style: TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 10.5,
                    height: 1.35,
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
            child: SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                key: const Key('save-late-point-setting'),
                onPressed: _submit,
                icon: const Icon(Icons.save_outlined),
                label: const Text('Simpan Pengaturan'),
              ),
            ),
          ),
        ],
      ),
    ),
  );

  void _addRange() {
    if (_ranges.length >= 20) return;
    final previous = _ranges.last;
    final previousStart = int.tryParse(previous.startController.text) ?? 1;
    var previousEnd = int.tryParse(previous.endController.text);
    if (previousEnd == null || previousEnd < previousStart) {
      previousEnd = (previousStart + 9).clamp(1, 1439);
      previous.endController.text = '$previousEnd';
    }
    final nextStart = (previousEnd + 1).clamp(1, 1440);
    setState(() {
      _error = null;
      _ranges.add(_EditableLateRange(start: nextStart, points: 0));
    });
  }

  void _removeRange(int index) {
    if (_ranges.length <= 1) return;
    final removedLast = index == _ranges.length - 1;
    setState(() {
      _ranges.removeAt(index).dispose();
      if (removedLast) _ranges.last.endController.clear();
      _error = null;
    });
  }

  void _submit() {
    final values = <LatePointRangeFormValue>[];
    for (var index = 0; index < _ranges.length; index++) {
      final item = _ranges[index];
      final start = int.tryParse(item.startController.text);
      final end = int.tryParse(item.endController.text);
      final points = int.tryParse(item.pointsController.text);
      if (start == null || start < 1 || start > 1440) {
        _setError('Mulai menit pada rentang ${index + 1} harus antara 1–1440.');
        return;
      }
      if (item.endController.text.trim().isNotEmpty &&
          (end == null || end < 1 || end > 1440)) {
        _setError(
          'Sampai menit pada rentang ${index + 1} harus antara 1–1440.',
        );
        return;
      }
      if (points == null || points < 0 || points > 500) {
        _setError('Poin pada rentang ${index + 1} harus antara 0–500.');
        return;
      }
      values.add(
        LatePointRangeFormValue(
          startMinute: start,
          endMinute: end,
          points: points,
        ),
      );
    }

    values.sort((left, right) => left.startMinute.compareTo(right.startMinute));
    if (values.first.startMinute != 1) {
      _setError('Rentang pertama harus dimulai dari menit ke-1.');
      return;
    }
    for (var index = 0; index < values.length; index++) {
      final current = values[index];
      final isLast = index == values.length - 1;
      if (!isLast && current.endMinute == null) {
        _setError('Hanya rentang terakhir yang boleh tanpa batas akhir.');
        return;
      }
      if (isLast && current.endMinute != null) {
        _setError('Rentang terakhir harus tanpa batas akhir.');
        return;
      }
      if (current.endMinute != null &&
          current.endMinute! < current.startMinute) {
        _setError(
          'Batas akhir rentang ${index + 1} lebih kecil dari batas awal.',
        );
        return;
      }
      if (index > 0) {
        final previous = values[index - 1];
        if (previous.endMinute == null ||
            current.startMinute != previous.endMinute! + 1) {
          _setError(
            'Rentang menit harus berurutan tanpa celah atau tumpang tindih.',
          );
          return;
        }
      }
    }

    Navigator.pop(
      context,
      LatePointSettingFormValue(active: _active, ranges: values),
    );
  }

  void _setError(String message) => setState(() => _error = message);
}

class _RangeEditorCard extends StatelessWidget {
  const _RangeEditorCard({
    required this.index,
    required this.range,
    required this.canRemove,
    required this.isLast,
    required this.onRemove,
  });

  final int index;
  final _EditableLateRange range;
  final bool canRemove;
  final bool isLast;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) => Container(
    key: Key('late-point-range-$index'),
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: NusaColors.surface,
      border: Border.all(color: NusaColors.outline),
      borderRadius: BorderRadius.circular(14),
    ),
    child: Column(
      children: [
        Row(
          children: [
            Container(
              width: 30,
              height: 30,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: NusaColors.primary,
                borderRadius: BorderRadius.circular(9),
              ),
              child: Text(
                '${index + 1}',
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
            const SizedBox(width: 9),
            Expanded(
              child: Text(
                isLast ? 'Rentang terakhir' : 'Rentang menit',
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
            ),
            IconButton(
              key: Key('remove-late-point-range-$index'),
              tooltip: 'Hapus rentang',
              onPressed: canRemove ? onRemove : null,
              icon: const Icon(Icons.delete_outline_rounded),
            ),
          ],
        ),
        const SizedBox(height: 8),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: TextField(
                key: Key('late-point-range-start-$index'),
                controller: range.startController,
                keyboardType: TextInputType.number,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                decoration: const InputDecoration(labelText: 'Mulai menit'),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: TextField(
                key: Key('late-point-range-end-$index'),
                controller: range.endController,
                keyboardType: TextInputType.number,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                decoration: InputDecoration(
                  labelText: 'Sampai menit',
                  hintText: isLast ? 'Tanpa batas' : null,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 9),
        TextField(
          key: Key('late-point-range-points-$index'),
          controller: range.pointsController,
          keyboardType: TextInputType.number,
          inputFormatters: [FilteringTextInputFormatter.digitsOnly],
          decoration: const InputDecoration(
            labelText: 'Poin',
            suffixText: 'poin',
          ),
        ),
      ],
    ),
  );
}

class _EditableLateRange {
  _EditableLateRange({required int start, required int points, int? end})
    : startController = TextEditingController(text: '$start'),
      endController = TextEditingController(text: end == null ? '' : '$end'),
      pointsController = TextEditingController(text: '$points');

  factory _EditableLateRange.fromDomain(LatePointRange range) =>
      _EditableLateRange(
        start: range.startMinute,
        end: range.endMinute,
        points: range.points,
      );

  final TextEditingController startController;
  final TextEditingController endController;
  final TextEditingController pointsController;

  void dispose() {
    startController.dispose();
    endController.dispose();
    pointsController.dispose();
  }
}
