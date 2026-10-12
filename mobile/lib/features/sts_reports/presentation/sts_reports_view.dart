import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/sts_reports/data/sts_reports_remote_data_source.dart';
import 'package:nusa/features/sts_reports/domain/sts_reports.dart';
import 'package:nusa/features/sts_reports/presentation/sts_report_widgets.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class RaporStsView extends StatelessWidget {
  const RaporStsView({super.key});
  @override
  Widget build(BuildContext context) =>
      const StsReportsView(kind: StsReportKind.report);
}

class LegerStsView extends StatelessWidget {
  const LegerStsView({super.key});
  @override
  Widget build(BuildContext context) =>
      const StsReportsView(kind: StsReportKind.ledger);
}

class StsReportsView extends ConsumerStatefulWidget {
  const StsReportsView({required this.kind, super.key});
  final StsReportKind kind;
  @override
  ConsumerState<StsReportsView> createState() => _StsReportsViewState();
}

class _StsReportsViewState extends ConsumerState<StsReportsView> {
  StsReportPage? _page;
  StsReportFilter _filter = const StsReportFilter();
  String? _error;
  bool _busy = false;
  int _request = 0;
  final _searchController = TextEditingController();
  String _query = '';
  String _status = 'semua';
  bool get _ledger => widget.kind == StsReportKind.ledger;

