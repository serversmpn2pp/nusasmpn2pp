import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/sts_behavior/data/sts_behavior_remote_data_source.dart';
import 'package:nusa/features/sts_behavior/domain/sts_behavior.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

String _errorMessage(Object error) => error is AppException
    ? error.message
    : 'Lampiran belum dapat dimuat. Periksa koneksi atau hubungi admin sekolah.';

class StsBehaviorView extends ConsumerStatefulWidget {
  const StsBehaviorView({super.key});
  @override
  ConsumerState<StsBehaviorView> createState() => _StsBehaviorViewState();
}

class _StsBehaviorViewState extends ConsumerState<StsBehaviorView> {
  StsBehaviorPage? _page;
  String? _error;
  bool _busy = false;
  final _selected = <int>{};

  @override
  void initState() {
    super.initState();
    Future.microtask(_load);
  }

  Future<void> _load({int? activityId, int? classId}) async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final page = await ref
          .read(stsBehaviorRemoteDataSourceProvider)
          .fetch(activityId: activityId, classId: classId);
      if (!mounted) return;
      setState(() {
        _page = page;
        _selected.clear();
      });
    } catch (error) {
      if (mounted) {
        setState(() {
          _page = null;
          _error = _errorMessage(error);
        });
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _refresh() =>
      _load(activityId: _page?.activityId, classId: _page?.classId);

  Future<void> _changeContent(String choice) async {
    final page = _page!;
    final report = page.report!;
    if (choice == report.content) return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Ubah isi lampiran?'),
        content: const Text(
          'Perubahan isi membuat pemeriksaan sebelumnya perlu diperiksa kembali. Sumber kejadian dan poin tidak diubah.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Batal'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Ubah'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() => _busy = true);
    try {
      await ref
          .read(stsBehaviorRemoteDataSourceProvider)
          .saveContent(
            page.activityId!,
            page.classId!,
            report.contentPayload(choice),
          );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Pilihan isi disimpan. Periksa kembali lampiran.'),
        ),
      );
      await _refresh();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(_errorMessage(error))));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _review(List<StsBehaviorStudent> students) async {
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => StsBehaviorReviewView(page: _page!, students: students),
      ),
    );
    if (changed == true && mounted) await _refresh();
  }

  @override
  Widget build(BuildContext context) {
    final page = _page;
    final report = page?.report;
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Lampiran Perilaku STS'),
        actions: [
          IconButton(
            key: const Key('sts-refresh'),
            tooltip: 'Muat ulang',
            onPressed: _busy ? null : _refresh,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: _busy
            ? const Center(child: CircularProgressIndicator())
            : _error != null
            ? Center(
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(_error!, textAlign: TextAlign.center),
                      TextButton(
                        onPressed: _refresh,
                        child: const Text('Coba lagi'),
                      ),
                    ],
                  ),
                ),
              )
            : page == null
            ? const SizedBox.shrink()
            : ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  _selector(
                    'sts-activity',
                    'Kegiatan STS',
                    page.activityId,
                    page.activities,
                    (id) => _load(activityId: id),
                  ),
                  const SizedBox(height: 12),
                  _selector(
                    'sts-class',
                    'Kelas',
                    page.classId,
                    page.classes,
                    (id) => _load(activityId: page.activityId, classId: id),
                  ),
                  if (report == null)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 24),
                      child: Text(
                        'Belum ada kegiatan STS atau kelas dalam cakupan tugas Anda.',
                      ),
                    ),
                  if (report != null) ...[
                    const SizedBox(height: 16),
                    Text(
                      'Periode ${report.start} — ${report.end}\nTanggal rapor: ${report.reportDate}',
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      'Ringkasan untuk lampiran rapor; bukan kronologi atau catatan rahasia BK. Pencetakan rapor gabungan tetap melalui wali kelas di web.',
                    ),
                    for (final notice in report.notices)
                      Padding(
                        padding: const EdgeInsets.only(top: 8),
                        child: Text(
                          notice,
                          style: TextStyle(
                            color: Theme.of(context).colorScheme.error,
                          ),
                        ),
                      ),
                    const SizedBox(height: 16),
                    NusaDropdownField<String>(
                      fieldKey: const Key('sts-content'),
                      value: report.content,
                      enabled: report.canChangeContent,
                      decoration: const InputDecoration(
                        labelText: 'Isi lampiran',
                      ),
                      options: [
                        for (final item in page.contentOptions.entries)
                          NusaDropdownOption(
                            value: item.key,
                            label: item.value,
                          ),
                      ],
                      onChanged: (choice) {
                        if (choice != null) _changeContent(choice);
                      },
                    ),
                    const SizedBox(height: 16),
                    Text(
                      '${report.students.where((s) => s.ready).length} dari ${report.students.length} lampiran siap',
                    ),
                    if (report.students.isEmpty)
                      const Padding(
                        padding: EdgeInsets.only(top: 16),
                        child: Text('Belum ada siswa aktif di kelas ini.'),
                      ),
                    if (report.canReview && report.students.isNotEmpty) ...[
                      CheckboxListTile(
                        contentPadding: EdgeInsets.zero,
                        title: const Text('Pilih semua siswa'),
                        value: _selected.length == report.students.length,
                        onChanged: (value) => setState(() {
                          _selected.clear();
                          if (value == true) {
                            _selected.addAll(
                              report.students.map((s) => s.memberId),
                            );
                          }
                        }),
                      ),
                      FilledButton.icon(
                        key: const Key('sts-review-selected'),
                        onPressed: _selected.isEmpty
                            ? null
                            : () => _review(
                                report.students
                                    .where(
                                      (s) => _selected.contains(s.memberId),
                                    )
                                    .toList(),
                              ),
                        icon: const Icon(Icons.fact_check_outlined),
                        label: Text(
                          'Periksa ${_selected.length} lampiran terpilih',
                        ),
                      ),
                    ],
                    for (final student in report.students)
                      Card(
                        child: Padding(
                          padding: const EdgeInsets.all(12),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              if (report.canReview)
                                CheckboxListTile(
                                  contentPadding: EdgeInsets.zero,
                                  controlAffinity:
                                      ListTileControlAffinity.leading,
                                  title: Text(
                                    '${student.attendanceNumber ?? '-'}. ${student.name}',
                                  ),
                                  value: _selected.contains(student.memberId),
                                  onChanged: (value) => setState(() {
                                    if (value == true) {
                                      _selected.add(student.memberId);
                                    } else {
                                      _selected.remove(student.memberId);
                                    }
                                  }),
                                )
                              else
                                Text(
                                  '${student.attendanceNumber ?? '-'}. ${student.name}',
                                ),
                              Text(
                                student.ready
                                    ? 'Siap · sudah diperiksa'
                                    : 'Belum siap · perlu pemeriksaan',
                              ),
                              Text(
                                '${student.incidentCount} kejadian · masuk ${student.pointsIn} · dikurangi ${student.pointsOut} · saldo ${student.balance}',
                              ),
                              TextButton(
                                key: Key('sts-student-${student.memberId}'),
                                onPressed: () => _review([student]),
                                child: Text(
                                  report.canReview
                                      ? 'Buka dan periksa'
                                      : 'Lihat ringkasan',
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                  ],
                ],
              ),
      ),
    );
  }

  Widget _selector(
    String key,
    String label,
    int? value,
    List<StsOption> options,
    ValueChanged<int?> onChanged,
  ) => NusaDropdownField<int>(
    fieldKey: Key(key),
    value: value,
    decoration: InputDecoration(labelText: label, hintText: 'Tidak tersedia'),
    options: [
      for (final option in options)
        NusaDropdownOption(value: option.id, label: option.label),
    ],
    onChanged: onChanged,
  );
}

