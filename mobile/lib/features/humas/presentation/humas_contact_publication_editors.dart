import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/humas/data/humas_photo_picker.dart';
import 'package:nusa/features/humas/data/humas_repository.dart';
import 'package:nusa/features/humas/domain/humas.dart';
import 'package:nusa/features/humas/presentation/humas_editors.dart';
import 'package:nusa/features/humas/presentation/humas_form_view.dart';
import 'package:nusa/features/humas/presentation/widgets/humas_widgets.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

Future<bool> editContactPublication(
  BuildContext context,
  HumasRepository repository,
  HumasModule module, {
  HumasData? record,
}) async {
  final refs = await repository.get('${module.path}/referensi', {
    if (module == HumasModule.publications && record != null)
      'publikasi_id': record['id'],
  });
  if (!context.mounted) return false;
  if (module == HumasModule.publications) {
    if (refs['dapat_buat'] != true) {
      throw const NetworkException(
        'Akses pengelolaan publikasi tidak tersedia.',
        statusCode: 403,
      );
    }
    return await Navigator.of(context).push<bool>(
          MaterialPageRoute(
            builder: (_) => _PublicationEditor(
              repository: repository,
              references: refs,
              record: record,
            ),
          ),
        ) ==
        true;
  }
  if (humasMap(refs['hak_akses'])['dapat_kelola'] != true) {
    throw const NetworkException(
      'Akses pengelolaan kontak tidak tersedia.',
      statusCode: 403,
    );
  }
  final data = record ?? <String, dynamic>{};
  final token = humasUuid();
  return openHumasForm(
    context,
    title: record == null ? 'Tambah Kontak' : 'Edit Kontak',
    fields: [
      HumasField('nama', 'Nama kontak', maxLength: 180, initial: data['nama']),
      HumasField(
        'instansi',
        'Instansi',
        maxLength: 180,
        required: false,
        initial: data['instansi'],
      ),
      HumasField(
        'jabatan',
        'Jabatan',
        maxLength: 120,
        required: false,
        initial: data['jabatan'],
      ),
      HumasField(
        'urusan',
        'Urusan',
        kind: HumasFieldKind.choice,
        options: humasOptions(refs['urusan']),
        initial: data['urusan'],
      ),
      HumasField(
        'nomor_kontak',
        'Nomor telepon / WhatsApp',
        maxLength: 32,
        required: false,
        initial: data['nomor_kontak'],
      ),
      HumasField(
        'email',
        'Email',
        maxLength: 180,
        required: false,
        initial: data['email'],
      ),
      HumasField(
        'dasar_penggunaan',
        'Dasar penggunaan kontak',
        kind: HumasFieldKind.multiline,
        maxLength: 1000,
        initial: data['dasar_penggunaan'],
      ),
      HumasField(
        'catatan',
        'Catatan privat',
        kind: HumasFieldKind.multiline,
        maxLength: 2000,
        required: false,
        initial: data['catatan'],
      ),
      HumasField(
        'status',
        'Status',
        kind: HumasFieldKind.choice,
        options: humasOptions(refs['status']),
        initial: data['status'] ?? 'aktif',
      ),
      if (record != null)
        const HumasField('alasan', 'Alasan perubahan', maxLength: 1000),
    ],
    onSave: (values) async {
      await repository.send(
        record == null ? 'kontak' : 'kontak/${record['id']}',
        {
          ...values,
          if (record == null)
            'token_pembuatan': token
          else
            'versi': record['versi'],
        },
        method: record == null ? 'POST' : 'PATCH',
      );
    },
  );
}

class _PublicationEditor extends ConsumerStatefulWidget {
  const _PublicationEditor({
    required this.repository,
    required this.references,
    this.record,
  });
  final HumasRepository repository;
  final HumasData references;
  final HumasData? record;
  @override
  ConsumerState<_PublicationEditor> createState() => _PublicationEditorState();
}

