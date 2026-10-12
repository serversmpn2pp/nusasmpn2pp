import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/attendance_exceptions/data/attendance_exceptions_repository.dart';
import 'package:nusa/features/attendance_exceptions/domain/attendance_exception.dart';
import 'package:nusa/features/attendance_exceptions/presentation/attendance_exception_widgets.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class AttendanceExceptionFormView extends ConsumerStatefulWidget {
  const AttendanceExceptionFormView({
    required this.reference,
    required this.accountId,
    super.key,
  });
  final ExceptionData reference;
  final int accountId;
  @override
  ConsumerState<AttendanceExceptionFormView> createState() =>
      _AttendanceExceptionFormViewState();
}

class _AttendanceExceptionFormViewState
    extends ConsumerState<AttendanceExceptionFormView> {
  final _form = GlobalKey<FormState>();
  final _reason = TextEditingController();
  String? _year;
  String _class = '';
  String? _kind;
  DateTime? _start;
  DateTime? _end;
  bool _busy = false;
  String? _error;
  List<ExceptionData> get _years =>
      exceptionItems(widget.reference['tahun_pelajaran']);
  ExceptionData get _selectedYear =>
      _years.where((t) => '${t['id']}' == _year).firstOrNull ?? {};
  @override
  void initState() {
    super.initState();
    _year =
        (_years.where((t) => t['aktif'] == true).firstOrNull ??
                _years.firstOrNull)?['id']
            ?.toString();
    _kind = exceptionMap(widget.reference['jenis']).keys.firstOrNull;
    _resetDates();
  }

  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  void _resetDates() {
    final first = DateTime.tryParse('${_selectedYear['tanggal_mulai']}');
    final last = DateTime.tryParse('${_selectedYear['tanggal_selesai']}');
    final now = DateUtils.dateOnly(DateTime.now());
    final d = first == null || last == null
        ? null
        : now.isBefore(first)
        ? first
        : now.isAfter(last)
        ? last
        : now;
    _start = _end = d;
  }

  Future<void> _pickDate(bool start) async {
    final first = DateTime.tryParse('${_selectedYear['tanggal_mulai']}');
    final last = DateTime.tryParse('${_selectedYear['tanggal_selesai']}');
    if (first == null || last == null || last.isBefore(first)) {
      setState(
        () => _error =
            'Lengkapi tanggal awal dan akhir tahun pelajaran terlebih dahulu.',
      );
      return;
    }
    final date = await showDatePicker(
      context: context,
      initialDate: (start ? _start : _end) ?? first,
      firstDate: first,
      lastDate: last,
    );
    if (date != null && mounted) {
      setState(() {
        if (start) {
          _start = date;
        } else {
          _end = date;
        }
      });
    }
  }

  bool get _sameAccount =>
      ref.read(authControllerProvider).value?.session?.pengguna.id ==
          widget.accountId &&
      canManageAttendanceExceptions(
        ref.read(authControllerProvider).value?.session?.pengguna,
      );
  Future<void> _preview() async {
    if (_busy || !_sameAccount || !_form.currentState!.validate()) return;
    if (_year == null ||
        _kind == null ||
        _start == null ||
        _end == null ||
        _end!.isBefore(_start!)) {
      setState(
        () => _error =
            'Pilih tahun pelajaran, jenis, dan rentang tanggal yang valid.',
      );
      return;
    }
    FocusScope.of(context).unfocus();
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final preview = await ref
          .read(attendanceExceptionsRepositoryProvider)
          .preview({
            'tahun_pelajaran_id': int.parse(_year!),
            'kelas_id': _class.isEmpty ? null : int.parse(_class),
            'jenis': _kind,
            'tanggal_mulai': _start!.toIso8601String().substring(0, 10),
            'tanggal_selesai': _end!.toIso8601String().substring(0, 10),
            'alasan': _reason.text.trim(),
          });
      if (!mounted || !_sameAccount) return;
      final result = await Navigator.push<bool>(
        context,
        MaterialPageRoute(
          builder: (_) => AttendanceExceptionPreviewView(
            preview: preview,
            accountId: widget.accountId,
          ),
        ),
      );
      if (mounted && _sameAccount && result == true) {
        Navigator.pop(context, true);
      }
    } catch (e) {
      if (mounted && _sameAccount) setState(() => _error = exceptionError(e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final same =
        ref.watch(authControllerProvider).value?.session?.pengguna.id ==
            widget.accountId &&
        canManageAttendanceExceptions(
          ref.read(authControllerProvider).value?.session?.pengguna,
        );
    final classes = exceptionItems(widget.reference['kelas'])
        .where((k) => '${k['tahun_pelajaran_id']}' == _year);
    return PopScope(
      canPop: !_busy,
      child: Scaffold(
        appBar: exceptionAppBar('Tambah Pengecualian'),
        body: !same
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
                        NusaDropdownField<String>(
                          fieldKey: const Key('exception-year'),
                          value: _year,
                          options: [
                            for (final t in _years)
                              NusaDropdownOption(
                                value: '${t['id']}',
                                label: exceptionText(t['nama']),
                              ),
                          ],
                          decoration: const InputDecoration(
                            labelText: 'Tahun pelajaran *',
                          ),
                          enabled: !_busy,
                          onChanged: (v) => setState(() {
                            _year = v;
                            _class = '';
                            _resetDates();
                          }),
                        ),
                        const SizedBox(height: 16),
                        NusaDropdownField<String>(
                          fieldKey: const Key('exception-class'),
                          value: _class,
                          options: [
                            const NusaDropdownOption(
                              value: '',
                              label: 'Seluruh sekolah',
                            ),
                            for (final k in classes)
                              NusaDropdownOption(
                                value: '${k['id']}',
                                label: exceptionText(k['nama']),
                              ),
                          ],
                          decoration: const InputDecoration(
                            labelText: 'Cakupan *',
                          ),
                          enabled: !_busy,
                          onChanged: (v) => setState(() => _class = v ?? ''),
                        ),
                        const SizedBox(height: 16),
                        _dateField(
                          'exception-start',
                          'Tanggal mulai *',
                          _start,
                          true,
                        ),
                        const SizedBox(height: 16),
                        _dateField(
                          'exception-end',
                          'Tanggal selesai *',
                          _end,
                          false,
                        ),
                        const SizedBox(height: 16),
                        NusaDropdownField<String>(
                          fieldKey: const Key('exception-kind'),
                          value: _kind,
                          options: [
                            for (final e in exceptionMap(
                              widget.reference['jenis'],
                            ).entries)
                              NusaDropdownOption(
                                value: e.key,
                                label: '${e.value}',
                              ),
                          ],
                          decoration: const InputDecoration(
                            labelText: 'Jenis kejadian *',
                          ),
                          enabled: !_busy,
                          onChanged: (v) => setState(() => _kind = v),
                        ),
                        const SizedBox(height: 16),
                        TextFormField(
                          key: const Key('exception-reason'),
                          controller: _reason,
                          maxLines: 4,
                          maxLength: 1000,
                          enabled: !_busy,
                          decoration: const InputDecoration(
                            labelText: 'Alasan penetapan *',
                          ),
                          validator: (v) => (v?.trim().length ?? 0) < 10
                              ? 'Alasan minimal 10 karakter.'
                              : null,
                        ),
                        if (_error != null)
                          Padding(
                            padding: const EdgeInsets.symmetric(vertical: 12),
                            child: Text(
                              _error!,
                              style: const TextStyle(color: NusaColors.danger),
                            ),
                          ),
                        FilledButton.icon(
                          key: const Key('exception-preview'),
                          onPressed: _busy ? null : _preview,
                          icon: const Icon(Icons.preview_outlined),
                          label: Text(
                            _busy ? 'Memuat pratinjau...' : 'Pratinjau dampak',
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
      ),
    );
  }

  Widget _dateField(String key, String label, DateTime? date, bool start) =>
      InkWell(
        key: Key(key),
        onTap: _busy ? null : () => _pickDate(start),
        child: InputDecorator(
          decoration: InputDecoration(
            labelText: label,
            enabled: !_busy,
            suffixIcon: const Icon(Icons.calendar_today_outlined),
          ),
          child: Text(
            date == null
                ? 'Pilih tanggal'
                : exceptionDate(date.toIso8601String().substring(0, 10)),
          ),
        ),
      );
}

class AttendanceExceptionPreviewView extends ConsumerStatefulWidget {
  const AttendanceExceptionPreviewView({
    required this.preview,
    required this.accountId,
    super.key,
  });
  final ExceptionData preview;
  final int accountId;
  @override
  ConsumerState<AttendanceExceptionPreviewView> createState() =>
      _AttendanceExceptionPreviewViewState();
}

class _AttendanceExceptionPreviewViewState
    extends ConsumerState<AttendanceExceptionPreviewView> {
  final _search = TextEditingController();
  bool _confirmed = false;
  bool _saving = false;
  bool _expired = false;
  bool _affected = false;
  int _limit = 20;
  String? _error;
  bool get _same =>
      ref.read(authControllerProvider).value?.session?.pengguna.id ==
          widget.accountId &&
      canManageAttendanceExceptions(
        ref.read(authControllerProvider).value?.session?.pengguna,
      );
  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _apply() async {
    if (!_confirmed || _saving || _expired || !_same) return;
    final token = exceptionText(widget.preview['token'], '');
    if (token.isEmpty) {
      setState(() {
        _expired = true;
        _error = 'Pratinjau tidak valid. Buat pratinjau kembali.';
      });
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await ref.read(attendanceExceptionsRepositoryProvider).apply(token);
      if (mounted && _same) Navigator.pop(context, true);
    } catch (e) {
      if (mounted && _same) {
        setState(() {
          _error = exceptionError(e);
          _expired = e is ValidationException && e.errors.containsKey('token');
        });
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final same =
        ref.watch(authControllerProvider).value?.session?.pengguna.id ==
            widget.accountId &&
        canManageAttendanceExceptions(
          ref.read(authControllerProvider).value?.session?.pengguna,
        );
    final p = widget.preview;
    final values = exceptionMap(p['isian']);
    final query = _search.text.toLowerCase();
    final students = exceptionItems(p['siswa'])
        .where(
          (s) =>
              (!_affected || exceptionInt(s['alfa_dibatalkan']) > 0) &&
              '${s['nama']} ${s['kelas']}'.toLowerCase().contains(query),
        )
        .toList();
    return PopScope(
      canPop: !_saving,
      child: Scaffold(
        appBar: exceptionAppBar('Pratinjau Pengecualian'),
        body: !same
            ? const Center(
                child: Text('Sesi akun telah berubah. Buat pratinjau kembali.'),
              )
            : SafeArea(
                child: Column(
                  children: [
                    Expanded(
                      child: ListView(
                        padding: const EdgeInsets.all(16),
                        children: [
                          ExceptionFacts({
                            'Tahun pelajaran': exceptionMap(
                              p['tahun_pelajaran'],
                            )['nama'],
                            'Cakupan': p['cakupan_label'],
                            'Jenis': p['jenis_label'],
                            'Mulai': exceptionDate(values['tanggal_mulai']),
                            'Selesai': exceptionDate(values['tanggal_selesai']),
                          }),
                          const ExceptionHeading('Alasan penetapan'),
                          Text(exceptionText(values['alasan'])),
                          const ExceptionHeading('Dampak pengecualian'),
                          ExceptionFacts({
                            for (final e in exceptionImpactLabels.entries)
                              e.value: exceptionMap(p['dampak'])[e.key],
                          }),
                          const ExceptionHeading('Perhatian'),
                          for (final warning
                              in (p['peringatan'] as List? ?? const []))
                            Padding(
                              padding: const EdgeInsets.only(bottom: 8),
                              child: Text('$warning'),
                            ),
                          Text(
                            'Pratinjau berlaku sampai ${exceptionDate(p['berlaku_sampai'])}',
                            style: const TextStyle(
                              color: NusaColors.textSecondary,
                            ),
                          ),
                          const ExceptionHeading('Siswa dalam cakupan'),
                          TextField(
                            key: const Key('exception-preview-search'),
                            controller: _search,
                            decoration: const InputDecoration(
                              labelText: 'Cari siswa atau kelas',
                              prefixIcon: Icon(Icons.search),
                            ),
                            enabled: !_saving,
                            onChanged: (_) => setState(() => _limit = 20),
                          ),
                          Align(
                            alignment: Alignment.centerLeft,
                            child: FilterChip(
                              label: const Text('Hanya terdampak'),
                              selected: _affected,
                              onSelected: _saving
                                  ? null
                                  : (v) => setState(() {
                                      _affected = v;
                                      _limit = 20;
                                    }),
                            ),
                          ),
                          Text('${students.length} siswa'),
                          for (final s in students.take(_limit))
                            ListTile(
                              contentPadding: EdgeInsets.zero,
                              title: Text(exceptionText(s['nama'])),
                              subtitle: Text(
                                '${s['kelas']} - Absen ${exceptionText(s['nomor_absen'])}',
                              ),
                              trailing: SizedBox(
                                width: 44,
                                child: Text(
                                  '${exceptionInt(s['alfa_dibatalkan'])}',
                                  textAlign: TextAlign.right,
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ),
                            ),
                          if (students.isEmpty)
                            const Padding(
                              padding: EdgeInsets.all(16),
                              child: Text('Tidak ada siswa yang sesuai.'),
                            ),
                          if (students.length > _limit)
                            OutlinedButton.icon(
                              onPressed: _saving
                                  ? null
                                  : () => setState(() => _limit += 20),
                              icon: const Icon(Icons.expand_more),
                              label: const Text('Muat siswa berikutnya'),
                            ),
                        ],
                      ),
                    ),
                    const Divider(height: 1),
                    Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          if (_error != null)
                            Text(
                              _error!,
                              key: const Key('exception-preview-error'),
                              style: const TextStyle(color: NusaColors.danger),
                            ),
                          if (_expired)
                            OutlinedButton.icon(
                              onPressed: _saving
                                  ? null
                                  : () => Navigator.pop(context, false),
                              icon: const Icon(Icons.refresh),
                              label: const Text('Buat pratinjau baru'),
                            )
                          else ...[
                            CheckboxListTile(
                              key: const Key('exception-confirm'),
                              contentPadding: EdgeInsets.zero,
                              title: const Text(
                                'Saya telah memeriksa cakupan dan dampak pengecualian.',
                              ),
                              value: _confirmed,
                              onChanged: _saving
                                  ? null
                                  : (v) =>
                                        setState(() => _confirmed = v == true),
                            ),
                            FilledButton.icon(
                              key: const Key('exception-apply'),
                              onPressed: _saving || !_confirmed ? null : _apply,
                              icon: const Icon(Icons.check_circle_outline),
                              label: Text(
                                _saving
                                    ? 'Menerapkan...'
                                    : 'Terapkan pengecualian',
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ],
                ),
              ),
      ),
    );
  }
}

class AttendanceExceptionCancelView extends ConsumerStatefulWidget {
  const AttendanceExceptionCancelView({
    required this.record,
    required this.accountId,
    super.key,
  });
  final ExceptionData record;
  final int accountId;
  @override
  ConsumerState<AttendanceExceptionCancelView> createState() =>
      _AttendanceExceptionCancelViewState();
}

class _AttendanceExceptionCancelViewState
    extends ConsumerState<AttendanceExceptionCancelView> {
  final _form = GlobalKey<FormState>();
  final _reason = TextEditingController();
  bool _confirmed = false;
  bool _saving = false;
  String? _error;
  bool get _same =>
      ref.read(authControllerProvider).value?.session?.pengguna.id ==
          widget.accountId &&
      canManageAttendanceExceptions(
        ref.read(authControllerProvider).value?.session?.pengguna,
      );
  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (_saving || !_same || !_confirmed || !_form.currentState!.validate()) {
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await ref
          .read(attendanceExceptionsRepositoryProvider)
          .cancel(exceptionInt(widget.record['id']), _reason.text.trim());
      if (mounted && _same) Navigator.pop(context, true);
    } catch (e) {
      if (mounted && _same) setState(() => _error = exceptionError(e));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final same =
        ref.watch(authControllerProvider).value?.session?.pengguna.id ==
            widget.accountId &&
        canManageAttendanceExceptions(
          ref.read(authControllerProvider).value?.session?.pengguna,
        );
    return PopScope(
      canPop: !_saving,
      child: Scaffold(
        appBar: exceptionAppBar('Batalkan Pengecualian'),
        body: !same
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
                        ExceptionFacts({
                          'Cakupan': widget.record['cakupan_label'],
                          'Mulai': exceptionDate(
                            widget.record['tanggal_mulai'],
                          ),
                          'Selesai': exceptionDate(
                            widget.record['tanggal_selesai'],
                          ),
                        }),
                        const SizedBox(height: 24),
                        TextFormField(
                          key: const Key('exception-cancel-reason'),
                          controller: _reason,
                          enabled: !_saving,
                          maxLength: 1000,
                          maxLines: 4,
                          decoration: const InputDecoration(
                            labelText: 'Alasan pembatalan *',
                          ),
                          validator: (v) => (v?.trim().length ?? 0) < 10
                              ? 'Alasan minimal 10 karakter.'
                              : null,
                        ),
                        CheckboxListTile(
                          key: const Key('exception-confirm-cancel'),
                          contentPadding: EdgeInsets.zero,
                          title: const Text(
                            'Saya memahami perhitungan alfa otomatis kembali berlaku.',
                          ),
                          value: _confirmed,
                          onChanged: _saving
                              ? null
                              : (v) => setState(() => _confirmed = v == true),
                        ),
                        if (_error != null)
                          Padding(
                            padding: const EdgeInsets.symmetric(vertical: 12),
                            child: Text(
                              _error!,
                              style: const TextStyle(color: NusaColors.danger),
                            ),
                          ),
                        FilledButton.icon(
                          key: const Key('exception-save-cancel'),
                          onPressed: _saving || !_confirmed ? null : _save,
                          icon: const Icon(Icons.cancel_outlined),
                          label: Text(
                            _saving
                                ? 'Membatalkan...'
                                : 'Batalkan pengecualian',
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
      ),
    );
  }
}