class _StudentDraft {
  _StudentDraft(this.student)
    : note = TextEditingController(text: student.note),
      rows = {
        for (final row in student.rows)
          row.key: (
            incident: TextEditingController(text: row.incident),
            action: TextEditingController(text: row.action),
          ),
      };
  final StsBehaviorStudent student;
  final TextEditingController note;
  final Map<
    String,
    ({TextEditingController incident, TextEditingController action})
  >
  rows;
  bool confirmed = false;
  Map<String, dynamic> payload() => student.reviewPayload(
    editedRows: {
      for (final row in rows.entries)
        row.key: {
          'kejadian': row.value.incident.text.trim(),
          'tindakan': row.value.action.text.trim(),
        },
    },
    note: note.text,
  );
  void dispose() {
    note.dispose();
    for (final row in rows.values) {
      row.incident.dispose();
      row.action.dispose();
    }
  }
}

class StsBehaviorReviewView extends ConsumerStatefulWidget {
  const StsBehaviorReviewView({
    required this.page,
    required this.students,
    super.key,
  });
  final StsBehaviorPage page;
  final List<StsBehaviorStudent> students;
  @override
  ConsumerState<StsBehaviorReviewView> createState() =>
      _StsBehaviorReviewViewState();
}

class _StsBehaviorReviewViewState extends ConsumerState<StsBehaviorReviewView> {
  final _form = GlobalKey<FormState>();
  late final List<_StudentDraft> _drafts;
  int? _counselor, _deputy;
  bool _saving = false, _dirty = false, _allowPop = false, _needsReload = false;
  String? _error;
  StsBehaviorReport get _report => widget.page.report!;

