import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/persuratan/data/persuratan_repository.dart';
import 'package:nusa/features/persuratan/domain/persuratan.dart';
import 'package:nusa/features/persuratan/presentation/surat_editors.dart';
import 'package:nusa/features/persuratan/presentation/widgets/surat_widgets.dart';

class PersuratanDetailView extends ConsumerStatefulWidget {
  const PersuratanDetailView({
    required this.id,
    this.personal = false,
    this.task = false,
    super.key,
  });
  final int id;
  final bool personal;
  final bool task;
  @override
  ConsumerState<PersuratanDetailView> createState() =>
      _PersuratanDetailViewState();
}

class _PersuratanDetailViewState extends ConsumerState<PersuratanDetailView> {
  SuratData? _data;
  bool _loading = true;
  bool _busy = false;
  Object? _error;
  int _request = 0;
  int? _account;
  String get _path => widget.task
      ? 'tindak-lanjut-surat'
      : widget.personal
      ? 'surat-saya'
      : 'persuratan';
  bool get _allowed {
    final u = ref.read(authControllerProvider).value?.session?.pengguna;
    return widget.task
        ? SuratModule.myTasks.canOpen(u) || SuratModule.monitoring.canOpen(u)
        : (widget.personal ? SuratModule.myLetters : SuratModule.archive)
              .canOpen(u);
  }

  @override
  void initState() {
    super.initState();
    Future.microtask(_load);
  }

