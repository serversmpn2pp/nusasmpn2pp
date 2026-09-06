import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/inventory_settings/application/inventory_settings_controller.dart';
import 'package:nusa/features/inventory_settings/domain/inventory_settings.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class InventorySettingsView extends ConsumerStatefulWidget {
  const InventorySettingsView({super.key});

  @override
  ConsumerState<InventorySettingsView> createState() =>
      _InventorySettingsViewState();
}

class _InventorySettingsViewState extends ConsumerState<InventorySettingsView> {
  bool _saving = false;

  @override
  Widget build(BuildContext context) {
    final settings = ref.watch(inventorySettingsControllerProvider);

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Pengaturan Inventaris'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: settings.isLoading || _saving
                ? null
                : ref
                      .read(inventorySettingsControllerProvider.notifier)
                      .refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: settings.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _SettingsError(
            message: _errorMessage(error),
            onRetry: ref
                .read(inventorySettingsControllerProvider.notifier)
                .refresh,
          ),
          data: (value) => _InventorySettingsForm(
            key: ValueKey(
              '${value.updatedAt?.microsecondsSinceEpoch}-'
              '${value.assetNumberPrefix}-${value.internalIdDigits}',
            ),
            settings: value,
            saving: _saving,
            onSave: _save,
          ),
        ),
      ),
    );
  }

  Future<void> _save(InventorySettingsFormValue value) async {
    setState(() => _saving = true);
    try {
      final saved = await ref
          .read(inventorySettingsActionsProvider)
          .update(value);
      ref.read(inventorySettingsControllerProvider.notifier).replace(saved);
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          const SnackBar(
            content: Text('Pengaturan inventaris berhasil disimpan.'),
          ),
        );
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(_errorMessage(error))));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }
}

class _InventorySettingsForm extends StatefulWidget {
  const _InventorySettingsForm({
    required this.settings,
    required this.saving,
    required this.onSave,
    super.key,
  });

  final InventorySettings settings;
  final bool saving;
  final Future<void> Function(InventorySettingsFormValue) onSave;

  @override
  State<_InventorySettingsForm> createState() => _InventorySettingsFormState();
}

