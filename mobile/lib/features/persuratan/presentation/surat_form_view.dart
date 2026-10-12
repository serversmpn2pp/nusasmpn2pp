import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/persuratan/domain/persuratan.dart';
import 'package:nusa/features/persuratan/presentation/widgets/surat_widgets.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

enum SuratFieldKind { text, multiline, choice, date, confirmation }

class SuratField {
  const SuratField(
    this.name,
    this.label, {
    this.initial,
    this.required = true,
    this.maxLength = 200,
    this.kind = SuratFieldKind.text,
    this.options = const {},
  });
  final String name;
  final String label;
  final Object? initial;
  final bool required;
  final int maxLength;
  final SuratFieldKind kind;
  final Map<String, String> options;
}

class SuratFormView extends ConsumerStatefulWidget {
  const SuratFormView({
    required this.title,
    required this.accountId,
    required this.fields,
    required this.onSave,
    this.extra,
    super.key,
  });
  final String title;
  final int accountId;
  final List<SuratField> fields;
  final Future<void> Function(SuratData data) onSave;
  final Widget Function(bool saving)? extra;
  @override
  ConsumerState<SuratFormView> createState() => _SuratFormViewState();
}

class _SuratFormViewState extends ConsumerState<SuratFormView> {
  final _form = GlobalKey<FormState>();
  final _controllers = <String, TextEditingController>{};
  final _values = <String, dynamic>{};
  bool _saving = false;
  String? _error;
  @override
  void initState() {
    super.initState();
    for (final f in widget.fields) {
      if (f.kind == SuratFieldKind.choice ||
          f.kind == SuratFieldKind.confirmation) {
        _values[f.name] = f.initial;
      } else {
        _controllers[f.name] = TextEditingController(
          text: suratText(f.initial, ''),
        );
      }
    }
  }

  @override
  void dispose() {
    for (final c in _controllers.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    if (_saving || !_form.currentState!.validate()) return;
    for (final f in widget.fields) {
      if (f.required &&
          ((f.kind == SuratFieldKind.choice &&
                  !f.options.containsKey(_values[f.name])) ||
              (f.kind == SuratFieldKind.confirmation &&
                  _values[f.name] != true))) {
        setState(() => _error = '${f.label} wajib dikonfirmasi atau dipilih.');
        return;
      }
    }
    if (ref.read(authControllerProvider).value?.session?.pengguna.id !=
        widget.accountId) {
      return;
    }
    FocusScope.of(context).unfocus();
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await widget.onSave({
        ..._values,
        for (final e in _controllers.entries)
          e.key: e.value.text.trim().isEmpty ? null : e.value.text.trim(),
      });
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      if (mounted) {
        setState(() {
          _saving = false;
          _error = suratError(e);
        });
      }
    }
  }

  Future<void> _date(SuratField f) async {
    final now = DateTime.now();
    final d = DateTime.tryParse(_controllers[f.name]!.text) ?? now;
    final chosen = await showDatePicker(
      context: context,
      initialDate: d.year < 2000 || d.year > 2100 ? now : d,
      firstDate: DateTime(2000),
      lastDate: DateTime(2100, 12, 31),
    );
    if (chosen != null && mounted) {
      setState(
        () => _controllers[f.name]!.text = chosen.toIso8601String().substring(
          0,
          10,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final sameAccount =
        ref.watch(authControllerProvider).value?.session?.pengguna.id ==
        widget.accountId;
    return PopScope(
      canPop: !_saving,
      child: Scaffold(
        appBar: AppBar(title: Text(widget.title)),
        body: !sameAccount
            ? const Center(
                child: Text('Sesi akun telah berubah. Buka kembali formulir.'),
              )
            : SafeArea(
                child: Form(
                  key: _form,
                  child: SingleChildScrollView(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        for (final f in widget.fields)
                          Padding(
                            padding: const EdgeInsets.only(bottom: 16),
                            child: _field(f),
                          ),
                        if (widget.extra != null) widget.extra!(_saving),
                        if (_error != null)
                          Padding(
                            padding: const EdgeInsets.symmetric(vertical: 16),
                            child: Text(
                              _error!,
                              key: const Key('surat-form-error'),
                              style: TextStyle(
                                color: Theme.of(context).colorScheme.error,
                              ),
                            ),
                          ),
                        const SizedBox(height: 16),
                        FilledButton.icon(
                          key: const Key('surat-save'),
                          onPressed: _saving ? null : _save,
                          icon: const Icon(Icons.save_outlined),
                          label: Text(_saving ? 'Menyimpan...' : 'Simpan'),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
      ),
    );
  }

  Widget _field(SuratField f) {
    final label = '${f.label}${f.required ? ' *' : ''}';
    if (f.kind == SuratFieldKind.confirmation) {
      return CheckboxListTile(
        contentPadding: EdgeInsets.zero,
        title: Text(f.label),
        value: _values[f.name] == true,
        onChanged: _saving ? null : (v) => setState(() => _values[f.name] = v),
      );
    }
    if (f.kind == SuratFieldKind.choice) {
      return NusaDropdownField<String>(
        fieldKey: Key('surat-${f.name}'),
        value: _values[f.name]?.toString(),
        options: f.options.entries
            .map((e) => NusaDropdownOption(value: e.key, label: e.value))
            .toList(),
        decoration: InputDecoration(
          labelText: label,
          hintText: 'Pilih ${f.label.toLowerCase()}',
        ),
        enabled: !_saving,
        onChanged: (v) => setState(() => _values[f.name] = v),
      );
    }
    return TextFormField(
      key: Key('surat-${f.name}'),
      controller: _controllers[f.name],
      enabled: !_saving,
      readOnly: f.kind == SuratFieldKind.date,
      onTap: f.kind == SuratFieldKind.date ? () => _date(f) : null,
      maxLines: f.kind == SuratFieldKind.multiline ? 4 : 1,
      maxLength: f.maxLength,
      decoration: InputDecoration(
        labelText: label,
        counterText: '',
        suffixIcon: f.kind == SuratFieldKind.date
            ? const Icon(Icons.calendar_today_outlined)
            : null,
      ),
      validator: (v) => f.required && (v == null || v.trim().isEmpty)
          ? '${f.label} wajib diisi.'
          : null,
    );
  }
}
