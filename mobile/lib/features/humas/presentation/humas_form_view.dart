import 'package:flutter/material.dart';
import 'package:nusa/features/humas/domain/humas.dart';
import 'package:nusa/features/humas/presentation/widgets/humas_widgets.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

enum HumasFieldKind { text, multiline, choice, boolean, date, dateTime, number }

class HumasField {
  const HumasField(
    this.name,
    this.label, {
    this.kind = HumasFieldKind.text,
    this.required = true,
    this.options = const {},
    this.maxLength = 500,
    this.initial,
    this.selectFirst = true,
    this.prompt,
  });
  final String name;
  final String label;
  final HumasFieldKind kind;
  final bool required;
  final Map<String, String> options;
  final int maxLength;
  final Object? initial;
  final bool selectFirst;
  final String? prompt;
}

class HumasFormView extends StatefulWidget {
  const HumasFormView({
    required this.title,
    required this.fields,
    required this.onSave,
    this.description,
    this.extra,
    super.key,
  });
  final String title;
  final String? description;
  final List<HumasField> fields;
  final Widget? extra;
  final Future<void> Function(HumasData values) onSave;
  @override
  State<HumasFormView> createState() => _HumasFormViewState();
}

class _HumasFormViewState extends State<HumasFormView> {
  final _formKey = GlobalKey<FormState>();
  final _controllers = <String, TextEditingController>{};
  final _values = <String, dynamic>{};
  bool _saving = false;
  String? _error;
  @override
  void initState() {
    super.initState();
    for (final field in widget.fields) {
      var initial = field.initial;
      if (field.kind == HumasFieldKind.dateTime && initial != null) {
        final date = DateTime.tryParse('$initial')?.toLocal();
        if (date != null) initial = date.toIso8601String().substring(0, 16);
      }
      if (field.kind == HumasFieldKind.choice) {
        _values[field.name] =
            initial ??
            (field.required && field.selectFirst
                ? field.options.keys.firstOrNull
                : null);
      } else if (field.kind == HumasFieldKind.boolean) {
        _values[field.name] = initial == true;
      } else {
        _controllers[field.name] = TextEditingController(
          text: humasText(initial, ''),
        );
      }
    }
  }

  @override
  void dispose() {
    for (final controller in _controllers.values) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    if (_saving || !_formKey.currentState!.validate()) return;
    for (final field in widget.fields.where(
      (f) => f.kind == HumasFieldKind.choice && f.required,
    )) {
      if (_values[field.name] == null) {
        setState(() => _error = '${field.label} wajib dipilih.');
        return;
      }
    }
    FocusScope.of(context).unfocus();
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final data = HumasData.from(_values);
      for (final field in widget.fields) {
        if (!_controllers.containsKey(field.name)) continue;
        final value = _controllers[field.name]!.text.trim();
        data[field.name] = value.isEmpty
            ? null
            : field.kind == HumasFieldKind.number
            ? int.tryParse(value)
            : value;
      }
      await widget.onSave(data);
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (mounted) {
        setState(() {
          _error = humasError(error);
          _saving = false;
        });
      }
    }
  }

  Future<void> _pickDate(HumasField field) async {
    final old =
        DateTime.tryParse(_controllers[field.name]!.text) ?? DateTime.now();
    final date = await showDatePicker(
      context: context,
      initialDate: old,
      firstDate: DateTime(1900),
      lastDate: DateTime(2100),
    );
    if (date == null || !mounted) return;
    var selected = date;
    if (field.kind == HumasFieldKind.dateTime) {
      final time = await showTimePicker(
        context: context,
        initialTime: TimeOfDay.fromDateTime(old),
      );
      if (time == null || !mounted) return;
      selected = DateTime(
        date.year,
        date.month,
        date.day,
        time.hour,
        time.minute,
      );
    }
    setState(
      () => _controllers[field.name]!.text = selected
          .toIso8601String()
          .substring(0, field.kind == HumasFieldKind.dateTime ? 16 : 10),
    );
  }

  @override
  Widget build(BuildContext context) => PopScope(
    canPop: !_saving,
    child: Scaffold(
      appBar: AppBar(title: Text(widget.title)),
      body: SafeArea(
        child: Form(
          key: _formKey,
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              if (widget.description != null)
                Padding(
                  padding: const EdgeInsets.only(bottom: 16),
                  child: Text(widget.description!),
                ),
              for (final field in widget.fields)
                Padding(
                  padding: const EdgeInsets.only(bottom: 16),
                  child: _field(field),
                ),
              if (widget.extra != null) widget.extra!,
              if (_error != null)
                Padding(
                  padding: const EdgeInsets.only(bottom: 16),
                  child: Text(
                    _error!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                    ),
                  ),
                ),
              FilledButton(
                onPressed: _saving ? null : _save,
                child: Text(_saving ? 'Menyimpan…' : 'Simpan'),
              ),
            ],
          ),
        ),
      ),
    ),
  );
  Widget _field(HumasField field) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (field.prompt != null) ...[
          Text(
            field.prompt!,
            style: const TextStyle(fontWeight: FontWeight.w600, height: 1.5),
          ),
          const SizedBox(height: 12),
        ],
        _input(field),
      ],
    );
  }

  Widget _input(HumasField field) {
    final label = '${field.label}${field.required ? ' *' : ''}';
    if (field.kind == HumasFieldKind.boolean) {
      return SwitchListTile.adaptive(
        contentPadding: EdgeInsets.zero,
        title: Text(field.label),
        value: _values[field.name] == true,
        onChanged: _saving
            ? null
            : (v) => setState(() => _values[field.name] = v),
      );
    }
    if (field.kind == HumasFieldKind.choice) {
      return NusaDropdownField<String>(
        fieldKey: Key('humas-field-${field.name}'),
        value: _values[field.name] as String?,
        enabled: !_saving,
        options: [
          if (!field.required)
            const NusaDropdownOption(value: '', label: 'Tidak dipilih'),
          for (final entry in field.options.entries)
            NusaDropdownOption(value: entry.key, label: entry.value),
        ],
        decoration: InputDecoration(labelText: label),
        onChanged: (v) => setState(() => _values[field.name] = v),
      );
    }
    final date =
        field.kind == HumasFieldKind.date ||
        field.kind == HumasFieldKind.dateTime;
    return TextFormField(
      key: Key('humas-field-${field.name}'),
      controller: _controllers[field.name],
      enabled: !_saving,
      readOnly: date,
      onTap: date ? () => _pickDate(field) : null,
      maxLength: date ? null : field.maxLength,
      minLines: field.kind == HumasFieldKind.multiline ? 3 : 1,
      maxLines: field.kind == HumasFieldKind.multiline ? 6 : 1,
      keyboardType: field.kind == HumasFieldKind.number
          ? TextInputType.number
          : null,
      decoration: InputDecoration(
        labelText: label,
        suffixIcon: date ? const Icon(Icons.calendar_today_rounded) : null,
      ),
      validator: (value) =>
          field.required && (value == null || value.trim().isEmpty)
          ? '${field.label} wajib diisi.'
          : null,
    );
  }
}

Future<bool> openHumasForm(
  BuildContext context, {
  required String title,
  required List<HumasField> fields,
  required Future<void> Function(HumasData) onSave,
  String? description,
  Widget? extra,
}) async =>
    await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => HumasFormView(
          title: title,
          fields: fields,
          onSave: onSave,
          description: description,
          extra: extra,
        ),
      ),
    ) ==
    true;