  @override
  void didUpdateWidget(covariant PersuratanDetailView oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.id != widget.id ||
        oldWidget.personal != widget.personal ||
        oldWidget.task != widget.task) {
      ++_request;
      _load();
    }
  }

  Future<void> _load() async {
    if (!mounted) return;
    if (!_allowed) {
      setState(() {
        _data = null;
        _loading = false;
      });
      return;
    }
    final account = ref
        .read(authControllerProvider)
        .value
        ?.session
        ?.pengguna
        .id;
    final request = ++_request;
    setState(() {
      _data = null;
      _loading = true;
      _error = null;
      _account = account;
    });
    try {
      if (widget.id <= 0) {
        throw const ValidationException('Nomor data surat tidak valid.');
      }
      final data = await ref
          .read(persuratanRepositoryProvider)
          .get('$_path/${widget.id}');
      if (!mounted ||
          request != _request ||
          account !=
              ref.read(authControllerProvider).value?.session?.pengguna.id) {
        return;
      }
      setState(() {
        _data = data;
        _loading = false;
      });
    } catch (e) {
      if (mounted && request == _request) {
        setState(() {
          _loading = false;
          _error = e;
        });
      }
    }
  }

  Future<void> _run(
    Future<bool> Function(PersuratanRepository, int, SuratData) action,
  ) async {
    if (_busy || _data == null) return;
    final account = ref
        .read(authControllerProvider)
        .value
        ?.session
        ?.pengguna
        .id;
    if (account == null || account != _account) return;
    setState(() => _busy = true);
    try {
      if (await action(
            ref.read(persuratanRepositoryProvider),
            account,
            _data!,
          ) &&
          mounted) {
        await _load();
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(suratError(e))));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<bool> _confirm(String title, String text) async =>
      await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: Text(title),
          content: Text(text),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Batal'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('Lanjutkan'),
            ),
          ],
        ),
      ) ==
      true;
  Future<void> _simple(String action, String title) =>
      _run((repo, account, data) async {
        if (!await _confirm(
          title,
          widget.task
              ? 'Surat: ${suratText(suratMap(data['surat'])['nomor_surat'])}'
              : 'Surat: ${suratText(data['nomor_surat'])}',
        )) {
          return false;
        }
        if (!mounted ||
            account !=
                ref.read(authControllerProvider).value?.session?.pengguna.id) {
          return false;
        }
        final path = widget.personal && !widget.task
            ? 'surat-saya/${widget.id}/baca'
            : '$_path/${widget.id}/tindakan/$action';
        await repo.send(
          path,
          widget.personal && !widget.task ? {} : {'versi': data['versi']},
        );
        return true;
      });
  Future<void> _download(String path, SuratData file) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      final saved = await ref
          .read(persuratanRepositoryProvider)
          .download(path, suratText(file['nama'], 'berkas.pdf'));
      if (mounted && saved) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Berkas berhasil disimpan.')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(suratError(e))));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    ref.listen(authControllerProvider, (previous, next) {
      if (previous?.value?.session?.pengguna.id !=
          next.value?.session?.pengguna.id) {
        ++_request;
        _load();
      }
    });
    final account = ref
        .watch(authControllerProvider)
        .value
        ?.session
        ?.pengguna
        .id;
    final data = _account == account ? _data : null;
    return PopScope(
      canPop: !_busy,
      child: Scaffold(
        appBar: AppBar(
          title: Text(widget.task ? 'Tindak Lanjut Surat' : 'Detail Surat'),
          actions: [
            if (_allowed)
              IconButton(
                tooltip: 'Perbarui',
                onPressed: _loading || _busy ? null : _load,
                icon: const Icon(Icons.refresh),
              ),
          ],
        ),
        body: !_allowed
            ? const Center(
                child: Text('Menu ini tidak tersedia untuk akun Anda.'),
              )
            : _loading
            ? const Center(child: CircularProgressIndicator())
            : _error != null
            ? Center(child: SuratErrorView(_error!, retry: _load))
            : data == null
            ? const SizedBox.shrink()
            : SafeArea(
                child: RefreshIndicator(
                  onRefresh: () async {
                    if (!_busy) await _load();
                  },
                  child: ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.all(16),
                    children: widget.task ? _task(data) : _letter(data),
                  ),
                ),
              ),
      ),
    );
  }

  List<Widget> _header(SuratData data, String title) => [
    Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        SuratStatus(suratText(data['status_label']), suratText(data['status'])),
        if (data['sifat'] == 'rahasia')
          const SuratStatus('Rahasia', 'dikembalikan'),
        if (data['terlambat'] == true)
          const SuratStatus('Terlewat tenggat', 'dikembalikan'),
      ],
    ),
    const SizedBox(height: 16),
    Text(
      title,
      style: const TextStyle(fontSize: 21, fontWeight: FontWeight.w700),
    ),
    const SizedBox(height: 16),
  ];
  List<Widget> _letter(SuratData data) {
    final rights = suratMap(data['hak_akses']);
    return [
      ..._header(data, suratText(data['perihal'])),
      SuratFacts({
        'Nomor surat': data['nomor_surat'],
        'Jenis': data['jenis_label'],
        'Sifat': data['sifat_label'],
        'Tanggal surat': suratDate(data['tanggal_surat']),
        'Pihak': data['pihak'],
        if (data['nomor_agenda'] != null) 'Nomor agenda': data['nomor_agenda'],
        if (data['tanggal_diterima'] != null)
          'Diterima': suratDate(data['tanggal_diterima']),
        if (data['pengesah'] != null)
          'Pengesah': suratMap(data['pengesah'])['nama'],
      }),
      if (data['catatan_status'] != null) ...[
        const SuratHeading('Catatan keputusan'),
        Text(suratText(data['catatan_status'])),
      ],
      if (data['keterangan'] != null) ...[
        const SuratHeading('Keterangan'),
        Text(suratText(data['keterangan'])),
      ],
      if (data['jenis'] == 'tugas') ...[
        const SuratHeading('Penugasan'),
        SuratFacts({
          'Mulai': suratDate(data['mulai_tugas']),
          'Selesai': suratDate(data['selesai_tugas']),
          'Tempat': data['tempat_tugas'],
        }),
        const SizedBox(height: 12),
        Text(suratText(data['uraian_tugas'])),
        for (final p in suratItems(data['penugasan']))
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.person_outline),
            title: Text(suratText(p['nama'])),
            subtitle: Text(
              p['dibaca_pada'] == null
                  ? 'Belum dibaca'
                  : 'Dibaca ${suratDate(p['dibaca_pada'])}',
            ),
          ),
      ],
      const SuratHeading('Dokumen surat'),
      for (final f in suratItems(data['berkas']))
        _file(
          f,
          '$_path/${widget.id}/berkas/${f['id']}',
          old: f['aktif'] == false,
        ),
      if (suratItems(data['berkas']).isEmpty)
        const Text('Berkas belum tersedia.'),
      const Divider(height: 32),
      _buttons([
        if (rights['dapat_edit'] == true)
          _Action(
            'Edit surat',
            Icons.edit_outlined,
            () => _run(
              (repo, account, d) =>
                  editSurat(context, repo, account, record: d),
            ),
          ),
        if (rights['dapat_revisi'] == true)
          _Action(
            'Buat revisi',
            Icons.note_add_outlined,
            () => _run(
              (repo, account, d) =>
                  editSurat(context, repo, account, record: d, revision: true),
            ),
          ),
        if (rights['dapat_disposisi'] == true)
          _Action(
            'Disposisi',
            Icons.assignment_ind_outlined,
            () => _run(
              (repo, account, d) => disposeSurat(context, repo, account, d),
            ),
          ),
        if (rights['dapat_ajukan'] == true)
          _Action(
            'Ajukan pengesahan',
            Icons.send_outlined,
            () => _simple('ajukan', 'Ajukan pengesahan?'),
          ),
        if (rights['dapat_konfirmasi_baca'] == true)
          _Action(
            'Sudah dibaca',
            Icons.mark_email_read_outlined,
            () => _simple('baca', 'Konfirmasi membaca surat?'),
          ),
        for (final action in [
          'sahkan',
          'tarik',
          'kembalikan',
          'kirim',
          'batal',
        ])
          if (rights['dapat_$action'] == true)
            _decisionButton(action, data, false),
      ]),
      if (suratItems(data['tindak_lanjut']).isNotEmpty) ...[
        const SuratHeading('Tindak lanjut'),
        for (final t in suratItems(data['tindak_lanjut']))
          ListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(suratText(t['nama'])),
            subtitle: Text(
              '${suratText(t['status_label'])} - ${suratDate(t['batas_tanggal'])}',
            ),
            trailing: const Icon(Icons.chevron_right),
            onTap: _busy
                ? null
                : () async {
                    await context.push('/tindak-lanjut-surat/${t['id']}');
                    if (mounted) _load();
                  },
          ),
      ],
      if (suratItems(data['surat_terkait']).isNotEmpty) ...[
        const SuratHeading('Surat terkait'),
        for (final s in suratItems(data['surat_terkait']))
          ListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(suratText(s['nomor_surat'])),
            subtitle: Text(suratText(s['status_label'])),
            trailing: const Icon(Icons.chevron_right),
            onTap: _busy
                ? null
                : () async {
                    await context.push('/persuratan/${s['id']}');
                    if (mounted) _load();
                  },
          ),
      ],
      ..._history(suratItems(data['riwayat'])),
    ];
  }

  List<Widget> _task(SuratData data) {
    final letter = suratMap(data['surat']);
    final rights = suratMap(data['hak_akses']);
    return [
      ..._header(data, suratText(letter['perihal'])),
      if (letter['status'] == 'dibatalkan')
        const SuratStatus('Surat induk dibatalkan', 'dibatalkan'),
      SuratFacts({
        'Nomor surat': letter['nomor_surat'],
        'Penerima': data['nama_pegawai'],
        'Tenggat': suratDate(data['batas_tanggal']),
        'Pemeriksa': suratMap(data['pemeriksa'])['nama'],
        'Dibaca': data['dibaca_pada'] == null
            ? 'Belum dibaca'
            : suratDate(data['dibaca_pada']),
        if (data['selesai_pada'] != null)
          'Selesai': suratDate(data['selesai_pada']),
      }),
      const SuratHeading('Instruksi'),
      Text(suratText(data['instruksi'])),
      const SuratHeading('Dokumen surat'),
      for (final f in suratItems(data['berkas_surat']))
        _file(f, 'tindak-lanjut-surat/${widget.id}/berkas/${f['id']}'),
      const Divider(height: 32),
      _buttons([
        if (rights['dapat_baca'] == true)
          _Action(
            'Sudah dibaca',
            Icons.mark_email_read_outlined,
            () => _simple('baca', 'Konfirmasi membaca instruksi?'),
          ),
        if (rights['dapat_mulai'] == true)
          _Action(
            'Mulai pengerjaan',
            Icons.play_arrow,
            () => _simple('mulai', 'Mulai mengerjakan tugas?'),
          ),
        if (rights['dapat_laporkan'] == true)
          _Action(
            'Laporan / progres',
            Icons.edit_note,
            () => _run(
              (repo, account, d) => reportSurat(context, repo, account, d),
            ),
          ),
        if (rights['dapat_ubah'] == true)
          _Action(
            'Ubah penugasan',
            Icons.manage_accounts_outlined,
            () => _run(
              (repo, account, d) => changeSuratTask(context, repo, account, d),
            ),
          ),
        if (rights['dapat_periksa'] == true) ...[
          _decisionButton('terima', data, true),
          _decisionButton('kembalikan', data, true),
        ],
        if (rights['dapat_batal'] == true) _decisionButton('batal', data, true),
      ]),
      const SuratHeading('Laporan pelaksanaan'),
      if (suratItems(data['laporan']).isEmpty)
        const Text('Belum ada laporan pelaksanaan.'),
      for (final l in suratItems(data['laporan'])) ...[
        Text(
          'Laporan versi ${l['nomor']}',
          style: const TextStyle(fontWeight: FontWeight.w700),
        ),
        const SizedBox(height: 8),
        Text(
          l['diajukan_pada'] == null
              ? 'Progres tersimpan ${suratDate(l['disimpan_pada'])}'
              : 'Diajukan ${suratDate(l['diajukan_pada'])}',
        ),
        const SizedBox(height: 8),
        Text(suratText(l['hasil'])),
        if (l['keputusan'] != null)
          Padding(
            padding: const EdgeInsets.only(top: 8),
            child: Text(
              l['keputusan'] == 'diterima'
                  ? 'Diterima pemeriksa'
                  : 'Dikembalikan untuk perbaikan',
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
          ),
        if (l['catatan_pemeriksa'] != null)
          Padding(
            padding: const EdgeInsets.only(top: 8),
            child: Text('Catatan pemeriksa: ${l['catatan_pemeriksa']}'),
          ),
        for (final f in suratItems(l['bukti']))
          _file(f, 'tindak-lanjut-surat/${widget.id}/bukti/${f['id']}'),
        const Divider(height: 32),
      ],
      ..._history(suratItems(data['riwayat'])),
    ];
  }

  Widget _file(SuratData file, String path, {bool old = false}) => ListTile(
    contentPadding: EdgeInsets.zero,
    leading: const Icon(Icons.description_outlined),
    title: Text(suratText(file['nama'])),
    subtitle: Text(
      '${(suratInt(file['ukuran_byte']) / 1024).ceil()} KB${old ? ' - Versi lama' : ''}',
    ),
    trailing: IconButton(
      tooltip: 'Unduh ${suratText(file['nama'])}',
      onPressed: _busy ? null : () => _download(path, file),
      icon: const Icon(Icons.download_outlined),
    ),
  );
  _Action _decisionButton(String action, SuratData data, bool task) {
    final label = switch (action) {
      'sahkan' => 'Sahkan surat',
      'tarik' => 'Tarik pengajuan',
      'kembalikan' => 'Kembalikan',
      'kirim' => 'Catat pengiriman',
      'batal' => task ? 'Batalkan tugas' : 'Batalkan surat',
      'terima' => 'Terima laporan',
      _ => action,
    };
    return _Action(
      label,
      switch (action) {
        'sahkan' || 'terima' => Icons.verified_outlined,
        'batal' => Icons.cancel_outlined,
        'kirim' => Icons.outbox_outlined,
        _ => Icons.undo,
      },
      () => _run(
        (repo, account, d) => suratDecision(
          context,
          repo,
          account,
          d,
          task ? 'tindak-lanjut-surat' : 'persuratan',
          action,
          label,
        ),
      ),
    );
  }

  Widget _buttons(List<_Action> actions) => LayoutBuilder(
    builder: (context, c) => Wrap(
      spacing: 12,
      runSpacing: 12,
      children: [
        for (final a in actions)
          SizedBox(
            width: c.maxWidth >= 420 ? (c.maxWidth - 12) / 2 : c.maxWidth,
            child: OutlinedButton.icon(
              onPressed: _busy ? null : a.run,
              icon: Icon(a.icon),
              label: Text(a.label),
            ),
          ),
      ],
    ),
  );
  List<Widget> _history(List<SuratData> entries) => entries.isEmpty
      ? []
      : [
          const SuratHeading('Riwayat'),
          for (final e in entries)
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.history),
              title: Text(_historyLabel(suratText(e['aksi']))),
              subtitle: Text(
                '${suratDate(e['waktu'])} - ${suratText(suratMap(e['pengguna'])['nama'])}${e['catatan'] == null ? '' : '\n${e['catatan']}'}',
              ),
            ),
        ];
  String _historyLabel(String action) => switch (action) {
    'dicatat' => 'Surat dicatat',
    'diperbarui' => 'Data diperbarui',
    'ajukan' => 'Diajukan',
    'sahkan' => 'Surat disahkan',
    'dibaca' || 'baca' => 'Dikonfirmasi dibaca',
    'kirim' => 'Pengiriman dicatat',
    'batal' || 'dibatalkan' => 'Dibatalkan',
    'kembalikan' => 'Dikembalikan',
    'tarik' => 'Pengajuan ditarik',
    'disposisi' => 'Disposisi dibuat',
    'ditugaskan' => 'Pegawai ditugaskan',
    'mulai' => 'Pengerjaan dimulai',
    'ubah' => 'Instruksi diperbarui',
    'terima' => 'Laporan diterima',
    'digantikan' => 'Digantikan revisi',
    'progres' => 'Progres disimpan',
    'laporan' => 'Laporan disimpan',
    _ => action.replaceAll('_', ' '),
  };
}

class _Action {
  const _Action(this.label, this.icon, this.run);
  final String label;
  final IconData icon;
  final VoidCallback run;
}