  @override
  void initState() {
    super.initState();
    Future.microtask(_refresh);
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _load(StsReportFilter filter, {bool changeScope = false}) async {
    final request = ++_request;
    if (!mounted) return;
    FocusScope.of(context).unfocus();
    setState(() {
      _filter = filter;
      _busy = true;
      _error = null;
      if (changeScope) {
        _searchController.clear();
        _query = '';
        _status = 'semua';
      }
    });
    try {
      final page = await ref
          .read(stsReportsRemoteDataSourceProvider)
          .fetch(widget.kind, filter);
      if (!mounted || request != _request) return;
      setState(() {
        _page = page;
        _filter = page.filter;
      });
    } catch (error) {
      if (!mounted || request != _request) return;
      setState(() {
        // Never leave a previous class's data on screen after a failed switch.
        _page = null;
        _error = error is AppException ? error.message : 'Data STS belum dapat dimuat. Coba lagi atau hubungi admin sekolah.';
      });
    } finally {
      if (mounted && request == _request) setState(() => _busy = false);
    }
  }

  Future<void> _refresh() => _load(_filter);

  void _openStudent(StsReportContext report, StsReportStudent student) {
    Navigator.of(context).push<void>(
      MaterialPageRoute(
        builder: (_) => StsReportStudentView(
          kind: widget.kind,
          report: report,
          student: student,
        ),
      ),
    );
  }

  void _info() => showModalBottomSheet<void>(
    context: context,
    showDragHandle: true,
    useSafeArea: true,
    isScrollControlled: true,
    builder: (context) => SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 4, 20, 28),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Tentang Rapor & Leger STS',
            style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 14),
          const StsReportNotice(
            message:
                'Mobile menampilkan ringkasan dari server. Pengaturan periode, '
                'mata pelajaran, koreksi kehadiran, pengecualian, dan pencetakan '
                'resmi rapor/leger tetap melalui web NUSA.',
          ),
          const SizedBox(height: 12),
          Text(
            _ledger
                ? 'Jumlah, rata-rata, urutan, dan ranking mengikuti backend. '
                      'Rata-rata leger memakai seluruh mapel terpilih sebagai pembagi. '
                      'Nilai belum lengkap atau masih draf membuat ranking sementara. '
                      'Nilai seri dapat memiliki ranking yang sama.'
                : 'Rapor hanya menampilkan nilai final. Nilai kosong berbeda dengan '
                      'nilai nol. Status siap cetak mengikuti periode, wali kelas, '
                      'dan pemeriksaan kehadiran di server; tidak berarti seluruh '
                      'nilai atau lampiran perilaku telah lengkap.',
            style: const TextStyle(fontSize: 13, height: 1.6),
          ),
          const SizedBox(height: 12),
          const Text(
            'Kelas dan tingkat yang tersedia mengikuti hak akses Anda. '
            'Tarik halaman atau tekan muat ulang untuk mengambil data terbaru.',
            style: TextStyle(fontSize: 13, height: 1.6),
          ),
        ],
      ),
    ),
  );

  @override
  Widget build(BuildContext context) {
    final page = _page;
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: Text(
          _ledger ? 'Leger STS' : 'Rapor STS',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        actions: [
          IconButton(
            key: const Key('sts-reports-info'),
            tooltip: 'Tentang rapor dan leger',
            onPressed: _info,
            icon: const Icon(Icons.info_outline_rounded),
          ),
          IconButton(
            key: const Key('sts-reports-refresh'),
            tooltip: 'Muat ulang',
            onPressed: _busy ? null : _refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: _busy
            ? const Center(child: CircularProgressIndicator())
            : _error != null
            ? _errorBody()
            : page == null
            ? const SizedBox.shrink()
            : RefreshIndicator(
                onRefresh: _refresh,
                child: ListView(
                  key: PageStorageKey(
                    'sts-reports-${widget.kind.name}-${_filter.activityId}-'
                    '${_filter.mode.name}-${_filter.mode == StsLedgerMode.grade ? _filter.grade : _filter.classId}',
                  ),
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.fromLTRB(16, 6, 16, 28),
                  children: _content(page),
                ),
              ),
      ),
    );
  }

  Widget _errorBody() => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(
            Icons.cloud_off_rounded,
            color: NusaColors.primary,
            size: 44,
          ),
          const SizedBox(height: 14),
          Text(_error!, textAlign: TextAlign.center),
          const SizedBox(height: 12),
          FilledButton.tonalIcon(
            onPressed: _refresh,
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Coba lagi'),
          ),
          TextButton(
            onPressed: () => _load(const StsReportFilter(), changeScope: true),
            child: const Text('Kembali ke pilihan awal'),
          ),
        ],
      ),
    ),
  );

  List<Widget> _content(StsReportPage page) {
    final report = page.context;
    final students =
        report?.students.where((student) {
          final matches =
              '${student.name} ${student.className} '
                      '${student.attendanceNumber ?? ''}'
                  .toLowerCase()
                  .contains(_query.toLowerCase());
          final statusMatches = switch (_status) {
            'belum_siap' => !student.ready,
            'siap' => student.ready,
            'belum_final' => !student.completeFinal,
            'belum_ranking' => student.rank == null,
            _ => true,
          };
          return matches && statusMatches;
        }).toList() ??
        [];
    return [
      StsReportSummaryCard(kind: widget.kind, report: report),
      const SizedBox(height: 14),
      _selector(
        'sts-reports-activity',
        'Kegiatan STS',
        _filter.activityId,
        page.activities,
        (id) => _load(
          StsReportFilter(activityId: id, mode: _filter.mode),
          changeScope: true,
        ),
      ),
      const SizedBox(height: 12),
      if (_ledger) ...[
        NusaDropdownField<StsLedgerMode>(
          fieldKey: const Key('sts-reports-mode'),
          value: _filter.mode,
          decoration: const InputDecoration(
            labelText: 'Cakupan leger',
            prefixIcon: Icon(Icons.layers_outlined, size: 20),
          ),
          options: const [
            NusaDropdownOption(
              value: StsLedgerMode.classGroup,
              label: 'Per kelas',
            ),
            NusaDropdownOption(
              value: StsLedgerMode.grade,
              label: 'Per tingkat',
            ),
          ],
          onChanged: (mode) {
            if (mode != null) {
              _load(
                StsReportFilter(
                  activityId: _filter.activityId,
                  classId: _filter.classId,
                  grade: _filter.grade,
                  mode: mode,
                ),
                changeScope: true,
              );
            }
          },
        ),
        const SizedBox(height: 12),
      ],
      if (_ledger && _filter.mode == StsLedgerMode.grade)
        _selector(
          'sts-reports-grade',
          'Tingkat',
          _filter.grade,
          page.grades,
          (id) => _load(
            StsReportFilter(
              activityId: _filter.activityId,
              grade: id,
              mode: StsLedgerMode.grade,
            ),
            changeScope: true,
          ),
        )
      else
        _selector(
          'sts-reports-class',
          'Kelas',
          _filter.classId,
          page.classes,
          (id) => _load(
            StsReportFilter(activityId: _filter.activityId, classId: id),
            changeScope: true,
          ),
        ),
      const SizedBox(height: 12),
      if (report == null)
        const StsReportNotice(
          message:
              'Belum ada kegiatan STS atau kelas dalam cakupan tugas Anda.',
        )
      else ...[
        if (_ledger) ..._ledgerContext(report) else ..._reportContext(report),
        const SizedBox(height: 18),
        TextField(
          key: const Key('sts-reports-search'),
          controller: _searchController,
          onChanged: (value) => setState(() => _query = value.trim()),
          decoration: const InputDecoration(
            hintText: 'Cari nama, kelas, atau nomor absen',
            prefixIcon: Icon(Icons.search_rounded),
          ),
        ),
        const SizedBox(height: 12),
        NusaDropdownField<String>(
          fieldKey: const Key('sts-reports-status'),
          value: _status,
          decoration: const InputDecoration(
            labelText: 'Tampilkan siswa',
            prefixIcon: Icon(Icons.filter_alt_outlined, size: 20),
          ),
          options: [
            const NusaDropdownOption(value: 'semua', label: 'Semua siswa'),
            if (_ledger) ...[
              const NusaDropdownOption(
                value: 'belum_final',
                label: 'Nilai belum lengkap final',
              ),
              const NusaDropdownOption(
                value: 'belum_ranking',
                label: 'Belum masuk ranking',
              ),
            ] else ...[
              const NusaDropdownOption(
                value: 'belum_siap',
                label: 'Belum siap cetak',
              ),
              const NusaDropdownOption(value: 'siap', label: 'Siap cetak'),
            ],
          ],
          onChanged: (value) {
            if (value != null) setState(() => _status = value);
          },
        ),
        const SizedBox(height: 18),
        Text(
          '${_ledger ? 'Ranking & Nilai' : 'Rapor Siswa'} · ${students.length} siswa',
          style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 10),
        if (students.isEmpty)
          StsReportNotice(
            message: report.students.isEmpty
                ? 'Belum ada siswa pada cakupan yang dipilih.'
                : 'Tidak ada siswa yang cocok dengan pencarian atau filter ini.',
          ),
        for (final student in students) ...[
          StsReportStudentCard(
            student: student,
            report: report,
            kind: widget.kind,
            onOpen: () => _openStudent(report, student),
          ),
          const SizedBox(height: 10),
        ],
      ],
    ];
  }

  Widget _selector(
    String key,
    String label,
    int? id,
    List<StsReportOption> options,
    ValueChanged<int> onChanged,
  ) => NusaDropdownField<int>(
    fieldKey: Key(key),
    value: id,
    decoration: InputDecoration(
      labelText: label,
      prefixIcon: Icon(
        label == 'Kegiatan STS'
            ? Icons.event_note_rounded
            : Icons.school_outlined,
        size: 20,
      ),
    ),
    options: [
      for (final option in options)
        NusaDropdownOption(value: option.id, label: option.label),
    ],
    onChanged: (value) {
      if (value != null) onChanged(value);
    },
  );

  List<Widget> _reportContext(StsReportContext report) => [
    if (report.period case final period?) ...[
      Card(
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Presensi: ${stsDate(period.start)} – ${stsDate(period.end)}',
                style: const TextStyle(fontSize: 12, height: 1.6),
              ),
              Text(
                'Tanggal rapor: ${stsDate(period.reportDate)}',
                style: const TextStyle(fontSize: 12, height: 1.6),
              ),
              Text(
                'Wali kelas: ${report.homeroomTeacher ?? 'Belum ditetapkan'}',
                style: const TextStyle(fontSize: 12, height: 1.6),
              ),
              const SizedBox(height: 6),
              Text(
                '${report.summary.attendanceReviewed}/${report.summary.studentCount} '
                'kehadiran telah diperiksa',
                style: const TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ),
      ),
      if (!period.saved || !period.ended || report.homeroomTeacher == null) ...[
        const SizedBox(height: 10),
        StsReportNotice(
          warning: true,
          message: [
            if (!period.saved) 'Periode rapor belum disimpan.',
            if (!period.ended) 'Periode presensi belum berakhir.',
            if (report.homeroomTeacher == null) 'Wali kelas belum ditetapkan.',
            'Lengkapi dan periksa kehadiran melalui web sebelum cetak resmi.',
          ].join(' '),
        ),
      ],
    ],
    const SizedBox(height: 10),
    const StsReportNotice(
      message:
          'Hanya nilai final ditampilkan. '
          'Nilai kosong bukan nilai nol. Pencetakan resmi tetap melalui web.',
    ),
  ];

  List<Widget> _ledgerContext(StsReportContext report) => [
    if (report.completeGradeScope == false) ...[
      const StsReportNotice(
        warning: true,
        message:
            'Cakupan tingkat terbatas '
            'pada kelas yang boleh Anda akses. Ini bukan ranking paralel seluruh tingkat.',
      ),
      const SizedBox(height: 10),
    ],
    if (report.temporaryRanking) ...[
      const StsReportNotice(
        warning: true,
        message:
            'Ranking masih sementara. '
            'Ada nilai belum lengkap atau masih draf; urutan dapat berubah setelah finalisasi.',
      ),
      const SizedBox(height: 10),
    ],
    Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            StsReportMetrics(
              values: [
                (label: 'Rata-rata', value: stsNumber(report.summary.average)),
                (label: 'Tertinggi', value: stsNumber(report.summary.highest)),
                (label: 'Terendah', value: stsNumber(report.summary.lowest)),
              ],
            ),
            const SizedBox(height: 10),
            Text(
              'Pembagi rata-rata: ${report.divisor} mapel terpilih. '
              '${report.summary.unrankedCount} siswa belum masuk ranking.',
              style: const TextStyle(fontSize: 11, height: 1.5),
            ),
          ],
        ),
      ),
    ),
    if (report.subjectStats.isNotEmpty) ...[
      const SizedBox(height: 10),
      Card(
        key: const Key('sts-reports-subject-stats'),
        child: ExpansionTile(
          key: const PageStorageKey('sts-reports-subject-stats-expanded'),
          title: const Text(
            'Statistik Mata Pelajaran',
            style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700),
          ),
          childrenPadding: const EdgeInsets.fromLTRB(14, 0, 14, 14),
          children: [
            for (final stat in report.subjectStats)
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(
                  stat.name,
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                subtitle: Text(
                  'Rata-rata ${stsNumber(stat.average)} · '
                  'Tertinggi ${stsNumber(stat.highest)} · Terendah ${stsNumber(stat.lowest)}\n'
                  '${stat.count} nilai · ${stat.finalCount} final · ${stat.draftCount} draf'
                  '${stat.highestAverage ? '\nRata-rata mapel tertinggi' : ''}',
                  style: const TextStyle(fontSize: 11, height: 1.5),
                ),
              ),
          ],
        ),
      ),
    ],
    if (report.classStats.isNotEmpty) ...[
      const SizedBox(height: 10),
      Card(
        key: const Key('sts-reports-class-stats'),
        child: ExpansionTile(
          key: const PageStorageKey('sts-reports-class-stats-expanded'),
          title: const Text(
            'Perbandingan Kelas',
            style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700),
          ),
          childrenPadding: const EdgeInsets.fromLTRB(14, 0, 14, 14),
          children: [
            for (final stat in report.classStats)
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(
                  '${stat.rank == null ? '—' : '#${stat.rank}'} · ${stat.name}',
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                subtitle: Text(
                  'Rata-rata ${stsNumber(stat.average)} · '
                  '${stat.rankedCount}/${stat.count} siswa masuk ranking',
                  style: const TextStyle(fontSize: 11, height: 1.5),
                ),
              ),
          ],
        ),
      ),
    ],
  ];
}