class _PublicationEditorState extends ConsumerState<_PublicationEditor> {
  final _token = humasUuid();
  final _photos = <HumasPickedFile>[];
  final _remove = <int>{}, _assets = <int>{}, _latestAssets = <int>{};
  final _agendaSearch = TextEditingController(),
      _assetSearch = TextEditingController();
  late Map<String, String> _agendas;
  late List<HumasData> _assetOptions;
  String? _agenda;
  bool _picking = false, _searching = false, _editAssets = false;
  String? _error;
  HumasData get _data => widget.record ?? const {};
  List<HumasData> get _existing =>
      humasItems(_data['lampiran']).where((f) => f['jenis'] == 'foto').toList();
  int get _count =>
      _existing.where((f) => !_remove.contains(humasInt(f['id']))).length +
      _photos.length;

  @override
  void initState() {
    super.initState();
    _agendas = {
      for (final a in humasItems(widget.references['agenda']))
        '${a['id']}': humasText(a['judul']),
    };
    _agenda = _data['agenda_humas_id']?.toString();
    _assetOptions = humasItems(_data['aset']);
    _assets.addAll(_assetOptions.map((a) => humasInt(a['id'])));
  }

  @override
  void dispose() {
    _agendaSearch.dispose();
    _assetSearch.dispose();
    super.dispose();
  }

  Future<void> _pick(HumasPhotoSource source) async {
    if (_picking || _searching) return;
    setState(() {
      _picking = true;
      _error = null;
    });
    try {
      final photos = await ref.read(humasPhotoPickerProvider).pick(source);
      if (!mounted) return;
      if (_count + photos.length > 5) {
        throw const ValidationException('Maksimal 5 foto aktif per publikasi.');
      }
      if (photos.any((f) => f.bytes.length > 2 * 1024 * 1024)) {
        throw const ValidationException('Foto maksimal 2 MB per berkas.');
      }
      setState(() => _photos.addAll(photos));
    } catch (error) {
      if (mounted) setState(() => _error = humasError(error));
    } finally {
      if (mounted) setState(() => _picking = false);
    }
  }

  Future<void> _search(bool assets) async {
    if (_picking || _searching) return;
    setState(() {
      _searching = true;
      _error = null;
    });
    try {
      final result = await widget.repository.get(
        assets ? 'publikasi/pilihan-aset' : 'publikasi/referensi',
        {
          if (assets)
            'cari': _assetSearch.text.trim()
          else
            'cari_agenda': _agendaSearch.text.trim(),
          if (!assets && widget.record != null) 'publikasi_id': _data['id'],
        },
      );
      if (!mounted) return;
      setState(() {
        if (assets) {
          final all = {
            for (final a in _assetOptions.where(
              (a) => _assets.contains(humasInt(a['id'])),
            ))
              humasInt(a['id']): a,
          };
          for (final a in humasItems(result['items'])) {
            all[humasInt(a['id'])] = a;
          }
          _assetOptions = all.values.toList();
        } else {
          _agendas = {
            if (_agenda != null && _agendas.containsKey(_agenda))
              _agenda!: _agendas[_agenda]!,
            for (final a in humasItems(result['agenda']))
              '${a['id']}': humasText(a['judul']),
          };
        }
      });
    } catch (error) {
      if (mounted) setState(() => _error = humasError(error));
    } finally {
      if (mounted) setState(() => _searching = false);
    }
  }

