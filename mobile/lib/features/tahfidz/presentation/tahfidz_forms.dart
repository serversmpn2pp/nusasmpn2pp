import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

import '../data/tahfidz_repository.dart';
import '../domain/tahfidz.dart';
import 'tahfidz_widgets.dart';
import 'tahfidz_surah_view.dart';

class TfField {
  const TfField(
    this.name,
    this.label, {
    this.value,
    this.options,
    this.kind = 'text',
    this.required = true,
  });
  final String name, label, kind;
  final Object? value;
  final List<TfData>? options;
  final bool required;
}

Future<TfData?> tfEdit(
  BuildContext context, {
  required String title,
  required String path,
  required List<TfField> fields,
  String method = 'POST',
  TfData hidden = const {},
}) => showModalBottomSheet<TfData>(
  context: context,
  isScrollControlled: true,
  useSafeArea: true,
  builder: (_) => TfEditor(
    title: title,
    path: path,
    fields: fields,
    method: method,
    hidden: hidden,
  ),
);

class TfEditor extends ConsumerStatefulWidget {
  const TfEditor({
    required this.title,
    required this.path,
    required this.fields,
    required this.method,
    required this.hidden,
    super.key,
  });
  final String title, path, method;
  final List<TfField> fields;
  final TfData hidden;
  @override
  ConsumerState<TfEditor> createState() => _TfEditorState();
}

class _TfEditorState extends ConsumerState<TfEditor> {
  final _form = GlobalKey<FormState>();
  final _controllers = <String, TextEditingController>{};
  final _values = <String, dynamic>{};
  bool _saving = false;
  String? _error;
  @override
  void initState() {
    super.initState();
    for (final f in widget.fields) {
      if (['choice', 'toggle', 'multiple'].contains(f.kind)) {
        _values[f.name] = f.kind == 'multiple'
            ? {...(f.value as List? ?? [])}
            : f.value;
      } else {
        _controllers[f.name] = TextEditingController(
          text: f.value == null ? '' : '${f.value}',
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
    if (!_form.currentState!.validate()) return;
    for (final f in widget.fields.where(
      (f) => f.kind == 'choice' && f.required,
    )) {
      if (_values[f.name] == null) {
        setState(() => _error = 'Pilih ${f.label.toLowerCase()}.');
        return;
      }
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final data = {...widget.hidden};
      for (final f in widget.fields) {
        final c = _controllers[f.name];
        data[f.name] = c != null
            ? c.text.trim()
            : f.kind == 'multiple'
            ? (_values[f.name] as Set).toList()
            : _values[f.name];
      }
      final result = await ref
          .read(tahfidzRepositoryProvider)
          .call(widget.method, widget.path, data: data);
      if (mounted) Navigator.pop(context, result);
    } catch (e) {
      if (mounted) setState(() => _error = tfError(e));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.fromLTRB(
      20,
      18,
      20,
      MediaQuery.viewInsetsOf(context).bottom + 20,
    ),
    child: SingleChildScrollView(
      child: Form(
        key: _form,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              widget.title,
              style: Theme.of(context).textTheme.titleLarge
                  ?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 16),
            AbsorbPointer(
              absorbing: _saving,
              child: Column(
                children: [
                  for (final f in widget.fields)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          _field(f),
                          if (f.name == 'nomor_surah' &&
                              _values[f.name] != null)
                            TfAction(
                              'Lihat Ayat Surah',
                              () => openTahfidzSurah(
                                context,
                                ref,
                                int.parse('${_values[f.name]}'),
                              ),
                              icon: Icons.menu_book_outlined,
                            ),
                        ],
                      ),
                    ),
                ],
              ),
            ),
            if (_error != null) TfNotice(_error!),
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: _saving ? null : _save,
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Text(_saving ? 'Menyimpan…' : 'Simpan'),
                ),
              ),
            ),
          ],
        ),
      ),
    ),
  );
  Widget _field(TfField f) {
    if (f.name == 'surah' && _values['mode'] != 'tetap') {
      return const SizedBox.shrink();
    }
    if (f.kind == 'toggle') {
      return SwitchListTile(
        contentPadding: EdgeInsets.zero,
        title: Text(f.label),
        value: _values[f.name] == true,
        onChanged: (v) => setState(() => _values[f.name] = v),
      );
    }
    if (f.kind == 'choice') {
      return NusaDropdownField<Object>(
        fieldKey: Key('tahfidz-field-${f.name}'),
        value: _values[f.name] as Object?,
        options: [
          for (final o in f.options ?? [])
            NusaDropdownOption(
              value: o['id'] as Object,
              label: tfText(o['nama']),
            ),
        ],
        decoration: InputDecoration(labelText: f.label),
        onChanged: (v) => setState(() => _values[f.name] = v),
      );
    }
    if (f.kind == 'multiple') {
      return ExpansionTile(
        tilePadding: EdgeInsets.zero,
        title: Text(f.label),
        subtitle: Text('${(_values[f.name] as Set).length} dipilih'),
        children: [
          for (final o in f.options ?? [])
            CheckboxListTile(
              dense: true,
              title: Text(tfText(o['nama'])),
              value: (_values[f.name] as Set).contains(o['id']),
              onChanged: (v) => setState(() {
                final selected = _values[f.name] as Set;
                v == true ? selected.add(o['id']) : selected.remove(o['id']);
              }),
            ),
        ],
      );
    }
    return TextFormField(
      key: Key('tahfidz-field-${f.name}'),
      controller: _controllers[f.name],
      readOnly: f.kind == 'date',
      maxLines: f.kind == 'notes' ? 3 : 1,
      keyboardType: f.kind == 'number'
          ? const TextInputType.numberWithOptions(decimal: true)
          : TextInputType.text,
      decoration: InputDecoration(
        labelText: f.label,
        suffixIcon: f.kind == 'date'
            ? const Icon(Icons.calendar_month_outlined)
            : null,
      ),
      validator: (v) => f.required && (v?.trim().isEmpty ?? true)
          ? '${f.label} wajib diisi.'
          : null,
      onTap: f.kind == 'date'
          ? () async {
              final date = await showDatePicker(
                context: context,
                initialDate:
                    DateTime.tryParse(_controllers[f.name]!.text) ??
                    DateTime.now(),
                firstDate: DateTime(2000),
                lastDate: DateTime(2100),
              );
              if (date != null) {
                _controllers[f.name]!.text = date
                    .toIso8601String()
                    .split('T')
                    .first;
              }
            }
          : null,
    );
  }
}

