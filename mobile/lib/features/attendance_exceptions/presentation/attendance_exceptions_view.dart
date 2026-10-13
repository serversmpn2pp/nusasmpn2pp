import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/attendance_exceptions/data/attendance_exceptions_repository.dart';
import 'package:nusa/features/attendance_exceptions/domain/attendance_exception.dart';
import 'package:nusa/features/attendance_exceptions/presentation/attendance_exception_form_view.dart';
import 'package:nusa/features/attendance_exceptions/presentation/attendance_exception_widgets.dart';
import 'package:nusa/features/attendance_exceptions/presentation/attendance_exception_list_widgets.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class AttendanceExceptionsView extends ConsumerStatefulWidget {
  const AttendanceExceptionsView({super.key});
  @override
  ConsumerState<AttendanceExceptionsView> createState() =>
      _AttendanceExceptionsViewState();
}

class _AttendanceExceptionsViewState
    extends ConsumerState<AttendanceExceptionsView> {
  final _search = TextEditingController();
  final _items = <ExceptionData>[];
  ExceptionData? _references;
  ExceptionData? _page;
  String _year = '';
  String _status = 'semua';
  bool _loading = true;
  bool _creating = false;
  int _request = 0;
  int? _account;
  Timer? _debounce;
  Object? _error;
  @override
  void initState() {
    super.initState();
    Future.microtask(_load);
  }

  @override
  void dispose() {
    _search.dispose();
    _debounce?.cancel();
    super.dispose();
  }

  Future<void> _load({bool append = false}) async {
    if (!mounted) return;
    final user = ref.read(authControllerProvider).value?.session?.pengguna;
    if (!canManageAttendanceExceptions(user)) {
      setState(() {
        _loading = false;
        _items.clear();
        _references = _page = null;
      });
      return;
    }
    final request = ++_request;
    final account = user!.id;
    setState(() {
      if (_account != account) _references = null;
      _account = account;
      _loading = true;
      _error = null;
      if (!append) {
        _items.clear();
        _page = null;
      }
    });
    bool current() =>
        mounted &&
        request == _request &&
        account == ref.read(authControllerProvider).value?.session?.pengguna.id;
    try {
      final repo = ref.read(attendanceExceptionsRepositoryProvider);
      final reference = _references ?? await repo.references();
      if (!current()) return;
      final page = await repo.list({
        'halaman': append
            ? exceptionInt(exceptionMap(_page?['paginasi'])['halaman']) + 1
            : 1,
        'per_halaman': 20,
        'status': _status,
        if (_year.isNotEmpty) 'tahun_pelajaran_id': int.parse(_year),
        if (_search.text.trim().isNotEmpty) 'kata_kunci': _search.text.trim(),
      });
      if (!current()) return;
      setState(() {
        _references = reference;
        _page = page;
        _items.addAll(exceptionItems(page['items']));
        _loading = false;
      });
    } catch (e) {
      if (current()) {
        setState(() {
          _error = e;
          _loading = false;
        });
      }
    }
  }

  void _change(VoidCallback assign) {
    _debounce?.cancel();
    setState(assign);
    _load();
  }

  Future<void> _create() async {
    if (_creating || _references == null) return;
    final account = ref
        .read(authControllerProvider)
        .value
        ?.session
        ?.pengguna
        .id;
    if (account == null || account != _account) return;
    final reference = _references!;
    setState(() => _creating = true);
    final result = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => AttendanceExceptionFormView(
          reference: reference,
          accountId: account,
        ),
      ),
    );
    if (!mounted) return;
    setState(() => _creating = false);
    if (result == true &&
        account ==
            ref.read(authControllerProvider).value?.session?.pengguna.id) {
      await _load();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'Pengecualian diterapkan. Periksa ulang rekap rapor yang telah disimpan.',
            ),
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    ref.listen(authControllerProvider, (old, next) {
      if (old?.value?.session?.pengguna.id !=
          next.value?.session?.pengguna.id) {
        ++_request;
        _search.clear();
        _year = '';
        _status = 'semua';
        _load();
      }
    });
    final user = ref.watch(authControllerProvider).value?.session?.pengguna;
    final allowed = canManageAttendanceExceptions(user);
    final visible = _account == user?.id;
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text(
          'Pengecualian Presensi',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        actions: [
          if (allowed)
            IconButton(
              tooltip: 'Perbarui',
              onPressed: _loading || _creating ? null : _load,
              icon: const Icon(Icons.refresh_rounded),
            ),
        ],
      ),
      floatingActionButton:
          allowed &&
              visible &&
              _references != null &&
              exceptionMap(_references!['hak_akses'])['dapat_kelola'] == true
          ? FloatingActionButton.extended(
              key: const Key('add-attendance-exception'),
              tooltip: 'Tambah pengecualian',
              onPressed: _creating || _loading ? null : _create,
              icon: const Icon(Icons.add_rounded),
              label: Text(
                MediaQuery.sizeOf(context).width >= 380 &&
                        MediaQuery.textScalerOf(context).scale(14) <= 18
                    ? 'Tambah Pengecualian'
                    : 'Tambah',
              ),
            )
          : null,
      body: !allowed
          ? const Center(
              child: Text('Menu ini tidak tersedia untuk akun Anda.'),
            )
          : SafeArea(
              top: false,
              child: RefreshIndicator(
                onRefresh: _load,
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 96),
                  physics: const AlwaysScrollableScrollPhysics(),
                  children: [
                    AttendanceExceptionSummary(
                      summary: visible && _page != null
                          ? exceptionMap(_page!['ringkasan'])
                          : null,
                    ),
                    const SizedBox(height: 10),
                    const AttendanceExceptionInfoBanner(),
                    const SizedBox(height: 10),
                    TextField(
                      key: const Key('exception-search'),
                      controller: _search,
                      maxLength: 100,
                      decoration: const InputDecoration(
                        hintText: 'Cari alasan pengecualian',
                        prefixIcon: Icon(Icons.search_rounded),
                        counterText: '',
                      ),
                      onChanged: (_) {
                        _debounce?.cancel();
                        ++_request;
                        _debounce = Timer(
                          const Duration(milliseconds: 450),
                          _load,
                        );
                      },
                    ),
                    if (visible) ...[
                      const SizedBox(height: 12),
                      NusaDropdownField<String>(
                        fieldKey: const Key('exception-filter-year'),
                        value: _year,
                        enabled: !_loading && !_creating && _references != null,
                        options: [
                          const NusaDropdownOption(
                            value: '',
                            label: 'Semua tahun pelajaran',
                          ),
                          for (final t in exceptionItems(
                            _references?['tahun_pelajaran'],
                          ))
                            NusaDropdownOption(
                              value: '${t['id']}',
                              label: exceptionText(t['nama']),
                            ),
                        ],
                        decoration: const InputDecoration(
                          labelText: 'Tahun pelajaran',
                          prefixIcon: Icon(
                            Icons.calendar_today_outlined,
                            size: 20,
                          ),
                        ),
                        onChanged: (v) => _change(() => _year = v ?? ''),
                      ),
                      const SizedBox(height: 12),
                      NusaDropdownField<String>(
                        fieldKey: const Key('exception-filter-status'),
                        value: _status,
                        enabled: !_loading && !_creating && _references != null,
                        options: const [
                          NusaDropdownOption(
                            value: 'semua',
                            label: 'Semua status',
                          ),
                          NusaDropdownOption(value: 'aktif', label: 'Aktif'),
                          NusaDropdownOption(
                            value: 'dibatalkan',
                            label: 'Dibatalkan',
                          ),
                        ],
                        decoration: const InputDecoration(
                          labelText: 'Status',
                          prefixIcon: Icon(Icons.toggle_on_outlined, size: 20),
                        ),
                        onChanged: (v) => _change(() => _status = v ?? 'semua'),
                      ),
                      if (_year.isNotEmpty ||
                          _status != 'semua' ||
                          _search.text.trim().isNotEmpty)
                        Align(
                          alignment: Alignment.centerRight,
                          child: TextButton.icon(
                            key: const Key('exception-reset-filters'),
                            label: const Text('Reset filter'),
                            icon: const Icon(
                              Icons.filter_alt_off_outlined,
                              size: 18,
                            ),
                            onPressed: _loading || _creating
                                ? null
                                : () => _change(() {
                                    _year = '';
                                    _status = 'semua';
                                    _search.clear();
                                  }),
                          ),
                        ),
                    ],
                    const SizedBox(height: 14),
                    if (_loading)
                      const Padding(
                        padding: EdgeInsets.all(24),
                        child: Center(child: CircularProgressIndicator()),
                      ),
                    if (_error != null)
                      ExceptionErrorView(_error!, retry: _load),
                    if (!_loading && _error == null && _items.isEmpty)
                      const Padding(
                        padding: EdgeInsets.all(32),
                        child: Text(
                          'Belum ada pengecualian yang sesuai.',
                          textAlign: TextAlign.center,
                        ),
                      ),
                    if (visible)
                      for (final item in _items)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: AttendanceExceptionCard(
                            item: item,
                            onOpen: _creating
                                ? null
                                : () async {
                                    await context.push(
                                      '/pengecualian-presensi/${item['id']}',
                                    );
                                    if (mounted) _load();
                                  },
                          ),
                        ),
                    if (!_loading &&
                        _error == null &&
                        exceptionMap(
                              _page?['paginasi'],
                            )['ada_halaman_berikutnya'] ==
                            true)
                      OutlinedButton.icon(
                        onPressed: () => _load(append: true),
                        icon: const Icon(Icons.expand_more),
                        label: const Text('Muat berikutnya'),
                      ),
                  ],
                ),
              ),
            ),
    );
  }
}

