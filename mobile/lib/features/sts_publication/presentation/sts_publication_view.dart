import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/sts_publication/data/sts_publication_repository.dart';

class StsPublicationView extends ConsumerStatefulWidget {
  const StsPublicationView({
    required this.activityId,
    required this.classId,
    super.key,
  });
  final int activityId, classId;
  @override
  ConsumerState<StsPublicationView> createState() => _StsPublicationState();
}

class _StsPublicationState extends ConsumerState<StsPublicationView> {
  StsData? _page;
  Object? _error;
  bool _loading = true, _busy = false, _behavior = false, _confirmed = false;
  String _audience = 'orang_tua', _search = '';
  final _selected = <int>{};
  final _reason = TextEditingController();
  int _serial = 0;
  int? _account;
  bool get _allowed =>
      stsPublisher(ref.read(authControllerProvider).value?.session?.pengguna);
  bool _current(int? account) =>
      mounted &&
      _allowed &&
      account == ref.read(authControllerProvider).value?.session?.pengguna.id;
  @override
  void initState() {
    super.initState();
    Future.microtask(_load);
  }

  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  @override
  void didUpdateWidget(covariant StsPublicationView old) {
    super.didUpdateWidget(old);
    if (old.classId != widget.classId || old.activityId != widget.activityId) {
      _serial++;
      _page = null;
      Future.microtask(_load);
    }
  }

  Future<void> _load() async {
    if (!mounted || !_allowed) return;
    final serial = ++_serial;
    final account = ref
        .read(authControllerProvider)
        .value
        ?.session
        ?.pengguna
        .id;
    setState(() {
      _account = account;
      _loading = true;
      _error = null;
      _page = null;
      _selected.clear();
      _confirmed = false;
    });
    try {
      final page = await ref
          .read(stsPublicationRepositoryProvider)
          .publication(widget.activityId, widget.classId);
      if (_current(account) && serial == _serial) {
        setState(() {
          _page = page;
          _loading = false;
        });
      }
    } catch (e) {
      if (_current(account) && serial == _serial) {
        setState(() {
          _error = e;
          _loading = false;
        });
      }
    }
  }