class _InventorySettingsFormState extends State<_InventorySettingsForm> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _prefixController;
  late final TextEditingController _suffixController;
  late final TextEditingController _ownerController;
  late int _digits;

  @override
  void initState() {
    super.initState();
    _prefixController = TextEditingController(
      text: widget.settings.assetNumberPrefix,
    );
    _suffixController = TextEditingController(
      text: widget.settings.assetNumberSuffix,
    );
    _ownerController = TextEditingController(text: widget.settings.ownerName);
    _digits = widget.settings.internalIdDigits.clamp(4, 10);
  }

  @override
  void dispose() {
    _prefixController.dispose();
    _suffixController.dispose();
    _ownerController.dispose();
    super.dispose();
  }

  String get _cleanPrefix {
    final value = _prefixController.text.trim().replaceAll(
      RegExp(r'^\.+|\.+$'),
      '',
    );
    return value.isEmpty ? '-' : value;
  }

  String get _cleanSuffix {
    final value = _suffixController.text.trim().replaceAll('.', '');
    return value.isEmpty ? '-' : value;
  }

  String get _sequence => '1'.padLeft(_digits, '0');

  @override
  Widget build(BuildContext context) => Form(
    key: _formKey,
    child: ListView(
      key: const Key('inventory-settings-scroll'),
      padding: const EdgeInsets.fromLTRB(16, 10, 16, 32),
      children: [
        _IntroCard(ownerName: _ownerController.text),
        const SizedBox(height: 12),
        _PreviewCard(
          assetNumber:
              '$_cleanPrefix.${widget.settings.exampleYear}.$_cleanSuffix',
          consumableCode: 'BHP-$_sequence',
          assetUnitCode: 'AST-${widget.settings.exampleYear}-$_sequence',
        ),
        const SizedBox(height: 12),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Identitas Aset Sekolah',
                  style: TextStyle(
                    color: NusaColors.textPrimary,
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 4),
                const Text(
                  'Tahun perolehan ditempatkan otomatis di antara awalan dan akhiran.',
                  style: TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 11.5,
                    height: 1.35,
                  ),
                ),
                const SizedBox(height: 16),
                TextFormField(
                  key: const Key('inventory-settings-prefix'),
                  controller: _prefixController,
                  maxLength: 80,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                  ),
                  inputFormatters: [
                    FilteringTextInputFormatter.allow(RegExp(r'[0-9.]')),
                  ],
                  onChanged: (_) => setState(() {}),
                  decoration: const InputDecoration(
                    labelText: 'Awalan nomor aset',
                    hintText: '12.03.15.08.10',
                    prefixIcon: Icon(Icons.pin_outlined),
                    helperText: 'Kelompok dua angka dipisahkan titik.',
                  ),
                  validator: _validatePrefix,
                ),
                const SizedBox(height: 8),
                TextFormField(
                  key: const Key('inventory-settings-suffix'),
                  controller: _suffixController,
                  maxLength: 2,
                  keyboardType: TextInputType.number,
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                  onChanged: (_) => setState(() {}),
                  decoration: const InputDecoration(
                    labelText: 'Akhiran tetap',
                    hintText: '08',
                    prefixIcon: Icon(Icons.numbers_rounded),
                    helperText: 'Harus terdiri dari dua angka.',
                  ),
                  validator: _validateSuffix,
                ),
                const SizedBox(height: 8),
                TextFormField(
                  key: const Key('inventory-settings-owner'),
                  controller: _ownerController,
                  maxLength: 160,
                  textCapitalization: TextCapitalization.words,
                  onChanged: (_) => setState(() {}),
                  decoration: const InputDecoration(
                    labelText: 'Nama pemilik',
                    hintText: 'SMP Negeri 2 Padang Panjang',
                    prefixIcon: Icon(Icons.account_balance_outlined),
                  ),
                  validator: (value) => value?.trim().isEmpty == true
                      ? 'Nama pemilik wajib diisi.'
                      : null,
                ),
                const SizedBox(height: 8),
                NusaDropdownField<int>(
                  fieldKey: const Key('inventory-settings-digits'),
                  value: _digits,
                  options: [
                    for (var value = 4; value <= 10; value++)
                      NusaDropdownOption(
                        value: value,
                        label: '$value digit · ${'1'.padLeft(value, '0')}',
                      ),
                  ],
                  decoration: const InputDecoration(
                    labelText: 'Jumlah digit ID internal',
                    prefixIcon: Icon(Icons.format_list_numbered_rounded),
                  ),
                  enabled: !widget.saving,
                  onChanged: (value) {
                    if (value != null) setState(() => _digits = value);
                  },
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 12),
        const _RulesCard(),
        if (widget.settings.updatedBy?.isNotEmpty == true) ...[
          const SizedBox(height: 10),
          Text(
            'Terakhir diperbarui oleh ${widget.settings.updatedBy}'
            '${widget.settings.updatedAt == null ? '' : ' · ${_dateTime(widget.settings.updatedAt!)}'}',
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 10.5,
            ),
          ),
        ],
        const SizedBox(height: 16),
        FilledButton.icon(
          key: const Key('save-inventory-settings'),
          onPressed: widget.saving ? null : _submit,
          icon: widget.saving
              ? const SizedBox.square(
                  dimension: 18,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: Colors.white,
                  ),
                )
              : const Icon(Icons.save_outlined),
          label: Text(widget.saving ? 'Menyimpan...' : 'Simpan Pengaturan'),
        ),
      ],
    ),
  );

  String? _validatePrefix(String? value) {
    final text = value?.trim() ?? '';
    if (text.isEmpty) return 'Awalan nomor aset wajib diisi.';
    if (!RegExp(r'^\d{2}(?:\.\d{2})*$').hasMatch(text)) {
      return 'Gunakan kelompok dua angka, misalnya 12.03.15.08.10.';
    }
    return null;
  }

  String? _validateSuffix(String? value) {
    if (!RegExp(r'^\d{2}$').hasMatch(value?.trim() ?? '')) {
      return 'Akhiran harus terdiri dari dua angka.';
    }
    return null;
  }

  Future<void> _submit() async {
    if (_formKey.currentState?.validate() != true) return;
    await widget.onSave(
      InventorySettingsFormValue(
        assetNumberPrefix: _prefixController.text.trim(),
        assetNumberSuffix: _suffixController.text.trim(),
        ownerName: _ownerController.text.trim(),
        internalIdDigits: _digits,
      ),
    );
  }
}