class AttendanceExceptionDetailView extends ConsumerStatefulWidget {
  const AttendanceExceptionDetailView({required this.id, super.key});
  final int id;
  @override
  ConsumerState<AttendanceExceptionDetailView> createState() =>
      _AttendanceExceptionDetailViewState();
}

class _AttendanceExceptionDetailViewState
    extends ConsumerState<AttendanceExceptionDetailView> {
  ExceptionData? _data;
  Object? _error;
  bool _loading = true;
  bool _busy = false;
  int? _account;
  int _request = 0;
  @override
  void initState() {
    super.initState();
    Future.microtask(_load);
  }

  @override
  void didUpdateWidget(covariant AttendanceExceptionDetailView oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.id != widget.id) _load();
  }

  Future<void> _load() async {
    if (!mounted) return;
    final user = ref.read(authControllerProvider).value?.session?.pengguna;
    if (!canManageAttendanceExceptions(user)) {
      setState(() {
        _data = null;
        _loading = false;
      });
      return;
    }
    final request = ++_request;
    setState(() {
      _account = user!.id;
      _data = null;
      _loading = true;
      _error = null;
    });
    try {
      if (widget.id <= 0) throw ArgumentError('id');
      final data = await ref
          .read(attendanceExceptionsRepositoryProvider)
          .detail(widget.id);
      if (mounted &&
          request == _request &&
          user!.id ==
              ref.read(authControllerProvider).value?.session?.pengguna.id) {
        setState(() {
          _data = data;
          _loading = false;
        });
      }
    } catch (e) {
      if (mounted && request == _request) {
        setState(() {
          _error = e;
          _loading = false;
        });
      }
    }
  }

  Future<void> _cancel() async {
    if (_busy || _data == null) return;
    final account = ref
        .read(authControllerProvider)
        .value
        ?.session
        ?.pengguna
        .id;
    if (account == null || account != _account) return;
    final record = _data!;
    setState(() => _busy = true);
    final result = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) =>
            AttendanceExceptionCancelView(record: record, accountId: account),
      ),
    );
    if (!mounted) return;
    setState(() => _busy = false);
    if (result == true) await _load();
  }

  @override
  Widget build(BuildContext context) {
    ref.listen(authControllerProvider, (old, next) {
      if (old?.value?.session?.pengguna.id !=
          next.value?.session?.pengguna.id) {
        ++_request;
        _load();
      }
    });
    final user = ref.watch(authControllerProvider).value?.session?.pengguna;
    final data = _account == user?.id ? _data : null;
    return PopScope(
      canPop: !_busy,
      child: Scaffold(
        appBar: exceptionAppBar('Detail Pengecualian'),
        body: !canManageAttendanceExceptions(user)
            ? const Center(
                child: Text('Menu ini tidak tersedia untuk akun Anda.'),
              )
            : _loading
            ? const Center(child: CircularProgressIndicator())
            : _error != null
            ? Center(child: ExceptionErrorView(_error!, retry: _load))
            : data == null
            ? const SizedBox.shrink()
            : SafeArea(
                child: ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    Align(
                      alignment: Alignment.centerLeft,
                      child: ExceptionStatus(data['aktif'] == true),
                    ),
                    const SizedBox(height: 16),
                    Text(
                      exceptionText(data['jenis_label']),
                      style: const TextStyle(
                        fontSize: 22,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 20),
                    ExceptionFacts({
                      'Cakupan': data['cakupan_label'],
                      'Tahun pelajaran': exceptionMap(
                        data['tahun_pelajaran'],
                      )['nama'],
                      'Mulai': exceptionDate(data['tanggal_mulai']),
                      'Selesai': exceptionDate(data['tanggal_selesai']),
                    }),
                    const ExceptionHeading('Alasan penetapan'),
                    Text(exceptionText(data['alasan'])),
                    const ExceptionHeading('Dampak saat penetapan'),
                    ExceptionFacts({
                      for (final e in exceptionImpactLabels.entries)
                        e.value: exceptionMap(data['dampak_pratinjau'])[e.key],
                    }),
                    const ExceptionHeading('Riwayat'),
                    ExceptionFacts({
                      'Ditetapkan oleh': exceptionMap(data['pembuat'])['nama'],
                      'Ditetapkan pada': exceptionDate(data['dibuat_pada']),
                      if (data['aktif'] != true) ...{
                        'Dibatalkan oleh': exceptionMap(
                          data['pembatal'],
                        )['nama'],
                        'Dibatalkan pada': exceptionDate(
                          data['dibatalkan_pada'],
                        ),
                      },
                    }),
                    if (data['alasan_pembatalan'] != null) ...[
                      const ExceptionHeading('Alasan pembatalan'),
                      Text(exceptionText(data['alasan_pembatalan'])),
                    ],
                    if (exceptionMap(data['hak_akses'])['dapat_batalkan'] ==
                        true) ...[
                      const Divider(height: 36),
                      OutlinedButton.icon(
                        key: const Key('exception-cancel'),
                        onPressed: _busy ? null : _cancel,
                        icon: const Icon(Icons.cancel_outlined),
                        label: const Text('Batalkan pengecualian'),
                      ),
                    ],
                  ],
                ),
              ),
      ),
    );
  }
}