class TfMembersView extends ConsumerStatefulWidget {
  const TfMembersView(this.groupId, {super.key});
  final int groupId;
  @override
  ConsumerState<TfMembersView> createState() => _TfMembersViewState();
}

class _TfMembersViewState extends ConsumerState<TfMembersView> {
  late Future<TfData> _future;
  Set<int> _selected = {};
  String _query = '';
  bool _saving = false;
  int _request = 0;
  @override
  void initState() {
    super.initState();
    _reload();
  }

  void _reload() {
    final request = ++_request;
    _future = ref
        .read(tahfidzRepositoryProvider)
        .get('tahfidz/${widget.groupId}/anggota')
        .then((d) {
          if (mounted && request == _request) {
            _selected = (d['terpilih'] as List? ?? []).map(tfInt).toSet();
          }
          return d;
        });
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Atur Anggota Tahfidz'),
      actions: [
        IconButton(
          onPressed: _saving ? null : () => setState(_reload),
          icon: const Icon(Icons.refresh),
          tooltip: 'Muat ulang',
        ),
      ],
    ),
    body: FutureBuilder<TfData>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.hasError) {
          return Center(child: TfNotice(tfError(snapshot.error!)));
        }
        if (snapshot.connectionState != ConnectionState.done ||
            !snapshot.hasData) {
          return const Center(child: CircularProgressIndicator());
        }
        final data = snapshot.data!;
        final items = tfItems(data['items']).where(
          (s) => '${s['nama']} ${s['kelas']}'.toLowerCase().contains(
            _query.toLowerCase(),
          ),
        );
        return Column(
          children: [
            Padding(
              padding: const EdgeInsets.all(16),
              child: TextField(
                decoration: InputDecoration(
                  labelText: 'Cari siswa atau kelas',
                  prefixIcon: const Icon(Icons.search),
                  helperText: '${_selected.length} siswa dipilih',
                ),
                onChanged: (v) => setState(() => _query = v),
              ),
            ),
            Expanded(
              child: ListView(
                children: [
                  for (final s in items)
                    CheckboxListTile(
                      title: Text(tfText(s['nama'])),
                      subtitle: Text(
                        '${tfText(s['kelas'])}${s['kelompok_lain'] == null ? '' : ' · Anggota ${s['kelompok_lain']}'}',
                      ),
                      value: _selected.contains(tfInt(s['id'])),
                      onChanged: _saving || s['dapat_dipilih'] != true
                          ? null
                          : (v) => setState(() {
                              v == true
                                  ? _selected.add(tfInt(s['id']))
                                  : _selected.remove(tfInt(s['id']));
                            }),
                    ),
                ],
              ),
            ),
            SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: SizedBox(
                  width: double.infinity,
                  child: FilledButton(
                    onPressed: _saving
                        ? null
                        : () async {
                            setState(() => _saving = true);
                            try {
                              await ref
                                  .read(tahfidzRepositoryProvider)
                                  .call(
                                    'PUT',
                                    'tahfidz/${widget.groupId}/anggota',
                                    data: {
                                      'versi': tfMap(data['kelompok'])['versi'],
                                      'daftar_lengkap': true,
                                      'siswa_ids': _selected.toList(),
                                    },
                                  );
                              if (context.mounted) Navigator.pop(context);
                            } catch (e) {
                              if (context.mounted) {
                                tfMessage(context, tfError(e));
                              }
                            } finally {
                              if (mounted) setState(() => _saving = false);
                            }
                          },
                    child: Text(
                      _saving
                          ? 'Menyimpan…'
                          : 'Simpan ${_selected.length} Anggota',
                    ),
                  ),
                ),
              ),
            ),
          ],
        );
      },
    ),
  );
}