  @override
  void initState() {
    super.initState();
    _drafts = widget.students.map(_StudentDraft.new).toList();
    final student = widget.students.length == 1 ? widget.students.single : null;
    _counselor = _initialSigner(_report.counselors, student?.counselorId);
    _deputy = _initialSigner(_report.deputies, student?.deputyId);
  }

  int? _initialSigner(List<StsOption> options, int? saved) =>
      options.any((option) => option.id == saved)
      ? saved
      : options.length == 1
      ? options.single.id
      : null;

  @override
  void dispose() {
    for (final draft in _drafts) {
      draft.dispose();
    }
    super.dispose();
  }

  Future<void> _leave() async {
    if (_saving) return;
    final discard =
        !_dirty ||
        await showDialog<bool>(
              context: context,
              builder: (context) => AlertDialog(
                title: const Text('Tutup pemeriksaan?'),
                content: const Text(
                  'Perubahan yang belum disimpan akan dibuang.',
                ),
                actions: [
                  TextButton(
                    onPressed: () => Navigator.pop(context, false),
                    child: const Text('Lanjutkan'),
                  ),
                  TextButton(
                    onPressed: () => Navigator.pop(context, true),
                    child: const Text('Buang perubahan'),
                  ),
                ],
              ),
            ) ==
            true;
    if (discard && mounted) {
      setState(() => _allowPop = true);
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) Navigator.pop(context, false);
      });
    }
  }

  Future<void> _save() async {
    if (_saving ||
        _needsReload ||
        !_report.canReview ||
        !_form.currentState!.validate()) {
      return;
    }
    if (_counselor == null ||
        _deputy == null ||
        _drafts.any((draft) => !draft.confirmed)) {
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      // Keep the original snapshot's versions/hash with its edits. Never silently
      // re-fetch a new fingerprint to force stale edits through the server check.
      await ref.read(stsBehaviorRemoteDataSourceProvider).saveReviews(
        widget.page.activityId!,
        widget.page.classId!,
        {
          'anggota_ids': _drafts
              .map((draft) => draft.student.memberId)
              .toList(),
          'guru_bk_id': _counselor,
          'wakil_kesiswaan_id': _deputy,
          'diperiksa': true,
          'siswa': {
            for (final draft in _drafts)
              '${draft.student.memberId}': draft.payload(),
          },
        },
      );
      if (!mounted) return;
      setState(() {
        _saving = false;
        _allowPop = true;
        _dirty = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${_drafts.length} lampiran telah diperiksa dan disimpan.',
          ),
        ),
      );
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) Navigator.pop(context, true);
      });
    } catch (error) {
      if (mounted) {
        setState(() {
          _saving = false;
          _needsReload = error is ValidationException;
          _error = _needsReload
              ? '${_errorMessage(error)} Tutup pemeriksaan, muat ulang daftar, lalu periksa data terbaru. Tidak ada penyimpanan otomatis.'
              : _errorMessage(error);
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final editable = _report.canReview && !_saving && !_needsReload;
    return PopScope<bool>(
      canPop: _allowPop || (!_dirty && !_saving),
      onPopInvokedWithResult: (didPop, result) {
        if (!didPop) _leave();
      },
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Periksa Lampiran STS'),
          leading: IconButton(
            onPressed: _saving ? null : _leave,
            icon: const Icon(Icons.arrow_back),
          ),
        ),
        body: SafeArea(
          top: false,
          child: Form(
            key: _form,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Text('Periode ${_report.start} — ${_report.end}'),
                const SizedBox(height: 8),
                const Text(
                  'Periksa setiap ringkasan sebelum menyimpan. Jangan mencantumkan rincian sensitif, kronologi lengkap, atau identitas siswa lain. Tanggal, status, dan poin hanya dapat dibaca.',
                ),
                const SizedBox(height: 16),
                _signer(
                  'sts-counselor',
                  'Guru BK',
                  _counselor,
                  _report.counselors,
                  editable,
                  (id) => setState(() {
                    _counselor = id;
                    _dirty = true;
                  }),
                ),
                const SizedBox(height: 12),
                _signer(
                  'sts-deputy',
                  'Wakil Kesiswaan',
                  _deputy,
                  _report.deputies,
                  editable,
                  (id) => setState(() {
                    _deputy = id;
                    _dirty = true;
                  }),
                ),
                for (final draft in _drafts) ...[
                  const SizedBox(height: 20),
                  Text(
                    '${draft.student.attendanceNumber ?? '-'}. ${draft.student.name}',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  Text(
                    '${draft.student.incidentCount} kejadian · masuk ${draft.student.pointsIn} · dikurangi ${draft.student.pointsOut} · saldo ${draft.student.balance}',
                  ),
                  if (draft.student.rows.isEmpty)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      child: Text(_report.emptyMessage),
                    ),
                  for (final row in draft.student.rows)
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              '${row.date} · ${row.status} · poin ${row.points ?? '-'}',
                            ),
                            const SizedBox(height: 8),
                            _text(
                              'sts-incident-${draft.student.memberId}-${row.key}',
                              'Ringkasan kejadian',
                              draft.rows[row.key]!.incident,
                              200,
                              editable,
                              required: true,
                            ),
                            const SizedBox(height: 8),
                            _text(
                              'sts-action-${draft.student.memberId}-${row.key}',
                              'Tindak lanjut',
                              draft.rows[row.key]!.action,
                              200,
                              editable,
                              required: true,
                            ),
                          ],
                        ),
                      ),
                    ),
                  _text(
                    'sts-note-${draft.student.memberId}',
                    'Catatan umum (opsional)',
                    draft.note,
                    600,
                    editable,
                  ),
                  if (_report.canReview)
                    CheckboxListTile(
                      key: Key('sts-confirm-${draft.student.memberId}'),
                      contentPadding: EdgeInsets.zero,
                      title: const Text(
                        'Saya telah memeriksa ringkasan siswa ini dan menyatakan layak disampaikan kepada orang tua/wali.',
                      ),
                      value: draft.confirmed,
                      onChanged: editable
                          ? (value) => setState(() {
                              draft.confirmed = value == true;
                              _dirty = true;
                            })
                          : null,
                    ),
                ],
                if (_error != null)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    child: Text(
                      _error!,
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.error,
                      ),
                    ),
                  ),
                if (_report.canReview) ...[
                  if (_counselor == null || _deputy == null)
                    const Text('Pilih kedua penandatangan secara eksplisit.'),
                  const SizedBox(height: 12),
                  FilledButton(
                    key: const Key('sts-save'),
                    onPressed:
                        editable &&
                            _counselor != null &&
                            _deputy != null &&
                            _drafts.every((draft) => draft.confirmed)
                        ? _save
                        : null,
                    child: Text(
                      _saving
                          ? 'Menyimpan…'
                          : 'Simpan ${_drafts.length} lampiran terperiksa',
                    ),
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _signer(
    String key,
    String label,
    int? value,
    List<StsOption> options,
    bool enabled,
    ValueChanged<int?> changed,
  ) => NusaDropdownField<int>(
    fieldKey: Key(key),
    value: value,
    enabled: enabled,
    decoration: InputDecoration(
      labelText: label,
      hintText: 'Pilih penandatangan',
    ),
    options: [
      for (final option in options)
        NusaDropdownOption(value: option.id, label: option.label),
    ],
    onChanged: changed,
  );

  Widget _text(
    String key,
    String label,
    TextEditingController controller,
    int maxLength,
    bool enabled, {
    bool required = false,
  }) => TextFormField(
    key: Key(key),
    controller: controller,
    readOnly: !enabled,
    maxLength: maxLength,
    minLines: 2,
    maxLines: 5,
    decoration: InputDecoration(labelText: label),
    onChanged: (_) => setState(() => _dirty = true),
    validator: (value) => required && (value == null || value.trim().isEmpty)
        ? 'Ringkasan wajib diisi.'
        : null,
  );
}