class _IntroCard extends StatelessWidget {
  const _IntroCard({required this.ownerName});

  final String ownerName;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(18),
    ),
    child: Row(
      children: [
        Container(
          width: 48,
          height: 48,
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(14),
          ),
          child: const Icon(
            Icons.settings_suggest_rounded,
            color: NusaColors.accent,
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Identitas Inventaris',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                ownerName.trim().isEmpty ? 'Pemilik belum diisi' : ownerName,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: Colors.white.withValues(alpha: 0.76),
                  fontSize: 11.5,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _PreviewCard extends StatelessWidget {
  const _PreviewCard({
    required this.assetNumber,
    required this.consumableCode,
    required this.assetUnitCode,
  });

  final String assetNumber;
  final String consumableCode;
  final String assetUnitCode;

  @override
  Widget build(BuildContext context) => Card(
    color: NusaColors.surfaceBlue,
    child: Container(
      decoration: const BoxDecoration(
        border: Border(left: BorderSide(color: NusaColors.accent, width: 5)),
      ),
      padding: const EdgeInsets.all(15),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Pratinjau Identitas',
            style: TextStyle(
              color: NusaColors.textPrimary,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 10),
          _PreviewRow(label: 'Nomor aset resmi', value: assetNumber),
          _PreviewRow(label: 'Barang habis pakai', value: consumableCode),
          _PreviewRow(label: 'Unit aset', value: assetUnitCode),
        ],
      ),
    ),
  );
}

class _PreviewRow extends StatelessWidget {
  const _PreviewRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 7),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          flex: 4,
          child: Text(
            label,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 10.5,
            ),
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          flex: 6,
          child: Text(
            value,
            textAlign: TextAlign.right,
            overflow: TextOverflow.ellipsis,
            maxLines: 2,
            style: const TextStyle(
              color: NusaColors.primary,
              fontSize: 11,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
      ],
    ),
  );
}

class _RulesCard extends StatelessWidget {
  const _RulesCard();

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Aturan Identitas',
            style: TextStyle(
              color: NusaColors.textPrimary,
              fontSize: 15,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 12),
          _Rule(
            number: 1,
            text: 'Nomor aset resmi mengikuti format sekolah; hanya bagian tahun yang berubah.',
          ),
          _Rule(
            number: 2,
            text: 'ID internal NUSA unik untuk setiap barang dan barcode.',
          ),
          _Rule(number: 3, text: 'Barang habis pakai menggunakan awalan BHP.'),
          _Rule(
            number: 4,
            text: 'Unit aset menggunakan awalan AST dan tahun perolehan.',
          ),
        ],
      ),
    ),
  );
}

class _Rule extends StatelessWidget {
  const _Rule({required this.number, required this.text});

  final int number;
  final String text;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 28,
          height: 28,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: NusaColors.primary,
            borderRadius: BorderRadius.circular(9),
          ),
          child: Text(
            '$number',
            style: const TextStyle(
              color: NusaColors.accent,
              fontWeight: FontWeight.w900,
              fontSize: 11,
            ),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            text,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 11.5,
              height: 1.35,
            ),
          ),
        ),
      ],
    ),
  );
}

class _SettingsError extends StatelessWidget {
  const _SettingsError({required this.message, required this.onRetry});

  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(
            Icons.cloud_off_rounded,
            size: 48,
            color: NusaColors.primary,
          ),
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

String _dateTime(DateTime value) {
  String two(int number) => number.toString().padLeft(2, '0');
  return '${two(value.day)}/${two(value.month)}/${value.year} '
      '${two(value.hour)}:${two(value.minute)}';
}

String _errorMessage(Object error) {
  if (error is ValidationException && error.errors.isNotEmpty) {
    final messages = error.errors.values.expand((items) => items).toList();
    if (messages.isNotEmpty) return messages.first;
  }
  return error is AppException
      ? error.message
      : 'Pengaturan inventaris belum dapat diproses.';
}