  @override
  Widget build(BuildContext context) => HumasFormView(
    title: widget.record == null
        ? 'Buat Draf Publikasi'
        : 'Edit Draf Publikasi',
    busy: _picking || _searching,
    fields: [
      HumasField(
        'judul',
        'Judul publikasi',
        maxLength: 180,
        initial: _data['judul'],
      ),
      HumasField(
        'jenis',
        'Jenis konten',
        kind: HumasFieldKind.choice,
        options: humasOptions(widget.references['jenis']),
        initial: _data['jenis'] ?? 'berita',
      ),
      HumasField(
        'kanal',
        'Media tujuan',
        kind: HumasFieldKind.choice,
        options: humasOptions(widget.references['kanal']),
        initial: _data['kanal'] ?? 'website',
      ),
      HumasField(
        'rencana_tayang',
        'Rencana tanggal tayang',
        kind: HumasFieldKind.date,
        required: false,
        initial: _data['rencana_tayang'],
      ),
      HumasField(
        'ringkasan',
        'Ringkasan',
        kind: HumasFieldKind.multiline,
        maxLength: 500,
        required: false,
        initial: _data['ringkasan'],
      ),
      HumasField(
        'isi',
        'Naskah publikasi',
        kind: HumasFieldKind.multiline,
        maxLength: 20000,
        initial: _data['isi'],
      ),
    ],
    extraBuilder: (context, saving) {
      final enabled = !saving && !_picking && !_searching;
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (widget.references['dapat_pilih_agenda'] == true) ...[
            TextField(
              key: const Key('publikasi-cari-agenda'),
              controller: _agendaSearch,
              enabled: enabled,
              maxLength: 120,
              decoration: InputDecoration(
                labelText: 'Cari agenda terkait',
                suffixIcon: IconButton(
                  tooltip: 'Cari agenda',
                  onPressed: enabled ? () => _search(false) : null,
                  icon: const Icon(Icons.search),
                ),
              ),
              onSubmitted: enabled ? (_) => _search(false) : null,
            ),
            NusaDropdownField<String>(
              fieldKey: const Key('publikasi-agenda'),
              value: _agendas.containsKey(_agenda) ? _agenda : '',
              enabled: enabled,
              decoration: const InputDecoration(labelText: 'Agenda terkait'),
              options: [
                const NusaDropdownOption(value: '', label: 'Tanpa agenda'),
                for (final a in _agendas.entries)
                  NusaDropdownOption(value: a.key, label: a.value),
              ],
              onChanged: (v) => setState(() => _agenda = v),
            ),
            const SizedBox(height: 20),
          ],
          Text(
            'Foto publikasi ($_count/5)',
            style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 12),
          for (final file in _existing)
            CheckboxListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(humasText(file['nama'])),
              subtitle: const Text('Hapus dari draf'),
              value: _remove.contains(humasInt(file['id'])),
              onChanged: !enabled
                  ? null
                  : (v) => setState(() {
                      if (v == true) {
                        _remove.add(humasInt(file['id']));
                      } else {
                        _remove.remove(humasInt(file['id']));
                      }
                    }),
            ),
          for (var i = 0; i < _photos.length; i++)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Row(
                children: [
                  Image.memory(
                    _photos[i].bytes,
                    width: 64,
                    height: 64,
                    cacheWidth: 128,
                    fit: BoxFit.cover,
                    errorBuilder: (_, _, _) =>
                        const Icon(Icons.image_not_supported),
                  ),
                  const SizedBox(width: 12),
                  Expanded(child: Text(_photos[i].name)),
                  IconButton(
                    tooltip: 'Hapus foto ${i + 1}',
                    onPressed: enabled
                        ? () => setState(() => _photos.removeAt(i))
                        : null,
                    icon: const Icon(Icons.close),
                  ),
                ],
              ),
            ),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final source in HumasPhotoSource.values)
                OutlinedButton.icon(
                  key: Key('publikasi-foto-${source.name}'),
                  onPressed: enabled && _count < 5 ? () => _pick(source) : null,
                  icon: Icon(switch (source) {
                    HumasPhotoSource.camera => Icons.camera_alt_outlined,
                    HumasPhotoSource.gallery => Icons.photo_library_outlined,
                    HumasPhotoSource.files => Icons.attach_file,
                  }),
                  label: Text(switch (source) {
                    HumasPhotoSource.camera => 'Kamera',
                    HumasPhotoSource.gallery => 'Galeri',
                    HumasPhotoSource.files => 'Berkas',
                  }),
                ),
            ],
          ),
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 12),
            child: Text(
              'JPG, PNG, WebP / maksimal 2 MB per foto',
              style: TextStyle(color: NusaColors.textSecondary),
            ),
          ),
          if (widget.references['dapat_pilih_aset'] == true) ...[
            SwitchListTile.adaptive(
              contentPadding: EdgeInsets.zero,
              title: Text(
                widget.record == null
                    ? 'Gunakan bank aset promosi'
                    : 'Ubah aset terpakai',
              ),
              value: _editAssets,
              onChanged: enabled
                  ? (v) => setState(() => _editAssets = v)
                  : null,
            ),
            if (_editAssets) ...[
              TextField(
                key: const Key('publikasi-cari-aset'),
                controller: _assetSearch,
                enabled: enabled,
                maxLength: 120,
                decoration: InputDecoration(
                  labelText: 'Cari aset',
                  suffixIcon: IconButton(
                    tooltip: 'Cari aset',
                    onPressed: enabled ? () => _search(true) : null,
                    icon: const Icon(Icons.search),
                  ),
                ),
                onSubmitted: enabled ? (_) => _search(true) : null,
              ),
              for (final a in _assetOptions) ...[
                CheckboxListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(humasText(a['nama'])),
                  value: _assets.contains(humasInt(a['id'])),
                  onChanged: !enabled
                      ? null
                      : (v) => setState(() {
                          if (v == true) {
                            if (_assets.length >= 10) {
                              _error = 'Maksimal 10 aset.';
                              return;
                            }
                            _assets.add(humasInt(a['id']));
                          } else {
                            _assets.remove(humasInt(a['id']));
                            _latestAssets.remove(humasInt(a['id']));
                          }
                        }),
                ),
                if (_assets.contains(humasInt(a['id'])) &&
                    humasItems(_data['aset'])
                        .any((old) => old['id'] == a['id']))
                  CheckboxListTile(
                    contentPadding: const EdgeInsets.only(left: 16),
                    title: const Text('Gunakan versi aset terbaru'),
                    value: _latestAssets.contains(humasInt(a['id'])),
                    onChanged: !enabled
                        ? null
                        : (v) => setState(() {
                            if (v == true) {
                              _latestAssets.add(humasInt(a['id']));
                            } else {
                              _latestAssets.remove(humasInt(a['id']));
                            }
                          }),
                  ),
              ],
            ],
          ],
          if (_searching || _picking) const LinearProgressIndicator(),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 12),
              child: Text(
                _error!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ),
          const SizedBox(height: 16),
        ],
      );
    },
    onSave: (values) async {
      final payload = <String, dynamic>{
        ...values,
        if (widget.record == null)
          'token_pembuatan': _token
        else ...{
          'versi': _data['versi'],
          '_method': 'PATCH',
        },
        if (widget.references['dapat_pilih_agenda'] == true)
          'agenda_humas_id': _agenda,
        if (_editAssets) 'aset_dikirim': '1',
      };
      final form = FormData.fromMap(payload);
      for (final f in _photos) {
        form.files.add(
          MapEntry(
            'foto[]',
            MultipartFile.fromBytes(f.bytes, filename: f.name),
          ),
        );
      }
      for (final id in _remove) {
        form.fields.add(MapEntry('hapus_foto[]', '$id'));
      }
      if (_editAssets) {
        for (final id in _assets) {
          form.fields.add(MapEntry('aset_ids[]', '$id'));
        }
        for (final id in _latestAssets) {
          form.fields.add(MapEntry('perbarui_aset[]', '$id'));
        }
      }
      await widget.repository.send(
        widget.record == null ? 'publikasi' : 'publikasi/${_data['id']}',
        payload,
        multipart: form,
      );
    },
  );
}