  Future<void> _submit({bool cancel = false}) async {
    if (_busy || !_confirmed || _selected.isEmpty || !_current(_account)) {
      return;
    }
    if (cancel && _reason.text.trim().length < 10) {
      setState(
        () => _error = const FormatException(
          'Isi alasan pembatalan minimal 10 karakter.',
        ),
      );
      return;
    }
    final account = _account;
    final activity = widget.activityId, classId = widget.classId;
    final chosen = stsItems(_page?['siswa'])
        .where((s) => _selected.contains(stsInt(s['anggota_id'])))
        .toList();
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final repo = ref.read(stsPublicationRepositoryProvider);
      final students = [
        for (final s in chosen)
          {
            'anggota_id': s['anggota_id'],
            'versi_publikasi': s['versi_publikasi'],
            if (!cancel)
              'sidik_sumber': s[_behavior ? 'sidik_gabungan' : 'sidik_rapor'],
          },
      ];
      final result = cancel
          ? await repo.cancel(activity, classId, students, _reason.text.trim())
          : await repo.publish(activity, classId, {
              'penerima': _audience,
              'sertakan_perilaku': _behavior,
              'siswa': students,
            });
      if (!mounted ||
          !_current(account) ||
          activity != widget.activityId ||
          classId != widget.classId) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(stsText(result['pesan'], 'Publikasi rapor disimpan.')),
        ),
      );
      _reason.clear();
      await _load();
    } catch (e) {
      if (_current(account) &&
          activity == widget.activityId &&
          classId == widget.classId) {
        setState(() {
          _error = e;
          _confirmed = false;
        });
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(authControllerProvider).value?.session?.pengguna;
    ref.listen(authControllerProvider, (previous, next) {
      if (previous?.value?.session?.pengguna.id !=
          next.value?.session?.pengguna.id) {
        _serial++;
        setState(() {
          _page = null;
          _selected.clear();
          _confirmed = false;
          _reason.clear();
          _account = null;
          _behavior = false;
          _audience = 'orang_tua';
          _search = '';
        });
        Future.microtask(_load);
      }
    });
    final allowed =
        stsPublisher(user) && (_account == null || _account == user?.id);
    final students = stsItems(_page?['siswa']);
    return PopScope(
      canPop: !_busy,
      child: Scaffold(
        appBar: AppBar(
          toolbarHeight: 68,
          title: const Text(
            'Publikasi Rapor STS',
            maxLines: 2,
            style: TextStyle(fontSize: 18),
          ),
          actions: [
            IconButton(
              tooltip: 'Muat ulang',
              onPressed: _busy || !allowed ? null : _load,
              icon: const Icon(Icons.refresh),
            ),
          ],
        ),
        body: !allowed
            ? const Center(child: Text('Akses publikasi rapor tidak tersedia.'))
            : _loading
            ? const Center(child: CircularProgressIndicator())
            : _page == null
            ? Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        _error == null
                            ? 'Belum ada data rapor.'
                            : stsError(_error!),
                        textAlign: TextAlign.center,
                      ),
                      TextButton.icon(
                        onPressed: _load,
                        icon: const Icon(Icons.refresh),
                        label: const Text('Coba lagi'),
                      ),
                    ],
                  ),
                ),
              )
            : ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  Text(
                    stsText(_page?['kegiatan']),
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  Text(
                    stsText(_page?['kelas']),
                    style: const TextStyle(color: NusaColors.textSecondary),
                  ),
                  const SizedBox(height: 20),
                  DropdownButtonFormField<String>(
                    initialValue: _audience,
                    isExpanded: true,
                    decoration: const InputDecoration(
                      labelText: 'Penerima rapor',
                    ),
                    items: const [
                      DropdownMenuItem(
                        value: 'orang_tua',
                        child: Text('Orang tua saja'),
                      ),
                      DropdownMenuItem(
                        value: 'orang_tua_dan_siswa',
                        child: Text('Orang tua dan siswa'),
                      ),
                    ],
                    onChanged: _busy
                        ? null
                        : (v) => setState(() {
                            _audience = v!;
                            _confirmed = false;
                          }),
                  ),
                  CheckboxListTile(
                    contentPadding: EdgeInsets.zero,
                    value: _behavior,
                    title: const Text('Sertakan lampiran perilaku'),
                    subtitle: const Text(
                      'Hanya lampiran yang sudah diperiksa BK',
                    ),
                    onChanged: _busy
                        ? null
                        : (v) => setState(() {
                            _behavior = v!;
                            _confirmed = false;
                          }),
                  ),
                  const Divider(),
                  TextField(
                    decoration: const InputDecoration(
                      labelText: 'Cari siswa',
                      prefixIcon: Icon(Icons.search),
                    ),
                    onChanged: (v) => setState(() => _search = v),
                    enabled: !_busy,
                  ),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    children: [
                      TextButton(
                        onPressed: _busy
                            ? null
                            : () => setState(() {
                                _selected.clear();
                                _selected.addAll(
                                  students
                                      .where(
                                        (s) =>
                                            s['siap'] == true &&
                                            (!_behavior ||
                                                s['perilaku_siap'] == true),
                                      )
                                      .map((s) => stsInt(s['anggota_id'])),
                                );
                                _confirmed = false;
                              }),
                        child: const Text('Pilih yang siap'),
                      ),
                      TextButton(
                        onPressed: _busy
                            ? null
                            : () => setState(() {
                                _selected.clear();
                                _selected.addAll(
                                  students
                                      .where((s) => s['aktif'] == true)
                                      .map((s) => stsInt(s['anggota_id'])),
                                );
                                _confirmed = false;
                              }),
                        child: const Text('Pilih terpublikasi'),
                      ),
                      TextButton(
                        onPressed: _busy
                            ? null
                            : () => setState(() {
                                _selected.clear();
                                _confirmed = false;
                              }),
                        child: const Text('Kosongkan'),
                      ),
                    ],
                  ),
                  Text('${_selected.length} siswa dipilih'),
                  for (final s in students.where(
                    (s) =>
                        stsText(s['nama'])
                            .toLowerCase()
                            .contains(_search.toLowerCase()),
                  )) ...[
                    const Divider(),
                    CheckboxListTile(
                      contentPadding: EdgeInsets.zero,
                      value: _selected.contains(stsInt(s['anggota_id'])),
                      title: Text(stsText(s['nama'])),
                      subtitle: Text(
                        '${s['siap'] == true ? 'Siap diterbitkan' : 'Periode / kehadiran belum siap'}\n${s['aktif'] == true ? 'Terpublikasi - versi ${s['versi_publikasi']}' : 'Draf / publikasi dibatalkan'}${s['berubah'] == true ? '\nAda perubahan belum dipublikasikan' : ''}\n${s['perilaku_siap'] == true ? 'Lampiran diperiksa BK' : 'Lampiran belum siap'}',
                      ),
                      onChanged: _busy
                          ? null
                          : (v) => setState(() {
                              v!
                                  ? _selected.add(stsInt(s['anggota_id']))
                                  : _selected.remove(stsInt(s['anggota_id']));
                              _confirmed = false;
                            }),
                    ),
                    if (stsItems(s['riwayat']).isNotEmpty)
                      ExpansionTile(
                        title: const Text(
                          'Riwayat penerbitan',
                          style: TextStyle(fontSize: 13),
                        ),
                        children: [
                          for (final r in stsItems(s['riwayat']))
                            ListTile(
                              dense: true,
                              title: Text(
                                'Versi ${r['versi']} - ${stsDate(r['dipublikasikan_pada'])}',
                              ),
                              subtitle: Text(
                                '${r['penerima'] == 'orang_tua' ? 'Orang tua saja' : 'Orang tua dan siswa'}${r['alasan_pembatalan'] == null ? '' : '\n${r['alasan_pembatalan']}'}',
                              ),
                            ),
                        ],
                      ),
                  ],
                  const Divider(),
                  if (_error != null)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      child: Text(
                        _error is FormatException
                            ? (_error! as FormatException).message
                            : stsError(_error!),
                        style: const TextStyle(color: NusaColors.danger),
                      ),
                    ),
                  if (_error != null)
                    TextButton.icon(
                      onPressed: _busy ? null : _load,
                      icon: const Icon(Icons.refresh),
                      label: const Text('Muat ulang dan periksa kembali'),
                    ),
                  CheckboxListTile(
                    key: const Key('sts-publish-confirm'),
                    contentPadding: EdgeInsets.zero,
                    value: _confirmed,
                    title: const Text(
                      'Saya sudah memeriksa dan mengonfirmasi tindakan untuk siswa terpilih.',
                    ),
                    onChanged: _busy
                        ? null
                        : (v) => setState(() => _confirmed = v!),
                  ),
                  FilledButton.icon(
                    key: const Key('sts-publish-submit'),
                    onPressed: _busy || !_confirmed || _selected.isEmpty
                        ? null
                        : _submit,
                    icon: Icon(_busy ? Icons.hourglass_top : Icons.publish),
                    label: const Text('Publikasikan / perbarui rapor'),
                  ),
                  const SizedBox(height: 16),
                  ExpansionTile(
                    key: const Key('sts-cancellation-panel'),
                    maintainState: true,
                    title: const Text('Batalkan publikasi'),
                    children: [
                      TextField(
                        key: const Key('sts-cancellation-reason'),
                        controller: _reason,
                        enabled: !_busy,
                        maxLength: 500,
                        minLines: 2,
                        maxLines: 4,
                        decoration: const InputDecoration(
                          labelText: 'Alasan pembatalan',
                        ),
                      ),
                      const Text(
                        'Akses NUSA dihentikan. Salinan yang sudah diunduh tidak dapat ditarik kembali.',
                        style: TextStyle(fontSize: 12),
                      ),
                      OutlinedButton.icon(
                        key: const Key('sts-publish-cancel'),
                        onPressed: _busy || !_confirmed || _selected.isEmpty
                            ? null
                            : () => _submit(cancel: true),
                        icon: const Icon(Icons.unpublished_outlined),
                        label: const Text('Batalkan rapor terpilih'),
                      ),
                    ],
                  ),
                  const SizedBox(height: 24),
                ],
              ),
      ),
    );
  }
}