class StsReportStudentView extends StatelessWidget {
  const StsReportStudentView({
    required this.kind,
    required this.report,
    required this.student,
    super.key,
  });
  final StsReportKind kind;
  final StsReportContext report;
  final StsReportStudent student;

  @override
  Widget build(BuildContext context) {
    final ledger = kind == StsReportKind.ledger;
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: Text(
          ledger ? 'Nilai Leger STS' : 'Rincian Rapor STS',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
      ),
      body: SafeArea(
        top: false,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 6, 16, 28),
          children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      student.name,
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 17,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      '${student.className} · Absen ${student.attendanceNumber ?? '—'}',
                      style: const TextStyle(
                        fontSize: 12,
                        color: NusaColors.textSecondary,
                      ),
                    ),
                    const SizedBox(height: 10),
                    Text(
                      report.activityName,
                      style: const TextStyle(fontSize: 12, height: 1.5),
                    ),
                    const SizedBox(height: 14),
                    StsReportMetrics(
                      values: [
                        (label: 'Jumlah', value: stsNumber(student.total)),
                        (label: 'Rata-rata', value: stsNumber(student.average)),
                        (
                          label: ledger ? 'Ranking' : 'Nilai tersedia',
                          value: ledger
                              ? student.rank?.toString() ?? '—'
                              : '${student.gradeCount}/${report.subjects.length}',
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 12),
            if (ledger) ...[
              StsReportNotice(
                warning: report.temporaryRanking || student.rank == null,
                message:
                    '${student.rank != null && report.temporaryRanking ? 'Ranking sementara' : student.rankingStatus}. '
                    '${student.finalCount} nilai final · ${student.draftCount} draf. '
                    'Rata-rata memakai pembagi ${report.divisor} mapel terpilih.',
              ),
              if (report.completeGradeScope == false) ...[
                const SizedBox(height: 10),
                const StsReportNotice(
                  warning: true,
                  message:
                      'Ranking ini hanya mencakup kelas yang dapat Anda akses.',
                ),
              ],
            ] else ...[
              StsReportNotice(
                warning: !student.ready,
                message: student.ready
                    ? 'Siap cetak melalui web. Kelengkapan nilai dan lampiran perilaku '
                          'tetap perlu diperiksa terpisah.'
                    : 'Belum siap cetak. Periksa periode, wali kelas, dan kehadiran di web.',
              ),
              const SizedBox(height: 12),
              _attendance(),
            ],
            const SizedBox(height: 20),
            const Text(
              'Nilai Mata Pelajaran',
              style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 10),
            if (student.grades.isEmpty)
              const StsReportNotice(
                message: 'Belum ada mata pelajaran untuk ditampilkan.',
              ),
            for (final grade in student.grades) ...[
              StsReportGradeCard(
                grade: grade,
                subjectName: report.subjectName(grade.subjectId),
                kind: kind,
              ),
              const SizedBox(height: 9),
            ],
            const SizedBox(height: 8),
            const StsReportNotice(
              message:
                  'Ini tampilan ringkasan, bukan dokumen cetak resmi. '
                  'Kembali dan muat ulang halaman untuk melihat perubahan terbaru dari server.',
            ),
          ],
        ),
      ),
    );
  }

  Widget _attendance() => Card(
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Kehadiran & Lampiran',
            style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 12),
          StsReportMetrics(
            values: [
              (label: 'Sakit', value: '${student.sick}'),
              (label: 'Izin', value: '${student.excused}'),
              (label: 'Alfa', value: '${student.absent}'),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            'Terlambat ${student.lateCount} kali · ${student.lateMinutes} menit',
            style: const TextStyle(fontSize: 12, height: 1.5),
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 6,
            runSpacing: 6,
            children: [
              StsReportBadge(
                label: student.sourceChanged
                    ? 'Sumber kehadiran berubah'
                    : student.attendanceReviewed
                    ? 'Kehadiran diperiksa'
                    : 'Kehadiran belum diperiksa',
                warning: student.sourceChanged || !student.attendanceReviewed,
              ),
              StsReportBadge(
                label: student.behaviorReady
                    ? 'Lampiran perilaku siap'
                    : 'Lampiran perilaku belum siap',
                warning: !student.behaviorReady,
              ),
            ],
          ),
          if (student.attendanceCorrection?.isNotEmpty == true) ...[
            const SizedBox(height: 10),
            Text(
              'Catatan koreksi: ${student.attendanceCorrection}',
              style: const TextStyle(fontSize: 12, height: 1.5),
            ),
          ],
        ],
      ),
    ),
  );
}
